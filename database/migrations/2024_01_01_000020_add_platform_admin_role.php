<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * CLOUD ONLY — thêm vai trò platform_admin (Super Admin của cả nền tảng,
 * KHÁC với "owner" là chủ 1 quán cụ thể). Super Admin không sở hữu location
 * nào, chỉ quản lý danh sách toàn bộ quán đã đăng ký trên nền tảng.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement("ALTER TABLE users MODIFY role ENUM('platform_admin', 'owner', 'staff') DEFAULT 'staff'");
    }

    public function down(): void
    {
        DB::statement("ALTER TABLE users MODIFY role ENUM('owner', 'staff') DEFAULT 'staff'");
    }
};
