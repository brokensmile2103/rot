@extends('layouts.app')
@section('title', 'Bảng công · Rót')
@section('page-title', 'Bảng công')
@section('content')
<div class="p-4 pb-24 md:p-8 max-w-3xl mx-auto">
    <div class="flex items-center gap-3 mb-4">
        <div class="w-10 h-10 rounded-xl bg-[var(--accent-light)] text-[var(--accent-text)] flex items-center justify-center shrink-0">
            <i class="fa-solid fa-clock"></i>
        </div>
        <h2 class="text-lg font-bold text-neutral-900">Bảng công — {{ $location->name }}</h2>
    </div>

    <form method="GET" action="{{ route('owner.staff.timesheet') }}" class="flex items-center justify-between gap-3 mb-5">
        <input type="month" name="month" id="timesheet-month" autocomplete="off" value="{{ $month->format('Y-m') }}" onchange="this.form.submit()"
               class="rounded-xl border border-neutral-300 bg-white text-neutral-900 py-2 px-3 text-sm focus:border-[var(--accent-ring)] focus:ring-1 focus:ring-[var(--accent-ring)] focus:outline-none">
        <a href="{{ route('owner.staff.index') }}" class="text-sm text-neutral-500 hover:underline shrink-0">← Về trang Nhân viên</a>
    </form>

    <p class="text-sm text-neutral-500 font-medium mb-3">Tháng {{ $month->format('m/Y') }}</p>

    <div class="bg-white rounded-2xl border border-neutral-200 shadow-sm overflow-hidden mb-5">
        <div class="px-4 py-3 border-b border-neutral-200 text-xs font-bold text-neutral-400 uppercase tracking-wider grid grid-cols-12 gap-2">
            <div class="col-span-4">Nhân viên</div>
            <div class="col-span-2 text-right">Số ca</div>
            <div class="col-span-3 text-right">Tổng giờ làm</div>
            <div class="col-span-3 text-right">Lương ước tính</div>
        </div>
        @forelse($rows as $row)
            <div class="px-4 py-3 border-b border-neutral-100 last:border-0 grid grid-cols-12 gap-2 items-center text-sm">
                <div class="col-span-4 min-w-0">
                    <div class="text-neutral-900 font-medium truncate">{{ $row['user']->name }}</div>
                    <div class="text-neutral-400 text-xs">
                        {{ match($row['user']->salary_type) {
                            'hourly' => money($row['user']->salary_amount, 0).'đ/giờ',
                            'monthly' => money($row['user']->salary_amount, 0).'đ/tháng',
                            default => 'Chưa thiết lập lương',
                        } }}
                    </div>
                </div>
                <div class="col-span-2 text-right text-neutral-600">{{ $row['shift_count'] }}</div>
                <div class="col-span-3 text-right text-neutral-900">{{ number_format($row['total_hours'], 1) }} giờ</div>
                <div class="col-span-3 text-right font-semibold {{ is_null($row['estimated_pay']) ? 'text-neutral-400' : 'text-emerald-700' }}">
                    {{ is_null($row['estimated_pay']) ? '—' : money($row['estimated_pay']).'đ' }}
                </div>
            </div>
        @empty
            <div class="text-center py-10 text-neutral-400">
                <i class="fa-solid fa-users text-3xl mb-2 block"></i>
                <p class="text-sm">Chưa có nhân viên nào — chỉ mình bạn quản lý xe này.</p>
            </div>
        @endforelse
        @if($rows->isNotEmpty())
            <div class="px-4 py-3 bg-neutral-50 grid grid-cols-12 gap-2 items-center text-sm font-bold">
                <div class="col-span-9 text-neutral-700">Tổng lương ước tính tháng này</div>
                <div class="col-span-3 text-right text-emerald-700">{{ money($totalEstimatedPay) }}đ</div>
            </div>
        @endif
    </div>

    <p class="text-xs text-neutral-400 leading-relaxed">
        <i class="fa-solid fa-circle-info mr-1"></i>
        "Tổng giờ làm" chỉ tính các ca <strong>đã chốt</strong> — ca đang mở dở không tính vào tháng này cho tới khi được chốt. Chỉnh lương cho từng người ở trang Nhân viên. Đây là số liệu để tham khảo, không phải bảng lương chính thức.
    </p>
</div>
@endsection
