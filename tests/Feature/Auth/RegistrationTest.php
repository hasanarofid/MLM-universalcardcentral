<?php

namespace Tests\Feature\Auth;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RegistrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_registration_screen_can_be_rendered(): void
    {
        $response = $this->get('/register');

        $response->assertStatus(200);
    }

    public function test_new_users_can_register(): void
    {
        \App\Models\User::factory()->create([
            'username' => 'rootsponsor',
            'nik' => '3201010101010099',
        ]);

        $response = $this->post('/register', [
            'nik' => '3201010101010098',
            'name' => 'Test User',
            'email' => 'test@example.com',
            'phone' => '081234567890',
            'member_tier' => 'bronze',
            'beneficiary_name' => 'Ahli Waris',
            'beneficiary_birth_date' => '1990-01-01',
            'beneficiary_relation' => 'Saudara',
            'emergency_phone' => '081298765432',
            'ktp_image' => \Illuminate\Http\UploadedFile::fake()->image('ktp.jpg'),
            'transfer_proof' => \Illuminate\Http\UploadedFile::fake()->image('transfer.jpg'),
            'password' => 'password',
            'password_confirmation' => 'password',
            'referral' => 'rootsponsor',
        ]);

        $this->assertAuthenticated();
        $response->assertRedirect(route('dashboard', absolute: false));
    }
}
