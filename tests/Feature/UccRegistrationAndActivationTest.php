<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\UccBonusService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

class UccRegistrationAndActivationTest extends TestCase
{
    use RefreshDatabase;

    public function test_member_can_register_with_ktp_fields_and_bronze_activation(): void
    {
        $sponsor = User::factory()->create([
            'username' => 'sponsor1',
            'nik' => '3201010101010001',
            'member_tier' => 'bronze',
        ]);

        $response = $this->post('/register', [
            'nik' => '3201010101010002',
            'name' => 'Budi Santoso',
            'email' => 'budi@example.com',
            'phone' => '081234567890',
            'address' => 'Jl. Merdeka No. 10, Jakarta',
            'member_tier' => 'bronze',
            'is_left_handed' => 'Tidak',
            'beneficiary_name' => 'Siti Aminah',
            'beneficiary_birth_date' => '1990-01-01',
            'beneficiary_relation' => 'Istri',
            'emergency_phone' => '081298765432',
            'bank_name' => 'Bank BCA',
            'bank_account_number' => '1234567890',
            'bank_account_name' => 'Budi Santoso',
            'ktp_image' => UploadedFile::fake()->image('ktp.jpg'),
            'transfer_proof' => UploadedFile::fake()->image('transfer.jpg'),
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'referral' => 'sponsor1',
        ]);

        $response->assertRedirect(route('dashboard', absolute: false));
        $this->assertAuthenticated();

        $this->assertDatabaseHas('users', [
            'nik' => '3201010101010002',
            'name' => 'Budi Santoso',
            'email' => 'budi@example.com',
            'activation_status' => 'inactive',
            'activation_amount' => 0,
            'parent_id' => $sponsor->id,
        ]);

        // Free registration does NOT trigger bonus until PIN is activated
        $sponsor->refresh();
        $this->assertEquals(0, (float) $sponsor->total_bonus);
        $this->assertEquals(0, (int) $sponsor->points);
    }

    public function test_registration_requires_valid_16_digit_unique_nik(): void
    {
        User::factory()->create([
            'username' => 'sponsor2',
            'nik' => '3201010101019999',
        ]);

        $response = $this->post('/register', [
            'nik' => '12345', // invalid length
            'name' => 'Test User',
            'email' => 'test@example.com',
            'phone' => '081234567890',
            'member_tier' => 'bronze',
            'beneficiary_name' => 'Ahli Waris',
            'beneficiary_birth_date' => '1990-01-01',
            'beneficiary_relation' => 'Saudara',
            'emergency_phone' => '081298765432',
            'ktp_image' => UploadedFile::fake()->image('ktp.jpg'),
            'transfer_proof' => UploadedFile::fake()->image('transfer.jpg'),
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'referral' => 'sponsor2',
        ]);

        $response->assertSessionHasErrors(['nik']);
    }

    public function test_200_point_block_splits_into_four_saldo_posts_and_auto_upgrades_at_1400_points(): void
    {
        $sponsor = User::factory()->create([
            'username' => 'upline1',
            'nik' => '3201010101010010',
            'member_tier' => 'silver',
        ]);

        $bronzeMember = User::factory()->create([
            'username' => 'bronze_star',
            'nik' => '3201010101010011',
            'member_tier' => 'bronze',
            'parent_id' => $sponsor->id,
        ]);

        $service = app(UccBonusService::class);

        // Credit Rp 200.000 (200 points @ Rp 1.000/point):
        // - Rp 100.000 Post Mitra -> splits 40% tunai, 30% belanja, 20% upgrade, 10% reward
        // - Rp 100.000 Post Induk -> Lumbung Anggaran Kompleks (saldo_induk)
        $service->creditMemberIncome($bronzeMember, 200000, 'recruit_activation', 'Test 200 point split');
        $bronzeMember->refresh();

        $this->assertEquals(200, (int) $bronzeMember->points);
        $this->assertEquals(0, (int) $bronzeMember->pending_points);
        $this->assertEquals(40000, (float) $bronzeMember->saldo_tunai);
        $this->assertEquals(30000, (float) $bronzeMember->saldo_belanja);
        $this->assertEquals(20000, (float) $bronzeMember->saldo_upgrade);
        $this->assertEquals(10000, (float) $bronzeMember->saldo_reward);
        $this->assertEquals(100000, (float) $bronzeMember->saldo_induk);
        $this->assertEquals('bronze', $bronzeMember->member_tier);

        // Credit another Rp 1.200.000 (1200 points) -> total 1400 points (7 blocks) -> auto upgrade to Silver
        // 7 blocks produce saldo_upgrade = Rp 140.000 & saldo_induk = Rp 700.000
        // Upgrade cost Rp 350.000 uses Rp 140.000 saldo_upgrade + Rp 210.000 sniper cost from saldo_induk (leaving Rp 490.000)
        $service->creditMemberIncome($bronzeMember, 1200000, 'royalty_transaction', 'Reach 1400 points');
        $bronzeMember->refresh();
        $sponsor->refresh();

        $this->assertEquals('silver', $bronzeMember->member_tier);
        $this->assertTrue((bool) $bronzeMember->has_silver_visa_card);
        $this->assertNotNull($bronzeMember->upgraded_to_silver_at);
        $this->assertEquals(0, (float) $bronzeMember->saldo_upgrade);
        $this->assertEquals(490000, (float) $bronzeMember->saldo_induk);

        // Sponsor gets 10% upgrade bonus (Rp 35.000) + 3% silver cluster royalty (Rp 10.500) = Rp 45.500
        $this->assertEquals(45500, (float) $sponsor->total_bonus);
        $this->assertEquals(45, (int) $sponsor->points); // floor(45500 / 1000) = 45 points
    }

    public function test_transaction_profit_splits_50_50_and_distributes_40_bronze_30_silver(): void
    {
        $bronze = User::factory()->create(['member_tier' => 'bronze', 'nik' => '3201010101010020']);
        $silver = User::factory()->create(['member_tier' => 'silver', 'nik' => '3201010101010021']);

        $service = app(UccBonusService::class);
        $result = $service->processTransactionProfit(1000000, $bronze, 'TRX-TEST-1');

        $this->assertEquals(500000, $result['post_induk']);
        $this->assertEquals(500000, $result['post_mitra']);
        $this->assertEquals(200000, $result['bronze_each']); // 40% of 500k
        $this->assertEquals(150000, $result['silver_each']); // 30% of 500k

        $bronze->refresh();
        $silver->refresh();
        $this->assertEquals(200000, (float) $bronze->total_bonus);
        $this->assertEquals(150000, (float) $silver->total_bonus);
    }

    public function test_admin_member_activation_persists_all_14_standard_ktp_fields(): void
    {
        $admin = User::factory()->create([
            'username' => 'admin',
            'email' => 'admin@universalcardcentral.com',
            'nik' => '0000000000000001',
            'member_tier' => 'platinum',
        ]);

        $response = $this->actingAs($admin)->post(route('admin.activation.store'), [
            'phone' => '081234567899',
            'email' => 'ktpmember@example.com',
            'postal_code' => '60111',
            'member_tier' => 'silver',
            'nik' => '3578010101900001',
            'name' => 'Andi Wijaya',
            'birth_place' => 'Surabaya',
            'birth_date' => '1990-01-01',
            'gender' => 'Laki-laki',
            'blood_type' => 'O',
            'address' => 'Jl. Pemuda No. 12',
            'rt_rw' => '002/005',
            'village' => 'Embong Kaliasin',
            'district' => 'Genteng',
            'city' => 'Kota Surabaya',
            'province' => 'Jawa Timur',
            'religion' => 'Islam',
            'marital_status' => 'Kawin',
            'beneficiary_name' => 'Rina Wijaya',
            'beneficiary_birth_date' => '1992-05-10',
            'beneficiary_relation' => 'Istri',
            'emergency_phone' => '081298765431',
            'ktp_image' => UploadedFile::fake()->image('ktp.jpg'),
            'transfer_proof' => UploadedFile::fake()->image('transfer.jpg'),
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'sponsor_username' => 'admin',
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('users', [
            'nik' => '3578010101900001',
            'name' => 'Andi Wijaya',
            'birth_place' => 'Surabaya',
            'gender' => 'Laki-laki',
            'blood_type' => 'O',
            'address' => 'Jl. Pemuda No. 12',
            'rt_rw' => '002/005',
            'village' => 'Embong Kaliasin',
            'district' => 'Genteng',
            'city' => 'Kota Surabaya',
            'province' => 'Jawa Timur',
            'religion' => 'Islam',
            'marital_status' => 'Kawin',
            'postal_code' => '60111',
            'member_tier' => 'silver',
            'activation_amount' => 350000,
        ]);
    }

    public function test_lightweight_public_registration_without_ktp_documents_succeeds(): void
    {
        $sponsor = User::factory()->create([
            'username' => 'admin',
            'email' => 'sponsor-lite@universalcardcentral.com',
            'nik' => '0000000000000009',
            'member_tier' => 'bronze',
        ]);

        $response = $this->post('/register', [
            'name' => 'Member Ringkas',
            'phone' => '081234500011',
            'email' => 'ringkas@example.com',
            'member_tier' => 'bronze',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'referral' => $sponsor->username,
        ]);

        $this->assertAuthenticated();
        $response->assertRedirect(route('dashboard', absolute: false));

        $this->assertDatabaseHas('users', [
            'name' => 'Member Ringkas',
            'phone' => '081234500011',
            'email' => 'ringkas@example.com',
            'activation_status' => 'inactive',
            'activation_amount' => 0,
            'nik' => null,
        ]);
    }
}

