<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Ingredient extends Model
{
    use SoftDeletes;

    protected $fillable = ['location_id', 'name', 'unit', 'current_stock', 'avg_cost_per_unit', 'low_stock_threshold'];

    protected function casts(): array
    {
        return [
            'current_stock' => 'decimal:2',
            'avg_cost_per_unit' => 'decimal:4',
            'low_stock_threshold' => 'decimal:2',
        ];
    }

    public function location()
    {
        return $this->belongsTo(Location::class);
    }

    public function recipes()
    {
        return $this->hasMany(Recipe::class);
    }

    public function stockIns()
    {
        return $this->hasMany(StockIn::class);
    }

    /** Nhật ký các lần sửa TRỰC TIẾP tồn kho/giá vốn TB (v1.1.3) — khác với stockIns() là các lần nhập kho. */
    public function stockAdjustments()
    {
        return $this->hasMany(StockAdjustment::class);
    }

    public function isLowStock(): bool
    {
        return (float) $this->current_stock <= (float) $this->low_stock_threshold;
    }

    /**
     * Nhập thêm nguyên liệu, tự tính lại giá vốn trung bình (weighted average).
     *
     * Tồn kho có thể ÂM (bán vượt số sổ sách khi chưa kịp nhập hàng). Phần âm đó
     * đã được tính giá vốn lúc bán, nên KHÔNG được đưa vào bình quân: trước đây
     * (-100 × giá cũ + tiền lô mới) / (tồn mới) làm giá vốn TB bị đội lên sai. Giờ
     * chỉ phần tồn DƯƠNG mới tham gia bình quân; tồn ≤ 0 thì giá vốn = giá lô mới.
     */
    public function receiveStock(float $quantity, float $totalCost): void
    {
        $avg = self::weightedAverageCost((float) $this->current_stock, (float) $this->avg_cost_per_unit, $quantity, $totalCost);

        $this->update([
            'current_stock' => (float) $this->current_stock + $quantity,
            'avg_cost_per_unit' => $avg,
        ]);
    }

    /** Thuần (không đụng DB) để kiểm thử — xem receiveStock(). */
    public static function weightedAverageCost(float $stock, float $avgCost, float $quantity, float $totalCost): float
    {
        $positiveStock = max(0.0, $stock);
        $base = $positiveStock + $quantity;

        if ($base <= 0) {
            return $avgCost;
        }

        return ($positiveStock * $avgCost + $totalCost) / $base;
    }

    public function deductStock(float $quantity): void
    {
        $this->decrement('current_stock', $quantity);
    }

    /**
     * Hoàn lại kho khi sửa/huỷ đơn — CHỈ cộng lại số lượng, TUYỆT ĐỐI không
     * đụng vào avg_cost_per_unit. Khác với receiveStock() (dùng khi NHẬP kho
     * thật với giá mới) — ở đây là hoàn tác 1 lần trừ kho trước đó do bán
     * nhầm/sửa đơn, không phải giao dịch mua hàng nên không có giá mới để
     * tính lại bình quân gia quyền.
     */
    public function restoreStock(float $quantity): void
    {
        $this->increment('current_stock', $quantity);
    }
}
