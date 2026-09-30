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
        $location = Location::findOrFail($request->session()->get('current_location_id'));

        $user = $request->user();
        $allowed = $onlyOwner
            ? $location->owner_id === $user->id
            : ($user->isOwner() ? $location->owner_id === $user->id : $location->staff()->where('users.id', $user->id)->exists());

        abort_unless($allowed, 403);

        return $location;
    }
}
