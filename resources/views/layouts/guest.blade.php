<!DOCTYPE html>
<html lang="vi" class="h-full">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1">

    @php
        $seoTitle = trim($__env->yieldContent('title')) ?: 'Rót · Phần mềm quản lý xe/quầy cà phê';
        $seoDescription = trim($__env->yieldContent('meta_description')) ?: 'Rót — phần mềm quản lý bán hàng cho xe/quầy cà phê nhỏ lẻ.';
        $seoImage = trim($__env->yieldContent('og_image')) ?: asset('og-image.png');
        // Self-hosted: mỗi bản cài là 1 công cụ RIÊNG TƯ của 1 quán, không
        // phải trang web công khai — mặc định luôn noindex, không có case
        // nào nên để Google index domain nội bộ của quán.
        $seoUrl = url()->current();
    @endphp

    <title>{{ $seoTitle }}</title>
    <meta name="description" content="{{ $seoDescription }}">
    <meta name="robots" content="noindex, nofollow">
    <link rel="canonical" href="{{ $seoUrl }}">

    {{-- Open Graph — chỉ để link hiển thị đẹp khi chủ quán share cho nhân
         viên qua Zalo/Messenger, không nhằm mục đích SEO công khai. --}}
    <meta property="og:type" content="website">
    <meta property="og:site_name" content="Rót">
    <meta property="og:locale" content="vi_VN">
    <meta property="og:title" content="{{ $seoTitle }}">
    <meta property="og:description" content="{{ $seoDescription }}">
    <meta property="og:url" content="{{ $seoUrl }}">
    <meta property="og:image" content="{{ $seoImage }}">
    <meta property="og:image:width" content="1200">
    <meta property="og:image:height" content="630">

    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:title" content="{{ $seoTitle }}">
    <meta name="twitter:description" content="{{ $seoDescription }}">
    <meta name="twitter:image" content="{{ $seoImage }}">

    <meta name="theme-color" content="#d97706">

    <link rel="icon" type="image/svg+xml" href="/favicon.svg">
    <link rel="alternate icon" href="/favicon.ico">
    <link rel="apple-touch-icon" sizes="180x180" href="/apple-touch-icon.png">

    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="h-full bg-gradient-to-br from-amber-50 via-white to-orange-50 text-neutral-900 antialiased">
    <div class="min-h-full flex flex-col items-center justify-center px-4 py-10">
        <div class="w-full max-w-md">
            <div class="text-center mb-6">
                <a href="/" class="inline-flex items-center justify-center w-14 h-14 rounded-2xl bg-amber-600 text-white text-2xl shadow-lg shadow-amber-600/30 mb-3 hover:bg-amber-700 transition">
                    <i class="fa-solid fa-mug-hot"></i>
                </a>
                <div class="text-3xl font-extrabold tracking-tight text-neutral-900">Rót</div>
                @hasSection('subtitle')
                    <p class="text-neutral-500 text-sm mt-1.5">@yield('subtitle')</p>
                @endif
            </div>

            @php
                $installSteps = [
                    'install.welcome' => 0, 'install.requirements' => 1, 'install.database' => 2,
                    'install.account' => 3, 'install.location' => 4,
                ];
                $currentStep = null;
                foreach ($installSteps as $routeName => $num) {
                    if (request()->routeIs($routeName)) { $currentStep = $num; break; }
                }
            @endphp
            @if(! is_null($currentStep) && $currentStep > 0)
                <div class="flex items-center justify-center gap-2 mb-6">
                    @for ($i = 1; $i <= 4; $i++)
                        <div class="h-1.5 rounded-full transition-all {{ $i <= $currentStep ? 'w-8 bg-amber-600' : 'w-4 bg-neutral-300' }}"></div>
                    @endfor
                </div>
            @endif

            <div class="bg-white rounded-3xl shadow-xl shadow-neutral-900/5 border border-neutral-200/60 p-6 sm:p-8">
                @yield('content')
            </div>

            <p class="text-center text-xs text-neutral-400 mt-6">Rót · Phần mềm quản lý xe cà phê</p>
        </div>
    </div>
</body>
</html>
