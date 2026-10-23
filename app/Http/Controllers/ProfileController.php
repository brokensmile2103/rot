<?php

namespace App\Http\Controllers;

use App\Services\AccountDeletionService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

/**
 * Hồ sơ cá nhân — dùng chung cho cả owner lẫn nhân viên, mỗi người chỉ sửa
 * được thông tin CỦA CHÍNH MÌNH (không truyền id qua request, luôn dùng
 * auth()->user() để không ai sửa được hồ sơ người khác qua URL).
 */
class ProfileController extends Controller
{
    public function edit(Request $request): View
    {
        $user = $request->user();

        return view('profile.edit', compact('user'));
    }

    public function update(Request $request): RedirectResponse
    {
        $user = $request->user();

        $data = $request->validate([
            'name' => 'required|string|max:100',
            'email' => 'nullable|email|max:255|unique:users,email,'.$user->id,
            'avatar' => 'nullable|image|mimes:jpeg,png,jpg,webp|max:4096',
        ]);

        if ($request->hasFile('avatar') && $request->file('avatar')->isValid()) {
            $this->deleteAvatar($user);
            $data['avatar_url'] = $this->storeAvatar($request->file('avatar'));
        }

        $user->update([
            'name' => $data['name'],
            'email' => $data['email'] ?? null,
        ] + (isset($data['avatar_url']) ? ['avatar_url' => $data['avatar_url']] : []));

        return back()->with('status', 'Đã cập nhật hồ sơ.');
    }

    public function updatePassword(Request $request): RedirectResponse
    {
        $user = $request->user();

        $data = $request->validate([
            // Tài khoản Google chưa từng có mật khẩu THẬT để "nhập lại đúng"
            // — cho phép họ ĐẶT mật khẩu lần đầu mà không cần biết chuỗi ngẫu
            // nhiên hệ thống tự sinh lúc đăng ký (xem ProfileController::destroy()
            // — cùng lý do, cùng cách xử lý).
            'current_password' => $user->google_id ? 'nullable|string' : 'required|string',
            'password' => 'required|string|min:8|confirmed',
        ]);

        if (! $user->google_id && ! Hash::check($data['current_password'], $user->password)) {
            return back()->withErrors(['current_password' => 'Mật khẩu hiện tại không đúng.']);
        }

        $user->update(['password' => Hash::make($data['password'])]);

        return back()->with('status', $user->google_id ? 'Đã đặt mật khẩu — giờ bạn có thể đăng nhập bằng số điện thoại + mật khẩu này, hoặc tiếp tục dùng Google.' : 'Đã đổi mật khẩu.');
    }

    /**
     * CLOUD ONLY — chủ quán tự xoá tài khoản của mình (xem
     * AccountDeletionService để biết phạm vi xoá/cascade chi tiết).
     *
     * CHỈ role=owner được dùng luồng này:
     * - Nhân viên do chủ quán quản lý ở trang Nhân viên (gỡ/khoá), không tự
     *   xoá tài khoản của chính mình qua đây để tránh nhân viên tự ý "biến
     *   mất" khỏi hệ thống trong khi vẫn còn ca làm/đơn hàng đang dở.
     * - Super Admin không sở hữu quán nào nên khái niệm "xoá tài khoản" ở
     *   đây (xoá kèm toàn bộ xe sở hữu) không áp dụng được.
     *
     * Bắt buộc xác nhận 2 lớp trước khi xoá — vì đây là hành động KHÔNG THỂ
     * HOÀN TÁC, xoá luôn toàn bộ lịch sử kinh doanh:
     * 1) Nhập lại đúng mật khẩu hiện tại (chứng minh chính chủ đang thao tác,
     *    không phải phiên đăng nhập bị bỏ quên trên máy công cộng) — BỎ QUA
     *    bước này với tài khoản đăng nhập bằng Google (không có mật khẩu
     *    thật để nhập lại).
     * 2) Gõ đúng cụm từ xác nhận cố định — chặn bấm nhầm/bấm theo quán tính.
     */
    public function destroy(Request $request, AccountDeletionService $deletionService): RedirectResponse
    {
        $user = $request->user();

        // Super Admin không tự xoá được (xoá sẽ mất luôn quyền quản trị nền tảng).
        abort_unless($user->isOwner() && ! $user->isPlatformAdmin(), 403);

        $data = $request->validate([
            // Tài khoản đăng nhập bằng Google không có mật khẩu THẬT (chỉ là
            // chuỗi ngẫu nhiên không ai biết, xem RegisterController::
            // storeGooglePhone()) — bắt nhập lại sẽ khiến họ KHÔNG BAO GIỜ tự
            // xoá được tài khoản. Với nhóm này, cụm xác nhận gõ tay + đang có
            // phiên đăng nhập hợp lệ là đủ, bỏ qua bước nhập mật khẩu.
            'delete_password' => $user->google_id ? 'nullable|string' : 'required|string',
            'confirmation' => 'required|string',
        ]);

        if (! $user->google_id && ! Hash::check($data['delete_password'], $user->password)) {
            return back()->withErrors(['delete_password' => 'Mật khẩu không đúng.']);
        }

        if (trim($data['confirmation']) !== 'XOA TAI KHOAN') {
            return back()->withErrors(['confirmation' => 'Bạn cần gõ đúng cụm "XOA TAI KHOAN" (không dấu, viết hoa) để xác nhận.']);
        }

        $deletionService->delete($user);

        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login')->with('status', 'Tài khoản và toàn bộ dữ liệu đã được xoá vĩnh viễn.');
    }

    private function storeAvatar(UploadedFile $file): string
    {
        $filename = 'avatars/'.uniqid('avatar_', true).'.jpg';

        try {
            if (extension_loaded('gd')) {
                $raw = @file_get_contents($file->getPathname());
                $source = $raw !== false ? @imagecreatefromstring($raw) : false;

                if ($source !== false) {
                    // Cắt vuông chính giữa rồi resize 300x300 — avatar luôn hiện tròn nên cần vuông trước.
                    $width = imagesx($source);
                    $height = imagesy($source);
                    $side = min($width, $height);
                    $cropped = imagecreatetruecolor($side, $side);
                    imagecopy($cropped, $source, 0, 0, (int) (($width - $side) / 2), (int) (($height - $side) / 2), $side, $side);
                    imagedestroy($source);

                    $resized = imagecreatetruecolor(300, 300);
                    imagecopyresampled($resized, $cropped, 0, 0, 0, 0, 300, 300, $side, $side);
                    imagedestroy($cropped);

                    ob_start();
                    imagejpeg($resized, null, 85);
                    $contents = ob_get_clean();
                    imagedestroy($resized);

                    Storage::disk('public')->put($filename, $contents);

                    return Storage::url($filename);
                }
            }
        } catch (\Throwable $e) {
            \Log::warning('Không nén được ảnh đại diện, lưu file gốc thay thế: '.$e->getMessage());
        }

        $path = $file->store('avatars', 'public');

        return Storage::url($path);
    }

    private function deleteAvatar($user): void
    {
        if ($user->avatar_url && str_starts_with($user->avatar_url, '/storage/')) {
            Storage::disk('public')->delete(substr($user->avatar_url, strlen('/storage/')));
        }
    }
}
