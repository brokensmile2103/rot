<?php

namespace App\Http\Controllers\Owner;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Concerns\ResolvesCurrentLocation;
use App\Models\Ingredient;
use App\Models\Location;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class InventoryController extends Controller
{
    use ResolvesCurrentLocation;

    public function index(Request $request): View
    {
        $location = $this->currentLocation($request);

        // KHÔNG eager-load stockIns ở đây nữa — với quán hoạt động lâu năm,
        // tổng số lần nhập kho CỘNG DỒN của tất cả nguyên liệu có thể rất
        // lớn (mỗi lần mua hàng là 1 dòng, lặp lại vô thời hạn), trong khi
        // panel "Lịch sử" của từng nguyên liệu mặc định đang ẨN, phần lớn
        // không bao giờ được mở tới. Lịch sử được tải LƯỜI (lazy) qua
        // stockInHistory() bên dưới, đúng lúc người dùng bấm mở panel.
        $ingredients = $location->ingredients()
            ->orderBy('name')
            ->paginate(30)->withQueryString();
        $trashedIngredients = $location->ingredients()->onlyTrashed()->orderBy('name')->get();

        return view('owner.inventory.index', compact('location', 'ingredients', 'trashedIngredients'));
    }

    /**
     * Lịch sử nhập kho của 1 nguyên liệu — PHÂN TRANG, tải theo yêu cầu khi
     * người dùng mở panel "Lịch sử" (xem owner/inventory/index.blade.php),
     * thay vì tải sẵn toàn bộ lịch sử của MỌI nguyên liệu ngay từ lúc vào
     * trang Kho. Định dạng số liệu (money()/quantity()) NGAY TẠI SERVER
     * trước khi trả JSON — tránh viết lại 2 lần cùng 1 logic định dạng tiền
     * Việt Nam ở phía JavaScript.
     */
    public function stockInHistory(Request $request, int $ingredient): JsonResponse
    {
        $location = $this->currentLocation($request);
        $item = $location->ingredients()->withTrashed()->findOrFail($ingredient);

        $stockIns = $item->stockIns()->with('creator')->latest()->paginate(15);

        return response()->json([
            'items' => collect($stockIns->items())->map(fn ($s) => [
                'id' => $s->id,
                'quantity' => quantity($s->quantity).' '.$item->unit,
                'total_cost' => money($s->total_cost, 0).'đ',
                'unit_cost' => money((float) $s->total_cost / max((float) $s->quantity, 0.01), 0).'đ/'.$item->unit,
                'created_at' => $s->created_at->format('d/m/Y H:i'),
                'creator_name' => $s->creator->name ?? 'Không rõ',
                'note' => $s->note,
            ]),
            'next_page' => $stockIns->hasMorePages() ? $stockIns->currentPage() + 1 : null,
        ]);
    }

    public function storeIngredient(Request $request): RedirectResponse
    {
        $location = $this->currentLocation($request);

        $data = $request->validate([
            'name' => 'required|string|max:100',
            'unit' => 'required|string|max:20',
            'low_stock_threshold' => 'nullable|numeric|min:0',
            'initial_quantity' => 'nullable|numeric|min:0',
            'initial_cost' => 'nullable|numeric|min:0',
        ]);

        $item = $location->ingredients()->create([
            'name' => $data['name'],
            'unit' => $data['unit'],
            'low_stock_threshold' => $data['low_stock_threshold'] ?? 0,
        ]);

        // Cho nhập tồn kho + giá vốn ban đầu ngay lúc tạo, để "Giá vốn TB" có số
        // đúng ngay từ đầu thay vì hiển thị 0đ cho tới lần nhập kho đầu tiên.
        if (! empty($data['initial_quantity']) && isset($data['initial_cost'])) {
            $item->stockIns()->create([
                'quantity' => $data['initial_quantity'],
                'total_cost' => $data['initial_cost'],
                'note' => 'Tồn kho ban đầu',
                'created_by' => $request->user()->id,
            ]);
            $item->receiveStock((float) $data['initial_quantity'], (float) $data['initial_cost']);
        }

        return back()->with('status', 'Đã thêm nguyên liệu.');
    }

    /**
     * Cho sửa TRỰC TIẾP cả tồn kho + giá vốn TB (ngoài tên/đơn vị/ngưỡng cảnh
     * báo) — cần thiết vì Thiết lập nhanh tạo sẵn số liệu MẪU (giá tham
     * khảo thị trường, không phải giá thật của từng quán), và người dùng
     * hoàn toàn có thể gõ nhầm lúc "Thêm nguyên liệu mới" hoặc kiểm kê thực
     * tế lệch với sổ sách. "Nhập kho" chỉ CỘNG THÊM (đúng bản chất 1 lần mua
     * hàng), không sửa lại được số liệu NỀN đã sai — sửa tay tại đây mới là
     * đúng công cụ cho việc đó.
     *
     * CỐ Ý không ghi log vào Lịch sử nhập kho: đây là SỬA LẠI số liệu (không
     * phải 1 giao dịch mua hàng thật), ghi thành 1 dòng "nhập kho" giả sẽ gây
     * hiểu lầm khi xem lại lịch sử sau này.
     */
    public function updateIngredient(Request $request, int $ingredient): RedirectResponse
    {
        $location = $this->currentLocation($request);
        $item = $location->ingredients()->findOrFail($ingredient);

        $data = $request->validate([
            'name' => 'required|string|max:100',
            'unit' => 'required|string|max:20',
            'low_stock_threshold' => 'nullable|numeric|min:0',
            'current_stock' => 'required|numeric|min:0',
            'avg_cost_per_unit' => 'required|numeric|min:0',
        ]);
        $data['low_stock_threshold'] = $data['low_stock_threshold'] ?? 0;

        $item->update($data);

        return back()->with('status', 'Đã cập nhật nguyên liệu.');
    }

    /**
     * Xoá mềm — công thức/lịch sử nhập kho cũ dùng nguyên liệu này vẫn giữ nguyên,
     * chỉ ẩn khỏi danh sách chọn khi gán công thức món mới.
     */
    public function deleteIngredient(Request $request, int $ingredient): RedirectResponse
    {
        $location = $this->currentLocation($request);
        $item = $location->ingredients()->findOrFail($ingredient);
        $item->delete();

        return back()->with('status', 'Đã xoá "'.$item->name.'" (có thể khôi phục lại).');
    }

    public function restoreIngredient(Request $request, int $ingredient): RedirectResponse
    {
        $location = $this->currentLocation($request);
        $item = $location->ingredients()->onlyTrashed()->findOrFail($ingredient);
        $item->restore();

        return back()->with('status', 'Đã khôi phục "'.$item->name.'".');
    }

    public function stockIn(Request $request, int $ingredient): RedirectResponse
    {
        $location = $this->currentLocation($request);
        $item = $location->ingredients()->findOrFail($ingredient);

        $data = $request->validate([
            'quantity' => 'required|numeric|min:0.01',
            'total_cost' => 'required|numeric|min:0',
            'note' => 'nullable|string|max:255',
        ]);

        $item->stockIns()->create([
            'quantity' => $data['quantity'],
            'total_cost' => $data['total_cost'],
            'note' => $data['note'] ?? null,
            'created_by' => $request->user()->id,
        ]);

        $item->receiveStock((float) $data['quantity'], (float) $data['total_cost']);

        return back()->with('status', 'Đã nhập kho '.$item->name.'.');
    }

}
