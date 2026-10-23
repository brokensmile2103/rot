@extends('layouts.guest')
@section('title', 'Quên mật khẩu · Rót')
@section('meta_description', 'Lấy lại mật khẩu Rót qua email khôi phục đã đăng ký ở Hồ sơ cá nhân.')
@section('robots', 'noindex, follow')
@section('subtitle', 'Lấy lại mật khẩu qua email đã đăng ký')
@section('content')
    @if (session('status'))
        <div class="mb-5 rounded-xl bg-emerald-50 border border-emerald-200 px-4 py-3 text-sm text-emerald-700">
            <i class="fa-solid fa-circle-check mr-1"></i>{{ session('status') }}
        </div>
    @else
        <p class="text-sm text-neutral-500 mb-5">
            Nhập số điện thoại đăng nhập của bạn. Nếu tài khoản đã có <strong>email khôi phục</strong>
            (thiết lập trong Hồ sơ cá nhân), chúng tôi sẽ gửi link đặt lại mật khẩu tới email đó.
        </p>

        <form method="POST" action="{{ route('password.email') }}" class="space-y-4">
            @csrf
            <x-input name="phone" label="Số điện thoại" icon="fa-phone" value="{{ old('phone') }}" required autofocus inputmode="tel" autocomplete="tel" />

            <x-form-guard name="forgot-password" />

            <x-button icon="fa-paper-plane" class="mt-2">Gửi link đặt lại mật khẩu</x-button>
        </form>
    @endif

    <p class="text-center text-sm text-neutral-500 mt-5">
        <a href="{{ route('login') }}" class="inline-flex items-center gap-1.5 text-amber-600 font-semibold hover:underline">
            <i class="fa-solid fa-arrow-left"></i>Quay lại đăng nhập
        </a>
    </p>
@endsection
