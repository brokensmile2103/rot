@extends('layouts.guest')
@section('title', 'Đăng nhập · Rót')
@section('meta_description', 'Đăng nhập Rót để quản lý bán hàng cho xe/quầy cà phê của bạn.')
@section('robots', 'noindex, follow')
@section('subtitle', 'Đăng nhập để bắt đầu bán hàng')
@section('content')
    <form method="POST" action="{{ route('login.attempt') }}" class="space-y-4">
        @csrf
        <x-input name="phone" label="Số điện thoại" icon="fa-phone" value="{{ old('phone') }}" required autofocus inputmode="tel" autocomplete="tel" />
        <div>
            <x-input name="password" type="password" label="Mật khẩu" icon="fa-lock" required autocomplete="current-password" />
            <a href="{{ route('password.request') }}" class="inline-block mt-1.5 text-xs text-amber-600 hover:underline">Quên mật khẩu?</a>
        </div>

        <label class="flex items-center gap-2 cursor-pointer">
            <input type="checkbox" name="remember" value="1" class="w-4 h-4 rounded border-neutral-300 text-amber-600 focus:ring-amber-500">
            <span class="text-sm text-neutral-600">Ghi nhớ đăng nhập</span>
        </label>

        <x-form-guard name="login" />

        <x-button icon="fa-right-to-bracket" class="mt-2">Đăng nhập</x-button>
    </form>

    @if(config('services.google.client_id'))
        <div class="flex items-center gap-4 my-5">
            <div class="flex-1 h-px bg-neutral-200"></div>
            <span class="text-xs text-neutral-400 font-medium px-2">HOẶC</span>
            <div class="flex-1 h-px bg-neutral-200"></div>
        </div>

        <a href="{{ route('auth.google') }}"
           class="flex items-center justify-center gap-2.5 w-full rounded-xl border border-neutral-300 bg-white hover:bg-neutral-50 text-neutral-700 py-3 px-4 text-sm font-semibold transition">
            <i class="fa-brands fa-google text-[#EA4335]"></i>Tiếp tục với Google
        </a>
    @endif

    @if(\App\Models\PlatformSetting::registrationOpen())
        <p class="text-center text-sm text-neutral-500 mt-5">
            Chưa có quán? <a href="{{ route('register') }}" class="text-amber-600 font-semibold hover:underline">Đăng ký miễn phí</a>
        </p>
    @endif
    <p class="text-center text-xs text-neutral-400 mt-2">
        <a href="{{ route('pages.about') }}" class="hover:underline">Giới thiệu</a> ·
        <a href="{{ route('pages.terms') }}" class="hover:underline">Điều khoản</a> ·
        <a href="{{ route('pages.privacy') }}" class="hover:underline">Chính sách bảo mật</a>
    </p>
@endsection
