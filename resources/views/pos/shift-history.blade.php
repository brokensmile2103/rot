@extends('layouts.app')
@section('title', 'Lịch sử ca · Rót')
@section('page-title', 'Lịch sử ca')
@section('content')
<div class="p-4 pb-24 md:p-8 max-w-2xl mx-auto">
    <div class="flex items-center justify-between gap-3 mb-5 md:mb-6">
        <div class="flex items-center gap-3">
            <div class="w-10 h-10 rounded-xl bg-[var(--accent-light)] text-[var(--accent-text)] flex items-center justify-center shrink-0">
                <i class="fa-solid fa-clock-rotate-left"></i>
            </div>
            <h2 class="text-lg font-bold text-neutral-900">Lịch sử ca</h2>
        </div>
        <form method="GET">
            <input type="date" name="date" id="history-date" autocomplete="off" value="{{ $date }}" onchange="this.form.submit()"
                   class="rounded-xl border border-neutral-300 bg-white text-neutral-900 py-2 px-3 text-sm focus:border-[var(--accent-ring)] focus:ring-1 focus:ring-[var(--accent-ring)] focus:outline-none">
        </form>
    </div>

    @if($date)
        <a href="{{ route('shift.history') }}" class="inline-flex items-center gap-1.5 text-xs text-neutral-500 hover:text-neutral-700 mb-3">
            <i class="fa-solid fa-xmark"></i>Bỏ lọc ngày {{ \Illuminate\Support\Carbon::parse($date)->format('d/m/Y') }}
        </a>
    @endif

    <div class="space-y-2">
        @forelse($shifts as $shift)
            <a href="{{ route('shift.history.show', $shift->id) }}"
               class="block bg-white rounded-xl px-4 py-3.5 border border-neutral-200 shadow-sm hover:border-[var(--accent)] transition">
                <div class="flex items-center justify-between">
                    <div class="min-w-0">
                        <div class="text-neutral-900 font-semibold text-sm">
                            {{ $shift->opened_at->format('d/m/Y') }}
                            <span class="text-neutral-400 font-normal">· {{ $shift->opened_at->format('H:i') }} - {{ $shift->closed_at->format('H:i') }}</span>
                        </div>
                        <div class="text-neutral-500 text-xs mt-0.5">{{ $shift->user->name }}</div>
                    </div>
                    <div class="text-right shrink-0">
                        <div class="font-bold text-neutral-900">{{ money($shift->revenue ?? 0) }}đ</div>
                        <div class="text-xs mt-0.5 {{ $shift->variance == 0 ? 'text-neutral-400' : ($shift->variance > 0 ? 'text-emerald-600' : 'text-red-600') }}">
                            @if($shift->variance == 0)
                                Khớp quỹ
                            @else
                                Lệch {{ $shift->variance > 0 ? '+' : '' }}{{ money($shift->variance) }}đ
                            @endif
                        </div>
                    </div>
                </div>
            </a>
        @empty
            <div class="text-center py-10 text-neutral-400">
                <i class="fa-solid fa-clock-rotate-left text-3xl mb-2 block"></i>
                <p class="text-sm">{{ $date ? 'Không có ca nào chốt vào ngày này.' : 'Chưa có ca nào được chốt.' }}</p>
            </div>
        @endforelse
    </div>

    <div class="mt-4">
        {{ $shifts->links() }}
    </div>
</div>
@endsection
