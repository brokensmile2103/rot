@extends('layouts.guest')
@section('subtitle', 'Cài đặt lần đầu')
@section('content')
    <p class="text-sm text-neutral-600 mb-5 leading-relaxed">
        Chào mừng bạn đến với <strong>Rót</strong> — phần mềm quản lý xe/quầy cà phê.
        Quá trình cài đặt chỉ mất khoảng 2 phút, không cần biết dòng lệnh nào cả.
    </p>
    <a href="{{ route('install.requirements') }}">
        <x-button icon="fa-arrow-right">Bắt đầu cài đặt</x-button>
    </a>
@endsection
