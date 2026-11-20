<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Location;
use App\Models\Order;
use App\Models\Product;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\View\View;

/**
 * CLOUD ONLY — khu vực Super Admin, quản lý TOÀN BỘ quán đã đăng ký trên
 * nền tảng. Không thuộc phạm vi 1 location cụ thể nào, nên KHÔNG dùng
 * currentLocation()/session('current_location_id') như các controller khác.
 */
class DashboardController extends Controller
{
    public function index(Request $request): View
    {
        $locations = Location::withCount(['staff'])
            ->with('owner')
            // Ngày đơn HOÀN THÀNH gần nhất — 1 phép JOIN tổng hợp duy nhất
            // cho CẢ TRANG (không phải 1 query/quán), để nhận biết quán nào
            // đang "im lìm" ngay trên danh sách mà không cần bấm vào từng
            // quán. CHỈ lấy đúng 1 mốc thời gian, không load nội dung đơn.
            ->withMax('completedOrders as last_completed_order_at', 'completed_at')
            ->when($request->query('q'), function ($q, $search) {
                $q->where(fn ($q2) => $q2->where('name', 'like', "%{$search}%")
                    ->orWhereHas('owner', fn ($q3) => $q3->where('phone', 'like', "%{$search}%")->orWhere('name', 'like', "%{$search}%")));
            })
            ->latest('id')
            ->paginate(20)
            ->withQueryString();

        $stats = [
            'total' => Location::count(),
            'active' => Location::where('is_active', true)->count(),
            'inactive' => Location::where('is_active', false)->count(),
        ];

        return view('admin.dashboard', compact('locations', 'stats'));
    }

    /**
     * Chi tiết 1 quán — CHỈ để Super Admin đánh giá quán còn hoạt động hay
     * không (hỗ trợ vận hành nền tảng: phát hiện quán "chết", ưu tiên hỗ
     * trợ, đối soát gói dịch vụ...), KHÔNG phải để xem/duyệt dữ liệu kinh
     * doanh của quán.
     *
     * CỐ TÌNH chỉ trả về SỐ LƯỢNG/TRUNG BÌNH (đếm đơn, đếm ca, cờ bật/tắt
     * tính năng...), tuyệt đối KHÔNG load nội dung đơn hàng (món gì, giá
     * bao nhiêu), KHÔNG load thông tin khách hàng (SĐT, tên, điểm), KHÔNG
     * load doanh thu/giá vốn — dữ liệu bán hàng thật của từng quán không
     * thuộc phạm vi Super Admin cần xử lý để vận hành nền tảng, tránh vượt
     * quá mục đích xử lý dữ liệu của khách hàng B2B (giữ đúng nguyên tắc
     * data minimization).
     */
    public function show(Location $location): View
    {
        $location->load('owner');

        $ordersQuery = Order::where('location_id', $location->id);

        $countsByStatus = (clone $ordersQuery)
            ->selectRaw('status, count(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');

        $totalCompleted = (int) ($countsByStatus['hoan_thanh'] ?? 0);
        $totalCancelled = (int) ($countsByStatus['da_huy'] ?? 0);
        $totalDrafts = (int) ($countsByStatus['nhap'] ?? 0);

        $orderCounts = [
            'completed' => $totalCompleted,
            'cancelled' => $totalCancelled,
            'draft' => $totalDrafts,
            'total' => $totalCompleted + $totalCancelled + $totalDrafts,
        ];

        // Nhịp bán hàng — chỉ tính trên đơn ĐÃ HOÀN THÀNH, vì đơn nháp/đã huỷ
        // không phản ánh hoạt động bán thật. Trả về null nếu quán CHƯA từng
        // bán được đơn nào (view sẽ hiện "Chưa phát sinh đơn hàng" thay vì
        // chia cho 0 hoặc suy diễn 1 con số vô nghĩa).
        $activity = $this->buildActivitySummary($ordersQuery);

        return view('admin.locations.show', [
            'location' => $location,
            'orderCounts' => $orderCounts,
            'activity' => $activity,
            'stats' => [
                'staff_count' => $location->staff()->count(),
                'category_count' => $location->categories()->count(),
                'product_count' => Product::whereHas(
                    'category', fn ($q) => $q->where('location_id', $location->id)
                )->count(),
                'ingredient_count' => $location->ingredients()->count(),
                'customer_count' => $location->customers()->count(),
                'shift_count' => $location->shifts()->count(),
                'has_open_shift' => $location->shifts()->whereNull('closed_at')->exists(),
                'last_shift_closed_at' => ($lastClosedAt = $location->shifts()->whereNotNull('closed_at')->max('closed_at'))
                    ? Carbon::parse($lastClosedAt)
                    : null,
            ],
            'features' => [
                'loyalty_enabled' => (bool) $location->loyalty_enabled,
                'qr_ordering_enabled' => (bool) $location->qr_ordering_enabled,
                'receipt_enabled' => (bool) $location->receipt_enabled,
                'einvoice_configured' => $location->hasEinvoiceConfig(),
                'bank_account_configured' => $location->hasBankAccount(),
            ],
        ]);
    }

    /**
     * Trung bình đơn/ngày-tuần-tháng tính trên SUỐT VÒNG ĐỜI quán (tổng đơn
     * hoàn thành / số ngày kể từ đơn đầu tiên tới hôm nay) — phản ánh nhịp
     * bán TRUNG BÌNH DÀI HẠN. Kèm thêm số đơn 7 ngày / 30 ngày gần nhất
     * (đếm THẬT, không suy ra từ trung bình) để nhận biết quán ĐANG hoạt
     * động hay đã ngừng bán từ lâu — 1 quán từng bán tốt rồi ngưng hẳn vẫn
     * có thể có trung bình dài hạn "đẹp" dù thực tế đã ngưng hoạt động.
     */
    private function buildActivitySummary(Builder $ordersQuery): ?array
    {
        $completed = (clone $ordersQuery)->where('status', 'hoan_thanh');

        $firstOrderAt = $completed->clone()->min('completed_at');
        if (! $firstOrderAt) {
            return null;
        }

        $firstOrderAt = Carbon::parse($firstOrderAt);
        $lastOrderAt = Carbon::parse($completed->clone()->max('completed_at'));
        $totalCompleted = $completed->clone()->count();

        // +1 để ngày đơn đầu tiên cũng được tính là 1 ngày hoạt động (không
        // lấy diffInDays thô làm mẫu số, sẽ hụt mất đúng 1 ngày).
        // days_between() đếm theo NGÀY LỊCH (số nguyên) — diffInDays() thô của Carbon 3 là số thực.
        $daysActive = max(1, days_between($firstOrderAt) + 1);
        $avgPerDay = $totalCompleted / $daysActive;

        return [
            'first_order_at' => $firstOrderAt,
            'last_order_at' => $lastOrderAt,
            'days_since_last_order' => max(0, days_between($lastOrderAt)),
            'days_active' => $daysActive,
            'avg_per_day' => $avgPerDay,
            'avg_per_week' => $avgPerDay * 7,
            'avg_per_month' => $avgPerDay * 30,
            'last_7_days' => $completed->clone()->where('completed_at', '>=', Carbon::now()->subDays(7))->count(),
            'last_30_days' => $completed->clone()->where('completed_at', '>=', Carbon::now()->subDays(30))->count(),
        ];
    }

    /** Khoá/mở lại 1 quán — quán bị khoá thì chủ quán/nhân viên không đăng nhập vào bán hàng được nữa (xem LoginController). */
    public function toggleActive(Location $location): RedirectResponse
    {
        $location->forceFill(['is_active' => ! $location->is_active])->save();

        $status = $location->is_active ? 'mở lại' : 'tạm khoá';

        return back()->with('status', 'Đã '.$status.' quán "'.$location->name.'".');
    }
}
