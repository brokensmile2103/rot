<?php

namespace Tests\Unit;

use App\Services\StockAdjustmentPresenter as P;
use PHPUnit\Framework\TestCase;

class StockAdjustmentPresenterTest extends TestCase
{
    public function test_chenh_lech_ton_kho_lam_tron_2_chu_so(): void
    {
        $this->assertSame(-50.0, P::delta(500, 450));
        $this->assertSame(0.01, P::delta(1.005, 1.015)); // sai số dấu phẩy động không làm lệch kết quả
        $this->assertSame(0.0, P::delta(300, 300));
    }

    public function test_gia_von_doi_hay_khong_theo_do_chinh_xac_cot(): void
    {
        $this->assertFalse(P::costChanged(150.0, 150.00004));
        $this->assertTrue(P::costChanged(150.0, 150.0001));
    }

    public function test_gia_tri_thieu_hut_chi_khi_ton_giam_va_co_gia_von(): void
    {
        $this->assertEquals(7500.0, P::shortageValue(500, 450, 150));   // 50 × 150
        $this->assertNull(P::shortageValue(450, 500, 150));             // tồn tăng
        $this->assertNull(P::shortageValue(500, 500, 150));             // không đổi
        $this->assertNull(P::shortageValue(500, 450, 0));               // chưa có giá vốn để quy đổi
    }

    public function test_dinh_dang_khi_ton_giam(): void
    {
        $n = P::numbers(1920.5, 1800, 150, 150, 'g', true);

        $this->assertTrue($n['stock_changed']);
        $this->assertSame('1.920,5 g', $n['stock_before']);
        $this->assertSame('1.800 g', $n['stock_after']);
        $this->assertSame('−120,5 g', $n['stock_delta']);
        $this->assertSame('down', $n['delta_sign']);
        $this->assertFalse($n['cost_changed']);
        $this->assertSame('≈ 18.075đ', $n['shortage_value']);   // 120,5 × 150
        $this->assertSame('150đ/g', $n['cost_before']);
    }

    public function test_dinh_dang_khi_ton_tang(): void
    {
        $n = P::numbers(100, 130.25, 150, 150, 'ml', true);
        $this->assertSame('+30,25 ml', $n['stock_delta']);
        $this->assertSame('up', $n['delta_sign']);
        $this->assertNull($n['shortage_value']);
    }

    public function test_ly_do_sua_so_lieu_khong_hien_gia_tri_thieu_hut(): void
    {
        // Sửa số liệu nhập sai (physical = false): là chỉnh lại sổ sách, không phải hàng thật mất đi.
        $n = P::numbers(500, 450, 150, 150, 'g', false);
        $this->assertSame('down', $n['delta_sign']);
        $this->assertNull($n['shortage_value']);
    }

    public function test_chi_doi_gia_von_thi_khong_co_thay_doi_ton_kho(): void
    {
        $n = P::numbers(500, 500, 0.25, 0.2504, 'g', true);
        $this->assertFalse($n['stock_changed']);
        $this->assertSame('flat', $n['delta_sign']);
        $this->assertTrue($n['cost_changed']);
        $this->assertSame('0,25đ/g', $n['cost_before']);
        $this->assertSame('0,2504đ/g', $n['cost_after']);
        $this->assertNull($n['shortage_value']);
    }
}
