@extends('layouts.app')
@section('title', 'Hồ sơ · Rót')
@section('page-title', 'Hồ sơ cá nhân')
@section('content')
<div class="p-4 pb-24 md:p-8 max-w-lg mx-auto">
    <div class="flex items-center gap-3 mb-5 md:mb-6">
        <div class="w-10 h-10 rounded-xl bg-amber-100 text-amber-700 flex items-center justify-center shrink-0">
            <i class="fa-solid fa-user"></i>
        </div>
        <h2 class="text-lg font-bold text-neutral-900">Hồ sơ cá nhân</h2>
    </div>

    @include('profile._form')
</div>
@endsection
