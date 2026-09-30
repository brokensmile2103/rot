@extends('layouts.guest')
@section('subtitle', 'Bước 3/4 · Tạo tài khoản chủ quán')
@section('content')
    <form method="POST" action="{{ route('install.account.save') }}" class="space-y-4">
        @csrf
        <x-input name="name" label="Tên của bạn" icon="fa-user" value="{{ old('name') }}" required autofocus autocomplete="name" />
        <x-input name="phone" label="Số điện thoại (dùng để đăng nhập)" icon="fa-phone" value="{{ old('phone') }}" required inputmode="tel" autocomplete="tel" />
        <x-input name="password" type="password" label="Mật khẩu (tối thiểu 8 ký tự)" icon="fa-lock" required autocomplete="new-password" />
        <x-input name="password_confirmation" type="password" label="Nhập lại mật khẩu" icon="fa-lock" required autocomplete="new-password" />

        <x-button icon="fa-user-plus" class="mt-2">Tạo tài khoản</x-button>
    </form>
@endsection
