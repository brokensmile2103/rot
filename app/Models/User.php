<?php

namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    use Notifiable;

    /**
     * 'role' và 'is_active' CỐ TÌNH không nằm trong $fillable — đây là 2
     * trường quyết định quyền hạn tài khoản (owner/staff/platform_admin,
     * khoá/mở), không bao giờ được phép set qua mass-assignment dù validate()
     * kỹ đến đâu. Nơi nào thật sự cần set (RegisterController, StaffController,
     * InstallController — luôn với giá trị CỐ ĐỊNH trong code, không lấy từ
     * request) phải dùng forceCreate()/forceFill() để thể hiện rõ đây là hành
     * động CÓ CHỦ ĐÍCH, tự chịu trách nhiệm bỏ qua guard, không phải vô tình.
     */
    protected $fillable = [
        'name', 'phone', 'email', 'password', 'avatar_url', 'google_id', 'salary_type', 'salary_amount',
    ];

    protected $hidden = [
        'password', 'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'salary_amount' => 'decimal:2',
        ];
    }

    /**
     * Có quyền chủ quán không. Super Admin cũng tính là chủ quán (có quán riêng,
     * dùng được mọi tính năng bán hàng/quản lý như tài khoản thường) — chỉ khác
     * là có thêm khu vực /sieu-quan-tri.
     */
    public function isOwner(): bool
    {
        return in_array($this->role, ['owner', 'platform_admin'], true);
    }

    /** Super Admin của cả nền tảng (vừa là chủ quán, vừa quản trị được mọi quán). */
    public function isPlatformAdmin(): bool
    {
        return $this->role === 'platform_admin';
    }

    public function ownedLocations()
    {
        return $this->hasMany(Location::class, 'owner_id');
    }

    public function locations()
    {
        return $this->belongsToMany(Location::class);
    }

    /**
     * Ghi đè để gửi email "Quên mật khẩu" bằng bản tiếng Việt của riêng Rót
     * Cloud thay vì template mặc định tiếng Anh của Laravel — cơ chế token
     * hết hạn/chống tái sử dụng vẫn nguyên vẹn, chỉ đổi nội dung hiển thị.
     */
    public function sendPasswordResetNotification($token): void
    {
        $this->notify(new \App\Notifications\ResetPasswordNotification($token));
    }
}
