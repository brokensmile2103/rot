<?php

namespace Tests\Feature;

use App\Http\Middleware\EnsureInstalled;
use App\Models\Category;
use App\Models\Location;
use App\Models\Product;
use App\Models\Recipe;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * "Quét" MỌI trang GET với vai trò chủ quán, nhân viên và Super Admin để bắt lỗi 500
 * (view lỗi, biến chưa truyền, quan hệ thiếu...). Cần MySQL/MariaDB giống PosFlowTest.
 */
class PageCrawlTest extends TestCase
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

    public function test_every_get_page_renders_for_each_role(): void
    {
        $owner = User::forceCreate(['name' => 'Chủ quán', 'phone' => '0900000001', 'password' => bcrypt('secret'), 'role' => 'owner', 'is_active' => true]);
        $staff = User::forceCreate(['name' => 'Nhân viên', 'phone' => '0900000002', 'password' => bcrypt('secret'), 'role' => 'staff', 'is_active' => true]);
        $admin = User::forceCreate(['name' => 'Admin', 'phone' => '0900000003', 'password' => bcrypt('secret'), 'role' => 'platform_admin', 'is_active' => true]);

        $location = new Location(['name' => 'Xe test']);
        $location->forceFill(['owner_id' => $owner->id, 'is_active' => true])->save();
        $location->staff()->attach($staff->id);

        $coffee = $location->ingredients()->create(['name' => 'Cà phê', 'unit' => 'g', 'current_stock' => 1000, 'avg_cost_per_unit' => 300]);
        $category = Category::create(['location_id' => $location->id, 'name' => 'Cà phê', 'sort_order' => 1]);
        $product = Product::create(['category_id' => $category->id, 'name' => 'Cà phê sữa', 'is_available' => true, 'sort_order' => 1]);
        $variant = $product->variants()->create(['name' => 'M', 'price' => 25000, 'is_default' => true]);
        Recipe::create(['product_variant_id' => $variant->id, 'ingredient_id' => $coffee->id, 'quantity' => 20]);
        $shift = $location->shifts()->create(['user_id' => $owner->id, 'opening_cash' => 100000, 'opened_at' => now()]);

        $orderId = $this->actingAs($owner)->withSession(['current_location_id' => $location->id])->postJson('/order/checkout', [
            'order_type' => 'mang_di', 'payment_method' => 'tien_mat',
            'items' => [['variant_id' => $variant->id, 'quantity' => 2, 'modifier_ids' => []]],
        ])->assertOk()->json('order_id');

        $params = [
            'ingredient' => $coffee->id, 'location' => $location->id, 'order' => $orderId, 'shift' => $shift->id,
            'slug' => 'gioi-thieu', 'token' => $location->fresh()->public_token,
        ];

        $checked = 0;
        foreach (Route::getRoutes() as $route) {
            $uri = $route->uri();
            if (! in_array('GET', $route->methods(), true) || str_starts_with($uri, 'install') || str_contains($uri, 'auth/google')
                || str_starts_with($uri, 'storage') || str_contains($uri, 'dat-lai-mat-khau') || array_diff($route->parameterNames(), array_keys($params))) {
                continue;
            }

            $url = url(strtr($uri, collect($params)->mapWithKeys(fn ($v, $k) => ['{'.$k.'}' => $v])->all()));

            foreach (['owner' => $owner, 'staff' => $staff, 'admin' => $admin] as $role => $user) {
                $res = $this->actingAs($user)->withSession(['current_location_id' => $location->id])->get($url);
                $this->assertLessThan(500, $res->getStatusCode(), "GET {$url} as {$role} → {$res->getStatusCode()}\n".substr((string) $res->getContent(), 0, 1500));
                $checked++;
            }
        }

        $this->assertGreaterThan(40, $checked);
    }

    public function test_remembered_login_without_location_in_session_self_heals(): void
    {
        $owner = User::forceCreate(['name' => 'Chủ quán', 'phone' => '0900000001', 'password' => bcrypt('secret'), 'role' => 'owner', 'is_active' => true]);
        $location = new Location(['name' => 'Xe test']);
        $location->forceFill(['owner_id' => $owner->id, 'is_active' => true])->save();

        // Phiên khôi phục từ cookie "Ghi nhớ đăng nhập": đã xác thực nhưng session chưa có current_location_id.
        $this->assertLessThan(400, $this->actingAs($owner)->get('/order')->getStatusCode());
        $this->assertSame($location->id, session('current_location_id'));
    }

    public function test_locked_user_loses_access_immediately(): void
    {
        $owner = User::forceCreate(['name' => 'Chủ quán', 'phone' => '0900000001', 'password' => bcrypt('secret'), 'role' => 'owner', 'is_active' => true]);
        $location = new Location(['name' => 'Xe test']);
        $location->forceFill(['owner_id' => $owner->id, 'is_active' => true])->save();
        $staff = User::forceCreate(['name' => 'NV', 'phone' => '0900000002', 'password' => bcrypt('secret'), 'role' => 'staff', 'is_active' => false]);
        $location->staff()->attach($staff->id);

        $this->actingAs($staff)->withSession(['current_location_id' => $location->id])->get('/order')->assertRedirect(route('login'));
        $this->assertGuest();
    }
}
