@extends('layouts.app')
@section('title', 'Báo cáo tổng hợp · Rót')
@section('page-title', 'Báo cáo tổng hợp nhiều xe')
@section('content')
<div class="p-4 pb-24 md:p-8 max-w-3xl mx-auto">
    <div class="flex items-center gap-3 mb-4">
        <div class="w-10 h-10 rounded-xl bg-[var(--accent-light)] text-[var(--accent-text)] flex items-center justify-center shrink-0">
            <i class="fa-solid fa-layer-group"></i>
        </div>
        <h2 class="text-lg font-bold text-neutral-900">Báo cáo tổng hợp nhiều xe</h2>
    </div>

    <div class="flex items-center justify-between gap-3 mb-5">
        <div class="flex rounded-xl border border-neutral-300 bg-white overflow-hidden">
            @foreach(['day' => 'Ngày', 'week' => 'Tuần', 'month' => 'Tháng'] as $value => $label)
                <a href="{{ route('owner.locations.report', ['period' => $value, 'date' => $anchor->format('Y-m-d')]) }}"
                   class="px-4 py-2 text-sm font-medium transition {{ $period === $value ? 'bg-[var(--accent)] text-white' : 'text-neutral-600 hover:bg-neutral-100' }}">
                    {{ $label }}
                </a>
            @endforeach
        </div>
        <a href="{{ route('owner.locations.index') }}" class="text-sm text-neutral-500 hover:underline shrink-0">← Về Các xe cà phê</a>
    </div>

    {{-- Tổng cộng TẤT CẢ các xe --}}
    <div class="grid grid-cols-3 gap-3 mb-3">
        <div class="bg-white rounded-2xl p-4 border border-neutral-200 shadow-sm text-center">
            <div class="text-xs text-neutral-500 font-medium mb-1">Tổng doanh thu</div>
            <div class="text-lg font-bold text-neutral-900">{{ money($totals['revenue']) }}đ</div>
        </div>
        <div class="bg-white rounded-2xl p-4 border border-neutral-200 shadow-sm text-center">
            <div class="text-xs text-neutral-500 font-medium mb-1">Tổng giá vốn</div>
            <div class="text-lg font-bold text-neutral-900">{{ money($totals['cost']) }}đ</div>
        </div>
        <div class="bg-white rounded-2xl p-4 border border-emerald-200 shadow-sm text-center">
            <div class="text-xs text-emerald-600 font-medium mb-1">Tổng lợi nhuận</div>
            <div class="text-lg font-bold text-emerald-700">{{ money($totals['profit']) }}đ</div>
        </div>
    </div>

    @if($totals['labor'] > 0 || $totals['rent'] > 0)
        <div class="grid grid-cols-3 gap-3 mb-3">
            <div class="bg-white rounded-2xl p-4 border border-neutral-200 shadow-sm text-center">
                <div class="text-xs text-neutral-500 font-medium mb-1">Tổng chi phí nhân sự</div>
                <div class="text-lg font-bold text-neutral-900">{{ money($totals['labor']) }}đ</div>
            </div>
            <div class="bg-white rounded-2xl p-4 border border-neutral-200 shadow-sm text-center">
                <div class="text-xs text-neutral-500 font-medium mb-1">Tổng chi phí mặt bằng</div>
                <div class="text-lg font-bold text-neutral-900">{{ money($totals['rent']) }}đ</div>
            </div>
            <div class="bg-white rounded-2xl p-4 border {{ $totals['netProfit'] >= 0 ? 'border-emerald-200' : 'border-red-200' }} shadow-sm text-center">
                <div class="text-xs {{ $totals['netProfit'] >= 0 ? 'text-emerald-600' : 'text-red-600' }} font-medium mb-1">Tổng lợi nhuận thực tế</div>
                <div class="text-lg font-bold {{ $totals['netProfit'] >= 0 ? 'text-emerald-700' : 'text-red-700' }}">{{ money($totals['netProfit']) }}đ</div>
            </div>
        </div>
    @endif
    <p class="text-xs text-neutral-400 mb-5">Cộng dồn từ {{ $rows->count() }} xe bên dưới, cùng khoảng thời gian đang chọn.</p>

    {{-- Chi tiết từng xe --}}
    <div class="bg-white rounded-2xl border border-neutral-200 shadow-sm overflow-hidden">
        <div class="px-4 py-3 border-b border-neutral-200 text-xs font-bold text-neutral-400 uppercase tracking-wider grid grid-cols-12 gap-2">
            <div class="col-span-4">Xe</div>
            <div class="col-span-3 text-right">Doanh thu</div>
            <div class="col-span-2 text-right">Giá vốn</div>
            <div class="col-span-3 text-right">Lợi nhuận</div>
        </div>
        @foreach($rows as $row)
            <div class="px-4 py-3 border-b border-neutral-100 last:border-0 grid grid-cols-12 gap-2 items-center text-sm">
                <div class="col-span-4 text-neutral-900 font-medium truncate">{{ $row['location']->name }}</div>
                <div class="col-span-3 text-right text-neutral-900">{{ money($row['revenue']) }}đ</div>
                <div class="col-span-2 text-right text-neutral-600">{{ money($row['cost']) }}đ</div>
                <div class="col-span-3 text-right text-emerald-700 font-semibold">{{ money($row['profit']) }}đ</div>
            </div>
        @endforeach
    </div>

    <p class="text-xs text-neutral-400 mt-4 leading-relaxed">
        <i class="fa-solid fa-circle-info mr-1"></i>
        Xem báo cáo chi tiết từng món của 1 xe cụ thể (Menu Engineering, dự đoán doanh thu...) ở trang Báo cáo sau khi chuyển sang đúng xe đó.
    </p>
</div>
@endsection
