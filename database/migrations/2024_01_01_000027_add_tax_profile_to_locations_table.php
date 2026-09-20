<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * v1.2.0 — Thông tin in ở đầu Sổ doanh thu (mẫu S1a-HKD): tên hộ kinh doanh
     * (người đại diện) và mã số thuế. Địa điểm kinh doanh lấy từ cột `address` đã có.
     * Cả 2 đều tuỳ chọn — chưa điền thì sổ vẫn in được, để trống chỗ đó.
     */
    public function up(): void
    {
        Schema::table('locations', function (Blueprint $table) {
            $table->string('tax_household_name', 100)->nullable()->after('address');
            $table->string('tax_code', 20)->nullable()->after('tax_household_name');
        });
    }

    public function down(): void
    {
        Schema::table('locations', function (Blueprint $table) {
            $table->dropColumn(['tax_household_name', 'tax_code']);
        });
    }
};
