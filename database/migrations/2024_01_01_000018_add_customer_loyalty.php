<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('customers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('location_id')->constrained()->cascadeOnDelete();
            $table->string('name')->nullable();
            $table->string('phone', 20);
            $table->unsignedInteger('points')->default(0);
            $table->decimal('total_spent', 12, 2)->default(0);
            $table->softDeletes();
            $table->timestamps();
            $table->unique(['location_id', 'phone']);
        });

        Schema::table('locations', function (Blueprint $table) {
            $table->boolean('loyalty_enabled')->default(false)->after('bank_account_name');
            // Chi bao nhiêu đồng thì được 1 điểm (VD: 10000 = 10.000đ/điểm)
            $table->decimal('points_earn_rate', 12, 2)->default(10000)->after('loyalty_enabled');
            // 1 điểm quy đổi được bao nhiêu đồng khi dùng để giảm giá
            $table->decimal('points_redeem_value', 12, 2)->default(1000)->after('points_earn_rate');
        });

        Schema::table('orders', function (Blueprint $table) {
            $table->foreignId('customer_id')->nullable()->after('location_id')->constrained()->nullOnDelete();
            $table->unsignedInteger('points_earned')->default(0)->after('discount_amount');
            $table->unsignedInteger('points_redeemed')->default(0)->after('points_earned');
            $table->decimal('points_redeemed_value', 12, 2)->default(0)->after('points_redeemed');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropConstrainedForeignId('customer_id');
            $table->dropColumn(['points_earned', 'points_redeemed', 'points_redeemed_value']);
        });

        Schema::table('locations', function (Blueprint $table) {
            $table->dropColumn(['loyalty_enabled', 'points_earn_rate', 'points_redeem_value']);
        });

        Schema::dropIfExists('customers');
    }
};
