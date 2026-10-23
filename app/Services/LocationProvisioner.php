<?php

namespace App\Services;

use App\Models\Location;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Tạo quán đầu tiên cho 1 chủ quán (kèm danh mục + món mẫu để thấy ngay
 * giao diện order). Dùng chung cho đăng ký công khai, đăng ký Google,
 * trình cài đặt và việc cấp quán riêng cho Super Admin.
 */
class LocationProvisioner
{
    public function createFor(User $owner, string $name): Location
    {
        $location = Location::forceCreate([
            'owner_id' => $owner->id,
            'name' => $name,
            'is_active' => true,
        ]);

        $location->staff()->attach($owner->id);

        $category = $location->categories()->create(['name' => 'Cà phê', 'sort_order' => 0]);
        $product = $category->products()->create(['name' => 'Cà phê đen', 'sort_order' => 0]);
        $product->variants()->create(['name' => 'Ly', 'price' => 20000, 'is_default' => true]);

        return $location;
    }

    /**
     * Super Admin cũng dùng được các tính năng như chủ quán nên cần có quán
     * riêng. Bản cài mới tạo sẵn ở bước cài đặt; bản đã cài từ trước (Super
     * Admin chưa có quán nào) sẽ được tạo tự động ở lần đăng nhập đầu tiên.
     * Chỉ tạo khi hoàn toàn chưa sở hữu quán nào (quán đang bị khoá vẫn tính là có).
     */
    public function ensureFor(User $user): ?Location
    {
        if (! $user->isPlatformAdmin()) {
            return null;
        }

        $existing = $user->ownedLocations()->orderBy('id')->first();

        if ($existing) {
            return $existing;
        }

        return DB::transaction(fn () => $this->createFor($user, 'Quán của '.$user->name));
    }
}
