<?php

namespace App\Http\Controllers\Owner;

use App\Http\Controllers\Controller;
use App\Services\BusinessTypeCatalog;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Cho phép owner quản lý nhiều xe cà phê, nhưng trường hợp phổ biến nhất
 * (chỉ có 1 xe) không hề bị làm phức tạp thêm — chọn location tự động khi login,
 * màn hình chuyển xe chỉ xuất hiện khi owner thật sự có >1 location.
 */
class LocationController extends Controller
{
    public function index(Request $request, BusinessTypeCatalog $catalog): View
    {
        $locations = $request->user()->ownedLocations()->withCount('categories')->get();

        return view('owner.locations.index', [
            'locations' => $locations,
            // Danh sách mô hình để chọn khi thêm xe/quán mới (v1.3.0) — xem
            // config/business_types.php.
            'businessTypeOptions' => $catalog->options(),
        ]);
    }

    /**
     * v1.1.1 — Báo cáo tổng hợp TẤT CẢ xe của cùng 1 chủ quán, cộng dồn
     * doanh thu/giá vốn/lợi nhuận/chi phí vận hành. Tái dùng đúng công thức
     * của ReportController (xem summaryFor()) cho từng xe rồi cộng lại —
     * không viết lại logic tính toán ở đây. Chỉ owner mới có nhiều xe nên
     * không cần lo bị nhân viên truy cập nhầm (route đã nằm trong nhóm
     * role:owner).
     */
    public function report(Request $request, ReportController $reportController): View
    {
        $user = $request->user();
        [$period, $anchor] = $reportController->resolvePeriodAndAnchor($request);

        $locations = $user->ownedLocations()->get();
        $rows = $locations->map(fn ($loc) => $reportController->summaryFor($loc, $period, $anchor));

        $totals = [
            'revenue' => $rows->sum('revenue'),
            'cost' => $rows->sum('cost'),
            'profit' => $rows->sum('profit'),
            'labor' => $rows->sum('labor'),
            'rent' => $rows->sum('rent'),
            'netProfit' => $rows->sum('netProfit'),
        ];

        return view('owner.locations.report', [
            'period' => $period,
            'anchor' => $anchor,
            'rows' => $rows,
            'totals' => $totals,
        ]);
    }

    public function store(Request $request, BusinessTypeCatalog $catalog): RedirectResponse
    {
        $data = $request->validate([
            'name' => 'required|string|max:100',
            'address' => 'nullable|string|max:255',
            // Mô hình kinh doanh (v1.3.0) — chỉ đổi chữ/icon hiển thị, không bắt
            // buộc phải chọn đúng ngay từ đầu vì sửa lại được bất cứ lúc nào ở
            // Cài đặt. Giá trị lạ (không có trong config) thì coi như bỏ trống,
            // rơi về mặc định 'cafe' — validate ở đây chỉ để chặn input rác, không
            // để 1 khoá không hợp lệ lọt vào cột rồi hiện chữ mặc định sai chỗ.
            'business_type' => 'nullable|string|max:30',
        ]);

        $businessType = $catalog->isKnownType((string) ($data['business_type'] ?? '')) ? $data['business_type'] : 'cafe';
        $terms = $catalog->terms($businessType);

        $location = $request->user()->ownedLocations()->create([
            'name' => $data['name'],
            'address' => $data['address'] ?? null,
            'business_type' => $businessType,
        ]);
        $location->staff()->attach($request->user()->id);
        $location->categories()->create(['name' => $terms['default_category_name'], 'sort_order' => 0]);

        return redirect()->route('owner.locations.index')->with('status', 'Đã thêm '.mb_strtolower($terms['locations_label']).' mới.');
    }

    public function update(Request $request, int $location): RedirectResponse
    {
        $loc = $request->user()->ownedLocations()->findOrFail($location);

        $data = $request->validate([
            'name' => 'required|string|max:100',
            'address' => 'nullable|string|max:255',
        ]);

        $loc->update($data);

        return redirect()->route('owner.locations.index')->with('status', 'Đã cập nhật "'.$loc->name.'".');
    }

    public function switch(Request $request, int $location): RedirectResponse
    {
        $loc = $request->user()->ownedLocations()->findOrFail($location);
        $request->session()->put('current_location_id', $loc->id);

        return redirect()->route('pos.order');
    }
}
