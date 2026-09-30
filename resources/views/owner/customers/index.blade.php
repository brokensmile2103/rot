@extends('layouts.app')
@section('title', 'Khách hàng · Rót')
@section('page-title', 'Khách hàng thân thiết')
@section('content')
<div class="p-4 pb-24 md:p-8 max-w-2xl mx-auto">
    <div class="flex items-center gap-3 mb-5 md:mb-6">
        <div class="w-10 h-10 rounded-xl bg-[var(--accent-light)] text-[var(--accent-text)] flex items-center justify-center shrink-0">
            <i class="fa-solid fa-users"></i>
        </div>
        <h2 class="text-lg font-bold text-neutral-900">Khách hàng — {{ $location->name }}</h2>
    </div>

    @unless($location->loyalty_enabled)
        <div class="bg-amber-50 border border-amber-200 rounded-xl px-4 py-3 text-sm text-amber-800 mb-5">
            Tính năng tích điểm đang <strong>tắt</strong>. Bật ở <a href="{{ route('owner.settings.index') }}" class="underline font-semibold">Cài đặt</a> để bắt đầu ghi nhận khách hàng lúc thanh toán.
        </div>
    @endunless

    <form method="GET" class="mb-4">
        <input type="text" name="q" value="{{ request('q') }}" placeholder="Tìm theo tên hoặc số điện thoại..."
               class="w-full rounded-xl border border-neutral-300 bg-white text-neutral-900 placeholder-neutral-400 py-2.5 px-4 text-sm focus:border-[var(--accent-ring)] focus:ring-1 focus:ring-[var(--accent-ring)] focus:outline-none">
    </form>

    <div class="space-y-2">
        @forelse($customers as $customer)
            <div class="bg-white rounded-xl px-4 py-3.5 border border-neutral-200 shadow-sm flex items-center justify-between">
                <div class="min-w-0">
                    <div class="text-neutral-900 font-semibold text-sm">{{ $customer->name ?: 'Chưa có tên' }}</div>
                    <div class="text-neutral-500 text-xs mt-0.5">{{ $customer->phone }} · Đã chi {{ money($customer->total_spent) }}đ</div>
                </div>
                <div class="text-right shrink-0">
                    <div class="text-lg font-bold text-[var(--accent-text)]">{{ money($customer->points) }}</div>
                    <div class="text-neutral-400 text-xs">điểm</div>
                </div>
            </div>
        @empty
            <div class="text-center py-10 text-neutral-400">
                <i class="fa-solid fa-users text-3xl mb-2 block"></i>
                <p class="text-sm">Chưa có khách hàng nào. Nhập SĐT khách lúc thanh toán để bắt đầu ghi nhận.</p>
            </div>
        @endforelse
    </div>

    <div class="mt-4">
        {{ $customers->links() }}
    </div>
</div>
@endsection
