@extends('layouts.guest')
@section('title', 'Cài đặt Rót hoàn tất · Rót')
@section('robots', 'noindex, nofollow')
@section('subtitle', 'Xong rồi!')
@section('content')
    <div class="text-center mb-5">
        <div class="inline-flex items-center justify-center w-16 h-16 rounded-full bg-emerald-100 text-emerald-600 text-3xl mb-4">
            <i class="fa-solid fa-check"></i>
        </div>
        <p class="text-sm text-neutral-600 leading-relaxed">
            Đã khởi tạo xong <strong>Rót</strong>. Tài khoản của bạn vừa là Super Admin vừa là chủ quán đầu tiên — vào bán hàng được ngay. Người khác có thể tự đăng ký qua <strong>/dang-ky</strong>; nếu chỉ tự dùng, hãy đóng đăng ký trong <strong>Cài đặt nền tảng</strong>.
        </p>
    </div>
    <a href="{{ route('pos.order') }}">
        <x-button variant="success" icon="fa-cash-register">Vào bán hàng</x-button>
    </a>
    <a href="{{ route('admin.settings.edit') }}" class="block text-center text-sm text-neutral-500 hover:underline mt-3">Cài đặt nền tảng (đóng/mở đăng ký)</a>
@endsection
