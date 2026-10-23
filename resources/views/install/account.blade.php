@extends('layouts.guest')
@section('title', 'Cài đặt Rót — Tạo tài khoản Super Admin · Rót')
@section('robots', 'noindex, nofollow')
@section('subtitle', 'Bước 3/3 · Tạo tài khoản Super Admin')
@section('content')
    <p class="text-xs text-neutral-400 mb-4 -mt-2">Tài khoản này vừa là Super Admin (quản lý toàn bộ nền tảng) vừa là chủ của quán đầu tiên, nên dùng được ngay mọi tính năng bán hàng. Người khác có thể tự đăng ký sau khi cài xong — hoặc bạn đóng đăng ký trong Cài đặt nền tảng nếu chỉ tự dùng.</p>
    <form method="POST" action="{{ route('install.account.save') }}" class="space-y-4">
        @csrf
        <x-input name="name" label="Tên của bạn" icon="fa-user" value="{{ old('name') }}" required autofocus autocomplete="name" />
        <x-input name="shop_name" label="Tên quán của bạn (có thể đổi sau)" icon="fa-shop" value="{{ old('shop_name') }}" placeholder="VD: Xe cà phê Nhà Rót" autocomplete="organization" />
        <x-input name="phone" label="Số điện thoại (dùng để đăng nhập)" icon="fa-phone" value="{{ old('phone') }}" required inputmode="tel" autocomplete="tel" />
        <x-input name="password" type="password" label="Mật khẩu (tối thiểu 8 ký tự)" icon="fa-lock" required autocomplete="new-password" />
        <x-input name="password_confirmation" type="password" label="Nhập lại mật khẩu" icon="fa-lock" required autocomplete="new-password" />

        <x-button icon="fa-user-plus" class="mt-2">Tạo tài khoản</x-button>
    </form>
@endsection
