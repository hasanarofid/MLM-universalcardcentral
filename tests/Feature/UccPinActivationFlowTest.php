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
        $this->assertEquals(350000, (int) $pinRequest->total_amount);

        // 3. Admin approves -> generates UCCS- PIN for member
        $this->actingAs($admin)
            ->post(route('admin.pin.approve', $pinRequest->id))
            ->assertRedirect();

        $this->assertEquals('approved', $pinRequest->fresh()->status);
        $pin = Voucher::where('user_id', $member->id)->where('status', 'active')->firstOrFail();
        $this->assertStringStartsWith('UCCS-', $pin->code);

        // 4. Member inputs PIN to activate ID -> Silver + 10% sponsor bonus (Rp 35.000)
        $this->actingAs($member)
            ->post(route('admin.pin.activate-self'), [
                'pin_code' => $pin->code,
            ])
            ->assertRedirect();

        $member->refresh();
        $admin->refresh();
        $this->assertEquals('active', $member->activation_status);
        $this->assertEquals('silver', $member->member_tier);
        $this->assertTrue((bool) $member->has_silver_visa_card);
        $this->assertContains($member->position, ['left', 'right']);
        $this->assertEquals('used', $pin->fresh()->status);
        $this->assertEquals(38500, (float) $admin->total_bonus); // 10% (35k) + 1% Platinum royalty (3.5k)
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
}
