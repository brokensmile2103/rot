<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Thêm "xoá mềm" (soft delete) cho các bảng thuộc thực đơn + nguyên liệu.
 *
 * LÝ DO: order_items tham chiếu tới product_variant_id, order_item_modifiers
 * tham chiếu tới modifier_id, recipes/stock_ins tham chiếu tới ingredient_id.
 * Nếu XOÁ THẬT các bản ghi này, lịch sử đơn hàng/báo cáo cũ sẽ vỡ (hoặc bị
 * chặn bởi ràng buộc khoá ngoại, hoặc tệ hơn là mất luôn nếu có cascade).
 *
 * Với xoá mềm: bấm "Xoá" chỉ đánh dấu deleted_at, dòng dữ liệu vẫn còn nguyên
 * trong database — chỉ tự động bị ẩn khỏi các danh sách/màn hình bán hàng.
 * Có thể khôi phục lại bất cứ lúc nào, không sợ "xoá là mất".
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('categories', fn (Blueprint $table) => $table->softDeletes());
        Schema::table('products', fn (Blueprint $table) => $table->softDeletes());
        Schema::table('product_variants', fn (Blueprint $table) => $table->softDeletes());
        Schema::table('modifier_groups', fn (Blueprint $table) => $table->softDeletes());
        Schema::table('modifiers', fn (Blueprint $table) => $table->softDeletes());
        Schema::table('ingredients', fn (Blueprint $table) => $table->softDeletes());
    }

    public function down(): void
    {
        Schema::table('categories', fn (Blueprint $table) => $table->dropSoftDeletes());
        Schema::table('products', fn (Blueprint $table) => $table->dropSoftDeletes());
        Schema::table('product_variants', fn (Blueprint $table) => $table->dropSoftDeletes());
        Schema::table('modifier_groups', fn (Blueprint $table) => $table->dropSoftDeletes());
        Schema::table('modifiers', fn (Blueprint $table) => $table->dropSoftDeletes());
        Schema::table('ingredients', fn (Blueprint $table) => $table->dropSoftDeletes());
    }
};
