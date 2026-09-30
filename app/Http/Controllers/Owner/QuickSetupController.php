<?php

namespace App\Http\Controllers\Owner;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Concerns\ResolvesCurrentLocation;
use App\Services\QuickSetupService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * "Thiết lập nhanh (dữ liệu mẫu)" — chỉ dành cho quán CHƯA có món nào (xem
 * QuickSetupService::hasExistingMenu). Chặn chạy lại để không tạo trùng/đè
 * lên thực đơn chủ quán đã tự thiết lập tay.
 */
class QuickSetupController extends Controller
{
    use ResolvesCurrentLocation;

    public function store(Request $request, QuickSetupService $quickSetup): RedirectResponse
    {
        $location = $this->currentLocation($request);
        $terms = $location->businessTerms();

        if ($quickSetup->hasExistingMenu($location)) {
            return back()->with('status', ucfirst($terms['location_singular']).' của bạn đã có '.$terms['catalog_singular'].' — không thể chạy Thiết lập nhanh nữa (chỉ dùng được cho quán hoàn toàn mới).');
        }

        $quickSetup->seed($location);

        return redirect()->route('owner.menu.index')
            ->with('status', 'Đã tạo xong dữ liệu mẫu — đầy đủ công thức và tuỳ chọn, sẵn sàng bán ngay!');
    }
}
