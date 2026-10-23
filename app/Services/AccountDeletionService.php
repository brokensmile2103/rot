<?php

namespace App\Services;

use App\Models\Order;
use App\Models\StockIn;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * CLOUD ONLY — chủ quán TỰ XOÁ tài khoản của mình.
 *
 * Phạm vi xoá: TOÀN BỘ location (xe) mà user này SỞ HỮU (owner_id), cùng
 * toàn bộ dữ liệu nghiệp vụ thuộc các xe đó (thực đơn, kho, khách hàng,
 * ca làm, sổ quỹ, đơn hàng...), rồi mới xoá chính bản ghi User.
 *
 * VỀ CASCADE Ở TẦNG DATABASE: hầu hết bảng nghiệp vụ đã có `location_id`
 * với `cascadeOnDelete()` xuyên suốt (categories → products → variants →
 * recipes, modifier_groups → modifiers → modifier_recipes, ingredients →
 * stock_ins + stock_adjustments, external_revenues, shifts → cash_adjustments, orders → order_items →
 * order_item_modifiers, customers) — nên chỉ cần gọi $location->delete()
 * là toàn bộ cây dữ liệu của xe đó tự động biến mất, KHÔNG cần tự tay xoá
 * từng bảng con.
 *
 * CHỖ DUY NHẤT KHÔNG TỰ ĐỘNG: `orders.created_by` và `stock_ins.created_by`
 * trỏ tới `users` nhưng KHÔNG cascade (mặc định RESTRICT) — cố tình như vậy
 * để không ai vô tình xoá mất lịch sử đơn hàng chỉ vì xoá tài khoản người
 * tạo ra nó. Vì các dòng orders/stock_ins này đã bị xoá SẠCH cùng với xe
 * (qua location_id cascade) TRƯỚC KHI ta xoá tới User, nên khi thật sự xoá
 * User sẽ không còn dòng nào chặn nữa — xoá theo đúng thứ tự location trước,
 * user sau là đủ an toàn, không cần tắt kiểm tra khoá ngoại.
 *
 * NHÂN VIÊN "MỒ CÔI": nhân viên chỉ là 1 User(role=staff) gắn vào location
 * qua bảng trung gian location_user — KHÔNG thuộc sở hữu của owner nào cả
 * (1 nhân viên về lý thuyết có thể được gắn vào xe của owner khác). Sau khi
 * xoá hết xe của owner này, nhân viên nào KHÔNG CÒN gắn với bất kỳ xe nào
 * nữa (kể cả xe của owner khác) sẽ bị xoá LUÔN tài khoản — theo đúng lựa
 * chọn của chủ quán khi xác nhận yêu cầu này. Để an toàn, TRƯỚC KHI xoá,
 * vẫn kiểm tra lại nhân viên đó có còn là "created_by" trên đơn hàng/nhập
 * kho ở XE KHÁC (không bị xoá) hay không — nếu có (từng làm việc ở quán
 * khác trước đó) thì KHÔNG xoá cứng (sẽ vỡ FK), thay vào đó chỉ khoá tài
 * khoản (is_active = false) để không đăng nhập được nữa nhưng vẫn giữ toàn
 * vẹn lịch sử đơn hàng của quán kia.
 */
class AccountDeletionService
{
    public function delete(User $owner): void
    {
        DB::transaction(function () use ($owner) {
            $candidateStaffIds = collect();

            foreach ($owner->ownedLocations()->get() as $location) {
                $candidateStaffIds = $candidateStaffIds->merge(
                    $location->staff()->where('role', 'staff')->pluck('users.id')
                );

                // Cascade DB tự lo toàn bộ dữ liệu con của xe này (xem docblock ở trên).
                $location->delete();
            }

            foreach ($candidateStaffIds->unique() as $staffId) {
                $this->cleanupOrphanedStaff($staffId);
            }

            $owner->delete();
        });
    }

    private function cleanupOrphanedStaff(int $staffId): void
    {
        $staff = User::find($staffId);

        if (! $staff || $staff->locations()->exists()) {
            // Vẫn còn gắn với ít nhất 1 xe khác (của owner khác) — không đụng tới.
            return;
        }

        $hasHistoricalRecords = Order::where('created_by', $staffId)->exists()
            || StockIn::where('created_by', $staffId)->exists();

        if ($hasHistoricalRecords) {
            // Từng tạo đơn/nhập kho ở 1 xe KHÁC (không bị xoá) — giữ lại tài khoản
            // để không vỡ lịch sử của quán đó, chỉ khoá đăng nhập cho chắc.
            $staff->forceFill(['is_active' => false])->save();

            return;
        }

        $staff->delete();
    }
}
