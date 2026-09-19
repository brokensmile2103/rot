@extends('layouts.guest')
@section('subtitle', 'Bước 4/4 · Thông tin xe cà phê')
@section('content')
    <form method="POST" action="{{ route('install.location.save') }}" class="space-y-4">
        @csrf
        <x-input name="name" label="Tên xe/quầy cà phê" icon="fa-shop" placeholder="VD: Cà phê Cô Ba" value="{{ old('name') }}" required autocomplete="off" />
        <x-input name="address" label="Địa chỉ (không bắt buộc)" icon="fa-location-dot" value="{{ old('address') }}" autocomplete="street-address" />

        <x-button icon="fa-check" class="mt-2">Hoàn tất cài đặt</x-button>
    </form>
@endsection
