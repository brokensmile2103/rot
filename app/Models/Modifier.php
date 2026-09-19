<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Modifier extends Model
{
    use SoftDeletes;

    protected $fillable = ['modifier_group_id', 'name', 'extra_price', 'is_default'];

    protected function casts(): array
    {
        return ['extra_price' => 'decimal:2', 'is_default' => 'boolean'];
    }

    public function group()
    {
        return $this->belongsTo(ModifierGroup::class, 'modifier_group_id');
    }

    /** Nguyên liệu bị trừ kho khi khách chọn tuỳ chọn này (VD: chọn "70% đường" trừ bao nhiêu ml syrup). */
    public function modifierRecipes()
    {
        return $this->hasMany(ModifierRecipe::class);
    }
}
