<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Order extends Model
{
    protected $fillable = [
        'location_id', 'customer_id', 'shift_id', 'order_type', 'guest_count', 'note', 'status',
        'payment_method', 'subtotal', 'discount_type', 'discount_value', 'discount_amount',
        'points_earned', 'points_redeemed', 'points_redeemed_value',
        'einvoice_status', 'einvoice_tracking_code', 'einvoice_reference_code', 'einvoice_pdf_url', 'einvoice_error',
        'total', 'created_by', 'completed_at', 'edited_at',
    ];

    protected function casts(): array
    {
        return [
            'subtotal' => 'decimal:2',
            'discount_value' => 'decimal:2',
            'discount_amount' => 'decimal:2',
            'total' => 'decimal:2',
            'completed_at' => 'datetime',
            'edited_at' => 'datetime',
        ];
    }

    public function isDraft(): bool
    {
        return $this->status === 'nhap';
    }

    public function location()
    {
        return $this->belongsTo(Location::class);
    }

    public function shift()
    {
        return $this->belongsTo(Shift::class);
    }

    public function items()
    {
        return $this->hasMany(OrderItem::class);
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }
}
