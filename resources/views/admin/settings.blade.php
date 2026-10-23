@extends('layouts.admin')
@section('title', 'Cài đặt nền tảng · Super Admin')
@section('content')
    <div class="flex items-center gap-3 mb-6">
        <div class="w-10 h-10 rounded-xl bg-neutral-900 text-white flex items-center justify-center shrink-0">
            <i class="fa-solid fa-gear"></i>
        </div>
        <h1 class="text-xl font-bold text-neutral-900">Cài đặt nền tảng</h1>
    </div>

    <form method="POST" action="{{ route('admin.settings.update') }}" class="max-w-lg bg-white rounded-2xl border border-neutral-200 p-4 sm:p-5 space-y-4">
        @csrf @method('PUT')

        <div>
            <p class="text-sm font-semibold text-neutral-900 mb-1">Đăng ký tài khoản mới</p>
            <label class="flex items-start gap-3 cursor-pointer">
                <input type="hidden" name="registration_open" value="0">
                <input type="checkbox" name="registration_open" value="1" @checked($registrationOpen)
                       class="mt-1 w-4 h-4 rounded border-neutral-300">
                <span class="text-sm text-neutral-700 leading-relaxed">
                    Cho phép người lạ tự đăng ký
                    <span class="block text-xs text-neutral-500 mt-0.5">
                        Tắt nếu bạn chỉ tự dùng hoặc dùng trong nhóm nhỏ. Khi tắt: trang đăng ký bị vô hiệu hoá, đăng nhập Google chỉ dành cho tài khoản đã có trong hệ thống. Tài khoản hiện có vẫn đăng nhập bình thường.
                    </span>
                </span>
            </label>
        </div>

        <x-button icon="fa-floppy-disk">Lưu cài đặt</x-button>
    </form>
@endsection
