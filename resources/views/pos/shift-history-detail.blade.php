@extends('layouts.app')
@section('title', 'Chi tiết ca · Rót')
@section('page-title', 'Chi tiết ca đã chốt')
@section('content')
<div class="p-4 pb-24 md:p-8 max-w-2xl mx-auto">
    <div class="flex items-center gap-3 mb-5 md:mb-6">
        <a href="{{ route('shift.history') }}" class="w-10 h-10 rounded-xl bg-neutral-100 text-neutral-600 flex items-center justify-center shrink-0 hover:bg-neutral-200 transition">
            <i class="fa-solid fa-arrow-left"></i>
        </a>
        <div>
            <h2 class="text-lg font-bold text-neutral-900">{{ $shift->opened_at->format('d/m/Y') }}</h2>
            <p class="text-xs text-neutral-500">{{ $shift->user->name }} · {{ $shift->opened_at->format('H:i') }} - {{ $shift->closed_at->format('H:i') }}</p>
        </div>
    </div>

    <div class="grid grid-cols-2 gap-3 mb-5">
        <div class="bg-white rounded-2xl p-4 border border-neutral-200 shadow-sm">
            <div class="text-xs text-neutral-500 font-medium mb-1">Doanh thu tiền mặt+chuyển khoản</div>
            <div class="text-lg font-bold text-neutral-900">{{ money($revenue) }}đ</div>
        </div>
        <div class="bg-white rounded-2xl p-4 border border-neutral-200 shadow-sm">
            <div class="text-xs text-neutral-500 font-medium mb-1">Chênh lệch quỹ tiền mặt</div>
            <div class="text-lg font-bold {{ $shift->variance == 0 ? 'text-neutral-900' : ($shift->variance > 0 ? 'text-emerald-600' : 'text-red-600') }}">
                {{ $shift->variance > 0 ? '+' : '' }}{{ money($shift->variance) }}đ
            </div>
        </div>
        <div class="bg-white rounded-2xl p-4 border border-neutral-200 shadow-sm">
            <div class="text-xs text-neutral-500 font-medium mb-1">Tiền đầu ca</div>
            <div class="text-sm font-semibold text-neutral-700">{{ money($shift->opening_cash) }}đ</div>
        </div>
        <div class="bg-white rounded-2xl p-4 border border-neutral-200 shadow-sm">
            <div class="text-xs text-neutral-500 font-medium mb-1">Tiền mặt đếm thực tế</div>
            <div class="text-sm font-semibold text-neutral-700">{{ money($shift->closing_cash_actual) }}đ</div>
        </div>
    </div>

    <p class="text-xs text-neutral-400 mb-3">
        <i class="fa-solid fa-lock mr-1"></i>Ca đã chốt — chỉ xem lại được, không sửa/huỷ đơn trong ca này nữa.
    </p>

    <div class="space-y-2">
        @forelse($orders as $order)
            @php $isCancelled = $order->status === 'da_huy'; @endphp
            <div class="bg-white rounded-xl px-4 py-3.5 border {{ $isCancelled ? 'border-neutral-200 opacity-60' : 'border-neutral-200' }} shadow-sm">
                <div class="flex items-center justify-between">
                    <div class="min-w-0">
                        <div class="flex items-center gap-2 flex-wrap">
                            <span class="text-neutral-900 font-semibold text-sm">Đơn #{{ $order->id }}</span>
                            <span class="text-xs px-2 py-0.5 rounded-full font-medium {{ $isCancelled ? 'bg-red-50 text-red-600' : 'bg-emerald-50 text-emerald-700' }}">
                                {{ $isCancelled ? 'Đã huỷ' : 'Hoàn thành' }}
                            </span>
                            @if($order->edited_at)
                                <span class="text-xs px-2 py-0.5 rounded-full bg-neutral-100 text-neutral-500 font-medium">Đã sửa</span>
                            @endif
                            @if($order->discount_amount > 0)
                                <span class="text-xs px-2 py-0.5 rounded-full bg-blue-50 text-blue-700 font-medium">
                                    <i class="fa-solid fa-tag"></i> -{{ money($order->discount_amount) }}đ
                                </span>
                            @endif
                            @if($order->customer)
                                <span class="text-xs px-2 py-0.5 rounded-full bg-purple-50 text-purple-700 font-medium">
                                    <i class="fa-solid fa-heart"></i> {{ $order->customer->name ?: $order->customer->phone }}
                                </span>
                            @endif
                        </div>
                        <div class="text-neutral-500 text-xs mt-0.5">
                            {{ $order->completed_at?->format('H:i') }} ·
                            {{ $order->order_type === 'mang_di' ? 'Mang đi' : 'Ngồi lại' }} ·
                            {{ $order->items->sum('quantity') }} món
                        </div>
                    </div>
                    <div class="text-right shrink-0">
                        <div class="font-bold text-neutral-900">{{ money($order->total) }}đ</div>
                    </div>
                </div>

                @unless($isCancelled)
                    <div class="flex items-center gap-2 mt-3 pt-3 border-t border-neutral-100">
                        <a href="{{ route('pos.orders.receipt', $order->id) }}" target="_blank"
                           class="text-xs px-3 py-1.5 rounded-lg bg-neutral-100 text-neutral-700 hover:bg-neutral-200 font-medium transition">
                            <i class="fa-solid fa-print mr-1"></i>In lại
                        </a>
                    </div>
                @endunless
            </div>
        @empty
            <div class="text-center py-10 text-neutral-400">
                <i class="fa-solid fa-receipt text-3xl mb-2 block"></i>
                <p class="text-sm">Ca này không có đơn nào.</p>
            </div>
        @endforelse
    </div>
    @if($orders->hasPages())
        <div class="mt-4">{{ $orders->links() }}</div>
    @endif
</div>
@endsection
