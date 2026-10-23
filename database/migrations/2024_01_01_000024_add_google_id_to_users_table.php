<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * CLOUD ONLY — hỗ trợ đăng nhập/đăng ký bằng Google.
 *
 * `google_id` nullable + unique: tài khoản đăng ký bằng số điện thoại (cách
 * cũ) sẽ có giá trị NULL ở đây cho tới khi họ tự liên kết Google (nếu có).
 * Không dùng `email` làm khoá liên kết chính vì `email` vẫn có thể NULL với
 * tài khoản đăng ký kiểu cũ chưa từng điền email ở Hồ sơ cá nhân.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('google_id')->nullable()->unique()->after('email');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('google_id');
        });
    }
};
