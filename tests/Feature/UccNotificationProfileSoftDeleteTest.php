<?php

namespace Tests\Feature;

use App\Models\Page;
use App\Models\Post;
use App\Models\Product;
use App\Models\User;
use App\Notifications\SystemActivityNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class UccNotificationProfileSoftDeleteTest extends TestCase
{
    use RefreshDatabase;

    public function test_database_notifications_read_read_all_and_soft_delete(): void
    {
        $adminRole = Role::firstOrCreate(['name' => 'admin']);
        $admin = User::factory()->create([
            'username' => 'admin',
            'email' => 'admin@universalcardcentral.com',
            'nik' => '0000000000000001',
        ]);
        $admin->assignRole($adminRole);

        $member = User::factory()->create([
            'username' => 'member01',
            'email' => 'member01@example.com',
            'nik' => '3201010101010099',
            'member_tier' => 'bronze',
        ]);

        SystemActivityNotification::sendSafe($member, new SystemActivityNotification(
            category: 'bonus',
            title: 'Bonus Masuk',
            message: 'Bonus Rp 10.000 masuk ke akun Anda.',
            link: '/admin/laporan?tab=bonus',
            roleTarget: 'member'
        ));

        SystemActivityNotification::notifyAdmins(
            category: 'network',
            title: 'Member Baru',
            message: 'Member01 baru saja bergabung.',
            link: '/admin/data-member'
        );

        $this->assertEquals(1, $member->unreadNotifications()->count());
        $this->assertEquals(1, $admin->unreadNotifications()->count());

        $memberNotif = $member->notifications()->first();
        $this->actingAs($member)
            ->post(route('admin.notifications.read', $memberNotif->id))
            ->assertRedirect();

        $this->assertEquals(0, $member->fresh()->unreadNotifications()->count());

        $this->actingAs($admin)
            ->post(route('admin.notifications.read-all'))
            ->assertRedirect();

        $this->assertEquals(0, $admin->fresh()->unreadNotifications()->count());

        $this->actingAs($member)
            ->delete(route('admin.notifications.destroy', $memberNotif->id))
            ->assertRedirect();

        $this->assertDatabaseHas('notifications', [
            'id' => $memberNotif->id,
        ]);
        $this->assertNotNull(\Illuminate\Support\Facades\DB::table('notifications')->where('id', $memberNotif->id)->value('deleted_at'));
    }

    public function test_member_can_complete_ktp_verification_and_password_in_panel_profile(): void
    {
        $member = User::factory()->create([
            'username' => 'memberlite',
            'email' => 'lite@example.com',
            'nik' => null,
            'member_tier' => 'bronze',
        ]);

        $response = $this->actingAs($member)->patch(route('profile.update'), [
            'name' => 'Member Lengkap',
            'username' => 'memberlite',
            'email' => 'lite@example.com',
            'phone' => '081234567890',
            'nik' => '3578010101900077',
            'gender' => 'Laki-laki',
            'blood_type' => 'AB',
            'rt_rw' => '003/007',
            'address' => 'Jl. Diponegoro No. 10',
            'postal_code' => '60241',
            'password' => 'newpassword123',
        ]);

        $response->assertRedirect(route('profile.edit'));

        $this->assertDatabaseHas('users', [
            'id' => $member->id,
            'nik' => '3578010101900077',
            'blood_type' => 'AB',
            'rt_rw' => '003/007',
            'postal_code' => '60241',
        ]);
    }

    public function test_soft_deletes_are_active_on_core_models(): void
    {
        $product = Product::create([
            'name' => 'Kartu NFC Gold',
            'slug' => 'kartu-nfc-gold',
            'price' => 150000,
            'stock' => 10,
            'is_active' => true,
        ]);

        $product->delete();
        $this->assertSoftDeleted('products', ['id' => $product->id]);

        $page = Page::create([
            'title' => 'Tentang UCC',
            'slug' => 'tentang-ucc',
            'content' => 'Konten',
            'is_published' => true,
        ]);

        $page->delete();
        $this->assertSoftDeleted('pages', ['id' => $page->id]);

        $user = User::factory()->create([
            'nik' => '3201010101010888',
        ]);
        $user->delete();
        $this->assertSoftDeleted('users', ['id' => $user->id]);
    }
}
