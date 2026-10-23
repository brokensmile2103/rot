<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Yêu cầu gọi món khách tự gửi qua trang menu công khai (quét QR) — CỐ
     * TÌNH tách bảng riêng, KHÔNG dùng chung bảng `orders`: 1 yêu cầu ở đây
     * chưa phải 1 đơn hàng thật (chưa gắn ca, chưa trừ kho, chưa tính tiền),
     * chỉ là "giỏ hàng nháp do khách tự chọn" chờ nhân viên duyệt lại rồi mới
     * biến thành đơn thật qua OrderController::acceptCustomerRequest(). Tách
     * bảng giúp không phải nới lỏng ràng buộc NOT NULL shift_id/status hiện
     * có của `orders` chỉ để phục vụ 1 trạng thái tạm thời.
     */
    public function up(): void
    {
        Schema::create('customer_order_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('location_id')->constrained()->cascadeOnDelete();
            // [{variant_id, quantity, modifier_ids: []}, ...] — CHỈ lưu lựa
            // chọn thô của khách, KHÔNG lưu giá tiền (giá luôn tính lại từ DB
            // lúc nhân viên nhận đơn, giống triết lý validateCart() ở
            // OrderController — không tin số liệu phía không đáng tin cậy).
            $table->json('items');
            $table->string('customer_name', 100)->nullable();
            $table->string('note', 150)->nullable();
            $table->enum('status', ['cho_xac_nhan', 'da_nhan', 'tu_choi'])->default('cho_xac_nhan');
            $table->foreignId('handled_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('handled_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customer_order_requests');
    }
};
