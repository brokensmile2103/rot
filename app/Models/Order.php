<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Order extends Model
{
    protected $fillable = [
        'location_id', 'customer_id', 'shift_id', 'daily_number', 'order_type', 'guest_count', 'note', 'status',
        'prep_status', 'prepared_at', 'payment_method', 'cash_portion', 'subtotal', 'discount_type', 'discount_value', 'discount_amount',
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
            'cash_portion' => 'decimal:2',
            'completed_at' => 'datetime',
            'prepared_at' => 'datetime',
            'edited_at' => 'datetime',
        ];
    }

    public const PAYMENT_LABELS = [
        'tien_mat' => 'Tiền mặt',
        'chuyen_khoan' => 'Chuyển khoản',
        'vi_dien_tu' => 'Ví điện tử',
        'ket_hop' => 'Kết hợp (tiền mặt + chuyển khoản)',
    ];

    public const PREP_WAITING = 'cho_pha';

    public const PREP_DONE = 'da_xong';

    public function isDraft(): bool
    {
        return $this->status === 'nhap';
    }

    public function isCancelled(): bool
    {
        return $this->status === 'da_huy';
    }

    public function paymentLabel(): string
    {
        return self::PAYMENT_LABELS[$this->payment_method] ?? (string) $this->payment_method;
    }

    /** Phần tiền MẶT thực nhận của đơn — dùng cho Chốt ca/sổ quỹ. */
    public function cashAmount(): float
    {
        return match ($this->payment_method) {
            'tien_mat' => (float) $this->total,
            'ket_hop' => min((float) $this->cash_portion, (float) $this->total),
            default => 0.0,
        };
    }

    /** Phần tiền KHÔNG phải tiền mặt (chuyển khoản/ví) — đối soát với sao kê ngân hàng. */
    public function nonCashAmount(): float
    {
        return (float) $this->total - $this->cashAmount();
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
