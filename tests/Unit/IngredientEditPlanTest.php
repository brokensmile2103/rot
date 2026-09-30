<?php

namespace Tests\Unit;

use App\Services\IngredientEditPlan;
use PHPUnit\Framework\TestCase;

class IngredientEditPlanTest extends TestCase
{
    public function test_chi_doi_ten_khong_dung_toi_ton_kho_dang_co_trong_db(): void
    {
        // Form mở lúc tồn = 500; trong lúc mở có đơn bán làm DB còn 470. Người dùng chỉ đổi tên
        // → form vẫn gửi 500 (đúng bằng giá trị gốc) → KHÔNG được ghi đè 470 bằng 500.
        $plan = IngredientEditPlan::make(
            dbStock: 470, dbCost: 150,
            submittedStock: 500, submittedCost: 150,
            originalStock: 500, originalCost: 150,
        );
        $this->assertSame(470.0, $plan['stock']);
        $this->assertFalse($plan['changed']);
    }

    public function test_sua_ton_kho_theo_kiem_ke_thi_ghi_nhan_thay_doi(): void
    {
        $plan = IngredientEditPlan::make(470, 150, 450, 150, 500, 150);
        $this->assertSame(450.0, $plan['stock']);
        $this->assertTrue($plan['stockChanged']);
        $this->assertFalse($plan['costChanged']);
        $this->assertTrue($plan['changed']);
        $this->assertSame(150.0, $plan['cost']);
    }

    public function test_sua_gia_von_thi_chi_gia_von_thay_doi(): void
    {
        $plan = IngredientEditPlan::make(500, 150, 500, 165.5, 500, 150);
        $this->assertFalse($plan['stockChanged']);
        $this->assertTrue($plan['costChanged']);
        $this->assertSame(165.5, $plan['cost']);
    }

    public function test_sua_ca_hai(): void
    {
        $plan = IngredientEditPlan::make(500, 150, 0, 0, 500, 150);
        $this->assertTrue($plan['stockChanged']);
        $this->assertTrue($plan['costChanged']);
        $this->assertSame(0.0, $plan['stock']);
        $this->assertSame(0.0, $plan['cost']);
    }

    public function test_go_lai_dung_so_dang_co_trong_db_thi_khong_coi_la_thay_doi(): void
    {
        // Nguoi dung sua 500 -> 470 nhung DB da la 470 (do don ban) => khong thay doi gi.
        $plan = IngredientEditPlan::make(470, 150, 470, 150, 500, 150);
        $this->assertFalse($plan['changed']);
        $this->assertSame(470.0, $plan['stock']);
    }

    public function test_thieu_gia_tri_goc_thi_so_sanh_thang_voi_db(): void
    {
        $same = IngredientEditPlan::make(500, 150, 500, 150);
        $this->assertFalse($same['changed']);

        $diff = IngredientEditPlan::make(500, 150, 480, 150);
        $this->assertTrue($diff['stockChanged']);
        $this->assertSame(480.0, $diff['stock']);
    }

    public function test_lam_tron_dung_do_chinh_xac_cot(): void
    {
        // Tồn kho: 2 chữ số thập phân; giá vốn: 4 chữ số.
        $plan = IngredientEditPlan::make(500, 150, 499.996, 150.00004, 500, 150);
        // 499.996 -> 500.00 (bằng DB) ; 150.00004 -> 150.0000 (bằng DB) => không đổi gì
        $this->assertFalse($plan['changed']);

        $plan = IngredientEditPlan::make(500, 150, 499.994, 150.00006, 500, 150);
        $this->assertTrue($plan['stockChanged']);
        $this->assertSame(499.99, $plan['stock']);
        $this->assertTrue($plan['costChanged']);
        $this->assertSame(150.0001, $plan['cost']);
    }
}
