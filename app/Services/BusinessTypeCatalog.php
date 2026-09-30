<?php

namespace App\Services;

/**
 * Giải quyết bộ CHỮ + ICON hiển thị theo mô hình kinh doanh của 1 xe/quán
 * (v1.3.0). Đọc dữ liệu từ config/business_types.php — xem docblock ở đó để
 * biết lý do và cách thêm mô hình mới.
 *
 * Class THUẦN (chỉ đọc mảng, không đụng Eloquent/database) để kiểm thử độc
 * lập — Location::businessTerms() là nơi nối nó với dữ liệu thật.
 */
class BusinessTypeCatalog
{
    public const CUSTOM_KEY = 'other';

    /** @var array<string, array<string, string>> */
    private array $types;

    private string $default;

    /**
     * Nhận thẳng mảng cấu hình (không tự gọi config() bên trong) để class
     * này kiểm thử được độc lập, không cần bootstrap Laravel — nơi dùng thật
     * (Location::businessTerms()) truyền vào config('business_types').
     *
     * @param  array{types?: array<string, array<string, string>>, default?: string}  $config
     */
    public function __construct(array $config = [])
    {
        $this->types = $config['types'] ?? (require __DIR__.'/../../config/business_types.php')['types'];
        $this->default = $config['default'] ?? 'cafe';
    }

    /**
     * @param  string  $type  Khoá mô hình (VD: 'cafe'); không khớp khoá nào thì dùng mặc định.
     * @param  string|null  $customLabel  Chỉ dùng khi $type === 'other' — từ chủ quán tự đặt cho đơn vị bán (VD: "suất ăn").
     * @return array<string, string> Bộ chữ/icon đầy đủ, luôn đủ mọi khoá (không bao giờ thiếu key gây lỗi view).
     */
    public function terms(string $type, ?string $customLabel = null): array
    {
        $terms = $this->types[$type] ?? $this->types[$this->default] ?? [];

        if ($type === self::CUSTOM_KEY) {
            $terms = $this->composeOther($terms, $customLabel);
        }

        return ['key' => $type] + $terms;
    }

    /**
     * Danh sách mô hình để hiển thị bộ chọn (Cài đặt, form thêm địa điểm) —
     * theo ĐÚNG thứ tự khai báo trong config, "other" luôn ở cuối cho dễ tìm.
     *
     * @return array<int, array{key: string, name: string, description: string, icon: string}>
     */
    public function options(): array
    {
        $keys = array_filter(array_keys($this->types), fn ($k) => $k !== self::CUSTOM_KEY);
        if (isset($this->types[self::CUSTOM_KEY])) {
            $keys[] = self::CUSTOM_KEY;
        }

        return array_map(fn ($key) => [
            'key' => $key,
            'name' => $this->types[$key]['name'] ?? $key,
            'description' => $this->types[$key]['description'] ?? '',
            'icon' => $this->types[$key]['select_icon'] ?? 'fa-store',
        ], $keys);
    }

    public function isKnownType(string $type): bool
    {
        return array_key_exists($type, $this->types);
    }

    /**
     * "Khác": chủ quán chỉ gõ ĐÚNG 1 từ (đơn vị bán, VD: "suất ăn", "gói dịch
     * vụ") — mọi câu còn lại được ghép lại từ từ đó, thay vì bắt gõ cả chục
     * ô nhãn khác nhau. Không có từ tự đặt (chưa lưu, hoặc để trống) thì dùng
     * nguyên bộ chữ trung tính có sẵn trong config (mặt hàng/bảng giá...).
     */
    private function composeOther(array $terms, ?string $customLabel): array
    {
        $word = trim((string) $customLabel);
        if ($word === '') {
            return $terms;
        }

        $singular = mb_strtolower($word);
        $cap = mb_strtoupper(mb_substr($singular, 0, 1)).mb_substr($singular, 1);

        return array_merge($terms, [
            'catalog_singular' => $singular,
            'catalog_singular_cap' => $cap,
            'menu_label' => 'Bảng giá '.$singular,
            'menu_page_title' => 'Quản lý '.$singular,
            'modifier_label' => 'Tuỳ chọn '.$singular,
            'default_category_name' => $cap,
            'qr_toggle_label' => 'Đặt '.$singular.' qua QR',
            'qr_description' => 'Bật để khách quét mã QR, tự xem bảng giá và gửi yêu cầu đặt '.$singular.'.',
            'qr_save_button' => 'Lưu cài đặt đặt '.$singular.' qua QR',
            'qr_badge_title' => 'Yêu cầu đặt '.$singular.' từ khách (QR) đang chờ',
            'qr_page_tagline' => 'Xem bảng giá & gửi yêu cầu đặt '.$singular,
            'qr_submit_button' => 'Gửi yêu cầu đặt '.$singular,
        ]);
    }
}
