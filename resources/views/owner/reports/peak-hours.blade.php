@extends('layouts.app')
@section('title', 'Giờ cao điểm · Rót')
@section('page-title', 'Giờ cao điểm')
@section('content')
@php
    $isOrders = $metric === 'orders';
    $hasData = $data['hasData'];
    $dowLabels = [1 => 'T2', 2 => 'T3', 3 => 'T4', 4 => 'T5', 5 => 'T6', 6 => 'T7', 7 => 'CN'];
    $dowNames = [1 => 'Thứ Hai', 2 => 'Thứ Ba', 3 => 'Thứ Tư', 4 => 'Thứ Năm', 5 => 'Thứ Sáu', 6 => 'Thứ Bảy', 7 => 'Chủ nhật'];

    // Giá trị bé hơn ngưỡng này hiện dấu "·" (coi như không có) — tránh ô đầy số 0,0 rối mắt.
    $zeroBelow = $isOrders ? 0.05 : 500;

    // Hiển thị gọn trong ô: số đơn "2,5" / doanh thu "125k". Chưa có dữ liệu (thứ chưa xuất hiện) → "–".
    $fmt = function ($v) use ($isOrders, $zeroBelow) {
        if ($v === null) return '–';
        if ($v < $zeroBelow) return '·';
        return $isOrders ? number_format($v, 1, ',', '.') : money_compact($v);
    };
    // Hiển thị đầy đủ (tooltip + thẻ tóm tắt).
    $fmtFull = function ($v) use ($isOrders) {
        if ($v === null) return 'chưa có dữ liệu';
        return $isOrders ? number_format($v, 1, ',', '.').' đơn' : money($v).'đ';
    };

    if ($hasData) {
        $m = $data['metrics'][$metric];
        // Độ đậm của ô: 0 (không có) hoặc 0,10 → 1,00 theo tỉ lệ so với ô cao nhất.
        $heat = function ($v) use ($m, $zeroBelow) {
            if ($v === null || $v < $zeroBelow || $m['max'] <= 0) return 0;
            return round(0.1 + 0.9 * min(1, $v / $m['max']), 2);
        };
        $overallPerDay = $data['dayCount'] > 0
            ? ($isOrders ? $data['totalOrders'] : $data['totalRevenue']) / $data['dayCount']
            : null;
    }
    $queryBase = ['weeks' => $weeks, 'metric' => $metric];
@endphp
<div class="p-4 pb-24 md:p-8 max-w-3xl mx-auto">
    <div class="flex items-center gap-3 mb-4">
        <div class="w-10 h-10 rounded-xl bg-[var(--accent-light)] text-[var(--accent-text)] flex items-center justify-center shrink-0">
            <i class="fa-solid fa-fire"></i>
        </div>
        <h2 class="text-lg font-bold text-neutral-900 flex-1">Giờ cao điểm — {{ $location->name }}</h2>
        <a href="{{ route('owner.reports.index') }}" class="text-sm text-neutral-500 hover:underline shrink-0">← Báo cáo</a>
    </div>

    <div class="flex flex-wrap items-center justify-between gap-3 mb-4">
        <div class="flex rounded-xl border border-neutral-300 bg-white overflow-hidden">
            @foreach($weekOptions as $option)
                <a href="{{ route('owner.reports.peak-hours', array_merge($queryBase, ['weeks' => $option])) }}"
                   class="px-4 py-2 text-sm font-medium transition {{ $weeks === $option ? 'bg-[var(--accent)] text-white' : 'text-neutral-600 hover:bg-neutral-100' }}">
                    {{ $option }} tuần
                </a>
            @endforeach
        </div>
        <div class="flex rounded-xl border border-neutral-300 bg-white overflow-hidden">
            @foreach(['orders' => 'Số đơn', 'revenue' => 'Doanh thu'] as $value => $label)
                <a href="{{ route('owner.reports.peak-hours', array_merge($queryBase, ['metric' => $value])) }}"
                   class="px-4 py-2 text-sm font-medium transition {{ $metric === $value ? 'bg-[var(--accent)] text-white' : 'text-neutral-600 hover:bg-neutral-100' }}">
                    {{ $label }}
                </a>
            @endforeach
        </div>
    </div>

    @if(! $hasData)
        <div class="bg-white rounded-2xl border border-neutral-200 shadow-sm text-center py-12 px-6 text-neutral-400">
            <i class="fa-solid fa-fire text-3xl mb-3 block"></i>
            <p class="text-sm">Chưa có dữ liệu để vẽ bảng giờ cao điểm.</p>
            <p class="text-xs mt-1">Bảng chỉ tính các đơn đã hoàn thành từ hôm qua trở về trước (hôm nay chưa bán xong nên chưa được tính).</p>
        </div>
    @else
        <p class="text-sm text-neutral-500 font-medium mb-3">
            {{ $from->format('d/m/Y') }} – {{ $to->format('d/m/Y') }}
            <span class="text-neutral-400 font-normal">· {{ $data['dayCount'] }} ngày · {{ money($data['totalOrders']) }} đơn</span>
        </p>

        @if($isUnstable)
            <div class="bg-amber-50 border border-amber-200 rounded-xl px-4 py-3 text-xs text-amber-800 leading-relaxed mb-4">
                <i class="fa-solid fa-triangle-exclamation mr-1"></i>
                Mới có <strong>{{ $data['dayCount'] }} ngày</strong> dữ liệu nên mỗi thứ mới xuất hiện 1–2 lần, các ô có thể dao động mạnh — chỉ nên xem để tham khảo.
                @if($data['dayCount'] < 7)
                    Thứ nào chưa xuất hiện lần nào sẽ hiện "–".
                @endif
            </div>
        @endif

        <div class="grid grid-cols-3 gap-3 mb-5">
            <div class="bg-white rounded-2xl p-3 border border-neutral-200 shadow-sm text-center">
                <div class="text-xs text-neutral-500 font-medium mb-1">Giờ đông nhất</div>
                @if(! is_null($m['busiestHour']))
                    <div class="text-base font-bold text-neutral-900">{{ $m['busiestHour'] }}h–{{ $m['busiestHour'] + 1 }}h</div>
                    <div class="text-xs text-neutral-400 mt-0.5">{{ $fmtFull($m['hourAvg'][$m['busiestHour']]) }}/ngày</div>
                @else
                    <div class="text-base font-bold text-neutral-300">–</div>
                @endif
            </div>
            <div class="bg-white rounded-2xl p-3 border border-neutral-200 shadow-sm text-center">
                <div class="text-xs text-neutral-500 font-medium mb-1">Ngày đông nhất</div>
                @if(! is_null($m['busiestDow']))
                    <div class="text-base font-bold text-neutral-900">{{ $dowNames[$m['busiestDow']] }}</div>
                    <div class="text-xs text-neutral-400 mt-0.5">{{ $fmtFull($m['dayAvg'][$m['busiestDow']]) }}/ngày</div>
                @else
                    <div class="text-base font-bold text-neutral-300">–</div>
                @endif
            </div>
            <div class="bg-white rounded-2xl p-3 border border-[var(--accent)] shadow-sm">
                <div class="text-xs text-[var(--accent-text)] font-medium mb-1 text-center">Khung giờ vàng</div>
                @forelse($m['peaks'] as $peak)
                    <div class="flex items-center justify-between gap-1 text-xs leading-relaxed">
                        <span class="text-neutral-700 font-semibold">{{ $dowLabels[$peak['dow']] }} · {{ $peak['hour'] }}h</span>
                        <span class="text-neutral-500">{{ $isOrders ? number_format($peak['value'], 1, ',', '.') : money_compact($peak['value']) }}</span>
                    </div>
                @empty
                    <div class="text-base font-bold text-neutral-300 text-center">–</div>
                @endforelse
            </div>
        </div>

        <div class="bg-white rounded-2xl border border-neutral-200 shadow-sm p-3 md:p-4 mb-3">
            <table class="w-full table-fixed border-separate border-spacing-[3px]">
                <colgroup>
                    <col class="w-9">
                    @foreach($dowLabels as $unused)
                        <col>
                    @endforeach
                    <col class="w-12">
                </colgroup>
                <thead>
                    <tr class="text-[11px] font-bold text-neutral-400">
                        <th class="font-bold text-left"></th>
                        @foreach($dowLabels as $dow => $label)
                            <th class="font-bold text-center pb-1" title="{{ $dowNames[$dow] }}">{{ $label }}</th>
                        @endforeach
                        <th class="font-bold text-center pb-1 text-neutral-500" title="Trung bình mỗi ngày, gộp cả 7 thứ">Cả tuần</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($data['hours'] as $hour)
                        <tr>
                            <th class="text-[11px] font-semibold text-neutral-400 text-left pr-1 whitespace-nowrap">{{ $hour }}h</th>
                            @foreach($dowLabels as $dow => $unused)
                                @php($v = $m['cells'][$dow][$hour])
                                @php($opacity = $heat($v))
                                <td class="p-0">
                                    <div class="relative h-9 rounded-md bg-neutral-100 overflow-hidden flex items-center justify-center"
                                         title="{{ $dowNames[$dow] }} · {{ $hour }}h–{{ $hour + 1 }}h: {{ $fmtFull($v) }} (trung bình mỗi {{ mb_strtolower($dowNames[$dow]) }})">
                                        @if($opacity > 0)
                                            <span class="absolute inset-0 bg-[var(--accent)]" style="opacity: {{ $opacity }}"></span>
                                        @endif
                                        <span class="relative text-[11px] font-semibold {{ $opacity >= 0.55 ? 'text-white' : ($v === null ? 'text-neutral-300' : 'text-neutral-600') }}">{{ $fmt($v) }}</span>
                                    </div>
                                </td>
                            @endforeach
                            <td class="p-0">
                                <div class="h-9 rounded-md bg-neutral-50 border border-neutral-200 flex items-center justify-center"
                                     title="{{ $hour }}h–{{ $hour + 1 }}h: {{ $fmtFull($m['hourAvg'][$hour]) }} mỗi ngày (gộp cả 7 thứ)">
                                    <span class="text-[11px] font-bold text-neutral-700">{{ $fmt($m['hourAvg'][$hour]) }}</span>
                                </div>
                            </td>
                        </tr>
                    @endforeach
                    <tr>
                        <th class="text-[10px] font-bold text-neutral-500 text-left pr-1 leading-tight pt-1">Cả ngày</th>
                        @foreach($dowLabels as $dow => $unused)
                            <td class="p-0 pt-1">
                                <div class="h-9 rounded-md bg-neutral-50 border border-neutral-200 flex items-center justify-center"
                                     title="{{ $dowNames[$dow] }}: {{ $fmtFull($m['dayAvg'][$dow]) }} mỗi ngày">
                                    <span class="text-[11px] font-bold text-neutral-700">{{ $fmt($m['dayAvg'][$dow]) }}</span>
                                </div>
                            </td>
                        @endforeach
                        <td class="p-0 pt-1">
                            <div class="h-9 rounded-md bg-neutral-100 border border-neutral-300 flex items-center justify-center"
                                 title="Trung bình mỗi ngày (cả tuần): {{ $fmtFull($overallPerDay) }}">
                                <span class="text-[11px] font-bold text-neutral-900">{{ $fmt($overallPerDay) }}</span>
                            </div>
                        </td>
                    </tr>
                </tbody>
            </table>

            <div class="flex items-center justify-end gap-2 mt-3 text-[11px] text-neutral-400">
                <span>Ít</span>
                <span class="h-2.5 w-24 rounded-full bg-neutral-100 overflow-hidden">
                    <span class="block h-full w-full" style="background: linear-gradient(to right, transparent, var(--accent))"></span>
                </span>
                <span>Nhiều</span>
            </div>
        </div>

        <div class="text-xs text-neutral-400 leading-relaxed space-y-1.5">
            <p>
                Mỗi ô là <strong class="text-neutral-500">{{ $isOrders ? 'số đơn' : 'doanh thu' }} trung bình</strong> của khung giờ đó trong <strong class="text-neutral-500">một ngày</strong> thứ tương ứng
                (tổng ÷ số lần thứ đó xuất hiện trong {{ $data['dayCount'] }} ngày, tính cả những ngày quán nghỉ). Không tính hôm nay vì chưa bán xong.
            </p>
            @unless($isOrders)
                <p>Doanh thu là số tiền thực nhận, tính đúng như trang Báo cáo (đã trừ mọi khoản giảm giá và điểm đổi).</p>
            @endunless
            @if($data['hiddenOrders'] > 0)
                <p>Có {{ money($data['hiddenOrders']) }} đơn nằm ở giờ rất ít khách (đầu/cuối khung giờ bán) nên được ẩn khỏi các ô cho gọn — vẫn được tính trong hàng "Cả ngày".</p>
            @endif
        </div>
    @endif
</div>
@endsection
