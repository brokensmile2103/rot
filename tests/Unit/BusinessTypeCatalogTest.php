<?php

namespace Tests\Unit;

use App\Services\BusinessTypeCatalog;
use PHPUnit\Framework\TestCase;

class BusinessTypeCatalogTest extends TestCase
{
    private function catalog(): BusinessTypeCatalog
    {
        // Dùng đúng file config thật (không mock) để test luôn khớp với dữ liệu Rót đang chạy.
        return new BusinessTypeCatalog(require __DIR__.'/../../config/business_types.php');
    }

    public function test_lay_dung_bo_chu_theo_khoa(): void
    {
        $terms = $this->catalog()->terms('salon');
        $this->assertSame('salon', $terms['key']);
        $this->assertSame('dịch vụ', $terms['catalog_singular']);
        $this->assertSame('Kho vật tư', $terms['ingredient_label']);
    }

    public function test_khoa_khong_ton_tai_thi_dung_mac_dinh_cafe(): void
    {
        $terms = $this->catalog()->terms('mo-hinh-khong-ton-tai');
        $this->assertSame('Thực đơn', $terms['menu_label']);
        $this->assertSame('mo-hinh-khong-ton-tai', $terms['key']); // giữ nguyên key gốc để nơi gọi biết đang fallback
    }

    public function test_mo_hinh_khac_ghep_cau_tu_1_tu_chu_quan_tu_dat(): void
    {
        $terms = $this->catalog()->terms('other', 'suất ăn');
        $this->assertSame('suất ăn', $terms['catalog_singular']);
        $this->assertSame('Suất ăn', $terms['catalog_singular_cap']);
        $this->assertSame('Bảng giá suất ăn', $terms['menu_label']);
        $this->assertSame('Đặt suất ăn qua QR', $terms['qr_toggle_label']);
        $this->assertSame('Gửi yêu cầu đặt suất ăn', $terms['qr_submit_button']);
    }

    public function test_mo_hinh_khac_chua_dat_ten_thi_dung_chu_trung_tinh(): void
    {
        $terms = $this->catalog()->terms('other', null);
        $this->assertSame('mặt hàng', $terms['catalog_singular']);

        $terms2 = $this->catalog()->terms('other', '   ');
        $this->assertSame('mặt hàng', $terms2['catalog_singular']);
    }

    public function test_ghep_cau_viet_hoa_dung_chu_dau_ke_ca_tieng_viet_co_dau(): void
    {
        $terms = $this->catalog()->terms('other', 'ổ bánh mì');
        $this->assertSame('Ổ bánh mì', $terms['catalog_singular_cap']);
    }

    public function test_danh_sach_option_du_4_mo_hinh_va_other_o_cuoi(): void
    {
        $keys = array_column($this->catalog()->options(), 'key');
        $this->assertSame(['cafe', 'food', 'salon', 'other'], $keys);
    }

    public function test_isKnownType(): void
    {
        $catalog = $this->catalog();
        $this->assertTrue($catalog->isKnownType('food'));
        $this->assertFalse($catalog->isKnownType('abc'));
    }

    public function test_moi_mo_hinh_dinh_nghia_san_deu_co_du_cac_khoa_can_thiet(): void
    {
        $required = [
            'name', 'select_icon', 'app_icon', 'order_tab_icon', 'menu_icon', 'menu_label',
            'menu_page_title', 'catalog_singular', 'catalog_singular_cap', 'modifier_label',
            'ingredient_label', 'ingredient_singular', 'ingredient_name_placeholder', 'ingredient_example_placeholder',
            'locations_label', 'locations_icon', 'add_location_label', 'location_name_placeholder',
            'default_category_name', 'location_singular', 'qr_toggle_label', 'qr_description', 'qr_save_button',
            'qr_badge_title', 'qr_page_tagline', 'qr_submit_button', 'quick_setup_description',
        ];

        $catalog = $this->catalog();
        foreach (['cafe', 'food', 'salon', 'other'] as $type) {
            $terms = $catalog->terms($type);
            foreach ($required as $key) {
                $this->assertArrayHasKey($key, $terms, "Mô hình '$type' thiếu khoá '$key'");
                $this->assertNotSame('', trim((string) $terms[$key]), "Mô hình '$type' có khoá '$key' rỗng");
            }
        }
    }
}
