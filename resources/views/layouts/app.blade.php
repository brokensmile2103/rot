<!DOCTYPE html>
@php
    $currentLocationForTheme = session('current_location_id') ? \App\Models\Location::find(session('current_location_id')) : null;
@endphp
<html lang="vi" class="h-full"
      data-font-scale="{{ $currentLocationForTheme->font_scale ?? 'md' }}"
      data-accent="{{ $currentLocationForTheme->accent_color ?? 'amber' }}">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1, viewport-fit=cover">
    <title>@yield('title', 'Rót')</title>
    <link rel="icon" type="image/svg+xml" href="/favicon.svg">
    <link rel="apple-touch-icon" href="/apple-touch-icon.png">
    {{-- PWA — cho phép "Cài đặt ứng dụng" lên màn hình chính, mở nhanh như app
         thật thay vì phải mở trình duyệt rồi gõ lại địa chỉ mỗi lần bán hàng. --}}
    <link rel="manifest" href="/manifest.json">
    @php
        // Khớp ĐÚNG mã hex --accent của từng màu trong resources/css/app.css —
        // thanh trạng thái trình duyệt/hệ điều hành khi mở app từ màn hình
        // chính nên đổi màu theo đúng màu quán đã chọn, không phải màu mặc định.
        $themeColors = [
            'amber' => '#d97706', 'orange' => '#ea580c', 'blue' => '#2563eb',
            'emerald' => '#059669', 'rose' => '#e11d48', 'violet' => '#7c3aed',
        ];
    @endphp
    <meta name="theme-color" content="{{ $themeColors[$currentLocationForTheme->accent_color ?? 'amber'] ?? '#d97706' }}">
    <meta name="mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="default">
    <meta name="apple-mobile-web-app-title" content="Rót">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="robots" content="noindex, nofollow">
    <script>
        // Giá trị KHỞI TẠO cho Alpine.store('nav') (xem resources/js/app.js) —
        // tính sẵn bởi view composer ở AppServiceProvider. Đặt TRƯỚC script
        // Vite để store có số đúng ngay từ đầu, trước khi Alpine.start() chạy.
        window.__navBadgeCounts = {
            order: {{ (int) ($orderBadgeCount ?? 0) }},
            lowStock: {{ (int) ($lowStockBadgeCount ?? 0) }},
            pendingRequests: {{ (int) ($pendingRequestCount ?? 0) }},
        };
        @if(! empty($qrLocationId))
        // Cấu hình polling yêu cầu gọi món qua QR (xem resources/js/qr-requests.js).
        window.__qrRequests = {
            locationId: {{ (int) $qrLocationId }},
            enabled: {{ ! empty($qrOrderingEnabled) ? 'true' : 'false' }},
            url: @js(route('pos.orders.requests.pending', [], false)),
        };
        @endif
    </script>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
{{--
    Nền SÁNG, tương phản cao — chủ động dùng cho toàn bộ khu vực bán hàng vì
    mô hình xe cà phê bán chính vào ban ngày, ngoài trời nắng. Dark mode làm
    giảm độ tương phản dưới ánh nắng mạnh nên KHÔNG dùng ở đây (khác với
    layouts.guest vốn chỉ dùng cho cài đặt/đăng nhập, ít khi thao tác ngoài nắng).
--}}
<body class="h-full bg-neutral-50 text-neutral-900 antialiased overscroll-none">
@php
    $currentLocationName = $currentLocationForTheme?->name;
    $navItems = [
        ['route' => 'pos.order', 'active' => 'pos.order', 'icon' => 'fa-mug-saucer', 'label' => 'Order'],
        ['route' => 'pos.orders.index', 'active' => 'pos.orders.*', 'icon' => 'fa-receipt', 'label' => 'Đơn hàng'],
        ['route' => 'cashbook.index', 'active' => 'cashbook.*', 'icon' => 'fa-wallet', 'label' => 'Sổ quỹ'],
        ['route' => 'shift.close.show', 'active' => 'shift.close.*', 'icon' => 'fa-lock', 'label' => 'Chốt ca'],
    ];
@endphp
<div class="h-full md:flex" style="height: 100dvh;">

    {{-- Sidebar: chỉ hiện từ tablet trở lên, kéo mép phải để tuỳ chỉnh độ rộng --}}
    <aside x-data="resizablePanel('rot-sidebar-width', 256, 220, 380, 'right')" :style="`width: ${width}px`"
           class="hidden md:flex md:flex-col bg-white border-r border-neutral-200 shrink-0 relative">
        <div @mousedown="startResize($event)" @touchstart="startResize($event)"
             class="hidden md:block absolute top-0 right-0 w-1.5 h-full cursor-col-resize hover:bg-[var(--accent)] transition z-10"
             :class="resizing && 'bg-[var(--accent)]'"></div>
        <a href="{{ route('pos.order') }}" class="h-16 px-5 flex items-center gap-2.5 border-b border-neutral-200 hover:bg-neutral-50 transition shrink-0">
            <div class="w-9 h-9 rounded-xl bg-[var(--accent)] flex items-center justify-center text-white shrink-0">
                <i class="fa-solid fa-mug-hot"></i>
            </div>
            <div class="min-w-0">
                <div class="text-base font-bold leading-tight text-neutral-900">Rót</div>
                <div class="text-xs text-neutral-500 truncate">{{ $currentLocationName }}</div>
            </div>
        </a>
        <nav class="flex-1 min-h-0 overflow-y-auto scrollbar-hide px-3 py-4 space-y-1">
            @foreach($navItems as $item)
                @php($isActive = request()->routeIs($item['active']))
                <a href="{{ route($item['route']) }}"
                   class="flex items-center gap-3 px-3 py-2.5 rounded-xl text-sm font-semibold transition
                          {{ $isActive ? 'bg-[var(--accent)] text-white' : 'text-neutral-600 hover:bg-neutral-100' }}">
                    <i class="fa-solid {{ $item['icon'] }} w-5 text-center"></i>{{ $item['label'] }}
                    @if($item['route'] === 'pos.orders.index')
                        {{-- 1 badge duy nhất: ưu tiên badge XANH (yêu cầu gọi món QR đang chờ nhận);
                             xử lý hết thì tự trở về badge tổng số đơn trong ca. --}}
                        <x-nav-badge :count="$pendingRequestCount" store="pendingRequestCount" color="info" title="Yêu cầu gọi món từ khách (QR) đang chờ" class="ml-auto" :inverted="$isActive" />
                        <x-nav-badge :count="$orderBadgeCount" store="orderBadgeCount" hide-when="pendingRequestCount" class="ml-auto" :inverted="$isActive" />
                    @endif
                </a>
            @endforeach
            <a href="{{ route('shift.history') }}"
               class="flex items-center gap-3 px-3 py-2.5 rounded-xl text-sm font-semibold transition
                      {{ request()->routeIs('shift.history*') ? 'bg-[var(--accent)] text-white' : 'text-neutral-600 hover:bg-neutral-100' }}">
                <i class="fa-solid fa-clock-rotate-left w-5 text-center"></i>Lịch sử ca
            </a>

            @if(auth()->user()?->isOwner())
                <div class="pt-3 mt-3 border-t border-neutral-200 space-y-1">
                    <p class="px-3 text-xs font-bold text-neutral-400 uppercase tracking-wider mb-1">Quản lý</p>
                    <a href="{{ route('owner.menu.index') }}"
                       class="flex items-center gap-3 px-3 py-2.5 rounded-xl text-sm font-semibold transition
                              {{ request()->routeIs('owner.menu.*') ? 'bg-[var(--accent)] text-white' : 'text-neutral-600 hover:bg-neutral-100' }}">
                        <i class="fa-solid fa-book-open w-5 text-center"></i>Thực đơn
                    </a>
                    <a href="{{ route('owner.modifier-groups.index') }}"
                       class="flex items-center gap-3 px-3 py-2.5 rounded-xl text-sm font-semibold transition
                              {{ request()->routeIs('owner.modifier-groups.*') ? 'bg-[var(--accent)] text-white' : 'text-neutral-600 hover:bg-neutral-100' }}">
                        <i class="fa-solid fa-sliders w-5 text-center"></i>Tuỳ chọn món
                    </a>
                    <a href="{{ route('owner.locations.index') }}"
                       class="flex items-center gap-3 px-3 py-2.5 rounded-xl text-sm font-semibold transition
                              {{ request()->routeIs('owner.locations.*') ? 'bg-[var(--accent)] text-white' : 'text-neutral-600 hover:bg-neutral-100' }}">
                        <i class="fa-solid fa-shop w-5 text-center"></i>Các xe cà phê
                    </a>
                    <a href="{{ route('owner.staff.index') }}"
                       class="flex items-center gap-3 px-3 py-2.5 rounded-xl text-sm font-semibold transition
                              {{ request()->routeIs('owner.staff.*') ? 'bg-[var(--accent)] text-white' : 'text-neutral-600 hover:bg-neutral-100' }}">
                        <i class="fa-solid fa-users w-5 text-center"></i>Nhân viên
                    </a>
                    <a href="{{ route('owner.customers.index') }}"
                       class="flex items-center gap-3 px-3 py-2.5 rounded-xl text-sm font-semibold transition
                              {{ request()->routeIs('owner.customers.*') ? 'bg-[var(--accent)] text-white' : 'text-neutral-600 hover:bg-neutral-100' }}">
                        <i class="fa-solid fa-heart w-5 text-center"></i>Khách hàng
                    </a>
                    @php($isInventoryActive = request()->routeIs('owner.inventory.*'))
                    <a href="{{ route('owner.inventory.index') }}"
                       class="flex items-center gap-3 px-3 py-2.5 rounded-xl text-sm font-semibold transition
                              {{ $isInventoryActive ? 'bg-[var(--accent)] text-white' : 'text-neutral-600 hover:bg-neutral-100' }}">
                        <i class="fa-solid fa-boxes-stacked w-5 text-center"></i>Kho nguyên liệu
                        <x-nav-badge :count="$lowStockBadgeCount" color="danger" class="ml-auto" :inverted="$isInventoryActive" />
                    </a>
                    <a href="{{ route('owner.reports.index') }}"
                       class="flex items-center gap-3 px-3 py-2.5 rounded-xl text-sm font-semibold transition
                              {{ request()->routeIs('owner.reports.*') ? 'bg-[var(--accent)] text-white' : 'text-neutral-600 hover:bg-neutral-100' }}">
                        <i class="fa-solid fa-chart-line w-5 text-center"></i>Báo cáo
                    </a>
                </div>
            @endif
        </nav>
        <div class="px-3 py-4 border-t border-neutral-200">
            <a href="{{ route('profile.edit') }}" class="flex items-center gap-2.5 px-3 py-2 mb-1 rounded-xl hover:bg-neutral-100 transition {{ request()->routeIs('profile.*') ? 'bg-neutral-100' : '' }}">
                @if(auth()->user()?->avatar_url)
                    <img src="{{ auth()->user()->avatar_url }}" class="w-6 h-6 rounded-full object-cover shrink-0" alt="">
                @else
                    <i class="fa-solid fa-circle-user text-lg text-neutral-400"></i>
                @endif
                <span class="text-sm text-neutral-600 truncate font-medium">{{ auth()->user()?->name }}</span>
            </a>
            @if(auth()->user()?->isOwner())
                <a href="{{ route('owner.settings.index') }}"
                   class="flex items-center gap-3 px-3 py-2.5 rounded-xl text-sm font-semibold transition mb-1
                          {{ request()->routeIs('owner.settings.*') ? 'bg-neutral-100 text-neutral-900' : 'text-neutral-600 hover:bg-neutral-100' }}">
                    <i class="fa-solid fa-gear w-5 text-center"></i>Cài đặt
                </a>
            @endif
            <form method="POST" action="{{ route('logout') }}">
                @csrf
                <button class="w-full flex items-center gap-3 px-3 py-2.5 rounded-xl text-neutral-600 hover:bg-neutral-100 text-sm font-semibold transition">
                    <i class="fa-solid fa-right-from-bracket w-5 text-center"></i>Đăng xuất
                </button>
            </form>
        </div>
    </aside>

    <div class="flex-1 flex flex-col min-w-0 min-h-0">
        {{-- Header mobile --}}
        <header class="flex md:hidden items-center justify-between px-4 py-3 bg-white border-b border-neutral-200 shrink-0">
            <a href="{{ route('pos.order') }}" class="flex items-center gap-2 min-w-0">
                <div class="w-7 h-7 rounded-lg bg-[var(--accent)] flex items-center justify-center text-white text-xs shrink-0">
                    <i class="fa-solid fa-mug-hot"></i>
                </div>
                <span class="text-sm text-neutral-700 font-semibold truncate">{{ $currentLocationName }}</span>
            </a>

            <div x-data="{ open: false }" class="relative shrink-0" @click.outside="open = false">
                <button @click="open = !open" class="block">
                    @if(auth()->user()?->avatar_url)
                        <img src="{{ auth()->user()->avatar_url }}" class="w-7 h-7 rounded-full object-cover border border-neutral-200" alt="">
                    @else
                        <i class="fa-solid fa-circle-user text-2xl text-neutral-400"></i>
                    @endif
                </button>

                <div x-show="open" x-cloak x-transition
                     class="absolute right-0 mt-2 w-60 bg-white rounded-2xl border border-neutral-200 shadow-lg py-2 z-30">
                    <div class="px-4 py-2 border-b border-neutral-100 mb-1">
                        <p class="text-sm font-semibold text-neutral-900 truncate">{{ auth()->user()?->name }}</p>
                        <span class="text-xs px-2 py-0.5 rounded-full {{ auth()->user()?->isOwner() ? 'bg-amber-100 text-amber-700' : 'bg-neutral-100 text-neutral-600' }} font-medium inline-block mt-1">
                            {{ auth()->user()?->isOwner() ? 'Chủ quán' : 'Nhân viên' }}
                        </span>
                    </div>

                    <a href="{{ route('profile.edit') }}" class="flex items-center gap-3 px-4 py-2 text-sm text-neutral-600 hover:bg-neutral-50 transition">
                        <i class="fa-solid fa-user w-4 text-center"></i>Hồ sơ cá nhân
                    </a>
                    <a href="{{ route('shift.history') }}" class="flex items-center gap-3 px-4 py-2 text-sm text-neutral-600 hover:bg-neutral-50 transition">
                        <i class="fa-solid fa-clock-rotate-left w-4 text-center"></i>Lịch sử ca
                    </a>

                    @if(auth()->user()?->isOwner())
                        <p class="px-4 pt-3 pb-1 text-xs font-bold text-neutral-400 uppercase tracking-wider">Quản lý</p>
                        <a href="{{ route('owner.menu.index') }}" class="flex items-center gap-3 px-4 py-2 text-sm text-neutral-600 hover:bg-neutral-50 transition">
                            <i class="fa-solid fa-book-open w-4 text-center"></i>Thực đơn
                        </a>
                        <a href="{{ route('owner.modifier-groups.index') }}" class="flex items-center gap-3 px-4 py-2 text-sm text-neutral-600 hover:bg-neutral-50 transition">
                            <i class="fa-solid fa-sliders w-4 text-center"></i>Tuỳ chọn món
                        </a>
                        <a href="{{ route('owner.locations.index') }}" class="flex items-center gap-3 px-4 py-2 text-sm text-neutral-600 hover:bg-neutral-50 transition">
                            <i class="fa-solid fa-shop w-4 text-center"></i>Các xe cà phê
                        </a>
                        <a href="{{ route('owner.staff.index') }}" class="flex items-center gap-3 px-4 py-2 text-sm text-neutral-600 hover:bg-neutral-50 transition">
                            <i class="fa-solid fa-users w-4 text-center"></i>Nhân viên
                        </a>
                        <a href="{{ route('owner.customers.index') }}" class="flex items-center gap-3 px-4 py-2 text-sm text-neutral-600 hover:bg-neutral-50 transition">
                            <i class="fa-solid fa-heart w-4 text-center"></i>Khách hàng
                        </a>
                        <a href="{{ route('owner.inventory.index') }}" class="flex items-center gap-3 px-4 py-2 text-sm text-neutral-600 hover:bg-neutral-50 transition">
                            <i class="fa-solid fa-boxes-stacked w-4 text-center"></i>Kho nguyên liệu
                            <x-nav-badge :count="$lowStockBadgeCount" color="danger" class="ml-auto" />
                        </a>
                        <a href="{{ route('owner.reports.index') }}" class="flex items-center gap-3 px-4 py-2 text-sm text-neutral-600 hover:bg-neutral-50 transition">
                            <i class="fa-solid fa-chart-line w-4 text-center"></i>Báo cáo
                        </a>
                        <a href="{{ route('owner.settings.index') }}" class="flex items-center gap-3 px-4 py-2 text-sm text-neutral-600 hover:bg-neutral-50 transition">
                            <i class="fa-solid fa-gear w-4 text-center"></i>Cài đặt
                        </a>
                    @endif

                    <div class="border-t border-neutral-100 mt-1 pt-1">
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

        {{-- Header desktop/tablet --}}
        <header class="hidden md:flex h-16 items-center justify-between px-8 bg-white border-b border-neutral-200 shrink-0">
            <div class="text-sm font-semibold text-neutral-700">@yield('page-title', 'Rót')</div>
            <div class="flex items-center gap-2 text-sm text-neutral-500 font-medium">
                <i class="fa-solid fa-location-dot"></i>{{ $currentLocationName }}
            </div>
        </header>

        @if (session('status'))
            <div class="bg-emerald-50 text-emerald-700 text-sm font-medium text-center py-2.5 flex items-center justify-center gap-2 border-b border-emerald-100">
                <i class="fa-solid fa-circle-check"></i>{{ session('status') }}
            </div>
        @endif

        <main class="flex-1 min-h-0 overflow-y-auto">
            @yield('content')
        </main>
    </div>

    {{-- Bottom nav: chỉ hiện dưới tablet, vùng ngón cái --}}
    <nav class="md:hidden fixed bottom-0 inset-x-0 bg-white border-t border-neutral-200 grid grid-cols-4 shrink-0 z-20 shadow-[0_-2px_12px_rgba(0,0,0,0.06)]"
         style="padding-bottom: env(safe-area-inset-bottom);">
        @foreach($navItems as $item)
            <a href="{{ route($item['route']) }}"
               class="flex flex-col items-center justify-center py-3 gap-1 {{ request()->routeIs($item['active']) ? 'text-[var(--accent-text)]' : 'text-neutral-500' }}">
                <span class="relative">
                    <i class="fa-solid {{ $item['icon'] }} text-lg"></i>
                    @if($item['route'] === 'pos.orders.index')
                        <x-nav-badge :count="$pendingRequestCount" store="pendingRequestCount" color="info" title="Yêu cầu gọi món từ khách (QR) đang chờ" class="absolute -top-1.5 -right-3.5" />
                        <x-nav-badge :count="$orderBadgeCount" store="orderBadgeCount" hide-when="pendingRequestCount" class="absolute -top-1.5 -right-3.5" />
                    @endif
                </span>
                <span class="text-xs font-semibold">{{ $item['label'] }}</span>
            </a>
        @endforeach
    </nav>
</div>
<script>
    // Đăng ký service worker (xem public/sw.js) — CHỈ để trình duyệt cho phép
    // "Cài đặt ứng dụng" lên màn hình chính, không cache dữ liệu gì. Chỉ đăng
    // ký ở khu vực dùng cho nhân viên/chủ quán (layout này), KHÔNG đăng ký ở
    // trang menu công khai cho khách (public/menu.blade.php).
    if ('serviceWorker' in navigator) {
        window.addEventListener('load', () => navigator.serviceWorker.register('/sw.js'));
    }
</script>
</body>
</html>
