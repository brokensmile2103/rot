<?php

namespace App\Http\Controllers\Concerns;

use App\Models\Location;
use Illuminate\Http\Request;

/**
 * Trait dùng chung cho MỌI controller cần thao tác trên "quán đang chọn"
 * (session current_location_id) — trước đây hàm này bị copy tay giống hệt
 * nhau ra 10 file khác nhau (10 owner/POS controller), rất dễ 1 lần sửa/thêm
 * controller mới quên mất dòng kiểm tra quyền sở hữu/nhân sự.
 *
 * KHÔNG BAO GIỜ tin location_id gửi lên từ client (URL, input ẩn...) — luôn
 * lấy từ session (server-side) rồi đối chiếu lại quyền với DB ở MỌI request,
 * vì session có thể bị set sai (dù các nơi ghi session đã tự validate, đây
 * vẫn là lớp phòng thủ theo chiều sâu cuối cùng trước khi trả dữ liệu).
 *
 * $onlyOwner = true: dùng cho các trang CHỈ chủ quán được vào (thực đơn, kho,
 * nhân viên, cài đặt, báo cáo...). $onlyOwner = false: dùng cho khu vực POS
 * dùng chung giữa chủ quán VÀ nhân viên (order, ca, sổ quỹ).
 */
trait ResolvesCurrentLocation
{
    protected function currentLocation(Request $request, bool $onlyOwner = true): Location
    {
        $user = $request->user();
        $id = $request->session()->get('current_location_id');
        $location = $id ? Location::find($id) : null;

        abort_if($onlyOwner && ! $user->isOwner(), 403);

        $allowed = $location && $location->is_active && ($onlyOwner
            ? $location->owner_id === $user->id
            : ($user->isOwner() ? $location->owner_id === $user->id : $location->staff()->where('users.id', $user->id)->exists()));

        if (! $allowed) {
            // Tự chữa thay vì lỗi 404/403 khó hiểu: phiên đăng nhập khôi phục từ "Ghi nhớ đăng nhập"
            // (session mới, CHƯA có current_location_id), hoặc quán đang chọn vừa bị khoá/gỡ quyền
            // → chọn lại quán đầu tiên còn hoạt động mà người dùng được phép vào.
            // Super Admin của bản cài cũ chưa có quán riêng → tạo tự động.
            app(\App\Services\LocationProvisioner::class)->ensureFor($user);

            $location = $user->isOwner()
                ? $user->ownedLocations()->where('is_active', true)->orderBy('id')->first()
                : $user->locations()->where('locations.is_active', true)->orderBy('locations.id')->first();

            abort_unless($location, 403, 'Quán đang tạm khoá hoặc bạn chưa thuộc quán nào đang hoạt động. Vui lòng liên hệ hỗ trợ.');

            $request->session()->put('current_location_id', $location->id);
        }

        return $location;
    }
}
