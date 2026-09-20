<?php

namespace Tests\Unit;

use App\Services\OrderRevenue;
use PHPUnit\Framework\TestCase;

class OrderRevenueTest extends TestCase
{
    public function test_don_khong_giam_gia_thi_ty_le_bang_1(): void
    {
        $this->assertSame(1.0, OrderRevenue::ratio(100000, 100000));
    }

    public function test_giam_gia_ca_don_va_diem_doi_lam_giam_ty_le(): void
    {
        // 100.000đ - giảm 10.000đ cả đơn - đổi điểm 10.000đ = thực nhận 80.000đ
        $this->assertEquals(0.8, OrderRevenue::ratio(100000, 80000));
    }

    public function test_don_0d_khong_chia_cho_0(): void
    {
        $this->assertSame(1.0, OrderRevenue::ratio(0, 0));
    }

    public function test_ty_le_bi_kep_trong_0_den_1(): void
    {
        $this->assertSame(1.0, OrderRevenue::ratio(100000, 120000)); // dữ liệu bất thường: total > subtotal
        $this->assertSame(0.0, OrderRevenue::ratio(100000, -5));
    }

    public function test_phan_bo_cac_dong_cong_lai_dung_bang_tong_thuc_nhan(): void
    {
        // Đơn 3 dòng: 45.000 + 30.000 + 25.000 = 100.000, thực nhận 87.500 (giảm 12.500 cả đơn).
        $lines = [45000, 30000, 25000];
        $sum = 0.0;
        foreach ($lines as $line) {
            $sum += OrderRevenue::allocate($line, 100000, 87500);
        }
        $this->assertEqualsWithDelta(87500.0, $sum, 0.0001);
        $this->assertEqualsWithDelta(39375.0, OrderRevenue::allocate(45000, 100000, 87500), 0.0001);
    }
}
