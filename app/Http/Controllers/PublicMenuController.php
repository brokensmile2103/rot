<?php

namespace App\Http\Controllers;

use App\Models\CustomerOrderRequest;
use App\Models\Location;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Trang KHÔNG cần đăng nhập — khách quét mã QR dán trên xe cà phê để tự xem
 * thực đơn và gửi yêu cầu gọi món. Đây CHỈ LÀ YÊU CẦU (xem docblock migration
 * customer_order_requests) — nhân viên vẫn phải bấm "Nhận đơn" rồi mới thật
 * sự tạo ra 1 đơn hàng, nên không có bước thanh toán nào ở đây cả.
 */
class PublicMenuController extends Controller
{
    public function show(string $token): View
    {
        $location = Location::where('public_token', $token)->where('qr_ordering_enabled', true)->firstOrFail();

        $categories = $location->categories()
            ->with(['products' => function ($q) {
                $q->where('is_available', true)->with(['variants', 'modifierGroups.modifiers']);
            }])
            ->orderBy('sort_order')
            ->get()
            // Ẩn luôn danh mục rỗng (toàn món hết hàng/chưa thêm món) — đỡ
            // khách bấm vào danh mục trống rồi tưởng app lỗi.
            ->filter(fn ($category) => $category->products->isNotEmpty())
            ->values();

        return view('public.menu', [
            'location' => $location,
            'categories' => $categories,
            'loyaltyEnabled' => $location->loyalty_enabled,
        ]);
    }

    public function store(Request $request, string $token): JsonResponse
    {
        $location = Location::where('public_token', $token)->where('qr_ordering_enabled', true)->firstOrFail();

        $data = $request->validate([
            'items' => 'required|array|min:1|max:30',
            'items.*.variant_id' => 'required|integer|exists:product_variants,id',
            'items.*.quantity' => 'required|integer|min:1|max:20',
            'items.*.modifier_ids' => 'nullable|array',
            'items.*.modifier_ids.*' => 'integer|exists:modifiers,id',
            'customer_name' => 'nullable|string|max:100',
            // Chỉ thật sự cần khi quán có bật tích điểm — nhưng vẫn validate
            // láng cho MỌI quán (không tin request), tự bỏ qua ở dưới nếu
            // location tắt loyalty, tránh việc gửi field thừa vô tình lưu vào
            // yêu cầu của quán không dùng tính năng này.
            'customer_phone' => 'nullable|string|max:20',
            'note' => 'nullable|string|max:150',
        ]);

        // Đối chiếu lại từng variant_id THẬT SỰ thuộc về đúng quán này (qua
        // token) — chặn trường hợp ai đó sửa payload để gửi variant_id của
        // quán KHÁC vào đây (dữ liệu từ client không bao giờ được tin thẳng).
        $validVariantIds = $location->categories()
            ->with('products.variants')
            ->get()
            ->flatMap(fn ($c) => $c->products)
            ->flatMap(fn ($p) => $p->variants)
            ->pluck('id');

        $items = collect($data['items'])->filter(
            fn ($item) => $validVariantIds->contains($item['variant_id'])
        )->values();

        if ($items->isEmpty()) {
            return response()->json(['message' => 'Món đã chọn không còn khả dụng, vui lòng chọn lại.'], 422);
        }

        CustomerOrderRequest::create([
            'location_id' => $location->id,
            'items' => $items->map(fn ($item) => [
                'variant_id' => (int) $item['variant_id'],
                'quantity' => (int) $item['quantity'],
                'modifier_ids' => array_values(array_unique($item['modifier_ids'] ?? [])),
            ])->all(),
            'customer_name' => $data['customer_name'] ?? null,
            'customer_phone' => $location->loyalty_enabled ? ($data['customer_phone'] ?? null) : null,
            'note' => $data['note'] ?? null,
        ]);

        return response()->json(['message' => 'Đã gửi yêu cầu — nhân viên sẽ xác nhận trong ít phút.']);
    }
}
