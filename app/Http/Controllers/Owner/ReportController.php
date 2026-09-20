<?php

namespace App\Http\Controllers\Owner;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Concerns\ResolvesCurrentLocation;
use App\Models\Location;
use App\Models\Order;
use App\Models\OrderItem;
use App\Services\PeakHoursCalculator;
use DateTimeImmutable;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Illuminate\View\View;

/**
 * Báo cáo giá vốn/lợi nhuận — CHÍNH XÁC TUYỆT ĐỐI theo thời gian.
 *
 * Giá vốn (unit_cost/total_cost) được snapshot ngay tại thời điểm bán
 * (xem OrderController::checkout), không tính lại theo giá nhập hiện tại.
 * Vì vậy báo cáo cho bất kỳ ngày nào trong quá khứ đều phản ánh đúng giá vốn
 * thực tế tại đúng thời điểm đó, kể cả khi giá nhập nguyên liệu đã thay đổi sau này.
 *
 * Lưu ý: đơn hàng phát sinh TRƯỚC khi nâng cấp tính năng snapshot này sẽ hiển thị
 * giá vốn = 0 (vì tại thời điểm đó chưa có dữ liệu để ghi lại) — không thể suy
 * ngược chính xác cho quá khứ trước bản nâng cấp.
 *
 * v1.0.4: hỗ trợ xem theo Ngày/Tuần/Tháng, so sánh % với kỳ liền trước.
 * v1.0.7: phân loại Menu Engineering (Stars/Plowhorses/Puzzles/Dogs).
 * v1.0.8: xuất CSV — dùng dấu CHẤM PHẨY (;) làm phân cách cột thay vì dấu
 * phẩy mặc định, vì Excel ở cấu hình vùng Việt Nam mặc định hiểu dấu phẩy là
 * ký tự thập phân nên sẽ dồn hết cột nếu dùng dấu phẩy để phân tách.
 * v1.1.3: trang "Giờ cao điểm" (heatmap giờ × thứ trong tuần) — xem peakHours().
 */
class ReportController extends Controller
{
    use ResolvesCurrentLocation;
    private const CATEGORY_LABELS = [
        'star' => 'Star (Bán chạy, lãi cao)',
        'plowhorse' => 'Plowhorse (Bán chạy, lãi thấp)',
        'puzzle' => 'Puzzle (Lãi cao, ít bán)',
        'dog' => 'Dog (Lãi thấp, ít bán)',
    ];

    /**
     * === Cấu hình thuật toán dự đoán doanh thu (v1.0.9) ===
     * Cần tối thiểu bấy nhiêu ngày lịch sử (tính từ đơn HOÀN THÀNH đầu tiên
     * của quán tới hôm nay) mới đủ tin cậy để dự đoán — quán mới mở vài
     * ngày thì hiện "chưa đủ dữ liệu" thay vì suy diễn ẩu.
     */
    private const FORECAST_MIN_HISTORY_DAYS = 14;

    /** Cửa sổ dữ liệu tối đa dùng để tính toán (12 tuần gần nhất) — đủ dài để thấy xu hướng, không quá dài để lạc hậu so với tình hình hiện tại. */
    private const FORECAST_MAX_WINDOW_DAYS = 84;

    /** Cửa sổ tính hệ số mùa vụ theo thứ trong tuần (8 tuần gần nhất). */
    private const FORECAST_SEASONAL_WINDOW_DAYS = 56;

    /** Cửa sổ so sánh tăng trưởng: trung bình 4 tuần gần nhất so với 4 tuần liền trước đó. */
    private const FORECAST_TREND_WINDOW_DAYS = 28;

    /** Chặn biên tăng trưởng ±50%/tuần — tránh dự đoán phi thực tế khi dữ liệu ít/biến động mạnh (VD quán mới viral). */
    private const FORECAST_MAX_WEEKLY_GROWTH = 0.5;

    /** Tỉ lệ ngày phải CÓ doanh thu trong cửa sổ dữ liệu — dưới mức này coi là dữ liệu quá thưa để tin được (quán nghỉ bán quá nhiều ngày). */
    private const FORECAST_MIN_ACTIVE_DAY_RATIO = 0.5;

    public function index(Request $request): View
    {
        $location = $this->currentLocation($request);
        [$period, $anchor] = $this->resolvePeriodAndAnchor($request);

        [$start, $end] = $this->resolveRange($period, $anchor);
        [$prevStart, $prevEnd] = $this->resolveRange($period, $this->previousAnchor($period, $anchor));

        $current = $this->summarize($location, $start, $end);
        $previous = $this->summarize($location, $prevStart, $prevEnd);
        $menuEngineering = $this->classifyMenuItems($current['rows']);
        $forecast = $this->buildForecast($location, $period);
        $operatingCosts = $this->operatingCosts($location, $period, $start, $end);

        return view('owner.reports.index', [
            'location' => $location,
            'period' => $period,
            'anchor' => $anchor,
            'rangeLabel' => $this->rangeLabel($period, $start, $end),
            'revenue' => $current['revenue'],
            'totalCost' => $current['cost'],
            'totalProfit' => $current['profit'],
            'rows' => $current['rows'],
            'revenueChange' => $this->percentChange($current['revenue'], $previous['revenue']),
            'profitChange' => $this->percentChange($current['profit'], $previous['profit']),
            'menuRows' => $menuEngineering['rows'],
            'avgCmPerUnit' => $menuEngineering['avgCmPerUnit'],
            'popularityThreshold' => $menuEngineering['popularityThreshold'],
            'forecast' => $forecast,
            'forecastMinHistoryDays' => self::FORECAST_MIN_HISTORY_DAYS,
            'laborCost' => $operatingCosts['labor'],
            'rentCost' => $operatingCosts['rent'],
            'netProfit' => $current['profit'] - $operatingCosts['labor'] - $operatingCosts['rent'],
        ]);
    }

    /** Các khoảng xem hợp lệ của trang Giờ cao điểm, tính bằng TUẦN (mỗi thứ xuất hiện đúng ngần ấy lần). */
    private const PEAK_HOURS_WEEK_OPTIONS = [4, 8, 13];

    private const PEAK_HOURS_DEFAULT_WEEKS = 8;

    /** Dưới ngần này ngày dữ liệu, mỗi thứ mới xuất hiện 1-2 lần → số liệu chưa ổn định, cần cảnh báo. */
    private const PEAK_HOURS_MIN_STABLE_DAYS = 14;

    /**
     * Heatmap GIỜ CAO ĐIỂM × THỨ TRONG TUẦN (v1.1.3) — trả lời câu hỏi "khung
     * giờ nào, thứ mấy đông khách nhất" để xếp ca/chuẩn bị nguyên liệu. Xem
     * PeakHoursCalculator để biết cách tính từng ô.
     *
     * Là 1 TRANG RIÊNG (không nhét vào trang Báo cáo chính) vì có bộ lọc riêng —
     * số tuần xem và chỉ số hiển thị, độc lập với bộ chọn Ngày/Tuần/Tháng: heatmap
     * theo thứ trong tuần chỉ có nghĩa khi gộp nhiều tuần — và để trang Báo cáo
     * chính không phải chạy thêm truy vấn nặng mà phần lớn lần xem không cần tới.
     *
     * Khoảng xem kết thúc ở HẾT HÔM QUA (hôm nay chưa bán xong, tính vào sẽ làm
     * các giờ chiều/tối của hôm nay bị thấp giả — cùng nguyên tắc với dự đoán
     * doanh thu) và không kéo dài về trước ngày có đơn đầu tiên của quán (tránh
     * những ngày "0 đơn" chỉ vì quán chưa mở làm loãng trung bình).
     *
     * Doanh thu = tổng OrderItem::line_total của đơn hoàn thành, đúng nguồn với
     * trang Báo cáo (summarize()) nên 2 nơi luôn cùng 1 con số.
     */
    public function peakHours(Request $request, PeakHoursCalculator $calculator): View
    {
        $location = $this->currentLocation($request);

        $weeks = (int) $request->query('weeks', self::PEAK_HOURS_DEFAULT_WEEKS);
        $weeks = in_array($weeks, self::PEAK_HOURS_WEEK_OPTIONS, true) ? $weeks : self::PEAK_HOURS_DEFAULT_WEEKS;
        $metric = $request->query('metric') === PeakHoursCalculator::METRIC_REVENUE
            ? PeakHoursCalculator::METRIC_REVENUE
            : PeakHoursCalculator::METRIC_ORDERS;

        $windowEnd = Carbon::today()->subDay();
        $windowStart = $windowEnd->copy()->subDays($weeks * 7 - 1);

        $firstOrderAt = Order::query()
            ->where('location_id', $location->id)
            ->where('status', 'hoan_thanh')
            ->min('completed_at');

        $from = $windowStart;
        if ($firstOrderAt) {
            $firstOrderDay = Carbon::parse($firstOrderAt)->startOfDay();
            $from = $firstOrderDay->greaterThan($windowStart) ? $firstOrderDay : $windowStart;
        }

        $data = ['hasData' => false, 'dayCount' => 0, 'totalOrders' => 0];
        if ($firstOrderAt && $from->lessThanOrEqualTo($windowEnd)) {
            // toBase(): chỉ cần 2 cột thô cho mỗi đơn, không dựng model Eloquent cho hàng
            // chục nghìn đơn (13 tuần × vài trăm đơn/ngày). Giờ/thứ được tách ở PHP
            // (theo múi giờ ứng dụng) thay vì HOUR()/DAYOFWEEK() của MySQL để không
            // phụ thuộc múi giờ của phiên kết nối database.
            $sales = Order::query()
                ->select(['orders.id', 'orders.completed_at'])
                ->where('location_id', $location->id)
                ->where('status', 'hoan_thanh')
                ->whereBetween('completed_at', [$from->copy()->startOfDay(), $windowEnd->copy()->endOfDay()])
                ->withSum('items as revenue', 'line_total')
                ->toBase()
                ->get()
                ->map(fn ($row) => [new DateTimeImmutable($row->completed_at), (float) $row->revenue]);

            $data = $calculator->compute($sales, $from, $windowEnd);
        }

        return view('owner.reports.peak-hours', [
            'location' => $location,
            'data' => $data,
            'weeks' => $weeks,
            'weekOptions' => self::PEAK_HOURS_WEEK_OPTIONS,
            'metric' => $metric,
            'from' => $from->copy(),
            'to' => $windowEnd->copy(),
            'isUnstable' => $data['dayCount'] < self::PEAK_HOURS_MIN_STABLE_DAYS,
        ]);
    }

    /**
     * Xuất báo cáo ra file CSV — giữ đúng bộ lọc Ngày/Tuần/Tháng đang xem.
     * Cột số xuất ra dạng SỐ THẬT không có dấu chấm ngăn cách hàng nghìn (khác
     * với cách hiển thị trên web) — để Excel tính tổng/trung bình được ngay,
     * không bị hiểu nhầm thành chữ.
     */
    public function export(Request $request)
    {
        $location = $this->currentLocation($request);
        [$period, $anchor] = $this->resolvePeriodAndAnchor($request);
        [$start, $end] = $this->resolveRange($period, $anchor);

        $current = $this->summarize($location, $start, $end);
        $menuEngineering = $this->classifyMenuItems($current['rows']);
        $rows = $menuEngineering['rows'];
        $operatingCosts = $this->operatingCosts($location, $period, $start, $end);

        $filename = Str::slug($location->name.'-bao-cao-'.$start->format('Y-m-d').'-den-'.$end->format('Y-m-d')).'.csv';

        return response()->streamDownload(function () use ($rows, $location, $start, $end, $current, $operatingCosts) {
            $out = fopen('php://output', 'w');

            // BOM để Excel đọc đúng chữ tiếng Việt có dấu (UTF-8) — thiếu dòng
            // này Excel sẽ hiện chữ bị lỗi font dù file vẫn đúng mã hoá.
            fwrite($out, "\xEF\xBB\xBF");

            fputcsv($out, ['Báo cáo lợi nhuận — '.$location->name], ';');
            fputcsv($out, ['Khoảng thời gian: '.$start->format('d/m/Y').' - '.$end->format('d/m/Y')], ';');
            fputcsv($out, ['Xuất lúc: '.now()->format('d/m/Y H:i')], ';');
            fputcsv($out, [], ';');
            fputcsv($out, ['Tổng doanh thu (đ)', round($current['revenue'])], ';');
            fputcsv($out, ['Tổng giá vốn (đ)', round($current['cost'])], ';');
            fputcsv($out, ['Tổng lợi nhuận (đ)', round($current['profit'])], ';');
            fputcsv($out, ['Chi phí nhân sự (đ)', round($operatingCosts['labor'])], ';');
            fputcsv($out, ['Chi phí mặt bằng (đ)', round($operatingCosts['rent'])], ';');
            fputcsv($out, ['Lợi nhuận thực tế (đ)', round($current['profit'] - $operatingCosts['labor'] - $operatingCosts['rent'])], ';');
            fputcsv($out, [], ';');

            fputcsv($out, [
                'Tên món', 'Số lượng bán', 'Doanh thu (đ)', 'Giá vốn (đ)', 'Lợi nhuận (đ)',
                'Lợi nhuận/món (đ)', 'Tỷ trọng bán (%)', 'Phân loại Menu Engineering',
            ], ';');

            foreach ($rows as $row) {
                fputcsv($out, [
                    $row['name'],
                    $row['quantity'],
                    round($row['revenue']),
                    round($row['cost']),
                    round($row['profit']),
                    round($row['cm_per_unit']),
                    // Phần trăm dùng dấu PHẨY thập phân (kiểu VN) để Excel vùng
                    // Việt Nam nhận đúng là số, không phải chữ.
                    number_format($row['popularity'], 1, ',', ''),
                    self::CATEGORY_LABELS[$row['category']] ?? $row['category'],
                ], ';');
            }

            fclose($out);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    /**
     * v1.1.1 — Bản tóm tắt "sạch" (không kèm chi tiết từng món) cho ĐÚNG 1
     * xe, dùng bởi LocationController::report() để tổng hợp báo cáo NHIỀU
     * xe cùng lúc — tái dùng lại đúng công thức doanh thu/giá vốn/lợi nhuận/
     * chi phí vận hành đã có, không viết lại logic ở nơi khác (tránh 2 nơi
     * tính ra 2 con số khác nhau cho cùng 1 khái niệm).
     */
    public function summaryFor(Location $location, string $period, Carbon $anchor): array
    {
        [$start, $end] = $this->resolveRange($period, $anchor);
        $current = $this->summarize($location, $start, $end);
        $costs = $this->operatingCosts($location, $period, $start, $end);

        return [
            'location' => $location,
            'revenue' => $current['revenue'],
            'cost' => $current['cost'],
            'profit' => $current['profit'],
            'labor' => $costs['labor'],
            'rent' => $costs['rent'],
            'netProfit' => $current['profit'] - $costs['labor'] - $costs['rent'],
        ];
    }

    /** Đọc period (day/week/month) + ngày mốc từ query string, mặc định hôm nay nếu không có. Public — dùng lại ở LocationController::report() (báo cáo tổng hợp nhiều xe). */
    public function resolvePeriodAndAnchor(Request $request): array
    {
        $period = in_array($request->query('period'), ['day', 'week', 'month']) ? $request->query('period') : 'day';
        $anchor = $request->query('date') ? Carbon::parse($request->query('date')) : Carbon::today();

        return [$period, $anchor];
    }

    /** Khoảng thời gian [đầu, cuối] tương ứng với 1 kỳ báo cáo, tính từ ngày mốc. */
    private function resolveRange(string $period, Carbon $anchor): array
    {
        return match ($period) {
            'week' => [$anchor->copy()->startOfWeek(Carbon::MONDAY), $anchor->copy()->endOfWeek(Carbon::SUNDAY)],
            'month' => [$anchor->copy()->startOfMonth(), $anchor->copy()->endOfMonth()],
            default => [$anchor->copy()->startOfDay(), $anchor->copy()->endOfDay()],
        };
    }

    /** Ngày mốc của kỳ LIỀN TRƯỚC — dùng để tính khoảng so sánh. */
    private function previousAnchor(string $period, Carbon $anchor): Carbon
    {
        return match ($period) {
            'week' => $anchor->copy()->subWeek(),
            'month' => $anchor->copy()->subMonthNoOverflow(),
            default => $anchor->copy()->subDay(),
        };
    }

    private function rangeLabel(string $period, Carbon $start, Carbon $end): string
    {
        return match ($period) {
            'week' => 'Tuần '.$start->format('d/m').' - '.$end->format('d/m/Y'),
            'month' => 'Tháng '.$start->format('m/Y'),
            default => 'Ngày '.$start->format('d/m/Y'),
        };
    }

    /** Tổng hợp doanh thu/giá vốn/lợi nhuận + chi tiết theo món trong 1 khoảng thời gian. */
    private function summarize(Location $location, Carbon $start, Carbon $end): array
    {
        $items = OrderItem::query()
            ->whereHas('order', function ($q) use ($location, $start, $end) {
                $q->where('location_id', $location->id)
                    ->where('status', 'hoan_thanh')
                    ->whereBetween('completed_at', [$start, $end]);
            })
            ->with('variant.product')
            ->get();

        $revenue = (float) $items->sum('line_total');
        $cost = (float) $items->sum('total_cost');

        $rows = $items->groupBy('product_variant_id')->map(function ($group) {
            $variant = $group->first()->variant;
            $lineRevenue = (float) $group->sum('line_total');
            $lineCost = (float) $group->sum('total_cost');

            return [
                'name' => $variant->product->name.' ('.$variant->name.')',
                'quantity' => $group->sum('quantity'),
                'revenue' => $lineRevenue,
                'cost' => $lineCost,
                'profit' => $lineRevenue - $lineCost,
            ];
        })->sortByDesc('revenue')->values();

        return ['revenue' => $revenue, 'cost' => $cost, 'profit' => $revenue - $cost, 'rows' => $rows];
    }

    /**
     * % thay đổi so với kỳ trước. Trả về null nếu kỳ trước = 0 nhưng kỳ này > 0
     * (tăng "vô cực", không có % hợp lý để hiển thị — UI sẽ hiện "Mới" thay vì %).
     */
    private function percentChange(float $current, float $previous): ?float
    {
        if ($previous == 0.0) {
            return $current > 0 ? null : 0.0;
        }

        return round((($current - $previous) / $previous) * 100, 1);
    }

    /**
     * === CHI PHÍ VẬN HÀNH (v1.1.0) — Lương nhân viên + mặt bằng ===
     *
     * Mục tiêu: ra được "Lợi nhuận thực tế" (đã trừ lương + mặt bằng), không
     * chỉ dừng ở lợi nhuận gộp (đã trừ giá vốn nguyên liệu) như trước đây.
     *
     * LƯƠNG THEO GIỜ: cộng dồn CHÍNH XÁC theo thời lượng từng ca ĐÃ CHỐT
     * (closed_at không null) có giờ MỞ CA nằm trong khoảng đang xem, nhân với
     * lương/giờ của đúng người đó. Ca CHƯA chốt không tính — số giờ của ca
     * đang mở còn thay đổi, tính vào sẽ sai lệch khi ca đó thực sự đóng.
     *
     * LƯƠNG THEO THÁNG + CHI PHÍ MẶT BẰNG: đều là chi phí CỐ ĐỊNH mỗi tháng,
     * không phụ thuộc có bán được hàng hay không, nên phải PHÂN BỔ theo tỉ lệ
     * số ngày của kỳ đang xem trên tổng số ngày của tháng đó — xem 1 ngày thì
     * tính đúng 1/số-ngày-trong-tháng, xem 1 tuần thì tính 7/số-ngày-trong-
     * tháng. Xem theo THÁNG thì dùng thẳng nguyên số tiền, không nhân chia gì
     * thêm (khớp chính xác 1 tháng dương lịch, tránh sai số làm tròn).
     */
    private function operatingCosts(Location $location, string $period, Carbon $start, Carbon $end): array
    {
        $hourlyLabor = (float) $location->shifts()
            ->whereNotNull('closed_at')
            ->whereBetween('opened_at', [$start, $end])
            ->whereHas('user', fn ($q) => $q->where('salary_type', 'hourly'))
            ->with('user:id,salary_amount')
            ->get()
            ->sum(fn ($shift) => ($shift->opened_at->diffInMinutes($shift->closed_at) / 60) * (float) $shift->user->salary_amount);

        $monthlySalaryTotal = (float) $location->staff()
            ->where('role', 'staff')
            ->where('salary_type', 'monthly')
            ->sum('salary_amount');

        if ($period === 'month') {
            $monthlyLabor = $monthlySalaryTotal;
            $rent = (float) $location->rent_cost;
        } else {
            $daysInPeriod = $start->diffInDays($end) + 1;
            $daysInMonth = $start->daysInMonth;
            $monthlyLabor = $monthlySalaryTotal / $daysInMonth * $daysInPeriod;
            $rent = (float) $location->rent_cost / $daysInMonth * $daysInPeriod;
        }

        return [
            'labor' => $hourlyLabor + $monthlyLabor,
            'rent' => $rent,
        ];
    }

    /**
     * Phân loại món theo mô hình Menu Engineering chuẩn (Kasavana & Smith) —
     * 2 trục: Contribution Margin/món (lãi ròng trung bình mỗi món bán ra) và
     * Popularity % (tỷ trọng số lượng bán so với tổng). Ngưỡng phân loại:
     * - CM: so với CM trung bình CHUNG của toàn thực đơn trong kỳ (tổng lợi
     *   nhuận / tổng số lượng bán) — món trên mức này là "CM cao".
     * - Popularity: so với 70% mức kỳ vọng NẾU chia đều cho số món (1/n) —
     *   đây là ngưỡng chuẩn của mô hình gốc, không phải mình tự đặt tuỳ ý.
     * 4 nhóm: Stars (CM cao + bán chạy), Plowhorses (CM thấp + bán chạy),
     * Puzzles (CM cao + ít bán), Dogs (CM thấp + ít bán).
     */
    private function classifyMenuItems($rows): array
    {
        $itemCount = $rows->count();
        $totalQuantity = $rows->sum('quantity');
        $totalProfit = $rows->sum('profit');

        $avgCmPerUnit = $totalQuantity > 0 ? $totalProfit / $totalQuantity : 0;
        $avgPopularity = $itemCount > 0 ? (1 / $itemCount) * 100 : 0;
        $popularityThreshold = $avgPopularity * 0.7;

        $classified = $rows->map(function ($row) use ($totalQuantity, $avgCmPerUnit, $popularityThreshold) {
            $cmPerUnit = $row['quantity'] > 0 ? $row['profit'] / $row['quantity'] : 0;
            $popularity = $totalQuantity > 0 ? ($row['quantity'] / $totalQuantity) * 100 : 0;
            $highCm = $cmPerUnit >= $avgCmPerUnit;
            $highPop = $popularity >= $popularityThreshold;

            $row['cm_per_unit'] = $cmPerUnit;
            $row['popularity'] = $popularity;
            $row['category'] = match (true) {
                $highCm && $highPop => 'star',
                ! $highCm && $highPop => 'plowhorse',
                $highCm && ! $highPop => 'puzzle',
                default => 'dog',
            };

            return $row;
        });

        return ['rows' => $classified, 'avgCmPerUnit' => $avgCmPerUnit, 'popularityThreshold' => $popularityThreshold];
    }

    /**
     * === DỰ ĐOÁN DOANH THU (v1.0.9) ===
     *
     * Luôn dự đoán cho kỳ SẮP TỚI tính từ HÔM NAY (không phụ thuộc ngày mốc
     * $anchor đang xem trên bộ lọc) — xem báo cáo của 1 ngày trong quá khứ mà
     * ra "dự đoán" cho ngày sau đó (cũng trong quá khứ) sẽ vô nghĩa với chủ
     * quán. VD: đang lọc "Tuần", nút hiện tại là 22/09 (tuần trước) → dự đoán
     * vẫn luôn là TUẦN SẮP TỚI kể từ hôm nay, không phải tuần sau 22/09.
     *
     * Phương pháp — minh bạch, không phải hộp đen, gồm 2 thành phần cộng
     * hưởng:
     *
     * 1) MÙA VỤ THEO THỨ TRONG TUẦN (weekly seasonality, hệ số nhân):
     *    Xe cà phê thường cuối tuần đông hơn ngày thường. Tính hệ số riêng
     *    cho từng thứ (T2..CN) = (doanh thu TB của thứ đó) / (doanh thu TB
     *    của CẢ TUẦN), dựa trên tối đa 8 tuần gần nhất.
     *
     * 2) XU HƯỚNG TĂNG TRƯỞNG (trend, hệ số nhân luỹ thừa theo tuần):
     *    So doanh thu TB/ngày của 4 tuần gần nhất với 4 tuần liền trước đó
     *    → ra % tăng trưởng/tuần, áp dụng dạng luỹ thừa (1+g)^(số tuần tới)
     *    cho những ngày càng xa hôm nay. Chặn biên ±50%/tuần để không viển
     *    vông khi dữ liệu ít/biến động mạnh.
     *
     * Công thức mỗi ngày dự đoán:
     *   DoanhThu(ngày) = TB doanh thu/ngày (4 tuần gần nhất)
     *                     × HệSốMùaVụ(thứ trong ngày đó)
     *                     × (1 + %tăng trưởng/tuần) ^ (số tuần tính từ hôm nay)
     * Dự đoán Tuần/Tháng = cộng dồn dự đoán từng ngày trong kỳ đó.
     *
     * Dải tin cậy thấp–cao dựa trên độ lệch chuẩn của phần dư thực tế so với
     * mô hình (đo trên 4 tuần gần nhất) — ~80% khoảng tin cậy, cộng dồn theo
     * căn bậc hai số ngày (giả định sai số các ngày độc lập với nhau).
     *
     * Trả về null nếu chưa đủ dữ liệu tin cậy (quán mới mở hoặc dữ liệu quá
     * thưa) — view sẽ hiện thông báo "chưa đủ dữ liệu" thay vì số dự đoán.
     */
    private function buildForecast(Location $location, string $period): ?array
    {
        $today = Carbon::today();

        $firstOrderDate = Order::query()
            ->where('location_id', $location->id)
            ->where('status', 'hoan_thanh')
            ->orderBy('completed_at')
            ->value('completed_at');

        if (! $firstOrderDate) {
            return null;
        }

        $firstOrderDate = Carbon::parse($firstOrderDate)->startOfDay();
        $historyDays = $firstOrderDate->diffInDays($today) + 1;

        if ($historyDays < self::FORECAST_MIN_HISTORY_DAYS) {
            return null;
        }

        // Cửa sổ dữ liệu thực tế dùng để tính — không kéo dài về trước ngày
        // quán có đơn đầu tiên (tránh cả loạt ngày "0đ giả" kéo trung bình
        // xuống sai lệch vì lúc đó quán còn chưa tồn tại, không phải ế ẩm).
        $windowDays = min(self::FORECAST_MAX_WINDOW_DAYS, $historyDays);
        $windowEnd = $today->copy()->subDay(); // đến hết HÔM QUA — hôm nay chưa bán xong, số liệu chưa đầy đủ nếu tính vào
        $windowStart = $windowEnd->copy()->subDays($windowDays - 1);

        $daily = $this->dailyRevenueSeries($location, $windowStart, $windowEnd);

        $activeDays = $daily->filter(fn ($v) => $v > 0)->count();
        if ($daily->count() === 0 || ($activeDays / $daily->count()) < self::FORECAST_MIN_ACTIVE_DAY_RATIO) {
            return null;
        }

        // 1) Hệ số mùa vụ theo thứ trong tuần — dùng tối đa 8 tuần gần nhất.
        $seasonalWindow = $daily->slice(-self::FORECAST_SEASONAL_WINDOW_DAYS);
        $seasonalIndex = $this->weeklySeasonalIndex($seasonalWindow);

        // 2) Xu hướng tăng trưởng — so 4 tuần gần nhất với 4 tuần liền trước.
        $trendWindowDays = min(self::FORECAST_TREND_WINDOW_DAYS, (int) floor($daily->count() / 2));
        $recentWindow = $trendWindowDays > 0 ? $daily->slice(-$trendWindowDays) : $daily;
        $baselineDaily = collect($recentWindow)->avg() ?: 0.0;

        // So sánh 2 khối liền kề (VD 28 ngày gần nhất so với 28 ngày trước đó) chỉ
        // cho ra % tăng trưởng GIỮA 2 KHỐI (cách nhau ~$trendWindowDays/7 tuần),
        // KHÔNG PHẢI %/tuần — phải khai căn bậc (số tuần lệch giữa 2 khối) mới quy
        // đổi đúng về tốc độ tăng trưởng MỖI TUẦN (VD lệch 4 tuần, khối sau cao hơn
        // 12% tổng cộng thì mỗi tuần chỉ ~2,9% chứ không phải 12%).
        $growthRatePerWeek = 0.0;
        if ($trendWindowDays >= 7 && $daily->count() >= $trendWindowDays * 2) {
            $priorWindow = $daily->slice(-$trendWindowDays * 2, $trendWindowDays);
            $priorAvg = collect($priorWindow)->avg();
            if ($priorAvg > 0) {
                $weeksBetweenWindows = $trendWindowDays / 7;
                $growthRatePerWeek = ($baselineDaily / $priorAvg) ** (1 / $weeksBetweenWindows) - 1;
                $growthRatePerWeek = max(-self::FORECAST_MAX_WEEKLY_GROWTH, min(self::FORECAST_MAX_WEEKLY_GROWTH, $growthRatePerWeek));
            }
        }

        // 3) Độ lệch chuẩn phần dư (thực tế − kỳ vọng theo mô hình mùa vụ) trên cửa sổ gần nhất, để tính dải tin cậy.
        $residuals = [];
        foreach ($recentWindow as $dateStr => $rev) {
            $expected = $baselineDaily * ($seasonalIndex[Carbon::parse($dateStr)->dayOfWeekIso] ?? 1.0);
            $residuals[] = $rev - $expected;
        }
        $stdDev = $this->standardDeviation($residuals);

        [$forecastStart, $forecastEnd] = $this->nextPeriodRangeFromToday($period, $today);

        $dailyForecasts = [];
        for ($d = $forecastStart->copy(); $d->lte($forecastEnd); $d->addDay()) {
            $weeksAhead = max(1, (int) ceil($today->diffInDays($d) / 7));
            $seasonal = $seasonalIndex[$d->dayOfWeekIso] ?? 1.0;
            $growthFactor = (1 + $growthRatePerWeek) ** $weeksAhead;
            $dailyForecasts[] = max(0.0, $baselineDaily * $seasonal * $growthFactor);
        }

        $expected = array_sum($dailyForecasts);
        $days = count($dailyForecasts);
        $margin = $stdDev * sqrt($days) * 1.28; // z ≈ 1.28 → khoảng tin cậy ~80%

        return [
            'expected' => $expected,
            'low' => max(0.0, $expected - $margin),
            'high' => $expected + $margin,
            'growthRatePerWeek' => $growthRatePerWeek,
            'rangeLabel' => $this->rangeLabel($period, $forecastStart, $forecastEnd),
            'historyDays' => $historyDays,
        ];
    }

    /**
     * Doanh thu THEO NGÀY trong khoảng [start, end] — LUÔN trả đủ mọi ngày
     * trong khoảng kể cả ngày không bán được gì (giá trị 0), để các phép
     * tính trung bình/mùa vụ không bị lệch do thiếu ngày. Dùng chung nguồn
     * dữ liệu (OrderItem::line_total của đơn hoàn thành) với summarize() để
     * số dự đoán nhất quán với số "Doanh thu" đang hiển thị trên báo cáo.
     */
    private function dailyRevenueSeries(Location $location, Carbon $start, Carbon $end): \Illuminate\Support\Collection
    {
        $items = OrderItem::query()
            ->whereHas('order', function ($q) use ($location, $start, $end) {
                $q->where('location_id', $location->id)
                    ->where('status', 'hoan_thanh')
                    ->whereBetween('completed_at', [$start->copy()->startOfDay(), $end->copy()->endOfDay()]);
            })
            ->with('order:id,completed_at')
            ->get();

        $byDay = $items->groupBy(fn ($item) => $item->order->completed_at->toDateString())
            ->map(fn ($group) => (float) $group->sum('line_total'));

        $series = collect();
        for ($d = $start->copy(); $d->lte($end); $d->addDay()) {
            $series->put($d->toDateString(), $byDay->get($d->toDateString(), 0.0));
        }

        return $series;
    }

    /**
     * Hệ số mùa vụ theo thứ trong tuần (1=Thứ 2 .. 7=Chủ nhật) = doanh thu
     * TB của thứ đó / doanh thu TB chung của cả cửa sổ. Thứ nào chưa có dữ
     * liệu (VD quán mới, chưa đủ 1 vòng tuần) thì mặc định hệ số = 1 (coi
     * như trung bình, không thiên vị).
     */
    private function weeklySeasonalIndex(\Illuminate\Support\Collection $series): array
    {
        $overallAvg = $series->avg() ?: 0.0;
        $byDow = [];
        foreach ($series as $dateStr => $rev) {
            $byDow[Carbon::parse($dateStr)->dayOfWeekIso][] = $rev;
        }

        $index = [];
        for ($dow = 1; $dow <= 7; $dow++) {
            $values = $byDow[$dow] ?? [];
            $index[$dow] = ($overallAvg > 0 && count($values) > 0)
                ? (array_sum($values) / count($values)) / $overallAvg
                : 1.0;
        }

        return $index;
    }

    private function standardDeviation(array $values): float
    {
        $n = count($values);
        if ($n < 2) {
            return 0.0;
        }

        $mean = array_sum($values) / $n;
        $variance = array_sum(array_map(fn ($v) => ($v - $mean) ** 2, $values)) / ($n - 1);

        return sqrt($variance);
    }

    /** Khoảng ngày/tuần/tháng SẮP TỚI tính từ hôm nay — dùng riêng cho dự đoán, KHÔNG phụ thuộc $anchor đang lọc trên báo cáo. */
    private function nextPeriodRangeFromToday(string $period, Carbon $today): array
    {
        return match ($period) {
            'week' => [
                $today->copy()->addWeek()->startOfWeek(Carbon::MONDAY),
                $today->copy()->addWeek()->endOfWeek(Carbon::SUNDAY),
            ],
            'month' => [
                $today->copy()->addMonthNoOverflow()->startOfMonth(),
                $today->copy()->addMonthNoOverflow()->endOfMonth(),
            ],
            default => [$today->copy()->addDay()->startOfDay(), $today->copy()->addDay()->endOfDay()],
        };
    }
}
