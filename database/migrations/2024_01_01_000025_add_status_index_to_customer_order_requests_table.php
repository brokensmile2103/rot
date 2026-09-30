<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * v1.1.3 — cơ chế polling hỏi số yêu cầu QR đang chờ (status = cho_xac_nhan)
     * mỗi ~10 giây trên MỌI trang đang mở, ngoài ra layout còn đếm thêm 1 lần
     * mỗi lần tải trang. Bảng này chỉ chứa vài dòng ĐANG chờ nhưng cộng dồn hàng
     * nghìn dòng đã xử lý theo thời gian — index (location_id, status) giúp các
     * truy vấn đếm này luôn nhanh dù bảng lớn lên.
     */
    public function up(): void
    {
        Schema::table('customer_order_requests', function (Blueprint $table) {
            $table->index(['location_id', 'status'], 'cor_location_status_index');
        });
    }

    public function down(): void
    {
        Schema::table('customer_order_requests', function (Blueprint $table) {
            $table->dropIndex('cor_location_status_index');
        });
    }
};
