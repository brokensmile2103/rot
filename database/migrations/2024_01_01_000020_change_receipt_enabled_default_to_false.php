<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Đổi mặc định "In hoá đơn" (locations.receipt_enabled) từ BẬT sang TẮT.
 *
 * CHỈ đổi giá trị mặc định cho location MỚI TẠO SAU NÀY — KHÔNG đụng tới
 * location đã tồn tại (ai đã bật/tắt sẵn thì giữ nguyên đúng lựa chọn của
 * họ, không âm thầm đổi cài đặt người dùng đã tự chọn).
 *
 * Dùng ALTER ... SET DEFAULT thay vì Schema::table()->change() vì
 * ->change() cần package doctrine/dbal (chưa cài trong dự án này) —
 * ALTER COLUMN SET DEFAULT là câu lệnh MySQL thuần, không cần thêm gì.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement('ALTER TABLE `locations` ALTER `receipt_enabled` SET DEFAULT 0');
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE `locations` ALTER `receipt_enabled` SET DEFAULT 1');
    }
};
