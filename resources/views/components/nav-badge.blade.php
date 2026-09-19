@props(['count' => 0, 'color' => 'accent', 'inverted' => false, 'store' => null])
{{--
    Badge số nhỏ gắn lên icon/label của menu điều hướng (VD: tổng đơn trong ca,
    số nguyên liệu sắp hết) — TỰ ẨN khi count = 0, không hiện số "0" để tránh
    tạo cảm giác cảnh báo giả khi thật ra chưa có gì cần chú ý.

    color 'accent' = trung tính, dùng cho số liệu để theo dõi (VD: tổng đơn).
    color 'danger' = cảnh báo cần xử lý (VD: tồn kho thấp).
    inverted = true khi nền của hàng menu ĐANG active đã là màu accent —
    lúc đó đổi badge sang nền trắng/chữ màu để không bị chìm vào nền.

    store = tên field trong Alpine.store('nav', ...) (xem resources/js/app.js)
    để badge tự cập nhật NGAY trên client khi số liệu đổi trong lúc đang ở
    trang, không cần tải lại — dùng cho "Đơn hàng" vì lên đơn ở màn Order đi
    qua fetch(), không có điều hướng nào để server tính lại view composer.
    Không truyền $store (VD: Kho nguyên liệu) thì badge hiển thị TĨNH như
    trước, đủ dùng vì tồn kho không tự đổi ngay tại các màn hình này.
--}}
@php
    $solid = ['accent' => 'bg-[var(--accent)] text-white', 'danger' => 'bg-red-500 text-white'];
    $inv = ['accent' => 'bg-white text-[var(--accent-text)]', 'danger' => 'bg-white text-red-600'];
    $classes = ($inverted ? $inv : $solid)[$color] ?? $solid['accent'];
    $baseClasses = "inline-flex items-center justify-center min-w-[1.25rem] h-5 px-1 rounded-full text-[0.65rem] font-bold leading-none $classes";
    // Chặn cứng danh sách field hợp lệ — $store luôn do code trong dự án
    // truyền vào (không phải input người dùng), nhưng vẫn tránh nhét thẳng
    // giá trị bất kỳ vào biểu thức JS của x-show/x-text cho chắc.
    $allowedStores = ['orderBadgeCount', 'lowStockBadgeCount'];
    $storeField = in_array($store, $allowedStores, true) ? $store : null;
@endphp
@if($storeField)
    <span x-data
          x-cloak
          x-show="$store.nav.{{ $storeField }} > 0"
          x-text="$store.nav.{{ $storeField }} > 99 ? '99+' : $store.nav.{{ $storeField }}"
          {{ $attributes->merge(['class' => $baseClasses]) }}></span>
@elseif($count > 0)
    <span {{ $attributes->merge(['class' => $baseClasses]) }}>
        {{ $count > 99 ? '99+' : $count }}
    </span>
@endif
