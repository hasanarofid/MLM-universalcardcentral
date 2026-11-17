<?php

namespace Tests\Feature;

use App\Models\PinRequest;
use App\Models\User;
use App\Models\Voucher;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class UccPinActivationFlowTest extends TestCase
{
    use RefreshDatabase;

    public function test_free_registration_then_buy_pin_admin_approve_and_self_activate(): void
    {
        $adminRole = Role::firstOrCreate(['name' => 'admin']);
        $admin = User::factory()->create([
            'username' => 'admin',
            'email' => 'admin@universalcardcentral.com',
            'nik' => '0000000000000001',
            'member_tier' => 'platinum',
            'activation_status' => 'active',
        ]);
        $admin->assignRole($adminRole);

        // 1. Free registration (inactive, zero bonus to sponsor)
        $this->post('/register', [
            'name' => 'Calon Member',
            'email' => 'calon@example.com',
            'phone' => '081234560001',
            'address' => 'Jl. Melati No. 12',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'referral' => 'admin',
        ])->assertRedirect(route('dashboard', absolute: false));

        $member = User::where('email', 'calon@example.com')->firstOrFail();
        $this->assertEquals('inactive', $member->activation_status);
        $this->assertEquals(0, (float) $admin->fresh()->total_bonus);

        // 2. Member uploads payment proof to order Silver PIN
        $this->actingAs($member)->post(route('admin.pin.request'), [
            'tier' => 'silver',
            'quantity' => 1,
            'transfer_proof' => UploadedFile::fake()->image('proof.jpg'),
        ])->assertRedirect();

        $pinRequest = PinRequest::where('user_id', $member->id)->firstOrFail();
        $this->assertEquals('pending', $pinRequest->status);
        $this->assertEquals(400000, (int) $pinRequest->total_amount);

        // 3. Admin approves -> generates UCCS- PIN for member
        $this->actingAs($admin)
            ->post(route('admin.pin.approve', $pinRequest->id))
            ->assertRedirect();

        $this->assertEquals('approved', $pinRequest->fresh()->status);
        $pin = Voucher::where('user_id', $member->id)->where('status', 'active')->firstOrFail();
        $this->assertStringStartsWith('UCCS-', $pin->code);

        // 4. Member inputs PIN to activate ID -> Silver (400k) + Cashback Saldo Belanja 50k + 10% sponsor bonus (40k) + 1% plat (4k)
        $this->actingAs($member)
            ->post(route('admin.pin.activate-self'), [
                'pin_code' => $pin->code,
            ])
            ->assertRedirect();

        $member->refresh();
        $admin->refresh();
        $this->assertEquals('active', $member->activation_status);
        $this->assertEquals('silver', $member->member_tier);
        $this->assertEquals(50000, (float) $member->saldo_belanja);
        $this->assertTrue((bool) $member->has_silver_visa_card);
        $this->assertContains($member->position, ['left', 'right']);
        $this->assertEquals('used', $pin->fresh()->status);
        $this->assertEquals(44000, (float) $admin->total_bonus); // 10% (40k) + 1% Platinum royalty (4k)
    }

    public function test_recruiter_can_register_and_activate_member_with_pin_stock_in_two_legs(): void
    {
        $recruiter = User::factory()->create([
            'username' => 'recruiter01',
            'email' => 'recruiter01@example.com',
            'nik' => '3201010101010101',
            'member_tier' => 'bronze',
            'activation_status' => 'active',
        ]);

        $pin = Voucher::create([
            'code' => 'UCCB-TEST0001',
            'user_id' => $recruiter->id,
            'package_name' => 'Bronze (Rp 100.000)',
            'voucher_type' => 'activation',
            'status' => 'active',
        ]);

        $this->actingAs($recruiter)->post(route('admin.activation.store'), [
            'name' => 'Mitra Kaki Kanan',
            'email' => 'kanan@example.com',
            'phone' => '081234560099',
            'address' => 'Jl. Anggrek No. 8',
            'pin_code' => $pin->code,
            'position' => 'right',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ])->assertRedirect();

        $newMember = User::where('email', 'kanan@example.com')->firstOrFail();
        $this->assertEquals('active', $newMember->activation_status);
        $this->assertEquals('bronze', $newMember->member_tier);
        $this->assertEquals('right', $newMember->position);
        $this->assertEquals('used', $pin->fresh()->status);
        $this->assertEquals(1, (int) $recruiter->fresh()->right_count);
        $this->assertEquals(14000, (float) $recruiter->fresh()->total_bonus); // 10% (10k) + 4% Bronze royalty (4k)
    }

    public function test_stokis_and_mobile_stokis_promo_requirements_pin_bundle_and_fees(): void
    {
        $adminRole = Role::firstOrCreate(['name' => 'admin']);
        $admin = User::factory()->create([
            'username' => 'admin',
            'email' => 'admin-stokis@universalcardcentral.com',
            'nik' => '0000000000000001',
            'member_tier' => 'platinum',
            'activation_status' => 'active',
        ]);
        $admin->assignRole($adminRole);

        $bronzeMember = User::factory()->create([
            'username' => 'bronzeguy',
            'email' => 'bronzeguy@example.com',
            'nik' => '3201010101010501',
            'member_tier' => 'bronze',
            'activation_status' => 'active',
        ]);

        // Bronze member cannot order Stokis / Mobile Stokis
        $this->actingAs($bronzeMember)->post(route('admin.pin.request'), [
            'tier' => 'stokis',
            'quantity' => 1,
            'transfer_proof' => UploadedFile::fake()->image('s.jpg'),
        ])->assertSessionHasErrors('tier');

        // Silver member orders Stokis (Rp 5.000.000 -> 50 PIN Bronze, Fee 3%)
        $stokisUser = User::factory()->create([
            'username' => 'stokis01',
            'email' => 'stokis01@example.com',
            'nik' => '3201010101010502',
            'member_tier' => 'silver',
            'activation_status' => 'active',
        ]);

        $this->actingAs($stokisUser)->post(route('admin.pin.request'), [
            'tier' => 'stokis',
            'quantity' => 1,
            'transfer_proof' => UploadedFile::fake()->image('stokis.jpg'),
        ])->assertRedirect();

        $stokisReq = PinRequest::where('user_id', $stokisUser->id)->firstOrFail();
        $this->assertEquals(5000000, (int) $stokisReq->total_amount);

        $this->actingAs($admin)->post(route('admin.pin.approve', $stokisReq->id))->assertRedirect();

        $stokisUser->refresh();
        $this->assertEquals('stokis', $stokisUser->stokis_type);
        $this->assertTrue((bool) $stokisUser->stokis_fee_eligible);
        $this->assertEquals(50, Voucher::where('user_id', $stokisUser->id)->where('status', 'active')->count());

        // Silver member orders Mobile Stokis (Rp 1.000.000 -> 1 PIN Silver + 6 PIN Bronze, Fee 2%)
        $miniUser = User::factory()->create([
            'username' => 'ministokis01',
            'email' => 'ministokis01@example.com',
            'nik' => '3201010101010503',
            'member_tier' => 'silver',
            'activation_status' => 'active',
        ]);

        $this->actingAs($miniUser)->post(route('admin.pin.request'), [
            'tier' => 'mini_stokis',
            'quantity' => 1,
            'transfer_proof' => UploadedFile::fake()->image('mini.jpg'),
        ])->assertRedirect();

        $miniReq = PinRequest::where('user_id', $miniUser->id)->firstOrFail();
        $this->assertEquals(1000000, (int) $miniReq->total_amount);

        $this->actingAs($admin)->post(route('admin.pin.approve', $miniReq->id))->assertRedirect();

        $miniUser->refresh();
        $this->assertEquals('mini_stokis', $miniUser->stokis_type);
        $this->assertTrue((bool) $miniUser->stokis_fee_eligible);
        $this->assertEquals(7, Voucher::where('user_id', $miniUser->id)->where('status', 'active')->count());

        // Stokis activates a new member with 1 Bronze PIN -> gets 10% sponsor (10k) + 3% Stokis fee (3k) = 13k
        $stokisPin = Voucher::where('user_id', $stokisUser->id)->where('status', 'active')->firstOrFail();
        $this->actingAs($stokisUser)->post(route('admin.activation.store'), [
            'name' => 'Member Stokis',
            'email' => 'memberstokis@example.com',
            'phone' => '081234560555',
            'address' => 'Jl. Stokis No. 1',
            'pin_code' => $stokisPin->code,
            'position' => 'left',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ])->assertRedirect();

        // 10% sponsor (10k) + 3% Silver cluster / 2 silver (1.5k) + 3% Fee Stokis (3k) = 14.500
        $this->assertEquals(14500, (float) $stokisUser->fresh()->total_bonus);
    }
}
