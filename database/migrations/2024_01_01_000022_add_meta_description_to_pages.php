<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * CLOUD ONLY — cho phép Super Admin tự viết mô tả SEO (thẻ meta
 * description) riêng cho từng trang tĩnh (Giới thiệu/Điều khoản/Chính sách),
 * thay vì luôn tự động cắt ngắn nội dung. Nullable — nếu để trống,
 * PageController sẽ tự suy ra từ $content.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pages', function (Blueprint $table) {
            $table->string('meta_description', 160)->nullable()->after('content');
        });

        // Điền sẵn mô tả SEO cho 3 trang seed có sẵn.
        \DB::table('pages')->where('slug', 'gioi-thieu')->update([
            'meta_description' => 'Rót là phần mềm quản lý bán hàng dành cho xe/quầy cà phê nhỏ lẻ — đăng ký là dùng ngay, không cần cài đặt máy chủ hay biết kỹ thuật.',
        ]);
        \DB::table('pages')->where('slug', 'dieu-khoan-su-dung')->update([
            'meta_description' => 'Điều khoản sử dụng dịch vụ Rót — quyền và trách nhiệm của bạn khi đăng ký, sử dụng phần mềm quản lý bán hàng Rót.',
        ]);
        \DB::table('pages')->where('slug', 'chinh-sach-bao-mat')->update([
            'meta_description' => 'Chính sách bảo mật của Rót — cách chúng tôi thu thập, sử dụng và bảo vệ dữ liệu cá nhân, dữ liệu bán hàng của bạn.',
        ]);
    }

    public function down(): void
    {
        Schema::table('pages', function (Blueprint $table) {
            $table->dropColumn('meta_description');
        });
    }
};
