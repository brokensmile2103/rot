<?php

namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    use Notifiable;

    /**
     * 'role' và 'is_active' CỐ TÌNH không nằm trong $fillable — xem giải
     * thích chi tiết ở bản Rót Cloud (app/Models/User.php), lý do giống hệt.
     */
    protected $fillable = [
        'name', 'phone', 'email', 'password', 'avatar_url', 'salary_type', 'salary_amount',
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

    public function isOwner(): bool
    {
        return $this->role === 'owner';
    }

    /**
     * Bản self-hosted không có vai trò Super Admin (chỉ có ở Rót Cloud) —
     * luôn trả false. Có hàm này để dùng chung được các view/controller viết
     * cho cả 2 bản (VD: profile/_form.blade.php) mà không cần code riêng.
     */
    public function isPlatformAdmin(): bool
    {
        return false;
    }

    public function ownedLocations()
    {
        return $this->hasMany(Location::class, 'owner_id');
    }

    public function locations()
    {
        return $this->belongsToMany(Location::class);
    }
}
