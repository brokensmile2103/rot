<?php

namespace Tests\Feature;

use App\Http\Middleware\EnsureInstalled;
use App\Models\Location;
use App\Models\PlatformSetting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\User as SocialiteUser;
use Tests\TestCase;

/**
 * Super Admin dùng được như tài khoản thường (có quán riêng, không bị ép về
 * /sieu-quan-tri) và việc đóng/mở đăng ký. Cần MySQL/MariaDB giống PosFlowTest.
 */
class PlatformAdminTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        $driver = $_SERVER['DB_CONNECTION'] ?? $_ENV['DB_CONNECTION'] ?? getenv('DB_CONNECTION');
        if (! in_array($driver, ['mysql', 'mariadb'], true)) {
            $this->markTestSkipped('Cần MySQL/MariaDB (migration dùng ALTER ... MODIFY ENUM).');
        }

        parent::setUp();
        $this->withoutMiddleware(EnsureInstalled::class);
    }

    private function admin(): User
    {
        return User::forceCreate(['name' => 'Admin', 'phone' => '0900000003', 'password' => bcrypt('secret123'), 'role' => 'platform_admin', 'is_active' => true]);
    }

    public function test_platform_admin_without_location_gets_one_on_login_and_lands_on_pos(): void
    {
        $admin = $this->admin();
        $this->assertSame(0, Location::count());

        $this->post('/login', ['phone' => $admin->phone, 'password' => 'secret123'])->assertRedirect(route('pos.order'));

        $location = Location::where('owner_id', $admin->id)->first();
        $this->assertNotNull($location);
        $this->assertSame($location->id, session('current_location_id'));
    }

    public function test_platform_admin_can_use_pos_owner_pages_and_admin_area_without_redirects(): void
    {
        $admin = $this->admin();
        $this->actingAs($admin)->get('/order')->assertOk();
        $this->get('/quan-ly/thuc-don')->assertOk();
        $this->get(route('admin.dashboard'))->assertOk();
        $this->get(route('admin.settings.edit'))->assertOk();
    }

    public function test_platform_admin_cannot_delete_own_account(): void
    {
        $admin = $this->admin();
        $this->actingAs($admin)->delete('/ho-so', ['delete_password' => 'secret123', 'confirmation' => 'XOA TAI KHOAN'])->assertForbidden();
        $this->assertNotNull($admin->fresh());
    }

    public function test_regular_owner_cannot_open_admin_area_or_settings(): void
    {
        $owner = User::forceCreate(['name' => 'Chủ', 'phone' => '0900000001', 'password' => bcrypt('x'), 'role' => 'owner', 'is_active' => true]);
        $this->actingAs($owner)->get(route('admin.settings.edit'))->assertForbidden();
        $this->actingAs($owner)->put(route('admin.settings.update'), ['registration_open' => '0'])->assertForbidden();
    }

    public function test_admin_can_close_and_reopen_registration(): void
    {
        $admin = $this->admin();
        $this->assertTrue(PlatformSetting::registrationOpen());

        $this->actingAs($admin)->put(route('admin.settings.update'), ['registration_open' => '0'])->assertRedirect();
        $this->assertFalse(PlatformSetting::registrationOpen());

        $this->put(route('admin.settings.update'), ['registration_open' => '1'])->assertRedirect();
        $this->assertTrue(PlatformSetting::registrationOpen());
    }

    public function test_closed_registration_blocks_register_pages_and_unknown_google_accounts(): void
    {
        PlatformSetting::put('registration_open', '0');

        $this->get('/dang-ky')->assertRedirect(route('login'));
        $this->post('/dang-ky', ['shop_name' => 'X', 'name' => 'Y', 'phone' => '0911111111', 'password' => 'secret123', 'password_confirmation' => 'secret123', 'terms' => '1'])
            ->assertRedirect(route('login'));
        $this->assertSame(0, User::count());

        config(['services.google.client_id' => 'x']);
        $google = (new SocialiteUser)->map(['id' => 'g-unknown', 'name' => 'Lạ', 'email' => 'la@example.com', 'avatar' => null]);
        Socialite::shouldReceive('driver->user')->andReturn($google);

        $this->get('/auth/google/callback')->assertRedirect(route('login'));
        $this->assertSame(0, User::count());
        $this->assertNull(session('google_pending'));
    }

    public function test_closed_registration_still_lets_existing_google_account_sign_in(): void
    {
        PlatformSetting::put('registration_open', '0');
        $admin = $this->admin();
        $admin->forceFill(['google_id' => 'g-admin'])->save();

        config(['services.google.client_id' => 'x']);
        $google = (new SocialiteUser)->map(['id' => 'g-admin', 'name' => 'Admin', 'email' => null, 'avatar' => null]);
        Socialite::shouldReceive('driver->user')->andReturn($google);

        $this->get('/auth/google/callback')->assertRedirect(route('pos.order'));
        $this->assertAuthenticatedAs($admin);
    }
}
