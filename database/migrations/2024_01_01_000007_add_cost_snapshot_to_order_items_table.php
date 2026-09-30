<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Ghi lại (snapshot) giá vốn NGAY TẠI THỜI ĐIỂM BÁN cho từng order_item, thay vì
 * tính lại theo giá vốn trung bình HIỆN TẠI của nguyên liệu như trước đây.
 *
 * Lý do: giá nhập nguyên liệu thay đổi theo thời gian. Nếu tính giá vốn của một
 * đơn hàng tháng trước bằng giá nhập của HÔM NAY, báo cáo lợi nhuận các ngày cũ
 * sẽ sai. Snapshot tại thời điểm bán là cách duy nhất đảm bảo chính xác tuyệt đối,
 * đúng yêu cầu của một phần mềm quản lý thực thụ.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('order_items', function (Blueprint $table) {
            $table->decimal('unit_cost', 12, 4)->default(0)->after('unit_price');
            $table->decimal('total_cost', 12, 2)->default(0)->after('line_total');
        });
    }

    public function down(): void
    {
        Schema::table('order_items', function (Blueprint $table) {
            $table->dropColumn(['unit_cost', 'total_cost']);
        });
    }
};
