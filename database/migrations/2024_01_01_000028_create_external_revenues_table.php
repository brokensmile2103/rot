<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * v1.2.0 — Doanh thu NGOÀI Rót do chủ quán tự nhập (bán qua app giao đồ ăn,
     * bán sỉ/bán lẻ không qua máy...). Doanh thu tính thuế là TỔNG mọi kênh bán,
     * nếu chỉ tính đơn trong Rót thì quán bán nhiều kênh sẽ bị đếm thiếu và tưởng
     * mình còn cách ngưỡng miễn thuế xa hơn thực tế.
     *
     * Các khoản này được cộng vào Sổ doanh thu và vào mức theo dõi ngưỡng thuế, nhưng
     * KHÔNG lẫn vào báo cáo lợi nhuận (không có giá vốn/món tương ứng để tính lãi).
     */
    public function up(): void
    {
        Schema::create('external_revenues', function (Blueprint $table) {
            $table->id();
            $table->foreignId('location_id')->constrained()->cascadeOnDelete();
            // Người nhập — nullOnDelete để khoản thu vẫn còn nếu tài khoản đó bị xoá về sau.
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->date('revenue_date');
            $table->decimal('amount', 14, 2);
            $table->string('description', 150);
            $table->timestamps();

            $table->index(['location_id', 'revenue_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('external_revenues');
    }
};
