@extends('layouts.app')
@section('title', 'Sổ quỹ · Rót')
@section('page-title', 'Sổ quỹ')
@section('content')
<div x-data="{ type: 'expense' }" class="p-4 pb-24 md:p-8 max-w-3xl mx-auto">
    <div class="flex items-center gap-3 mb-5 md:hidden">
        <div class="w-10 h-10 rounded-xl bg-[var(--accent-light)] text-[var(--accent-text)] flex items-center justify-center">
            <i class="fa-solid fa-wallet"></i>
        </div>
        <h2 class="text-lg font-bold text-neutral-900">Sổ quỹ ca hiện tại</h2>
    </div>

    <div class="grid md:grid-cols-2 gap-5">
        <div>
            <div class="bg-gradient-to-br from-[var(--accent)] to-[var(--accent-hover)] rounded-2xl p-5 mb-5 text-center shadow-lg shadow-[var(--accent)]/20">
                <div class="text-xs text-amber-50 font-semibold">Tiền mặt lý thuyết hiện có</div>
                <div class="text-3xl font-bold text-white mt-1">{{ money($expected) }}đ</div>
            </div>

            <form method="POST" action="{{ route('cashbook.store') }}" class="bg-white rounded-2xl p-5 border border-neutral-200 shadow-sm space-y-3">
                @csrf
                <div class="grid grid-cols-3 gap-2">
                    <label class="text-center cursor-pointer">
                        <input type="radio" name="type" value="expense" x-model="type" class="peer hidden" checked>
                        <span class="flex flex-col items-center gap-1 py-2.5 rounded-xl bg-neutral-100 border border-neutral-200 peer-checked:bg-[var(--accent)] peer-checked:text-white peer-checked:border-[var(--accent)] text-neutral-700 text-xs font-semibold transition">
                            <i class="fa-solid fa-receipt"></i>Chi phí
                        </span>
                    </label>
                    <label class="text-center cursor-pointer">
                        <input type="radio" name="type" value="deposit" x-model="type" class="peer hidden">
                        <span class="flex flex-col items-center gap-1 py-2.5 rounded-xl bg-neutral-100 border border-neutral-200 peer-checked:bg-emerald-600 peer-checked:text-white peer-checked:border-emerald-600 text-neutral-700 text-xs font-semibold transition">
                            <i class="fa-solid fa-plus"></i>Nạp thêm
                        </span>
                    </label>
                    <label class="text-center cursor-pointer">
                        <input type="radio" name="type" value="withdrawal" x-model="type" class="peer hidden">
                        <span class="flex flex-col items-center gap-1 py-2.5 rounded-xl bg-neutral-100 border border-neutral-200 peer-checked:bg-red-600 peer-checked:text-white peer-checked:border-red-600 text-neutral-700 text-xs font-semibold transition">
                            <i class="fa-solid fa-minus"></i>Rút quỹ
                        </span>
                    </label>
                </div>
                <x-input type="text" inputmode="numeric" class="money-input" name="amount" placeholder="Số tiền" icon="fa-money-bill" required autocomplete="off" />
                <x-input type="text" name="note" placeholder="Ghi chú (VD: mua thêm đá)" icon="fa-note-sticky" />
                <x-button variant="ghost" icon="fa-floppy-disk">Ghi vào sổ quỹ</x-button>
            </form>
        </div>

        <div class="space-y-2">
            @forelse($shift->cashAdjustments as $adj)
                @php
                    $isNegative = in_array($adj->type, ['expense', 'withdrawal']);
                    $icon = match($adj->type) { 'expense' => 'fa-receipt', 'deposit' => 'fa-plus', default => 'fa-minus' };
                @endphp
                <div class="flex items-center justify-between text-sm bg-white rounded-xl px-4 py-3 border border-neutral-200 shadow-sm">
                    <div class="flex items-center gap-3">
                        <div class="w-9 h-9 rounded-lg flex items-center justify-center {{ $isNegative ? 'bg-red-50 text-red-600' : 'bg-emerald-50 text-emerald-600' }}">
                            <i class="fa-solid {{ $icon }}"></i>
                        </div>
                        <div>
                            <div class="text-neutral-900 font-medium">
                                @if($adj->type === 'expense') Chi phí
                                @elseif($adj->type === 'deposit') Nạp thêm
                                @else Rút quỹ @endif
                            </div>
                            @if($adj->note)
                                <div class="text-neutral-500 text-xs">{{ $adj->note }}</div>
                            @endif
                        </div>
                    </div>
                    <div class="{{ $isNegative ? 'text-red-600' : 'text-emerald-600' }} font-bold">
                        {{ $isNegative ? '-' : '+' }}{{ money($adj->amount) }}đ
                    </div>
                </div>
            @empty
                <div class="text-center py-10 text-neutral-400">
                    <i class="fa-solid fa-inbox text-3xl mb-2 block"></i>
                    <p class="text-sm">Chưa có ghi chép nào trong ca này.</p>
                </div>
            @endforelse
        </div>
    </div>
</div>
@endsection
