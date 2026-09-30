<?php

namespace Tests\Unit;

use App\Services\RevenueThresholdTracker;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

class RevenueThresholdTrackerTest extends TestCase
{
    private const T = 1_000_000_000.0;

    private function eval(float $ytd, float $recent, int $days, string $today = '2026-07-01'): array
    {
        return (new RevenueThresholdTracker())->evaluate($ytd, $recent, $days, new DateTimeImmutable($today), self::T);
    }

    public function test_thong_so_lich_trong_nam(): void
    {
        $r = $this->eval(0, 0, 0, '2026-07-01');
        $this->assertSame(182, $r['dayOfYear']);
        $this->assertSame(365, $r['daysInYear']);
        $this->assertSame(183, $r['daysLeft']);

        $leap = $this->eval(0, 0, 0, '2028-12-31');
        $this->assertSame(366, $leap['daysInYear']);
        $this->assertSame(0, $leap['daysLeft']);
    }

    public function test_it_du_lieu_gan_day_thi_khong_du_bao(): void
    {
        $r = $this->eval(50_000_000, 5_000_000, 6);
        $this->assertFalse($r['hasProjection']);
        $this->assertNull($r['projectedYear']);
        $this->assertNull($r['crossDate']);
        $this->assertSame('ok', $r['status']);
    }

    public function test_du_bao_nam_va_ngay_du_kien_cham_nguong(): void
    {
        // Từ đầu năm 500tr; 28 ngày gần đây 84tr => 3tr/ngày; còn 183 ngày => 500tr + 549tr = 1,049 tỷ (vượt).
        $r = $this->eval(500_000_000, 84_000_000, 28);
        $this->assertTrue($r['hasProjection']);
        $this->assertEquals(3_000_000.0, $r['avgDaily']);
        $this->assertEquals(1_049_000_000.0, $r['projectedYear']);
        $this->assertSame('will_exceed', $r['status']);
        // Cần thêm 500tr / 3tr = 166,67 → 167 ngày sau 01/07 = 15/12/2026 (còn trong năm).
        $this->assertSame('2026-12-15', $r['crossDate']);
    }

    public function test_khong_vuot_ngan_sach_nam_thi_khong_co_ngay_cham_nguong(): void
    {
        // 2tr/ngày × 183 = 366tr + 300tr = 666tr < 1 tỷ
        $r = $this->eval(300_000_000, 56_000_000, 28);
        $this->assertEquals(666_000_000.0, $r['projectedYear']);
        $this->assertNull($r['crossDate']);
        $this->assertSame('ok', $r['status']);
    }

    public function test_sap_cham_nguong_khi_tu_80_phan_tram(): void
    {
        // Đã 850tr nhưng đang bán rất ít nên dự báo chưa vượt: 100k/ngày × 183 = 18,3tr → 868,3tr
        $r = $this->eval(850_000_000, 2_800_000, 28);
        $this->assertSame('near', $r['status']);
        $this->assertEquals(85.0, $r['percent']);
    }

    public function test_da_vuot_nguong(): void
    {
        $r = $this->eval(1_020_000_000, 84_000_000, 28);
        $this->assertSame('exceeded', $r['status']);
        $this->assertEquals(20_000_000.0, $r['over']);
        $this->assertEquals(0.0, $r['remaining']);
        $this->assertNull($r['crossDate']); // đã vượt rồi, không còn "ngày dự kiến"
    }

    public function test_bang_dung_nguong_chua_tinh_la_vuot(): void
    {
        $r = $this->eval(1_000_000_000, 84_000_000, 28);
        $this->assertNotSame('exceeded', $r['status']);
        $this->assertSame('2026-07-02', $r['crossDate']); // đơn kế tiếp sẽ vượt
    }

    public function test_cuoi_nam_khong_con_ngay_de_du_bao(): void
    {
        $r = $this->eval(900_000_000, 84_000_000, 28, '2026-12-31');
        $this->assertSame(0, $r['daysLeft']);
        $this->assertEquals(900_000_000.0, $r['projectedYear']);
        $this->assertNull($r['crossDate']);
    }
}
