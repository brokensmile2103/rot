<?php

namespace App\Http\Controllers\Owner;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Concerns\ResolvesCurrentLocation;
use App\Models\Location;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Hash;
use Illuminate\View\View;

class StaffController extends Controller
{
    use ResolvesCurrentLocation;

    public function index(Request $request): View
    {
        $location = $this->currentLocation($request);
        $staff = $location->staff()->where('role', 'staff')->get();

        return view('owner.staff.index', compact('location', 'staff'));
    }

    public function store(Request $request): RedirectResponse
    {
        $location = $this->currentLocation($request);

        $data = $request->validate([
            'name' => 'required|string|max:100',
            'phone' => 'required|string|max:20|unique:users,phone',
            'password' => 'required|string|min:8',
        ]);

        $staff = User::forceCreate([
            'name' => $data['name'],
            'phone' => $data['phone'],
            'password' => Hash::make($data['password']),
            'role' => 'staff',
            'is_active' => true,
        ]);

        $location->staff()->attach($staff->id);

        return back()->with('status', 'Đã thêm tài khoản nhân viên.');
    }

    public function toggleActive(Request $request, int $user): RedirectResponse
    {
        $location = $this->currentLocation($request);
        $staff = $location->staff()->where('role', 'staff')->findOrFail($user);
        $staff->forceFill(['is_active' => ! $staff->is_active])->save();

        return back()->with('status', $staff->is_active ? 'Đã kích hoạt lại tài khoản.' : 'Đã khoá tài khoản nhân viên.');
    }

    public function resetPassword(Request $request, int $user): RedirectResponse
    {
        $location = $this->currentLocation($request);
        $staff = $location->staff()->where('role', 'staff')->findOrFail($user);

        $data = $request->validate([
            'password' => 'required|string|min:8',
        ]);

        $staff->update(['password' => Hash::make($data['password'])]);

        return back()->with('status', 'Đã đổi mật khẩu cho '.$staff->name.'.');
    }

    /**
     * v1.1.0 — Lương nhân viên, theo giờ hoặc theo tháng, dùng để tính "Lợi
     * nhuận thực tế" ở trang Báo cáo (xem ReportController::operatingCosts()).
     *
     * Lương theo GIỜ khớp chính xác theo thời lượng từng ca ĐÃ CHỐT của
     * chính nhân viên đó (giờ đóng ca − giờ mở ca) — không phải giờ hành
     * chính cố định, vì Rót vốn đã ghi lại đúng giờ mở/đóng ca thật của từng
     * người, dùng luôn số liệu có sẵn thay vì bắt nhập tay giờ công riêng.
     *
     * Để trống "Loại lương" (salary_type = null) nghĩa là CHƯA thiết lập
     * lương cho người này — Báo cáo sẽ không tính bất kỳ chi phí nhân sự nào
     * cho họ, không mặc định ép về 0 hay một con số đoán bừa nào cả.
     */
    public function updateSalary(Request $request, int $user): RedirectResponse
    {
        $location = $this->currentLocation($request);
        $staff = $location->staff()->where('role', 'staff')->findOrFail($user);

        $data = $request->validate([
            'salary_type' => 'nullable|in:hourly,monthly',
            'salary_amount' => 'required_with:salary_type|nullable|numeric|min:0',
        ]);

        // Bỏ chọn "Chưa thiết lập" thì xoá luôn số tiền cũ — tránh trường hợp
        // salary_type null nhưng salary_amount vẫn còn giá trị cũ treo lại,
        // gây hiểu lầm nếu sau này bật lại loại lương mà quên nhập lại số tiền.
        if (empty($data['salary_type'])) {
            $data['salary_type'] = null;
            $data['salary_amount'] = null;
        }

        $staff->update($data);

        return back()->with('status', 'Đã cập nhật lương cho '.$staff->name.'.');
    }

    public function remove(Request $request, int $user): RedirectResponse
    {
        $location = $this->currentLocation($request);
        $staff = $location->staff()->where('role', 'staff')->findOrFail($user);
        $location->staff()->detach($staff->id);

        return back()->with('status', 'Đã gỡ nhân viên khỏi xe này.');
    }

    /**
     * v1.1.1 — Bảng công/chấm công theo THÁNG (khớp chu kỳ trả lương phổ biến
     * ở VN, không cần chọn Ngày/Tuần rắc rối như trang Báo cáo).
     *
     * "Tổng giờ làm" tính từ CHÍNH XÁC các ca ĐÃ CHỐT có giờ mở ca trong
     * tháng đang xem — cùng nguồn dữ liệu, cùng công thức với
     * ReportController::operatingCosts() nên số liệu 2 trang luôn khớp
     * nhau. Nhân viên lương THÁNG vẫn hiện tổng giờ làm để chủ quán tham
     * khảo chuyên cần, nhưng "Lương ước tính" của họ luôn là đúng 1 mức cố
     * định mỗi tháng (không nhân theo giờ).
     */
    public function timesheet(Request $request): View
    {
        $location = $this->currentLocation($request);

        $month = $request->query('month')
            ? Carbon::parse($request->query('month').'-01')
            : Carbon::today();
        $start = $month->copy()->startOfMonth();
        $end = $month->copy()->endOfMonth();

        $staff = $location->staff()->where('role', 'staff')->orderBy('name')->get();

        $rows = $staff->map(function (User $s) use ($location, $start, $end) {
            $shifts = $location->shifts()
                ->where('user_id', $s->id)
                ->whereNotNull('closed_at')
                ->whereBetween('opened_at', [$start, $end])
                ->get();

            $totalHours = $shifts->sum(fn ($shift) => $shift->opened_at->diffInMinutes($shift->closed_at) / 60);

            $estimatedPay = match ($s->salary_type) {
                'hourly' => $totalHours * (float) $s->salary_amount,
                'monthly' => (float) $s->salary_amount,
                default => null,
            };

            return [
                'user' => $s,
                'shift_count' => $shifts->count(),
                'total_hours' => $totalHours,
                'estimated_pay' => $estimatedPay,
            ];
        });

        return view('owner.staff.timesheet', [
            'location' => $location,
            'month' => $start,
            'rows' => $rows,
            'totalEstimatedPay' => $rows->sum('estimated_pay'),
        ]);
    }

}
