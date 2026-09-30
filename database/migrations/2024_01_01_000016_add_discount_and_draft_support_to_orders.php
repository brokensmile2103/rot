<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Mở rộng enum status để thêm trạng thái "nhap" (đơn nháp/đang giữ chỗ) —
        // dùng raw SQL vì Laravel không hỗ trợ sửa cột enum trực tiếp mà không
        // cần thêm gói doctrine/dbal.
        DB::statement("ALTER TABLE orders MODIFY status ENUM('nhap', 'dang_pha_che', 'hoan_thanh', 'da_huy') DEFAULT 'hoan_thanh'");

        Schema::table('orders', function (Blueprint $table) {
            $table->string('discount_type', 10)->nullable()->after('subtotal');
            $table->decimal('discount_value', 12, 2)->nullable()->after('discount_type');
            $table->decimal('discount_amount', 12, 2)->default(0)->after('discount_value');
            $table->string('note', 100)->nullable()->after('guest_count');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn(['discount_type', 'discount_value', 'discount_amount', 'note']);
        });

        DB::statement("ALTER TABLE orders MODIFY status ENUM('dang_pha_che', 'hoan_thanh', 'da_huy') DEFAULT 'hoan_thanh'");
    }
};
