<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Product extends Model
{
    use SoftDeletes;

    protected $fillable = ['category_id', 'name', 'image_url', 'is_available', 'sort_order'];

    protected function casts(): array
    {
        return ['is_available' => 'boolean'];
    }

    public function category()
    {
        return $this->belongsTo(Category::class);
    }

    public function variants()
    {
        return $this->hasMany(ProductVariant::class);
    }

    public function modifierGroups()
    {
        return $this->belongsToMany(ModifierGroup::class, 'product_modifier_group');
    }

    public function defaultVariant()
    {
        return $this->variants()->where('is_default', true)->first()
            ?? $this->variants()->first();
    }

    /**
     * Phát hiện nguyên liệu bị tính TRÙNG LẶP giữa công thức gốc của 1 size
     * và tuỳ chọn (VD: "Lượng đường") đang gắn cho món này — lỗi logic RẤT
     * dễ mắc: đặt "Đường 8g" trong công thức gốc, RỒI CŨNG đặt "Đường 4g" cho
     * tuỳ chọn "50%", khiến hệ thống trừ kho CẢ HAI (12g) khi bán, dù về mặt
     * kỹ thuật đây là hành vi đúng thiết kế (cộng dồn mọi công thức áp dụng).
     * Cần dùng $variants/$modifierGroups ĐÃ eager-load sẵn (xem
     * MenuController::index()) để không phát sinh query thêm.
     *
     * @return array<int, array{variant: string, ingredient: string}>
     */
    public function ingredientConflicts(): array
    {
        $conflicts = [];

        $modifierIngredientIds = $this->modifierGroups
            ->flatMap(fn ($group) => $group->modifiers)
            ->flatMap(fn ($modifier) => $modifier->modifierRecipes)
            ->pluck('ingredient_id')
            ->unique();

        if ($modifierIngredientIds->isEmpty()) {
            return [];
        }

        foreach ($this->variants as $variant) {
            foreach ($variant->recipes as $recipe) {
                if ($modifierIngredientIds->contains($recipe->ingredient_id)) {
                    $conflicts[] = [
                        'variant' => $variant->name,
                        'ingredient' => $recipe->ingredient?->name ?? '(đã xoá)',
                    ];
                }
            }
        }

        return $conflicts;
    }
}
