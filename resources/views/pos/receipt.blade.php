<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Hoá đơn #{{ $order->id }}</title>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body {
            font-family: 'Courier New', Courier, monospace;
            font-size: 13px;
            line-height: 1.5;
            color: #000;
            background: #e5e5e5;
        }
        .receipt {
            width: {{ $location->receipt_paper_width }}mm;
            margin: 16px auto;
            padding: 10px 8px;
            background: #fff;
        }
        .center { text-align: center; }
        .right { text-align: right; }
        .bold { font-weight: bold; }
        .line { border-top: 1px dashed #000; margin: 8px 0; }
        .row { display: flex; justify-content: space-between; gap: 8px; }
        .shop-name { font-size: 16px; font-weight: bold; }
        table { width: 100%; border-collapse: collapse; }
        td { padding: 2px 0; vertical-align: top; }
        .item-name { max-width: 60%; }
        .modifiers { font-size: 11px; color: #444; padding-left: 8px; }
        .toolbar {
            max-width: {{ $location->receipt_paper_width }}mm;
            margin: 0 auto 12px;
            text-align: center;
        }
        .toolbar button {
            font-family: inherit;
            font-size: 14px;
            padding: 10px 20px;
            border-radius: 8px;
            border: none;
            background: #d97706;
            color: #fff;
            cursor: pointer;
        }
        @media print {
            body { background: #fff; }
            .no-print { display: none !important; }
            .receipt { margin: 0; width: {{ $location->receipt_paper_width }}mm; }
            @page { size: {{ $location->receipt_paper_width }}mm auto; margin: 0; }
        }
    </style>
</head>
<body>
    <div class="toolbar no-print">
        <button onclick="window.print()">In hoá đơn</button>
    </div>

    <div class="receipt">
        <div class="center">
            <div class="shop-name">{{ $location->name }}</div>
            @if($location->address)
                <div>{{ $location->address }}</div>
            @endif
        </div>

        <div class="line"></div>

        <div class="row"><span>Đơn số:</span><span class="bold">#{{ $order->id }}</span></div>
        <div class="row"><span>Thời gian:</span><span>{{ $order->completed_at?->format('d/m/Y H:i') }}</span></div>
        <div class="row"><span>Loại đơn:</span><span>{{ $order->order_type === 'mang_di' ? 'Mang đi' : 'Ngồi lại' }}</span></div>
        @if($order->creator)
            <div class="row"><span>Nhân viên:</span><span>{{ $order->creator->name }}</span></div>
        @endif
        @if($order->customer)
            <div class="row"><span>Khách hàng:</span><span>{{ $order->customer->name ?: $order->customer->phone }}</span></div>
        @endif

        <div class="line"></div>

        <table>
            @foreach($order->items as $item)
                <tr>
                    <td class="item-name">
                        {{ $item->variant->product->name }} ({{ $item->variant->name }})
                        @if($item->modifiers->isNotEmpty())
                            <div class="modifiers">
                                @foreach($item->modifiers as $mod)
                                    {{ $mod->modifier?->name ?? 'Tuỳ chọn' }}@if(!$loop->last), @endif
                                @endforeach
                            </div>
                        @endif
                        @if($item->discount_amount > 0)
                            <div class="modifiers">Giảm giá: -{{ money($item->discount_amount) }}đ</div>
                        @endif
                    </td>
                    <td class="right">x{{ $item->quantity }}</td>
                    <td class="right">{{ money($item->line_total) }}đ</td>
                </tr>
            @endforeach
        </table>

        <div class="line"></div>

        @if($order->discount_amount > 0)
            <div class="row"><span>Tạm tính</span><span>{{ money($order->subtotal) }}đ</span></div>
            <div class="row"><span>Giảm giá</span><span>-{{ money($order->discount_amount) }}đ</span></div>
        @endif

        <div class="row bold" style="font-size: 15px;">
            <span>TỔNG CỘNG</span>
            <span>{{ money($order->total) }}đ</span>
        </div>
        <div class="row" style="margin-top: 4px;">
            <span>Thanh toán:</span>
            <span>
                @if($order->payment_method === 'tien_mat') Tiền mặt
                @elseif($order->payment_method === 'chuyen_khoan') Chuyển khoản
                @else Ví điện tử @endif
            </span>
        </div>

        <div class="line"></div>

        @if($order->customer && ($order->points_earned > 0 || $order->points_redeemed > 0))
            <div class="line"></div>
            @if($order->points_redeemed > 0)
                <div class="row"><span>Điểm đã dùng:</span><span>-{{ $order->points_redeemed }}</span></div>
            @endif
            @if($order->points_earned > 0)
                <div class="row"><span>Điểm tích thêm:</span><span>+{{ $order->points_earned }}</span></div>
            @endif
            <div class="row"><span>Điểm hiện có:</span><span>{{ $order->customer->points }}</span></div>
        @endif

        <div class="center" style="margin-top: 8px;">Cảm ơn quý khách!</div>
    </div>
    <script>
        // Tự mở hộp thoại in ngay khi trang load xong — đợi sự kiện 'load' (không
        // phải DOMContentLoaded) để đảm bảo mọi thứ (font, layout) đã sẵn sàng,
        // tránh in thiếu/lệch nội dung. Vẫn giữ nút bấm tay để in lại nếu cần.
        window.addEventListener('load', () => window.print());
    </script>
</body>
</html>
