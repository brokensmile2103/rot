<?php

namespace App\Http\Controllers\Owner;

use App\Http\Controllers\Concerns\ResolvesCurrentLocation;
use App\Http\Controllers\Controller;
use App\Models\ExternalRevenue;
use App\Models\Location;
use App\Models\Order;
use App\Models\User;
use App\Services\RevenueBookBuilder;
use App\Services\RevenueThresholdTracker;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Sổ doanh thu & theo dõi ngưỡng thuế cho hộ kinh doanh (v1.2.0).
 *
 * Từ 2026 mọi hộ kinh doanh đều phải ghi sổ kế toán; hộ có doanh thu năm ở mức
 * miễn thuế chỉ cần 1 quyển duy nhất là Sổ doanh thu bán hàng hóa, dịch vụ (mẫu
 * S1a-HKD, Thông tư 152/2025/TT-BTC). Controller này dựng sổ đó từ dữ liệu bán
 * hàng và theo dõi doanh thu năm so với ngưỡng miễn thuế (config/tax.php) để chủ
 * quán biết trước khi nào sắp phải chuyển sang chế độ chịu thuế.
 *
 * Phân biệt 2 phạm vi:
 *  - SỔ là của TỪNG XE (địa điểm kinh doanh) — mỗi xe 1 sổ.
 *  - NGƯỠNG THUẾ tính CỘNG DỒN mọi xe của cùng chủ (cùng 1 hộ/cá nhân kinh doanh),
 *    chứ không tính riêng từng xe — nếu không, chủ 3 xe mỗi xe 400 triệu sẽ tưởng
 *    mình vẫn còn xa ngưỡng dù tổng đã 1,2 tỷ.
 *
 * Mọi số doanh thu là TIỀN THỰC NHẬN (Order::total) — xem OrderRevenue.
 */
class TaxBookController extends Controller
{
    use ResolvesCurrentLocation;

    /** Cửa sổ ngày gần đây dùng tính nhịp bán để dự báo (tối đa; quán mới bán thì ngắn hơn). */
    private const RECENT_WINDOW_DAYS = 28;

    /** Năm sớm nhất cho phép chọn/nhập (chế độ ghi sổ mới áp dụng từ 2026, nhưng chừa dư cho dữ liệu cũ). */
    private const MIN_YEAR = 2020;

    public function index(Request $request, RevenueBookBuilder $builder, RevenueThresholdTracker $tracker): View
    {
        $location = $this->currentLocation($request);
        [$year, $month] = $this->resolvePeriod($request, true);
        [$from, $to] = $this->periodRange($year, $month);

        $overview = $this->thresholdOverview($request->user(), $tracker);

        return view('owner.tax.revenue-book', [
            'location' => $location,
            'year' => $year,
            'month' => $month,
            'book' => $this->buildBook($location, $from, $to, $builder),
            'overview' => $overview,
            'yearOptions' => range(Carbon::today()->year, min($overview['firstYear'], Carbon::today()->year)),
            'periodLabel' => $this->periodLabel($year, $month),
            'today' => Carbon::today(),
        ]);
    }

    /** Bản in (Ctrl+P / "Lưu thành PDF") của sổ — trang riêng không có thanh điều hướng, có chỗ ký tên. */
    public function print(Request $request, RevenueBookBuilder $builder): View
    {
        $location = $this->currentLocation($request);
        [$year, $month] = $this->resolvePeriod($request, false);
        [$from, $to] = $this->periodRange($year, $month);

        return view('owner.tax.revenue-book-print', [
            'location' => $location,
            'household' => $location->tax_household_name ?: $request->user()->name,
            'book' => $this->buildBook($location, $from, $to, $builder),
            'periodLabel' => $this->periodLabel($year, $month),
        ]);
    }

    /**
     * Xuất sổ ra CSV — cùng quy ước với file báo cáo: dấu chấm phẩy (;) làm phân cách cột
     * (Excel vùng Việt Nam hiểu dấu phẩy là số thập phân), số tiền dạng SỐ THẬT không có dấu
     * ngăn cách hàng nghìn để Excel cộng được ngay, và BOM để hiện đúng tiếng Việt có dấu.
     */
    public function export(Request $request, RevenueBookBuilder $builder): StreamedResponse
    {
        $location = $this->currentLocation($request);
        [$year, $month] = $this->resolvePeriod($request, false);
        [$from, $to] = $this->periodRange($year, $month);
        $book = $this->buildBook($location, $from, $to, $builder);
        $household = $location->tax_household_name ?: $request->user()->name;
        $periodLabel = $this->periodLabel($year, $month);

        $filename = Str::slug($location->name.'-so-doanh-thu-'.$periodLabel).'.csv';

        return response()->streamDownload(function () use ($book, $location, $household, $periodLabel) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF");

            fputcsv($out, ['SỔ DOANH THU BÁN HÀNG HÓA, DỊCH VỤ (Mẫu số S1a-HKD)'], ';');
            fputcsv($out, ['Hộ, cá nhân kinh doanh: '.$household], ';');
            fputcsv($out, ['Địa điểm kinh doanh: '.($location->address ?: $location->name)], ';');
            fputcsv($out, ['Mã số thuế: '.($location->tax_code ?? '')], ';');
            fputcsv($out, ['Kỳ ghi sổ: '.$periodLabel], ';');
            fputcsv($out, ['Đơn vị tính: đồng'], ';');
            fputcsv($out, [], ';');
            fputcsv($out, ['Ngày tháng', 'Diễn giải', 'Số tiền (đ)'], ';');

            foreach ($book['rows'] as $row) {
                fputcsv($out, [
                    $row['date'] ? Carbon::parse($row['date'])->format('d/m/Y') : '',
                    $row['description'],
                    (int) $row['amount'],
                ], ';');
            }

            fputcsv($out, ['', 'Tổng cộng', (int) $book['total']], ';');
            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    /** Thông tin in ở đầu sổ: tên hộ kinh doanh + mã số thuế của xe đang chọn. */
    public function updateProfile(Request $request): RedirectResponse
    {
        $location = $this->currentLocation($request);

        $data = $request->validate([
            'tax_household_name' => 'nullable|string|max:100',
            // Mã số thuế: 10 số, hoặc 10 số + "-" + 3 số (địa điểm phụ thuộc), hoặc 12 số (căn cước/định danh cá nhân).
            'tax_code' => ['nullable', 'string', 'regex:/^(\d{10}(-\d{3})?|\d{12})$/'],
        ], [
            'tax_code.regex' => 'Mã số thuế gồm 10 chữ số (hoặc 10 số kèm -XXX, hoặc 12 số của căn cước công dân).',
        ]);

        $location->update([
            'tax_household_name' => $data['tax_household_name'] ?? null,
            'tax_code' => $data['tax_code'] ?? null,
        ]);

        return back()->with('status', 'Đã lưu thông tin hộ kinh doanh.');
    }

    /** Thêm 1 khoản doanh thu NGOÀI Rót (giao đồ ăn, bán sỉ...) để sổ và mức theo dõi ngưỡng đủ mọi kênh bán. */
    public function storeExternal(Request $request): RedirectResponse
    {
        $location = $this->currentLocation($request);

        $data = $request->validate([
            'revenue_date' => ['required', 'date_format:Y-m-d', 'before_or_equal:today', 'after_or_equal:'.self::MIN_YEAR.'-01-01'],
            'amount' => 'required|numeric|min:1|max:99999999999',
            'description' => 'required|string|max:150',
        ], [
            'revenue_date.before_or_equal' => 'Không nhập được doanh thu của ngày chưa tới.',
        ]);

        $location->externalRevenues()->create($data + ['user_id' => $request->user()->id]);

        $date = Carbon::parse($data['revenue_date']);

        return redirect()
            ->route('owner.tax.revenue-book', ['year' => $date->year, 'month' => $date->month])
            ->with('status', 'Đã thêm khoản doanh thu ngoài Rót vào sổ.');
    }

    public function destroyExternal(Request $request, int $external): RedirectResponse
    {
        $location = $this->currentLocation($request);
        $location->externalRevenues()->findOrFail($external)->delete();

        return back()->with('status', 'Đã xoá khoản doanh thu ngoài Rót.');
    }

    // ------------------------------------------------------------------ nội bộ

    /**
     * Đọc năm/tháng từ query string. month = 0 nghĩa là cả năm. Trang xem mặc định tháng hiện tại
     * (gọn, dễ đọc trên điện thoại); bản in/CSV mặc định CẢ NĂM vì sổ kế toán là sổ theo năm.
     *
     * @return array{0: int, 1: int}
     */
    private function resolvePeriod(Request $request, bool $defaultToCurrentMonth): array
    {
        $today = Carbon::today();

        $year = (int) $request->query('year', $today->year);
        if ($year < self::MIN_YEAR || $year > $today->year) {
            $year = $today->year;
        }

        if ($request->query('month') === null) {
            $month = ($defaultToCurrentMonth && $year === $today->year) ? $today->month : 0;
        } else {
            $month = (int) $request->query('month');
            if ($month < 0 || $month > 12) {
                $month = 0;
            }
        }

        // Năm hiện tại: không cho chọn tháng chưa tới (sổ chưa có gì để ghi).
        if ($year === $today->year && $month > $today->month) {
            $month = $today->month;
        }

        return [$year, $month];
    }

    /** @return array{0: Carbon, 1: Carbon} */
    private function periodRange(int $year, int $month): array
    {
        if ($month === 0) {
            return [Carbon::create($year, 1, 1)->startOfDay(), Carbon::create($year, 12, 31)->endOfDay()];
        }

        $from = Carbon::create($year, $month, 1)->startOfDay();

        return [$from, $from->copy()->endOfMonth()];
    }

    private function periodLabel(int $year, int $month): string
    {
        return $month === 0 ? 'Năm '.$year : 'Tháng '.sprintf('%02d', $month).'/'.$year;
    }

    /** Dựng các dòng sổ cho 1 xe trong khoảng [from, to]. */
    private function buildBook(Location $location, Carbon $from, Carbon $to, RevenueBookBuilder $builder): array
    {
        // Gộp theo ngày ngay trong SQL — không kéo hàng chục nghìn đơn của cả năm về PHP.
        $daily = Order::query()
            ->where('location_id', $location->id)
            ->where('status', 'hoan_thanh')
            ->whereBetween('completed_at', [$from, $to])
            ->selectRaw('DATE(completed_at) AS sale_date, COUNT(*) AS order_count, SUM(total) AS day_revenue')
            ->groupBy('sale_date')
            ->orderBy('sale_date')
            ->get()
            ->map(fn ($row) => ['date' => (string) $row->sale_date, 'orders' => (int) $row->order_count, 'revenue' => (float) $row->day_revenue])
            ->all();

        $externals = $location->externalRevenues()
            ->whereBetween('revenue_date', [$from->toDateString(), $to->toDateString()])
            ->orderBy('revenue_date')->orderBy('id')
            ->get()
            ->map(fn (ExternalRevenue $e) => [
                'id' => $e->id,
                'date' => $e->revenue_date->format('Y-m-d'),
                'amount' => (float) $e->amount,
                'description' => $e->description,
            ])
            ->all();

        return $builder->build($daily, $externals);
    }

    /**
     * Doanh thu năm HIỆN TẠI của chủ hộ (cộng dồn MỌI xe + các khoản ngoài Rót) so với ngưỡng, kèm
     * dự báo. Nhịp bán dùng để dự báo lấy từ tối đa 28 ngày trọn vẹn gần nhất (kết thúc HÔM QUA vì
     * hôm nay chưa bán xong) và không kéo về trước ngày có doanh thu đầu tiên — quán mới không bị
     * pha loãng bởi những ngày chưa mở bán.
     */
    private function thresholdOverview(User $user, RevenueThresholdTracker $tracker): array
    {
        $today = Carbon::today();
        $yesterday = $today->copy()->subDay();
        $yearStart = $today->copy()->startOfYear();

        $locations = $user->ownedLocations()->orderBy('id')->get(['id', 'name']);
        $ids = $locations->pluck('id')->all();

        $salesQuery = fn () => Order::query()->whereIn('location_id', $ids)->where('status', 'hoan_thanh');
        $externalQuery = fn () => ExternalRevenue::query()->whereIn('location_id', $ids);

        $salesByLocation = $salesQuery()
            ->where('completed_at', '>=', $yearStart)
            ->selectRaw('location_id, SUM(total) AS revenue')->groupBy('location_id')
            ->pluck('revenue', 'location_id');
        $externalByLocation = $externalQuery()
            ->whereBetween('revenue_date', [$yearStart->toDateString(), $today->toDateString()])
            ->selectRaw('location_id, SUM(amount) AS revenue')->groupBy('location_id')
            ->pluck('revenue', 'location_id');

        $breakdown = $locations->map(fn ($loc) => [
            'name' => $loc->name,
            'sales' => (float) $salesByLocation->get($loc->id, 0),
            'external' => (float) $externalByLocation->get($loc->id, 0),
        ])->map(fn ($row) => $row + ['total' => $row['sales'] + $row['external']])->all();

        $ytd = array_sum(array_column($breakdown, 'total'));

        // Ngày có doanh thu đầu tiên (mọi xe, mọi kênh) — mốc bắt đầu cửa sổ dự báo và năm sớm nhất để chọn.
        $firstDates = array_filter([
            ($firstSale = $salesQuery()->min('completed_at')) ? Carbon::parse($firstSale)->startOfDay() : null,
            ($firstExternal = $externalQuery()->min('revenue_date')) ? Carbon::parse($firstExternal)->startOfDay() : null,
        ]);
        $first = $firstDates ? min($firstDates) : null;

        $recentDays = 0;
        $recentRevenue = 0.0;
        if ($first) {
            $windowStart = $yesterday->copy()->subDays(self::RECENT_WINDOW_DAYS - 1)->startOfDay();
            if ($first->greaterThan($windowStart)) {
                $windowStart = $first->copy();
            }

            if ($windowStart->lessThanOrEqualTo($yesterday)) {
                $recentDays = (int) round(abs($windowStart->copy()->startOfDay()->diffInDays($yesterday->copy()->startOfDay()))) + 1;
                $recentRevenue = (float) $salesQuery()
                    ->whereBetween('completed_at', [$windowStart->copy()->startOfDay(), $yesterday->copy()->endOfDay()])
                    ->sum('total')
                    + (float) $externalQuery()
                        ->whereBetween('revenue_date', [$windowStart->toDateString(), $yesterday->toDateString()])
                        ->sum('amount');
            }
        }

        return $tracker->evaluate($ytd, $recentRevenue, $recentDays, $today, (float) config('tax.exempt_revenue_threshold')) + [
            'year' => $today->year,
            'recentDays' => $recentDays,
            'locations' => $breakdown,
            'multiLocation' => count($breakdown) > 1,
            'firstYear' => $first ? max(self::MIN_YEAR, $first->year) : $today->year,
            'basis' => (string) config('tax.threshold_basis'),
            'basisUpdated' => (string) config('tax.threshold_basis_updated'),
        ];
    }
}
