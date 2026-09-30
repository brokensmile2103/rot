<?php

namespace App\Services;

use DateTimeInterface;

/**
 * Tính bảng nhiệt (heatmap) GIỜ CAO ĐIỂM × THỨ TRONG TUẦN (v1.1.3).
 *
 * Cố tình là class THUẦN (chỉ nhận mảng + DateTimeInterface, không đụng
 * database/Laravel/Carbon) để kiểm thử độc lập được toàn bộ phép tính — việc
 * đọc dữ liệu đơn hàng nằm ở ReportController::peakHours().
 *
 * Mỗi ô (thứ × giờ) = TRUNG BÌNH mỗi lần thứ đó xuất hiện trong khoảng thời
 * gian đang xem: tổng số đơn (hoặc doanh thu) của ô ÷ số ngày thuộc thứ đó.
 * VD: 8 tuần, 40 đơn dồn vào "Thứ 7, 8h-9h" → 40 ÷ 8 = 5,0 đơn mỗi thứ 7 lúc
 * 8h. Dùng trung bình (không dùng tổng) để so sánh công bằng giữa các ô khi
 * khoảng xem không chia hết cho 7 ngày, hoặc quán mới mở giữa chừng.
 *
 * Số ngày chia ($occurrences) TÍNH CẢ những ngày quán nghỉ không bán gì — cùng
 * cách với dự đoán doanh thu ở ReportController::dailyRevenueSeries() (ngày
 * nghỉ = 0đ, không bị loại khỏi mẫu), để 2 chỗ nhìn ra cùng 1 "nhịp" của quán.
 */
class PeakHoursCalculator
{
    public const METRIC_ORDERS = 'orders';

    public const METRIC_REVENUE = 'revenue';

    /**
     * Giờ nằm ở 2 ĐẦU khung giờ mà có ít hơn 1% tổng số đơn (VD: 1 đơn bán thử
     * lúc 2h sáng) thì ẩn khỏi bảng — nếu không, 1 đơn lẻ loi sẽ kéo bảng dài
     * thêm nhiều hàng trống. Số đơn bị ẩn được trả về ở `hiddenOrders` để giao
     * diện ghi chú minh bạch, không âm thầm bỏ mất dữ liệu.
     */
    public const EDGE_HOUR_MIN_SHARE = 0.01;

    /** Số ô cao điểm nhất hiển thị ở phần "Khung giờ vàng". */
    public const TOP_PEAKS = 3;

    /**
     * @param  iterable<array{0: DateTimeInterface, 1: float|int|string|null}>  $sales  Mỗi phần tử = [thời điểm hoàn thành đơn, doanh thu của đơn]
     * @param  DateTimeInterface  $from  Ngày đầu của khoảng xem (tính cả ngày này)
     * @param  DateTimeInterface  $to  Ngày cuối của khoảng xem (tính cả ngày này)
     */
    public function compute(iterable $sales, DateTimeInterface $from, DateTimeInterface $to): array
    {
        $fromDate = $from->format('Y-m-d');
        $toDate = $to->format('Y-m-d');

        [$occurrences, $dayCount] = $this->countWeekdayOccurrences($fromDate, $toDate);

        $orders = [];   // [thứ ISO 1..7][giờ 0..23] => số đơn
        $revenue = [];  // [thứ ISO 1..7][giờ 0..23] => doanh thu
        $totalOrders = 0;
        $totalRevenue = 0.0;

        foreach ($sales as [$completedAt, $orderRevenue]) {
            $date = $completedAt->format('Y-m-d');
            if ($date < $fromDate || $date > $toDate) {
                continue; // ngoài khoảng đang xem (phòng thủ — truy vấn ở controller đã lọc sẵn)
            }

            $dow = (int) $completedAt->format('N');
            $hour = (int) $completedAt->format('G');
            $amount = (float) $orderRevenue;

            $orders[$dow][$hour] = ($orders[$dow][$hour] ?? 0) + 1;
            $revenue[$dow][$hour] = ($revenue[$dow][$hour] ?? 0.0) + $amount;
            $totalOrders++;
            $totalRevenue += $amount;
        }

        $base = [
            'dayCount' => $dayCount,
            'occurrences' => $occurrences,
            'totalOrders' => $totalOrders,
            'totalRevenue' => $totalRevenue,
        ];

        if ($totalOrders === 0) {
            return $base + ['hasData' => false];
        }

        [$firstHour, $lastHour, $hiddenOrders] = $this->visibleHourRange($orders, $totalOrders);
        $hours = range($firstHour, $lastHour);

        return $base + [
            'hasData' => true,
            'hours' => $hours,
            'hiddenOrders' => $hiddenOrders,
            'metrics' => [
                self::METRIC_ORDERS => $this->buildMetric($orders, $occurrences, $dayCount, $hours),
                self::METRIC_REVENUE => $this->buildMetric($revenue, $occurrences, $dayCount, $hours),
            ],
        ];
    }

    /**
     * Đếm mỗi thứ (ISO 1=Thứ 2 .. 7=Chủ nhật) xuất hiện bao nhiêu lần trong
     * [from, to], và tổng số ngày. Duyệt từng ngày theo chuỗi Y-m-d qua
     * timestamp giữa trưa (12:00) để không bao giờ lệch ngày do giờ mùa hè.
     */
    private function countWeekdayOccurrences(string $fromDate, string $toDate): array
    {
        $occurrences = array_fill(1, 7, 0);
        $dayCount = 0;

        if ($fromDate > $toDate) {
            return [$occurrences, 0];
        }

        $cursor = strtotime($fromDate.' 12:00:00');
        $end = strtotime($toDate.' 12:00:00');
        while ($cursor <= $end) {
            $occurrences[(int) date('N', $cursor)]++;
            $dayCount++;
            $cursor = strtotime('+1 day', $cursor);
        }

        return [$occurrences, $dayCount];
    }

    /**
     * Khung giờ hiển thị = từ giờ đầu tiên đến giờ cuối cùng có "đủ đơn" (xem
     * EDGE_HOUR_MIN_SHARE). Chỉ cắt ở 2 ĐẦU — giờ trống nằm GIỮA khung vẫn giữ
     * nguyên để bảng liền mạch.
     *
     * @return array{0: int, 1: int, 2: int} [giờ đầu, giờ cuối, số đơn bị ẩn]
     */
    private function visibleHourRange(array $orders, int $totalOrders): array
    {
        $perHour = array_fill(0, 24, 0);
        foreach ($orders as $byHour) {
            foreach ($byHour as $hour => $count) {
                $perHour[$hour] += $count;
            }
        }

        $threshold = max(1, $totalOrders * self::EDGE_HOUR_MIN_SHARE);

        $first = null;
        $last = null;
        foreach ($perHour as $hour => $count) {
            if ($count >= $threshold) {
                $first ??= $hour;
                $last = $hour;
            }
        }

        // Không thể xảy ra (giờ đông nhất luôn ≥ 1/24 ≈ 4,2% > 1% tổng đơn), giữ lại như lớp phòng thủ.
        if ($first === null) {
            $first = min(array_keys(array_filter($perHour)));
            $last = max(array_keys(array_filter($perHour)));
        }

        $hidden = 0;
        foreach ($perHour as $hour => $count) {
            if ($hour < $first || $hour > $last) {
                $hidden += $count;
            }
        }

        return [$first, $last, $hidden];
    }

    /**
     * Dựng toàn bộ số liệu cho 1 chỉ số (số đơn HOẶC doanh thu) từ bảng tổng
     * [thứ][giờ] — cùng 1 hàm cho cả 2 chỉ số nên 2 chế độ xem luôn nhất quán.
     */
    private function buildMetric(array $totals, array $occurrences, int $dayCount, array $hours): array
    {
        $cells = [];
        $max = 0.0;
        $hourSums = array_fill_keys($hours, 0.0);

        for ($dow = 1; $dow <= 7; $dow++) {
            foreach ($hours as $hour) {
                $sum = (float) ($totals[$dow][$hour] ?? 0);
                $hourSums[$hour] += $sum;

                // Thứ chưa xuất hiện lần nào trong khoảng xem (khoảng < 7 ngày) → null,
                // giao diện hiện "–" thay vì số 0 gây hiểu nhầm là "vắng khách".
                $avg = $occurrences[$dow] > 0 ? $sum / $occurrences[$dow] : null;
                $cells[$dow][$hour] = $avg;
                if ($avg !== null && $avg > $max) {
                    $max = $avg;
                }
            }
        }

        // Trung bình MỖI NGÀY cho từng giờ (gộp cả 7 thứ) — cột "Cả tuần".
        $hourAvg = [];
        foreach ($hours as $hour) {
            $hourAvg[$hour] = $dayCount > 0 ? $hourSums[$hour] / $dayCount : 0.0;
        }

        // Trung bình MỖI NGÀY cho từng thứ (cả ngày, gồm cả giờ bị ẩn ở 2 đầu) — hàng "Cả ngày".
        $dayAvg = [];
        for ($dow = 1; $dow <= 7; $dow++) {
            $daySum = array_sum($totals[$dow] ?? []);
            $dayAvg[$dow] = $occurrences[$dow] > 0 ? $daySum / $occurrences[$dow] : null;
        }

        return [
            'cells' => $cells,
            'max' => $max,
            'hourAvg' => $hourAvg,
            'dayAvg' => $dayAvg,
            'peaks' => $this->topPeaks($cells),
            'busiestHour' => $this->argMax($hourAvg),
            'busiestDow' => $this->argMax(array_filter($dayAvg, fn ($v) => $v !== null)),
        ];
    }

    /** Các ô cao nhất, giảm dần; hoà thì ưu tiên thứ nhỏ hơn rồi giờ nhỏ hơn cho kết quả ổn định. */
    private function topPeaks(array $cells): array
    {
        $flat = [];
        foreach ($cells as $dow => $byHour) {
            foreach ($byHour as $hour => $value) {
                if ($value !== null && $value > 0) {
                    $flat[] = ['dow' => $dow, 'hour' => $hour, 'value' => $value];
                }
            }
        }

        usort($flat, fn ($a, $b) => [$b['value'], $a['dow'], $a['hour']] <=> [$a['value'], $b['dow'], $b['hour']]);

        return array_slice($flat, 0, self::TOP_PEAKS);
    }

    /** Khoá có giá trị lớn nhất (hoà → khoá nhỏ nhất); null nếu rỗng hoặc mọi giá trị đều ≤ 0. */
    private function argMax(array $values): ?int
    {
        $bestKey = null;
        $bestValue = 0.0;
        foreach ($values as $key => $value) {
            if ($value > $bestValue) {
                $bestKey = $key;
                $bestValue = $value;
            }
        }

        return $bestKey;
    }
}
