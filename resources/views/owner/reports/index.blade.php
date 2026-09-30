@extends('layouts.app')
@section('title', 'Báo cáo · Rót')
@section('page-title', 'Báo cáo lợi nhuận')
@section('content')
<div class="p-4 pb-24 md:p-8 max-w-3xl mx-auto">
    <div class="flex items-center gap-3 mb-4">
        <div class="w-10 h-10 rounded-xl bg-[var(--accent-light)] text-[var(--accent-text)] flex items-center justify-center shrink-0">
            <i class="fa-solid fa-chart-line"></i>
        </div>
        <h2 class="text-lg font-bold text-neutral-900 flex-1">Báo cáo lợi nhuận</h2>
    </div>
    <div class="flex flex-wrap gap-2 mb-4">
        <a href="{{ route('owner.reports.peak-hours') }}" class="text-xs px-3 py-2 rounded-lg bg-white border border-neutral-300 text-neutral-600 hover:bg-neutral-50 font-medium transition">
            <i class="fa-solid fa-fire mr-1"></i>Giờ cao điểm
        </a>
        <a href="{{ route('owner.tax.revenue-book') }}" class="text-xs px-3 py-2 rounded-lg bg-white border border-neutral-300 text-neutral-600 hover:bg-neutral-50 font-medium transition">
            <i class="fa-solid fa-book mr-1"></i>Sổ doanh thu &amp; ngưỡng thuế
        </a>
    </div>

    <div class="flex flex-wrap items-center justify-between gap-3 mb-5">
        <div class="flex rounded-xl border border-neutral-300 bg-white overflow-hidden">
            @foreach(['day' => 'Ngày', 'week' => 'Tuần', 'month' => 'Tháng'] as $value => $label)
                <a href="{{ route('owner.reports.index', ['period' => $value, 'date' => $anchor->format('Y-m-d')]) }}"
                   class="px-4 py-2 text-sm font-medium transition {{ $period === $value ? 'bg-[var(--accent)] text-white' : 'text-neutral-600 hover:bg-neutral-100' }}">
                    {{ $label }}
                </a>
            @endforeach
        </div>

        <form method="GET" action="{{ route('owner.reports.index') }}" class="flex items-center gap-2">
            <input type="hidden" name="period" value="{{ $period }}">
            <input type="date" name="date" id="report-date" autocomplete="off" value="{{ $anchor->format('Y-m-d') }}" onchange="this.form.submit()"
                   class="rounded-xl border border-neutral-300 bg-white text-neutral-900 py-2 px-3 text-sm focus:border-[var(--accent-ring)] focus:ring-1 focus:ring-[var(--accent-ring)] focus:outline-none">

            <a href="{{ route('owner.reports.export', ['period' => $period, 'date' => $anchor->format('Y-m-d')]) }}"
               class="flex items-center gap-2 rounded-xl border border-neutral-300 bg-white text-neutral-700 hover:bg-neutral-50 py-2 px-4 text-sm font-medium transition shrink-0">
                <i class="fa-solid fa-file-csv"></i>Xuất CSV
            </a>
        </form>
    </div>

    <p class="text-sm text-neutral-500 font-medium mb-3">{{ $rangeLabel }}</p>

    <div class="grid grid-cols-3 gap-3 mb-5">
        <div class="bg-white rounded-2xl p-4 border border-neutral-200 shadow-sm text-center">
            <div class="text-xs text-neutral-500 font-medium mb-1">Doanh thu</div>
            <div class="text-lg font-bold text-neutral-900">{{ money($revenue) }}đ</div>
            @if($orderDiscount > 0)
                <div class="text-[11px] text-neutral-400 mt-0.5">đã trừ {{ money($orderDiscount) }}đ giảm giá</div>
            @endif
            @if(!is_null($revenueChange))
                <div class="text-xs font-semibold mt-1 {{ $revenueChange >= 0 ? 'text-emerald-600' : 'text-red-600' }}">
                    <i class="fa-solid {{ $revenueChange >= 0 ? 'fa-arrow-up' : 'fa-arrow-down' }}"></i>
                    {{ number_format(abs($revenueChange), 1) }}%
                </div>
            @elseif($revenue > 0)
                <div class="text-xs font-semibold mt-1 text-emerald-600">Mới</div>
            @endif
        </div>
        <div class="bg-white rounded-2xl p-4 border border-neutral-200 shadow-sm text-center">
            <div class="text-xs text-neutral-500 font-medium mb-1">Giá vốn</div>
            <div class="text-lg font-bold text-neutral-900">{{ money($totalCost) }}đ</div>
        </div>
        <div class="bg-white rounded-2xl p-4 border border-emerald-200 shadow-sm text-center">
            <div class="text-xs text-emerald-600 font-medium mb-1">Lợi nhuận</div>
            <div class="text-lg font-bold text-emerald-700">{{ money($totalProfit) }}đ</div>
            @if(!is_null($profitChange))
                <div class="text-xs font-semibold mt-1 {{ $profitChange >= 0 ? 'text-emerald-600' : 'text-red-600' }}">
                    <i class="fa-solid {{ $profitChange >= 0 ? 'fa-arrow-up' : 'fa-arrow-down' }}"></i>
                    {{ number_format(abs($profitChange), 1) }}%
                </div>
            @elseif($totalProfit > 0)
                <div class="text-xs font-semibold mt-1 text-emerald-600">Mới</div>
            @endif
        </div>
    </div>
    <p class="text-xs text-neutral-400 -mt-3 mb-5">So với kỳ liền trước ({{ $period === 'day' ? 'hôm qua' : ($period === 'week' ? 'tuần trước' : 'tháng trước') }})</p>

    {{-- v1.1.1 — Gộp 2 khối "nâng cao" (lợi nhuận thực tế + dự đoán doanh thu) vào
         1 khối có thể thu gọn — trước đây xếp chồng liên tiếp ngay dưới 3 ô số
         chính, hơi rối mắt trên điện thoại. Mặc định ĐÓNG, chỉ mở khi cần xem. --}}
    <div x-data="{ advancedOpen: false }" class="mb-5">
        <button type="button" @click="advancedOpen = !advancedOpen"
                class="w-full flex items-center justify-between gap-2 bg-white rounded-2xl border border-neutral-200 shadow-sm px-4 py-3 text-sm font-semibold text-neutral-700">
            <span class="flex items-center gap-2"><i class="fa-solid fa-chart-pie text-neutral-400"></i>Phân tích nâng cao</span>
            <i class="fa-solid fa-chevron-down text-xs text-neutral-400 transition" :class="advancedOpen && 'rotate-180'"></i>
        </button>

        <div x-show="advancedOpen" x-cloak class="mt-3 space-y-3">
            {{-- Lợi nhuận thực tế (đã trừ lương + mặt bằng) — chỉ hiện khi đã thiết lập
                 ít nhất 1 trong 2 khoản này (trang Nhân viên / Cài đặt), tránh làm rối
                 mắt quán chưa dùng tới tính năng này với toàn số 0. --}}
            @if($laborCost > 0 || $rentCost > 0)
                <div>
                    <div class="grid grid-cols-3 gap-3">
                        <div class="bg-white rounded-2xl p-4 border border-neutral-200 shadow-sm text-center">
                            <div class="text-xs text-neutral-500 font-medium mb-1">Chi phí nhân sự</div>
                            <div class="text-lg font-bold text-neutral-900">{{ money($laborCost) }}đ</div>
                        </div>
                        <div class="bg-white rounded-2xl p-4 border border-neutral-200 shadow-sm text-center">
                            <div class="text-xs text-neutral-500 font-medium mb-1">Chi phí mặt bằng</div>
                            <div class="text-lg font-bold text-neutral-900">{{ money($rentCost) }}đ</div>
                        </div>
                        <div class="bg-white rounded-2xl p-4 border {{ $netProfit >= 0 ? 'border-emerald-200' : 'border-red-200' }} shadow-sm text-center">
                            <div class="text-xs {{ $netProfit >= 0 ? 'text-emerald-600' : 'text-red-600' }} font-medium mb-1">Lợi nhuận thực tế</div>
                            <div class="text-lg font-bold {{ $netProfit >= 0 ? 'text-emerald-700' : 'text-red-700' }}">{{ money($netProfit) }}đ</div>
                        </div>
                    </div>
                    <p class="text-xs text-neutral-400 mt-2">Lợi nhuận thực tế = Lợi nhuận − Chi phí nhân sự − Chi phí mặt bằng. Chỉnh lương ở trang Nhân viên, chi phí mặt bằng ở Cài đặt.</p>
                </div>
            @endif

            {{-- Dự đoán doanh thu — luôn cho kỳ SẮP TỚI tính từ hôm nay, không phụ thuộc ngày đang lọc ở trên --}}
            <div class="bg-gradient-to-br from-violet-50 to-white rounded-2xl border border-violet-200 shadow-sm p-4">
                <div class="flex items-center gap-2 mb-1">
                    <div class="w-7 h-7 rounded-lg bg-violet-100 text-violet-700 flex items-center justify-center shrink-0 text-xs">
                        <i class="fa-solid fa-wand-magic-sparkles"></i>
                    </div>
                    <h3 class="text-sm font-bold text-neutral-900">Dự đoán doanh thu — {{ $forecast['rangeLabel'] ?? '' }}</h3>
                </div>

                @if(is_null($forecast))
                    <p class="text-xs text-neutral-500 mt-2 leading-relaxed">
                        Chưa đủ dữ liệu để dự đoán — cần có lịch sử bán hàng ít nhất {{ $forecastMinHistoryDays }} ngày
                        và bán tương đối đều tay (không nghỉ bán quá nhiều ngày). Quay lại đây sau ít hôm nhé.
                    </p>
                @else
                    <p class="text-2xl font-bold text-violet-700 mt-2">{{ money($forecast['expected']) }}đ</p>
                    <p class="text-xs text-neutral-500 mt-1">
                        Khoảng dự kiến: {{ money($forecast['low']) }}đ – {{ money($forecast['high']) }}đ
                    </p>
                    <div class="flex items-center gap-1.5 mt-2 text-xs font-medium {{ $forecast['growthRatePerWeek'] >= 0 ? 'text-emerald-600' : 'text-red-600' }}">
                        <i class="fa-solid {{ $forecast['growthRatePerWeek'] >= 0 ? 'fa-arrow-trend-up' : 'fa-arrow-trend-down' }}"></i>
                        Xu hướng {{ $forecast['growthRatePerWeek'] >= 0 ? 'tăng' : 'giảm' }} ~{{ number_format(abs($forecast['growthRatePerWeek']) * 100, 1) }}%/tuần
                        <span class="text-neutral-400 font-normal">· dựa trên {{ $forecast['historyDays'] }} ngày dữ liệu</span>
                    </div>
                    <p class="text-xs text-neutral-400 mt-2 leading-relaxed">
                        <i class="fa-solid fa-circle-info mr-1"></i>
                        Đây là ƯỚC TÍNH dựa trên xu hướng bán hàng thực tế + quy luật ngày trong tuần, KHÔNG PHẢI cam kết — chỉ mang tính tham khảo để chuẩn bị hàng/nhân sự.
                    </p>
                @endif
            </div>
        </div>
    </div>

    <div class="bg-white rounded-2xl border border-neutral-200 shadow-sm overflow-hidden">
        <div class="px-4 py-3 border-b border-neutral-200 text-xs font-bold text-neutral-400 uppercase tracking-wider grid grid-cols-12 gap-2">
            <div class="col-span-5">Món</div>
            <div class="col-span-2 text-right">SL</div>
            <div class="col-span-2 text-right">Doanh thu</div>
            <div class="col-span-3 text-right">Lợi nhuận</div>
        </div>
        @forelse($rows as $row)
            <div class="px-4 py-3 border-b border-neutral-100 last:border-0 grid grid-cols-12 gap-2 items-center text-sm">
                <div class="col-span-5 text-neutral-900 font-medium truncate">{{ $row['name'] }}</div>
                <div class="col-span-2 text-right text-neutral-600">{{ $row['quantity'] }}</div>
                <div class="col-span-2 text-right text-neutral-900">{{ money($row['revenue']) }}đ</div>
                <div class="col-span-3 text-right text-emerald-700 font-semibold">{{ money($row['profit']) }}đ</div>
            </div>
        @empty
            <div class="text-center py-10 text-neutral-400">
                <i class="fa-solid fa-inbox text-3xl mb-2 block"></i>
                <p class="text-sm">Chưa có đơn hàng hoàn thành nào trong khoảng thời gian này.</p>
            </div>
        @endforelse
    </div>

    <p class="text-xs text-neutral-400 mt-4 leading-relaxed">
        <i class="fa-solid fa-circle-info mr-1"></i>
        <strong class="text-neutral-500">Doanh thu là số tiền thực nhận</strong> của các đơn hoàn thành — đã trừ giảm giá từng món, giảm giá cả đơn và điểm khách đổi — nên khớp đúng với số tiền khi Chốt ca.
        Ở bảng theo món, phần giảm giá cả đơn được chia theo tỷ lệ giá trị từng món để tổng các món luôn bằng tổng doanh thu.
    </p>
    <p class="text-xs text-neutral-400 mt-2 leading-relaxed">
        <i class="fa-solid fa-circle-info mr-1"></i>
        Giá vốn được ghi lại chính xác ngay tại thời điểm bán — số liệu đúng cho mọi thời điểm kể cả khi giá nhập nguyên liệu đã thay đổi sau này.
        Riêng đơn hàng phát sinh trước khi tính năng này được bật sẽ hiển thị giá vốn = 0 do chưa có dữ liệu ghi nhận.
    </p>

    @if($menuRows->isNotEmpty())
        <div class="mt-8">
            <h3 class="text-base font-bold text-neutral-900 mb-1">Phân loại thực đơn (Menu Engineering)</h3>
            <p class="text-xs text-neutral-500 mb-4 leading-relaxed">
                Xếp từng món vào 1 trong 4 nhóm dựa trên lãi ròng trung bình mỗi món bán ra (Contribution Margin) và tỷ trọng số lượng bán (Popularity) — theo đúng mô hình chuẩn ngành F&B (Kasavana &amp; Smith). Nên xem theo Tuần hoặc Tháng để đủ dữ liệu, xem theo Ngày dễ bị lệch nếu bán ít.
            </p>

            @php
                $quadrants = [
                    'star' => ['label' => 'Stars — Bán chạy, lãi cao', 'desc' => 'Ngôi sao của thực đơn. Giữ nguyên chất lượng, đặt ở vị trí dễ thấy nhất.', 'icon' => 'fa-star', 'color' => 'amber'],
                    'plowhorse' => ['label' => 'Plowhorses — Bán chạy, lãi thấp', 'desc' => 'Khách thích nhưng lãi mỏng. Cân nhắc tăng giá nhẹ hoặc giảm giá vốn (đổi khẩu phần, nguyên liệu).', 'icon' => 'fa-horse', 'color' => 'blue'],
                    'puzzle' => ['label' => 'Puzzles — Lãi cao, ít người mua', 'desc' => 'Tiềm năng chưa khai thác hết. Thử quảng bá, đổi tên gọi hấp dẫn hơn, hoặc gợi ý thêm khi khách order.', 'icon' => 'fa-puzzle-piece', 'color' => 'violet'],
                    'dog' => ['label' => 'Dogs — Lãi thấp, ít người mua', 'desc' => 'Kém hiệu quả nhất trên cả 2 mặt. Cân nhắc bỏ khỏi thực đơn hoặc tái cấu trúc lại hoàn toàn.', 'icon' => 'fa-bone', 'color' => 'neutral'],
                ];
                $colorClasses = [
                    'amber' => 'bg-amber-50 text-amber-700 border-amber-200',
                    'blue' => 'bg-blue-50 text-blue-700 border-blue-200',
                    'violet' => 'bg-violet-50 text-violet-700 border-violet-200',
                    'neutral' => 'bg-neutral-100 text-neutral-600 border-neutral-200',
                ];
            @endphp

            <div class="space-y-3">
                @foreach($quadrants as $key => $info)
                    @php $itemsInGroup = $menuRows->where('category', $key); @endphp
                    <div class="bg-white rounded-2xl border border-neutral-200 shadow-sm overflow-hidden">
                        <div class="px-4 py-3 border-b border-neutral-100 flex items-center gap-2.5">
                            <div class="w-8 h-8 rounded-lg {{ $colorClasses[$info['color']] }} border flex items-center justify-center shrink-0">
                                <i class="fa-solid {{ $info['icon'] }} text-xs"></i>
                            </div>
                            <div class="min-w-0">
                                <p class="text-sm font-bold text-neutral-900">{{ $info['label'] }} <span class="text-neutral-400 font-normal">({{ $itemsInGroup->count() }})</span></p>
                                <p class="text-xs text-neutral-400">{{ $info['desc'] }}</p>
                            </div>
                        </div>
                        @if($itemsInGroup->isNotEmpty())
                            <div class="divide-y divide-neutral-100">
                                @foreach($itemsInGroup->sortByDesc('quantity') as $item)
                                    <div class="px-4 py-2.5 flex items-center justify-between text-sm">
                                        <span class="text-neutral-700 font-medium truncate">{{ $item['name'] }}</span>
                                        <span class="text-neutral-400 text-xs shrink-0 ml-2">
                                            {{ $item['quantity'] }} món · {{ number_format($item['popularity'], 1) }}% · CM {{ money($item['cm_per_unit']) }}đ
                                        </span>
                                    </div>
                                @endforeach
                            </div>
                        @else
                            <p class="px-4 py-3 text-xs text-neutral-400">Không có món nào trong nhóm này ở kỳ này.</p>
                        @endif
                    </div>
                @endforeach
            </div>

            <p class="text-xs text-neutral-400 mt-3 leading-relaxed">
                <i class="fa-solid fa-circle-info mr-1"></i>
                Ngưỡng phân loại: CM (lãi ròng/món) so với mức trung bình chung là {{ money($avgCmPerUnit) }}đ; Popularity so với ngưỡng {{ number_format($popularityThreshold, 1) }}% (bằng 70% mức kỳ vọng nếu chia đều cho {{ $menuRows->count() }} món đang bán trong kỳ).
            </p>
        </div>
    @endif
</div>
@endsection
