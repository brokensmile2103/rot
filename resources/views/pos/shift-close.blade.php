@extends('layouts.app')
@section('title', 'Chốt ca · Rót')
@section('page-title', 'Chốt ca')
@section('content')
<div class="p-4 pb-24 md:p-8 max-w-lg mx-auto">
    <div class="flex items-center gap-3 mb-5 md:hidden">
        <div class="w-10 h-10 rounded-xl bg-red-50 text-red-600 flex items-center justify-center">
            <i class="fa-solid fa-lock"></i>
        </div>
        <h2 class="text-lg font-bold text-neutral-900">Chốt ca</h2>
    </div>

    <div class="bg-white rounded-2xl p-5 mb-4 border border-neutral-200 space-y-3 shadow-sm">
        <div class="flex justify-between items-center text-sm">
            <span class="text-neutral-500 font-medium"><i class="fa-solid fa-sack-dollar mr-2 text-neutral-400"></i>Tiền đầu ca</span>
            <span class="text-neutral-900 font-semibold">{{ money($shift->opening_cash) }}đ</span>
        </div>
        <div class="flex justify-between items-center text-sm pt-3 border-t border-neutral-200">
            <span class="text-neutral-500 font-medium"><i class="fa-solid fa-calculator mr-2 text-neutral-400"></i>Tiền mặt lý thuyết hiện tại</span>
            <span class="text-[var(--accent-text)] font-bold text-lg">{{ money($expected) }}đ</span>
        </div>
    </div>

    {{-- Tổng kết theo hình thức thanh toán — tiền mặt đối chiếu ngăn kéo, chuyển khoản/ví đối chiếu sao kê. --}}
    @include('pos.partials.shift-summary', ['summary' => $summary])

    @if($summary['draft_count'] > 0)
        <div class="bg-amber-50 border border-amber-200 text-amber-800 text-sm rounded-xl px-4 py-3 mb-4">
            <i class="fa-solid fa-triangle-exclamation mr-1"></i>
            Ca còn <strong>{{ $summary['draft_count'] }} đơn nháp</strong> chưa hoàn tất. Chốt ca sẽ <strong>huỷ các đơn nháp này</strong> (chưa thu tiền, chưa trừ kho).
            <a href="{{ route('pos.orders.index') }}" class="underline font-semibold">Xem đơn nháp</a>
        </div>
    @endif

    <form method="POST" action="{{ route('shift.close') }}" class="space-y-4" x-data="cashCounter()">
        @csrf

        {{-- Bộ đếm theo mệnh giá — đếm từng loại tờ, tự cộng tổng, đỡ nhẩm sai khi đếm nhiều tiền lẻ. --}}
        <div class="bg-white rounded-2xl border border-neutral-200 shadow-sm">
            <button type="button" @click="open = !open" class="w-full px-5 py-3.5 flex items-center gap-2 text-sm font-semibold text-neutral-700">
                <i class="fa-solid fa-money-bills text-neutral-400"></i>Đếm theo mệnh giá (không bắt buộc)
                <i class="fa-solid fa-chevron-down text-xs text-neutral-400 ml-auto transition" :class="open && 'rotate-180'"></i>
            </button>
            <div x-show="open" x-cloak class="px-5 pb-4 space-y-2">
                <template x-for="d in denominations" :key="d.value">
                    <div class="flex items-center gap-3">
                        <span class="w-20 text-sm font-semibold text-neutral-700" x-text="formatPrice(d.value)"></span>
                        <span class="text-neutral-400 text-xs">×</span>
                        <input type="number" min="0" inputmode="numeric" x-model.number="d.count" @input="sync()"
                               :aria-label="'Số tờ ' + formatPrice(d.value)"
                               class="w-20 rounded-lg border border-neutral-300 px-2 py-1.5 text-sm text-center focus:border-[var(--accent-ring)] focus:outline-none">
                        <span class="ml-auto text-sm text-neutral-500" x-text="formatPrice(d.value * (d.count || 0)) + 'đ'"></span>
                    </div>
                </template>
                <div class="flex justify-between pt-2 border-t border-neutral-100 text-sm font-bold">
                    <span>Tổng đếm được</span><span x-text="formatPrice(total()) + 'đ'"></span>
                </div>
            </div>
        </div>

        <x-input type="text" inputmode="numeric" class="money-input" name="closing_cash_actual" label="Đếm tiền mặt thực tế trong quầy" icon="fa-coins" required autocomplete="off" x-ref="actual" />
        <x-button variant="danger" icon="fa-lock">Xác nhận chốt ca</x-button>
    </form>
</div>

<script>
function cashCounter() {
    return {
        open: false,
        denominations: [500000, 200000, 100000, 50000, 20000, 10000, 5000, 2000, 1000].map(value => ({ value, count: null })),
        total() {
            return this.denominations.reduce((s, d) => s + d.value * (d.count || 0), 0);
        },
        formatPrice(v) {
            return new Intl.NumberFormat('vi-VN').format(Math.round(v || 0));
        },
        // Điền tổng vào ô "Đếm tiền mặt thực tế" (vẫn sửa tay được nếu cần).
        sync() {
            this.$refs.actual.value = this.total() ? this.formatPrice(this.total()) : '';
        },
    };
}
</script>
@endsection
