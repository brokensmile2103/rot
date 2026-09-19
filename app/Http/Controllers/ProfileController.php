<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
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

        // RÓT CLOUD ONLY — Super Admin không thuộc layout có sidebar cửa hàng
        // (layouts.app), dùng view riêng bọc layouts.admin. Logic form/lưu
        // hoàn toàn dùng chung (xem profile/_form.blade.php).
        if (method_exists($user, 'isPlatformAdmin') && $user->isPlatformAdmin()) {
            return view('admin.profile', compact('user'));
        }

        return view('profile.edit', compact('user'));
    }

    public function update(Request $request): RedirectResponse
    {
        $user = $request->user();

        $data = $request->validate([
            'name' => 'required|string|max:100',
            'avatar' => 'nullable|image|mimes:jpeg,png,jpg,webp|max:4096',
        ]);

        if ($request->hasFile('avatar') && $request->file('avatar')->isValid()) {
            $this->deleteAvatar($user);
            $data['avatar_url'] = $this->storeAvatar($request->file('avatar'));
        }

        $user->update(['name' => $data['name']] + (isset($data['avatar_url']) ? ['avatar_url' => $data['avatar_url']] : []));

        return back()->with('status', 'Đã cập nhật hồ sơ.');
    }

    public function updatePassword(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'current_password' => 'required|string',
            'password' => 'required|string|min:8|confirmed',
        ]);

        $user = $request->user();

        if (! Hash::check($data['current_password'], $user->password)) {
            return back()->withErrors(['current_password' => 'Mật khẩu hiện tại không đúng.']);
        }

        $user->update(['password' => Hash::make($data['password'])]);

        return back()->with('status', 'Đã đổi mật khẩu.');
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
