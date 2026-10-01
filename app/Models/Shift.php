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

    /**
     * Tổng tiền mặt lý thuyết đang có trong quỹ tại thời điểm hiện tại. Đơn thanh
     * toán KẾT HỢP chỉ cộng đúng phần tiền mặt (cash_portion) — phần chuyển khoản
     * nằm ở tài khoản ngân hàng, không nằm trong ngăn kéo.
     */
    public function expectedCash(): float
    {
        $completed = $this->orders()->where('status', 'hoan_thanh');

        $cashFromOrders = (float) (clone $completed)->where('payment_method', 'tien_mat')->sum('total');
        $cashFromSplit = (float) (clone $completed)->where('payment_method', 'ket_hop')
            ->selectRaw('COALESCE(SUM(CASE WHEN cash_portion < total THEN cash_portion ELSE total END), 0) AS cash')
            ->value('cash');

        $deposits = $this->cashAdjustments()->where('type', 'deposit')->sum('amount');
        $expenses = $this->cashAdjustments()->where('type', 'expense')->sum('amount');
        $withdrawals = $this->cashAdjustments()->where('type', 'withdrawal')->sum('amount');

        return (float) $this->opening_cash + $cashFromOrders + $cashFromSplit + $deposits - $expenses - $withdrawals;
    }

    /**
     * Tổng kết bán hàng của ca theo HÌNH THỨC THANH TOÁN — để đối soát cuối ca: tiền
     * mặt khớp ngăn kéo, chuyển khoản/ví khớp sao kê ngân hàng/app ví.
     *
     * @return array{order_count: int, cancelled_count: int, draft_count: int, revenue: float, cash: float, transfer: float, ewallet: float}
     */
    public function salesSummary(): array
    {
        $orders = $this->orders()
            ->whereIn('status', ['hoan_thanh', 'da_huy', 'nhap'])
            ->get(['id', 'status', 'payment_method', 'total', 'cash_portion']);

        $completed = $orders->where('status', 'hoan_thanh');

        return [
            'order_count' => $completed->count(),
            'cancelled_count' => $orders->where('status', 'da_huy')->count(),
            'draft_count' => $orders->where('status', 'nhap')->count(),
            'revenue' => (float) $completed->sum('total'),
            'cash' => (float) $completed->sum(fn (Order $o) => $o->cashAmount()),
            'transfer' => (float) $completed->whereIn('payment_method', ['chuyen_khoan', 'ket_hop'])->sum(fn (Order $o) => $o->nonCashAmount()),
            'ewallet' => (float) $completed->where('payment_method', 'vi_dien_tu')->sum('total'),
        ];
    }
}
