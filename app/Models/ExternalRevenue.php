<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * 1 khoản doanh thu ngoài Rót do chủ quán tự nhập để Sổ doanh thu và theo dõi
 * ngưỡng thuế phản ánh ĐỦ mọi kênh bán (xem migration để biết lý do). Chỉ thêm/xoá,
 * không sửa — muốn đổi thì xoá rồi nhập lại, tránh số trên sổ bị đổi âm thầm.
 */
class ExternalRevenue extends Model
{
    protected $fillable = ['location_id', 'user_id', 'revenue_date', 'amount', 'description'];

    protected function casts(): array
    {
        return [
            'revenue_date' => 'date',
            'amount' => 'decimal:2',
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
}
