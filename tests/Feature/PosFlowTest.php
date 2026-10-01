<?php

namespace Tests\Feature;

use App\Http\Middleware\EnsureInstalled;
use App\Models\Category;
use App\Models\Customer;
use App\Models\Ingredient;
use App\Models\Location;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Recipe;
use App\Models\Shift;
use App\Models\StockAdjustment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Luồng bán hàng end-to-end. Migration của Rót dùng câu lệnh riêng của MySQL (ALTER ...
 * MODIFY ENUM) nên bộ test này CHỈ chạy với MySQL/MariaDB, VD:
 *
 *   DB_CONNECTION=mysql DB_DATABASE=rot_test DB_USERNAME=... DB_PASSWORD=... php artisan test
 *
 * Với cấu hình mặc định (sqlite) các test này tự bỏ qua.
 */
class PosFlowTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    private Location $location;

    private Ingredient $coffee;

    private Ingredient $milk;

    private ProductVariant $variant;

    protected function setUp(): void
    {
        // Kiểm tra TRƯỚC parent::setUp() — RefreshDatabase chạy migrate ngay trong đó.
        $driver = $_SERVER['DB_CONNECTION'] ?? $_ENV['DB_CONNECTION'] ?? getenv('DB_CONNECTION');
        if (! in_array($driver, ['mysql', 'mariadb'], true)) {
            $this->markTestSkipped('Cần MySQL/MariaDB (migration dùng ALTER ... MODIFY ENUM).');
        }

        parent::setUp();

        $this->withoutMiddleware(EnsureInstalled::class);

        $this->owner = User::forceCreate([
            'name' => 'Chủ quán', 'phone' => '0900000001', 'password' => bcrypt('secret'),
            'role' => 'owner', 'is_active' => true,
        ]);

        $this->location = new Location(['name' => 'Xe test', 'loyalty_enabled' => true, 'points_earn_rate' => 10000, 'points_redeem_value' => 1000]);
        $this->location->forceFill(['owner_id' => $this->owner->id, 'is_active' => true])->save();

        $this->coffee = $this->location->ingredients()->create(['name' => 'Cà phê', 'unit' => 'g', 'current_stock' => 1000, 'avg_cost_per_unit' => 300]);
        $this->milk = $this->location->ingredients()->create(['name' => 'Sữa', 'unit' => 'ml', 'current_stock' => 500, 'avg_cost_per_unit' => 50]);

        $category = Category::create(['location_id' => $this->location->id, 'name' => 'Cà phê', 'sort_order' => 1]);
        $product = Product::create(['category_id' => $category->id, 'name' => 'Cà phê sữa', 'is_available' => true, 'sort_order' => 1]);
        $this->variant = $product->variants()->create(['name' => 'M', 'price' => 25000, 'is_default' => true]);
        Recipe::create(['product_variant_id' => $this->variant->id, 'ingredient_id' => $this->coffee->id, 'quantity' => 20]);
        Recipe::create(['product_variant_id' => $this->variant->id, 'ingredient_id' => $this->milk->id, 'quantity' => 30]);

        $this->location->shifts()->create(['user_id' => $this->owner->id, 'opening_cash' => 100000, 'opened_at' => now()]);
    }

    private function actor()
    {
        return $this->actingAs($this->owner)->withSession(['current_location_id' => $this->location->id]);
    }

    private function checkout(array $overrides = [], int $qty = 1)
    {
        return $this->actor()->postJson('/order/checkout', array_merge([
            'order_type' => 'mang_di',
            'payment_method' => 'tien_mat',
            'items' => [['variant_id' => $this->variant->id, 'quantity' => $qty, 'modifier_ids' => []]],
        ], $overrides));
    }

    public function test_checkout_deducts_stock_snapshots_deductions_and_numbers_orders(): void
    {
        $first = $this->checkout(qty: 2)->assertOk()->json();
        $second = $this->checkout()->assertOk()->json();

        $this->assertSame(1, $first['daily_number']);
        $this->assertSame(2, $second['daily_number']);
        $this->assertEqualsWithDelta(1000 - 60, (float) $this->coffee->fresh()->current_stock, 0.001);
        $this->assertEqualsWithDelta(500 - 90, (float) $this->milk->fresh()->current_stock, 0.001);

        $item = Order::find($first['order_id'])->items()->first();
        $this->assertEqualsCanonicalizing(
            [['i' => $this->coffee->id, 'q' => 40], ['i' => $this->milk->id, 'q' => 60]],
            $item->stock_deductions,
        );
        $this->assertEqualsWithDelta(2 * (20 * 300 + 30 * 50), (float) $item->total_cost, 0.01);
    }

    public function test_cancel_restores_what_was_actually_deducted_even_after_recipe_change(): void
    {
        $orderId = $this->checkout()->json('order_id');

        // Đổi công thức SAU khi bán: hoàn kho phải theo lượng đã trừ (20g), không phải 50g.
        Recipe::where('ingredient_id', $this->coffee->id)->update(['quantity' => 50]);

        $this->actor()->post("/don-hang/{$orderId}/huy")->assertRedirect();
        $this->assertEqualsWithDelta(1000, (float) $this->coffee->fresh()->current_stock, 0.001);
    }

    public function test_cancelling_twice_does_not_restore_stock_twice(): void
    {
        $orderId = $this->checkout()->json('order_id');

        $this->actor()->post("/don-hang/{$orderId}/huy")->assertRedirect();
        $this->actor()->post("/don-hang/{$orderId}/huy")->assertStatus(422);
        $this->actor()->putJson("/don-hang/{$orderId}", [
            'order_type' => 'mang_di', 'payment_method' => 'tien_mat',
            'items' => [['variant_id' => $this->variant->id, 'quantity' => 1, 'modifier_ids' => []]],
        ])->assertStatus(422);

        $this->assertEqualsWithDelta(1000, (float) $this->coffee->fresh()->current_stock, 0.001);
    }

    public function test_split_payment_counts_only_cash_part_in_shift(): void
    {
        $this->checkout(['payment_method' => 'ket_hop', 'cash_portion' => 10000])->assertOk();
        $this->checkout(['payment_method' => 'chuyen_khoan'])->assertOk();

        $shift = Shift::first();
        $this->assertEqualsWithDelta(110000, $shift->expectedCash(), 0.001);

        $summary = $shift->salesSummary();
        $this->assertEqualsWithDelta(10000, $summary['cash'], 0.001);
        $this->assertEqualsWithDelta(40000, $summary['transfer'], 0.001);
        $this->assertEqualsWithDelta(50000, $summary['revenue'], 0.001);
    }

    public function test_loyalty_normalises_phone_and_restores_soft_deleted_customer(): void
    {
        $customer = $this->location->customers()->create(['phone' => '0901234567', 'name' => 'An', 'points' => 7]);
        $customer->delete();

        $this->checkout(['customer_phone' => '0901 234 567'])->assertOk();

        $this->assertSame(1, Customer::withTrashed()->count());
        $customer = Customer::first();
        $this->assertNotNull($customer, 'Khách đã xoá mềm phải được khôi phục, không lỗi trùng SĐT.');
        $this->assertSame(7 + 2, $customer->points); // 25.000đ / 10.000đ = 2 điểm
    }

    public function test_closing_shift_cancels_drafts_and_rejects_double_open(): void
    {
        $this->actor()->postJson('/order/luu-nhap', [
            'items' => [['variant_id' => $this->variant->id, 'quantity' => 1, 'modifier_ids' => []]],
        ])->assertOk();

        $this->actor()->post('/ca/dong', ['closing_cash_actual' => 100000])->assertRedirect();

        $this->assertSame(0, Order::where('status', 'nhap')->count());
        $this->assertSame(1, Order::where('status', 'da_huy')->count());

        $this->actor()->post('/ca/mo', ['opening_cash' => 0])->assertRedirect('/order');
        $this->actor()->post('/ca/mo', ['opening_cash' => 0])->assertRedirect('/order');
        $this->assertSame(1, Shift::whereNull('closed_at')->count());
    }

    public function test_stocktake_adjusts_counted_items_and_keeps_sales_made_while_counting(): void
    {
        $snapshot = (float) $this->coffee->current_stock; // 1000

        $this->checkout(); // bán 20g TRONG LÚC đang đếm

        $this->actor()->post('/quan-ly/kho/kiem-ke', [
            'counts' => [$this->coffee->id => 990, $this->milk->id => ''],
            'snapshot' => [$this->coffee->id => $snapshot],
        ])->assertRedirect();

        // Đếm 990 so với sổ lúc mở trang 1000 → hao hụt 10g; 20g bán sau đó vẫn được trừ.
        $this->assertEqualsWithDelta(970, (float) $this->coffee->fresh()->current_stock, 0.001);
        $adjustment = StockAdjustment::sole();
        $this->assertSame('kiem_ke', $adjustment->reason);
        $this->assertEqualsWithDelta(980, (float) $adjustment->stock_before, 0.001);
        $this->assertEqualsWithDelta(970, (float) $adjustment->stock_after, 0.001);
    }

    public function test_prep_queue_flow(): void
    {
        $this->location->update(['prep_queue_enabled' => true]);
        $orderId = $this->checkout()->json('order_id');

        $this->actor()->getJson('/pha-che/du-lieu')->assertOk()->assertJsonPath('waiting.0.id', $orderId);
        $this->actor()->postJson("/pha-che/{$orderId}/xong")->assertJsonPath('ok', true);
        $this->actor()->postJson("/pha-che/{$orderId}/xong")->assertJsonPath('ok', false);
        $this->actor()->getJson('/pha-che/du-lieu')->assertJsonCount(0, 'waiting')->assertJsonPath('done.0.id', $orderId);
    }

    public function test_reports_receipt_and_export_survive_deleted_product(): void
    {
        $orderId = $this->checkout()->json('order_id');
        $this->variant->product->delete();

        $this->actor()->get('/quan-ly/bao-cao')->assertOk()->assertSee('Cà phê sữa');
        $this->actor()->get("/don-hang/{$orderId}/in")->assertOk()->assertSee('Cà phê sữa');
        $this->actor()->get('/quan-ly/bao-cao/xuat-csv')->assertOk();
        $this->actor()->get('/quan-ly/cai-dat/xuat-du-lieu')->assertOk();

        // Không bán được món đã xoá.
        $this->checkout()->assertNotFound();
    }

    public function test_day_report_prorates_rent_once_per_day(): void
    {
        $this->location->update(['rent_cost' => 3100000]);

        $html = $this->actor()->get('/quan-ly/bao-cao?period=day&date=2026-10-01')->assertOk()->getContent();

        // 3.100.000đ / 31 ngày = 100.000đ — trước đây bị tính gấp đôi (200.000đ).
        $this->assertStringContainsString('100.000đ', $html);
        $this->assertStringNotContainsString('200.000đ', $html);
    }

    public function test_pos_and_inventory_pages_render(): void
    {
        $this->checkout();

        $this->actor()->get('/order')->assertOk();
        $this->actor()->get('/don-hang')->assertOk()->assertSee('Số 1');
        $this->actor()->get('/ca/dong')->assertOk()->assertSee('Tổng kết ca');
        $this->actor()->get('/pha-che')->assertOk();
        $this->actor()->get('/quan-ly/kho')->assertOk();
        $this->actor()->get('/quan-ly/kho/kiem-ke')->assertOk();
        $this->actor()->get('/quan-ly/kho/goi-y-nhap-hang')->assertOk()->assertSee('Cà phê');
        $this->actor()->get('/quan-ly/cai-dat')->assertOk()->assertSee('Hàng chờ pha chế');
        $this->actor()->get('/quan-ly/bao-cao?date=khong-hop-le')->assertOk();
        $this->actor()->get('/quan-ly/nhan-vien/bang-cong?month=abc')->assertOk();
    }
}
