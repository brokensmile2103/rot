@extends('layouts.app')
@section('title', 'Chốt ca · Rót')
@section('page-title', 'Chốt ca')
@section('content')
<div class="p-4 pb-24 md:p-8 max-w-lg mx-auto">
    <div class="flex items-center gap-3 mb-5 md:hidden">
        <div class="w-10 h-10 rounded-xl bg-red-50 text-red-600 flex items-center justify-center">
            <i class="fa-solid fa-lock"></i>
        </div>
        <h2 class="text-lg font-bold text-neutral-900">Chốt ca</h2>
    </div>

    <div class="bg-white rounded-2xl p-5 mb-5 border border-neutral-200 space-y-3 shadow-sm">
        <div class="flex justify-between items-center text-sm">
            <span class="text-neutral-500 font-medium"><i class="fa-solid fa-sack-dollar mr-2 text-neutral-400"></i>Tiền đầu ca</span>
            <span class="text-neutral-900 font-semibold">{{ money($shift->opening_cash) }}đ</span>
        </div>
        <div class="flex justify-between items-center text-sm pt-3 border-t border-neutral-200">
            <span class="text-neutral-500 font-medium"><i class="fa-solid fa-calculator mr-2 text-neutral-400"></i>Tiền mặt lý thuyết hiện tại</span>
            <span class="text-[var(--accent-text)] font-bold text-lg">{{ money($expected) }}đ</span>
        </div>
    </div>

    <form method="POST" action="{{ route('shift.close') }}" class="space-y-4">
        @csrf
        <x-input type="text" inputmode="numeric" class="money-input" name="closing_cash_actual" label="Đếm tiền mặt thực tế trong quầy" icon="fa-coins" required autocomplete="off" />
        <x-button variant="danger" icon="fa-lock">Xác nhận chốt ca</x-button>
    </form>
</div>
@endsection
