<?php

namespace App\Http\Controllers\Pos;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Concerns\ResolvesCurrentLocation;
use App\Models\Customer;
use App\Models\CustomerOrderRequest;
use App\Models\Location;
use App\Models\Modifier;
use App\Models\ModifierRecipe;
use App\Models\Order;
use App\Models\ProductVariant;
use App\Models\Recipe;
use App\Services\QuickSetupService;
use App\Services\SePayEInvoiceService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/**
 * Màn hình order — trung tâm của cả app. Ưu tiên tốc độ: 1 request checkout
 * duy nhất, giá luôn tính lại từ DB (không tin số tiền client gửi lên) để
 * tránh gian lận sửa giá qua devtools.
 */
class OrderController extends Controller
{
    use ResolvesCurrentLocation;

    public function __construct(
        private SePayEInvoiceService $einvoiceService,
        private QuickSetupService $quickSetup,
    ) {
    }

    public function index(Request $request): View|RedirectResponse
    {
        $location = $this->currentLocation($request, false);
        $shift = $location->openShiftFor($request->user());

        if (! $shift) {
            return redirect()->route('shift.create')->with('status', 'Mở ca trước khi bắt đầu bán nhé.');
        }

        $categories = $this->loadCategories($location);
        $draftCount = $shift->orders()->where('status', 'nhap')->count();

        return view('pos.order', [
            'location' => $location,
            'shift' => $shift,
            'categories' => $categories,
            'editingOrder' => null,
            'initialCart' => [],
            'draftCount' => $draftCount,
            // Dùng ĐÚNG 1 định nghĩa "chưa có thực đơn thật" với trang Cài đặt
            // (QuickSetupService coi món placeholder "Cà phê đen" tự tạo lúc
            // đăng ký là CHƯA thiết lập gì) — tránh case món placeholder vẫn
            // đang is_available=true khiến banner này không bao giờ hiện ra.
            'showQuickSetupBanner' => ! $this->quickSetup->hasExistingMenu($location),
        ]);
    }

    public function checkout(Request $request): JsonResponse
    {
        $location = $this->currentLocation($request, false);
        $shift = $location->openShiftFor($request->user());

        if (! $shift) {
            return response()->json(['message' => 'Ca đã đóng, vui lòng mở ca mới.'], 422);
        }

        $data = $this->validateCart($request);

        $order = DB::transaction(function () use ($data, $location, $shift, $request) {
            [$subtotal, $itemsToCreate] = $this->buildItemsFromCart($data['items'], $location);
            $discountAmount = $this->calculateDiscount($subtotal, $data['discount_type'] ?? null, $data['discount_value'] ?? null);
            $amountAfterDiscount = $subtotal - $discountAmount;

            [$customer, $pointsRedeemed, $pointsRedeemedValue] = $this->resolveCustomerAndRedemption(
                $location, $data['customer_phone'] ?? null, $data['customer_name'] ?? null,
                $data['redeem_points'] ?? null, $amountAfterDiscount
            );

            $total = $amountAfterDiscount - $pointsRedeemedValue;
            $pointsEarned = $this->calculatePointsEarned($location, $customer, $total);

            $order = Order::create([
                'location_id' => $location->id,
                'customer_id' => $customer?->id,
                'shift_id' => $shift->id,
                'order_type' => $data['order_type'],
                'guest_count' => $data['guest_count'] ?? null,
                'status' => 'hoan_thanh',
                'payment_method' => $data['payment_method'],
                'subtotal' => $subtotal,
                'discount_type' => $data['discount_type'] ?? null,
                'discount_value' => $data['discount_value'] ?? null,
                'discount_amount' => $discountAmount,
                'points_earned' => $pointsEarned,
                'points_redeemed' => $pointsRedeemed,
                'points_redeemed_value' => $pointsRedeemedValue,
                'total' => $total,
                'created_by' => $request->user()->id,
                'completed_at' => now(),
            ]);

            $this->createOrderItems($order, $itemsToCreate);
            $customer?->applyOrder($pointsEarned, $pointsRedeemed, (float) $total);

            return $order;
        });

        // Gọi SAU khi transaction đã commit — gọi API bên ngoài (mạng có thể
        // chậm/lỗi) không bao giờ được giữ transaction DB mở. Nếu SePay lỗi,
        // đơn hàng vẫn đã bán thành công, chỉ đánh dấu hoá đơn 'failed' để
        // xuất lại sau (xem OrderController::retryEinvoice).
        $this->einvoiceService->createInvoice($order);

        $change = null;
        if ($data['payment_method'] === 'tien_mat' && isset($data['cash_received'])) {
            $change = max(0, (float) $data['cash_received'] - (float) $order->total);
        }

        return response()->json([
            'order_id' => $order->id,
            'total' => (float) $order->total,
            'change' => $change,
        ]);
    }

    /**
     * Lưu đơn NHÁP — giữ chỗ cho khách đang chờ lâu, để bán tiếp cho khách
     * khác mà không mất nội dung đơn cũ. KHÔNG trừ kho, KHÔNG tính giá vốn —
     * việc này chỉ xảy ra khi đơn thực sự hoàn tất (xem update()).
     */
    public function saveDraft(Request $request): JsonResponse
    {
        $location = $this->currentLocation($request, false);
        $shift = $location->openShiftFor($request->user());

        if (! $shift) {
            return response()->json(['message' => 'Ca đã đóng, vui lòng mở ca mới.'], 422);
        }

        $data = $request->validate([
            'items' => 'required|array|min:1',
            'items.*.variant_id' => 'required|exists:product_variants,id',
            'items.*.quantity' => 'required|integer|min:1|max:99',
            'items.*.modifier_ids' => 'nullable|array',
            'items.*.modifier_ids.*' => 'exists:modifiers,id',
            'items.*.discount_type' => 'nullable|in:percent,amount',
            'items.*.discount_value' => 'nullable|numeric|min:0',
            'note' => 'nullable|string|max:100',
        ]);

        $order = DB::transaction(function () use ($data, $location, $shift, $request) {
            [$subtotal, $itemsToCreate] = $this->buildItemsFromCart($data['items'], $location);

            $order = Order::create([
                'location_id' => $location->id,
                'shift_id' => $shift->id,
                'order_type' => 'mang_di',
                'note' => $data['note'] ?? null,
                'status' => 'nhap',
                'payment_method' => 'tien_mat',
                'subtotal' => $subtotal,
                'total' => $subtotal,
                'created_by' => $request->user()->id,
            ]);

            $this->createDraftItems($order, $itemsToCreate);

            return $order;
        });

        return response()->json(['order_id' => $order->id]);
    }

    /** Danh sách đơn trong ca ĐANG MỞ hiện tại — nơi bắt đầu để sửa/huỷ đơn, tiếp tục đơn nháp. */
    public function orders(Request $request): View|RedirectResponse
    {
        $location = $this->currentLocation($request, false);
        $shift = $location->openShiftFor($request->user());

        if (! $shift) {
            return redirect()->route('shift.create')->with('status', 'Mở ca để xem danh sách đơn.');
        }

        // Đơn nháp — chỉ là những đơn ĐANG GIỮ CHỖ chờ khách, số lượng luôn
        // nhỏ tại bất kỳ thời điểm nào nên không cần phân trang. Tách hẳn
        // truy vấn này khỏi đơn đã hoàn thành/huỷ bên dưới, để không phải
        // tải TOÀN BỘ đơn của ca vào bộ nhớ chỉ để lọc ra vài đơn nháp.
        $drafts = $shift->orders()->where('status', 'nhap')
            ->with(['items.variant.product', 'customer'])->latest('id')->get();

        // Đơn đã hoàn thành/huỷ — CÓ THỂ rất nhiều nếu ca kéo dài (VD: quên
        // chốt ca nhiều ngày liền, hoặc ngày bán cực đông), nên PHÂN TRANG
        // thay vì tải hết một lần như trước đây.
        $orders = $shift->orders()->where('status', '!=', 'nhap')
            ->with(['items.variant.product', 'customer'])->latest('id')
            ->paginate(20)->withQueryString();

        // Yêu cầu gọi món khách gửi qua QR — hiện cho MỌI nhân viên đang bán
        // ở quán này (không giới hạn theo ca, vì khách gửi yêu cầu không biết
        // và không cần biết ai đang đứng ca) đang chờ được nhận hoặc từ chối.
        $customerRequests = $location->qr_ordering_enabled
            ? $location->customerOrderRequests()->where('status', 'cho_xac_nhan')->latest()->get()
            : collect();
        $this->attachReadableItemLabels($customerRequests);

        return view('pos.orders', compact('location', 'shift', 'orders', 'drafts', 'customerRequests'));
    }

    /**
     * Endpoint cho cơ chế polling (v1.1.3) — trình duyệt của nhân viên/chủ quán gọi
     * định kỳ (xem resources/js/qr-requests.js) để lấy SỐ yêu cầu gọi món từ khách
     * (QR) đang chờ nhận, rồi tự cập nhật badge menu, thanh thông báo ở màn Order và
     * tiêu đề tab mà không cần tải lại trang.
     *
     * Chỉ trả về đúng 1 con số (và cờ bật/tắt tính năng) — KHÔNG trả nội dung
     * món/ghi chú/SĐT khách: chi tiết chỉ hiện ở trang "Đơn hàng" sau khi bấm vào xem.
     */
    public function pendingCustomerRequests(Request $request): JsonResponse
    {
        $location = $this->currentLocation($request, false);

        $pendingCount = $location->qr_ordering_enabled
            ? $location->customerOrderRequests()->where('status', 'cho_xac_nhan')->count()
            : 0;

        return response()->json([
            'enabled' => (bool) $location->qr_ordering_enabled,
            'pending_count' => $pendingCount,
        ])->header('Cache-Control', 'no-store, no-cache, must-revalidate');
    }

    /**
     * Nhân viên "Nhận đơn" 1 yêu cầu khách gửi qua QR — mở màn Order BÌNH
     * THƯỜNG (không phải sửa đơn, $editingOrder = null) với giỏ hàng nạp sẵn
     * từ yêu cầu đó, để nhân viên rà lại lần cuối (món còn hàng không, có
     * cần thêm/bớt gì không) rồi tự tay bấm thanh toán — KHÔNG tự động tạo
     * đơn hàng thẳng từ yêu cầu, vì khách có thể chọn nhầm/món vừa hết hàng.
     */
    public function acceptCustomerRequest(Request $request, int $customerRequest): View|RedirectResponse
    {
        $location = $this->currentLocation($request, false);
        $shift = $location->openShiftFor($request->user());

        if (! $shift) {
            return redirect()->route('shift.create')->with('status', 'Mở ca trước khi nhận đơn nhé.');
        }

        // Không dùng findOrFail(): giờ có thông báo realtime nên 2 thiết bị (2 nhân
        // viên, hoặc chủ quán + nhân viên) rất dễ cùng bấm "Nhận đơn" 1 yêu cầu —
        // người bấm sau phải nhận thông báo dễ hiểu chứ không phải trang 404.
        $requestModel = $location->customerOrderRequests()->where('status', 'cho_xac_nhan')->find($customerRequest);

        if (! $requestModel) {
            return redirect()->route('pos.orders.index')->with('status', 'Yêu cầu này đã được xử lý rồi (có thể người khác vừa nhận hoặc từ chối).');
        }

        $categories = $this->loadCategories($location);

        // Chỉ giữ lại những variant_id CÒN THẬT SỰ tồn tại + đang bán trong
        // $categories vừa nạp — món có thể đã bị xoá/ẩn kể từ lúc khách gửi
        // yêu cầu tới giờ, giữ nguyên dòng đó sẽ làm giao diện giỏ hàng lỗi.
        $availableVariantIds = $categories->flatMap(fn ($c) => $c->products)
            ->flatMap(fn ($p) => $p->variants)
            ->pluck('id');

        $initialCart = collect($requestModel->items)
            ->filter(fn ($item) => $availableVariantIds->contains($item['variant_id']))
            ->values();

        $skippedCount = count($requestModel->items) - $initialCart->count();

        // "Giành" yêu cầu bằng 1 câu UPDATE có điều kiện status = cho_xac_nhan (nguyên
        // tử ở tầng database) thay vì update() trên model đã đọc từ trước — nếu 2
        // người bấm gần như đồng thời, chỉ ĐÚNG 1 người cập nhật được dòng (số dòng
        // bị ảnh hưởng = 1), người còn lại nhận 0 và được báo đã có người xử lý.
        $claimed = $location->customerOrderRequests()
            ->whereKey($requestModel->id)
            ->where('status', 'cho_xac_nhan')
            ->update([
                'status' => 'da_nhan',
                'handled_by' => $request->user()->id,
                'handled_at' => now(),
            ]);

        if ($claimed === 0) {
            return redirect()->route('pos.orders.index')->with('status', 'Yêu cầu này vừa được người khác nhận rồi.');
        }

        $requestModel->refresh();

        return view('pos.order', [
            'location' => $location,
            'shift' => $shift,
            'categories' => $categories,
            'editingOrder' => null,
            'initialCart' => $initialCart,
            'draftCount' => $shift->orders()->where('status', 'nhap')->count(),
            'showQuickSetupBanner' => false,
            'acceptedRequest' => $requestModel,
            'skippedRequestItems' => $skippedCount,
        ]);
    }

    /** Từ chối 1 yêu cầu — VD: quán sắp đóng cửa, hoặc khách gửi trùng/nhầm. */
    public function rejectCustomerRequest(Request $request, int $customerRequest): RedirectResponse
    {
        $location = $this->currentLocation($request, false);

        $rejected = $location->customerOrderRequests()
            ->whereKey($customerRequest)
            ->where('status', 'cho_xac_nhan')
            ->update([
                'status' => 'tu_choi',
                'handled_by' => $request->user()->id,
                'handled_at' => now(),
            ]);

        return redirect()->route('pos.orders.index')->with(
            'status',
            $rejected ? 'Đã từ chối yêu cầu.' : 'Yêu cầu này đã được xử lý rồi (có thể người khác vừa nhận hoặc từ chối).'
        );
    }

    /** Mở lại đơn để sửa (hoặc tiếp tục đơn nháp) — dùng LẠI y hệt giao diện order, nạp sẵn các món đã có. */
    public function edit(Request $request, int $order): View|RedirectResponse
    {
        $location = $this->currentLocation($request, false);
        $orderModel = $this->findEditableOrder($request, $location, $order);
        $orderModel->load('customer');

        $categories = $this->loadCategories($location);

        $initialCart = $orderModel->items->map(fn ($item) => [
            'variant_id' => $item->product_variant_id,
            'quantity' => $item->quantity,
            'modifier_ids' => $item->modifiers->pluck('modifier_id')->all(),
            'discount_type' => $item->discount_type,
            'discount_value' => $item->discount_value ? (float) $item->discount_value : null,
        ])->values();

        return view('pos.order', [
            'location' => $location,
            'shift' => $orderModel->shift,
            'categories' => $categories,
            'editingOrder' => $orderModel,
            'initialCart' => $initialCart,
            'draftCount' => 0,
        ]);
    }

    /**
     * Lưu lại đơn đã sửa HOẶC hoàn tất 1 đơn nháp. Nếu đơn trước đó là nháp
     * (chưa từng trừ kho), bỏ qua bước hoàn tác — chỉ trừ kho MỘT LẦN DUY NHẤT
     * lúc hoàn tất thật sự. Nếu đơn đã hoàn thành từ trước (sửa đơn thường),
     * hoàn tác ảnh hưởng cũ rồi áp dụng lại từ đầu như bình thường.
     */
    public function update(Request $request, int $order): JsonResponse
    {
        $location = $this->currentLocation($request, false);
        $orderModel = $this->findEditableOrder($request, $location, $order);
        $wasDraft = $orderModel->isDraft();

        $data = $this->validateCart($request);

        DB::transaction(function () use ($data, $orderModel, $wasDraft, $location) {
            if (! $wasDraft) {
                $this->reverseOrderInventory($orderModel);
                $this->reverseOrderPoints($orderModel);
            }
            $orderModel->items()->delete(); // order_item_modifiers tự xoá theo (cascade)

            [$subtotal, $itemsToCreate] = $this->buildItemsFromCart($data['items'], $location);
            $discountAmount = $this->calculateDiscount($subtotal, $data['discount_type'] ?? null, $data['discount_value'] ?? null);
            $amountAfterDiscount = $subtotal - $discountAmount;

            [$customer, $pointsRedeemed, $pointsRedeemedValue] = $this->resolveCustomerAndRedemption(
                $location, $data['customer_phone'] ?? null, $data['customer_name'] ?? null,
                $data['redeem_points'] ?? null, $amountAfterDiscount
            );

            $total = $amountAfterDiscount - $pointsRedeemedValue;
            $pointsEarned = $this->calculatePointsEarned($location, $customer, $total);

            $orderModel->update([
                'customer_id' => $customer?->id,
                'order_type' => $data['order_type'],
                'guest_count' => $data['guest_count'] ?? null,
                'payment_method' => $data['payment_method'],
                'subtotal' => $subtotal,
                'discount_type' => $data['discount_type'] ?? null,
                'discount_value' => $data['discount_value'] ?? null,
                'discount_amount' => $discountAmount,
                'points_earned' => $pointsEarned,
                'points_redeemed' => $pointsRedeemed,
                'points_redeemed_value' => $pointsRedeemedValue,
                'total' => $total,
                'status' => 'hoan_thanh',
                'completed_at' => $orderModel->completed_at ?? now(),
                'edited_at' => $wasDraft ? null : now(),
            ]);

            $this->createOrderItems($orderModel, $itemsToCreate);
            $customer?->applyOrder($pointsEarned, $pointsRedeemed, (float) $total);
        });

        // Chỉ xuất hoá đơn điện tử nếu đơn này CHƯA TỪNG xuất trước đó — sửa
        // lại 1 đơn đã có hoá đơn điện tử cần luồng "hoá đơn điều chỉnh/thay
        // thế" riêng biệt (ngoài phạm vi phiên bản này), không tự động xuất
        // hoá đơn mới đè lên để tránh sai lệch với cơ quan thuế.
        if (! $orderModel->einvoice_status) {
            $this->einvoiceService->createInvoice($orderModel);
        }

        return response()->json(['order_id' => $orderModel->id, 'total' => (float) $orderModel->fresh()->total]);
    }

    /** Cập nhật lại nội dung đơn NHÁP (chưa hoàn tất) — vẫn không trừ kho. */
    public function updateDraft(Request $request, int $order): JsonResponse
    {
        $location = $this->currentLocation($request, false);
        $orderModel = $this->findEditableOrder($request, $location, $order);
        abort_unless($orderModel->isDraft(), 422, 'Đơn này đã hoàn tất, không thể lưu nháp lại.');

        $data = $request->validate([
            'items' => 'required|array|min:1',
            'items.*.variant_id' => 'required|exists:product_variants,id',
            'items.*.quantity' => 'required|integer|min:1|max:99',
            'items.*.discount_type' => 'nullable|in:percent,amount',
            'items.*.discount_value' => 'nullable|numeric|min:0',
            'items.*.modifier_ids' => 'nullable|array',
            'items.*.modifier_ids.*' => 'exists:modifiers,id',
            'note' => 'nullable|string|max:100',
        ]);

        DB::transaction(function () use ($data, $orderModel, $location) {
            $orderModel->items()->delete();
            [$subtotal, $itemsToCreate] = $this->buildItemsFromCart($data['items'], $location);

            $orderModel->update([
                'subtotal' => $subtotal,
                'total' => $subtotal,
                'note' => $data['note'] ?? $orderModel->note,
            ]);

            $this->createDraftItems($orderModel, $itemsToCreate);
        });

        return response()->json(['order_id' => $orderModel->id]);
    }

    /** Huỷ đơn — hoàn tác kho NẾU đã từng trừ (bỏ qua với đơn nháp vì chưa hề trừ kho), loại khỏi sổ quỹ. */
    public function cancel(Request $request, int $order): RedirectResponse
    {
        $location = $this->currentLocation($request, false);
        $orderModel = $this->findEditableOrder($request, $location, $order);

        DB::transaction(function () use ($orderModel) {
            if (! $orderModel->isDraft()) {
                $this->reverseOrderInventory($orderModel);
                $this->reverseOrderPoints($orderModel);
            }
            $orderModel->update(['status' => 'da_huy', 'edited_at' => now()]);
        });

        return redirect()->route('pos.orders.index')->with('status', 'Đã huỷ đơn #'.$orderModel->id.'.');
    }

    /** Trang in hoá đơn — layout riêng tối giản, khổ giấy theo cài đặt của xe. */
    /** Xuất lại hoá đơn điện tử bị lỗi, hoặc kiểm tra lại trạng thái nếu đang "pending". */
    public function retryEinvoice(Request $request, int $order): RedirectResponse
    {
        $location = $this->currentLocation($request, false);
        $orderModel = Order::where('location_id', $location->id)->findOrFail($order);

        if ($orderModel->einvoice_status === 'pending') {
            $this->einvoiceService->checkStatus($orderModel);
        } else {
            $this->einvoiceService->createInvoice($orderModel);
        }

        return back()->with('status', 'Đã kiểm tra lại hoá đơn điện tử cho đơn #'.$orderModel->id.'.');
    }

    public function receipt(Request $request, int $order): View
    {
        $location = $this->currentLocation($request, false);
        $order = Order::where('location_id', $location->id)
            ->with(['items.variant.product', 'items.modifiers.modifier', 'creator', 'customer'])
            ->findOrFail($order);

        return view('pos.receipt', compact('location', 'order'));
    }

    /**
     * Tìm hoặc tạo khách hàng theo SĐT (nếu có nhập), rồi tính số điểm THỰC
     * TẾ được dùng để giảm giá — luôn chặn ở 3 giới hạn: không vượt quá điểm
     * khách đang có, không vượt quá số điểm khách MUỐN dùng, và không giảm
     * quá số tiền còn phải trả (tránh trả âm tiền).
     */
    private function resolveCustomerAndRedemption(
        Location $location, ?string $phone, ?string $name, ?int $requestedRedeem, float $amountAfterDiscount
    ): array {
        if (! $location->loyalty_enabled || ! $phone) {
            return [null, 0, 0];
        }

        $customer = $location->customers()->firstOrCreate(
            ['phone' => $phone],
            ['name' => $name]
        );

        if ($name && $customer->name !== $name) {
            $customer->update(['name' => $name]);
        }

        $redeemRate = (float) $location->points_redeem_value;
        $maxUsableByBill = $redeemRate > 0 ? (int) floor($amountAfterDiscount / $redeemRate) : 0;
        $actualRedeemed = max(0, min((int) ($requestedRedeem ?? 0), $customer->points, $maxUsableByBill));
        $redeemedValue = round($actualRedeemed * $redeemRate, 2);

        return [$customer, $actualRedeemed, $redeemedValue];
    }

    /** Số điểm kiếm được từ đơn này — tính trên số tiền THỰC TRẢ (đã trừ hết mọi giảm giá). */
    private function calculatePointsEarned(Location $location, ?Customer $customer, float $finalTotal): int
    {
        if (! $location->loyalty_enabled || ! $customer || $location->points_earn_rate <= 0) {
            return 0;
        }

        return (int) floor($finalTotal / (float) $location->points_earn_rate);
    }

    /** Hoàn tác điểm đã cộng/trừ của 1 đơn — dùng khi sửa hoặc huỷ đơn ĐÃ HOÀN THÀNH. */
    private function reverseOrderPoints(Order $order): void
    {
        if (! $order->customer_id) {
            return;
        }

        $order->customer?->reversePoints(
            (int) $order->points_earned,
            (int) $order->points_redeemed,
            (float) $order->total
        );
    }

    /** Tra cứu nhanh khách hàng theo SĐT lúc thanh toán — biết ngay đang có bao nhiêu điểm trước khi quyết định dùng. */
    public function lookupCustomer(Request $request): JsonResponse
    {
        $location = $this->currentLocation($request, false);
        $data = $request->validate(['phone' => 'required|string|max:20']);

        $customer = $location->customers()->where('phone', $data['phone'])->first();

        return response()->json([
            'found' => (bool) $customer,
            'name' => $customer?->name,
            'points' => $customer?->points ?? 0,
        ]);
    }

    /**
     * Gắn thuộc tính runtime `readable_items` (VD: "Cà phê đen (L) + Ít đá x2")
     * lên từng $request — CustomerOrderRequest chỉ lưu variant_id/modifier_id
     * thô (xem docblock migration), nên cần tra tên món/size/tuỳ chọn ở đây
     * để hiện cho nhân viên đọc được. Tra 1 LẦN cho CẢ danh sách (không lặp
     * query trong vòng lặp blade) để tránh N+1 khi có nhiều yêu cầu cùng lúc.
     */
    private function attachReadableItemLabels($requests): void
    {
        if ($requests->isEmpty()) {
            return;
        }

        $variantIds = $requests->flatMap(fn ($r) => collect($r->items)->pluck('variant_id'))->unique();
        $modifierIds = $requests->flatMap(fn ($r) => collect($r->items)->pluck('modifier_ids')->flatten())->unique();

        $variants = ProductVariant::with('product')->whereIn('id', $variantIds)->get()->keyBy('id');
        $modifiers = Modifier::whereIn('id', $modifierIds)->get()->keyBy('id');

        foreach ($requests as $requestModel) {
            $requestModel->readable_items = collect($requestModel->items)->map(function ($item) use ($variants, $modifiers) {
                $variant = $variants->get($item['variant_id']);
                $name = $variant
                    ? $variant->product->name.' ('.$variant->name.')'
                    : '(món đã xoá)';
                $modifierNames = collect($item['modifier_ids'] ?? [])
                    ->map(fn ($id) => $modifiers->get($id)?->name)
                    ->filter()
                    ->implode(', ');

                return trim($name.($modifierNames ? " — {$modifierNames}" : '')).' x'.$item['quantity'];
            })->implode('; ');
        }
    }

    private function loadCategories(Location $location)
    {
        return $location->categories()
            ->with(['products' => function ($q) {
                $q->where('is_available', true)->with(['variants', 'modifierGroups.modifiers']);
            }])
            ->orderBy('sort_order')
            ->get();
    }

    private function validateCart(Request $request): array
    {
        return $request->validate([
            'order_type' => 'required|in:mang_di,ngoi_lai',
            'guest_count' => 'nullable|integer|min:1',
            'payment_method' => 'required|in:tien_mat,chuyen_khoan,vi_dien_tu',
            'cash_received' => 'nullable|numeric|min:0',
            'discount_type' => 'nullable|in:percent,amount',
            'discount_value' => 'nullable|numeric|min:0',
            'customer_phone' => 'nullable|string|max:20',
            'customer_name' => 'nullable|string|max:100',
            'redeem_points' => 'nullable|integer|min:0',
            'items' => 'required|array|min:1',
            'items.*.discount_type' => 'nullable|in:percent,amount',
            'items.*.discount_value' => 'nullable|numeric|min:0',
            'items.*.variant_id' => 'required|exists:product_variants,id',
            'items.*.quantity' => 'required|integer|min:1|max:99',
            'items.*.modifier_ids' => 'nullable|array',
            'items.*.modifier_ids.*' => 'exists:modifiers,id',
        ]);
    }

    /**
     * Tính số tiền giảm giá THẬT từ subtotal đã tính lại ở server — không tin
     * số discount_amount nào gửi từ client. Giảm % không quá 100%, giảm tiền
     * không quá subtotal (không cho tổng tiền âm).
     */
    private function calculateDiscount(float $subtotal, ?string $type, ?float $value): float
    {
        if (! $type || ! $value || $value <= 0) {
            return 0;
        }

        if ($type === 'percent') {
            return round($subtotal * min($value, 100) / 100, 2);
        }

        return min($value, $subtotal);
    }

    /** Tính giá, giá vốn từ dữ liệu THẬT trong DB — không tin số client gửi lên. */
    /**
     * Tính giá + giảm giá TỪNG DÒNG từ dữ liệu THẬT trong DB — không tin số
     * client gửi lên. line_total trả về đã trừ giảm giá của dòng đó, dùng
     * làm nền cho subtotal chung của cả đơn (giảm giá toàn đơn ở bước sau
     * áp dụng THÊM trên nền đã giảm theo dòng, không phải trên giá gốc).
     */
    /**
     * === LỖ HỔNG ĐÃ VÁ (rất nghiêm trọng) ===
     * Trước đây hàm này tra `ProductVariant`/`Modifier` KHÔNG giới hạn theo
     * quán (`ProductVariant::findOrFail($id)` — tìm trên TOÀN BỘ bảng, mọi
     * quán). Validation `exists:product_variants,id` ở validateCart() cũng
     * chỉ kiểm tra ID có tồn tại ĐÂU ĐÓ trong hệ thống, không kiểm tra có
     * thuộc quán đang bán hay không.
     *
     * Hậu quả: 1 request thanh toán cố tình chỉnh sửa (qua devtools) gửi
     * `variant_id` của 1 QUÁN KHÁC (ở bản Cloud — khác tài khoản/khác chủ
     * hoàn toàn) vẫn được chấp nhận — đơn tạo ra dưới quán CỦA MÌNH nhưng
     * lại TRỪ KHO của quán NGƯỜI KHÁC (qua Recipe/ModifierRecipe tra theo
     * đúng variant_id đó), và đọc được giá bán thật của quán đó. Ở bản
     * self-hosted, rủi ro tương tự xảy ra giữa NHIỀU XE của CÙNG 1 chủ quán
     * (trừ nhầm kho của xe A dù đang bán ở xe B).
     *
     * Vá bằng cách bắt buộc variant/modifier phải thuộc ĐÚNG $location đang
     * bán (qua chuỗi quan hệ variant → product → category → location, và
     * modifier → group → location) — sai thì báo lỗi 404 luôn, không cho lọt
     * xuống bước trừ kho/tính tiền.
     */
    private function buildItemsFromCart(array $items, Location $location): array
    {
        $subtotal = 0;
        $itemsToCreate = [];

        foreach ($items as $line) {
            $variant = ProductVariant::whereHas('product.category', fn ($q) => $q->where('location_id', $location->id))
                ->findOrFail($line['variant_id']);
            $modifierIds = $line['modifier_ids'] ?? [];
            $modifiers = Modifier::whereHas('group', fn ($q) => $q->where('location_id', $location->id))
                ->whereIn('id', $modifierIds)
                ->get();

            // Có ID tuỳ chọn gửi lên nhưng sau khi lọc theo quán lại KHÔNG tìm thấy đủ —
            // nghĩa là có ID không thuộc quán này (bị chèn/sửa) — chặn luôn thay vì âm
            // thầm bỏ qua (bỏ qua sẽ tính tiền/thực đơn sai lệch với ý định thật).
            abort_if(count($modifierIds) !== $modifiers->count(), 404);

            $unitPrice = (float) $variant->price + $modifiers->sum(fn ($m) => (float) $m->extra_price);
            $grossLineTotal = $unitPrice * $line['quantity'];

            $discountType = $line['discount_type'] ?? null;
            $discountValue = isset($line['discount_value']) ? (float) $line['discount_value'] : null;
            $discountAmount = $this->calculateDiscount($grossLineTotal, $discountType, $discountValue);
            $lineTotal = $grossLineTotal - $discountAmount;
            $subtotal += $lineTotal;

            $itemsToCreate[] = [
                'variant_id' => $variant->id,
                'quantity' => $line['quantity'],
                'unit_price' => $unitPrice,
                'line_total' => $lineTotal,
                'discount_type' => $discountType,
                'discount_value' => $discountValue,
                'discount_amount' => $discountAmount,
                'modifiers' => $modifiers,
            ];
        }

        return [$subtotal, $itemsToCreate];
    }

    /** Tạo order_items + snapshot giá vốn + trừ kho — dùng khi đơn THỰC SỰ hoàn tất (tạo mới, sửa đơn, hoặc hoàn tất từ nháp). */
    private function createOrderItems(Order $order, array $itemsToCreate): void
    {
        foreach ($itemsToCreate as $line) {
            $variantRecipes = Recipe::where('product_variant_id', $line['variant_id'])->with('ingredient')->get();
            $modifierIds = collect($line['modifiers'])->pluck('id');
            $modifierRecipes = ModifierRecipe::whereIn('modifier_id', $modifierIds)->with('ingredient')->get();

            $costPerUnit = fn ($r) => (float) $r->quantity * (float) ($r->ingredient?->avg_cost_per_unit ?? 0);
            $unitCost = $variantRecipes->sum($costPerUnit) + $modifierRecipes->sum($costPerUnit);
            $totalCost = $unitCost * $line['quantity'];

            $item = $order->items()->create([
                'product_variant_id' => $line['variant_id'],
                'quantity' => $line['quantity'],
                'unit_price' => $line['unit_price'],
                'line_total' => $line['line_total'],
                'discount_type' => $line['discount_type'],
                'discount_value' => $line['discount_value'],
                'discount_amount' => $line['discount_amount'],
                'unit_cost' => $unitCost,
                'total_cost' => $totalCost,
            ]);

            foreach ($line['modifiers'] as $modifier) {
                $item->modifiers()->create([
                    'modifier_id' => $modifier->id,
                    'extra_price' => $modifier->extra_price,
                ]);
            }

            $deduct = fn ($r) => $r->ingredient?->deductStock((float) $r->quantity * $line['quantity']);
            $variantRecipes->each($deduct);
            $modifierRecipes->each($deduct);
        }
    }

    /** Tạo order_items cho đơn NHÁP — ghi nhận món (kể cả giảm giá dòng) nhưng KHÔNG trừ kho, KHÔNG tính giá vốn (chưa thực sự bán). */
    private function createDraftItems(Order $order, array $itemsToCreate): void
    {
        foreach ($itemsToCreate as $line) {
            $item = $order->items()->create([
                'product_variant_id' => $line['variant_id'],
                'quantity' => $line['quantity'],
                'unit_price' => $line['unit_price'],
                'line_total' => $line['line_total'],
                'discount_type' => $line['discount_type'],
                'discount_value' => $line['discount_value'],
                'discount_amount' => $line['discount_amount'],
                'unit_cost' => 0,
                'total_cost' => 0,
            ]);

            foreach ($line['modifiers'] as $modifier) {
                $item->modifiers()->create([
                    'modifier_id' => $modifier->id,
                    'extra_price' => $modifier->extra_price,
                ]);
            }
        }
    }

    /**
     * Hoàn tác toàn bộ ảnh hưởng kho của 1 đơn ĐANG TỒN TẠI — cộng lại đúng số
     * lượng nguyên liệu đã trừ trước đó (dùng khi sửa hoặc huỷ đơn ĐÃ HOÀN
     * THÀNH). Giả định công thức không đổi giữa lúc bán và lúc sửa — trường
     * hợp hiếm khi lệch (đã đổi công thức sau khi bán) chấp nhận sai số nhỏ,
     * không chặn thao tác.
     */
    private function reverseOrderInventory(Order $order): void
    {
        $order->load('items.modifiers');

        foreach ($order->items as $item) {
            $variantRecipes = Recipe::where('product_variant_id', $item->product_variant_id)->with('ingredient')->get();
            $modifierIds = $item->modifiers->pluck('modifier_id');
            $modifierRecipes = ModifierRecipe::whereIn('modifier_id', $modifierIds)->with('ingredient')->get();

            $restore = fn ($r) => $r->ingredient?->restoreStock((float) $r->quantity * $item->quantity);
            $variantRecipes->each($restore);
            $modifierRecipes->each($restore);
        }
    }

    /**
     * Chỉ cho sửa/huỷ đơn thuộc ca CÒN ĐANG MỞ — đơn thuộc ca đã chốt bị khoá
     * để giữ tính toàn vẹn sổ sách đã đối soát. Chủ quán sửa được đơn của bất
     * kỳ nhân viên nào; nhân viên chỉ sửa được đơn trong ca CỦA CHÍNH MÌNH.
     */
    private function findEditableOrder(Request $request, Location $location, int $orderId): Order
    {
        $order = Order::where('location_id', $location->id)->with('shift')->findOrFail($orderId);

        abort_if($order->shift->closed_at !== null, 403, 'Ca chứa đơn này đã chốt, không thể sửa/huỷ nữa.');

        $user = $request->user();
        abort_unless($user->isOwner() || $order->shift->user_id === $user->id, 403);

        return $order;
    }

}
