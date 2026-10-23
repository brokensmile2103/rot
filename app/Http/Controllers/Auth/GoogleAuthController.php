<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\PlatformSetting;
use App\Models\User;
use App\Services\LocationProvisioner;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Laravel\Socialite\Facades\Socialite;

/**
 * CLOUD ONLY — Đăng nhập/Đăng ký bằng Google. Dùng CHUNG 1 điểm vào cho
 * cả 2 mục đích (đúng chuẩn UX phổ biến của "Sign in with Google" — người
 * dùng không cần biết trước là mình "đăng nhập" hay "đăng ký"):
 *
 * 1. Đã từng đăng nhập Google trước đó (có google_id khớp) → đăng nhập luôn.
 * 2. Chưa có google_id nhưng email khớp 1 tài khoản đã đăng ký bằng số điện
 *    thoại/mật khẩu từ trước → TỰ LIÊN KẾT rồi đăng nhập. An toàn vì Google
 *    đã tự xác minh người này THẬT SỰ sở hữu hộp thư đó (OAuth chỉ trả về
 *    sau khi họ đăng nhập thành công vào chính tài khoản Google chứa email
 *    này) — không phải tự nhận email suông như form thường.
 * 3. Hoàn toàn mới → CHƯA tạo User ngay (bảng `users` bắt buộc phải có số
 *    điện thoại, mà Google không cung cấp) — tạm giữ thông tin Google trong
 *    session, chuyển qua màn "Hoàn tất đăng ký" để nhập số điện thoại + tên
 *    quán (xem RegisterController::showGooglePhone()/storeGooglePhone()).
 */
class GoogleAuthController extends Controller
{
    public function redirect(): RedirectResponse
    {
        // Phòng khi ai đó vào thẳng URL này (bookmark cũ, bot quét...) lúc
        // chưa cấu hình GOOGLE_CLIENT_ID/SECRET — nút ở giao diện đã tự ẩn
        // (xem auth/login.blade.php, auth/register.blade.php) nhưng route
        // vẫn tồn tại, nên chặn thêm ở đây để không văng lỗi 500 thô.
        if (! config('services.google.client_id')) {
            return redirect()->route('login')->withErrors(['phone' => 'Đăng nhập Google hiện chưa khả dụng.']);
        }

        return Socialite::driver('google')->redirect();
    }

    public function callback(Request $request): RedirectResponse
    {
        try {
            $googleUser = Socialite::driver('google')->user();
        } catch (\Throwable $e) {
            Log::warning('Đăng nhập Google thất bại', ['error' => $e->getMessage()]);

            return redirect()->route('login')->withErrors(['phone' => 'Đăng nhập Google không thành công, vui lòng thử lại.']);
        }

        $user = User::where('google_id', $googleUser->getId())->first();

        if (! $user && $googleUser->getEmail()) {
            $user = User::where('email', $googleUser->getEmail())->first();

            if ($user) {
                $user->forceFill(['google_id' => $googleUser->getId()])->save();
            }
        }

        if ($user) {
            if (! $user->is_active) {
                return redirect()->route('login')->withErrors(['phone' => 'Tài khoản của bạn đã bị khoá.']);
            }

            Auth::login($user, remember: true);
            $request->session()->regenerate();

            // Super Admin cũng là chủ quán: bản cài cũ chưa có quán riêng thì tạo ngay lúc này.
            app(LocationProvisioner::class)->ensureFor($user);

            // Chỉ chọn trong số các location ĐANG HOẠT ĐỘNG — khớp đúng logic
            // ở LoginController::login() (quán bị Super Admin tạm khoá thì
            // không cho vào bán hàng nữa).
            $firstLocation = $user->isOwner()
                ? $user->ownedLocations()->where('is_active', true)->first()
                : $user->locations()->where('locations.is_active', true)->first();

            if (! $firstLocation) {
                Auth::logout();

                return redirect()->route('login')->withErrors(['phone' => 'Quán của bạn đang tạm khoá hoặc chưa được kích hoạt. Vui lòng liên hệ hỗ trợ.']);
            }

            $request->session()->put('current_location_id', $firstLocation->id);

            return redirect()->intended(route('pos.order'));
        }

        // Đăng ký đang đóng: chỉ tài khoản ĐÃ CÓ trong hệ thống mới được đăng nhập bằng Google.
        if (! PlatformSetting::registrationOpen()) {
            return redirect()->route('login')->withErrors(['phone' => 'Tài khoản Google này chưa có trong hệ thống và hệ thống hiện không nhận đăng ký mới.']);
        }

        // Chưa từng có tài khoản nào khớp — giữ tạm thông tin Google trong
        // session (KHÔNG lưu DB) để dùng ở bước nhập số điện thoại tiếp theo.
        // Tự hết hạn theo session bình thường nếu người dùng bỏ dở giữa chừng.
        $request->session()->put('google_pending', [
            'google_id' => $googleUser->getId(),
            'email' => $googleUser->getEmail(),
            'name' => $googleUser->getName() ?: $googleUser->getNickname() ?: 'Chủ quán',
            'avatar_url' => $googleUser->getAvatar(),
        ]);

        return redirect()->route('register.google-phone');
    }
}
