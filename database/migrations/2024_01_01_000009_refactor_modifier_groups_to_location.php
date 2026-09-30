<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * REFACTOR QUAN TRỌNG: Nhóm tuỳ chọn (VD: "Lượng đường", "Topping") trước đây
 * gắn cứng vào 1 món (product_id) — nghĩa là mỗi món phải tự tạo lại "Lượng
 * đường" riêng, rất bất tiện vì hầu hết đồ uống đều cần "Lượng đường" giống hệt
 * nhau.
 *
 * Sau refactor: modifier_groups gắn theo LOCATION (tạo 1 lần, dùng cho mọi món),
 * và bảng trung gian product_modifier_group quyết định món nào áp dụng nhóm nào.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('modifier_groups', function (Blueprint $table) {
            $table->foreignId('location_id')->nullable()->after('id')->constrained()->cascadeOnDelete();
        });

        // Di chuyển dữ liệu cũ: mỗi group cũ suy ra location từ product cha của nó.
        DB::table('modifier_groups')->whereNotNull('product_id')->orderBy('id')->get()->each(function ($group) {
            $product = DB::table('products')->find($group->product_id);
            $category = $product ? DB::table('categories')->find($product->category_id) : null;

            if ($category) {
                DB::table('modifier_groups')->where('id', $group->id)->update(['location_id' => $category->location_id]);
            }
        });

        Schema::create('product_modifier_group', function (Blueprint $table) {
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->foreignId('modifier_group_id')->constrained()->cascadeOnDelete();
            $table->primary(['product_id', 'modifier_group_id']);
        });

        // Giữ nguyên liên kết món <-> nhóm tuỳ chọn đã có từ trước khi refactor.
        DB::table('modifier_groups')->whereNotNull('product_id')->get()->each(function ($group) {
            DB::table('product_modifier_group')->insert([
                'product_id' => $group->product_id,
                'modifier_group_id' => $group->id,
            ]);
        });

        Schema::table('modifier_groups', function (Blueprint $table) {
            $table->dropForeign(['product_id']);
            $table->dropColumn('product_id');
        });

        Schema::create('modifier_recipes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('modifier_id')->constrained()->cascadeOnDelete();
            $table->foreignId('ingredient_id')->constrained()->cascadeOnDelete();
            $table->decimal('quantity', 12, 4);
            $table->timestamps();
            $table->unique(['modifier_id', 'ingredient_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('modifier_recipes');

        Schema::table('modifier_groups', function (Blueprint $table) {
            $table->foreignId('product_id')->nullable()->after('location_id')->constrained()->cascadeOnDelete();
        });

        DB::table('product_modifier_group')->get()->each(function ($pivot) {
            DB::table('modifier_groups')->where('id', $pivot->modifier_group_id)->update(['product_id' => $pivot->product_id]);
        });

        Schema::dropIfExists('product_modifier_group');

        Schema::table('modifier_groups', function (Blueprint $table) {
            $table->dropForeign(['location_id']);
            $table->dropColumn('location_id');
        });
    }
};
