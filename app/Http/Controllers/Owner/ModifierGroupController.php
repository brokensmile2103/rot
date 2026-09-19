<?php

namespace App\Http\Controllers\Owner;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Concerns\ResolvesCurrentLocation;
use App\Models\Location;
use App\Models\Modifier;
use App\Models\ModifierGroup;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Quản lý "Nhóm tuỳ chọn" (VD: Lượng đường, Topping, Đá...) — tạo 1 LẦN ở cấp
 * độ xe, dùng lại được cho nhiều món khác nhau, thay vì phải tạo lại cho từng
 * món (xem ghi chú trong migration refactor_modifier_groups_to_location).
 */
class ModifierGroupController extends Controller
{
    use ResolvesCurrentLocation;

    public function index(Request $request): View
    {
        $location = $this->currentLocation($request);
        $groups = $location->modifierGroups()
            ->with(['modifiers.modifierRecipes.ingredient', 'products'])
            ->get();
        $ingredients = $location->ingredients()->orderBy('name')->get();

        return view('owner.modifier-groups.index', compact('location', 'groups', 'ingredients'));
    }

    public function storeGroup(Request $request): RedirectResponse
    {
        $location = $this->currentLocation($request);

        $data = $request->validate([
            'name' => 'required|string|max:100',
            'is_required' => 'nullable|boolean',
            'allow_multiple' => 'nullable|boolean',
        ]);

        $group = $location->modifierGroups()->create([
            'name' => $data['name'],
            'is_required' => $request->boolean('is_required'),
            'allow_multiple' => $request->boolean('allow_multiple'),
        ]);

        return back()->with('status', 'Đã tạo nhóm tuỳ chọn.')->with('focus_group', $group->id);
    }

    public function deleteGroup(Request $request, int $group): RedirectResponse
    {
        $location = $this->currentLocation($request);
        $g = $location->modifierGroups()->findOrFail($group);
        $g->delete();

        return back()->with('status', 'Đã xoá nhóm "'.$g->name.'" (có thể khôi phục lại).');
    }

    public function restoreGroup(Request $request, int $group): RedirectResponse
    {
        $location = $this->currentLocation($request);
        $g = $location->modifierGroups()->onlyTrashed()->findOrFail($group);
        $g->restore();

        return back()->with('status', 'Đã khôi phục nhóm "'.$g->name.'".');
    }

    public function storeModifier(Request $request, int $group): RedirectResponse
    {
        $location = $this->currentLocation($request);
        $g = $location->modifierGroups()->findOrFail($group);

        $data = $request->validate([
            'name' => 'required|string|max:100',
            'extra_price' => 'nullable|numeric|min:0',
            'is_default' => 'nullable|boolean',
        ]);

        $isDefault = $request->boolean('is_default');

        // Nhóm KHÔNG cho chọn nhiều (VD: Lượng đường — chỉ 1 mức tại 1 thời điểm)
        // thì chỉ được phép có TỐI ĐA 1 mục mặc định, đặt mục mới sẽ tự bỏ mặc
        // định của mục cũ để tránh mâu thuẫn khi tự động chọn sẵn lúc order.
        if ($isDefault && ! $g->allow_multiple) {
            $g->modifiers()->update(['is_default' => false]);
        }

        $g->modifiers()->create([
            'name' => $data['name'],
            'extra_price' => $data['extra_price'] ?? 0,
            'is_default' => $isDefault,
        ]);

        return back()->with('status', 'Đã thêm tuỳ chọn "'.$data['name'].'".')->with('focus_group', $g->id);
    }

    /** Bấm để đặt/bỏ 1 tuỳ chọn làm mặc định — tự chọn sẵn khi mở màn hình order. */
    public function toggleDefaultModifier(Request $request, int $modifier): RedirectResponse
    {
        $location = $this->currentLocation($request);
        $m = Modifier::with('group')->whereHas('group', fn ($q) => $q->where('location_id', $location->id))->findOrFail($modifier);

        $newValue = ! $m->is_default;

        if ($newValue && ! $m->group->allow_multiple) {
            $m->group->modifiers()->update(['is_default' => false]);
        }

        $m->update(['is_default' => $newValue]);

        return back()->with('status', $newValue ? 'Đã đặt "'.$m->name.'" làm mặc định.' : 'Đã bỏ mặc định.');
    }

    public function deleteModifier(Request $request, int $modifier): RedirectResponse
    {
        $location = $this->currentLocation($request);
        $m = Modifier::whereHas('group', fn ($q) => $q->where('location_id', $location->id))->findOrFail($modifier);
        $m->delete();

        return back()->with('status', 'Đã xoá tuỳ chọn (có thể khôi phục lại).');
    }

    public function restoreModifier(Request $request, int $modifier): RedirectResponse
    {
        $location = $this->currentLocation($request);
        $m = Modifier::onlyTrashed()->whereHas('group', fn ($q) => $q->where('location_id', $location->id))->findOrFail($modifier);
        $m->restore();

        return back()->with('status', 'Đã khôi phục tuỳ chọn.');
    }

    /**
     * Gán nguyên liệu bị trừ kho khi khách chọn tuỳ chọn này — VD: chọn "70%
     * đường" trừ 15ml syrup đường; chọn "Trân châu" trừ 30g trân châu.
     */
    public function storeModifierRecipe(Request $request, int $modifier): RedirectResponse
    {
        $location = $this->currentLocation($request);
        $m = Modifier::whereHas('group', fn ($q) => $q->where('location_id', $location->id))->findOrFail($modifier);

        $data = $request->validate([
            'ingredient_id' => 'required|exists:ingredients,id',
            'quantity' => 'required|numeric|min:0.0001',
        ]);

        $location->ingredients()->findOrFail($data['ingredient_id']);

        $m->modifierRecipes()->updateOrCreate(
            ['ingredient_id' => $data['ingredient_id']],
            ['quantity' => $data['quantity']]
        );

        return back()->with('status', 'Đã cập nhật công thức tuỳ chọn.');
    }

    public function deleteModifierRecipe(Request $request, int $recipe): RedirectResponse
    {
        $location = $this->currentLocation($request);

        $r = \App\Models\ModifierRecipe::whereHas(
            'modifier.group',
            fn ($q) => $q->where('location_id', $location->id)
        )->findOrFail($recipe);
        $r->delete();

        return back()->with('status', 'Đã xoá nguyên liệu khỏi công thức.');
    }

}
