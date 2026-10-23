@extends('layouts.admin')
@section('title', 'Quản lý người dùng · Super Admin')
@section('content')
    <div x-data="{ resettingFor: null }">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 mb-6">
            <h1 class="text-xl font-bold text-neutral-900">Toàn bộ người dùng trên nền tảng</h1>
            <form method="GET" class="flex items-center gap-2">
                <input type="text" name="q" id="user-search" value="{{ request('q') }}" placeholder="Tìm theo tên, SĐT..." autocomplete="off"
                       class="rounded-xl border border-neutral-300 bg-white text-neutral-900 placeholder-neutral-400 py-2 px-4 text-sm w-full sm:w-64 focus:border-neutral-500 focus:outline-none">
            </form>
        </div>

        <div class="bg-white rounded-2xl border border-neutral-200 overflow-hidden">
            @forelse($users as $u)
                <div class="px-4 sm:px-5 py-4 border-b border-neutral-100 last:border-0">
                    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
                        <div class="min-w-0">
                            <div class="flex items-center gap-2 flex-wrap">
                                <span class="font-semibold text-neutral-900">{{ $u->name }}</span>
                                @if($u->isPlatformAdmin())
                                    <span class="text-xs px-2 py-0.5 rounded-full bg-neutral-900 text-white font-medium">Super Admin</span>
                                @elseif($u->isOwner())
                                    <span class="text-xs px-2 py-0.5 rounded-full bg-amber-100 text-amber-700 font-medium">Chủ quán</span>
                                @else
                                    <span class="text-xs px-2 py-0.5 rounded-full bg-neutral-100 text-neutral-600 font-medium">Nhân viên</span>
                                @endif
                                <span class="text-xs px-2 py-0.5 rounded-full font-medium {{ $u->is_active ? 'bg-emerald-50 text-emerald-700' : 'bg-red-50 text-red-600' }}">
                                    {{ $u->is_active ? 'Hoạt động' : 'Đã khoá' }}
                                </span>
                            </div>
                            <p class="text-sm text-neutral-500 mt-0.5">
                                {{ $u->phone }}
                                @if($u->ownedLocations->isNotEmpty())
                                    · Sở hữu: {{ $u->ownedLocations->pluck('name')->implode(', ') }}
                                @elseif($u->locations->isNotEmpty())
                                    · Làm việc tại: {{ $u->locations->pluck('name')->implode(', ') }}
                                @endif
                            </p>
                        </div>
                        <div class="flex items-center gap-2 shrink-0">
                            <button @click="resettingFor = resettingFor === {{ $u->id }} ? null : {{ $u->id }}"
                                    class="text-xs px-3 py-1.5 rounded-lg bg-neutral-100 text-neutral-700 hover:bg-neutral-200 font-medium transition">
                                <i class="fa-solid fa-key mr-1"></i>Đổi mật khẩu
                            </button>
                            @unless($u->id === auth()->id())
                                <form method="POST" action="{{ route('admin.users.toggle', $u->id) }}"
                                      onsubmit="return confirm('{{ $u->is_active ? 'Khoá' : 'Mở lại' }} tài khoản {{ $u->name }}?')">
                                    @csrf @method('PUT')
                                    <button class="text-xs px-3 py-1.5 rounded-lg font-medium transition {{ $u->is_active ? 'bg-red-50 text-red-600 hover:bg-red-100' : 'bg-emerald-50 text-emerald-700 hover:bg-emerald-100' }}">
                                        {{ $u->is_active ? 'Khoá' : 'Mở lại' }}
                                    </button>
                                </form>
                            @endunless
                        </div>
                    </div>

                    <form x-show="resettingFor === {{ $u->id }}" x-cloak method="POST" action="{{ route('admin.users.password', $u->id) }}"
                          class="flex flex-wrap items-center gap-2 mt-3 pt-3 border-t border-neutral-100">
                        @csrf @method('PUT')
                        <input type="password" name="password" id="reset-pw-{{ $u->id }}" placeholder="Mật khẩu mới (tối thiểu 8 ký tự)" required minlength="8" autocomplete="new-password"
                               class="flex-1 min-w-[10rem] rounded-lg border border-neutral-300 py-2 px-3 text-sm focus:border-neutral-500 focus:outline-none">
                        <button class="text-xs px-4 py-2 rounded-lg bg-neutral-900 hover:bg-neutral-800 text-white font-medium transition">Lưu</button>
                    </form>
                </div>
            @empty
                <p class="text-center py-10 text-neutral-400 text-sm">Không tìm thấy người dùng nào.</p>
            @endforelse
        </div>

        <div class="mt-4">{{ $users->links() }}</div>
    </div>
@endsection
