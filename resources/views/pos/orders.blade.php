@extends('layouts.app')
@section('title', 'Đơn hàng · Rót')
@section('page-title', 'Đơn hàng trong ca')
@section('content')
<div class="p-4 pb-24 md:p-8 max-w-2xl mx-auto">
    <div class="flex items-center gap-3 mb-5 md:mb-6">
        <div class="w-10 h-10 rounded-xl bg-[var(--accent-light)] text-[var(--accent-text)] flex items-center justify-center shrink-0">
            <i class="fa-solid fa-receipt"></i>
        </div>
        <h2 class="text-lg font-bold text-neutral-900">Đơn hàng trong ca</h2>
    </div>

    @if($customerRequests->isNotEmpty())
        <div class="mb-6">
            <div class="text-xs font-bold text-blue-700 uppercase tracking-wider mb-2 flex items-center gap-1.5">
                <i class="fa-solid fa-qrcode"></i>Yêu cầu từ khách qua QR ({{ $customerRequests->count() }})
            </div>
            <div class="space-y-2">
                @foreach($customerRequests as $req)
                    <div class="bg-blue-50 rounded-xl px-4 py-3.5 border border-blue-200">
                        <div class="flex items-center justify-between gap-3">
                            <div class="min-w-0">
                                <div class="text-neutral-900 font-semibold text-sm">
                                    {{ $req->customer_name ?: 'Khách' }}
                                    @if($req->customer_phone)
                                        <span class="text-neutral-500 font-normal">· {{ $req->customer_phone }}</span>
                                    @endif
                                    <span class="text-neutral-400 font-normal text-xs">· {{ $req->created_at->diffForHumans() }}</span>
                                </div>
                                <div class="text-neutral-600 text-xs mt-1">{{ $req->readable_items }}</div>
                                @if($req->note)
                                    <div class="text-neutral-500 text-xs mt-0.5 italic">"{{ $req->note }}"</div>
                                @endif
                            </div>
                            <div class="flex items-center gap-2 shrink-0">
                                <form method="POST" action="{{ route('pos.orders.requests.accept', $req->id) }}">
                                    @csrf
                                    <button class="text-xs px-3 py-1.5 rounded-lg bg-blue-600 hover:bg-blue-700 text-white font-semibold transition whitespace-nowrap">
                                        <i class="fa-solid fa-check mr-1"></i>Nhận đơn
                                    </button>
                                </form>
                                <form method="POST" action="{{ route('pos.orders.requests.reject', $req->id) }}"
                                      onsubmit="return confirm('Từ chối yêu cầu này của khách?')">
                                    @csrf
                                    <button class="text-xs px-3 py-1.5 rounded-lg bg-white border border-red-200 text-red-600 hover:bg-red-50 font-medium transition">
                                        <i class="fa-solid fa-xmark"></i>
                                    </button>
                                </form>
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    @endif

    @if($drafts->isNotEmpty())
        <div class="mb-6">
            <div class="text-xs font-bold text-amber-700 uppercase tracking-wider mb-2 flex items-center gap-1.5">
                <i class="fa-solid fa-clock"></i>Đang chờ ({{ $drafts->count() }})
            </div>
            <div class="space-y-2">
                @foreach($drafts as $draft)
                    <div class="bg-amber-50 rounded-xl px-4 py-3.5 border border-amber-200">
                        <div class="flex items-center justify-between">
                            <div class="min-w-0">
                                <div class="text-neutral-900 font-semibold text-sm">
                                    Đơn nháp #{{ $draft->id }}
                                    @if($draft->note)
                                        <span class="text-neutral-500 font-normal">— {{ $draft->note }}</span>
                                    @endif
                                </div>
                                <div class="text-neutral-500 text-xs mt-0.5">{{ $draft->items->sum('quantity') }} món · {{ money($draft->total) }}đ</div>
                            </div>
                            <div class="flex items-center gap-2 shrink-0">
                                <a href="{{ route('pos.orders.edit', $draft->id) }}"
                                   class="text-xs px-3 py-1.5 rounded-lg bg-amber-600 hover:bg-amber-700 text-white font-semibold transition">
                                    <i class="fa-solid fa-play mr-1"></i>Tiếp tục
                                </a>
                                <form method="POST" action="{{ route('pos.orders.cancel', $draft->id) }}"
                                      onsubmit="return confirm('Xoá đơn nháp #{{ $draft->id }}? Không thể khôi phục lại.')">
                                    @csrf
                                    <button class="text-xs px-3 py-1.5 rounded-lg bg-white border border-red-200 text-red-600 hover:bg-red-50 font-medium transition">
                                        <i class="fa-solid fa-trash-can"></i>
                                    </button>
                                </form>
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    @endif

    <div class="space-y-2">
        @forelse($orders as $order)
            @php
                $isCancelled = $order->status === 'da_huy';
            @endphp
            <div class="bg-white rounded-xl px-4 py-3.5 border {{ $isCancelled ? 'border-neutral-200 opacity-60' : 'border-neutral-200' }} shadow-sm">
                <div class="flex items-center justify-between">
                    <div class="min-w-0">
                        <div class="flex items-center gap-2 flex-wrap">
                            <span class="text-neutral-900 font-semibold text-sm">Đơn #{{ $order->id }}</span>
                            <span class="text-xs px-2 py-0.5 rounded-full font-medium
                                         {{ $isCancelled ? 'bg-red-50 text-red-600' : 'bg-emerald-50 text-emerald-700' }}">
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
                            @if($order->einvoice_status === 'issued')
                                <span class="text-xs px-2 py-0.5 rounded-full bg-emerald-50 text-emerald-700 font-medium">
                                    <i class="fa-solid fa-file-invoice"></i> Đã xuất HĐĐT
                                </span>
                            @elseif($order->einvoice_status === 'pending')
                                <span class="text-xs px-2 py-0.5 rounded-full bg-amber-50 text-amber-700 font-medium">
                                    <i class="fa-solid fa-clock"></i> HĐĐT đang xử lý
                                </span>
                            @elseif($order->einvoice_status === 'failed')
                                <span class="text-xs px-2 py-0.5 rounded-full bg-red-50 text-red-600 font-medium" title="{{ $order->einvoice_error }}">
                                    <i class="fa-solid fa-triangle-exclamation"></i> HĐĐT lỗi
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
                        <a href="{{ route('pos.orders.edit', $order->id) }}"
                           class="text-xs px-3 py-1.5 rounded-lg bg-neutral-100 text-neutral-700 hover:bg-neutral-200 font-medium transition">
                            <i class="fa-solid fa-pen mr-1"></i>Sửa đơn
                        </a>
                        @if(in_array($order->einvoice_status, ['failed', 'pending']))
                            <form method="POST" action="{{ route('pos.orders.einvoice.retry', $order->id) }}">
                                @csrf
                                <button class="text-xs px-3 py-1.5 rounded-lg bg-blue-50 text-blue-700 hover:bg-blue-100 font-medium transition">
                                    <i class="fa-solid fa-rotate mr-1"></i>Xuất lại HĐĐT
                                </button>
                            </form>
                        @endif
                        <form method="POST" action="{{ route('pos.orders.cancel', $order->id) }}"
                              onsubmit="return confirm('Huỷ đơn #{{ $order->id }}? Kho và sổ quỹ sẽ được hoàn tác tương ứng.')">
                            @csrf
                            <button class="text-xs px-3 py-1.5 rounded-lg bg-red-50 text-red-600 hover:bg-red-100 font-medium transition">
                                <i class="fa-solid fa-ban mr-1"></i>Huỷ đơn
                            </button>
                        </form>
                    </div>
                @endunless
            </div>
        @empty
            @if($drafts->isEmpty())
                <div class="text-center py-10 text-neutral-400">
                    <i class="fa-solid fa-receipt text-3xl mb-2 block"></i>
                    <p class="text-sm">Chưa có đơn nào trong ca này.</p>
                </div>
            @endif
        @endforelse
    </div>
    @if($orders instanceof \Illuminate\Contracts\Pagination\Paginator && $orders->hasPages())
        <div class="mt-4">{{ $orders->links() }}</div>
    @endif
</div>
@endsection
