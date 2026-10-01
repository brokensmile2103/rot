@extends('layouts.app')
@section('title', 'Gợi ý nhập hàng · Rót')
@section('page-title', 'Gợi ý nhập hàng')
@section('content')
<div class="p-4 pb-24 md:p-8 max-w-3xl mx-auto">
    <div class="flex items-center gap-3 mb-4">
        <a href="{{ route('owner.inventory.index') }}" class="w-10 h-10 rounded-xl bg-white border border-neutral-200 text-neutral-500 flex items-center justify-center shrink-0 hover:bg-neutral-50">
            <i class="fa-solid fa-arrow-left"></i>
        </a>
        <div class="min-w-0">
            <h2 class="text-lg font-bold text-neutral-900">Gợi ý nhập hàng — {{ $location->name }}</h2>
            <p class="text-xs text-neutral-500">Theo tốc độ bán {{ $windowDays }} ngày gần nhất, đủ dùng cho số ngày bạn chọn.</p>
        </div>
    </div>

    <div class="flex items-center gap-2 mb-4 flex-wrap">
        <span class="text-xs text-neutral-500 font-medium">Nhập đủ dùng:</span>
        @foreach([3, 7, 14, 30] as $d)
            <a href="{{ route('owner.inventory.reorder', ['days' => $d]) }}"
               class="text-xs px-3 py-1.5 rounded-full font-semibold transition {{ $coverDays === $d ? 'bg-[var(--accent)] text-white' : 'bg-white border border-neutral-200 text-neutral-600 hover:bg-neutral-50' }}">
                {{ $d }} ngày
            </a>
        @endforeach
    </div>

    @php $needed = $rows->filter(fn ($r) => $r['suggested_qty'] > 0); @endphp

    <div class="bg-white rounded-2xl border border-neutral-200 shadow-sm p-4 mb-4 flex items-center justify-between">
        <div>
            <div class="text-xs text-neutral-500 font-medium">Cần nhập {{ $needed->count() }} nguyên liệu</div>
            <div class="text-xl font-bold text-neutral-900">~{{ money($totalCost) }}đ</div>
        </div>
        <div class="text-xs text-neutral-400 text-right max-w-[50%]">Tiền ước tính theo giá vốn TB hiện tại</div>
    </div>

    <div class="space-y-2">
        @forelse($rows as $row)
            @php $ing = $row['ingredient']; @endphp
            <div class="bg-white rounded-xl px-4 py-3 border shadow-sm {{ $row['urgent'] ? 'border-red-200' : 'border-neutral-200' }}">
                <div class="flex items-start justify-between gap-3">
                    <div class="min-w-0">
                        <div class="text-sm font-semibold text-neutral-900 flex items-center gap-2">
                            {{ $ing->name }}
                            @if($row['urgent'])
                                <span class="text-xs px-2 py-0.5 rounded-full bg-red-50 text-red-600 font-medium">Cần nhập gấp</span>
                            @endif
                        </div>
                        <div class="text-xs text-neutral-500 mt-0.5">
                            Tồn {{ quantity($ing->current_stock) }} {{ $ing->unit }}
                            @if($row['daily_usage'] > 0)
                                · dùng ~{{ quantity($row['daily_usage']) }} {{ $ing->unit }}/ngày
                                · đủ ~{{ $row['days_left'] >= 100 ? '99+' : number_format($row['days_left'], 1, ',', '.') }} ngày
                            @else
                                · chưa bán ra trong {{ $windowDays }} ngày qua
                            @endif
                        </div>
                    </div>
                    <div class="text-right shrink-0">
                        @if($row['suggested_qty'] > 0)
                            <div class="text-sm font-bold text-[var(--accent-text)]">+{{ quantity($row['suggested_qty']) }} {{ $ing->unit }}</div>
                            <div class="text-xs text-neutral-500">~{{ money($row['estimated_cost']) }}đ</div>
                        @else
                            <div class="text-xs text-emerald-600 font-semibold"><i class="fa-solid fa-check"></i> Đủ</div>
                        @endif
                    </div>
                </div>
            </div>
        @empty
            <p class="text-sm text-neutral-500 text-center py-8">Chưa có nguyên liệu nào.</p>
        @endforelse
    </div>

    <p class="text-xs text-neutral-400 mt-4 leading-relaxed">
        Gợi ý = lượng dùng/ngày × số ngày chọn − tồn hiện tại (không thấp hơn ngưỡng cảnh báo bạn đặt cho nguyên liệu).
        Chỉ để tham khảo — điều chỉnh theo lịch bán, khuyến mãi, mùa vụ thực tế.
    </p>
</div>
@endsection
