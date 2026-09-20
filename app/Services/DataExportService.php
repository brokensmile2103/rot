<?php

namespace App\Services;

use App\Models\Location;
use Illuminate\Support\Facades\Storage;
use ZipArchive;

/**
 * Xuất TOÀN BỘ dữ liệu của 1 quán (location) ra 1 file ZIP gồm nhiều CSV,
 * mỗi bảng dữ liệu 1 file — phục vụ chủ quán tự sao lưu / mang đi nơi khác,
 * không phụ thuộc vào Rót nữa nếu họ không muốn dùng tiếp.
 *
 * Dùng LẠI đúng quy ước CSV đã có sẵn ở ReportController::export() để trải
 * nghiệm nhất quán trong toàn app:
 * - BOM UTF-8 đầu file để Excel đọc đúng tiếng Việt có dấu.
 * - Dấu CHẤM PHẨY (;) làm phân cách cột (Excel vùng Việt Nam hiểu dấu phẩy
 *   là ký tự thập phân nên dùng dấu phẩy sẽ dồn cột).
 * - Số tiền/số lượng xuất dạng SỐ THẬT (không có dấu chấm ngăn cách hàng
 *   nghìn) để Excel tính toán được ngay, không bị hiểu nhầm thành chữ.
 *
 * Cố tình KHÔNG xuất bảng `settings` (cấu hình toàn hệ thống, không thuộc
 * riêng quán nào) và mật khẩu/token nhạy cảm (không có trường nào như vậy
 * lọt vào các CSV bên dưới — chỉ toàn dữ liệu nghiệp vụ của quán).
 */
class DataExportService
{
    /**
     * Sinh file ZIP tạm trong storage, trả về đường dẫn tuyệt đối trên đĩa.
     * Người gọi (controller) chịu trách nhiệm stream về client rồi tự xoá
     * file tạm này sau khi gửi xong.
     */
    public function buildZip(Location $location): string
    {
        $tmpDir = storage_path('app/tmp');
        if (! is_dir($tmpDir)) {
            mkdir($tmpDir, 0755, true);
        }

        $zipPath = $tmpDir.'/rot-export-'.$location->id.'-'.now()->format('YmdHis').'-'.uniqid().'.zip';

        $zip = new ZipArchive();
        $zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE);

        foreach ($this->buildDatasets($location) as $filename => $rows) {
            $zip->addFromString($filename, $this->toCsv($rows['header'], $rows['rows']));
        }

        $zip->close();

        return $zipPath;
    }

    /**
     * Từng dataset là 1 CSV riêng — trả về mảng ['header' => [...], 'rows' => [[...], ...]].
     * Đặt tên file có số thứ tự đầu (00-, 01-...) để khi giải nén ra, thứ tự
     * file hiện đúng theo mạch dữ liệu (thông tin xe → thực đơn → kho → đơn hàng...),
     * dễ đọc hơn là để hệ điều hành tự sắp xếp theo alphabet.
     */
    private function buildDatasets(Location $location): array
    {
        return [
            '00-thong-tin-xe.csv' => $this->locationInfo($location),
            '01-danh-muc.csv' => $this->categories($location),
            '02-mon.csv' => $this->products($location),
            '03-bien-the.csv' => $this->variants($location),
            '04-cong-thuc.csv' => $this->recipes($location),
            '05-nhom-tuy-chon.csv' => $this->modifierGroups($location),
            '06-tuy-chon.csv' => $this->modifiers($location),
            '07-cong-thuc-tuy-chon.csv' => $this->modifierRecipes($location),
            '08-kho-nguyen-lieu.csv' => $this->ingredients($location),
            '09-nhap-kho.csv' => $this->stockIns($location),
            '10-khach-hang.csv' => $this->customers($location),
            '11-ca-lam-viec.csv' => $this->shifts($location),
            '12-so-quy.csv' => $this->cashAdjustments($location),
            '13-don-hang.csv' => $this->orders($location),
            '14-chi-tiet-don-hang.csv' => $this->orderItems($location),
            '15-tuy-chon-trong-don.csv' => $this->orderItemModifiers($location),
            '16-dieu-chinh-kho.csv' => $this->stockAdjustments($location),
            '17-doanh-thu-ngoai-rot.csv' => $this->externalRevenues($location),
        ];
    }

    private function locationInfo(Location $location): array
    {
        return [
            'header' => ['Trường', 'Giá trị'],
            'rows' => [
                ['Tên quán', $location->name],
                ['Địa chỉ', $location->address],
                ['Cỡ chữ', $location->font_scale],
                ['Màu chủ đạo', $location->accent_color],
                ['Khổ giấy in hoá đơn', $location->receipt_paper_width],
                ['Bật in hoá đơn', $location->receipt_enabled ? 'Có' : 'Không'],
                ['Ngân hàng (BIN)', $location->bank_bin],
                ['Số tài khoản', $location->bank_account_no],
                ['Tên chủ tài khoản', $location->bank_account_name],
                ['Bật tích điểm', $location->loyalty_enabled ? 'Có' : 'Không'],
                ['Tỷ lệ tích điểm (đ/điểm)', $location->points_earn_rate],
                ['Giá trị quy đổi 1 điểm (đ)', $location->points_redeem_value],
                ['Ngày tạo', optional($location->created_at)->format('d/m/Y H:i')],
                ['Xuất dữ liệu lúc', now()->format('d/m/Y H:i')],
            ],
        ];
    }

    private function categories(Location $location): array
    {
        $rows = $location->categories()->withTrashed()->orderBy('sort_order')->get();

        return [
            'header' => ['ID', 'Tên danh mục', 'Thứ tự', 'Đã xoá', 'Ngày tạo'],
            'rows' => $rows->map(fn ($c) => [
                $c->id, $c->name, $c->sort_order, $c->trashed() ? 'Có' : 'Không',
                optional($c->created_at)->format('d/m/Y H:i'),
            ])->all(),
        ];
    }

    private function products(Location $location): array
    {
        $products = \App\Models\Product::withTrashed()
            ->whereIn('category_id', $location->categories()->withTrashed()->pluck('id'))
            ->with('category')
            ->get();

        return [
            'header' => ['ID', 'Danh mục', 'Tên món', 'Đang bán', 'Đã xoá', 'Ngày tạo'],
            'rows' => $products->map(fn ($p) => [
                $p->id, $p->category->name ?? '', $p->name,
                $p->is_available ? 'Có' : 'Không', $p->trashed() ? 'Có' : 'Không',
                optional($p->created_at)->format('d/m/Y H:i'),
            ])->all(),
        ];
    }

    private function variants(Location $location): array
    {
        $productIds = \App\Models\Product::withTrashed()
            ->whereIn('category_id', $location->categories()->withTrashed()->pluck('id'))
            ->pluck('id');

        $variants = \App\Models\ProductVariant::withTrashed()
            ->whereIn('product_id', $productIds)
            ->with('product')
            ->get();

        return [
            'header' => ['ID', 'Món', 'Tên size', 'Giá bán (đ)', 'Mặc định', 'Đã xoá'],
            'rows' => $variants->map(fn ($v) => [
                $v->id, $v->product->name ?? '', $v->name, round((float) $v->price),
                $v->is_default ? 'Có' : 'Không', $v->trashed() ? 'Có' : 'Không',
            ])->all(),
        ];
    }

    private function recipes(Location $location): array
    {
        $productIds = \App\Models\Product::withTrashed()
            ->whereIn('category_id', $location->categories()->withTrashed()->pluck('id'))
            ->pluck('id');
        $variantIds = \App\Models\ProductVariant::withTrashed()->whereIn('product_id', $productIds)->pluck('id');

        $recipes = \App\Models\Recipe::whereIn('product_variant_id', $variantIds)
            ->with(['variant.product', 'ingredient'])
            ->get();

        return [
            'header' => ['Món', 'Size', 'Nguyên liệu', 'Định lượng', 'Đơn vị'],
            'rows' => $recipes->map(fn ($r) => [
                $r->variant->product->name ?? '', $r->variant->name ?? '',
                $r->ingredient->name ?? '(đã xoá)', (float) $r->quantity, $r->ingredient->unit ?? '',
            ])->all(),
        ];
    }

    private function modifierGroups(Location $location): array
    {
        $groups = $location->modifierGroups()->withTrashed()->get();

        return [
            'header' => ['ID', 'Tên nhóm', 'Bắt buộc chọn', 'Cho chọn nhiều', 'Đã xoá'],
            'rows' => $groups->map(fn ($g) => [
                $g->id, $g->name, $g->is_required ? 'Có' : 'Không',
                $g->allow_multiple ? 'Có' : 'Không', $g->trashed() ? 'Có' : 'Không',
            ])->all(),
        ];
    }

    private function modifiers(Location $location): array
    {
        $groupIds = $location->modifierGroups()->withTrashed()->pluck('id');
        $modifiers = \App\Models\Modifier::withTrashed()->whereIn('modifier_group_id', $groupIds)->with('group')->get();

        return [
            'header' => ['ID', 'Nhóm', 'Tên tuỳ chọn', 'Phụ thu (đ)', 'Mặc định', 'Đã xoá'],
            'rows' => $modifiers->map(fn ($m) => [
                $m->id, $m->group->name ?? '', $m->name, round((float) $m->extra_price),
                $m->is_default ? 'Có' : 'Không', $m->trashed() ? 'Có' : 'Không',
            ])->all(),
        ];
    }

    private function modifierRecipes(Location $location): array
    {
        $groupIds = $location->modifierGroups()->withTrashed()->pluck('id');
        $modifierIds = \App\Models\Modifier::withTrashed()->whereIn('modifier_group_id', $groupIds)->pluck('id');

        $recipes = \App\Models\ModifierRecipe::whereIn('modifier_id', $modifierIds)
            ->with(['modifier', 'ingredient'])
            ->get();

        return [
            'header' => ['Tuỳ chọn', 'Nguyên liệu', 'Định lượng', 'Đơn vị'],
            'rows' => $recipes->map(fn ($r) => [
                $r->modifier->name ?? '', $r->ingredient->name ?? '(đã xoá)',
                (float) $r->quantity, $r->ingredient->unit ?? '',
            ])->all(),
        ];
    }

    private function ingredients(Location $location): array
    {
        $ingredients = $location->ingredients()->withTrashed()->get();

        return [
            'header' => ['ID', 'Tên nguyên liệu', 'Đơn vị', 'Tồn kho', 'Giá vốn TB/đơn vị (đ)', 'Ngưỡng cảnh báo', 'Đã xoá'],
            'rows' => $ingredients->map(fn ($i) => [
                $i->id, $i->name, $i->unit, (float) $i->current_stock,
                (float) $i->avg_cost_per_unit, (float) $i->low_stock_threshold,
                $i->trashed() ? 'Có' : 'Không',
            ])->all(),
        ];
    }

    private function stockIns(Location $location): array
    {
        $ingredientIds = $location->ingredients()->withTrashed()->pluck('id');
        $stockIns = \App\Models\StockIn::whereIn('ingredient_id', $ingredientIds)->with(['ingredient', 'creator'])->get();

        return [
            'header' => ['ID', 'Nguyên liệu', 'Số lượng', 'Tổng tiền (đ)', 'Ghi chú', 'Người nhập', 'Thời gian'],
            'rows' => $stockIns->map(fn ($s) => [
                $s->id, $s->ingredient->name ?? '(đã xoá)', (float) $s->quantity, round((float) $s->total_cost),
                $s->note, $s->creator->name ?? '', optional($s->created_at)->format('d/m/Y H:i'),
            ])->all(),
        ];
    }

    /** Nhật ký sửa trực tiếp tồn kho/giá vốn (v1.1.3) — đặt sau các file cũ để không đổi số thứ tự file đã có. */
    private function stockAdjustments(Location $location): array
    {
        $rows = \App\Models\StockAdjustment::where('location_id', $location->id)
            ->with(['ingredient', 'user'])
            ->orderBy('id')
            ->get();

        return [
            'header' => [
                'ID', 'Nguyên liệu', 'Lý do', 'Tồn kho trước', 'Tồn kho sau', 'Chênh lệch tồn kho',
                'Giá vốn TB trước (đ)', 'Giá vốn TB sau (đ)', 'Ghi chú', 'Người sửa', 'Thời gian',
            ],
            'rows' => $rows->map(fn ($a) => [
                $a->id, $a->ingredient->name ?? '(đã xoá)', $a->reasonLabel(),
                (float) $a->stock_before, (float) $a->stock_after, $a->stockDelta(),
                (float) $a->cost_before, (float) $a->cost_after,
                $a->note, $a->user->name ?? '', optional($a->created_at)->format('d/m/Y H:i'),
            ])->all(),
        ];
    }

    /** v1.2.0 — Doanh thu ngoài Rót do chủ quán tự nhập (dùng cho Sổ doanh thu và theo dõi ngưỡng thuế). */
    private function externalRevenues(Location $location): array
    {
        $rows = \App\Models\ExternalRevenue::where('location_id', $location->id)
            ->with('user')->orderBy('revenue_date')->orderBy('id')->get();

        return [
            'header' => ['ID', 'Ngày', 'Số tiền (đ)', 'Nội dung', 'Người nhập', 'Nhập lúc'],
            'rows' => $rows->map(fn ($e) => [
                $e->id, $e->revenue_date->format('d/m/Y'), round((float) $e->amount), $e->description,
                $e->user->name ?? '', optional($e->created_at)->format('d/m/Y H:i'),
            ])->all(),
        ];
    }

    private function customers(Location $location): array
    {
        $customers = $location->customers()->withTrashed()->get();

        return [
            'header' => ['ID', 'Tên', 'Số điện thoại', 'Điểm hiện có', 'Tổng chi tiêu (đ)', 'Đã xoá'],
            'rows' => $customers->map(fn ($c) => [
                $c->id, $c->name, $c->phone, $c->points, round((float) $c->total_spent),
                $c->trashed() ? 'Có' : 'Không',
            ])->all(),
        ];
    }

    private function shifts(Location $location): array
    {
        $shifts = $location->shifts()->with('user')->orderBy('opened_at')->get();

        return [
            'header' => ['ID', 'Nhân viên', 'Tiền đầu ca (đ)', 'Tiền cuối ca dự kiến (đ)', 'Tiền cuối ca thực tế (đ)', 'Chênh lệch (đ)', 'Mở ca lúc', 'Đóng ca lúc'],
            'rows' => $shifts->map(fn ($s) => [
                $s->id, $s->user->name ?? '', round((float) $s->opening_cash),
                is_null($s->closing_cash_expected) ? '' : round((float) $s->closing_cash_expected),
                is_null($s->closing_cash_actual) ? '' : round((float) $s->closing_cash_actual),
                is_null($s->variance) ? '' : round((float) $s->variance),
                optional($s->opened_at)->format('d/m/Y H:i'),
                optional($s->closed_at)->format('d/m/Y H:i'),
            ])->all(),
        ];
    }

    private function cashAdjustments(Location $location): array
    {
        $shiftIds = $location->shifts()->pluck('id');
        $adjustments = \App\Models\CashAdjustment::whereIn('shift_id', $shiftIds)->orderBy('created_at')->get();

        $typeLabels = ['expense' => 'Chi', 'deposit' => 'Nộp thêm', 'withdrawal' => 'Rút quỹ'];

        return [
            'header' => ['ID', 'Mã ca', 'Loại', 'Số tiền (đ)', 'Ghi chú', 'Thời gian'],
            'rows' => $adjustments->map(fn ($a) => [
                $a->id, $a->shift_id, $typeLabels[$a->type] ?? $a->type, round((float) $a->amount),
                $a->note, optional($a->created_at)->format('d/m/Y H:i'),
            ])->all(),
        ];
    }

    private function orders(Location $location): array
    {
        $orders = $location->orders()->with(['customer', 'creator'])->orderBy('id')->get();

        $typeLabels = ['mang_di' => 'Mang đi', 'ngoi_lai' => 'Ngồi lại'];
        $statusLabels = ['dang_pha_che' => 'Đang pha chế', 'hoan_thanh' => 'Hoàn thành', 'da_huy' => 'Đã huỷ'];
        $paymentLabels = ['tien_mat' => 'Tiền mặt', 'chuyen_khoan' => 'Chuyển khoản', 'vi_dien_tu' => 'Ví điện tử'];

        return [
            'header' => [
                'ID', 'Loại đơn', 'Khách hàng', 'Trạng thái', 'Phương thức thanh toán',
                'Tạm tính (đ)', 'Giảm giá (đ)', 'Tổng tiền (đ)', 'Điểm tích được', 'Điểm đã dùng',
                'Người tạo', 'Hoàn thành lúc', 'Tạo lúc',
            ],
            'rows' => $orders->map(fn ($o) => [
                $o->id, $typeLabels[$o->order_type] ?? $o->order_type, $o->customer->name ?? '',
                $statusLabels[$o->status] ?? $o->status, $paymentLabels[$o->payment_method] ?? $o->payment_method,
                round((float) $o->subtotal), round((float) $o->discount_amount), round((float) $o->total),
                $o->points_earned, $o->points_redeemed, $o->creator->name ?? '',
                optional($o->completed_at)->format('d/m/Y H:i'), optional($o->created_at)->format('d/m/Y H:i'),
            ])->all(),
        ];
    }

    private function orderItems(Location $location): array
    {
        $orderIds = $location->orders()->pluck('id');
        $items = \App\Models\OrderItem::whereIn('order_id', $orderIds)->with('variant.product')->orderBy('order_id')->get();

        return [
            'header' => ['ID', 'Mã đơn', 'Món', 'Số lượng', 'Đơn giá (đ)', 'Thành tiền (đ)', 'Giá vốn (đ)'],
            'rows' => $items->map(fn ($i) => [
                $i->id, $i->order_id,
                trim(($i->variant->product->name ?? '(món đã xoá)').' ('.($i->variant->name ?? '').')'),
                $i->quantity, round((float) $i->unit_price), round((float) $i->line_total), round((float) $i->total_cost),
            ])->all(),
        ];
    }

    private function orderItemModifiers(Location $location): array
    {
        $orderIds = $location->orders()->pluck('id');
        $itemIds = \App\Models\OrderItem::whereIn('order_id', $orderIds)->pluck('id');
        $mods = \App\Models\OrderItemModifier::whereIn('order_item_id', $itemIds)->with('modifier')->get();

        return [
            'header' => ['Mã chi tiết đơn', 'Tuỳ chọn', 'Phụ thu (đ)'],
            'rows' => $mods->map(fn ($m) => [
                $m->order_item_id, $m->modifier->name ?? '(đã xoá)', round((float) $m->extra_price),
            ])->all(),
        ];
    }

    /** Sinh nội dung CSV 1 sheet, theo đúng quy ước BOM + dấu chấm phẩy đã dùng trong ReportController. */
    private function toCsv(array $header, array $rows): string
    {
        $out = fopen('php://temp', 'w+');
        fwrite($out, "\xEF\xBB\xBF");
        fputcsv($out, $header, ';');
        foreach ($rows as $row) {
            fputcsv($out, $row, ';');
        }
        rewind($out);
        $content = stream_get_contents($out);
        fclose($out);

        return $content;
    }
}
