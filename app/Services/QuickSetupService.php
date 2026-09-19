<?php

namespace App\Services;

use App\Models\Category;
use App\Models\Ingredient;
use App\Models\Location;
use App\Models\Modifier;
use App\Models\ModifierGroup;
use App\Models\Product;
use Illuminate\Support\Facades\DB;

/**
 * "Thiết lập nhanh (dữ liệu mẫu)" — tạo sẵn 1 bộ thực đơn + kho HOÀN CHỈNH,
 * đủ để bán ngay lập tức cho 1 xe cà phê cơ bản, đúng CHÍNH XÁC từng nguyên
 * liệu (kể cả ống hút, túi mang đi) — mục đích là để chủ quán MỚI hiểu ngay
 * cách thiết lập 1 món ăn hoàn chỉnh (danh mục → món → công thức trừ kho →
 * tuỳ chọn) bằng cách nhìn vào 1 ví dụ THẬT, thay vì phải tự mò từ đầu.
 *
 * === NGUỒN SỐ LIỆU (giá tham khảo thị trường VN cho XE cà phê vỉa hè,
 * KHÔNG phải giá quán ngồi có mặt bằng — xe cà phê vốn giá rẻ hơn):
 * - Cà phê rang xay (robusta): ~150.000đ/kg
 * - Đường nước (syrup pha sẵn): ~10.000đ/lít
 * - Sữa đặc: ~58.000đ/kg (tương đương ~22.000đ/lon 380g)
 * - Đá viên: ~1.500đ/kg (mua bao)
 * - Ly nhựa, nắp, ống hút, túi ni-lông: giá sỉ phổ biến cho xe cà phê
 * Định lượng theo công thức pha phổ biến của cà phê đá/cà phê sữa vỉa hè.
 *
 * === GIỚI HẠN CỐ Ý:
 * App hiện CHƯA hỗ trợ công thức khác nhau theo loại đơn (mang đi/ngồi lại),
 * nên "Túi mang đi" được tính vào công thức CHUẨN của cả 2 món (giả định đa
 * số khách xe cà phê là mua mang đi) — chủ quán có thể tự xoá dòng này khỏi
 * công thức ở trang Thực đơn nếu quán mình chủ yếu bán tại chỗ.
 *
 * === TẠI SAO "Lượng đường"/"Lượng sữa" không đổi giá và KHÔNG mức nào trừ
 * kho riêng: công thức CHUẨN (recipe của biến thể) đã tính sẵn định lượng
 * đường/sữa ở mức bình thường rồi. App có sẵn cảnh báo "trừ kho có thể bị
 * nhân đôi" (xem Product::ingredientConflicts()) mỗi khi 1 nguyên liệu vừa
 * nằm trong công thức gốc VỪA nằm trong công thức của 1 tuỳ chọn gắn cho
 * món đó — cảnh báo này KHÔNG phân biệt được "cộng thêm có chủ đích" với
 * "trùng lặp do nhầm lẫn", nên nếu để "Nhiều đường" cộng thêm đường vào kho,
 * dữ liệu mẫu sẽ hiện cảnh báo ngay từ đầu (trải nghiệm xấu cho quán mới,
 * lại đúng vào chỗ đáng lẽ phải là VÍ DỤ CHUẨN). Vì vậy toàn bộ "Lượng
 * đường"/"Lượng sữa" ở đây CHỈ là hướng dẫn pha chế cho nhân viên (Ít/Bình
 * thường/Nhiều), không gắn công thức trừ kho ở bất kỳ mức nào — an toàn
 * tuyệt đối, không đụng tới hệ thống cảnh báo, không trừ kho sai.
 */
class QuickSetupService
{
    /**
     * Đã có món nào "thật" trong thực đơn thì coi như quán đã tự thiết lập —
     * không cho chạy Thiết lập nhanh đè lên nữa.
     *
     * LƯU Ý QUAN TRỌNG: lúc đăng ký/cài đặt, hệ thống đã TỰ ĐỘNG tạo sẵn 1
     * món mẫu "Cà phê đen" (xem InstallController::saveLocation() bản
     * self-hosted / RegisterController bản Cloud) chỉ để màn Order không
     * trống trơn — món này CHƯA có công thức, CHƯA có tuỳ chọn, giá mặc định
     * cứng 20.000đ. Nếu coi món placeholder này là "đã có thực đơn thật" thì
     * Thiết lập nhanh sẽ KHÔNG BAO GIỜ hiện ra được cho tài khoản mới — đúng
     * cái bug đã gặp. Nên ở đây, nếu toàn bộ thực đơn CHỈ có đúng 1 món và nó
     * khớp y hệt "dấu vân tay" của placeholder này (chưa ai đụng vào: đúng
     * tên/giá/tên size, không ảnh, không công thức, không tuỳ chọn), vẫn coi
     * như quán CHƯA thiết lập gì.
     */
    public function hasExistingMenu(Location $location): bool
    {
        $products = $this->allProducts($location);

        if ($products->isEmpty()) {
            return false;
        }

        return ! ($products->count() === 1 && $this->isUntouchedPlaceholder($products->first()));
    }

    public function seed(Location $location): void
    {
        DB::transaction(function () use ($location) {
            $this->removeUntouchedPlaceholder($location);

            $ingredients = $this->seedIngredients($location);
            $category = $location->categories()->firstOrCreate(['name' => 'Cà phê'], ['sort_order' => 0]);

            $sugarGroup = $this->createSugarGroup($location);
            $milkGroup = $this->createMilkGroup($location);

            $capheDa = $this->createProduct($category, 'Cà phê đá', 18000, [
                $ingredients['ca_phe']->id => 20,   // 20g cà phê xay/ly
                $ingredients['duong']->id => 15,    // 15ml đường nước (mức chuẩn)
                $ingredients['da']->id => 200,       // 200g đá viên
                $ingredients['ly']->id => 1,
                $ingredients['nap']->id => 1,
                $ingredients['ong_hut']->id => 1,
                $ingredients['tui']->id => 1,
            ]);
            $capheDa->modifierGroups()->syncWithoutDetaching([$sugarGroup->id]);

            $capheSua = $this->createProduct($category, 'Cà phê sữa', 22000, [
                $ingredients['ca_phe']->id => 20,
                $ingredients['sua']->id => 30,       // 30g sữa đặc/ly — đã đủ ngọt, gần như không ai pha thêm đường nước cho cà phê sữa
                $ingredients['da']->id => 200,
                $ingredients['ly']->id => 1,
                $ingredients['nap']->id => 1,
                $ingredients['ong_hut']->id => 1,
                $ingredients['tui']->id => 1,
            ]);
            $capheSua->modifierGroups()->syncWithoutDetaching([$sugarGroup->id, $milkGroup->id]);
        });
    }

    /** Toàn bộ món (kể cả đã xoá mềm) thuộc mọi danh mục (kể cả danh mục đã xoá mềm) của quán này. */
    private function allProducts(Location $location): \Illuminate\Support\Collection
    {
        $categoryIds = Category::withTrashed()->where('location_id', $location->id)->pluck('id');

        return Product::withTrashed()
            ->whereIn('category_id', $categoryIds)
            ->with('variants')
            ->get();
    }

    /**
     * "Dấu vân tay" của món mẫu tự động tạo lúc đăng ký — xem giải thích ở
     * hasExistingMenu(). Chỉ cần KHÁC 1 trong các điểm dưới đây (đổi tên, đổi
     * giá, thêm ảnh, thêm công thức, thêm size...) là coi như chủ quán ĐÃ tự
     * chỉnh sửa, không đụng vào nữa.
     */
    private function isUntouchedPlaceholder(Product $product): bool
    {
        if ($product->trashed() || $product->name !== 'Cà phê đen' || $product->image_url) {
            return false;
        }

        if ($product->variants->count() !== 1) {
            return false;
        }

        $variant = $product->variants->first();

        return $variant->name === 'Ly'
            && (float) $variant->price === 20000.0
            && $variant->recipes()->count() === 0
            && $product->modifierGroups()->count() === 0;
    }

    /**
     * Xoá MỀM (không forceDelete) món placeholder chưa ai chỉnh sửa — dùng
     * đúng cơ chế xoá món có sẵn của app (xem MenuController::deleteProduct),
     * KHÔNG xoá thật. Lý do: dù "chưa ai đụng vào" về mặt CẤU HÌNH (tên/giá/
     * công thức — điều kiện isUntouchedPlaceholder() đã kiểm), không có gì
     * đảm bảo chủ quán chưa LỠ BÁN vài đơn bằng đúng món này trước khi bấm
     * Thiết lập nhanh. Nếu variant đó đã có order_items tham chiếu tới, xoá
     * thật (forceDelete) sẽ vỡ khoá ngoại (order_items.product_variant_id
     * không cascade — cố tình, xem migration add_soft_deletes_to_menu_tables)
     * và làm sập luôn transaction. Xoá mềm luôn an toàn trong mọi trường hợp,
     * chỉ đánh dấu ẩn khỏi thực đơn, còn khôi phục được ở trang Thực đơn.
     */
    private function removeUntouchedPlaceholder(Location $location): void
    {
        $products = $this->allProducts($location);

        if ($products->count() === 1 && $this->isUntouchedPlaceholder($products->first())) {
            $products->first()->delete();
        }
    }

    /**
     * 8 nguyên liệu — ĐỦ để vận hành 1 xe cà phê cơ bản. Tồn kho ban đầu tính
     * sẵn cho ~100 ly (vài ngày bán đầu, tuỳ quy mô) để chủ quán thấy ngay
     * "Giá vốn TB" đã có số thật, không phải 0đ chờ nhập kho lần đầu.
     */
    private function seedIngredients(Location $location): array
    {
        return [
            'ca_phe' => $this->createIngredient($location, 'Cà phê xay', 'g', 1000, 150000, 200),
            'duong' => $this->createIngredient($location, 'Đường nước (syrup)', 'ml', 2000, 20000, 300),
            'sua' => $this->createIngredient($location, 'Sữa đặc', 'g', 1000, 58000, 200),
            'da' => $this->createIngredient($location, 'Đá viên', 'g', 20000, 30000, 3000),
            'ly' => $this->createIngredient($location, 'Ly nhựa', 'cái', 100, 60000, 20),
            'nap' => $this->createIngredient($location, 'Nắp ly', 'cái', 100, 35000, 20),
            'ong_hut' => $this->createIngredient($location, 'Ống hút', 'cái', 100, 15000, 20),
            'tui' => $this->createIngredient($location, 'Túi mang đi', 'cái', 100, 40000, 20),
        ];
    }

    /**
     * Tạo 1 nguyên liệu + nhập kho ban đầu — làm ĐÚNG y hệt luồng
     * InventoryController::storeIngredient() (tạo StockIn + gọi receiveStock()
     * để avg_cost_per_unit tính đúng ngay từ đầu), không đi tắt bằng cách gán
     * thẳng current_stock/avg_cost_per_unit để lịch sử Nhập kho có dữ liệu
     * mẫu luôn — chủ quán mở trang Kho sẽ thấy 1 dòng nhập kho thật, hiểu
     * ngay "nhập kho" hoạt động thế nào.
     */
    private function createIngredient(Location $location, string $name, string $unit, float $qty, float $totalCost, float $lowStockThreshold): Ingredient
    {
        $ingredient = $location->ingredients()->create([
            'name' => $name,
            'unit' => $unit,
            'low_stock_threshold' => $lowStockThreshold,
        ]);

        $ingredient->stockIns()->create([
            'quantity' => $qty,
            'total_cost' => $totalCost,
            'note' => 'Tồn kho ban đầu (thiết lập nhanh)',
            'created_by' => $location->owner_id,
        ]);
        $ingredient->receiveStock($qty, $totalCost);

        return $ingredient;
    }

    /** @param array<int,float> $recipe [ingredient_id => định lượng cho 1 ly] */
    private function createProduct(\App\Models\Category $category, string $name, float $price, array $recipe): Product
    {
        $product = $category->products()->create([
            'name' => $name,
            'sort_order' => $category->products()->max('sort_order') + 1,
        ]);

        // "Ly" — đúng quy ước đặt tên biến thể của app khi món không phân size
        // (xem placeholder gợi ý "VD: Ly, Size M" ở trang Thực đơn).
        $variant = $product->variants()->create([
            'name' => 'Ly',
            'price' => $price,
            'is_default' => true,
        ]);

        foreach ($recipe as $ingredientId => $quantity) {
            $variant->recipes()->create([
                'ingredient_id' => $ingredientId,
                'quantity' => $quantity,
            ]);
        }

        return $product;
    }

    /**
     * Nhóm "Lượng đường" — DÙNG CHUNG cho cả 2 món. KHÔNG mức nào (Ít/Bình
     * thường/Nhiều) gắn công thức trừ kho riêng — xem giải thích ở docblock
     * đầu class: chỉ là hướng dẫn pha chế cho nhân viên.
     */
    private function createSugarGroup(Location $location): ModifierGroup
    {
        $group = $location->modifierGroups()->create([
            'name' => 'Lượng đường',
            'is_required' => true,
            'allow_multiple' => false,
        ]);

        $this->createModifier($group, 'Ít đường', isDefault: false);
        $this->createModifier($group, 'Bình thường', isDefault: true);
        $this->createModifier($group, 'Nhiều đường', isDefault: false);

        return $group;
    }

    /** Nhóm "Lượng sữa" — CHỈ gắn cho Cà phê sữa (Cà phê đá không có sữa). */
    private function createMilkGroup(Location $location): ModifierGroup
    {
        $group = $location->modifierGroups()->create([
            'name' => 'Lượng sữa',
            'is_required' => true,
            'allow_multiple' => false,
        ]);

        $this->createModifier($group, 'Ít sữa', isDefault: false);
        $this->createModifier($group, 'Bình thường', isDefault: true);
        $this->createModifier($group, 'Nhiều sữa', isDefault: false);

        return $group;
    }

    private function createModifier(ModifierGroup $group, string $name, bool $isDefault): Modifier
    {
        // Miễn phí toàn bộ mức đường/sữa — đúng thông lệ xe cà phê VN, không
        // tính thêm tiền chỉ vì khách yêu cầu ít/nhiều đường. KHÔNG gắn
        // modifierRecipe cho bất kỳ mức nào — xem docblock đầu class.
        return $group->modifiers()->create([
            'name' => $name,
            'extra_price' => 0,
            'is_default' => $isDefault,
        ]);
    }
}
