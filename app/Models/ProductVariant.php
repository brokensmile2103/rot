<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class ProductVariant extends Model
{
    use SoftDeletes;

    protected $fillable = ['product_id', 'name', 'price', 'is_default'];

    protected function casts(): array
    {
        return ['is_default' => 'boolean', 'price' => 'decimal:2'];
    }

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    public function recipes()
    {
        return $this->hasMany(Recipe::class);
    }

    /**
     * Giá vốn ước tính từ công thức nguyên liệu — dùng để gợi ý giá bán.
     * KHÔNG tính topping/tuỳ chọn (những cái đó phụ thu riêng, không nằm
     * trong giá gốc của size). Dùng $recipes ĐÃ eager-load sẵn ở
     * MenuController::index() (`variants.recipes.ingredient`), không tự
     * query thêm để tránh N+1 khi hiện cả trang thực đơn.
     */
    public function estimatedCost(): float
    {
        return (float) $this->recipes->sum(
            fn ($r) => (float) $r->quantity * (float) ($r->ingredient?->avg_cost_per_unit ?? 0)
        );
    }
}
