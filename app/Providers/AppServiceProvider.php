<?php

namespace App\Providers;

use App\Models\Location;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Badge số trên menu "Đơn hàng" / "Kho nguyên liệu" — tính 1 LẦN DUY
        // NHẤT tại đây (view composer của layout dùng chung mọi trang) thay vì
        // lặp lại ở từng controller, vì layout hiện diện ở MỌI trang. Cố tình
        // gói toàn bộ trong try/catch: layout này còn được dùng cho vài màn
        // hình trước khi có ca/địa điểm hợp lệ (VD: vừa đăng nhập, chưa mở ca)
        // — badge lỗi âm thầm về 0 KHÔNG được phép làm sập cả trang.
        View::composer('layouts.app', function ($view) {
            $view->with([
                'orderBadgeCount' => 0,
                'lowStockBadgeCount' => 0,
                // Yêu cầu gọi món từ khách (QR) đang chờ nhận — số ban đầu cho
                // badge/banner; sau đó trình duyệt tự cập nhật bằng polling (xem
                // resources/js/qr-requests.js), không cần tải lại trang.
                'pendingRequestCount' => 0,
                'qrOrderingEnabled' => false,
                // id quán để polling biết hỏi cho quán nào — null (không polling) khi
                // chưa có quán hợp lệ/không có quyền (VD: vừa đăng nhập, chưa chọn quán).
                'qrLocationId' => null,
            ]);

            $user = auth()->user();
            $locationId = session('current_location_id');
            if (! $user || ! $locationId) {
                return;
            }

            $location = Location::find($locationId);
            if (! $location) {
                return;
            }

            $allowed = $user->isOwner()
                ? $location->owner_id === $user->id
                : $location->staff()->where('users.id', $user->id)->exists();
            if (! $allowed) {
                return;
            }

            // Tổng đơn hàng của CA ĐANG MỞ (không tính đơn nháp — đơn nháp đã
            // có banner riêng của nó trong màn Order) — khớp đúng với số đơn
            // nhân viên nhìn thấy khi bấm vào "Đơn hàng".
            $shift = $location->openShiftFor($user);
            if ($shift) {
                $view->with('orderBadgeCount', $shift->orders()->where('status', '!=', 'nhap')->count());
            }

            // Hiện cho MỌI người bán ở quán này (không phụ thuộc ca) — khớp với
            // trang "Đơn hàng" đang liệt kê yêu cầu cho tất cả nhân viên.
            $view->with('qrLocationId', $location->id);
            if ($location->qr_ordering_enabled) {
                $view->with([
                    'qrOrderingEnabled' => true,
                    'pendingRequestCount' => $location->customerOrderRequests()->where('status', 'cho_xac_nhan')->count(),
                ]);
            }

            // Cảnh báo tồn kho thấp — chỉ chủ quán mới thấy mục "Kho nguyên
            // liệu" nên chỉ tính cho chủ quán, tránh 1 query thừa cho nhân viên.
            if ($user->isOwner()) {
                $view->with(
                    'lowStockBadgeCount',
                    $location->ingredients()->whereColumn('current_stock', '<=', 'low_stock_threshold')->count()
                );
            }
        });
    }
}
