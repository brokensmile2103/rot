<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class ModifierGroup extends Model
{
    use SoftDeletes;

    protected $fillable = ['location_id', 'name', 'is_required', 'allow_multiple'];

    protected function casts(): array
    {
        return ['is_required' => 'boolean', 'allow_multiple' => 'boolean'];
    }

    public function location()
    {
        return $this->belongsTo(Location::class);
    }

    public function modifiers()
    {
        return $this->hasMany(Modifier::class);
    }

    /** Các món đang áp dụng nhóm tuỳ chọn này (VD: món nào có "Lượng đường"). */
    public function products()
    {
        return $this->belongsToMany(Product::class, 'product_modifier_group');
    }
}
