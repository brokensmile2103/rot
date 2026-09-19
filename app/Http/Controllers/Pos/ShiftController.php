<?php

namespace App\Http\Controllers\Pos;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Concerns\ResolvesCurrentLocation;
use App\Models\Location;
use App\Services\QuickSetupService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ShiftController extends Controller
{
    use ResolvesCurrentLocation;

    /**
     * v1.1.1 — Gợi ý sẵn "Tiền mặt đầu ca" theo đúng tiền cuối ca ĐÃ ĐẾM
     * THỰC TẾ của ca gần nhất vừa chốt (bất kể ai đứng ca đó) — vì về mặt vật
     * lý, tiền trong ngăn kéo lúc bàn giao chính là con số đó. CHỈ LÀ GỢI Ý:
     * vẫn bắt buộc người mở ca tự xem/gõ lại số thật đang có trong tay, không
     * tự động điền cứng và cho qua — giữ đúng tinh thần "mỗi người tự chịu
     * trách nhiệm với số tiền lúc bắt đầu ca của mình" (xem docblock
     * Shift::expectedCash()), chỉ đỡ phải tính nhẩm/hỏi lại người ca trước.
     */
    public function create(Request $request, QuickSetupService $quickSetup): View
    {
        $location = $this->currentLocation($request, false);
        $showQuickSetup = auth()->user()?->isOwner() && ! $quickSetup->hasExistingMenu($location);

        $lastClosedShift = $location->shifts()
            ->whereNotNull('closed_at')
            ->with('user:id,name')
            ->latest('closed_at')
            ->first();

        return view('pos.shift-open', compact('location', 'showQuickSetup', 'lastClosedShift'));
    }

    public function store(Request $request): RedirectResponse
    {
        $location = $this->currentLocation($request, false);

        $data = $request->validate([
            'opening_cash' => 'required|numeric|min:0',
        ]);

        $location->shifts()->create([
            'user_id' => $request->user()->id,
            'opening_cash' => $data['opening_cash'],
            'opened_at' => now(),
        ]);

        return redirect()->route('pos.order');
    }

    public function showClose(Request $request): View|RedirectResponse
    {
        $location = $this->currentLocation($request, false);
        $shift = $location->openShiftFor($request->user());

        if (! $shift) {
            return redirect()->route('shift.create');
        }

        return view('pos.shift-close', [
            'shift' => $shift,
            'expected' => $shift->expectedCash(),
        ]);
    }

    public function close(Request $request): RedirectResponse
    {
        $location = $this->currentLocation($request, false);
        $shift = $location->openShiftFor($request->user());
        abort_if(! $shift, 404);

        $data = $request->validate([
            'closing_cash_actual' => 'required|numeric|min:0',
        ]);

        $expected = $shift->expectedCash();

        $shift->update([
            'closing_cash_expected' => $expected,
            'closing_cash_actual' => $data['closing_cash_actual'],
            'variance' => $data['closing_cash_actual'] - $expected,
            'closed_at' => now(),
        ]);

        // Đưa sang màn "Kết quả chốt ca" thay vì thẳng tới mở ca mới — chênh
        // lệch quỹ trước đây chỉ nằm lọt trong 1 dòng flash message chữ nhỏ,
        // rất dễ bị bỏ lỡ đúng lúc quan trọng nhất (giao ca, tiền mặt cầm tay
        // đã đếm xong). Giờ hiện hẳn 1 màn riêng, màu sắc rõ ràng, rồi mới cho
        // đi tiếp — xem closeResult() bên dưới.
        return redirect()->route('shift.close.result', $shift->id);
    }

    /**
     * Màn "Kết quả chốt ca" — hiện NGAY sau khi chốt, để người đứng ca thấy rõ
     * chênh lệch quỹ trong lúc còn đang cầm tiền mặt trên tay (dễ đối chiếu
     * lại ngay nếu lệch, thay vì phải vào Lịch sử ca tìm lại sau). Dùng
     * chung logic phân quyền với historyShow() — chủ quán xem được ca của cả
     * nhân viên khác, nhân viên chỉ xem ca của chính mình.
     */
    public function closeResult(Request $request, int $shift): View
    {
        $location = $this->currentLocation($request, false);
        $user = $request->user();

        $shiftModel = $location->shifts()->whereNotNull('closed_at')->findOrFail($shift);
        abort_unless($user->isOwner() || $shiftModel->user_id === $user->id, 403);

        return view('pos.shift-close-result', ['shift' => $shiftModel]);
    }

    /** Danh sách các ca ĐÃ CHỐT — chủ quán xem được tất cả, nhân viên chỉ xem ca của chính mình. Lọc được theo ngày mở ca. */
    public function history(Request $request): View
    {
        $location = $this->currentLocation($request, false);
        $user = $request->user();

        $query = $location->shifts()
            ->whereNotNull('closed_at')
            ->with('user')
            ->withSum(['orders as revenue' => fn ($q) => $q->where('status', 'hoan_thanh')], 'total');

        if (! $user->isOwner()) {
            $query->where('user_id', $user->id);
        }

        if ($request->filled('date')) {
            $query->whereDate('opened_at', $request->query('date'));
        }

        $shifts = $query->latest('opened_at')->paginate(20)->withQueryString();

        return view('pos.shift-history', [
            'location' => $location,
            'shifts' => $shifts,
            'date' => $request->query('date'),
        ]);
    }

    /** Chi tiết 1 ca đã chốt — xem lại đơn hàng, KHÔNG sửa/huỷ được vì ca đã khoá sổ. */
    public function historyShow(Request $request, int $shift): View
    {
        $location = $this->currentLocation($request, false);
        $user = $request->user();

        $shiftModel = $location->shifts()
            ->whereNotNull('closed_at')
            ->with('user')
            ->findOrFail($shift);

        abort_unless($user->isOwner() || $shiftModel->user_id === $user->id, 403);

        // Doanh thu tính trên TOÀN BỘ đơn hoàn thành của ca (không phụ thuộc
        // trang đang xem) — dùng SUM ở tầng DB, không tải hết đơn vào PHP
        // chỉ để cộng dồn.
        $revenue = (float) $shiftModel->orders()->where('status', 'hoan_thanh')->sum('total');

        // Danh sách đơn để hiển thị thì PHÂN TRANG — 1 ca bán cực đông hoặc
        // kéo dài nhiều ngày (quên chốt ca) có thể phát sinh rất nhiều đơn,
        // tải hết 1 lần kèm items/variant/product/customer lồng nhau sẽ rất
        // nặng.
        $orders = $shiftModel->orders()->where('status', '!=', 'nhap')
            ->with('items.variant.product', 'customer')
            ->latest('id')
            ->paginate(20)->withQueryString();

        return view('pos.shift-history-detail', [
            'location' => $location,
            'shift' => $shiftModel,
            'orders' => $orders,
            'revenue' => $revenue,
        ]);
    }

}
