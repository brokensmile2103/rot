<?php

namespace App\Services;

use DateTimeImmutable;
use DateTimeInterface;

/**
 * Theo dõi doanh thu năm so với ngưỡng miễn thuế của hộ kinh doanh (v1.2.0).
 *
 * Class THUẦN (không đụng database/Laravel) để kiểm thử độc lập; việc gom số
 * liệu doanh thu nằm ở TaxBookController.
 *
 * Dự báo rất đơn giản và minh bạch: lấy doanh thu TRUNG BÌNH MỖI NGÀY trong cửa
 * sổ gần đây (đã tính cả ngày nghỉ bán) nhân với số ngày còn lại của năm. Không
 * dùng mô hình phức tạp vì đây là con số để CẢNH BÁO SỚM, người dùng cần hiểu
 * được vì sao có con số đó chứ không cần độ chính xác từng đồng.
 */
class RevenueThresholdTracker
{
    /** Cần ít nhất ngần này ngày dữ liệu gần đây mới dám dự báo — ít hơn thì "chưa đủ dữ liệu". */
    public const MIN_PROJECTION_DAYS = 7;

    /** Từ mức % này của ngưỡng trở lên thì cảnh báo "sắp chạm ngưỡng". */
    public const NEAR_PERCENT = 80.0;

    public const STATUS_OK = 'ok';

    public const STATUS_NEAR = 'near';

    public const STATUS_WILL_EXCEED = 'will_exceed';

    public const STATUS_EXCEEDED = 'exceeded';

    /**
     * @param  float  $ytdRevenue  Doanh thu từ đầu năm đến hết hôm nay (đã gồm doanh thu ngoài Rót)
     * @param  float  $recentRevenue  Doanh thu của $recentDays NGÀY TRỌN VẸN gần nhất, kết thúc hôm qua
     * @param  int  $recentDays  Số ngày trong cửa sổ gần đây (0 nếu quán chưa bán ngày nào trước hôm nay)
     * @return array<string, mixed>
     */
    public function evaluate(float $ytdRevenue, float $recentRevenue, int $recentDays, DateTimeInterface $today, float $threshold): array
    {
        $today = DateTimeImmutable::createFromInterface($today)->setTime(0, 0);
        $dayOfYear = (int) $today->format('z') + 1;
        $daysInYear = $today->format('L') === '1' ? 366 : 365;
        $daysLeft = $daysInYear - $dayOfYear; // số ngày CÒN LẠI sau hôm nay

        $percent = $threshold > 0 ? $ytdRevenue / $threshold * 100 : 0.0;
        $hasProjection = $recentDays >= self::MIN_PROJECTION_DAYS;
        $avgDaily = $recentDays > 0 ? max(0.0, $recentRevenue) / $recentDays : 0.0;

        $projectedYear = $hasProjection ? $ytdRevenue + $avgDaily * $daysLeft : null;

        $crossDate = null;
        if ($hasProjection && $avgDaily > 0 && $ytdRevenue <= $threshold) {
            // Cần thêm ngần này ngày (tối thiểu 1: bằng đúng ngưỡng thì đơn kế tiếp là vượt).
            $daysNeeded = (int) max(1, ceil(($threshold - $ytdRevenue) / $avgDaily));
            if ($daysNeeded <= $daysLeft) {
                $crossDate = $today->modify('+'.$daysNeeded.' days')->format('Y-m-d');
            }
        }

        if ($ytdRevenue > $threshold) {
            $status = self::STATUS_EXCEEDED;
        } elseif ($projectedYear !== null && $projectedYear > $threshold) {
            $status = self::STATUS_WILL_EXCEED;
        } elseif ($percent >= self::NEAR_PERCENT) {
            $status = self::STATUS_NEAR;
        } else {
            $status = self::STATUS_OK;
        }

        return [
            'ytd' => $ytdRevenue,
            'threshold' => $threshold,
            'percent' => $percent,
            'remaining' => max(0.0, $threshold - $ytdRevenue),
            'over' => max(0.0, $ytdRevenue - $threshold),
            'dayOfYear' => $dayOfYear,
            'daysInYear' => $daysInYear,
            'daysLeft' => $daysLeft,
            'avgDaily' => $avgDaily,
            'hasProjection' => $hasProjection,
            'projectedYear' => $projectedYear,
            'crossDate' => $crossDate,
            'status' => $status,
        ];
    }
}
