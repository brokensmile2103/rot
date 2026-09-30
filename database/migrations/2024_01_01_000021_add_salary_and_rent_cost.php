<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * v1.1.0 — Lương nhân viên (theo giờ/theo tháng) + chi phí mặt bằng, phục vụ
 * tính "Lợi nhuận thực tế" ở trang Báo cáo (xem ReportController::operatingCosts()).
 *
 * `salary_type`/`salary_amount` CỐ TÌNH nullable, mặc định NULL — nhân viên
 * chưa thiết lập lương thì không tính vào chi phí nhân sự ở Báo cáo, hoàn
 * toàn không ảnh hưởng gì tới quán chưa dùng tính năng này.
 *
 * `rent_cost` mặc định 0 — quán không có mặt bằng cố định (xe đẩy thuần) thì
 * để 0, không cộng thêm chi phí ảo nào vào báo cáo.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->enum('salary_type', ['hourly', 'monthly'])->nullable()->after('is_active');
            $table->decimal('salary_amount', 12, 2)->nullable()->after('salary_type');
        });

        Schema::table('locations', function (Blueprint $table) {
            $table->decimal('rent_cost', 12, 2)->default(0)->after('is_active');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['salary_type', 'salary_amount']);
        });

        Schema::table('locations', function (Blueprint $table) {
            $table->dropColumn('rent_cost');
        });
    }
};
