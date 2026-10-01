@extends('layouts.app')
@php
    $terms = $location->businessTerms();
@endphp
@section('title', 'Kiểm kê kho · Rót')
@section('page-title', 'Kiểm kê kho')
@section('content')
<div class="p-4 pb-24 md:p-8 max-w-3xl mx-auto" x-data="{ counts: {}, filter: '' }">
    <div class="flex items-center gap-3 mb-4">
        <a href="{{ route('owner.inventory.index') }}" class="w-10 h-10 rounded-xl bg-white border border-neutral-200 text-neutral-500 flex items-center justify-center shrink-0 hover:bg-neutral-50">
            <i class="fa-solid fa-arrow-left"></i>
        </a>
        <div class="min-w-0">
            <h2 class="text-lg font-bold text-neutral-900">Kiểm kê kho — {{ $location->name }}</h2>
            <p class="text-xs text-neutral-500">Đếm thực tế rồi nhập vào ô bên phải. Để trống = không kiểm nguyên liệu đó.</p>
        </div>
    </div>

    <div class="bg-blue-50 border border-blue-200 text-blue-800 text-xs rounded-xl px-4 py-3 mb-4 leading-relaxed">
        <i class="fa-solid fa-circle-info mr-1"></i>
        Mỗi nguyên liệu có số đếm <strong>khác</strong> sổ sách sẽ được điều chỉnh và ghi vào <strong>Nhật ký điều chỉnh</strong> (lý do "Kiểm kê thực tế").
        Giá vốn TB giữ nguyên. Vẫn bán hàng bình thường trong lúc đếm được — đơn bán ra trong lúc đếm không bị tính nhầm là hao hụt.
    </div>

    <input type="search" x-model="filter" placeholder="Tìm nguyên liệu..." aria-label="Tìm nguyên liệu"
           class="w-full rounded-xl border border-neutral-300 px-4 py-2.5 text-sm mb-3 focus:border-[var(--accent-ring)] focus:outline-none">

    <form method="POST" action="{{ route('owner.inventory.stocktake.store') }}"
          onsubmit="return confirm('Lưu kết quả kiểm kê? Tồn kho sẽ được điều chỉnh theo số đếm thực tế.')">
        @csrf
        <div class="bg-white rounded-2xl border border-neutral-200 shadow-sm divide-y divide-neutral-100">
            @forelse($ingredients as $ing)
                @php $system = (float) $ing->current_stock; @endphp
                <div class="flex items-center gap-3 px-4 py-3" x-show="!filter || @js(mb_strtolower($ing->name)).includes(filter.toLowerCase())">
                    <input type="hidden" name="snapshot[{{ $ing->id }}]" value="{{ $system }}">
                    <div class="min-w-0 flex-1">
                        <div class="text-sm font-semibold text-neutral-900 truncate">{{ $ing->name }}</div>
                        <div class="text-xs text-neutral-500">Sổ sách: {{ quantity($system) }} {{ $ing->unit }}</div>
                        <template x-if="counts[{{ $ing->id }}] !== undefined && counts[{{ $ing->id }}] !== ''">
                            <div class="text-xs font-semibold"
                                 :class="(counts[{{ $ing->id }}] - {{ $system }}) < 0 ? 'text-red-600' : ((counts[{{ $ing->id }}] - {{ $system }}) > 0 ? 'text-emerald-600' : 'text-neutral-400')"
                                 x-text="(() => { const d = Math.round((counts[{{ $ing->id }}] - {{ $system }}) * 100) / 100; return d === 0 ? 'Khớp' : (d > 0 ? '+' : '') + d.toLocaleString('vi-VN') + ' {{ $ing->unit }} · ~' + Math.round(Math.abs(d) * {{ (float) $ing->avg_cost_per_unit }}).toLocaleString('vi-VN') + 'đ'; })()"></div>
                        </template>
                    </div>
                    <div class="flex items-center gap-1.5 shrink-0">
                        <input type="number" step="0.01" min="0" inputmode="decimal" name="counts[{{ $ing->id }}]"
                               x-model="counts[{{ $ing->id }}]" placeholder="Đếm được" aria-label="Số đếm thực tế {{ $ing->name }}"
                               class="w-28 rounded-lg border border-neutral-300 px-2 py-2 text-sm text-right focus:border-[var(--accent-ring)] focus:outline-none">
                        <span class="text-xs text-neutral-500 w-8">{{ $ing->unit }}</span>
                    </div>
                </div>
            @empty
                <p class="text-sm text-neutral-500 text-center py-8">Chưa có nguyên liệu nào.</p>
            @endforelse
        </div>

        @if($ingredients->isNotEmpty())
            <div class="mt-4 space-y-3">
                <x-input name="note" label="Ghi chú (không bắt buộc)" icon="fa-note-sticky" placeholder="VD: Kiểm kê cuối tháng 10" autocomplete="off" />
                <x-button icon="fa-floppy-disk">Lưu kết quả kiểm kê</x-button>
            </div>
        @endif
    </form>
</div>
@endsection
