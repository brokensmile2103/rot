<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * v1.1.3 — Nhật ký điều chỉnh kho: mỗi lần chủ quán SỬA TRỰC TIẾP "Tồn kho"
     * hoặc "Giá vốn TB" của 1 nguyên liệu (khác với "Nhập kho" vốn đã có lịch sử
     * riêng ở bảng stock_ins) sẽ ghi lại 1 dòng: ai sửa, lúc nào, số liệu TRƯỚC
     * và SAU, lý do.
     *
     * Vì sao cần: trước đây sửa tay là ghi đè thẳng lên số liệu, không để lại dấu
     * vết — không biết tồn kho đã bị đổi bao giờ, đổi từ bao nhiêu, vì sao (kiểm kê
     * lệch, hàng hỏng, hay chỉ gõ nhầm). Có nhật ký này thì chênh lệch kiểm kê
     * (hao hụt) mới đo được.
     *
     * location_id được lưu riêng (dù đã suy ra được qua ingredient_id) để trang
     * nhật ký toàn quán lọc/đếm trực tiếp không phải JOIN, và để chắc chắn mọi
     * truy vấn đều bị khoá theo đúng quán.
     */
    public function up(): void
    {
        Schema::create('stock_adjustments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('location_id')->constrained()->cascadeOnDelete();
            // Nguyên liệu chỉ bị XOÁ MỀM nên dòng này luôn còn nguyên; cascadeOnDelete
            // chỉ có tác dụng khi bị xoá cứng (VD: xoá cả quán).
            $table->foreignId('ingredient_id')->constrained()->cascadeOnDelete();
            // Người sửa — nullOnDelete để nhật ký vẫn còn nguyên (hiện "Không rõ")
            // nếu tài khoản đó bị xoá về sau, thay vì chặn việc xoá tài khoản.
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            // kiem_ke | hao_hut | sai_so_lieu | khac (xem App\Models\StockAdjustment::REASONS)
            $table->string('reason', 20);
            $table->decimal('stock_before', 12, 2);
            $table->decimal('stock_after', 12, 2);
            $table->decimal('cost_before', 12, 4);
            $table->decimal('cost_after', 12, 4);
            $table->string('note')->nullable();
            $table->timestamps();

            $table->index(['location_id', 'created_at']);
            $table->index(['ingredient_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_adjustments');
    }
};
