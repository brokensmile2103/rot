<?php

namespace App\Services;

use App\Models\Location;
use App\Models\Order;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Tích hợp SePay eInvoice API — mỗi xe cà phê tự cấu hình client_id/secret
 * riêng của mình trong Cài đặt (tài khoản họ tự đăng ký với SePay), Rót chỉ
 * đóng vai trò gọi API hộ, KHÔNG có key dùng chung của Rót.
 *
 * Tài liệu tham khảo: https://developer.sepay.vn/vi/einvoice-api/v1/tong-quan
 */
class SePayEInvoiceService
{
    private function baseUrl(Location $location): string
    {
        return $location->einvoice_sandbox
            ? 'https://einvoice-api-sandbox.sepay.vn/v1'
            : 'https://einvoice-api.sepay.vn/v1';
    }

    /**
     * Lấy access token — dùng lại token đã cache nếu còn hạn (token sống 24h),
     * chỉ xin token mới khi hết hạn hoặc chưa từng lấy. Tránh gọi API xin
     * token thừa cho mỗi hoá đơn.
     */
    public function getAccessToken(Location $location): ?string
    {
        if ($location->einvoice_access_token && $location->einvoice_token_expires_at?->isFuture()) {
            return $location->einvoice_access_token;
        }

        $response = Http::timeout(10)
            ->withBasicAuth($location->einvoice_client_id, $location->einvoice_client_secret)
            ->post($this->baseUrl($location).'/token');

        if (! $response->successful() || ! $response->json('success')) {
            Log::warning('SePay eInvoice: lấy token thất bại', ['location_id' => $location->id, 'body' => $response->body()]);

            return null;
        }

        $token = $response->json('data.access_token');
        $expiresIn = $response->json('data.expires_in', 86400);

        $location->update([
            'einvoice_access_token' => $token,
            'einvoice_token_expires_at' => now()->addSeconds($expiresIn - 60), // trừ hao 60s cho an toàn
        ]);

        return $token;
    }

    /**
     * Kiểm tra kết nối — dùng cho nút "Kiểm tra kết nối" trong Cài đặt. Lấy
     * token rồi thử gọi API chi tiết tài khoản để xác nhận thông tin đúng.
     */
    public function testConnection(Location $location): array
    {
        $token = $this->getAccessToken($location);
        if (! $token) {
            return ['success' => false, 'message' => 'Sai Client ID hoặc Client Secret — không lấy được token xác thực.'];
        }

        if (! $location->einvoice_provider_account_id) {
            return ['success' => true, 'message' => 'Kết nối thành công! Còn thiếu Provider Account ID — nhập vào để hoàn tất.'];
        }

        $response = Http::timeout(10)
            ->withToken($token)
            ->get($this->baseUrl($location).'/provider-accounts/'.$location->einvoice_provider_account_id);

        if (! $response->successful() || ! $response->json('success')) {
            return ['success' => false, 'message' => 'Kết nối được nhưng không tìm thấy Provider Account ID này. Kiểm tra lại giá trị đã nhập.'];
        }

        $templates = collect($response->json('data.templates', []))
            ->map(fn ($t) => $t['invoice_label'] ?? ($t['template_code'].' - '.$t['invoice_series']))
            ->implode('; ');

        return ['success' => true, 'message' => 'Kết nối thành công! Các mẫu hoá đơn khả dụng: '.($templates ?: '(không có)')];
    }

    /**
     * Xuất hoá đơn điện tử cho 1 đơn hàng đã hoàn tất — gọi ngay sau khi
     * thanh toán. KHÔNG BAO GIỜ ném lỗi ra ngoài — nếu API lỗi/mạng lỗi, chỉ
     * ghi nhận trạng thái 'failed' trên đơn, KHÔNG được làm hỏng luồng bán
     * hàng (tiền đã thu, hàng đã giao, không thể vì hoá đơn điện tử mà huỷ
     * ngược giao dịch).
     */
    public function createInvoice(Order $order): void
    {
        $location = $order->location;

        if (! $location->hasEinvoiceConfig()) {
            return;
        }

        try {
            $token = $this->getAccessToken($location);
            if (! $token) {
                $order->update(['einvoice_status' => 'failed', 'einvoice_error' => 'Không lấy được token xác thực — kiểm tra lại Client ID/Secret trong Cài đặt.']);

                return;
            }

            $order->loadMissing('items.variant.product', 'customer');

            $items = $order->items->values()->map(function ($item, $index) {
                $qty = max(1, (int) $item->quantity);

                return [
                    'line_number' => $index + 1,
                    'line_type' => 1,
                    'item_name' => $item->variant->product->name.' ('.$item->variant->name.')',
                    'unit' => 'Ly',
                    'quantity' => $qty,
                    // Đơn giá THỰC LÃNH sau khi trừ giảm giá riêng của dòng (nếu có) — quy về
                    // đơn giá hiệu lực, để tổng các dòng khớp gần đúng với subtotal thật.
                    'unit_price' => round((float) $item->line_total / $qty),
                ];
            })->all();

            $payload = [
                'template_code' => $location->einvoice_template_code,
                'invoice_series' => $location->einvoice_invoice_series,
                'issued_date' => now()->format('Y-m-d H:i:s'),
                'currency' => 'VND',
                'provider_account_id' => $location->einvoice_provider_account_id,
                'reference_code' => 'ROT-'.$order->id.'-'.$order->completed_at?->timestamp,
                'payment_method' => match ($order->payment_method) {
                    'tien_mat' => 'TM',
                    'chuyen_khoan' => 'CK',
                    default => 'KHAC',
                },
                'is_draft' => false,
                'buyer' => [
                    'type' => 'personal',
                    'name' => $order->customer?->name ?: 'Khách lẻ',
                ],
                'items' => $items,
                'notes' => 'Đơn hàng #'.$order->id.' — '.$location->name,
                // Tổng tiền THẬT sau TẤT CẢ giảm giá (theo món + toàn đơn + điểm) — SePay cho phép
                // ghi đè total_amount mà không đối chiếu lại với các dòng hàng, nên đây là cách an
                // toàn nhất để đảm bảo số tiền trên hoá đơn khớp CHÍNH XÁC với số tiền khách đã trả,
                // thay vì cố quy đổi cơ chế giảm giá 2 tầng của Rót sang cú pháp chiết khấu của SePay.
                'total_amount' => (int) round((float) $order->total),
            ];

            $response = Http::timeout(15)->withToken($token)
                ->post($this->baseUrl($location).'/invoices/create', $payload);

            $body = $response->json();

            if (! $response->successful() || ! ($body['success'] ?? false)) {
                $order->update([
                    'einvoice_status' => 'failed',
                    'einvoice_error' => $body['error']['message'] ?? $body['message'] ?? 'Lỗi không xác định từ SePay ('.$response->status().')',
                ]);

                return;
            }

            $order->update([
                'einvoice_status' => 'pending',
                'einvoice_tracking_code' => $body['data']['tracking_code'] ?? null,
                'einvoice_reference_code' => $payload['reference_code'],
                'einvoice_error' => null,
            ]);
        } catch (\Throwable $e) {
            Log::error('SePay eInvoice: lỗi khi xuất hoá đơn', ['order_id' => $order->id, 'error' => $e->getMessage()]);
            $order->update(['einvoice_status' => 'failed', 'einvoice_error' => 'Lỗi kết nối tới SePay: '.$e->getMessage()]);
        }
    }

    /** Kiểm tra lại trạng thái 1 hoá đơn đang "pending" — dùng cho nút "Kiểm tra trạng thái" trong danh sách đơn. */
    public function checkStatus(Order $order): void
    {
        $location = $order->location;

        if (! $order->einvoice_tracking_code) {
            return;
        }

        try {
            $token = $this->getAccessToken($location);
            if (! $token) {
                return;
            }

            $response = Http::timeout(10)->withToken($token)
                ->get($this->baseUrl($location).'/invoices/create/check/'.$order->einvoice_tracking_code);

            $body = $response->json();
            $status = $body['data']['status'] ?? null;

            if ($status === 'Success') {
                $order->update([
                    'einvoice_status' => 'issued',
                    'einvoice_pdf_url' => $body['data']['invoice']['pdf_url'] ?? null,
                    'einvoice_error' => null,
                ]);
            } elseif ($status === 'Failed') {
                $order->update([
                    'einvoice_status' => 'failed',
                    'einvoice_error' => $body['data']['message'] ?? 'Phát hành thất bại phía SePay.',
                ]);
            }
            // status = Pending: giữ nguyên, thử lại sau.
        } catch (\Throwable $e) {
            Log::error('SePay eInvoice: lỗi khi kiểm tra trạng thái', ['order_id' => $order->id, 'error' => $e->getMessage()]);
        }
    }
}
