@extends('layouts.app')
@section('title', 'Mở ca · Rót')
@section('page-title', 'Mở ca bán hàng')
@section('content')
<div class="p-4 pb-24 md:p-8 max-w-lg mx-auto">
    <div class="flex items-center gap-3 mb-5 md:mb-6 md:hidden">
        <div class="w-10 h-10 rounded-xl bg-amber-100 text-amber-600 flex items-center justify-center shrink-0">
            <i class="fa-solid fa-cash-register"></i>
        </div>
        <h2 class="text-lg font-bold text-neutral-900">Mở ca bán hàng</h2>
    </div>

    @if(auth()->user()?->isOwner())
        <div class="bg-white rounded-2xl border border-neutral-200 shadow-sm p-4 mb-5" x-data="{ confirmOpen: false }">
            <p class="text-xs font-bold text-neutral-400 uppercase tracking-wider mb-3">Thiết lập trước khi bán</p>

            @if($showQuickSetup ?? false)
                <button type="button" @click="confirmOpen = true"
                        class="w-full flex items-center justify-center gap-2 rounded-xl bg-amber-600 hover:bg-amber-700 text-white py-3 px-4 text-sm font-semibold transition mb-3">
                    <i class="fa-solid fa-wand-magic-sparkles"></i>Thiết lập nhanh
                </button>
                <p class="text-xs text-neutral-400 -mt-1 mb-3 text-center">Chưa có món để bán? Tạo ngay 2 món mẫu, đủ nguyên liệu, bán được luôn.</p>

                <div x-show="confirmOpen" x-cloak @click.self="confirmOpen = false" class="fixed inset-0 bg-black/50 z-40 flex items-center justify-center p-4">
                    <div class="bg-white rounded-2xl p-5 max-w-sm w-full shadow-xl">
                        <p class="text-sm font-semibold text-neutral-900 mb-2">Tạo dữ liệu mẫu?</p>
                        <p class="text-xs text-neutral-500 leading-relaxed mb-4">
                            Sẽ tạo 2 món (Cà phê đá, Cà phê sữa) và 8 nguyên liệu trong kho. Bạn sửa/xoá lại được sau ở trang Thực đơn và Kho.
                        </p>
                        <div class="flex gap-2">
                            <button type="button" @click="confirmOpen = false" class="flex-1 rounded-xl bg-neutral-100 hover:bg-neutral-200 text-neutral-700 py-2.5 text-sm font-semibold transition">Huỷ</button>
                            <form method="POST" action="{{ route('owner.settings.quick-setup') }}" class="flex-1">
                                @csrf
                                <button class="w-full rounded-xl bg-amber-600 hover:bg-amber-700 text-white py-2.5 text-sm font-semibold transition">Tạo ngay</button>
                            </form>
                        </div>
                    </div>
                </div>
            @endif

            <div class="grid grid-cols-4 gap-2">
                <a href="{{ route('owner.menu.index') }}" class="flex flex-col items-center gap-1.5 py-3 rounded-xl hover:bg-neutral-50 transition text-center">
                    <div class="w-10 h-10 rounded-xl bg-[var(--accent-light)] text-[var(--accent-text)] flex items-center justify-center">
                        <i class="fa-solid fa-book-open"></i>
                    </div>
                    <span class="text-xs text-neutral-600 font-medium">Thực đơn</span>
                </a>
                <a href="{{ route('owner.modifier-groups.index') }}" class="flex flex-col items-center gap-1.5 py-3 rounded-xl hover:bg-neutral-50 transition text-center">
                    <div class="w-10 h-10 rounded-xl bg-[var(--accent-light)] text-[var(--accent-text)] flex items-center justify-center">
                        <i class="fa-solid fa-sliders"></i>
                    </div>
                    <span class="text-xs text-neutral-600 font-medium">Tuỳ chọn món</span>
                </a>
                <a href="{{ route('owner.inventory.index') }}" class="flex flex-col items-center gap-1.5 py-3 rounded-xl hover:bg-neutral-50 transition text-center">
                    <div class="w-10 h-10 rounded-xl bg-[var(--accent-light)] text-[var(--accent-text)] flex items-center justify-center">
                        <i class="fa-solid fa-boxes-stacked"></i>
                    </div>
                    <span class="text-xs text-neutral-600 font-medium">Kho nguyên liệu</span>
                </a>
                <a href="{{ route('owner.settings.index') }}" class="flex flex-col items-center gap-1.5 py-3 rounded-xl hover:bg-neutral-50 transition text-center">
                    <div class="w-10 h-10 rounded-xl bg-[var(--accent-light)] text-[var(--accent-text)] flex items-center justify-center">
                        <i class="fa-solid fa-gear"></i>
                    </div>
                    <span class="text-xs text-neutral-600 font-medium">Cài đặt</span>
                </a>
            </div>
        </div>
    @endif

    <div class="bg-white rounded-2xl border border-neutral-200 shadow-sm p-5">
        <div class="text-center mb-5">
            <div class="inline-flex items-center justify-center w-14 h-14 rounded-2xl bg-amber-100 text-amber-600 text-2xl mb-3">
                <i class="fa-solid fa-cash-register"></i>
            </div>
            <h3 class="text-lg font-bold text-neutral-900">Mở ca bán hàng</h3>
            <p class="text-sm text-neutral-500 mt-1">Nhập số tiền mặt đang có trong quỹ để bắt đầu.</p>
        </div>

        <form method="POST" action="{{ route('shift.store') }}" class="space-y-2">
            @csrf
            <x-input type="text" inputmode="numeric" class="money-input" name="opening_cash" label="Tiền mặt đầu ca" icon="fa-sack-dollar" value="{{ old('opening_cash', $lastClosedShift ? (float) $lastClosedShift->closing_cash_actual : 0) }}" required autocomplete="off" />
            @if($lastClosedShift)
                <p class="text-xs text-neutral-400 leading-relaxed -mt-1 mb-2">
                    <i class="fa-solid fa-circle-info mr-1"></i>Gợi ý theo tiền cuối ca gần nhất ({{ $lastClosedShift->user->name }}, {{ $lastClosedShift->closed_at->format('H:i d/m') }}) — đếm lại tiền thật đang có và sửa số nếu khác.
                </p>
            @endif
            <x-button variant="success" icon="fa-play">Bắt đầu bán</x-button>
        </form>
    </div>
</div>
@endsection
