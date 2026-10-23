<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\FormGuard;
use App\Services\LocationProvisioner;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;

/**
 * CLOUD ONLY — đăng ký công khai, thay thế hoàn toàn cho trình cài đặt
 * /install của bản self-hosted (vốn chỉ chạy 1 lần lúc triển khai nền tảng).
 * Tạo cùng lúc: tài khoản chủ quán (role=owner) + quán đầu tiên của họ.
 *
 * Giai đoạn đầu: KHÔNG giới hạn tính năng nào theo gói cước — mọi quán đăng
 * ký mới đều dùng được toàn bộ tính năng miễn phí. Khi nào cần thu phí, chỉ
 * cần thêm điều kiện kiểm tra ở đây và ở Location (xem cột dự phòng
 * `plan`/`trial_ends_at` nếu đã thêm qua migration sau này).
 */
class RegisterController extends Controller
{
    public function show(): Response
    {
        // Cùng lý do với LoginController::show() — trang chứa token FormGuard
        // phải luôn mới, không được để bất kỳ lớp cache nào (Nginx, plugin
        // cache WordPress dùng chung VPS...) lưu lại response này.
        return response()
            ->view('auth.register')
            ->header('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0')
            ->header('Pragma', 'no-cache');
    }

    public function store(Request $request): RedirectResponse
    {
        // Chặn bot TRƯỚC khi validate dữ liệu thật — Honeypot + token thời
        // gian ký HMAC. Cố tình dùng chung 1 thông báo lỗi mơ hồ cho cả 2,
        // không tiết lộ cơ chế phát hiện cụ thể cho bot dò cách né tránh.
        $isBot = ! FormGuard::verify(
            $request,
            'register',
            $request->input('form_ts'),
            $request->input('form_sig'),
            $request->input('hp_key'),
        );

        if ($isBot) {
            return back()->withInput($request->except('password', 'password_confirmation'))
                ->withErrors(['shop_name' => 'Phiên đăng ký đã hết hạn hoặc có lỗi xảy ra. Vui lòng tải lại trang và thử lại.']);
        }

        // Chặn spam tạo tài khoản hàng loạt — honeypot/token chỉ chặn bot đơn
        // giản, không chặn được ai đó cố tình gửi request lặp lại đúng định
        // dạng. Giới hạn RỘNG RÃI (10 lần/giờ/IP) vì đây là đăng ký thật của
        // người dùng thật, không phải hành động lặp lại nhiều lần như login.
        $throttleKey = 'register:'.$request->ip();

        if (RateLimiter::tooManyAttempts($throttleKey, 10)) {
            $seconds = RateLimiter::availableIn($throttleKey);
            $minutes = (int) ceil($seconds / 60);

            return back()->withInput($request->except('password', 'password_confirmation'))
                ->withErrors(['shop_name' => "Bạn thao tác quá nhiều lần. Vui lòng thử lại sau khoảng {$minutes} phút."]);
        }

        RateLimiter::hit($throttleKey, 3600);

        $data = $request->validate([
            'shop_name' => 'required|string|max:100',
            'name' => 'required|string|max:100',
            'phone' => 'required|string|max:20|unique:users,phone',
            'password' => 'required|string|min:8|confirmed',
            'terms' => 'accepted',
        ], [
            'terms.accepted' => 'Bạn cần đồng ý với Điều khoản sử dụng và Chính sách bảo mật để tiếp tục đăng ký.',
        ]);

        [$owner, $location] = DB::transaction(function () use ($data) {
            $owner = User::forceCreate([
                'name' => $data['name'],
                'phone' => $data['phone'],
                'password' => Hash::make($data['password']),
                'role' => 'owner',
                'is_active' => true,
            ]);

            $location = app(LocationProvisioner::class)->createFor($owner, $data['shop_name']);

            return [$owner, $location];
        });

        // Đăng nhập SAU KHI transaction đã commit thành công — gọi Auth::login()
        // bên trong transaction là sai: nếu 1 bước nào đó sau đó lỗi khiến
        // transaction rollback, session sẽ đăng nhập vào 1 user đã KHÔNG CÒN
        // tồn tại trong database, gây lỗi khó hiểu ở các request tiếp theo.
        Auth::login($owner);
        $request->session()->regenerate();
        $request->session()->put('current_location_id', $location->id);

        return redirect()->route('pos.order')->with('status', 'Chào mừng đến với Rót! Vào Thực đơn để thiết lập món trước khi bán nhé.');
    }

    /**
     * Bước "Hoàn tất đăng ký" sau khi đăng nhập Google lần đầu (xem
     * GoogleAuthController::callback()) — bảng `users` bắt buộc phải có số
     * điện thoại nên KHÔNG thể tạo tài khoản ngay lúc callback, phải hỏi
     * thêm bước này trước khi thực sự tạo User + quán.
     */
    public function showGooglePhone(Request $request): RedirectResponse|Response
    {
        $pending = $request->session()->get('google_pending');

        if (! $pending) {
            return redirect()->route('register');
        }

        return response()
            ->view('auth.google-phone', ['pending' => $pending])
            ->header('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0')
            ->header('Pragma', 'no-cache');
    }

    public function storeGooglePhone(Request $request): RedirectResponse
    {
        $pending = $request->session()->get('google_pending');

        if (! $pending) {
            return redirect()->route('register');
        }

        $isBot = ! FormGuard::verify(
            $request,
            'register-google-phone',
            $request->input('form_ts'),
            $request->input('form_sig'),
            $request->input('hp_key'),
        );

        if ($isBot) {
            return back()->withErrors(['shop_name' => 'Phiên đăng ký đã hết hạn hoặc có lỗi xảy ra. Vui lòng tải lại trang và thử lại.']);
        }

        $throttleKey = 'register:'.$request->ip();

        if (RateLimiter::tooManyAttempts($throttleKey, 10)) {
            $seconds = RateLimiter::availableIn($throttleKey);
            $minutes = (int) ceil($seconds / 60);

            return back()->withErrors(['shop_name' => "Bạn thao tác quá nhiều lần. Vui lòng thử lại sau khoảng {$minutes} phút."]);
        }

        RateLimiter::hit($throttleKey, 3600);

        $data = $request->validate([
            'shop_name' => 'required|string|max:100',
            'phone' => 'required|string|max:20|unique:users,phone',
            'terms' => 'accepted',
        ], [
            'terms.accepted' => 'Bạn cần đồng ý với Điều khoản sử dụng và Chính sách bảo mật để tiếp tục đăng ký.',
        ]);

        [$owner, $location] = DB::transaction(function () use ($data, $pending) {
            $owner = User::forceCreate([
                'name' => $pending['name'],
                'phone' => $data['phone'],
                'email' => $pending['email'],
                'email_verified_at' => $pending['email'] ? now() : null,
                'google_id' => $pending['google_id'],
                'avatar_url' => $pending['avatar_url'],
                // Đăng nhập bằng Google không bao giờ dùng tới mật khẩu này —
                // vẫn phải điền vì cột `password` NOT NULL, dùng chuỗi ngẫu
                // nhiên không ai đoán được thay vì để trống/giá trị cố định.
                'password' => Hash::make(Str::random(40)),
                'role' => 'owner',
                'is_active' => true,
            ]);

            $location = app(LocationProvisioner::class)->createFor($owner, $data['shop_name']);

            return [$owner, $location];
        });

        $request->session()->forget('google_pending');

        Auth::login($owner);
        $request->session()->regenerate();
        $request->session()->put('current_location_id', $location->id);

        return redirect()->route('pos.order')->with('status', 'Chào mừng đến với Rót! Vào Thực đơn để thiết lập món trước khi bán nhé.');
    }
}
