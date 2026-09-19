<div x-data="{ changingPassword: false }">
    <div class="bg-white rounded-2xl p-5 border border-neutral-200 shadow-sm mb-5">
        <form method="POST" action="{{ route('profile.update') }}" enctype="multipart/form-data" class="space-y-4">
            @csrf @method('PUT')

            <div class="flex items-center gap-4">
                <label class="relative cursor-pointer group shrink-0">
                    @if($user->avatar_url)
                        <img src="{{ $user->avatar_url }}" class="w-20 h-20 rounded-full object-cover border border-neutral-200" alt="">
                    @else
                        <div class="w-20 h-20 rounded-full bg-neutral-100 text-neutral-400 flex items-center justify-center text-2xl border border-neutral-200">
                            <i class="fa-solid fa-user"></i>
                        </div>
                    @endif
                    <div class="absolute inset-0 rounded-full bg-black/40 opacity-0 group-hover:opacity-100 flex items-center justify-center transition">
                        <i class="fa-solid fa-camera text-white"></i>
                    </div>
                    <input type="file" name="avatar" accept="image/*" class="hidden" onchange="this.form.submit()">
                </label>
                <div class="text-xs text-neutral-500">
                    Bấm vào ảnh để đổi ảnh đại diện.<br>Ảnh sẽ được cắt vuông và nén tự động.
                </div>
            </div>

            <x-input name="name" label="Tên hiển thị" icon="fa-user" value="{{ $user->name }}" required autocomplete="name" />

            <div>
                <p class="block text-sm font-medium text-neutral-700 mb-2">Số điện thoại</p>
                <div class="w-full rounded-xl border border-neutral-200 bg-neutral-50 text-neutral-500 py-3 px-4">
                    {{ $user->phone }}
                </div>
                <p class="text-xs text-neutral-400 mt-1">
                    Số điện thoại dùng để đăng nhập.
                    @unless($user->isPlatformAdmin())
                        Liên hệ chủ quán nếu cần đổi.
                    @endunless
                </p>
            </div>

            <div class="flex items-center gap-2 text-xs text-neutral-500">
                @if($user->isPlatformAdmin())
                    <span class="px-2 py-1 rounded-full bg-neutral-900 text-white font-medium">Super Admin</span>
                @elseif($user->isOwner())
                    <span class="px-2 py-1 rounded-full bg-amber-100 text-amber-700 font-medium">Chủ quán</span>
                @else
                    <span class="px-2 py-1 rounded-full bg-neutral-100 text-neutral-600 font-medium">Nhân viên</span>
                @endif
            </div>

            <x-button icon="fa-floppy-disk">Lưu thay đổi</x-button>
        </form>
    </div>

    <div class="bg-white rounded-2xl p-5 border border-neutral-200 shadow-sm">
        <button @click="changingPassword = !changingPassword" class="text-sm font-semibold text-neutral-700 flex items-center gap-2 w-full">
            <i class="fa-solid fa-key text-neutral-400"></i>Đổi mật khẩu
            <i class="fa-solid fa-chevron-down text-xs text-neutral-400 ml-auto transition" :class="changingPassword && 'rotate-180'"></i>
        </button>

        <form x-show="changingPassword" x-cloak method="POST" action="{{ route('profile.password') }}" class="space-y-3 mt-4 pt-4 border-t border-neutral-100">
            @csrf @method('PUT')
            <x-input type="password" name="current_password" label="Mật khẩu hiện tại" icon="fa-lock" required autocomplete="current-password" />
            <x-input type="password" name="password" label="Mật khẩu mới (tối thiểu 8 ký tự)" icon="fa-lock" required autocomplete="new-password" />
            <x-input type="password" name="password_confirmation" label="Nhập lại mật khẩu mới" icon="fa-lock" required autocomplete="new-password" />
            <x-button variant="ghost" icon="fa-check">Đổi mật khẩu</x-button>
        </form>
    </div>
</div>
