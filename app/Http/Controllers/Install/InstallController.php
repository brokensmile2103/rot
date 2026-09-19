<?php

namespace App\Http\Controllers\Install;

use App\Http\Controllers\Controller;
use App\Models\Location;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\View\View;

/**
 * Luồng cài đặt kiểu WordPress — chạy hoàn toàn qua trình duyệt, KHÔNG cần SSH/CLI,
 * để tương thích với hosting giá rẻ (cPanel) là môi trường phổ biến nhất của khách hàng mục tiêu.
 *
 * Các bước: welcome -> requirements -> database -> (đợi F5 để nạp config mới) -> account -> location -> finish
 */
class InstallController extends Controller
{
    public function welcome(): View
    {
        return view('install.welcome');
    }

    public function requirements(): View
    {
        $checks = [
            'PHP >= 8.2' => version_compare(PHP_VERSION, '8.2.0', '>='),
            'Extension pdo_mysql' => extension_loaded('pdo_mysql'),
            'Extension mbstring' => extension_loaded('mbstring'),
            'Extension openssl' => extension_loaded('openssl'),
            'Extension tokenizer' => extension_loaded('tokenizer'),
            'Extension ctype' => extension_loaded('ctype'),
            'Thư mục storage/ ghi được' => is_writable(storage_path()),
            'Thư mục bootstrap/cache ghi được' => is_writable(base_path('bootstrap/cache')),
            'File .env ghi được' => is_writable(base_path('.env')) || is_writable(base_path()),
        ];

        $allPassed = ! in_array(false, $checks, true);

        return view('install.requirements', compact('checks', 'allPassed'));
    }

    public function showDatabaseForm(): View
    {
        return view('install.database');
    }

    /**
     * Test kết nối DB, ghi vào .env, sinh APP_KEY, rồi chạy migrate ngay
     * (Artisan::call chạy được qua request web, không cần quyền CLI trên server).
     */
    public function saveDatabase(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'db_host' => 'required|string',
            'db_port' => 'required|string',
            'db_database' => 'required|string',
            'db_username' => 'required|string',
            'db_password' => 'nullable|string',
        ]);

        try {
            config([
                'database.connections.mysql.host' => $data['db_host'],
                'database.connections.mysql.port' => $data['db_port'],
                'database.connections.mysql.database' => $data['db_database'],
                'database.connections.mysql.username' => $data['db_username'],
                'database.connections.mysql.password' => $data['db_password'] ?? '',
            ]);

            \DB::purge('mysql');
            \DB::connection('mysql')->getPdo();
        } catch (\Throwable $e) {
            return back()->withInput()->withErrors([
                'db_host' => 'Không kết nối được database. Kiểm tra lại thông tin (chi tiết lỗi đã ghi vào log).',
            ]);
        }

        $this->writeEnv([
            'DB_CONNECTION' => 'mysql',
            'DB_HOST' => $data['db_host'],
            'DB_PORT' => $data['db_port'],
            'DB_DATABASE' => $data['db_database'],
            'DB_USERNAME' => $data['db_username'],
            'DB_PASSWORD' => $data['db_password'] ?? '',
        ]);

        if (empty(env('APP_KEY'))) {
            Artisan::call('key:generate', ['--force' => true]);
        }

        Artisan::call('config:clear');
        Artisan::call('migrate', ['--force' => true]);

        // Tạo symlink public/storage -> storage/app/public để ảnh upload (món, sau
        // này có thể có thêm avatar...) truy cập công khai được. Một số hosting rẻ
        // tắt hàm symlink() vì lý do bảo mật — không để lỗi này chặn cả quá trình cài,
        // chỉ ghi log, người dùng có thể tự tạo bằng tay theo hướng dẫn trong README.
        try {
            Artisan::call('storage:link');
        } catch (\Throwable $e) {
            \Log::warning('Không thể tự tạo storage:link, cần tạo thủ công: '.$e->getMessage());
        }

        return redirect()->route('install.account');
    }

    public function showAccountForm(): View
    {
        return view('install.account');
    }

    public function saveAccount(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => 'required|string|max:100',
            'phone' => 'required|string|max:20|unique:users,phone',
            'password' => 'required|string|min:8|confirmed',
        ]);

        $owner = User::forceCreate([
            'name' => $data['name'],
            'phone' => $data['phone'],
            'password' => Hash::make($data['password']),
            'role' => 'owner',
            'is_active' => true,
        ]);

        session(['install.owner_id' => $owner->id]);

        return redirect()->route('install.location');
    }

    public function showLocationForm(): View
    {
        return view('install.location');
    }

    public function saveLocation(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => 'required|string|max:100',
            'address' => 'nullable|string|max:255',
        ]);

        $ownerId = session('install.owner_id');
        $owner = User::findOrFail($ownerId);

        $location = Location::forceCreate([
            'owner_id' => $owner->id,
            'name' => $data['name'],
            'address' => $data['address'] ?? null,
            'is_active' => true,
        ]);

        $location->staff()->attach($owner->id);

        // Danh mục & món mẫu để chủ quán thấy ngay giao diện order có gì, sửa/xoá sau đều được.
        $category = $location->categories()->create(['name' => 'Cà phê', 'sort_order' => 0]);
        $product = $category->products()->create(['name' => 'Cà phê đen', 'sort_order' => 0]);
        $product->variants()->create(['name' => 'Ly', 'price' => 20000, 'is_default' => true]);

        file_put_contents(storage_path('installed.lock'), now()->toDateTimeString());

        auth()->login($owner);
        session()->forget('install.owner_id');
        session(['current_location_id' => $location->id]);

        return redirect()->route('install.finish');
    }

    public function finish(): View
    {
        return view('install.finish');
    }

    private function writeEnv(array $values): void
    {
        $envPath = base_path('.env');
        $content = file_exists($envPath) ? file_get_contents($envPath) : file_get_contents(base_path('.env.example'));

        foreach ($values as $key => $value) {
            $escaped = preg_quote($key, '/');
            $line = $key.'='.(Str::contains($value, ' ') ? '"'.$value.'"' : $value);

            if (preg_match("/^{$escaped}=.*/m", $content)) {
                $content = preg_replace("/^{$escaped}=.*/m", $line, $content);
            } else {
                $content .= "\n{$line}";
            }
        }

        file_put_contents($envPath, $content);
    }
}
