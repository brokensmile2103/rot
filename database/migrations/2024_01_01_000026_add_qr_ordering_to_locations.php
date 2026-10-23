<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('locations', function (Blueprint $table) {
            // Token ngẫu nhiên dùng làm phần định danh trong link menu công
            // khai (/menu/{token}) — KHÔNG dùng ID số nguyên tuần tự của
            // location, vì ID dễ đoán (VD: thử /menu/2, /menu/3...) sẽ lộ ra
            // thực đơn của quán khác chưa từng chia sẻ link.
            $table->string('public_token', 40)->nullable()->unique()->after('id');
            // Mặc định TẮT — chủ quán phải chủ động bật ở trang Cài đặt mới
            // lộ link ra ngoài, tránh trường hợp có khách gửi yêu cầu vào 1
            // quán còn chưa sẵn sàng nhận đơn qua QR.
            $table->boolean('qr_ordering_enabled')->default(false)->after('public_token');
        });

        // Sinh sẵn token cho các quán đã tồn tại trước migration này — dùng
        // raw DB query (không qua Eloquent) để migration không phụ thuộc vào
        // trạng thái model ở các phiên bản code sau này.
        foreach (DB::table('locations')->select('id')->get() as $location) {
            DB::table('locations')->where('id', $location->id)->update([
                'public_token' => Str::random(32),
            ]);
        }
    }

    public function down(): void
    {
        Schema::table('locations', function (Blueprint $table) {
            $table->dropColumn(['public_token', 'qr_ordering_enabled']);
        });
    }
};
