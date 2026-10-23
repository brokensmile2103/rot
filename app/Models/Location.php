<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class Location extends Model
{
    /**
     * 'owner_id' và 'is_active' CỐ TÌNH không nằm trong $fillable — xem giải
     * thích chi tiết ở bản Rót (app/Models/Location.php), lý do giống hệt.
     * 'public_token' cũng KHÔNG nằm trong $fillable cùng lý do — chỉ được
     * sinh ra ở backend (xem booted()/regeneratePublicToken()), không
     * bao giờ nhận trực tiếp từ input người dùng.
     */
    protected $fillable = [
        'name', 'address', 'tax_household_name', 'tax_code', 'business_type', 'business_type_label', 'font_scale', 'accent_color',
        'receipt_paper_width', 'receipt_enabled', 'bank_bin', 'bank_account_no', 'bank_account_name', 'bank_memo',
        'loyalty_enabled', 'points_earn_rate', 'points_redeem_value',
        'einvoice_enabled', 'einvoice_sandbox', 'einvoice_client_id', 'einvoice_client_secret',
        'einvoice_provider_account_id', 'einvoice_template_code', 'einvoice_invoice_series',
        'einvoice_access_token', 'einvoice_token_expires_at', 'rent_cost', 'qr_ordering_enabled', 'prep_queue_enabled',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean', 'receipt_enabled' => 'boolean', 'loyalty_enabled' => 'boolean',
            'points_earn_rate' => 'decimal:2', 'points_redeem_value' => 'decimal:2',
            'einvoice_enabled' => 'boolean', 'einvoice_sandbox' => 'boolean',
            'rent_cost' => 'decimal:2', 'qr_ordering_enabled' => 'boolean', 'prep_queue_enabled' => 'boolean',
            // Mã hoá bằng APP_KEY — dữ liệu nhạy cảm (client_secret, access_token) không bao giờ
            // lưu ở dạng đọc được trực tiếp trong database, kể cả khi ai đó truy cập được DB.
            'einvoice_client_secret' => 'encrypted',
            'einvoice_access_token' => 'encrypted',
            'einvoice_token_expires_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        // Luôn có sẵn public_token NGAY khi tạo quán mới — tránh trường hợp
        // owner vào Cài đặt bật "Đặt món qua QR" ngay sau khi tạo quán mà
        // chưa có token nào để dùng.
        static::creating(function (Location $location) {
            $location->public_token ??= Str::random(32);
        });
    }

    public function regeneratePublicToken(): void
    {
        // Đổi token mới — link/QR cũ (nếu lỡ lộ ra ngoài ý muốn, VD: dán ở
        // nơi công cộng rồi tháo đi) sẽ hết tác dụng ngay lập tức.
        $this->update(['public_token' => Str::random(32)]);
    }

    public function publicMenuUrl(): string
    {
        return route('public.menu.show', $this->public_token);
    }

    public function customerOrderRequests()
    {
        return $this->hasMany(CustomerOrderRequest::class);
    }

    /** Doanh thu ngoài Rót do chủ quán tự nhập — xem ExternalRevenue. */
    public function externalRevenues()
    {
        return $this->hasMany(ExternalRevenue::class);
    }

    /**
     * Bộ chữ/icon hiển thị theo mô hình kinh doanh của xe này (Cải tiến) — xem
     * App\Services\BusinessTypeCatalog và config/business_types.php. Dùng ở
     * layout (nav), trang Thực đơn/Kho/Cài đặt, và trang menu công khai cho
     * khách quét QR.
     */
    public function businessTerms(): array
    {
        return app(\App\Services\BusinessTypeCatalog::class)->terms(
            $this->business_type ?? 'cafe',
            $this->business_type_label,
        );
    }

    public function owner()
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    public function staff()
    {
        return $this->belongsToMany(User::class);
    }

    public function categories()
    {
        return $this->hasMany(Category::class);
    }

    public function shifts()
    {
        return $this->hasMany(Shift::class);
    }

    public function ingredients()
    {
        return $this->hasMany(Ingredient::class);
    }

    public function customers()
    {
        return $this->hasMany(Customer::class);
    }

    /**
     * Toàn bộ đơn của xe — Super Admin chỉ dùng để đếm/thống kê hoạt động (không đọc nội dung
     * đơn); "Xuất dữ liệu" của chính chủ quán dùng để xuất đơn của xe mình.
     */
    public function orders()
    {
        return $this->hasMany(Order::class);
    }

    /** Đơn ĐÃ HOÀN THÀNH — dùng để tính "còn hoạt động hay không" mà không tính nhầm đơn nháp/đã huỷ. */
    public function completedOrders()
    {
        return $this->hasMany(Order::class)->where('status', 'hoan_thanh');
    }

    public function hasEinvoiceConfig(): bool
    {
        return $this->einvoice_enabled
            && $this->einvoice_client_id
            && $this->einvoice_client_secret
            && $this->einvoice_provider_account_id
            && $this->einvoice_template_code
            && $this->einvoice_invoice_series;
    }

    public const DEFAULT_BANK_MEMO = 'Thanh toan don hang';

    /** Nội dung chuyển khoản điền sẵn vào VietQR. Rỗng = không điền sẵn (ngân hàng tự xử lý). */
    public function bankMemo(): string
    {
        $memo = array_key_exists('bank_memo', $this->attributes)
            ? (string) $this->attributes['bank_memo']
            : self::DEFAULT_BANK_MEMO;

        return self::cleanMemo($memo);
    }

    /** Bỏ dấu và ký tự lạ (nhiều app ngân hàng từ chối nội dung có dấu/ký tự đặc biệt). */
    public static function cleanMemo(string $memo): string
    {
        return trim(preg_replace('/\s+/', ' ', preg_replace('/[^A-Za-z0-9 \-]/', '', \Illuminate\Support\Str::ascii($memo))));
    }

    public function hasBankAccount(): bool
    {
        return ! empty($this->bank_bin) && ! empty($this->bank_account_no) && ! empty($this->bank_account_name);
    }

    /**
     * Sinh URL ảnh QR chuyển khoản theo chuẩn VietQR (Napas) — dùng dịch vụ
     * Quick Link công khai của img.vietqr.io, KHÔNG cần API key/secret gì cả
     * (chỉ cần số tài khoản, vốn đã công khai để nhận tiền). An toàn tuyệt đối
     * về mặt bảo mật vì không có thông tin nhạy cảm nào được gửi đi.
     */
    public function vietQrUrl(float $amount, ?string $addInfo = null): ?string
    {
        if (! $this->hasBankAccount()) {
            return null;
        }

        $query = [
            'amount' => (int) round($amount),
            'accountName' => $this->bank_account_name ?? '',
        ];

        $memo = $addInfo ?? $this->bankMemo();
        if ($memo !== '') {
            $query['addInfo'] = $memo;
        }

        $params = http_build_query($query);

        return "https://img.vietqr.io/image/{$this->bank_bin}-{$this->bank_account_no}-compact2.png?{$params}";
    }

    public function modifierGroups()
    {
        return $this->hasMany(ModifierGroup::class);
    }

    public function openShiftFor(User $user): ?Shift
    {
        return $this->shifts()
            ->where('user_id', $user->id)
            ->whereNull('closed_at')
            ->latest('opened_at')
            ->first();
    }
}
