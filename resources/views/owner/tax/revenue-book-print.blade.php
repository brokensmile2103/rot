<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title>Sổ doanh thu — {{ $location->name }} — {{ $periodLabel }}</title>
    {{-- Trang in độc lập (không dùng layout/Vite): chỉ cần CSS in đơn giản, mở nhanh, không phụ thuộc bản build. --}}
    <style>
        * { box-sizing: border-box; }
        body { font-family: "Times New Roman", Times, serif; color: #111; margin: 0; padding: 24px; background: #fff; font-size: 14px; line-height: 1.45; }
        .toolbar { max-width: 800px; margin: 0 auto 16px; display: flex; gap: 8px; font-family: system-ui, sans-serif; }
        .toolbar button, .toolbar a { font: inherit; font-size: 13px; padding: 8px 14px; border: 1px solid #bbb; border-radius: 8px; background: #fff; color: #333; cursor: pointer; text-decoration: none; }
        .toolbar .primary { background: #111; color: #fff; border-color: #111; }
        .sheet { max-width: 800px; margin: 0 auto; }
        .form-no { text-align: right; font-size: 12px; font-style: italic; }
        h1 { text-align: center; font-size: 18px; margin: 10px 0 4px; text-transform: uppercase; }
        .sub { text-align: center; margin: 0 0 14px; font-style: italic; }
        .meta p { margin: 2px 0; }
        table { width: 100%; border-collapse: collapse; margin-top: 14px; }
        thead { display: table-header-group; }
        tr { page-break-inside: avoid; }
        th, td { border: 1px solid #333; padding: 5px 8px; vertical-align: top; }
        th { text-align: center; background: #f2f2f2; font-weight: bold; }
        .letters th { font-weight: normal; font-style: italic; background: #fff; padding: 2px 8px; }
        td.date { width: 110px; text-align: center; white-space: nowrap; }
        td.amount { width: 150px; text-align: right; white-space: nowrap; }
        tr.month td { font-weight: bold; background: #f7f7f7; }
        tr.total td { font-weight: bold; background: #eaeaea; }
        .empty { text-align: center; padding: 24px; font-style: italic; }
        .sign { margin-top: 28px; display: flex; justify-content: flex-end; }
        .sign div { text-align: center; width: 260px; }
        .sign .role { font-weight: bold; }
        .sign .hint { font-style: italic; font-size: 12px; }
        .foot { margin-top: 40px; font-size: 11px; color: #666; font-family: system-ui, sans-serif; }
        @media print {
            body { padding: 0; }
            .toolbar { display: none; }
            @page { size: A4; margin: 15mm; }
        }
    </style>
</head>
<body>
    <div class="toolbar">
        <button type="button" class="primary" onclick="window.print()">In / Lưu thành PDF</button>
        <button type="button" onclick="window.close()">Đóng</button>
    </div>

    <div class="sheet">
        <div class="form-no">Mẫu số S1a-HKD<br>(Theo Thông tư số 152/2025/TT-BTC)</div>

        <h1>Sổ doanh thu bán hàng hóa, dịch vụ</h1>
        <p class="sub">{{ $periodLabel }}</p>

        <div class="meta">
            <p><strong>Hộ, cá nhân kinh doanh:</strong> {{ $household }}</p>
            <p><strong>Địa điểm kinh doanh:</strong> {{ $location->address ?: $location->name }}</p>
            <p><strong>Mã số thuế:</strong> {{ $location->tax_code ?: '.......................................' }}</p>
            <p><strong>Đơn vị tính:</strong> đồng</p>
        </div>

        <table>
            <thead>
                <tr>
                    <th>Ngày, tháng</th>
                    <th>Diễn giải</th>
                    <th>Số tiền</th>
                </tr>
                <tr class="letters">
                    <th>A</th>
                    <th>B</th>
                    <th>1</th>
                </tr>
            </thead>
            <tbody>
                @forelse($book['rows'] as $row)
                    @if($row['type'] === 'month_total')
                        <tr class="month">
                            <td colspan="2">{{ $row['description'] }}</td>
                            <td class="amount">{{ money($row['amount']) }}</td>
                        </tr>
                    @else
                        <tr>
                            <td class="date">{{ \Illuminate\Support\Carbon::parse($row['date'])->format('d/m/Y') }}</td>
                            <td>{{ $row['description'] }}</td>
                            <td class="amount">{{ money($row['amount']) }}</td>
                        </tr>
                    @endif
                @empty
                    <tr><td colspan="3" class="empty">Chưa có doanh thu trong kỳ này.</td></tr>
                @endforelse

                @if(count($book['rows']) > 0)
                    <tr class="total">
                        <td colspan="2">Tổng cộng</td>
                        <td class="amount">{{ money($book['total']) }}</td>
                    </tr>
                @endif
            </tbody>
        </table>

        <div class="sign">
            <div>
                <div>Ngày ..... tháng ..... năm ..........</div>
                <div class="role">Người đại diện hộ kinh doanh</div>
                <div class="hint">(Ký, họ tên)</div>
            </div>
        </div>

        <div class="foot">Sổ được lập tự động từ phần mềm Rót lúc {{ now()->format('H:i d/m/Y') }}. Doanh thu là số tiền thực nhận của các đơn hoàn thành, gồm cả các khoản ngoài Rót do chủ hộ tự nhập (nếu có).</div>
    </div>
</body>
</html>
