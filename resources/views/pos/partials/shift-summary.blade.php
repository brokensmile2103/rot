{{-- Tổng kết bán hàng của ca theo hình thức thanh toán — xem Shift::salesSummary(). --}}
<div class="bg-white rounded-2xl p-5 mb-4 border border-neutral-200 shadow-sm">
    <div class="flex items-center justify-between mb-3">
        <p class="text-sm font-semibold text-neutral-700"><i class="fa-solid fa-chart-pie mr-2 text-neutral-400"></i>Tổng kết ca</p>
        <span class="text-xs text-neutral-500">
            {{ $summary['order_count'] }} đơn
            @if($summary['cancelled_count'] > 0)
                · {{ $summary['cancelled_count'] }} huỷ
            @endif
        </span>
    </div>
    <div class="space-y-2 text-sm">
        <div class="flex justify-between"><span class="text-neutral-500"><i class="fa-solid fa-money-bill-wave w-5 text-neutral-400"></i>Tiền mặt</span><span class="font-semibold">{{ money($summary['cash']) }}đ</span></div>
        <div class="flex justify-between"><span class="text-neutral-500"><i class="fa-solid fa-building-columns w-5 text-neutral-400"></i>Chuyển khoản</span><span class="font-semibold">{{ money($summary['transfer']) }}đ</span></div>
        <div class="flex justify-between"><span class="text-neutral-500"><i class="fa-solid fa-wallet w-5 text-neutral-400"></i>Ví điện tử</span><span class="font-semibold">{{ money($summary['ewallet']) }}đ</span></div>
        <div class="flex justify-between pt-2 border-t border-neutral-100"><span class="text-neutral-700 font-semibold">Doanh thu ca</span><span class="font-bold text-neutral-900">{{ money($summary['revenue']) }}đ</span></div>
    </div>
    <p class="text-xs text-neutral-400 mt-2">Chuyển khoản/ví: đối chiếu với sao kê ngân hàng/app ví trước khi chốt.</p>
</div>
