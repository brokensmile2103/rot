<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">

    @php
        $seoTitle = 'Rót — Bán hàng, chốt ca và sổ doanh thu cho xe cà phê, quán ăn nhỏ, tiệm làm đẹp';
        $seoDescription = 'Rót: lên đơn một chạm, khách tự gọi món qua QR, gọi số pha chế, thanh toán tiền mặt + chuyển khoản, chốt ca đếm theo mệnh giá, kho trừ theo công thức và tự lập sổ doanh thu S1a cho hộ kinh doanh. Dùng ngay trên điện thoại, đăng ký miễn phí.';
        $seoImage = asset('og-image.png');
        $seoUrl = url()->current();

        // Ngưỡng thuế lấy từ config/tax.php — luật đổi thì trang này tự đổi theo, không phải sửa lại.
        $threshold = (float) config('tax.exempt_revenue_threshold', 1_000_000_000);
        $thresholdLabel = $threshold >= 1_000_000_000
            ? rtrim(rtrim(number_format($threshold / 1_000_000_000, 1, ',', '.'), '0'), ',').' tỷ đồng'
            : number_format($threshold / 1_000_000, 0, ',', '.').' triệu đồng';
        $thresholdBasis = config('tax.threshold_basis');
        $thresholdUpdated = config('tax.threshold_basis_updated');
    @endphp

    <title>{{ $seoTitle }}</title>
    <meta name="description" content="{{ $seoDescription }}">
    <meta name="robots" content="index, follow">
    <link rel="canonical" href="{{ $seoUrl }}">

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

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Big+Shoulders+Display:wght@600..900&family=Be+Vietnam+Pro:wght@400;500;600;700&display=swap" rel="stylesheet">

    @vite(['resources/css/app.css', 'resources/js/app.js'])

    <style>
        /*
         * Rót — trang giới thiệu. Ý tưởng: "vé gọi số & bảng hiệu vỉa hè". Chữ tiêu đề hẹp, đậm
         * như biển hiệu quán (Big Shoulders Display); nội dung đọc bằng Be Vietnam Pro. Nền xám xi măng
         * vỉa hè, giấy in nhiệt trắng, cam hổ phách của Rót CHỈ cho nút hành động & điểm nhấn chính.
         */
        .r-page {
            --concrete: #ecebe7;
            --concrete-2: #e2e0da;
            --espresso: #2a1a10;
            --roast: #5a4334;
            --muted: #6f625a;
            --paper: #ffffff;
            --amber: #d97706;
            --amber-dark: #b45309;
            --amber-soft: #fbe9cf;
            --ice: #dcebef;
            --ok: #2f7d4f;
            --rule: #d6d3cc;
            font-family: 'Be Vietnam Pro', ui-sans-serif, system-ui, sans-serif;
            background: var(--concrete);
            color: var(--espresso);
            line-height: 1.6;
        }
        .r-page *:focus-visible { outline: 3px solid var(--amber); outline-offset: 3px; border-radius: 4px; }
        .r-sign { font-family: 'Big Shoulders Display', 'Be Vietnam Pro', sans-serif; font-weight: 800; letter-spacing: -0.005em; line-height: .95; }
        .r-wrap { max-width: 1160px; margin: 0 auto; padding-left: 20px; padding-right: 20px; }

        .r-head { display: flex; align-items: center; height: 76px; gap: 14px; }
        .r-logo { display: inline-flex; align-items: center; gap: 10px; color: var(--espresso); text-decoration: none; }
        .r-logo img { width: 36px; height: 36px; border-radius: 10px; }
        .r-logo b { font-size: 1.9rem; }
        .r-head nav { margin-left: auto; display: flex; align-items: center; gap: 24px; white-space: nowrap; font-size: .95rem; font-weight: 500; }
        .r-head nav a { color: var(--roast); text-decoration: none; }
        .r-head nav a:hover { color: var(--espresso); }
        .r-hide-sm { display: none; }
        @media (min-width: 760px) { .r-hide-sm { display: inline; } }
        @media (max-width: 400px) { .r-head nav { gap: 14px; font-size: .88rem; } }

        .r-btn { display: inline-flex; align-items: center; justify-content: center; gap: 10px; border-radius: 12px; padding: 15px 24px; font-weight: 600; text-decoration: none; transition: background-color .15s; }
        .r-btn-amber { background: var(--amber); color: #fff !important; }
        .r-btn-amber:hover { background: var(--amber-dark); }
        .r-btn-sm { padding: 10px 16px; font-size: .9rem; border-radius: 10px; }
        .r-link { color: var(--espresso) !important; font-weight: 600; text-decoration: underline; text-decoration-color: var(--amber); text-underline-offset: 5px; text-decoration-thickness: 2px; }

        /* HERO */
        .r-hero { display: grid; gap: 56px; padding-top: 28px; padding-bottom: 88px; align-items: center; }
        @media (min-width: 980px) { .r-hero { grid-template-columns: 1.15fr .85fr; gap: 40px; padding-top: 48px; padding-bottom: 112px; } }
        .r-hero h1 { font-size: clamp(3.1rem, 8vw, 6.4rem); }
        .r-hero h1 span { display: block; color: var(--roast); }
        .r-lede { margin-top: 26px; font-size: 1.15rem; color: var(--roast); max-width: 48ch; }
        .r-cta { margin-top: 32px; display: flex; flex-wrap: wrap; align-items: center; gap: 16px 24px; }
        .r-facts { margin-top: 40px; display: flex; flex-wrap: wrap; gap: 10px 28px; font-size: .92rem; color: var(--muted); }
        .r-facts span { display: inline-flex; align-items: center; gap: 8px; }
        .r-facts i { color: var(--amber); }

        /* Vé gọi số + hoá đơn giấy */
        .r-stack { position: relative; max-width: 470px; width: 100%; margin: 0 auto; padding: 140px 0 40px; }
        @media (min-width: 560px) { .r-stack { padding: 40px 0 40px 8px; } }
        .r-receipt {
            position: relative; width: min(300px, 82vw); background: var(--paper); padding: 30px 24px 26px;
            transform: rotate(-2.5deg); box-shadow: 0 30px 50px -30px rgba(42, 26, 16, .55);
            font-size: .93rem;
        }
        .r-receipt::before, .r-receipt::after { /* mép răng cưa của cuộn giấy in nhiệt */
            content: ''; position: absolute; left: 0; right: 0; height: 12px;
            background: radial-gradient(circle at 8px 0, transparent 7px, var(--paper) 7.5px) 0 0 / 16px 12px repeat-x;
        }
        .r-receipt::before { top: -11px; }
        .r-receipt::after { bottom: -11px; transform: rotate(180deg); }
        .r-receipt-head { text-align: center; border-bottom: 1px dashed var(--rule); padding-bottom: 12px; margin-bottom: 12px; }
        .r-receipt-head b { font-family: 'Big Shoulders Display', sans-serif; font-size: 1.45rem; font-weight: 800; letter-spacing: .02em; }
        .r-receipt-head small { display: block; color: var(--muted); font-size: .78rem; }
        .r-line { display: flex; justify-content: space-between; gap: 12px; padding: 3px 0; }
        .r-line small { display: block; color: var(--muted); font-size: .78rem; }
        .r-total { border-top: 1px dashed var(--rule); margin-top: 10px; padding-top: 10px; font-weight: 700; font-size: 1.05rem; }
        .r-pay { margin-top: 8px; font-size: .82rem; color: var(--muted); }
        .r-pay b { color: var(--espresso); font-weight: 600; }
        .r-ticket {
            position: absolute; right: 6px; top: 0; width: 128px;
            background: var(--amber); color: #fff; border-radius: 10px; padding: 12px 14px 14px; text-align: center;
            transform: rotate(6deg); box-shadow: 0 18px 30px -16px rgba(180, 83, 9, .8);
        }
        .r-ticket::after { /* lỗ đục của vé */
            content: ''; position: absolute; left: 50%; top: 8px; width: 12px; height: 12px; margin-left: -6px; border-radius: 50%; background: var(--concrete);
        }
        .r-ticket small { display: block; margin-top: 14px; font-size: .78rem; opacity: .9; }
        .r-ticket strong { display: block; font-family: 'Big Shoulders Display', sans-serif; font-weight: 900; font-size: 4.4rem; line-height: .9; }
        .r-ticket em { display: block; font-style: normal; font-size: .76rem; margin-top: 4px; }
        .r-qr-note {
            position: absolute; left: 0; bottom: 4px; background: var(--paper); border-radius: 12px; padding: 10px 14px;
            display: flex; gap: 10px; align-items: center; font-size: .82rem; font-weight: 600; box-shadow: 0 16px 30px -18px rgba(42, 26, 16, .6);
            transform: rotate(2deg); border-left: 4px solid #5b9fb0;
        }
        @media (prefers-reduced-motion: no-preference) {
            .r-ticket { animation: r-call .7s cubic-bezier(.2, .9, .3, 1.2) .5s both; }
            @keyframes r-call { from { transform: translateY(-40px) rotate(0deg); opacity: 0; } to { transform: translateY(0) rotate(6deg); opacity: 1; } }
        }

        /* Section chung */
        .r-sec { padding-top: 72px; padding-bottom: 72px; }
        @media (min-width: 768px) { .r-sec { padding-top: 104px; padding-bottom: 104px; } }
        .r-light { background: #f6f5f2; }
        .r-h2 { font-size: clamp(2.3rem, 5vw, 3.8rem); max-width: 18ch; }
        .r-sub { margin-top: 16px; font-size: 1.08rem; color: var(--roast); max-width: 58ch; }

        /* Ba kiểu quán */
        .r-kinds { display: grid; gap: 40px; align-items: start; margin-top: 44px; }
        @media (min-width: 980px) { .r-kinds { grid-template-columns: .8fr 1.2fr; gap: 56px; } }
        .r-tabs { display: grid; gap: 8px; }
        .r-tab { text-align: left; border: 2px solid var(--rule); background: transparent; border-radius: 12px; padding: 14px 16px; cursor: pointer; font: inherit; color: var(--espresso); display: grid; grid-template-columns: 30px 1fr; gap: 12px; align-items: start; }
        .r-tab i { color: var(--roast); margin-top: 4px; font-size: 1.05rem; }
        .r-tab b { display: block; font-weight: 600; }
        .r-tab span span { display: block; font-size: .9rem; color: var(--muted); margin-top: 2px; }
        .r-tab[aria-selected="true"] { border-color: var(--espresso); background: var(--paper); }
        .r-tab[aria-selected="true"] i { color: var(--amber); }
        .r-pos { background: var(--espresso); border-radius: 22px; padding: 18px; color: #fff; }
        .r-pos-top { display: flex; justify-content: space-between; align-items: center; font-size: .85rem; color: #cdbfb4; margin-bottom: 14px; padding: 0 4px; }
        .r-pos-top b { color: #fff; font-weight: 600; }
        .r-pos-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 8px; }
        .r-tile { background: #3b281c; border-radius: 12px; padding: 12px; min-height: 84px; display: flex; flex-direction: column; justify-content: space-between; }
        .r-tile b { font-weight: 600; font-size: .9rem; line-height: 1.3; }
        .r-tile span { font-size: .8rem; color: #cdbfb4; }
        .r-tile.is-in { outline: 2px solid var(--amber); }
        .r-tile small { color: #f6c27a; font-size: .74rem; }
        .r-pos-note { margin-top: 12px; display: flex; gap: 10px; align-items: flex-start; font-size: .86rem; color: #e8ddd5; background: #3b281c; border-radius: 12px; padding: 12px 14px; }
        .r-pos-note i { color: #f6c27a; margin-top: 3px; }
        .r-pos-pay { margin-top: 12px; background: var(--amber); border-radius: 12px; padding: 13px 16px; display: flex; justify-content: space-between; font-weight: 600; }
        @media (max-width: 420px) { .r-pos-grid { grid-template-columns: repeat(2, 1fr); } }
        [x-cloak] { display: none !important; }

        /* Một ca bán — trình tự thật nên dùng cuống vé đánh số */
        .r-shift { margin-top: 48px; display: grid; gap: 14px; counter-reset: stub; }
        @media (min-width: 700px) { .r-shift { grid-template-columns: repeat(2, 1fr); } }
        @media (min-width: 1040px) { .r-shift { grid-template-columns: repeat(3, 1fr); } }
        .r-stub { position: relative; background: var(--paper); border-radius: 14px; padding: 22px 22px 22px 86px; min-height: 150px; }
        .r-stub::before { /* cuống vé: số thứ tự + đường đục lỗ */
            counter-increment: stub; content: counter(stub, decimal-leading-zero);
            position: absolute; left: 0; top: 0; bottom: 0; width: 64px; display: grid; place-items: center;
            font-family: 'Big Shoulders Display', sans-serif; font-weight: 800; font-size: 1.9rem; color: var(--amber-dark);
            border-right: 2px dashed var(--concrete-2);
        }
        .r-stub h3 { font-weight: 700; font-size: 1.08rem; }
        .r-stub p { margin-top: 6px; color: var(--muted); font-size: .95rem; }

        /* Chốt ca & kho */
        .r-pair { display: grid; gap: 64px; margin-top: 8px; }
        @media (min-width: 980px) { .r-pair { grid-template-columns: 1fr 1fr; gap: 56px; } }
        .r-sheet { margin-top: 28px; background: var(--paper); border-radius: 16px; padding: 20px; }
        .r-count { width: 100%; border-collapse: collapse; font-size: .95rem; font-variant-numeric: tabular-nums; }
        .r-count th { text-align: left; font-weight: 500; color: var(--muted); font-size: .82rem; padding: 0 0 8px; }
        .r-count td { padding: 7px 0; border-top: 1px solid #efede8; }
        .r-count td:nth-child(2), .r-count th:nth-child(2) { text-align: center; }
        .r-count td:last-child, .r-count th:last-child { text-align: right; }
        .r-count tfoot td { border-top: 2px solid var(--espresso); font-weight: 700; padding-top: 10px; }
        .r-match { margin-top: 12px; display: flex; gap: 10px; align-items: center; color: var(--ok); font-weight: 600; font-size: .92rem; }
        .r-stock { display: grid; gap: 14px; }
        .r-stock-row { display: grid; grid-template-columns: 1fr auto; gap: 4px 12px; font-size: .95rem; }
        .r-stock-row span:last-child { font-variant-numeric: tabular-nums; color: var(--roast); }
        .r-bar { grid-column: 1 / -1; height: 8px; border-radius: 99px; background: #efede8; overflow: hidden; }
        .r-bar i { display: block; height: 100%; border-radius: 99px; background: var(--roast); }
        .r-stock-row.is-low span:last-child { color: #b42318; font-weight: 600; }
        .r-stock-row.is-low .r-bar i { background: #d9480f; }
        .r-suggest { margin-top: 16px; padding-top: 14px; border-top: 1px dashed var(--rule); font-size: .9rem; color: var(--roast); display: flex; gap: 10px; }
        .r-suggest i { color: var(--amber); margin-top: 4px; }
        .r-pair h3 { font-size: clamp(1.9rem, 3.6vw, 2.6rem); }
        .r-pair p.r-sub { font-size: 1rem; }

        /* Sổ thuế — dải tối duy nhất */
        .r-dark { background: var(--espresso); color: #f4ede7; }
        .r-dark .r-sub { color: #cdbfb4; }
        .r-tax { display: grid; gap: 48px; align-items: center; }
        @media (min-width: 980px) { .r-tax { grid-template-columns: 1fr 1fr; gap: 72px; } }
        .r-meter-num { font-size: clamp(3rem, 7vw, 5.2rem); color: #fff; }
        .r-meter-of { color: #cdbfb4; margin-top: 6px; }
        .r-meter { margin-top: 22px; height: 18px; background: #4a3426; border-radius: 99px; overflow: hidden; position: relative; }
        .r-meter i { position: absolute; inset: 0 auto 0 0; width: 62%; background: repeating-linear-gradient(-45deg, var(--amber) 0 10px, #e98e1e 10px 20px); border-radius: 99px; }
        .r-meter-legend { margin-top: 14px; display: grid; gap: 6px; font-size: .9rem; color: #cdbfb4; }
        .r-meter-legend span { display: flex; justify-content: space-between; gap: 12px; }
        .r-meter-legend b { color: #fff; font-weight: 600; font-variant-numeric: tabular-nums; }
        .r-book { margin-top: 22px; display: flex; justify-content: space-between; gap: 12px; align-items: center; background: #3b281c; border-radius: 12px; padding: 12px 16px; font-size: .9rem; }
        .r-book b { color: #f6c27a; font-weight: 600; white-space: nowrap; }
        .r-basis { margin-top: 18px; font-size: .82rem; color: #a8968a; }

        /* Còn có */
        .r-more { margin-top: 40px; display: grid; gap: 0 48px; }
        @media (min-width: 760px) { .r-more { grid-template-columns: 1fr 1fr; } }
        .r-more li { display: grid; grid-template-columns: 28px 1fr; gap: 12px; padding: 16px 0; border-top: 1px solid var(--rule); }
        .r-more i { color: var(--amber-dark); margin-top: 4px; }
        .r-more b { font-weight: 600; }
        .r-more span span { color: var(--muted); }

        /* Kết */
        .r-final { text-align: left; }
        .r-final h2 { font-size: clamp(2.8rem, 7vw, 5.4rem); max-width: 14ch; }

        .r-foot { border-top: 1px solid var(--rule); padding: 32px 0 40px; font-size: .9rem; color: var(--muted); }
        .r-foot .r-wrap { display: flex; flex-wrap: wrap; justify-content: space-between; gap: 16px 32px; align-items: center; }
        .r-foot nav { display: flex; flex-wrap: wrap; gap: 8px 24px; }
        .r-foot a { color: var(--roast); text-decoration: none; }
        .r-foot a:hover { color: var(--espresso); text-decoration: underline; }
    </style>
    {{-- Dữ liệu có cấu trúc giúp công cụ tìm kiếm hiểu đây là phần mềm ứng dụng web --}}
    <script type="application/ld+json">
        {!! json_encode([
            '@context' => 'https://schema.org',
            '@type' => 'SoftwareApplication',
            'name' => 'Rót',
            'applicationCategory' => 'BusinessApplication',
            'operatingSystem' => 'Web, Android, iOS',
            'inLanguage' => 'vi',
            'url' => $seoUrl,
            'description' => $seoDescription,
            'offers' => ['@type' => 'Offer', 'price' => '0', 'priceCurrency' => 'VND'],
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) !!}
    </script>
</head>
<body class="r-page antialiased">

<header class="r-wrap r-head">
    <a href="{{ route('home') }}" class="r-logo" aria-label="Rót — trang chủ">
        <img src="{{ asset('favicon.svg') }}" alt="">
        <b class="r-sign">Rót</b>
    </a>
    <nav aria-label="Điều hướng chính">
        <a href="#kieu-quan" class="r-hide-sm">Kiểu quán</a>
        <a href="#mot-ca" class="r-hide-sm">Một ca bán</a>
        <a href="#so-doanh-thu" class="r-hide-sm">Sổ doanh thu</a>
        <a href="{{ route('login') }}">Đăng nhập</a>
        @if(\App\Models\PlatformSetting::registrationOpen())
            <a href="{{ route('register') }}" class="r-btn r-btn-amber r-btn-sm">Đăng ký miễn phí</a>
        @endif
    </nav>
</header>

<main>
    {{-- ===== HERO ===== --}}
    <section class="r-wrap r-hero">
        <div>
            <h1 class="r-sign">Bán nhanh lúc đông khách. <span>Cuối ngày sổ tự khớp.</span></h1>
            <p class="r-lede">Rót là máy bán hàng trên điện thoại cho xe cà phê, quán ăn nhỏ và tiệm làm đẹp: lên đơn, gọi số, chốt ca, trừ kho — và tự lập sổ doanh thu cho hộ kinh doanh.</p>
            <div class="r-cta">
                @if(\App\Models\PlatformSetting::registrationOpen())
                    <a href="{{ route('register') }}" class="r-btn r-btn-amber">Đăng ký miễn phí</a>
                    <a href="{{ route('login') }}" class="r-link">Đăng nhập</a>
                @else
                    <a href="{{ route('login') }}" class="r-btn r-btn-amber">Đăng nhập</a>
                @endif
            </div>
            <div class="r-facts">
                <span><i class="fa-solid fa-hand-pointer" aria-hidden="true"></i>Bán bằng một tay</span>
                <span><i class="fa-solid fa-sun" aria-hidden="true"></i>Đọc rõ ngoài nắng</span>
                <span><i class="fa-solid fa-mobile-screen" aria-hidden="true"></i>Không phải mua máy POS</span>
            </div>
        </div>

        <div class="r-stack" aria-label="Minh hoạ: vé gọi số 27 và hoá đơn của đơn hàng">
            <div class="r-receipt">
                <div class="r-receipt-head"><b>Xe cà phê Cô Ba</b><small>Đơn số 27 · 07:42 · ca sáng</small></div>
                <div class="r-line"><span>Cà phê sữa đá ×2<small>ít ngọt</small></span><span>50.000</span></div>
                <div class="r-line"><span>Bạc xỉu ×1</span><span>25.000</span></div>
                <div class="r-line"><span>Trà đào cam sả ×1<small>thêm đào</small></span><span>40.000</span></div>
                <div class="r-line r-total"><span>Tổng</span><span>115.000</span></div>
                <p class="r-pay">Tiền mặt <b>50.000</b> + chuyển khoản <b>65.000</b></p>
            </div>
            <div class="r-ticket" aria-hidden="true"><small>Mời lấy món</small><strong class="r-sign">27</strong><em>đã pha xong</em></div>
            <div class="r-qr-note"><i class="fa-solid fa-qrcode" aria-hidden="true"></i>Chị Lan vừa gọi 3 món qua QR</div>
        </div>
    </section>

    {{-- ===== BA KIỂU QUÁN ===== --}}
    @php
        $kinds = [
            'cafe' => [
                'icon' => 'fa-mug-hot', 'name' => 'Xe cà phê', 'desc' => 'Cà phê, trà, nước ép — bán mang đi',
                'place' => 'Xe cà phê Cô Ba', 'items' => [['Cà phê sữa đá', '25.000', true], ['Bạc xỉu', '25.000', false], ['Trà đào cam sả', '35.000', true], ['Cam vắt', '30.000', false], ['Cà phê muối', '29.000', false], ['Sữa chua đá', '25.000', false]],
                'note' => 'Tuỳ chọn ít đá, ít ngọt, thêm shot có giá riêng. Bán một ly là kho tự trừ cà phê, sữa, ly theo công thức.', 'pay' => '60.000đ',
            ],
            'food' => [
                'icon' => 'fa-utensils', 'name' => 'Quán ăn nhỏ', 'desc' => 'Cơm, bún, phở, bánh mì — bán tại quầy',
                'place' => 'Cơm tấm Út Hiền', 'items' => [['Cơm sườn bì chả', '45.000', true], ['Cơm gà nướng', '40.000', false], ['Canh rong biển', '10.000', true], ['Trà đá', '3.000', true], ['Sườn thêm', '20.000', false], ['Hộp mang đi', '2.000', false]],
                'note' => 'Món thêm, hộp mang đi trừ kho riêng. Giờ trưa đông thì bật hàng chờ để bếp gọi số khách.', 'pay' => '58.000đ',
            ],
            'salon' => [
                'icon' => 'fa-scissors', 'name' => 'Tiệm làm đẹp', 'desc' => 'Cắt, gội, nhuộm, làm móng — khách vãng lai',
                'place' => 'Tiệm tóc Minh', 'items' => [['Cắt tóc nam', '60.000', true], ['Gội dưỡng sinh', '80.000', false], ['Nhuộm phủ bạc', '250.000', false], ['Uốn lạnh', '350.000', false], ['Sơn gel', '120.000', false], ['Cạo mặt', '30.000', true]],
                'note' => 'Bảng giá dịch vụ thay cho thực đơn, vật tư thay cho nguyên liệu. Khách chờ tới lượt có thể quét QR chọn sẵn dịch vụ.', 'pay' => '90.000đ',
            ],
        ];
    @endphp
    <section id="kieu-quan" class="r-sec r-light" x-data="{ kind: 'cafe' }">
        <div class="r-wrap">
            <h2 class="r-sign r-h2">Một ứng dụng, đúng chữ của từng nghề.</h2>
            <p class="r-sub">Chọn kiểu quán lúc đăng ký, Rót gọi đúng tên thứ bạn bán. Bên dưới vẫn là cùng một bộ nghiệp vụ đã chạy ổn: bán, trừ kho, chốt ca, báo cáo, sổ doanh thu.</p>

            <div class="r-kinds">
                <div class="r-tabs" role="tablist" aria-label="Chọn kiểu quán để xem màn bán hàng">
                    @foreach($kinds as $key => $k)
                        <button type="button" role="tab" class="r-tab" id="tab-{{ $key }}" aria-controls="pos-{{ $key }}"
                                :aria-selected="(kind === '{{ $key }}').toString()" aria-selected="{{ $key === 'cafe' ? 'true' : 'false' }}"
                                @click="kind = '{{ $key }}'">
                            <i class="fa-solid {{ $k['icon'] }}" aria-hidden="true"></i>
                            <span><b>{{ $k['name'] }}</b><span>{{ $k['desc'] }}</span></span>
                        </button>
                    @endforeach
                </div>

                @foreach($kinds as $key => $k)
                    <div class="r-pos" role="tabpanel" id="pos-{{ $key }}" aria-labelledby="tab-{{ $key }}" x-show="kind === '{{ $key }}'" @if($key !== 'cafe') x-cloak @endif>
                        <div class="r-pos-top"><b>{{ $k['place'] }}</b><span>Ca sáng · cô Lan</span></div>
                        <div class="r-pos-grid">
                            @foreach($k['items'] as [$name, $price, $in])
                                <div class="r-tile {{ $in ? 'is-in' : '' }}"><b>{{ $name }}</b><span>{{ $price }}@if($in) <small>· trong đơn</small>@endif</span></div>
                            @endforeach
                        </div>
                        <p class="r-pos-note"><i class="fa-solid fa-circle-info" aria-hidden="true"></i><span>{{ $k['note'] }}</span></p>
                        <div class="r-pos-pay"><span>Thanh toán</span><span>{{ $k['pay'] }}</span></div>
                    </div>
                @endforeach
            </div>
        </div>
    </section>

    {{-- ===== MỘT CA BÁN ===== --}}
    <section id="mot-ca" class="r-sec">
        <div class="r-wrap">
            <h2 class="r-sign r-h2">Một ca bán trên Rót, từ lúc mở xe tới lúc khoá két.</h2>
            <ol class="r-shift">
                <li class="r-stub"><h3>Mở ca</h3><p>Nhập tiền lẻ đầu ca. Mỗi người bán một ca riêng, số liệu không lẫn vào nhau.</p></li>
                <li class="r-stub"><h3>Lên đơn hoặc nhận đơn QR</h3><p>Chạm món, chọn tuỳ chọn, giảm giá. Khách quét QR dán ở quán để tự gọi món — bấm Nhận là giỏ hàng điền sẵn.</p></li>
                <li class="r-stub"><h3>Pha chế gọi số</h3><p>Đơn vào hàng chờ kèm số thứ tự trong ngày. Pha xong bấm Xong để gọi số khách. Món sắp hết nguyên liệu được báo ngay trên màn bán.</p></li>
                <li class="r-stub"><h3>Thanh toán</h3><p>Tiền mặt, chuyển khoản qua mã VietQR, hoặc kết hợp cả hai. In hoá đơn, xuất hoá đơn điện tử khi khách cần.</p></li>
                <li class="r-stub"><h3>Chốt ca</h3><p>Đếm tiền theo từng mệnh giá, Rót so với số phải có. Chỉ tiền mặt được cộng vào quỹ, đơn nháp còn sót sẽ được nhắc.</p></li>
                <li class="r-stub"><h3>Xem báo cáo</h3><p>Doanh thu thực nhận, lợi nhuận sau giá vốn, số đơn, trung bình mỗi đơn, theo hình thức thanh toán, người bán và giờ cao điểm.</p></li>
            </ol>
        </div>
    </section>

    {{-- ===== CHỐT CA & KHO ===== --}}
    <section class="r-sec r-light">
        <div class="r-wrap r-pair">
            <div>
                <h3 class="r-sign">Chốt ca khớp tới từng tờ tiền.</h3>
                <p class="r-sub">Đếm két theo mệnh giá thay cho bấm máy tính. Chênh lệch hiện ngay, kèm tổng chuyển khoản riêng để đối chiếu với sao kê.</p>
                <div class="r-sheet">
                    <table class="r-count">
                        <caption class="sr-only">Ví dụ bảng đếm tiền khi chốt ca</caption>
                        <thead><tr><th scope="col">Mệnh giá</th><th scope="col">Số tờ</th><th scope="col">Thành tiền</th></tr></thead>
                        <tbody>
                            @foreach([['500.000', 2, '1.000.000'], ['200.000', 3, '600.000'], ['100.000', 4, '400.000'], ['50.000', 5, '250.000'], ['20.000', 4, '80.000'], ['10.000', 1, '10.000']] as [$d, $n, $t])
                                <tr><td>{{ $d }}</td><td>{{ $n }}</td><td>{{ $t }}</td></tr>
                            @endforeach
                        </tbody>
                        <tfoot><tr><td colspan="2">Tiền mặt trong két</td><td>2.340.000</td></tr></tfoot>
                    </table>
                    <p class="r-match"><i class="fa-solid fa-circle-check" aria-hidden="true"></i>Khớp quỹ: 500.000 đầu ca + 1.840.000 tiền mặt bán được</p>
                </div>
            </div>
            <div>
                <h3 class="r-sign">Kho tự trừ theo công thức.</h3>
                <p class="r-sub">Gán nguyên liệu cho từng món, Rót tính giá vốn, gợi ý giá bán và trừ kho mỗi lần bán. Kiểm kê hàng loạt một lượt, sửa tồn luôn có nhật ký ai sửa, lúc nào.</p>
                <div class="r-sheet">
                    <div class="r-stock">
                        @foreach([['Cà phê hạt rang mộc', 'còn 2,4 kg', 70, false], ['Sữa đặc', 'còn 6 lon', 55, false], ['Đào ngâm', 'còn 9 ly — sắp hết', 14, true], ['Ly nhựa 500ml', 'còn 320 cái', 80, false]] as [$name, $qty, $pct, $low])
                            <div class="r-stock-row {{ $low ? 'is-low' : '' }}"><span>{{ $name }}</span><span>{{ $qty }}</span><span class="r-bar" aria-hidden="true"><i style="width: {{ $pct }}%"></i></span></div>
                        @endforeach
                    </div>
                    <p class="r-suggest"><i class="fa-solid fa-cart-flatbed" aria-hidden="true"></i><span>Gợi ý nhập hàng: thêm 3 hộp đào ngâm để đủ dùng 7 ngày, tính theo mức bán 2 tuần gần đây.</span></p>
                </div>
            </div>
        </div>
    </section>

    {{-- ===== SỔ DOANH THU & NGƯỠNG THUẾ ===== --}}
    <section id="so-doanh-thu" class="r-sec r-dark">
        <div class="r-wrap r-tax">
            <div>
                <h2 class="r-sign r-h2">Sổ doanh thu tự lên, ngưỡng thuế báo trước.</h2>
                <p class="r-sub">Rót lập sổ doanh thu mẫu S1a cho hộ kinh doanh từ các đơn đã bán, cộng cả doanh thu bán qua app giao đồ ăn hay bán sỉ mà bạn tự nhập. In hoặc lưu PDF khi cần.</p>
                <p class="r-sub">Thanh theo dõi cho biết doanh thu năm đang ở đâu so với ngưỡng miễn thuế hiện hành — {{ $thresholdLabel }} mỗi năm.</p>
                @if($thresholdBasis)
                    <p class="r-basis">Căn cứ: {{ $thresholdBasis }}@if($thresholdUpdated), cập nhật {{ $thresholdUpdated }}@endif. Rót là công cụ ghi chép và tính tham khảo, không thay thế tư vấn thuế.</p>
                @endif
            </div>
            <div aria-label="Ví dụ theo dõi doanh thu năm so với ngưỡng miễn thuế">
                <p class="r-sign r-meter-num">620.000.000đ</p>
                <p class="r-meter-of">doanh thu năm 2026 tới nay, 62% ngưỡng {{ $thresholdLabel }}</p>
                <div class="r-meter" aria-hidden="true"><i></i></div>
                <div class="r-meter-legend">
                    <span>Bán tại quán (đơn trên Rót)<b>548.000.000đ</b></span>
                    <span>App giao đồ ăn, bán sỉ (tự nhập)<b>72.000.000đ</b></span>
                </div>
                <div class="r-book"><span><i class="fa-solid fa-book" aria-hidden="true"></i>&nbsp; Sổ doanh thu S1a, tháng 9</span><b>In / Lưu PDF</b></div>
            </div>
        </div>
    </section>

    {{-- ===== CÒN CÓ ===== --}}
    <section class="r-sec">
        <div class="r-wrap">
            <h2 class="r-sign r-h2">Những thứ nhỏ mà chủ quán hay hỏi.</h2>
            <ul class="r-more">
                <li><i class="fa-solid fa-star" aria-hidden="true"></i><span><b>Tích điểm khách quen</b><span> — nhập số điện thoại lúc thanh toán để cộng điểm, lần sau dùng điểm trừ thẳng vào tiền.</span></span></li>
                <li><i class="fa-solid fa-user-clock" aria-hidden="true"></i><span><b>Lương nhân viên</b><span> — theo giờ hoặc theo tháng, tính vào báo cáo lợi nhuận cùng tiền mặt bằng.</span></span></li>
                <li><i class="fa-solid fa-store" aria-hidden="true"></i><span><b>Nhiều điểm bán</b><span> — vài xe, vài quầy vẫn chung một tài khoản, chuyển qua lại một chạm.</span></span></li>
                <li><i class="fa-solid fa-file-invoice" aria-hidden="true"></i><span><b>Hoá đơn điện tử</b><span> — kết nối SePay, xuất ngay khi khách yêu cầu.</span></span></li>
                <li><i class="fa-solid fa-wand-magic-sparkles" aria-hidden="true"></i><span><b>Thiết lập nhanh</b><span> — có sẵn thực đơn và kho mẫu cho từng kiểu quán, sửa lại là bán được.</span></span></li>
                <li><i class="fa-solid fa-file-export" aria-hidden="true"></i><span><b>Dữ liệu là của bạn</b><span> — xuất toàn bộ ra CSV bất cứ lúc nào.</span></span></li>
                <li><i class="fa-solid fa-mobile-screen-button" aria-hidden="true"></i><span><b>Như một ứng dụng</b><span> — thêm vào màn hình chính điện thoại hoặc máy tính bảng, không cần tải từ kho ứng dụng.</span></span></li>
                <li><i class="fa-solid fa-user-shield" aria-hidden="true"></i><span><b>Phân quyền rõ</b><span> — nhân viên chỉ bán và chốt ca của mình, không thấy giá vốn hay lợi nhuận.</span></span></li>
            </ul>
        </div>
    </section>

    {{-- ===== KẾT ===== --}}
    <section class="r-sec r-light">
        <div class="r-wrap r-final">
            <h2 class="r-sign">Mở ca đầu tiên ngay hôm nay.</h2>
            <p class="r-sub">Đăng ký miễn phí, bấm Thiết lập nhanh để có sẵn thực đơn mẫu, rồi lên đơn đầu tiên — không cần thẻ thanh toán, không cần lắp đặt.</p>
            <div class="r-cta">
                @if(\App\Models\PlatformSetting::registrationOpen())
                    <a href="{{ route('register') }}" class="r-btn r-btn-amber">Đăng ký miễn phí</a>
                    <a href="{{ route('login') }}" class="r-link">Tôi đã có tài khoản</a>
                @else
                    <a href="{{ route('login') }}" class="r-btn r-btn-amber">Đăng nhập</a>
                @endif
            </div>
        </div>
    </section>
</main>

<footer class="r-foot">
    <div class="r-wrap">
        <p>© {{ date('Y') }} Rót — bán hàng một tay, nhanh gọn cho xe cà phê.</p>
        <nav aria-label="Liên kết cuối trang">
            <a href="{{ route('pages.about') }}">Giới thiệu</a>
            <a href="{{ route('pages.terms') }}">Điều khoản sử dụng</a>
            <a href="{{ route('pages.privacy') }}">Chính sách bảo mật</a>
            <a href="{{ route('login') }}">Đăng nhập</a>
        </nav>
    </div>
</footer>
</body>
</html>
