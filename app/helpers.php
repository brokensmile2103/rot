<?php

if (! function_exists('money')) {
    /**
     * Định dạng số tiền theo chuẩn Việt Nam — dấu CHẤM ngăn cách hàng nghìn,
     * KHÔNG dùng dấu phẩy như number_format() mặc định của PHP (kiểu Mỹ).
     * Dùng hàm này thay cho number_format() ở MỌI nơi hiển thị tiền trong app.
     */
    function money($value, int $decimals = 0): string
    {
        return number_format((float) $value, $decimals, ',', '.');
    }
}

if (! function_exists('quantity')) {
    /**
     * Định dạng SỐ LƯỢNG (kg, g, ml...) theo chuẩn Việt Nam — TỰ BỎ số 0 thừa
     * ở phần thập phân thay vì luôn ép đủ N chữ số như number_format() thường
     * làm (VD: 1920 hiện "1.920" thay vì "1.920,00"; 1920.5 vẫn hiện đúng
     * "1.920,5"). Khác với money() — tiền luôn muốn số nguyên, còn số lượng
     * nguyên liệu nhiều lúc cần phần thập phân thật (0.5g, 1.25kg...).
     */
    function quantity($value, int $maxDecimals = 2): string
    {
        $rounded = round((float) $value, $maxDecimals);
        $trimmed = rtrim(rtrim(number_format($rounded, $maxDecimals, '.', ''), '0'), '.');

        [$intPart, $decPart] = array_pad(explode('.', $trimmed), 2, null);
        $intFormatted = number_format((float) $intPart, 0, '', '.');

        return $decPart !== null ? $intFormatted.','.$decPart : $intFormatted;
    }
}

if (! function_exists('money_compact')) {
    /**
     * Số tiền RÚT GỌN cho những ô rất hẹp (VD: ô heatmap giờ cao điểm trên điện
     * thoại): 125.000 → "125k", 1.250.000 → "1,3tr", 12.000.000 → "12tr". CHỈ để
     * hiển thị gọn — chỗ nào cần số tiền chính xác vẫn dùng money().
     */
    function money_compact($value): string
    {
        $amount = (float) $value;

        // >= 999.500 làm tròn theo nghìn sẽ ra "1.000k" — chuyển sang đơn vị triệu cho gọn.
        if (abs($amount) >= 999_500) {
            $millions = round($amount / 1_000_000, 1);

            return rtrim(rtrim(number_format($millions, 1, ',', '.'), '0'), ',').'tr';
        }

        if (abs($amount) >= 1_000) {
            return number_format(round($amount / 1_000), 0, ',', '.').'k';
        }

        return number_format(round($amount), 0, ',', '.');
    }
}
