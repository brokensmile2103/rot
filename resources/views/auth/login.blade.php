@extends('layouts.guest')
@section('title', 'Đăng nhập · Rót')
@section('meta_description', 'Đăng nhập Rót để bắt đầu bán hàng cho xe/quầy cà phê của bạn.')
@section('subtitle', 'Đăng nhập để bắt đầu bán hàng')
@section('content')
    <form method="POST" action="{{ route('login.attempt') }}" class="space-y-4">
        @csrf
        <x-input name="phone" label="Số điện thoại" icon="fa-phone" value="{{ old('phone') }}" required autofocus inputmode="tel" autocomplete="tel" />
        <x-input name="password" type="password" label="Mật khẩu" icon="fa-lock" required autocomplete="current-password" />

        <label class="flex items-center gap-2 cursor-pointer">
            <input type="checkbox" name="remember" value="1" class="w-4 h-4 rounded border-neutral-300 text-amber-600 focus:ring-amber-500">
            <span class="text-sm text-neutral-600">Ghi nhớ đăng nhập</span>
        </label>

        <x-form-guard name="login" />

        <x-button icon="fa-right-to-bracket" class="mt-2">Đăng nhập</x-button>
    </form>
@endsection
