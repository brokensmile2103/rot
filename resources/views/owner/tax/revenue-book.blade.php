@extends('layouts.app')
@section('title', 'Sổ doanh thu · Rót')
@section('page-title', 'Sổ doanh thu')
@section('content')
@php
    $o = $overview;
    $monthLabels = [0 => 'Cả năm'];
    for ($m = 1; $m <= 12; $m++) { $monthLabels[$m] = 'Tháng '.$m; }
    $maxMonth = $year === $today->year ? $today->month : 12;

    // Màu theo mức độ: xanh = an toàn, vàng = sắp chạm/dự kiến vượt, đỏ = đã vượt.
    $ui = [
        'ok' => ['bar' => 'bg-emerald-500', 'border' => 'border-emerald-200', 'icon' => 'fa-circle-check text-emerald-600'],
        'near' => ['bar' => 'bg-amber-500', 'border' => 'border-amber-300', 'icon' => 'fa-triangle-exclamation text-amber-600'],
        'will_exceed' => ['bar' => 'bg-amber-500', 'border' => 'border-amber-300', 'icon' => 'fa-triangle-exclamation text-amber-600'],
        'exceeded' => ['bar' => 'bg-red-500', 'border' => 'border-red-300', 'icon' => 'fa-circle-exclamation text-red-600'],
    ][$o['status']];
    $barWidth = min(100, max(0, $o['percent']));
    $query = ['year' => $year, 'month' => $month];
@endphp
<div class="p-4 pb-24 md:p-8 max-w-3xl mx-auto">
    <div class="flex items-center gap-3 mb-4">
        <div class="w-10 h-10 rounded-xl bg-[var(--accent-light)] text-[var(--accent-text)] flex items-center justify-center shrink-0">
            <i class="fa-solid fa-book"></i>
        </div>
        <h2 class="text-lg font-bold text-neutral-900 flex-1">Sổ doanh thu &amp; ngưỡng thuế</h2>
        <a href="{{ route('owner.reports.index') }}" class="text-sm text-neutral-500 hover:underline shrink-0">← Báo cáo</a>
    </div>

    @if($errors->any())
        <div class="bg-red-50 border border-red-200 text-red-700 rounded-xl px-4 py-3 text-sm mb-4">
            <ul class="list-disc pl-5 space-y-0.5">
                @foreach($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    {{-- ===== Theo dõi ngưỡng miễn thuế (cộng dồn mọi xe của chủ hộ) ===== --}}
    <div class="bg-white rounded-2xl border {{ $ui['border'] }} shadow-sm p-4 md:p-5 mb-6">
        <div class="flex items-start justify-between gap-3 mb-3">
            <div>
                <h3 class="text-sm font-bold text-neutral-900">Doanh thu năm {{ $o['year'] }} so với ngưỡng miễn thuế</h3>
                <p class="text-xs text-neutral-400 mt-0.5">
                    Đến hết hôm nay
                    @if($o['multiLocation']) · cộng dồn {{ count($o['locations']) }} xe của bạn @endif
                </p>
            </div>
            <i class="fa-solid {{ $ui['icon'] }} text-lg mt-0.5"></i>
        </div>

        <div class="flex items-baseline justify-between gap-2 mb-2">
            <div class="text-2xl font-bold text-neutral-900">{{ money($o['ytd']) }}đ</div>
            <div class="text-xs text-neutral-500">/ {{ money($o['threshold']) }}đ · {{ number_format($o['percent'], 1, ',', '.') }}%</div>
        </div>
        <div class="h-3 rounded-full bg-neutral-100 overflow-hidden mb-3" role="progressbar" aria-valuemin="0" aria-valuemax="100" aria-valuenow="{{ round($barWidth) }}">
            <div class="h-full rounded-full {{ $ui['bar'] }}" style="width: {{ $barWidth }}%"></div>
        </div>

        <div class="text-sm text-neutral-700 leading-relaxed space-y-1.5">
            @if($o['status'] === 'exceeded')
                <p><strong>Doanh thu năm đã vượt ngưỡng {{ money($o['over']) }}đ.</strong> Từ khi vượt ngưỡng, hộ kinh doanh chịu thuế GTGT, TNCN và cần dùng hoá đơn điện tử khởi tạo từ máy tính tiền có kết nối với cơ quan thuế.</p>
                <p class="text-xs text-neutral-500">Hãy liên hệ chi cục thuế hoặc nhà cung cấp hoá đơn điện tử để được hướng dẫn cụ thể cho trường hợp của bạn.</p>
            @elseif($o['status'] === 'will_exceed')
                <p><strong>Với nhịp bán hiện tại, dự kiến vượt ngưỡng
                    @if($o['crossDate']) vào khoảng {{ \Illuminate\Support\Carbon::parse($o['crossDate'])->format('d/m/Y') }} @else trong năm nay @endif
                    </strong> (cả năm ước khoảng {{ money($o['projectedYear']) }}đ).</p>
                <p class="text-xs text-neutral-500">Nên chuẩn bị trước: khi vượt ngưỡng, hộ kinh doanh chịu thuế GTGT, TNCN và cần dùng hoá đơn điện tử khởi tạo từ máy tính tiền kết nối cơ quan thuế. Liên hệ chi cục thuế hoặc nhà cung cấp hoá đơn điện tử để được hướng dẫn.</p>
            @elseif($o['status'] === 'near')
                <p><strong>Bạn đã dùng {{ number_format($o['percent'], 0, ',', '.') }}% ngưỡng</strong> — còn {{ money($o['remaining']) }}đ nữa là chạm ngưỡng miễn thuế.</p>
            @else
                <p>Còn <strong>{{ money($o['remaining']) }}đ</strong> nữa mới chạm ngưỡng miễn thuế.</p>
            @endif

            @if($o['hasProjection'] && in_array($o['status'], ['ok', 'near'], true))
                <p class="text-xs text-neutral-500">Nhịp bán khoảng {{ money($o['avgDaily']) }}đ/ngày ({{ $o['recentDays'] }} ngày gần đây) → dự kiến cả năm khoảng {{ money($o['projectedYear']) }}đ.</p>
            @elseif(! $o['hasProjection'])
                <p class="text-xs text-neutral-500">Chưa đủ dữ liệu để dự báo (mới có {{ $o['recentDays'] }}/{{ \App\Services\RevenueThresholdTracker::MIN_PROJECTION_DAYS }} ngày bán gần đây).</p>
            @endif
        </div>

        @if($o['multiLocation'])
            <div class="mt-4 pt-3 border-t border-neutral-100 space-y-1.5">
                @foreach($o['locations'] as $row)
                    <div class="flex items-center justify-between gap-2 text-xs">
                        <span class="text-neutral-600 truncate">{{ $row['name'] }}</span>
                        <span class="text-neutral-800 font-semibold shrink-0">{{ money($row['total']) }}đ</span>
                    </div>
                @endforeach
            </div>
        @endif

        <p class="text-[11px] text-neutral-400 mt-4 leading-relaxed">
            Ngưỡng {{ money($o['threshold']) }}đ/năm theo {{ $o['basis'] }} (cập nhật {{ $o['basisUpdated'] }}). Doanh thu gồm các đơn trong Rót (tiền thực nhận) và các khoản ngoài Rót bạn nhập bên dưới.
            Quy định thuế có thể thay đổi — hãy đối chiếu với cơ quan thuế; Rót chỉ ghi chép và tính tham khảo, không thay thế tư vấn thuế.
        </p>
    </div>

    {{-- ===== Sổ doanh thu (mẫu S1a-HKD) của xe đang chọn ===== --}}
    <div class="flex items-center gap-2 mb-3">
        <h3 class="text-base font-bold text-neutral-900 flex-1">Sổ doanh thu — {{ $location->name }}</h3>
    </div>

    <form method="GET" action="{{ route('owner.tax.revenue-book') }}" class="flex flex-wrap items-center gap-2 mb-3">
        <select name="year" onchange="this.form.submit()" class="rounded-xl border border-neutral-300 bg-white text-sm py-2.5 pl-3 pr-8 focus:border-[var(--accent-ring)] focus:ring-2 focus:ring-amber-500/30 focus:outline-none">
            @foreach($yearOptions as $y)
                <option value="{{ $y }}" @selected($y === $year)>Năm {{ $y }}</option>
            @endforeach
        </select>
        <select name="month" onchange="this.form.submit()" class="rounded-xl border border-neutral-300 bg-white text-sm py-2.5 pl-3 pr-8 focus:border-[var(--accent-ring)] focus:ring-2 focus:ring-amber-500/30 focus:outline-none">
            @foreach($monthLabels as $value => $label)
                @if($value <= $maxMonth)
                    <option value="{{ $value }}" @selected($value === $month)>{{ $label }}</option>
                @endif
            @endforeach
        </select>
        <div class="flex gap-2 ml-auto">
            <a href="{{ route('owner.tax.revenue-book.print', $query) }}" target="_blank" rel="noopener"
               class="text-xs px-3 py-2.5 rounded-lg bg-white border border-neutral-300 text-neutral-600 hover:bg-neutral-50 font-medium transition">
                <i class="fa-solid fa-print mr-1"></i>In / Lưu PDF
            </a>
            <a href="{{ route('owner.tax.revenue-book.export', $query) }}"
               class="text-xs px-3 py-2.5 rounded-lg bg-white border border-neutral-300 text-neutral-600 hover:bg-neutral-50 font-medium transition">
                <i class="fa-solid fa-download mr-1"></i>CSV
            </a>
        </div>
    </form>

    <div class="bg-white rounded-2xl border border-neutral-200 shadow-sm overflow-hidden mb-2">
        <div class="px-4 py-3 border-b border-neutral-200 text-xs font-bold text-neutral-400 uppercase tracking-wider grid grid-cols-12 gap-2">
            <div class="col-span-3">Ngày</div>
            <div class="col-span-5">Diễn giải</div>
            <div class="col-span-4 text-right">Số tiền</div>
        </div>

        @forelse($book['rows'] as $row)
            @if($row['type'] === 'month_total')
                <div class="px-4 py-2.5 bg-neutral-50 border-b border-neutral-200 grid grid-cols-12 gap-2 items-center text-sm font-bold text-neutral-900">
                    <div class="col-span-8">{{ $row['description'] }}</div>
                    <div class="col-span-4 text-right">{{ money($row['amount']) }}đ</div>
                </div>
            @else
                <div class="px-4 py-2.5 border-b border-neutral-100 grid grid-cols-12 gap-2 items-center text-sm">
                    <div class="col-span-3 text-neutral-500">{{ \Illuminate\Support\Carbon::parse($row['date'])->format('d/m/Y') }}</div>
                    <div class="col-span-5 min-w-0 {{ $row['type'] === 'external' ? 'text-blue-700' : 'text-neutral-700' }}">
                        <span class="block truncate">{{ $row['description'] }}</span>
                    </div>
                    <div class="col-span-4 text-right text-neutral-900 flex items-center justify-end gap-2">
                        <span>{{ money($row['amount']) }}đ</span>
                        @if($row['type'] === 'external')
                            <form method="POST" action="{{ route('owner.tax.external.destroy', $row['id']) }}"
                                  onsubmit="return confirm('Xoá khoản doanh thu ngoài Rót này khỏi sổ?')">
                                @csrf @method('DELETE')
                                <button type="submit" class="text-neutral-300 hover:text-red-600 transition" aria-label="Xoá khoản này" title="Xoá">
                                    <i class="fa-solid fa-trash-can text-xs"></i>
                                </button>
                            </form>
                        @endif
                    </div>
                </div>
            @endif
        @empty
            <div class="text-center py-10 text-neutral-400">
                <i class="fa-solid fa-book-open text-3xl mb-2 block"></i>
                <p class="text-sm">Chưa có doanh thu nào trong {{ mb_strtolower($periodLabel) }}.</p>
            </div>
        @endforelse

        @if(count($book['rows']) > 0)
            <div class="px-4 py-3 bg-neutral-100 grid grid-cols-12 gap-2 items-center text-sm font-bold text-neutral-900">
                <div class="col-span-8">Tổng cộng — {{ $periodLabel }}</div>
                <div class="col-span-4 text-right">{{ money($book['total']) }}đ</div>
            </div>
        @endif
    </div>
    @if(count($book['rows']) > 0 && $book['externalTotal'] > 0)
        <p class="text-xs text-neutral-400 mb-2">Trong đó bán hàng trên Rót {{ money($book['salesTotal']) }}đ, ngoài Rót {{ money($book['externalTotal']) }}đ.</p>
    @endif
    <p class="text-xs text-neutral-400 mb-6 leading-relaxed">
        <i class="fa-solid fa-circle-info mr-1"></i>
        Mỗi ngày có bán ghi 1 dòng tổng doanh thu <strong class="text-neutral-500">thực nhận</strong> (đã trừ giảm giá, điểm đổi) của các đơn hoàn thành, kể cả đơn ở ca đang mở.
        Sổ này của xe <strong class="text-neutral-500">{{ $location->name }}</strong>; mức theo dõi ngưỡng ở trên thì cộng dồn tất cả xe của bạn.
    </p>

    {{-- ===== Nhập doanh thu ngoài Rót ===== --}}
    <details class="bg-white rounded-2xl border border-neutral-200 shadow-sm mb-3 group" @if($errors->has('revenue_date') || $errors->has('amount') || $errors->has('description')) open @endif>
        <summary class="px-4 py-3.5 cursor-pointer text-sm font-semibold text-neutral-800 flex items-center gap-2 list-none">
            <i class="fa-solid fa-plus text-neutral-400"></i>Thêm doanh thu bán ngoài Rót
            <i class="fa-solid fa-chevron-down text-xs text-neutral-300 ml-auto transition group-open:rotate-180"></i>
        </summary>
        <form method="POST" action="{{ route('owner.tax.external.store') }}" class="px-4 pb-4 space-y-3">
            @csrf
            <p class="text-xs text-neutral-500 leading-relaxed">
                Doanh thu tính thuế là tổng MỌI kênh bán. Nếu bạn còn bán qua app giao đồ ăn, bán sỉ, bán ở nơi khác không ghi trên Rót, nhập tổng của ngày đó tại đây để sổ và mức theo dõi ngưỡng không bị thiếu.
            </p>
            <x-input type="date" name="revenue_date" label="Ngày" icon="fa-calendar" value="{{ old('revenue_date', $today->toDateString()) }}" max="{{ $today->toDateString() }}" required />
            <x-input type="text" inputmode="numeric" class="money-input" name="amount" label="Số tiền (đ)" icon="fa-coins" value="{{ old('amount') }}" placeholder="VD: 350.000" required />
            <x-input type="text" name="description" label="Nội dung" icon="fa-pen" value="{{ old('description') }}" placeholder="VD: Bán qua app giao đồ ăn" maxlength="150" required />
            <x-button icon="fa-plus">Thêm vào sổ</x-button>
        </form>
    </details>

    {{-- ===== Thông tin in trên sổ ===== --}}
    <details class="bg-white rounded-2xl border border-neutral-200 shadow-sm group" @if($errors->has('tax_code') || $errors->has('tax_household_name')) open @endif>
        <summary class="px-4 py-3.5 cursor-pointer text-sm font-semibold text-neutral-800 flex items-center gap-2 list-none">
            <i class="fa-solid fa-id-card text-neutral-400"></i>Thông tin hộ kinh doanh in trên sổ
            <i class="fa-solid fa-chevron-down text-xs text-neutral-300 ml-auto transition group-open:rotate-180"></i>
        </summary>
        <form method="POST" action="{{ route('owner.tax.profile') }}" class="px-4 pb-4 space-y-3">
            @csrf @method('PUT')
            <x-input type="text" name="tax_household_name" label="Tên hộ / cá nhân kinh doanh" icon="fa-user" value="{{ old('tax_household_name', $location->tax_household_name) }}" placeholder="Để trống sẽ dùng tên tài khoản của bạn" maxlength="100" />
            <x-input type="text" inputmode="numeric" name="tax_code" label="Mã số thuế" icon="fa-hashtag" value="{{ old('tax_code', $location->tax_code) }}" placeholder="10 số (hoặc 12 số căn cước)" maxlength="14" />
            <p class="text-xs text-neutral-400 leading-relaxed">Địa điểm kinh doanh lấy từ địa chỉ của xe (sửa ở mục Các xe cà phê): <strong class="text-neutral-500">{{ $location->address ?: 'chưa có địa chỉ' }}</strong></p>
            <x-button icon="fa-floppy-disk">Lưu thông tin</x-button>
        </form>
    </details>
</div>
@endsection
