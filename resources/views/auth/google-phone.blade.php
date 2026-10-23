@extends('layouts.guest')
@section('title', 'Hoàn tất đăng ký · Rót')
@section('robots', 'noindex, follow')
@section('subtitle', 'Gần xong rồi — chỉ còn 1 bước nữa thôi')
@section('content')
    <div class="flex items-center gap-3 mb-5 bg-amber-50 rounded-xl p-3">
        @if($pending['avatar_url'] ?? null)
            <img src="{{ $pending['avatar_url'] }}" alt="" class="w-10 h-10 rounded-full shrink-0">
        @else
            <div class="w-10 h-10 rounded-full bg-amber-100 text-amber-700 flex items-center justify-center shrink-0"><i class="fa-solid fa-user"></i></div>
        @endif
        <div class="min-w-0">
            <p class="text-sm font-semibold text-neutral-900 truncate">Xin chào, {{ $pending['name'] }}!</p>
            <p class="text-xs text-neutral-500 truncate">{{ $pending['email'] ?? 'Đã xác thực qua Google' }}</p>
        </div>
    </div>

    <p class="text-sm text-neutral-500 mb-4 leading-relaxed">
        Rót dùng số điện thoại để đăng nhập và liên hệ khi cần — nhập nốt thông tin dưới đây để tạo quán của bạn.
    </p>

    <form method="POST" action="{{ route('register.google-phone.store') }}" class="space-y-4">
        @csrf
        <x-input name="shop_name" label="Tên quán/xe cà phê" icon="fa-shop" placeholder="VD: Cà phê Cô Ba" value="{{ old('shop_name') }}" required autofocus autocomplete="off" />
        <x-input name="phone" label="Số điện thoại (dùng để đăng nhập)" icon="fa-phone" value="{{ old('phone') }}" required inputmode="tel" autocomplete="tel" />

        <label class="flex items-start gap-2.5 cursor-pointer">
            <input type="checkbox" name="terms" value="1" required
                   class="w-4 h-4 mt-0.5 rounded border-neutral-300 text-amber-600 focus:ring-amber-500 shrink-0">
            <span class="text-sm text-neutral-600 leading-relaxed">
                Tôi đã đọc và đồng ý với
                <a href="{{ route('pages.terms') }}" target="_blank" class="text-amber-600 font-medium hover:underline">Điều khoản sử dụng</a>
                và
                <a href="{{ route('pages.privacy') }}" target="_blank" class="text-amber-600 font-medium hover:underline">Chính sách bảo mật</a>
                của Rót.
            </span>
        </label>

        <x-form-guard name="register-google-phone" />

        <x-button icon="fa-rocket" class="mt-2">Tạo quán ngay</x-button>
    </form>
@endsection
