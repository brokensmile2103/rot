<?php

namespace App\Services;

/**
 * Dựng các dòng Sổ doanh thu bán hàng hóa, dịch vụ (mẫu S1a-HKD) từ số liệu đã
 * gom sẵn (v1.2.0). Class THUẦN để kiểm thử độc lập; việc đọc database nằm ở
 * TaxBookController.
 *
 * Theo hướng dẫn ghi sổ, doanh thu được ghi theo trình tự thời gian và có thể ghi
 * theo từng nghiệp vụ hoặc theo định kỳ — Rót ghi THEO NGÀY: mỗi ngày có bán 1
 * dòng tổng doanh thu bán hàng trong ngày, kèm các dòng doanh thu ngoài Rót (nếu
 * chủ quán tự nhập), và 1 dòng cộng cho từng tháng.
 *
 * Mọi số tiền được làm tròn về đồng NGAY TỪNG DÒNG rồi mới cộng dồn — để người đọc
 * cộng tay các dòng trên sổ ra đúng con số "Cộng tháng" và "Tổng cộng" (không lệch
 * vài đồng do làm tròn).
 */
class RevenueBookBuilder
{
    public const TYPE_SALES = 'sales';

    public const TYPE_EXTERNAL = 'external';

    public const TYPE_MONTH_TOTAL = 'month_total';

    /**
     * @param  array<int, array{date: string, orders: int, revenue: float|int|string}>  $dailySales  Doanh thu bán hàng mỗi ngày (ngày dạng Y-m-d)
     * @param  array<int, array{id?: int, date: string, amount: float|int|string, description?: string}>  $externals  Doanh thu ngoài Rót do chủ quán tự nhập
     * @return array{rows: array<int, array<string, mixed>>, total: float, salesTotal: float, externalTotal: float, orderCount: int}
     */
    public function build(array $dailySales, array $externals): array
    {
        $entries = [];
        foreach ($dailySales as $day) {
            $entries[] = [
                'sort' => [$day['date'], 0, 0],
                'row' => [
                    'type' => self::TYPE_SALES,
                    'date' => $day['date'],
                    'description' => 'Doanh thu bán hàng trong ngày ('.(int) $day['orders'].' đơn)',
                    'amount' => (float) round((float) $day['revenue']),
                    'orders' => (int) $day['orders'],
                    'id' => null,
                ],
            ];
        }
        foreach ($externals as $ext) {
            $description = trim((string) ($ext['description'] ?? ''));
            $entries[] = [
                'sort' => [$ext['date'], 1, (int) ($ext['id'] ?? 0)],
                'row' => [
                    'type' => self::TYPE_EXTERNAL,
                    'date' => $ext['date'],
                    'description' => 'Doanh thu ngoài Rót'.($description !== '' ? ': '.$description : ''),
                    'amount' => (float) round((float) $ext['amount']),
                    'orders' => null,
                    'id' => $ext['id'] ?? null,
                ],
            ];
        }

        // Cùng ngày: dòng bán hàng trước, rồi tới các khoản ngoài Rót theo thứ tự nhập.
        usort($entries, fn ($a, $b) => $a['sort'] <=> $b['sort']);

        $rows = [];
        $total = $salesTotal = $externalTotal = 0.0;
        $orderCount = 0;
        $currentMonth = null;
        $monthSum = 0.0;

        $flushMonth = function () use (&$rows, &$currentMonth, &$monthSum) {
            if ($currentMonth !== null) {
                $rows[] = [
                    'type' => self::TYPE_MONTH_TOTAL,
                    'date' => null,
                    'month' => $currentMonth,
                    'description' => 'Cộng tháng '.substr($currentMonth, 5, 2).'/'.substr($currentMonth, 0, 4),
                    'amount' => $monthSum,
                    'orders' => null,
                    'id' => null,
                ];
            }
        };

        foreach ($entries as $entry) {
            $row = $entry['row'];
            $month = substr($row['date'], 0, 7);

            if ($month !== $currentMonth) {
                $flushMonth();
                $currentMonth = $month;
                $monthSum = 0.0;
            }

            $rows[] = $row;
            $monthSum += $row['amount'];
            $total += $row['amount'];

            if ($row['type'] === self::TYPE_SALES) {
                $salesTotal += $row['amount'];
                $orderCount += $row['orders'];
            } else {
                $externalTotal += $row['amount'];
            }
        }
        $flushMonth();

        return [
            'rows' => $rows,
            'total' => $total,
            'salesTotal' => $salesTotal,
            'externalTotal' => $externalTotal,
            'orderCount' => $orderCount,
        ];
    }
}
