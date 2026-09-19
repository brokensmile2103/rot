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
