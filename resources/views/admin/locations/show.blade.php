@extends('layouts.admin')
@section('title', $location->name.' · Super Admin')
@section('content')
    <div class="flex items-center gap-2 mb-1">
        <a href="{{ route('admin.dashboard') }}" class="text-sm text-neutral-400 hover:text-neutral-600 transition">
            <i class="fa-solid fa-arrow-left mr-1"></i>Danh sách quán
        </a>
    </div>

    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 mb-6">
        <div>
            <div class="flex items-center gap-2 flex-wrap">
                <h1 class="text-xl font-bold text-neutral-900">{{ $location->name }}</h1>
                <span class="text-xs px-2 py-0.5 rounded-full font-medium {{ $location->is_active ? 'bg-emerald-50 text-emerald-700' : 'bg-red-50 text-red-600' }}">
                    {{ $location->is_active ? 'Hoạt động' : 'Đã khoá' }}
                </span>
                @if($stats['has_open_shift'])
                    <span class="text-xs px-2 py-0.5 rounded-full font-medium bg-blue-50 text-blue-700">
                        <i class="fa-solid fa-circle text-[6px] align-middle mr-1"></i>Đang mở ca
                    </span>
                @endif
            </div>
            <p class="text-sm text-neutral-500 mt-1">
                Chủ quán: {{ $location->owner?->name ?? '(đã xoá)' }} · {{ $location->owner?->phone }}
                · Đăng ký {{ $location->created_at->format('d/m/Y') }}
                @if($location->address) · {{ $location->address }} @endif
            </p>
        </div>
        <form method="POST" action="{{ route('admin.locations.toggle', $location->id) }}" class="shrink-0"
              onsubmit="return confirm('{{ $location->is_active ? 'Tạm khoá' : 'Mở lại' }} quán \"{{ $location->name }}\"?')">
            @csrf @method('PUT')
            <button class="text-xs px-3 py-1.5 rounded-lg font-medium transition {{ $location->is_active ? 'bg-red-50 text-red-600 hover:bg-red-100' : 'bg-emerald-50 text-emerald-700 hover:bg-emerald-100' }}">
                {{ $location->is_active ? 'Tạm khoá quán' : 'Mở lại quán' }}
            </button>
        </form>
    </div>

    <div class="bg-blue-50 border border-blue-100 rounded-xl px-4 py-3 mb-6 text-xs text-blue-700 flex items-start gap-2">
        <i class="fa-solid fa-shield-halved mt-0.5"></i>
        <p>Trang này chỉ hiển thị SỐ LƯỢNG/TRUNG BÌNH để đánh giá quán còn hoạt động hay không — không hiển thị nội dung đơn hàng, doanh thu, hay thông tin khách hàng của quán.</p>
    </div>

    {{-- Nhịp bán hàng — trọng tâm chính của trang, trả lời câu hỏi "quán này còn sống không" --}}
    <div class="bg-white rounded-2xl border border-neutral-200 p-5 mb-6">
        <h2 class="text-sm font-bold text-neutral-900 mb-4">
            <i class="fa-solid fa-chart-line text-neutral-400 mr-1.5"></i>Nhịp bán hàng
        </h2>

        @if(is_null($activity))
            <p class="text-sm text-neutral-400 py-4 text-center">Quán chưa phát sinh đơn hàng hoàn thành nào.</p>
        @else
            <div class="grid grid-cols-3 gap-2 sm:gap-3 mb-4">
                <div class="bg-neutral-50 rounded-xl p-3 sm:p-4 text-center">
                    <p class="text-xs text-neutral-500 font-medium mb-1">TB / ngày</p>
                    <p class="text-lg sm:text-xl font-bold text-neutral-900">{{ money($activity['avg_per_day'], 1) }}</p>
                </div>
                <div class="bg-neutral-50 rounded-xl p-3 sm:p-4 text-center">
                    <p class="text-xs text-neutral-500 font-medium mb-1">TB / tuần</p>
                    <p class="text-lg sm:text-xl font-bold text-neutral-900">{{ money($activity['avg_per_week'], 1) }}</p>
                </div>
                <div class="bg-neutral-50 rounded-xl p-3 sm:p-4 text-center">
                    <p class="text-xs text-neutral-500 font-medium mb-1">TB / tháng</p>
                    <p class="text-lg sm:text-xl font-bold text-neutral-900">{{ money($activity['avg_per_month'], 1) }}</p>
                </div>
            </div>
            <p class="text-xs text-neutral-400 mb-4">
                Tính trên {{ money($activity['days_active']) }} ngày kể từ đơn hoàn thành đầu tiên ({{ $activity['first_order_at']->format('d/m/Y') }}) đến hôm nay.
            </p>

            <div class="grid grid-cols-2 gap-3 border-t border-neutral-100 pt-4">
                <div>
                    <p class="text-xs text-neutral-500 font-medium mb-1">Đơn 7 ngày gần nhất</p>
                    <p class="text-base font-bold text-neutral-900">{{ money($activity['last_7_days']) }}</p>
                </div>
                <div>
                    <p class="text-xs text-neutral-500 font-medium mb-1">Đơn 30 ngày gần nhất</p>
                    <p class="text-base font-bold text-neutral-900">{{ money($activity['last_30_days']) }}</p>
                </div>
            </div>

            <div class="flex items-center gap-2 mt-4 pt-4 border-t border-neutral-100 text-xs font-medium
                        {{ $activity['days_since_last_order'] <= 3 ? 'text-emerald-600' : ($activity['days_since_last_order'] <= 14 ? 'text-amber-600' : 'text-red-600') }}">
                <i class="fa-solid fa-clock"></i>
                @if($activity['days_since_last_order'] == 0)
                    Đơn hoàn thành gần nhất: hôm nay
                @else
                    Đơn hoàn thành gần nhất: {{ $activity['days_since_last_order'] }} ngày trước ({{ $activity['last_order_at']->format('d/m/Y') }})
                @endif
            </div>
        @endif
    </div>

    {{-- Tổng đơn theo trạng thái --}}
    <div class="bg-white rounded-2xl border border-neutral-200 p-5 mb-6">
        <h2 class="text-sm font-bold text-neutral-900 mb-4">
            <i class="fa-solid fa-receipt text-neutral-400 mr-1.5"></i>Tổng đơn hàng (toàn thời gian)
        </h2>
        <div class="grid grid-cols-2 sm:grid-cols-4 gap-2 sm:gap-3">
            <div class="bg-neutral-50 rounded-xl p-3 sm:p-4 text-center">
                <p class="text-xs text-neutral-500 font-medium mb-1">Tổng cộng</p>
                <p class="text-lg sm:text-xl font-bold text-neutral-900">{{ money($orderCounts['total']) }}</p>
            </div>
            <div class="bg-neutral-50 rounded-xl p-3 sm:p-4 text-center">
                <p class="text-xs text-neutral-500 font-medium mb-1">Hoàn thành</p>
                <p class="text-lg sm:text-xl font-bold text-emerald-600">{{ money($orderCounts['completed']) }}</p>
            </div>
            <div class="bg-neutral-50 rounded-xl p-3 sm:p-4 text-center">
                <p class="text-xs text-neutral-500 font-medium mb-1">Đã huỷ</p>
                <p class="text-lg sm:text-xl font-bold text-red-500">{{ money($orderCounts['cancelled']) }}</p>
            </div>
            <div class="bg-neutral-50 rounded-xl p-3 sm:p-4 text-center">
                <p class="text-xs text-neutral-500 font-medium mb-1">Đang nháp</p>
                <p class="text-lg sm:text-xl font-bold text-neutral-500">{{ money($orderCounts['draft']) }}</p>
            </div>
        </div>
    </div>

    <div class="grid sm:grid-cols-2 gap-6">
        {{-- Quy mô quán --}}
        <div class="bg-white rounded-2xl border border-neutral-200 p-5">
            <h2 class="text-sm font-bold text-neutral-900 mb-4">
                <i class="fa-solid fa-shop text-neutral-400 mr-1.5"></i>Quy mô quán
            </h2>
            <dl class="space-y-2.5 text-sm">
                <div class="flex items-center justify-between">
                    <dt class="text-neutral-500">Nhân sự</dt>
                    <dd class="font-semibold text-neutral-900">{{ money($stats['staff_count']) }}</dd>
                </div>
                <div class="flex items-center justify-between">
                    <dt class="text-neutral-500">Danh mục thực đơn</dt>
                    <dd class="font-semibold text-neutral-900">{{ money($stats['category_count']) }}</dd>
                </div>
                <div class="flex items-center justify-between">
                    <dt class="text-neutral-500">Món (sản phẩm)</dt>
                    <dd class="font-semibold text-neutral-900">{{ money($stats['product_count']) }}</dd>
                </div>
                <div class="flex items-center justify-between">
                    <dt class="text-neutral-500">Nguyên liệu trong kho</dt>
                    <dd class="font-semibold text-neutral-900">{{ money($stats['ingredient_count']) }}</dd>
                </div>
                <div class="flex items-center justify-between">
                    <dt class="text-neutral-500">Khách hàng thân thiết</dt>
                    <dd class="font-semibold text-neutral-900">{{ money($stats['customer_count']) }}</dd>
                </div>
                <div class="flex items-center justify-between">
                    <dt class="text-neutral-500">Tổng số ca đã mở</dt>
                    <dd class="font-semibold text-neutral-900">{{ money($stats['shift_count']) }}</dd>
                </div>
                <div class="flex items-center justify-between">
                    <dt class="text-neutral-500">Ca gần nhất đã chốt</dt>
                    <dd class="font-semibold text-neutral-900">
                        {{ $stats['last_shift_closed_at'] ? $stats['last_shift_closed_at']->format('d/m/Y H:i') : '—' }}
                    </dd>
                </div>
            </dl>
        </div>

        {{-- Tính năng đang sử dụng --}}
        <div class="bg-white rounded-2xl border border-neutral-200 p-5">
            <h2 class="text-sm font-bold text-neutral-900 mb-4">
                <i class="fa-solid fa-toggle-on text-neutral-400 mr-1.5"></i>Tính năng đang bật
            </h2>
            <dl class="space-y-2.5 text-sm">
                @php
                    $featureLabels = [
                        'loyalty_enabled' => 'Khách hàng thân thiết',
                        'qr_ordering_enabled' => 'Đặt món qua QR',
                        'receipt_enabled' => 'In hoá đơn',
                        'einvoice_configured' => 'Hoá đơn điện tử (SePay)',
                        'bank_account_configured' => 'Nhận chuyển khoản VietQR',
                    ];
                @endphp
                @foreach($featureLabels as $key => $label)
                    <div class="flex items-center justify-between">
                        <dt class="text-neutral-500">{{ $label }}</dt>
                        <dd>
                            @if($features[$key])
                                <span class="text-xs px-2 py-0.5 rounded-full font-medium bg-emerald-50 text-emerald-700">Đang bật</span>
                            @else
                                <span class="text-xs px-2 py-0.5 rounded-full font-medium bg-neutral-100 text-neutral-500">Chưa dùng</span>
                            @endif
                        </dd>
                    </div>
                @endforeach
            </dl>
        </div>
    </div>
@endsection
