<?php

namespace Tests\Unit;

use App\Http\Controllers\Owner\ReportController;
use App\Http\Controllers\Pos\OrderController;
use App\Models\Ingredient;
use App\Models\Order;
use App\Services\StockForecast;
use Illuminate\Support\Carbon;
use PHPUnit\Framework\TestCase;

class StockAndPaymentMathTest extends TestCase
{
    public function test_sellable_count_uses_bottleneck_ingredient(): void
    {
        $this->assertSame(4, StockForecast::sellableCount([
            ['stock' => 100, 'per_unit' => 20],  // 5 ly
            ['stock' => 130, 'per_unit' => 30],  // 4 ly ← thắt cổ chai
        ]));
    }

    public function test_sellable_count_handles_float_noise_and_negative_stock(): void
    {
        $this->assertSame(3, StockForecast::sellableCount([['stock' => 0.3, 'per_unit' => 0.1]]));
        $this->assertSame(0, StockForecast::sellableCount([['stock' => -50, 'per_unit' => 10]]));
        $this->assertSame(0, StockForecast::sellableCount([]));
    }

    public function test_reorder_plan_covers_target_days_and_threshold(): void
    {
        $plan = StockForecast::reorderPlan(stock: 300, dailyUsage: 100, lowThreshold: 0, coverDays: 7);
        $this->assertSame(3.0, $plan['days_left']);
        $this->assertSame(400.0, $plan['suggested_qty']);
        $this->assertFalse($plan['urgent']);

        // Không bán gì nhưng tồn dưới ngưỡng cảnh báo → vẫn gợi ý nhập đủ ngưỡng.
        $plan = StockForecast::reorderPlan(stock: 50, dailyUsage: 0, lowThreshold: 200, coverDays: 7);
        $this->assertNull($plan['days_left']);
        $this->assertSame(150.0, $plan['suggested_qty']);
        $this->assertTrue($plan['urgent']);

        // Còn đủ dùng → không gợi ý.
        $this->assertSame(0.0, StockForecast::reorderPlan(5000, 100, 0, 7)['suggested_qty']);
    }

    public function test_weighted_average_ignores_negative_stock(): void
    {
        // Bình thường: 100 × 10 + 1000 = 2000 / 200 = 10
        $this->assertEqualsWithDelta(10.0, Ingredient::weightedAverageCost(100, 10, 100, 1000), 1e-9);

        // Tồn âm -100 (bán vượt sổ sách) rồi nhập lô 1000 đơn vị giá 20/đv: giá vốn phải là 20,
        // trước đây ra (−1000 + 20000) / 900 = 21,11 (sai).
        $this->assertEqualsWithDelta(20.0, Ingredient::weightedAverageCost(-100, 10, 1000, 20000), 1e-9);
    }

    public function test_month_fraction_for_day_week_and_cross_month_week(): void
    {
        $day = Carbon::parse('2026-10-01');
        $this->assertEqualsWithDelta(1 / 31, ReportController::monthFraction($day->copy()->startOfDay(), $day->copy()->endOfDay()), 1e-12);

        // Tuần 28/09 – 04/10/2026: 3 ngày tháng 9 (30 ngày) + 4 ngày tháng 10 (31 ngày).
        $start = Carbon::parse('2026-09-28')->startOfDay();
        $end = Carbon::parse('2026-10-04')->endOfDay();
        $this->assertEqualsWithDelta(3 / 30 + 4 / 31, ReportController::monthFraction($start, $end), 1e-12);
    }

    public function test_resolve_payment_normalises_split_payment(): void
    {
        $this->assertSame(['tien_mat', null], OrderController::resolvePayment('tien_mat', 999, 50000));
        $this->assertSame(['ket_hop', 20000.0], OrderController::resolvePayment('ket_hop', 20000, 50000));
        $this->assertSame(['tien_mat', null], OrderController::resolvePayment('ket_hop', 60000, 50000));
        $this->assertSame(['chuyen_khoan', null], OrderController::resolvePayment('ket_hop', 0, 50000));
    }

    public function test_normalize_phone(): void
    {
        $this->assertSame('0901234567', OrderController::normalizePhone(' 0901 234.567 '));
        $this->assertSame('+84901234567', OrderController::normalizePhone('+84 901-234-567'));
        $this->assertNull(OrderController::normalizePhone('  '));
        $this->assertNull(OrderController::normalizePhone(null));
    }

    public function test_order_cash_and_non_cash_amounts(): void
    {
        $split = new Order(['payment_method' => 'ket_hop', 'total' => 50000, 'cash_portion' => 20000]);
        $this->assertSame(20000.0, $split->cashAmount());
        $this->assertSame(30000.0, $split->nonCashAmount());

        $cash = new Order(['payment_method' => 'tien_mat', 'total' => 35000]);
        $this->assertSame(35000.0, $cash->cashAmount());
        $this->assertSame(0.0, $cash->nonCashAmount());

        $transfer = new Order(['payment_method' => 'chuyen_khoan', 'total' => 35000]);
        $this->assertSame(0.0, $transfer->cashAmount());
    }
}
