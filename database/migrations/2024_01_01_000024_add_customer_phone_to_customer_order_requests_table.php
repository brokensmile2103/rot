<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Thêm SĐT khách hàng vào yêu cầu QR — CHỈ khi quán đang bật "Khách hàng
     * thân thiết" (xem PublicMenuController::show) mới hỏi khách số này, để
     * nhân viên nhận đơn khỏi phải gõ lại tay, tự tra cứu/tích điểm luôn.
     */
    public function up(): void
    {
        Schema::table('customer_order_requests', function (Blueprint $table) {
            $table->string('customer_phone', 20)->nullable()->after('customer_name');
        });
    }

    public function down(): void
    {
        Schema::table('customer_order_requests', function (Blueprint $table) {
            $table->dropColumn('customer_phone');
        });
    }
};
