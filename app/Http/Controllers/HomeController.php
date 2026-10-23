<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

/**
 * CLOUD ONLY — trang chủ công khai, thay cho redirect thẳng vào /order
 * như trước đây. Redirect thẳng vào 1 route yêu cầu đăng nhập khiến domain
 * gốc không có gì để Google index (khách vãng lai + Googlebot đều bị đá qua
 * /login ngay), rất bất lợi cho SEO/tiếp thị. Người ĐÃ đăng nhập vẫn được
 * đưa thẳng vào khu vực làm việc của họ — không cần xem lại trang giới thiệu
 * mỗi lần ghé domain gốc.
 */
class HomeController extends Controller
{
    public function index(): View|RedirectResponse
    {
        if (Auth::check()) {
            return redirect()->intended(route('pos.order'));
        }

        return view('home');
    }
}
