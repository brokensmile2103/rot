<?php

namespace App\Http\Controllers\Owner;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Concerns\ResolvesCurrentLocation;
use App\Models\Location;
use App\Services\SePayEInvoiceService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Mỗi mục cài đặt là 1 form/nút Lưu RIÊNG BIỆT (xem view) — tách theo đúng
 * từng khối chức năng độc lập, để sửa 1 mục không cần đụng tới toàn bộ trang
 * và dễ hiểu hơn là gộp chung 1 form khổng lồ.
 */
class SettingsController extends Controller
{
    use ResolvesCurrentLocation;
    /** Các màu preset — chọn sẵn để đảm bảo tương phản/thẩm mỹ, không cho tự do chọn màu tuỳ ý. */
    public const ACCENT_COLORS = ['amber', 'orange', 'blue', 'emerald', 'rose', 'violet'];

    public function index(Request $request): View
    {
        $location = $this->currentLocation($request);

        return view('owner.settings.index', [
            'location' => $location,
            'accentColors' => self::ACCENT_COLORS,
            'banks' => config('banks'),
        ]);
    }

    public function updateAppearance(Request $request): RedirectResponse
    {
        $location = $this->currentLocation($request);

        $data = $request->validate([
            'font_scale' => 'required|in:sm,md,lg',
            'accent_color' => 'required|in:'.implode(',', self::ACCENT_COLORS),
        ]);

        $location->update($data);

        return back()->with('status', 'Đã lưu giao diện.');
    }

    public function updateReceipt(Request $request): RedirectResponse
    {
        $location = $this->currentLocation($request);

        $data = $request->validate([
            'receipt_paper_width' => 'required|in:58,80',
        ]);
        $data['receipt_enabled'] = $request->boolean('receipt_enabled');

        $location->update($data);

        return back()->with('status', 'Đã lưu cài đặt in hoá đơn.');
    }

    public function updateBank(Request $request): RedirectResponse
    {
        $location = $this->currentLocation($request);

        // Dọn khoảng trắng/dấu gạch trong số tài khoản trước khi validate —
        // nhiều người quen gõ số tài khoản có giãn cách cho dễ đọc.
        if ($request->filled('bank_account_no')) {
            $request->merge(['bank_account_no' => preg_replace('/[\s-]/', '', $request->input('bank_account_no'))]);
        }

        $data = $request->validate([
            'bank_bin' => 'nullable|string|in:'.implode(',', array_keys(config('banks'))),
            'bank_account_no' => 'nullable|string|max:30|regex:/^[0-9]+$/',
            'bank_account_name' => 'nullable|string|max:100',
        ]);

        $location->update($data);

        $missingBankInfo = $data['bank_bin'] && (! $data['bank_account_no'] || ! $data['bank_account_name']);
        $status = $missingBankInfo
            ? 'Đã lưu. Lưu ý: cần điền đủ cả Số tài khoản và Tên chủ tài khoản thì QR chuyển khoản mới hiện được.'
            : 'Đã lưu tài khoản ngân hàng.';

        return back()->with('status', $status);
    }

    public function updateLoyalty(Request $request): RedirectResponse
    {
        $location = $this->currentLocation($request);

        $data = $request->validate([
            'points_earn_rate' => 'required|numeric|min:1',
            'points_redeem_value' => 'required|numeric|min:1',
        ]);
        $data['loyalty_enabled'] = $request->boolean('loyalty_enabled');

        $location->update($data);

        return back()->with('status', 'Đã lưu cài đặt khách hàng thân thiết.');
    }

    /**
     * v1.1.0 — Chi phí mặt bằng hàng THÁNG, dùng để phân bổ vào "Lợi nhuận
     * thực tế" ở trang Báo cáo (xem ReportController::operatingCosts()).
     * Mặc định 0 — quán không có mặt bằng cố định (xe đẩy thuần) để 0 là đủ,
     * không ảnh hưởng gì tới báo cáo.
     */
    public function updateCosts(Request $request): RedirectResponse
    {
        $location = $this->currentLocation($request);

        $data = $request->validate([
            'rent_cost' => 'required|numeric|min:0',
        ]);

        $location->update($data);

        return back()->with('status', 'Đã lưu chi phí mặt bằng.');
    }

    public function updateEinvoice(Request $request, SePayEInvoiceService $einvoiceService): RedirectResponse
    {
        $location = $this->currentLocation($request);

        $data = $request->validate([
            'einvoice_client_id' => 'nullable|string|max:255',
            'einvoice_provider_account_id' => 'nullable|string|max:255',
            'einvoice_template_code' => 'nullable|string|max:20',
            'einvoice_invoice_series' => 'nullable|string|max:20',
        ]);
        $data['einvoice_enabled'] = $request->boolean('einvoice_enabled');
        $data['einvoice_sandbox'] = $request->boolean('einvoice_sandbox');

        // Client Secret hiển thị dạng ẩn (••••) khi đã có sẵn — chỉ cập nhật
        // nếu người dùng thực sự nhập giá trị MỚI, để trống thì giữ nguyên
        // giá trị cũ (không vô tình xoá mất secret đã lưu).
        if ($request->filled('einvoice_client_secret')) {
            $data['einvoice_client_secret'] = $request->input('einvoice_client_secret');
        }

        // Đổi môi trường sandbox/production hoặc đổi tài khoản kết nối thì
        // token cũ (nếu có) không còn dùng được nữa — xoá để lần xuất hoá
        // đơn tiếp theo tự xin token mới.
        if ($location->einvoice_sandbox !== $data['einvoice_sandbox'] || $location->einvoice_client_id !== $data['einvoice_client_id']) {
            $data['einvoice_access_token'] = null;
            $data['einvoice_token_expires_at'] = null;
        }

        $location->update($data);

        $status = 'Đã lưu cài đặt hoá đơn điện tử.';

        // Tự kiểm tra kết nối ngay nếu đã bật + điền đủ — để biết ngay Client
        // ID/Secret đúng hay sai, không phải đợi tới lúc bán hàng thật mới
        // phát hiện lỗi.
        if ($location->einvoice_enabled && $location->hasEinvoiceConfig()) {
            $result = $einvoiceService->testConnection($location);
            $status .= ' '.($result['success'] ? '✅ ' : '❌ ').$result['message'];
        }

        return back()->with('status', $status);
    }

    public function updateQrOrdering(Request $request): RedirectResponse
    {
        $location = $this->currentLocation($request);

        $location->update(['qr_ordering_enabled' => $request->boolean('qr_ordering_enabled')]);

        return back()->with('status', $location->qr_ordering_enabled
            ? 'Đã bật đặt món qua QR — dán link/mã QR bên dưới cho khách quét.'
            : 'Đã tắt đặt món qua QR.');
    }

    /** Đổi sang link/QR mới — link cũ (nếu lỡ để lộ) sẽ không dùng được nữa. */
    public function regenerateQrToken(Request $request): RedirectResponse
    {
        $location = $this->currentLocation($request);
        $location->regeneratePublicToken();

        return back()->with('status', 'Đã đổi link/mã QR mới. Nhớ in/dán lại mã mới cho khách.');
    }

}
