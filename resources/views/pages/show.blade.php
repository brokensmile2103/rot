@extends('layouts.guest')
@section('title', $page->title.' · Rót')
@section('meta_description', $page->meta_description ?: \Illuminate\Support\Str::limit(strip_tags($page->content), 155))
@section('subtitle', $page->title)
@section('content')
    <div class="legal-content">
        {!! $page->content !!}
    </div>

    <a href="{{ route('login') }}" class="inline-flex items-center gap-1.5 text-sm text-neutral-500 hover:text-neutral-700 mt-6">
        <i class="fa-solid fa-arrow-left"></i>Quay lại đăng nhập
    </a>
@endsection
