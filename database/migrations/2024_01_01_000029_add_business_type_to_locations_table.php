<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * v1.3.0 — Mô hình kinh doanh của xe/quán (xem config/business_types.php và
     * App\Services\BusinessTypeCatalog): chỉ đổi CHỮ/ICON hiển thị cho đúng
     * ngành hàng (quán cà phê, quán ăn, tiệm tóc...), KHÔNG đổi nghiệp vụ.
     *
     * Mặc định 'cafe' cho MỌI xe đã có sẵn trước khi nâng cấp — hành vi hiển
     * thị giữ nguyên y hệt trước đây, không có gì đổi cho tới khi chủ quán tự
     * vào Cài đặt chọn mô hình khác.
     */
    public function up(): void
    {
        Schema::table('locations', function (Blueprint $table) {
            $table->string('business_type', 30)->default('cafe')->after('tax_code');
            // Chỉ dùng khi business_type = 'other' — chủ quán tự đặt 1 từ gọi tên
            // đơn vị bán (VD: "suất ăn") để ghép thành các câu còn lại.
            $table->string('business_type_label', 40)->nullable()->after('business_type');
        });
    }

    public function down(): void
    {
        Schema::table('locations', function (Blueprint $table) {
            $table->dropColumn(['business_type', 'business_type_label']);
        });
    }
};
