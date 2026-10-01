<?php

namespace App\Services;

use App\Models\Ingredient;
use App\Models\Location;
use App\Models\ModifierRecipe;
use App\Models\OrderItem;
use App\Models\OrderItemModifier;
use App\Models\Recipe;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Dự báo tồn kho từ dữ liệu bán hàng thật:
 *
 * 1. "Còn bán được bao nhiêu" cho từng size món (variant) — tồn kho hiện tại chia cho
 *    định lượng công thức, lấy nguyên liệu "thắt cổ chai" (ít nhất). Hiện ở màn Order để
 *    nhân viên biết món sắp hết TRƯỚC khi nhận đơn, không phải đợi khách gọi mới biết.
 *    Chỉ tính công thức gốc của size (tuỳ chọn/topping tuỳ khách nên không tính vào).
 *
 * 2. Tốc độ tiêu thụ nguyên liệu (trung bình/ngày trong N ngày gần nhất) → "đủ dùng
 *    khoảng X ngày" và GỢI Ý NHẬP HÀNG đủ dùng cho số ngày mong muốn.
 *    Ưu tiên lượng ĐÃ TRỪ THẬT lưu ở order_items.stock_deductions; đơn cũ chưa có
 *    snapshot thì quy đổi theo công thức hiện tại.
 *
 * Phần tính toán thuần được tách thành hàm static để kiểm thử độc lập.
 */
class StockForecast
{
    public const CONSUMPTION_WINDOW_DAYS = 14;

    public const DEFAULT_COVER_DAYS = 7;

    /** Món còn bán được ≤ ngần này thì cảnh báo "sắp hết" trên màn Order. */
    public const LOW_SELLABLE_THRESHOLD = 5;

    /**
     * @return array<int, int> [variant_id => số phần còn bán được]; size không có công thức thì không có trong mảng.
     */
    public function sellableByVariant(Location $location): array
    {
        $recipes = Recipe::query()
            ->whereHas('variant.product.category', fn ($q) => $q->where('location_id', $location->id))
            ->whereHas('ingredient')
            ->with('ingredient:id,current_stock')
            ->get(['id', 'product_variant_id', 'ingredient_id', 'quantity']);

        $byVariant = [];
        foreach ($recipes as $recipe) {
            $byVariant[$recipe->product_variant_id][] = [
                'stock' => (float) $recipe->ingredient->current_stock,
                'per_unit' => (float) $recipe->quantity,
            ];
        }

        return array_map(fn (array $lines) => self::sellableCount($lines), $byVariant);
    }

    /**
     * @param  array<int, array{stock: float, per_unit: float}>  $recipeLines
     */
    public static function sellableCount(array $recipeLines): int
    {
        $min = null;
        foreach ($recipeLines as $line) {
            if ($line['per_unit'] <= 0) {
                continue;
            }
            // Cộng 1e-9 để tránh 0.3 / 0.1 = 2.9999999 bị làm tròn xuống thành 2.
            $count = (int) floor(max(0.0, $line['stock']) / $line['per_unit'] + 1e-9);
            $min = $min === null ? $count : min($min, $count);
        }

        return $min ?? 0;
    }

    /**
     * Lượng tiêu thụ trung bình/ngày của từng nguyên liệu trong $days ngày gần nhất
     * (không tính hôm nay vì chưa bán xong).
     *
     * @return array<int, float> [ingredient_id => lượng/ngày]
     */
    public function dailyConsumption(Location $location, int $days = self::CONSUMPTION_WINDOW_DAYS): array
    {
        $end = Carbon::today()->subDay()->endOfDay();
        $start = Carbon::today()->subDays($days)->startOfDay();

        $orderScope = fn ($q) => $q->where('location_id', $location->id)
            ->where('status', 'hoan_thanh')
            ->whereBetween('completed_at', [$start, $end]);

        $items = OrderItem::query()
            ->whereHas('order', $orderScope)
            ->get(['id', 'product_variant_id', 'quantity', 'stock_deductions']);

        $totals = [];
        $add = function (int $ingredientId, float $qty) use (&$totals) {
            $totals[$ingredientId] = ($totals[$ingredientId] ?? 0.0) + $qty;
        };

        // 1) Dòng đã có snapshot lượng trừ thật.
        foreach ($items->whereNotNull('stock_deductions') as $item) {
            foreach ($item->stock_deductions as $d) {
                $add((int) $d['i'], (float) $d['q']);
            }
        }

        // 2) Dòng cũ chưa có snapshot — quy đổi theo công thức hiện tại.
        $legacy = $items->whereNull('stock_deductions');
        if ($legacy->isNotEmpty()) {
            $qtyByVariant = $legacy->groupBy('product_variant_id')->map(fn ($g) => (float) $g->sum('quantity'));
            Recipe::whereIn('product_variant_id', $qtyByVariant->keys())->get()
                ->each(fn ($r) => $add($r->ingredient_id, (float) $r->quantity * $qtyByVariant[$r->product_variant_id]));

            $itemQty = $legacy->pluck('quantity', 'id');
            $modQty = OrderItemModifier::whereIn('order_item_id', $itemQty->keys())->get(['order_item_id', 'modifier_id'])
                ->groupBy('modifier_id')
                ->map(fn ($g) => (float) $g->sum(fn ($m) => $itemQty[$m->order_item_id]));
            ModifierRecipe::whereIn('modifier_id', $modQty->keys())->get()
                ->each(fn ($r) => $add($r->ingredient_id, (float) $r->quantity * $modQty[$r->modifier_id]));
        }

        return array_map(fn ($total) => $total / max(1, $days), $totals);
    }

    /**
     * Bảng dự báo cho từng nguyên liệu: tồn kho, tiêu thụ/ngày, đủ dùng bao nhiêu ngày,
     * gợi ý nhập bao nhiêu để đủ dùng $coverDays ngày, tiền ước tính theo giá vốn TB.
     */
    public function forecast(Location $location, int $coverDays = self::DEFAULT_COVER_DAYS): Collection
    {
        $daily = $this->dailyConsumption($location);

        return $location->ingredients()->orderBy('name')->get()->map(function (Ingredient $ingredient) use ($daily, $coverDays) {
            $plan = self::reorderPlan(
                (float) $ingredient->current_stock,
                $daily[$ingredient->id] ?? 0.0,
                (float) $ingredient->low_stock_threshold,
                $coverDays,
            );

            return $plan + [
                'ingredient' => $ingredient,
                'estimated_cost' => $plan['suggested_qty'] * (float) $ingredient->avg_cost_per_unit,
            ];
        });
    }

    /**
     * @return array{daily_usage: float, days_left: ?float, suggested_qty: float, urgent: bool}
     */
    public static function reorderPlan(float $stock, float $dailyUsage, float $lowThreshold, int $coverDays): array
    {
        $daysLeft = $dailyUsage > 0 ? max(0.0, $stock) / $dailyUsage : null;

        // Cần đủ dùng $coverDays ngày VÀ không thấp hơn ngưỡng cảnh báo chủ quán đã đặt.
        $target = max($dailyUsage * $coverDays, $lowThreshold);
        $suggested = max(0.0, $target - $stock);

        // Làm tròn LÊN tới 2 chữ số thập phân (đúng độ chính xác cột tồn kho) — gợi ý thiếu 0,004 là vô nghĩa.
        $suggested = ceil(round($suggested * 100, 6)) / 100;

        $urgent = $stock <= $lowThreshold || ($daysLeft !== null && $daysLeft < 2);

        return [
            'daily_usage' => $dailyUsage,
            'days_left' => $daysLeft,
            'suggested_qty' => $suggested,
            'urgent' => $urgent,
        ];
    }
}
