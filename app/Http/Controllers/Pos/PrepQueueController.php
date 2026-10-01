<?php

namespace App\Http\Controllers\Pos;

use App\Http\Controllers\Concerns\ResolvesCurrentLocation;
use App\Http\Controllers\Controller;
use App\Models\Order;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Màn "Pha chế" — hàng chờ các đơn ĐÃ TÍNH TIỀN nhưng chưa làm xong, đơn cũ nhất lên
 * đầu. Người pha chế bấm "Xong" → gọi số thứ tự cho khách lấy món. Dùng chung cho chủ
 * quán và nhân viên; KHÔNG giới hạn theo ca (người thu ngân và người pha chế có thể đứng
 * 2 ca khác nhau trên 2 máy).
 *
 * Trình duyệt tự tải lại danh sách ~8 giây/lần qua data() — rất nhẹ, chỉ đọc đơn đang chờ.
 */
class PrepQueueController extends Controller
{
    use ResolvesCurrentLocation;

    /** Đơn đã xong hiện thêm ở cột "Vừa xong" trong ngần này phút để còn gọi lại số/hoàn tác. */
    private const RECENT_DONE_MINUTES = 15;

    public function index(Request $request): View
    {
        $location = $this->currentLocation($request, false);

        return view('pos.prep-queue', ['location' => $location]);
    }

    public function data(Request $request): JsonResponse
    {
        $location = $this->currentLocation($request, false);

        $base = Order::query()
            ->where('location_id', $location->id)
            ->where('status', 'hoan_thanh')
            ->with(['items.variant.product', 'items.modifiers.modifier']);

        $waiting = (clone $base)->where('prep_status', Order::PREP_WAITING)
            ->orderBy('completed_at')->limit(60)->get();

        $done = (clone $base)->where('prep_status', Order::PREP_DONE)
            ->where('prepared_at', '>=', now()->subMinutes(self::RECENT_DONE_MINUTES))
            ->latest('prepared_at')->limit(12)->get();

        return response()->json([
            'enabled' => (bool) $location->prep_queue_enabled,
            'waiting' => $waiting->map(fn (Order $o) => $this->present($o))->values(),
            'done' => $done->map(fn (Order $o) => $this->present($o))->values(),
        ])->header('Cache-Control', 'no-store, no-cache, must-revalidate');
    }

    public function markDone(Request $request, int $order): JsonResponse
    {
        return $this->transition($request, $order, Order::PREP_WAITING, Order::PREP_DONE);
    }

    public function undo(Request $request, int $order): JsonResponse
    {
        return $this->transition($request, $order, Order::PREP_DONE, Order::PREP_WAITING);
    }

    /** UPDATE có điều kiện trạng thái cũ (nguyên tử) — 2 máy cùng bấm không bị đè nhau. */
    private function transition(Request $request, int $orderId, string $from, string $to): JsonResponse
    {
        $location = $this->currentLocation($request, false);

        $updated = Order::where('location_id', $location->id)
            ->whereKey($orderId)
            ->where('status', 'hoan_thanh')
            ->where('prep_status', $from)
            ->update([
                'prep_status' => $to,
                'prepared_at' => $to === Order::PREP_DONE ? now() : null,
            ]);

        return response()->json(['ok' => $updated === 1]);
    }

    private function present(Order $order): array
    {
        return [
            'id' => $order->id,
            'number' => $order->daily_number,
            'note' => $order->note,
            'order_type' => $order->order_type,
            'completed_at' => $order->completed_at?->format('H:i'),
            'waiting_minutes' => $order->completed_at ? (int) floor($order->completed_at->diffInMinutes(now(), true)) : 0,
            'items' => $order->items->map(fn ($item) => [
                'name' => $item->variant?->product
                    ? $item->variant->product->name.' ('.$item->variant->name.')'
                    : '(món đã xoá)',
                'quantity' => $item->quantity,
                'modifiers' => $item->modifiers->map(fn ($m) => $m->modifier?->name)->filter()->values(),
            ])->values(),
        ];
    }
}
