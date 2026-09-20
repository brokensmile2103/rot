<?php

namespace App\Http\Controllers\Owner;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Concerns\ResolvesCurrentLocation;
use App\Models\Ingredient;
use App\Models\Location;
use App\Models\StockAdjustment;
use App\Services\IngredientEditPlan;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
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
     * CỐ Ý không ghi vào Lịch sử nhập kho: đây là SỬA LẠI số liệu (không phải
     * 1 giao dịch mua hàng thật), ghi thành 1 dòng "nhập kho" giả sẽ gây hiểu
     * lầm khi xem lại lịch sử sau này.
     *
     * v1.1.3 — thay vào đó ghi vào NHẬT KÝ ĐIỀU CHỈNH KHO riêng (bảng
     * stock_adjustments): số liệu trước/sau, lý do, người sửa, thời điểm. Bắt
     * buộc chọn lý do khi thật sự đổi Tồn kho hoặc Giá vốn TB (đổi tên/đơn
     * vị/ngưỡng cảnh báo thì không cần và không ghi nhật ký).
     *
     * Toàn bộ chạy trong 1 transaction có KHOÁ dòng nguyên liệu (lockForUpdate):
     * số "trước" ghi vào nhật ký phải là số THẬT tại đúng thời điểm ghi, không
     * bị lệch do 1 đơn bán ra (trừ kho) chen vào giữa lúc đọc và lúc ghi. Xem
     * thêm IngredientEditPlan về việc không ghi đè tồn kho khi chỉ đổi tên.
     */
    public function updateIngredient(Request $request, int $ingredient): RedirectResponse
    {
        $location = $this->currentLocation($request);
        $location->ingredients()->findOrFail($ingredient); // 404 sớm nếu không thuộc quán/đã xoá

        $data = $request->validate([
            'name' => 'required|string|max:100',
            'unit' => 'required|string|max:20',
            'low_stock_threshold' => 'nullable|numeric|min:0',
            'current_stock' => 'required|numeric|min:0',
            'avg_cost_per_unit' => 'required|numeric|min:0',
            'original_current_stock' => 'nullable|numeric|min:0',
            'original_avg_cost_per_unit' => 'nullable|numeric|min:0',
            'adjust_reason' => ['nullable', Rule::in(array_keys(StockAdjustment::REASONS))],
            'adjust_note' => 'nullable|string|max:255',
        ]);

        return DB::transaction(function () use ($request, $location, $ingredient, $data) {
            $item = $location->ingredients()->whereKey($ingredient)->lockForUpdate()->firstOrFail();

            $plan = IngredientEditPlan::make(
                dbStock: (float) $item->current_stock,
                dbCost: (float) $item->avg_cost_per_unit,
                submittedStock: (float) $data['current_stock'],
                submittedCost: (float) $data['avg_cost_per_unit'],
                originalStock: isset($data['original_current_stock']) ? (float) $data['original_current_stock'] : null,
                originalCost: isset($data['original_avg_cost_per_unit']) ? (float) $data['original_avg_cost_per_unit'] : null,
            );

            $reason = $data['adjust_reason'] ?? null;
            $note = trim((string) ($data['adjust_note'] ?? ''));

            if ($plan['changed']) {
                $errors = [];
                if (! $reason) {
                    $errors['adjust_reason'] = 'Chọn lý do khi sửa Tồn kho / Giá vốn TB để ghi vào nhật ký điều chỉnh kho.';
                } elseif ($reason === StockAdjustment::REASON_NEEDS_NOTE && $note === '') {
                    $errors['adjust_note'] = 'Chọn "Lý do khác" thì cần ghi chú ngắn cho biết lý do cụ thể.';
                }

                if ($errors) {
                    // Giữ form mở lại đúng nguyên liệu này với dữ liệu vừa gõ, thay vì bắt gõ lại từ đầu.
                    return back()->withInput()->withErrors($errors)->with('open_edit', $item->id);
                }
            }

            // Chụp số "trước" TRƯỚC khi update(): sau update() Eloquent đã đồng bộ lại
            // bản gốc (getOriginal() trả về số MỚI), lấy sau sẽ ghi nhật ký sai "trước = sau".
            $stockBefore = (float) $item->current_stock;
            $costBefore = (float) $item->avg_cost_per_unit;

            $item->update([
                'name' => $data['name'],
                'unit' => $data['unit'],
                'low_stock_threshold' => $data['low_stock_threshold'] ?? 0,
                'current_stock' => $plan['stock'],
                'avg_cost_per_unit' => $plan['cost'],
            ]);

            if ($plan['changed']) {
                StockAdjustment::create([
                    'location_id' => $location->id,
                    'ingredient_id' => $item->id,
                    'user_id' => $request->user()->id,
                    'reason' => $reason,
                    'stock_before' => $stockBefore,
                    'stock_after' => $plan['stock'],
                    'cost_before' => $costBefore,
                    'cost_after' => $plan['cost'],
                    'note' => $note !== '' ? $note : null,
                ]);
            }

            return back()->with('status', $plan['changed']
                ? 'Đã cập nhật nguyên liệu và ghi vào nhật ký điều chỉnh kho.'
                : 'Đã cập nhật nguyên liệu.');
        });
    }

    /**
     * Nhật ký điều chỉnh của 1 nguyên liệu — PHÂN TRANG, tải theo yêu cầu khi
     * mở tab "Điều chỉnh" trong panel Lịch sử (cùng cách với stockInHistory()).
     */
    public function adjustmentHistory(Request $request, int $ingredient): JsonResponse
    {
        $location = $this->currentLocation($request);
        $item = $location->ingredients()->withTrashed()->findOrFail($ingredient);

        $rows = $item->stockAdjustments()->with('user')->latest('id')->paginate(15);

        return response()->json([
            'items' => collect($rows->items())->map(fn (StockAdjustment $a) => $a->display($item->unit)),
            'next_page' => $rows->hasMorePages() ? $rows->currentPage() + 1 : null,
        ]);
    }

    /**
     * Trang NHẬT KÝ ĐIỀU CHỈNH KHO của cả quán: ai sửa tồn kho/giá vốn nguyên
     * liệu nào, lúc nào, từ bao nhiêu thành bao nhiêu, vì sao — kèm tổng giá trị
     * thiếu hụt/dư ước tính để thấy hao hụt kho đang ở mức nào.
     */
    public function adjustments(Request $request): View
    {
        $location = $this->currentLocation($request);

        $ingredientId = (int) $request->query('ingredient', 0);
        $reason = array_key_exists((string) $request->query('reason'), StockAdjustment::REASONS) ? $request->query('reason') : null;
        $days = in_array((int) $request->query('days', 30), [30, 90, 365, 0], true) ? (int) $request->query('days', 30) : 30;

        $query = StockAdjustment::query()
            ->where('location_id', $location->id)
            ->when($ingredientId > 0, fn ($q) => $q->where('ingredient_id', $ingredientId))
            ->when($reason, fn ($q) => $q->where('reason', $reason))
            ->when($days > 0, fn ($q) => $q->where('created_at', '>=', now()->subDays($days)->startOfDay()));

        // Chỉ cộng các lý do phản ánh chênh lệch HÀNG THẬT (xem StockAdjustment::PHYSICAL_REASONS).
        // Dùng CASE WHEN thay vì hàm riêng của MySQL để câu lệnh chạy được trên mọi CSDL.
        $summary = (clone $query)
            ->whereIn('reason', StockAdjustment::PHYSICAL_REASONS)
            ->selectRaw('
                COALESCE(SUM(CASE WHEN stock_after < stock_before THEN (stock_before - stock_after) * cost_before ELSE 0 END), 0) AS shortage_value,
                COALESCE(SUM(CASE WHEN stock_after > stock_before THEN (stock_after - stock_before) * cost_before ELSE 0 END), 0) AS surplus_value,
                COUNT(*) AS physical_count
            ')
            ->first();

        $adjustments = (clone $query)
            ->with(['ingredient', 'user'])
            ->latest('id')
            ->paginate(30)
            ->withQueryString();

        $ingredientOptions = $location->ingredients()->withTrashed()->orderBy('name')->get(['id', 'name', 'deleted_at']);

        return view('owner.inventory.adjustments', [
            'location' => $location,
            'adjustments' => $adjustments,
            'ingredientOptions' => $ingredientOptions,
            'reasons' => StockAdjustment::REASONS,
            'filters' => ['ingredient' => $ingredientId, 'reason' => $reason, 'days' => $days],
            'shortageValue' => (float) $summary->shortage_value,
            'surplusValue' => (float) $summary->surplus_value,
            'physicalCount' => (int) $summary->physical_count,
        ]);
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
