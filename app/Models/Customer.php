<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Customer extends Model
{
    use SoftDeletes;

    protected $fillable = ['location_id', 'name', 'phone', 'points', 'total_spent'];

    protected function casts(): array
    {
        return ['total_spent' => 'decimal:2'];
    }

    public function location()
    {
        return $this->belongsTo(Location::class);
    }

    public function orders()
    {
        return $this->hasMany(Order::class);
    }

    /** Cộng điểm kiếm được, trừ điểm đã dùng, cộng tiền đã chi — dùng khi đơn hoàn tất. */
    public function applyOrder(int $pointsEarned, int $pointsRedeemed, float $spent): void
    {
        $this->update([
            'points' => max(0, $this->points + $pointsEarned - $pointsRedeemed),
            'total_spent' => (float) $this->total_spent + $spent,
        ]);
    }

    /**
     * Hoàn tác điểm khi sửa/huỷ đơn: TRẢ LẠI điểm đã dùng (redeemed) và
     * TRỪ ĐI điểm đã cộng (earned) trước đó — ngược hoàn toàn với addPoints.
     * Dùng update() thay vì increment/decrement để chủ động chặn điểm âm
     * (cột points là unsignedInteger, cộng số âm trực tiếp có thể lỗi SQL).
     */
    public function reversePoints(int $pointsEarned, int $pointsRedeemed, float $spent): void
    {
        $this->update([
            'points' => max(0, $this->points - $pointsEarned + $pointsRedeemed),
            'total_spent' => max(0, (float) $this->total_spent - $spent),
        ]);
    }
}
