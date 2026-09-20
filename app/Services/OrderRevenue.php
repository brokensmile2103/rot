<?php

namespace App\Services;

/**
 * Doanh thu THỰC NHẬN của đơn hàng (v1.2.0).
 *
 * Một đơn có 3 lớp giảm trừ: giảm giá từng dòng món (đã nằm sẵn trong
 * OrderItem::line_total), giảm giá CẢ ĐƠN (Order::discount_amount) và điểm thưởng
 * khách đổi (Order::points_redeemed_value). Số tiền khách THỰC SỰ trả — cũng là số
 * tiền nhân viên đối chiếu khi chốt ca — là Order::total.
 *
 * Trước v1.2.0 trang Báo cáo cộng thẳng line_total nên bỏ sót 2 lớp giảm trừ sau
 * (doanh thu bị báo CAO hơn thực tế mỗi khi có giảm giá cả đơn/đổi điểm) và lệch
 * với số Chốt ca. Từ v1.2.0 doanh thu = tổng Order::total.
 *
 * Với bảng theo từng món, phần giảm trừ cấp đơn được PHÂN BỔ theo tỷ lệ giá trị
 * dòng (ratio = total ÷ subtotal) để tổng doanh thu của các dòng khớp CHÍNH XÁC
 * với tổng doanh thu — nếu không, Menu Engineering sẽ đánh giá lãi từng món cao
 * hơn thực tế.
 */
class OrderRevenue
{
    /**
     * Tỷ lệ thực nhận của 1 đơn: total ÷ subtotal, kẹp trong [0, 1] (giảm giá chỉ
     * làm giảm, không thể làm tăng). subtotal = 0 (đơn 0đ) → 1 để không chia cho 0.
     */
    public static function ratio(float $subtotal, float $total): float
    {
        if ($subtotal <= 0) {
            return 1.0;
        }

        return max(0.0, min(1.0, $total / $subtotal));
    }

    /** Phần doanh thu thực nhận thuộc về 1 dòng món có giá trị $lineTotal trong đơn ($subtotal, $total). */
    public static function allocate(float $lineTotal, float $subtotal, float $total): float
    {
        return $lineTotal * self::ratio($subtotal, $total);
    }
}
