<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Shift extends Model
{
    protected $fillable = [
        'location_id', 'user_id', 'opening_cash', 'closing_cash_expected',
        'closing_cash_actual', 'variance', 'opened_at', 'closed_at',
    ];

    protected function casts(): array
    {
        return [
            'opened_at' => 'datetime',
            'closed_at' => 'datetime',
        ];
    }

    public function location()
    {
        return $this->belongsTo(Location::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function orders()
    {
        return $this->hasMany(Order::class);
    }

    public function cashAdjustments()
    {
        return $this->hasMany(CashAdjustment::class);
    }

    public function isOpen(): bool
    {
        return is_null($this->closed_at);
    }

    /** Tổng tiền mặt lý thuyết đang có trong quỹ tại thời điểm hiện tại. */
    public function expectedCash(): float
    {
        $cashFromOrders = $this->orders()
            ->where('status', 'hoan_thanh')
            ->where('payment_method', 'tien_mat')
            ->sum('total');

        $deposits = $this->cashAdjustments()->where('type', 'deposit')->sum('amount');
        $expenses = $this->cashAdjustments()->where('type', 'expense')->sum('amount');
        $withdrawals = $this->cashAdjustments()->where('type', 'withdrawal')->sum('amount');

        return (float) $this->opening_cash + $cashFromOrders + $deposits - $expenses - $withdrawals;
    }
}
