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
     */
    public function receiveStock(float $quantity, float $totalCost): void
    {
        $currentValue = (float) $this->current_stock * (float) $this->avg_cost_per_unit;
        $newStock = (float) $this->current_stock + $quantity;
        $newAvgCost = $newStock > 0 ? ($currentValue + $totalCost) / $newStock : 0;

        $this->update([
            'current_stock' => $newStock,
            'avg_cost_per_unit' => $newAvgCost,
        ]);
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
