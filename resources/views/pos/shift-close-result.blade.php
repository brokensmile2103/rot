@extends('layouts.app')
@section('title', 'Kết quả chốt ca · Rót')
@section('page-title', 'Kết quả chốt ca')
@section('content')
@php
    $isMatch = (float) $shift->variance === 0.0;
    $isSurplus = (float) $shift->variance > 0;
    $label = $isMatch ? 'Khớp quỹ' : ($isSurplus ? 'Dư quỹ' : 'Thiếu quỹ');
    $icon = $isMatch ? 'fa-circle-check' : 'fa-triangle-exclamation';
    // Dùng CHUỖI CLASS ĐẦY ĐỦ theo từng nhánh (không ghép chuỗi động
    // "bg-{$tone}-100") vì Tailwind v4 quét mã nguồn để biết class nào cần
    // sinh ra — ghép chuỗi lúc chạy PHP sẽ khiến Tailwind KHÔNG thấy được
    // tên class đầy đủ và bỏ qua không tạo CSS cho nó.
    [$iconBox, $heading, $amount, $cardBorder] = match(true) {
        $isMatch => ['bg-emerald-100 text-emerald-600', 'text-emerald-600', 'text-emerald-600', 'border-emerald-200'],
        $isSurplus => ['bg-amber-100 text-amber-600', 'text-amber-600', 'text-amber-600', 'border-amber-200'],
        default => ['bg-red-100 text-red-600', 'text-red-600', 'text-red-600', 'border-red-200'],
    };
@endphp
<div class="p-4 pb-24 md:p-8 max-w-lg mx-auto">
    <div class="flex flex-col items-center text-center mb-6 pt-2">
        <div class="w-16 h-16 rounded-2xl {{ $iconBox }} flex items-center justify-center text-2xl mb-3">
            <i class="fa-solid {{ $icon }}"></i>
        </div>
        <h2 class="text-lg font-bold text-neutral-900">Đã chốt ca — <span class="{{ $heading }}">{{ $label }}</span></h2>
        <p class="text-sm text-neutral-500 mt-1">{{ $shift->opened_at->format('H:i') }} - {{ $shift->closed_at->format('H:i') }}, {{ $shift->closed_at->format('d/m/Y') }}</p>
    </div>

    <div class="bg-white rounded-2xl p-5 mb-4 border {{ $cardBorder }} shadow-sm text-center">
        <div class="text-xs text-neutral-500 font-medium mb-1">Chênh lệch quỹ tiền mặt</div>
        <div class="text-3xl font-extrabold {{ $amount }}">
            {{ $shift->variance > 0 ? '+' : '' }}{{ money($shift->variance) }}đ
        </div>
        @if(! $isMatch)
            <p class="text-xs text-neutral-500 mt-2">
                @if($isSurplus)
                    Tiền mặt đếm được nhiều hơn lý thuyết {{ money(abs($shift->variance)) }}đ.
                @else
                    Tiền mặt đếm được ít hơn lý thuyết {{ money(abs($shift->variance)) }}đ — kiểm tra lại phiếu chi/thối tiền nếu cần.
                @endif
            </p>
        @endif
    </div>

    <div class="grid grid-cols-3 gap-2 mb-6">
        <div class="bg-white rounded-xl p-3 border border-neutral-200 text-center">
            <div class="text-[0.65rem] text-neutral-500 font-medium mb-1">Đầu ca</div>
            <div class="text-sm font-bold text-neutral-800">{{ money($shift->opening_cash) }}đ</div>
        </div>
        <div class="bg-white rounded-xl p-3 border border-neutral-200 text-center">
            <div class="text-[0.65rem] text-neutral-500 font-medium mb-1">Lý thuyết</div>
            <div class="text-sm font-bold text-neutral-800">{{ money($shift->closing_cash_expected) }}đ</div>
        </div>
        <div class="bg-white rounded-xl p-3 border border-neutral-200 text-center">
            <div class="text-[0.65rem] text-neutral-500 font-medium mb-1">Đếm thực tế</div>
            <div class="text-sm font-bold text-neutral-800">{{ money($shift->closing_cash_actual) }}đ</div>
        </div>
    </div>

    <div class="space-y-2">
        <a href="{{ route('shift.create') }}" class="w-full inline-flex items-center justify-center gap-2 rounded-xl py-3.5 font-semibold text-base transition active:scale-[0.98] bg-[var(--accent)] hover:bg-[var(--accent-hover)] text-white shadow-lg shadow-[var(--accent)]/25">
            <i class="fa-solid fa-play"></i>Mở ca mới
        </a>
        <a href="{{ route('shift.history.show', $shift->id) }}" class="w-full inline-flex items-center justify-center gap-2 rounded-xl py-3.5 font-semibold text-base transition active:scale-[0.98] bg-neutral-100 hover:bg-neutral-200 text-neutral-800">
            <i class="fa-solid fa-receipt"></i>Xem chi tiết đơn trong ca
        </a>
    </div>
</div>
@endsection
