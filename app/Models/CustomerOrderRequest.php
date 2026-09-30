<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CustomerOrderRequest extends Model
{
    protected $fillable = [
        'location_id', 'items', 'customer_name', 'customer_phone', 'note', 'status', 'handled_by', 'handled_at',
    ];

    protected function casts(): array
    {
        return [
            'items' => 'array',
            'handled_at' => 'datetime',
        ];
    }

    public function location()
    {
        return $this->belongsTo(Location::class);
    }

    public function handledBy()
    {
        return $this->belongsTo(User::class, 'handled_by');
    }

    public function isPending(): bool
    {
        return $this->status === 'cho_xac_nhan';
    }

    /** Tổng số món (cộng dồn số lượng) — hiển thị nhanh ở danh sách, không cần load quan hệ nào thêm. */
    public function itemCount(): int
    {
        return collect($this->items)->sum('quantity');
    }
}
