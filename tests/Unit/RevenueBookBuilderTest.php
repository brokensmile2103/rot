<?php

namespace Tests\Unit;

use App\Services\RevenueBookBuilder;
use PHPUnit\Framework\TestCase;

class RevenueBookBuilderTest extends TestCase
{
    private function build(array $sales, array $ext = []): array
    {
        return (new RevenueBookBuilder())->build($sales, $ext);
    }

    public function test_khong_co_du_lieu_thi_so_rong(): void
    {
        $book = $this->build([]);
        $this->assertSame([], $book['rows']);
        $this->assertSame(0.0, $book['total']);
    }

    public function test_moi_ngay_mot_dong_va_moi_thang_mot_dong_cong(): void
    {
        $book = $this->build([
            ['date' => '2026-01-30', 'orders' => 10, 'revenue' => 500000],
            ['date' => '2026-01-31', 'orders' => 5, 'revenue' => 250000],
            ['date' => '2026-02-01', 'orders' => 8, 'revenue' => 400000],
        ]);

        $types = array_column($book['rows'], 'type');
        $this->assertSame(['sales', 'sales', 'month_total', 'sales', 'month_total'], $types);
        $this->assertSame('Cộng tháng 01/2026', $book['rows'][2]['description']);
        $this->assertSame(750000.0, $book['rows'][2]['amount']);
        $this->assertSame(400000.0, $book['rows'][4]['amount']);
        $this->assertSame(1150000.0, $book['total']);
        $this->assertSame(23, $book['orderCount']);
        $this->assertSame('Doanh thu bán hàng trong ngày (10 đơn)', $book['rows'][0]['description']);
    }

    public function test_doanh_thu_ngoai_hoat_dong_cung_ngay_xep_sau_dong_ban_hang(): void
    {
        $book = $this->build(
            [['date' => '2026-03-05', 'orders' => 4, 'revenue' => 200000]],
            [
                ['id' => 2, 'date' => '2026-03-05', 'amount' => 90000, 'description' => 'Giao đồ ăn'],
                ['id' => 1, 'date' => '2026-03-05', 'amount' => 60000, 'description' => ''],
                ['id' => 3, 'date' => '2026-03-01', 'amount' => 10000, 'description' => 'Bán sỉ'],
            ]
        );

        $this->assertSame(
            ['Doanh thu ngoài Rót: Bán sỉ', 'Doanh thu bán hàng trong ngày (4 đơn)', 'Doanh thu ngoài Rót', 'Doanh thu ngoài Rót: Giao đồ ăn', 'Cộng tháng 03/2026'],
            array_column($book['rows'], 'description')
        );
        $this->assertSame(360000.0, $book['total']);
        $this->assertSame(200000.0, $book['salesTotal']);
        $this->assertSame(160000.0, $book['externalTotal']);
        $this->assertSame(4, $book['orderCount']);
    }

    public function test_lam_tron_tung_dong_de_cong_tay_khop_tong(): void
    {
        $book = $this->build([
            ['date' => '2026-04-01', 'orders' => 1, 'revenue' => 10000.4],
            ['date' => '2026-04-02', 'orders' => 1, 'revenue' => 10000.4],
            ['date' => '2026-04-03', 'orders' => 1, 'revenue' => 10000.4],
        ]);
        $this->assertSame(30000.0, $book['total']);            // 3 × 10.000 (không phải 30.001,2 → 30.001)
        $this->assertSame(30000.0, $book['rows'][3]['amount']);
    }

    public function test_dong_ngoai_he_thong_giu_lai_id_de_xoa(): void
    {
        $book = $this->build([], [['id' => 7, 'date' => '2026-05-05', 'amount' => 1000, 'description' => 'x']]);
        $this->assertSame(7, $book['rows'][0]['id']);
        $this->assertSame('external', $book['rows'][0]['type']);
    }
}
