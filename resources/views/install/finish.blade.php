@extends('layouts.guest')
@section('subtitle', 'Xong rồi!')
@section('content')
    <div class="text-center mb-5">
        <div class="inline-flex items-center justify-center w-16 h-16 rounded-full bg-emerald-100 text-emerald-600 text-3xl mb-4">
            <i class="fa-solid fa-check"></i>
        </div>
        <p class="text-sm text-neutral-600 leading-relaxed">
            Đã cài đặt xong <strong>Rót</strong>. Mình đã thêm sẵn danh mục "Cà phê" với món "Cà phê đen" để bạn hình dung màn hình order — sửa hoặc xoá trong mục Thực đơn bất cứ lúc nào.
        </p>
    </div>
    <a href="{{ route('shift.create') }}">
        <x-button variant="success" icon="fa-play">Mở ca &amp; bắt đầu bán</x-button>
    </a>
@endsection
