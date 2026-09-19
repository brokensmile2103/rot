<?php

namespace App\Http\Controllers\Owner;

use App\Http\Controllers\Controller;
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
    public function index(Request $request): View
    {
        $locations = $request->user()->ownedLocations()->withCount('categories')->get();

        return view('owner.locations.index', compact('locations'));
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

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => 'required|string|max:100',
            'address' => 'nullable|string|max:255',
        ]);

        $location = $request->user()->ownedLocations()->create($data);
        $location->staff()->attach($request->user()->id);
        $location->categories()->create(['name' => 'Cà phê', 'sort_order' => 0]);

        return redirect()->route('owner.locations.index')->with('status', 'Đã thêm xe cà phê mới.');
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
