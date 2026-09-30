<?php

namespace App\Http\Controllers\Owner;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Concerns\ResolvesCurrentLocation;
use App\Models\Category;
use App\Models\Location;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Recipe;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

class MenuController extends Controller
{
    use ResolvesCurrentLocation;

    public function index(Request $request): View
    {
        $location = $this->currentLocation($request);
        $categories = $location->categories()
            ->with(['products.variants.recipes.ingredient', 'products.modifierGroups.modifiers.modifierRecipes.ingredient'])
            ->orderBy('sort_order')
            ->get();
        $ingredients = $location->ingredients()->orderBy('name')->get();
        $allModifierGroups = $location->modifierGroups()->with('modifiers')->get();
        $trashedProducts = Product::onlyTrashed()
            ->whereHas('category', fn ($q) => $q->where('location_id', $location->id))
            ->with('category')
            ->get();

        return view('owner.menu.index', compact('location', 'categories', 'ingredients', 'allModifierGroups', 'trashedProducts'));
    }

    public function storeCategory(Request $request): RedirectResponse
    {
        $location = $this->currentLocation($request);
        $data = $request->validate(['name' => 'required|string|max:100']);

        $location->categories()->create([
            'name' => $data['name'],
            'sort_order' => $location->categories()->max('sort_order') + 1,
        ]);

        return back()->with('status', 'Đã thêm danh mục.');
    }

    public function updateCategory(Request $request, int $category): RedirectResponse
    {
        $location = $this->currentLocation($request);
        $cat = $location->categories()->findOrFail($category);

        $data = $request->validate(['name' => 'required|string|max:100']);
        $cat->update($data);

        return back()->with('status', 'Đã cập nhật danh mục.');
    }

    public function storeProduct(Request $request): RedirectResponse
    {
        $location = $this->currentLocation($request);
        $data = $request->validate([
            'category_id' => 'required|exists:categories,id',
            'name' => 'required|string|max:150',
            'variant_name' => 'required|string|max:50',
            'price' => 'required|numeric|min:0',
            'image' => 'nullable|image|mimes:jpeg,png,jpg,webp|max:4096',
        ]);

        $category = $location->categories()->findOrFail($data['category_id']);

        // Chặn tạo trùng tên món trong cùng danh mục — lỗi thường gặp: muốn thêm
        // size mới cho món đã có (VD: "Cà phê đen" ly nhỏ) lại vô tình tạo THÊM 1
        // món "Cà phê đen" khác thay vì thêm size vào món cũ, gây trùng tên rối loạn
        // khi bán hàng (2 thẻ giống hệt nhau trên màn hình Order).
        $existing = $category->products()->whereRaw('LOWER(name) = ?', [mb_strtolower($data['name'])])->first();
        if ($existing) {
            return back()->withInput()->with(
                'status',
                'Món "'.$data['name'].'" đã có trong danh mục này rồi. Nếu muốn thêm size khác, bấm "Thêm size khác" ngay dưới món đó thay vì tạo món mới nhé.'
            );
        }

        $product = $category->products()->create([
            'name' => $data['name'],
            'image_url' => ($request->hasFile('image') && $request->file('image')->isValid())
                ? $this->storeProductImage($request->file('image'))
                : null,
            'sort_order' => $category->products()->max('sort_order') + 1,
        ]);

        $product->variants()->create([
            'name' => $data['variant_name'],
            'price' => $data['price'],
            'is_default' => true,
        ]);

        return back()->with('status', 'Đã thêm món.');
    }

    public function updateProduct(Request $request, int $product): RedirectResponse
    {
        $location = $this->currentLocation($request);
        $p = $this->findProduct($location, $product);

        $data = $request->validate([
            'name' => 'required|string|max:150',
            'image' => 'nullable|image|mimes:jpeg,png,jpg,webp|max:4096',
        ]);

        if ($request->hasFile('image') && $request->file('image')->isValid()) {
            $this->deleteProductImage($p);
            $data['image_url'] = $this->storeProductImage($request->file('image'));
        }

        $p->update($data);

        return back()->with('status', 'Đã cập nhật món.');
    }

    public function toggleAvailability(Request $request, int $product): RedirectResponse
    {
        $location = $this->currentLocation($request);
        $p = $this->findProduct($location, $product);
        $p->update(['is_available' => ! $p->is_available]);

        return back();
    }

    /**
     * Xoá mềm — món vẫn còn nguyên trong lịch sử đơn hàng, chỉ ẩn khỏi thực đơn.
     */
    public function deleteProduct(Request $request, int $product): RedirectResponse
    {
        $location = $this->currentLocation($request);
        $p = $this->findProduct($location, $product);
        $p->delete();

        return back()->with('status', 'Đã xoá "'.$p->name.'" khỏi thực đơn (có thể khôi phục lại bất cứ lúc nào).');
    }

    public function restoreProduct(Request $request, int $product): RedirectResponse
    {
        $location = $this->currentLocation($request);
        $p = Product::onlyTrashed()
            ->whereHas('category', fn ($q) => $q->where('location_id', $location->id))
            ->findOrFail($product);
        $p->restore();

        return back()->with('status', 'Đã khôi phục "'.$p->name.'".');
    }

    /**
     * Thêm size/biến thể MỚI vào món đã có sẵn — dùng khi lỡ quên chọn size lúc tạo món,
     * KHÔNG cần xoá món cũ đi làm lại (xoá sẽ mất liên kết với lịch sử đơn hàng đã bán).
     */
    public function storeVariant(Request $request, int $product): RedirectResponse
    {
        $location = $this->currentLocation($request);
        $p = $this->findProduct($location, $product);

        $data = $request->validate([
            'name' => 'required|string|max:50',
            'price' => 'required|numeric|min:0',
        ]);

        $p->variants()->create($data);

        return back()->with('status', 'Đã thêm size "'.$data['name'].'" cho '.$p->name.'.');
    }

    public function updateVariant(Request $request, int $variant): RedirectResponse
    {
        $location = $this->currentLocation($request);
        $v = $this->findVariant($location, $variant);

        $data = $request->validate([
            'name' => 'required|string|max:50',
            'price' => 'required|numeric|min:0',
        ]);
        $v->update($data);

        return back()->with('status', 'Đã cập nhật size.');
    }

    /**
     * Xoá mềm 1 size — chặn nếu đây là size DUY NHẤT còn lại của món (món phải
     * luôn bán được ít nhất 1 size). Đơn hàng cũ dùng size này vẫn không bị ảnh hưởng.
     */
    public function deleteVariant(Request $request, int $variant): RedirectResponse
    {
        $location = $this->currentLocation($request);
        $v = $this->findVariant($location, $variant);

        $remaining = $v->product->variants()->where('id', '!=', $v->id)->count();
        if ($remaining === 0) {
            return back()->with('status', 'Không thể xoá — đây là size duy nhất của món này. Hãy xoá cả món nếu không muốn bán nữa.');
        }

        $v->delete();

        return back()->with('status', 'Đã xoá size "'.$v->name.'" (có thể khôi phục lại).');
    }

    public function restoreVariant(Request $request, int $variant): RedirectResponse
    {
        $location = $this->currentLocation($request);
        $v = ProductVariant::onlyTrashed()
            ->whereHas('product.category', fn ($q) => $q->where('location_id', $location->id))
            ->findOrFail($variant);
        $v->restore();

        return back()->with('status', 'Đã khôi phục size.');
    }

    public function storeRecipe(Request $request, int $variant): RedirectResponse
    {
        $location = $this->currentLocation($request);
        $productVariant = $this->findVariant($location, $variant);

        $data = $request->validate([
            'ingredient_id' => 'required|exists:ingredients,id',
            'quantity' => 'required|numeric|min:0.0001',
        ]);

        $location->ingredients()->findOrFail($data['ingredient_id']);

        $productVariant->recipes()->updateOrCreate(
            ['ingredient_id' => $data['ingredient_id']],
            ['quantity' => $data['quantity']]
        );

        return back()->with('status', 'Đã cập nhật công thức.');
    }

    public function deleteRecipe(Request $request, int $recipe): RedirectResponse
    {
        $location = $this->currentLocation($request);

        $r = Recipe::whereHas(
            'variant.product.category',
            fn ($q) => $q->where('location_id', $location->id)
        )->findOrFail($recipe);

        $r->delete();

        return back()->with('status', 'Đã xoá nguyên liệu khỏi công thức.');
    }

    /**
     * Gắn/gỡ 1 nhóm tuỳ chọn (VD: "Lượng đường") vào món — nhóm được tạo sẵn
     * ở trang riêng (Owner\ModifierGroupController), ở đây chỉ chọn áp dụng
     * cho món nào.
     */
    public function attachModifierGroup(Request $request, int $product): RedirectResponse
    {
        $location = $this->currentLocation($request);
        $p = $this->findProduct($location, $product);

        $data = $request->validate(['modifier_group_id' => 'required|exists:modifier_groups,id']);
        $group = $location->modifierGroups()->findOrFail($data['modifier_group_id']);

        $p->modifierGroups()->syncWithoutDetaching([$group->id]);

        return back()->with('status', 'Đã thêm "'.$group->name.'" cho '.$p->name.'.');
    }

    public function detachModifierGroup(Request $request, int $product, int $group): RedirectResponse
    {
        $location = $this->currentLocation($request);
        $p = $this->findProduct($location, $product);
        $location->modifierGroups()->findOrFail($group);

        $p->modifierGroups()->detach($group);

        return back()->with('status', 'Đã gỡ tuỳ chọn khỏi '.$p->name.'.');
    }

    private function findProduct(Location $location, int $productId): Product
    {
        return Product::whereHas('category', fn ($q) => $q->where('location_id', $location->id))
            ->findOrFail($productId);
    }

    /**
     * Lưu ảnh món đã nén lại (max chiều rộng 800px, JPEG chất lượng 80%) để tiết
     * kiệm dung lượng ổ đĩa — quan trọng với hosting giá rẻ có giới hạn dung lượng.
     * Nếu server không bật extension GD, tự động lưu nguyên file gốc (không nén).
     */
    private function storeProductImage(UploadedFile $file): string
    {
        $filename = 'products/'.uniqid('img_', true).'.jpg';

        try {
            if (extension_loaded('gd')) {
                $tempPath = $file->getPathname();
                $raw = $tempPath ? @file_get_contents($tempPath) : false;
                $source = $raw !== false ? @imagecreatefromstring($raw) : false;

                if ($source !== false) {
                    $width = imagesx($source);
                    $height = imagesy($source);
                    $maxWidth = 800;

                    if ($width > $maxWidth) {
                        $newHeight = (int) round($height * $maxWidth / $width);
                        $resized = imagecreatetruecolor($maxWidth, $newHeight);
                        imagecopyresampled($resized, $source, 0, 0, 0, 0, $maxWidth, $newHeight, $width, $height);
                        imagedestroy($source);
                        $source = $resized;
                    }

                    ob_start();
                    imagejpeg($source, null, 80);
                    $contents = ob_get_clean();
                    imagedestroy($source);

                    Storage::disk('public')->put($filename, $contents);

                    return Storage::url($filename);
                }
            }
        } catch (\Throwable $e) {
            \Log::warning('Không nén được ảnh món, lưu file gốc thay thế: '.$e->getMessage());
        }

        // Fallback: không có GD, không đọc được ảnh để nén, hoặc có lỗi bất kỳ ở
        // trên — luôn lưu được file gốc theo cách Laravel xử lý upload chuẩn,
        // không bao giờ để trang bị crash chỉ vì bước nén ảnh (không bắt buộc) lỗi.
        $path = $file->store('products', 'public');

        return Storage::url($path);
    }

    private function deleteProductImage(Product $product): void
    {
        if ($product->image_url && str_starts_with($product->image_url, '/storage/')) {
            Storage::disk('public')->delete(substr($product->image_url, strlen('/storage/')));
        }
    }

    private function findVariant(Location $location, int $variantId): ProductVariant
    {
        return ProductVariant::whereHas(
            'product.category',
            fn ($q) => $q->where('location_id', $location->id)
        )->findOrFail($variantId);
    }

}
