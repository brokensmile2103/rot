<?php

namespace App\Http\Controllers;

use App\Models\Page;
use Illuminate\Http\Response;
use Illuminate\View\View;

/** CLOUD ONLY — hiển thị công khai 3 trang tĩnh, nội dung lấy từ database. */
class PageController extends Controller
{
    public function show(string $slug): View
    {
        $page = Page::where('slug', $slug)->firstOrFail();

        return view('pages.show', compact('page'));
    }

    /**
     * Sitemap chỉ liệt kê các trang CÔNG KHAI và ĐÁNG được index (không đưa
     * /login vào vì trang đó cố tình đặt noindex). Build URL bằng route()
     * để luôn khớp đúng domain thật đang chạy, không hardcode domain.
     */
    public function sitemap(): Response
    {
        $urls = [
            ['loc' => route('home'), 'priority' => '1.0'],
            ['loc' => route('register'), 'priority' => '0.9'],
            ['loc' => route('pages.about'), 'priority' => '0.7'],
            ['loc' => route('pages.terms'), 'priority' => '0.3'],
            ['loc' => route('pages.privacy'), 'priority' => '0.3'],
        ];

        // Đăng ký đang đóng thì không đưa trang đăng ký vào sitemap.
        if (! \App\Models\PlatformSetting::registrationOpen()) {
            $urls = array_values(array_filter($urls, fn ($u) => $u['loc'] !== route('register')));
        }

        $xml = view('sitemap', compact('urls'))->render();

        return response($xml, 200)->header('Content-Type', 'application/xml');
    }

    /**
     * robots.txt DỘNG thay vì file tĩnh trong public/ — vì mỗi quán deploy
     * Rót trên 1 domain khác nhau, và dòng "Sitemap:" theo chuẩn phải
     * là URL TUYỆT ĐỐI, nên phải build lúc chạy bằng url() chứ không hardcode.
     * Chặn toàn bộ khu vực cần đăng nhập (POS, quản lý quán, Super Admin) —
     * dù đằng nào bot cũng không đăng nhập được, nhưng chặn rõ ràng để không
     * lãng phí ngân sách crawl của Google vào các URL luôn trả về redirect.
     */
    public function robots(): Response
    {
        $lines = [
            'User-agent: *',
            'Allow: /',
            'Disallow: /order',
            'Disallow: /don-hang',
            'Disallow: /ca',
            'Disallow: /khach-hang',
            'Disallow: /sieu-quan-tri',
            'Disallow: /install',
            'Disallow: /login',
            '',
            'Sitemap: '.url('/sitemap.xml'),
        ];

        return response(implode("\n", $lines), 200)->header('Content-Type', 'text/plain');
    }
}
