<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * CLOUD ONLY — nội dung 3 trang Giới thiệu/Điều khoản/Chính sách được
 * lưu trong database (không hardcode trong mã nguồn), để Super Admin tự sửa
 * qua giao diện mà không cần đụng tới code hay deploy lại.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pages', function (Blueprint $table) {
            $table->id();
            $table->string('slug')->unique();
            $table->string('title');
            $table->longText('content')->nullable();
            $table->timestamps();
        });

        // Seed sẵn 3 trang với nội dung khởi điểm — Super Admin sửa lại qua
        // /sieu-quan-tri/trang/{slug}. Điều khoản & Chính sách chỉ là BẢN
        // KHUNG để tham khảo, KHÔNG phải văn bản pháp lý hoàn chỉnh — cần luật
        // sư rà soát trước khi dùng thật, Claude không thay thế được vai trò đó.
        $now = now();
        \DB::table('pages')->insert([
            [
                'slug' => 'gioi-thieu',
                'title' => 'Giới thiệu',
                'content' => "<p>Rót là phần mềm quản lý bán hàng dành riêng cho mô hình xe/quầy cà phê nhỏ lẻ — bán hàng một tay, tối ưu cho thao tác nhanh, giao diện sáng dễ đọc ngoài trời.</p><p>Đăng ký là dùng được ngay, không cần tự cài đặt máy chủ, không cần biết kỹ thuật.</p><p>Nội dung trang này do quản trị viên nền tảng cập nhật.</p>",
                'created_at' => $now, 'updated_at' => $now,
            ],
            [
                'slug' => 'dieu-khoan-su-dung',
                'title' => 'Điều khoản sử dụng',
                'content' => "<p><em>Đây là bản khung tạm thời, cần được luật sư rà soát trước khi phát hành chính thức.</em></p><h3>1. Chấp nhận điều khoản</h3><p>Bằng việc đăng ký và sử dụng Rót, bạn đồng ý tuân thủ các điều khoản được nêu tại trang này.</p><h3>2. Tài khoản người dùng</h3><p>Bạn chịu trách nhiệm bảo mật thông tin đăng nhập và mọi hoạt động diễn ra dưới tài khoản của mình.</p><h3>3. Dữ liệu của bạn</h3><p>Dữ liệu bán hàng bạn nhập vào hệ thống thuộc quyền sở hữu của bạn. Nhà cung cấp có trách nhiệm lưu trữ và bảo vệ dữ liệu đó theo Chính sách bảo mật.</p><h3>4. Giới hạn trách nhiệm</h3><p>Dịch vụ được cung cấp trên cơ sở \"nguyên trạng\". Nhà cung cấp không chịu trách nhiệm cho các thiệt hại gián tiếp phát sinh từ việc sử dụng dịch vụ.</p><h3>5. Thay đổi điều khoản</h3><p>Điều khoản có thể được cập nhật theo thời gian. Việc tiếp tục sử dụng dịch vụ sau khi thay đổi đồng nghĩa với việc chấp nhận điều khoản mới.</p>",
                'created_at' => $now, 'updated_at' => $now,
            ],
            [
                'slug' => 'chinh-sach-bao-mat',
                'title' => 'Chính sách bảo mật',
                'content' => "<p><em>Đây là bản khung tạm thời, cần được luật sư rà soát trước khi phát hành chính thức.</em></p><h3>1. Thông tin thu thập</h3><p>Chúng tôi thu thập thông tin bạn cung cấp khi đăng ký (tên, số điện thoại, tên quán) và dữ liệu bán hàng phát sinh trong quá trình sử dụng.</p><h3>2. Mục đích sử dụng</h3><p>Thông tin được dùng để vận hành dịch vụ, hỗ trợ kỹ thuật, và cải thiện chất lượng phần mềm.</p><h3>3. Chia sẻ thông tin</h3><p>Chúng tôi không bán hoặc cho thuê dữ liệu cá nhân của bạn cho bên thứ ba vì mục đích quảng cáo.</p><h3>4. Bảo mật dữ liệu</h3><p>Dữ liệu nhạy cảm (mật khẩu, khoá kết nối API) được mã hoá khi lưu trữ.</p><h3>5. Quyền của bạn</h3><p>Bạn có quyền yêu cầu xuất hoặc xoá dữ liệu của mình bằng cách liên hệ bộ phận hỗ trợ.</p>",
                'created_at' => $now, 'updated_at' => $now,
            ],
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('pages');
    }
};
