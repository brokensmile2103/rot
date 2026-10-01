<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * - orders.daily_number: số thứ tự đơn TRONG NGÀY của từng xe (gọi số khách lấy món).
 * - orders.prep_status / prepared_at: hàng chờ pha chế (cho_pha → da_xong).
 * - orders.cash_portion: phần TIỀN MẶT của đơn thanh toán kết hợp (payment_method = ket_hop),
 *   phần còn lại là chuyển khoản — để Chốt ca tính đúng tiền mặt lý thuyết.
 * - order_items.stock_deductions: snapshot lượng nguyên liệu ĐÃ TRỪ thật lúc bán
 *   ([{"i": ingredient_id, "q": số lượng}]) — huỷ/sửa đơn hoàn lại đúng số đã trừ kể cả
 *   khi công thức món bị đổi sau đó. Đơn cũ (null) vẫn hoàn theo công thức hiện tại.
 * - locations.prep_queue_enabled: bật/tắt màn hình "Pha chế".
 */
return new class extends Migration
{
    public function up(): void
    {
        if (in_array(DB::getDriverName(), ['mysql', 'mariadb'], true)) {
            DB::statement("ALTER TABLE orders MODIFY payment_method ENUM('tien_mat', 'chuyen_khoan', 'vi_dien_tu', 'ket_hop') NOT NULL");
        }

        Schema::table('orders', function (Blueprint $table) {
            $table->decimal('cash_portion', 12, 2)->nullable()->after('payment_method');
            $table->unsignedSmallInteger('daily_number')->nullable()->after('id');
            $table->string('prep_status', 20)->nullable()->after('status');
            $table->timestamp('prepared_at')->nullable()->after('completed_at');
            $table->index(['location_id', 'prep_status']);
            $table->index(['location_id', 'completed_at']);
        });

        Schema::table('order_items', function (Blueprint $table) {
            $table->json('stock_deductions')->nullable()->after('total_cost');
        });

        Schema::table('locations', function (Blueprint $table) {
            $table->boolean('prep_queue_enabled')->default(false)->after('qr_ordering_enabled');
        });
    }

    public function down(): void
    {
        Schema::table('locations', function (Blueprint $table) {
            $table->dropColumn('prep_queue_enabled');
        });

        Schema::table('order_items', function (Blueprint $table) {
            $table->dropColumn('stock_deductions');
        });

        Schema::table('orders', function (Blueprint $table) {
            // Khoá ngoại location_id đang dựa vào index ghép bên dưới — tạo index riêng trước khi gỡ.
            $table->index('location_id');
            $table->dropIndex(['location_id', 'prep_status']);
            $table->dropIndex(['location_id', 'completed_at']);
            $table->dropColumn(['cash_portion', 'daily_number', 'prep_status', 'prepared_at']);
        });

        if (in_array(DB::getDriverName(), ['mysql', 'mariadb'], true)) {
            DB::statement("UPDATE orders SET payment_method = 'chuyen_khoan' WHERE payment_method = 'ket_hop'");
            DB::statement("ALTER TABLE orders MODIFY payment_method ENUM('tien_mat', 'chuyen_khoan', 'vi_dien_tu') NOT NULL");
        }
    }
};
