<?php

/**
 * Thông số thuế cho hộ kinh doanh (v1.2.0) — dùng cho trang "Sổ doanh thu & ngưỡng thuế".
 *
 * Tách ra file cấu hình (không viết cứng trong code) vì quy định thuế thay đổi
 * rất nhanh: ngưỡng doanh thu miễn thuế của hộ kinh doanh đã đổi từ 500 triệu lên
 * 1 tỷ chỉ trong vài tháng đầu năm 2026. Khi luật đổi lần nữa chỉ cần sửa số này
 * (hoặc đặt TAX_EXEMPT_THRESHOLD trong .env) — không phải sửa mã nguồn.
 */
return [
    // Doanh thu năm (đồng) từ mức này TRỞ XUỐNG thì hộ kinh doanh không phải nộp thuế GTGT, TNCN.
    // Vượt mức này thì chịu thuế và phải dùng hoá đơn điện tử khởi tạo từ máy tính tiền.
    'exempt_revenue_threshold' => (float) env('TAX_EXEMPT_THRESHOLD', 1_000_000_000),

    // Căn cứ pháp lý đang áp dụng cho con số trên — hiện ra trên trang để người dùng đối chiếu.
    'threshold_basis' => env('TAX_THRESHOLD_BASIS', 'Nghị định 141/2026/NĐ-CP (sửa đổi Nghị định 68/2026/NĐ-CP)'),

    // Tháng/năm cập nhật căn cứ trên — cho người dùng biết số liệu quy định còn mới hay đã cũ.
    'threshold_basis_updated' => env('TAX_THRESHOLD_BASIS_UPDATED', '07/2026'),
];
