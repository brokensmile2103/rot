<?php

namespace Tests\Unit;

use App\Services\PeakHoursCalculator;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

/**
 * Lưu ý lịch dùng trong test: 14/09/2026 là Thứ Hai, nên 17/08 → 13/09 là đúng
 * 4 tuần trọn vẹn (mỗi thứ xuất hiện đúng 4 lần).
 */
class PeakHoursCalculatorTest extends TestCase
{
    private function sale(string $datetime, float $revenue = 30000): array
    {
        return [new DateTimeImmutable($datetime), $revenue];
    }

    private function calc(array $sales, string $from = '2026-08-17', string $to = '2026-09-13'): array
    {
        return (new PeakHoursCalculator())->compute($sales, new DateTimeImmutable($from), new DateTimeImmutable($to));
    }

    public function test_dem_so_lan_xuat_hien_cua_tung_thu(): void
    {
        $result = $this->calc([]);
        $this->assertSame(28, $result['dayCount']);
        $this->assertSame([1 => 4, 2 => 4, 3 => 4, 4 => 4, 5 => 4, 6 => 4, 7 => 4], $result['occurrences']);
    }

    public function test_khoang_khong_chia_het_cho_7_ngay(): void
    {
        // Thứ Hai 07/09 → Thứ Tư 16/09/2026: 10 ngày.
        $result = $this->calc([], '2026-09-07', '2026-09-16');
        $this->assertSame(10, $result['dayCount']);
        $this->assertSame([1 => 2, 2 => 2, 3 => 2, 4 => 1, 5 => 1, 6 => 1, 7 => 1], $result['occurrences']);
    }

    public function test_khong_co_don_nao_thi_hasData_false(): void
    {
        $result = $this->calc([]);
        $this->assertFalse($result['hasData']);
        $this->assertSame(0, $result['totalOrders']);
    }

    public function test_o_la_trung_binh_moi_lan_thu_do_xuat_hien(): void
    {
        // 10 đơn vào các Thứ Bảy lúc 8h (Thứ Bảy trong khoảng: 22/08, 29/08, 05/09, 12/09 → 4 lần).
        $sales = [];
        foreach (['2026-08-22', '2026-08-29', '2026-09-05'] as $d) {
            $sales[] = $this->sale("$d 08:10:00", 20000);
            $sales[] = $this->sale("$d 08:40:00", 30000);
            $sales[] = $this->sale("$d 08:59:59", 40000);
        }
        $sales[] = $this->sale('2026-09-12 08:00:00', 50000); // tổng 10 đơn, 4 thứ Bảy

        $result = $this->calc($sales);
        $orders = $result['metrics']['orders'];
        $revenue = $result['metrics']['revenue'];

        $this->assertTrue($result['hasData']);
        $this->assertEquals(2.5, $orders['cells'][6][8]);                 // 10 đơn ÷ 4 thứ Bảy
        $this->assertEquals((3 * 90000 + 50000) / 4, $revenue['cells'][6][8]);
        $this->assertEquals(0.0, $orders['cells'][1][8]);                 // Thứ Hai lúc 8h: không có đơn
    }

    public function test_gio_va_thu_lay_dung_o_bien_ngay(): void
    {
        // 23:59:59 Thứ Bảy 12/09 và 00:00:00 Chủ Nhật 13/09 phải vào 2 ô khác nhau.
        $result = $this->calc([
            $this->sale('2026-09-12 23:59:59'),
            $this->sale('2026-09-13 00:00:00'),
        ]);
        $cells = $result['metrics']['orders']['cells'];
        $this->assertEquals(0.25, $cells[6][23]);
        $this->assertEquals(0.25, $cells[7][0]);
    }

    public function test_don_ngoai_khoang_xem_bi_bo_qua(): void
    {
        $result = $this->calc([
            $this->sale('2026-08-16 09:00:00'), // trước khoảng
            $this->sale('2026-09-14 09:00:00'), // sau khoảng
            $this->sale('2026-08-17 09:00:00'), // trong khoảng (Thứ Hai đầu tiên)
        ]);
        $this->assertSame(1, $result['totalOrders']);
    }

    public function test_thu_chua_xuat_hien_trong_khoang_ngan_thi_o_la_null(): void
    {
        // Chỉ 3 ngày Thứ Hai → Thứ Tư: Thứ Năm..Chủ Nhật chưa xuất hiện lần nào.
        $result = $this->calc([$this->sale('2026-09-07 09:00:00')], '2026-09-07', '2026-09-09');
        $cells = $result['metrics']['orders']['cells'];
        $this->assertEquals(1.0, $cells[1][9]);
        $this->assertNull($cells[4][9]);
        $this->assertNull($result['metrics']['orders']['dayAvg'][7]);
    }

    public function test_cot_ca_tuan_va_hang_ca_ngay(): void
    {
        // 8 đơn: 4 đơn Thứ Hai 9h, 4 đơn Thứ Ba 9h — trong 28 ngày.
        $sales = [];
        foreach (['2026-08-17', '2026-08-24', '2026-08-31', '2026-09-07'] as $monday) {
            $sales[] = $this->sale("$monday 09:00:00");
            $tuesday = date('Y-m-d', strtotime("$monday +1 day"));
            $sales[] = $this->sale("$tuesday 09:30:00");
        }
        $m = $this->calc($sales)['metrics']['orders'];

        $this->assertEquals(8 / 28, $m['hourAvg'][9]);   // 8 đơn ÷ 28 ngày
        $this->assertEquals(1.0, $m['dayAvg'][1]);       // 4 đơn ÷ 4 thứ Hai
        $this->assertEquals(1.0, $m['dayAvg'][2]);
        $this->assertEquals(0.0, $m['dayAvg'][3]);
        $this->assertSame(9, $m['busiestHour']);
        $this->assertSame(1, $m['busiestDow']);          // hoà giữa Thứ Hai/Thứ Ba → chọn thứ nhỏ hơn
    }

    public function test_top_khung_gio_vang_sap_giam_dan_va_hoa_thi_uu_tien_thu_roi_gio_nho(): void
    {
        $sales = [];
        // Thứ Sáu 18h: 8 đơn (2/lần), Thứ Bảy 8h: 8 đơn (2/lần) → hoà; Chủ Nhật 10h: 4 đơn (1/lần)
        foreach (['2026-08-21', '2026-08-28', '2026-09-04', '2026-09-11'] as $friday) {
            $sales[] = $this->sale("$friday 18:00:00");
            $sales[] = $this->sale("$friday 18:30:00");
            $sat = date('Y-m-d', strtotime("$friday +1 day"));
            $sales[] = $this->sale("$sat 08:00:00");
            $sales[] = $this->sale("$sat 08:30:00");
            $sun = date('Y-m-d', strtotime("$friday +2 day"));
            $sales[] = $this->sale("$sun 10:00:00");
        }
        $peaks = $this->calc($sales)['metrics']['orders']['peaks'];

        $this->assertCount(3, $peaks);
        $this->assertSame([5, 18], [$peaks[0]['dow'], $peaks[0]['hour']]); // hoà 2,0 → thứ nhỏ hơn (Thứ Sáu = 5)
        $this->assertSame([6, 8], [$peaks[1]['dow'], $peaks[1]['hour']]);
        $this->assertSame([7, 10], [$peaks[2]['dow'], $peaks[2]['hour']]);
        $this->assertEquals(2.0, $peaks[0]['value']);
    }

    public function test_it_don_thi_khong_cat_gio_o_hai_dau(): void
    {
        // Dưới 100 đơn: ngưỡng cắt = 1 đơn → 1 đơn lúc 2h sáng vẫn giữ nguyên.
        $result = $this->calc([
            $this->sale('2026-08-22 02:00:00'),
            $this->sale('2026-08-22 09:00:00'),
            $this->sale('2026-08-22 20:00:00'),
        ]);
        $this->assertSame(range(2, 20), $result['hours']);
        $this->assertSame(0, $result['hiddenOrders']);
    }

    public function test_nhieu_don_thi_cat_gio_le_loi_o_hai_dau_va_ghi_nhan_so_don_bi_an(): void
    {
        $sales = [];
        for ($i = 0; $i < 300; $i++) {
            $sales[] = $this->sale('2026-08-22 '.sprintf('%02d', 8 + ($i % 4)).':00:00'); // 8h..11h
        }
        $sales[] = $this->sale('2026-08-23 02:00:00');  // 1 đơn lẻ lúc 2h (< 1% của 302)
        $sales[] = $this->sale('2026-08-23 23:00:00');  // 1 đơn lẻ lúc 23h
        $result = $this->calc($sales);

        $this->assertSame([8, 9, 10, 11], $result['hours']);
        $this->assertSame(2, $result['hiddenOrders']);
        $this->assertSame(302, $result['totalOrders']);
    }

    public function test_hang_ca_ngay_van_tinh_ca_gio_bi_an(): void
    {
        $sales = [];
        for ($i = 0; $i < 300; $i++) {
            $sales[] = $this->sale('2026-08-22 09:00:00'); // Thứ Bảy 9h
        }
        $sales[] = $this->sale('2026-08-22 02:00:00');     // Thứ Bảy 2h — bị ẩn khỏi bảng nhưng vẫn là đơn của thứ Bảy
        $result = $this->calc($sales);

        $this->assertSame([9], $result['hours']);
        $this->assertEquals(301 / 4, $result['metrics']['orders']['dayAvg'][6]);
    }

    public function test_doanh_thu_dung_chung_cach_tinh_voi_so_don(): void
    {
        $sales = [
            $this->sale('2026-08-22 09:00:00', 100000),
            $this->sale('2026-08-29 09:00:00', 60000),
        ];
        $rev = $this->calc($sales)['metrics']['revenue'];
        $this->assertEquals(40000.0, $rev['cells'][6][9]);          // 160.000 ÷ 4 thứ Bảy
        $this->assertEquals(160000 / 28, $rev['hourAvg'][9]);
        $this->assertEquals(40000.0, $rev['max']);
    }

    public function test_doanh_thu_null_hoac_chuoi_so_van_an_toan(): void
    {
        $result = $this->calc([
            [new DateTimeImmutable('2026-08-22 09:00:00'), null],
            [new DateTimeImmutable('2026-08-22 09:10:00'), '25000.50'],
        ]);
        $this->assertSame(2, $result['totalOrders']);
        $this->assertEquals(25000.50, $result['totalRevenue']);
    }

    public function test_khoang_xem_dao_nguoc_khong_gay_loi(): void
    {
        $result = $this->calc([$this->sale('2026-08-22 09:00:00')], '2026-09-13', '2026-08-17');
        $this->assertSame(0, $result['dayCount']);
        $this->assertFalse($result['hasData']);
    }
}
