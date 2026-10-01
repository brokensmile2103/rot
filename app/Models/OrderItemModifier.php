<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OrderItemModifier extends Model
{
    protected $fillable = ['order_item_id', 'modifier_id', 'extra_price'];

    protected function casts(): array
    {
        return ['extra_price' => 'decimal:2'];
    }

    public function orderItem()
    {
        return $this->belongsTo(OrderItem::class);
    }

    public function modifier()
    {
        // Tuỳ chọn đã xoá (mềm) vẫn hiện đúng tên trên hoá đơn/đơn cũ.
        return $this->belongsTo(Modifier::class)->withTrashed();
    }
}
