@extends('layouts.app')
@section('title', 'Các xe cà phê · Rót')
@section('page-title', 'Các xe cà phê')
@section('content')
<div class="p-4 pb-24 md:p-8 max-w-2xl mx-auto">
    <div class="flex items-center gap-3 mb-5 md:mb-6">
        <div class="w-10 h-10 rounded-xl bg-[var(--accent-light)] text-[var(--accent-text)] flex items-center justify-center shrink-0">
            <i class="fa-solid fa-shop"></i>
        </div>
        <h2 class="text-lg font-bold text-neutral-900 flex-1">Các xe/quầy của bạn</h2>
        <a href="{{ route('owner.locations.report') }}" class="text-xs px-3 py-2 rounded-lg bg-white border border-neutral-300 text-neutral-600 hover:bg-neutral-50 font-medium transition shrink-0">
            <i class="fa-solid fa-layer-group mr-1"></i>Báo cáo tổng hợp
        </a>
    </div>

    <div x-data="{ editingId: null }" class="grid sm:grid-cols-2 gap-3 mb-5">
        @foreach($locations as $loc)
            <div class="bg-white rounded-xl px-4 py-3.5 border border-neutral-200 shadow-sm">
                <template x-if="editingId !== {{ $loc->id }}">
                    <div class="flex items-center justify-between">
                        <div class="flex items-center gap-3 min-w-0">
                            <div class="w-9 h-9 rounded-lg bg-neutral-100 text-[var(--accent-text)] flex items-center justify-center shrink-0">
                                <i class="fa-solid fa-mug-hot"></i>
                            </div>
                            <div class="min-w-0">
                                <div class="text-neutral-900 text-sm font-semibold truncate">{{ $loc->name }}</div>
                                <div class="text-neutral-500 text-xs truncate">{{ $loc->address ?: 'Chưa có địa chỉ' }}</div>
                            </div>
                        </div>
                        <div class="flex items-center gap-1.5 shrink-0">
                            <button @click="editingId = {{ $loc->id }}" class="text-xs px-2.5 py-1.5 rounded-lg bg-neutral-100 text-neutral-600 hover:bg-neutral-200 transition">
                                <i class="fa-solid fa-pen"></i>
                            </button>
                            <form method="POST" action="{{ route('owner.locations.switch', $loc->id) }}">
                                @csrf
                                <button class="text-xs px-3 py-1.5 rounded-lg bg-[var(--accent)] hover:bg-[var(--accent-hover)] text-white font-medium transition">Chuyển</button>
                            </form>
                        </div>
                    </div>
                </template>

                <form x-show="editingId === {{ $loc->id }}" x-cloak method="POST" action="{{ route('owner.locations.update', $loc->id) }}" class="space-y-2">
                    @csrf @method('PUT')
                    <input name="name" value="{{ $loc->name }}" required autocomplete="off"
                           class="w-full rounded-lg border border-neutral-300 bg-white text-neutral-900 py-2 px-3 text-sm focus:border-[var(--accent-ring)] focus:outline-none">
                    <input name="address" value="{{ $loc->address }}" placeholder="Địa chỉ (không bắt buộc)" autocomplete="street-address"
                           class="w-full rounded-lg border border-neutral-300 bg-white text-neutral-900 placeholder-neutral-400 py-2 px-3 text-sm focus:border-[var(--accent-ring)] focus:outline-none">
                    <div class="flex gap-2">
                        <button type="button" @click="editingId = null" class="flex-1 text-xs py-2 rounded-lg bg-neutral-100 text-neutral-600 font-medium">Huỷ</button>
                        <button class="flex-1 text-xs py-2 rounded-lg bg-[var(--accent)] hover:bg-[var(--accent-hover)] text-white font-medium transition">Lưu</button>
                    </div>
                </form>
            </div>
        @endforeach
    </div>

    <details class="bg-white rounded-2xl p-4 border border-neutral-200 shadow-sm">
        <summary class="text-[var(--accent-text)] font-semibold cursor-pointer flex items-center gap-2">
            <i class="fa-solid fa-circle-plus"></i>Thêm xe cà phê mới
        </summary>
        <form method="POST" action="{{ route('owner.locations.store') }}" class="mt-3 space-y-2">
            @csrf
            <input name="name" id="location-name" autocomplete="off" placeholder="Tên xe cà phê" required
                   class="w-full rounded-xl border border-neutral-300 bg-white text-neutral-900 placeholder-neutral-400 py-2.5 px-3 text-sm focus:border-[var(--accent-ring)] focus:ring-1 focus:ring-[var(--accent-ring)] focus:outline-none">
            <input name="address" id="location-address" autocomplete="street-address" placeholder="Địa chỉ (không bắt buộc)"
                   class="w-full rounded-xl border border-neutral-300 bg-white text-neutral-900 placeholder-neutral-400 py-2.5 px-3 text-sm focus:border-[var(--accent-ring)] focus:ring-1 focus:ring-[var(--accent-ring)] focus:outline-none">
            <button class="w-full bg-[var(--accent)] hover:bg-[var(--accent-hover)] text-white rounded-xl py-2.5 text-sm font-semibold transition">Thêm</button>
        </form>
    </details>
</div>
@endsection
