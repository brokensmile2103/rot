@extends('layouts.app')
@section('title', 'Nhật ký điều chỉnh kho · Rót')
@section('page-title', 'Nhật ký điều chỉnh kho')
@section('content')
<div class="p-4 pb-24 md:p-8 max-w-3xl mx-auto">
    <div class="flex items-center gap-3 mb-4">
        <div class="w-10 h-10 rounded-xl bg-[var(--accent-light)] text-[var(--accent-text)] flex items-center justify-center shrink-0">
            <i class="fa-solid fa-clipboard-list"></i>
        </div>
        <h2 class="text-lg font-bold text-neutral-900 flex-1 min-w-0">Nhật ký điều chỉnh kho — {{ $location->name }}</h2>
        <a href="{{ route('owner.inventory.index') }}" class="text-sm text-neutral-500 hover:underline shrink-0">← Kho</a>
    </div>

    <p class="text-xs text-neutral-500 leading-relaxed mb-4">
        Mỗi lần bạn sửa TRỰC TIẾP "Tồn kho" hoặc "Giá vốn TB" của một nguyên liệu (không phải "Nhập kho") đều được ghi lại ở đây: ai sửa, lúc nào, số liệu trước/sau và lý do.
    </p>

    <form method="GET" action="{{ route('owner.inventory.adjustments') }}" class="flex flex-wrap items-center gap-2 mb-5">
        <select name="ingredient" id="adjust-filter-ingredient" onchange="this.form.submit()"
                class="rounded-xl border border-neutral-300 bg-white text-neutral-900 py-2 px-3 text-sm min-w-0 max-w-full focus:border-[var(--accent-ring)] focus:ring-1 focus:ring-[var(--accent-ring)] focus:outline-none">
            <option value="0">Tất cả nguyên liệu</option>
            @foreach($ingredientOptions as $opt)
                <option value="{{ $opt->id }}" @selected($filters['ingredient'] === $opt->id)>{{ $opt->name }}{{ $opt->trashed() ? ' (đã xoá)' : '' }}</option>
            @endforeach
        </select>
        <select name="reason" id="adjust-filter-reason" onchange="this.form.submit()"
                class="rounded-xl border border-neutral-300 bg-white text-neutral-900 py-2 px-3 text-sm focus:border-[var(--accent-ring)] focus:ring-1 focus:ring-[var(--accent-ring)] focus:outline-none">
            <option value="">Mọi lý do</option>
            @foreach($reasons as $key => $label)
                <option value="{{ $key }}" @selected($filters['reason'] === $key)>{{ $label }}</option>
            @endforeach
        </select>
        <select name="days" id="adjust-filter-days" onchange="this.form.submit()"
                class="rounded-xl border border-neutral-300 bg-white text-neutral-900 py-2 px-3 text-sm focus:border-[var(--accent-ring)] focus:ring-1 focus:ring-[var(--accent-ring)] focus:outline-none">
            @foreach([30 => '30 ngày qua', 90 => '90 ngày qua', 365 => '1 năm qua', 0 => 'Tất cả'] as $value => $label)
                <option value="{{ $value }}" @selected($filters['days'] === $value)>{{ $label }}</option>
            @endforeach
        </select>
    </form>

    @if($physicalCount > 0)
        <div class="grid grid-cols-2 gap-3 mb-2">
            <div class="bg-white rounded-2xl p-4 border border-red-200 shadow-sm text-center">
                <div class="text-xs text-red-600 font-medium mb-1">Thiếu hụt ước tính</div>
                <div class="text-lg font-bold text-red-700">{{ money($shortageValue) }}đ</div>
            </div>
            <div class="bg-white rounded-2xl p-4 border border-emerald-200 shadow-sm text-center">
                <div class="text-xs text-emerald-600 font-medium mb-1">Dư ước tính</div>
                <div class="text-lg font-bold text-emerald-700">{{ money($surplusValue) }}đ</div>
            </div>
        </div>
        <p class="text-xs text-neutral-400 leading-relaxed mb-5">
            Quy đổi theo giá vốn TB tại thời điểm sửa, từ {{ $physicalCount }} lần điều chỉnh có lý do "Kiểm kê thực tế", "Hao hụt / hư hỏng / hết hạn" hoặc "Lý do khác". Lý do "Sửa số liệu nhập sai" không được tính vì đó là chỉnh lại sổ sách, không phải hàng thật bị mất hay dư.
        </p>
    @endif

    <div class="space-y-2">
        @forelse($adjustments as $row)
            @php($d = $row->display())
            <div class="bg-white rounded-xl px-4 py-3.5 border border-neutral-200 shadow-sm">
                <div class="flex items-start justify-between gap-3">
                    <div class="min-w-0">
                        <div class="text-neutral-900 text-sm font-semibold flex items-center gap-2 flex-wrap">
                            <span class="truncate">{{ $d['ingredient_name'] }}</span>
                            @if($d['ingredient_trashed'])
                                <span class="text-xs px-2 py-0.5 rounded-full bg-neutral-100 text-neutral-500 font-medium">đã xoá</span>
                            @endif
                            <span class="text-xs px-2 py-0.5 rounded-full font-medium
                                         {{ $d['reason'] === 'hao_hut' ? 'bg-red-50 text-red-600' : ($d['reason'] === 'kiem_ke' ? 'bg-blue-50 text-blue-700' : 'bg-neutral-100 text-neutral-600') }}">
                                {{ $d['reason_label'] }}
                            </span>
                        </div>

                        @if($d['stock_changed'])
                            <div class="text-xs mt-1.5 flex items-center gap-1.5 flex-wrap">
                                <span class="text-neutral-500">Tồn kho:</span>
                                <span class="text-neutral-700 font-medium">{{ $d['stock_before'] }} → {{ $d['stock_after'] }}</span>
                                <span class="font-semibold {{ $d['delta_sign'] === 'down' ? 'text-red-600' : 'text-emerald-600' }}">({{ $d['stock_delta'] }})</span>
                                @if($d['shortage_value'])
                                    <span class="text-neutral-400">{{ $d['shortage_value'] }}</span>
                                @endif
                            </div>
                        @endif
                        @if($d['cost_changed'])
                            <div class="text-xs mt-1 text-neutral-600">
                                <span class="text-neutral-500">Giá vốn TB:</span>
                                <span class="font-medium">{{ $d['cost_before'] }} → {{ $d['cost_after'] }}</span>
                            </div>
                        @endif
                        @if($d['note'])
                            <div class="text-xs mt-1 text-neutral-500 italic">"{{ $d['note'] }}"</div>
                        @endif
                    </div>
                    <div class="text-right text-xs text-neutral-400 shrink-0 leading-relaxed">
                        <div>{{ $d['created_at'] }}</div>
                        <div>{{ $d['user_name'] }}</div>
                    </div>
                </div>
            </div>
        @empty
            <div class="text-center py-10 text-neutral-400">
                <i class="fa-solid fa-clipboard-list text-3xl mb-2 block"></i>
                <p class="text-sm">Chưa có lần điều chỉnh nào trong bộ lọc hiện tại.</p>
            </div>
        @endforelse
    </div>

    @if($adjustments->hasPages())
        <div class="mt-4">{{ $adjustments->links() }}</div>
    @endif
</div>
@endsection
