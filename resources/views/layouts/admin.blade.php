<!DOCTYPE html>
<html lang="vi" class="h-full">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', 'Super Admin · Rót')</title>
    <meta name="robots" content="noindex, nofollow">
    <link rel="icon" type="image/svg+xml" href="/favicon.svg">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="h-full bg-neutral-50 text-neutral-900 antialiased">
    @php
        $adminNavItems = [
            ['route' => 'admin.dashboard', 'active' => 'admin.dashboard', 'icon' => 'fa-shop', 'label' => 'Danh sách quán'],
            ['route' => 'admin.users.index', 'active' => 'admin.users.*', 'icon' => 'fa-users', 'label' => 'Quản lý người dùng'],
            ['route' => 'admin.settings.edit', 'active' => 'admin.settings.*', 'icon' => 'fa-gear', 'label' => 'Cài đặt nền tảng'],
        ];
        $adminPageItems = [
            ['slug' => 'gioi-thieu', 'icon' => 'fa-circle-info', 'label' => 'Giới thiệu'],
            ['slug' => 'dieu-khoan-su-dung', 'icon' => 'fa-file-contract', 'label' => 'Điều khoản sử dụng'],
            ['slug' => 'chinh-sach-bao-mat', 'icon' => 'fa-shield-halved', 'label' => 'Chính sách bảo mật'],
        ];
    @endphp
    <div class="flex h-full">
        {{-- Sidebar: chỉ hiện từ tablet trở lên, y hệt pattern trong layouts/app.blade.php --}}
        <aside class="hidden md:flex md:flex-col w-60 shrink-0 bg-white border-r border-neutral-200 h-full">
            <a href="{{ route('admin.dashboard') }}" class="flex items-center gap-2.5 px-5 py-4 border-b border-neutral-100">
                <div class="w-8 h-8 rounded-lg bg-neutral-900 flex items-center justify-center text-white text-sm shrink-0">
                    <i class="fa-solid fa-mug-hot"></i>
                </div>
                <div class="min-w-0">
                    <div class="font-bold text-neutral-900 leading-tight">Rót</div>
                    <div class="text-xs text-neutral-400 leading-tight">Super Admin</div>
                </div>
            </a>

            <nav class="flex-1 px-3 py-4 space-y-1">
                @foreach ($adminNavItems as $item)
                    <a href="{{ route($item['route']) }}"
                       class="flex items-center gap-3 px-3 py-2 rounded-lg text-sm font-medium transition
                              {{ request()->routeIs($item['active']) ? 'bg-neutral-900 text-white' : 'text-neutral-600 hover:bg-neutral-100' }}">
                        <i class="fa-solid {{ $item['icon'] }} w-4 text-center"></i>{{ $item['label'] }}
                    </a>
                @endforeach

                <p class="px-3 pt-4 pb-1 text-xs font-bold text-neutral-400 uppercase tracking-wider">Nội dung trang</p>
                @foreach ($adminPageItems as $item)
                    <a href="{{ route('admin.pages.edit', $item['slug']) }}"
                       class="flex items-center gap-3 px-3 py-2 rounded-lg text-sm font-medium transition
                              {{ request()->routeIs('admin.pages.*') && request()->route('slug') === $item['slug'] ? 'bg-neutral-900 text-white' : 'text-neutral-600 hover:bg-neutral-100' }}">
                        <i class="fa-solid {{ $item['icon'] }} w-4 text-center"></i>{{ $item['label'] }}
                    </a>
                @endforeach
            </nav>

            <div class="px-3 py-3 border-t border-neutral-100">
                <a href="{{ route('pos.order') }}"
                   class="flex items-center gap-3 px-3 py-2 rounded-lg text-sm font-medium transition mb-1 text-neutral-600 hover:bg-neutral-100">
                    <i class="fa-solid fa-cash-register w-4 text-center"></i>Vào bán hàng
                </a>
                <a href="{{ route('profile.edit') }}"
                   class="flex items-center gap-3 px-3 py-2 rounded-lg text-sm font-medium transition mb-1
                          {{ request()->routeIs('profile.*') ? 'bg-neutral-100 text-neutral-900' : 'text-neutral-600 hover:bg-neutral-100' }}">
                    <i class="fa-solid fa-user w-4 text-center"></i>Hồ sơ cá nhân
                </a>
                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <button class="w-full flex items-center gap-3 px-3 py-2 rounded-lg text-sm font-medium text-red-600 hover:bg-red-50 transition text-left">
                        <i class="fa-solid fa-right-from-bracket w-4 text-center"></i>Đăng xuất
                    </button>
                </form>
            </div>
        </aside>

        <div class="flex-1 flex flex-col min-w-0">
            {{-- Header mobile: thay sidebar bằng menu dropdown, đúng pattern đang dùng ở layouts/app.blade.php --}}
            <header class="flex md:hidden items-center justify-between px-4 py-3 bg-white border-b border-neutral-200 shrink-0">
                <a href="{{ route('admin.dashboard') }}" class="flex items-center gap-2 min-w-0">
                    <div class="w-7 h-7 rounded-lg bg-neutral-900 flex items-center justify-center text-white text-xs shrink-0">
                        <i class="fa-solid fa-mug-hot"></i>
                    </div>
                    <span class="text-sm text-neutral-700 font-semibold truncate">Super Admin</span>
                </a>

                <div x-data="{ open: false }" class="relative shrink-0" @click.outside="open = false">
                    <button @click="open = !open" class="w-9 h-9 rounded-lg flex items-center justify-center text-neutral-600 hover:bg-neutral-100 transition">
                        <i class="fa-solid fa-bars text-lg"></i>
                    </button>

                    <div x-show="open" x-cloak x-transition
                         class="absolute right-0 mt-2 w-64 bg-white rounded-2xl border border-neutral-200 shadow-lg py-2 z-30">
                        @foreach ($adminNavItems as $item)
                            <a href="{{ route($item['route']) }}"
                               class="flex items-center gap-3 px-4 py-2 text-sm transition
                                      {{ request()->routeIs($item['active']) ? 'text-neutral-900 font-semibold bg-neutral-50' : 'text-neutral-600 hover:bg-neutral-50' }}">
                                <i class="fa-solid {{ $item['icon'] }} w-4 text-center"></i>{{ $item['label'] }}
                            </a>
                        @endforeach

                        <p class="px-4 pt-3 pb-1 text-xs font-bold text-neutral-400 uppercase tracking-wider">Nội dung trang</p>
                        @foreach ($adminPageItems as $item)
                            <a href="{{ route('admin.pages.edit', $item['slug']) }}"
                               class="flex items-center gap-3 px-4 py-2 text-sm transition
                                      {{ request()->routeIs('admin.pages.*') && request()->route('slug') === $item['slug'] ? 'text-neutral-900 font-semibold bg-neutral-50' : 'text-neutral-600 hover:bg-neutral-50' }}">
                                <i class="fa-solid {{ $item['icon'] }} w-4 text-center"></i>{{ $item['label'] }}
                            </a>
                        @endforeach

                        <div class="border-t border-neutral-100 mt-1 pt-1">
                            <a href="{{ route('pos.order') }}" class="flex items-center gap-3 px-4 py-2 text-sm text-neutral-600 hover:bg-neutral-50 transition">
                                <i class="fa-solid fa-cash-register w-4 text-center"></i>Vào bán hàng
                            </a>
                            <a href="{{ route('profile.edit') }}" class="flex items-center gap-3 px-4 py-2 text-sm text-neutral-600 hover:bg-neutral-50 transition">
                                <i class="fa-solid fa-user w-4 text-center"></i>Hồ sơ cá nhân
                            </a>
                            <form method="POST" action="{{ route('logout') }}">
                                @csrf
                                <button class="w-full flex items-center gap-3 px-4 py-2 text-sm text-red-600 hover:bg-red-50 transition text-left">
                                    <i class="fa-solid fa-right-from-bracket w-4 text-center"></i>Đăng xuất
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
            </header>

            {{-- Nội dung --}}
            <main class="flex-1 overflow-y-auto p-4 sm:p-6 md:p-10">
                <div class="max-w-5xl mx-auto">
                    @if(session('status'))
                        <div class="mb-5 rounded-xl bg-emerald-50 border border-emerald-200 px-4 py-3 text-sm text-emerald-700">
                            {{ session('status') }}
                        </div>
                    @endif
                    @yield('content')
                </div>
            </main>
        </div>
    </div>
</body>
</html>
