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

            <div>
                <x-input name="email" type="email" label="Email khôi phục" icon="fa-envelope" value="{{ old('email', $user->email) }}" autocomplete="email" placeholder="ban@example.com" />
                <p class="text-xs text-neutral-400 mt-1">
                    Dùng để lấy lại mật khẩu nếu quên — chưa điền thì không dùng được "Quên mật khẩu".
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
            <i class="fa-solid fa-key text-neutral-400"></i>{{ $user->google_id ? 'Đặt mật khẩu' : 'Đổi mật khẩu' }}
            <i class="fa-solid fa-chevron-down text-xs text-neutral-400 ml-auto transition" :class="changingPassword && 'rotate-180'"></i>
        </button>

        <form x-show="changingPassword" x-cloak method="POST" action="{{ route('profile.password') }}" class="space-y-3 mt-4 pt-4 border-t border-neutral-100">
            @csrf @method('PUT')
            @if($user->google_id)
                <p class="text-xs text-neutral-500 leading-relaxed">
                    <i class="fa-brands fa-google mr-1"></i>Tài khoản đăng nhập bằng Google. Đặt thêm mật khẩu ở đây để có thể đăng nhập bằng số điện thoại nếu cần, không ảnh hưởng tới việc đăng nhập bằng Google.
                </p>
            @else
                <x-input type="password" name="current_password" label="Mật khẩu hiện tại" icon="fa-lock" required autocomplete="current-password" />
            @endif
            <x-input type="password" name="password" label="Mật khẩu mới (tối thiểu 8 ký tự)" icon="fa-lock" required autocomplete="new-password" />
            <x-input type="password" name="password_confirmation" label="Nhập lại mật khẩu mới" icon="fa-lock" required autocomplete="new-password" />
            <x-button variant="ghost" icon="fa-check">{{ $user->google_id ? 'Đặt mật khẩu' : 'Đổi mật khẩu' }}</x-button>
        </form>
    </div>

    {{--
        CLOUD ONLY — Vùng nguy hiểm: chỉ CHỦ QUÁN được tự xoá tài khoản.
        Nhân viên do chủ quán quản lý ở trang Nhân viên; Super Admin không tự
        xoá được (sẽ mất quyền quản trị nền tảng).
    --}}
    @if($user->isOwner() && ! $user->isPlatformAdmin())
        <div id="vung-nguy-hiem" x-data="{ open: {{ ($errors->has('delete_password') || $errors->has('confirmation')) ? 'true' : 'false' }} }" class="bg-white rounded-2xl p-5 border border-red-200 shadow-sm mt-5">
            <button type="button" @click="open = !open" class="text-sm font-semibold text-red-600 flex items-center gap-2 w-full">
                <i class="fa-solid fa-triangle-exclamation"></i>Vùng nguy hiểm
                <i class="fa-solid fa-chevron-down text-xs text-red-400 ml-auto transition" :class="open && 'rotate-180'"></i>
            </button>

            <div x-show="open" x-cloak class="mt-4 pt-4 border-t border-red-100 space-y-4">
                <div>
                    <p class="text-sm font-semibold text-neutral-900 mb-1">Xoá tài khoản</p>
                    <p class="text-xs text-neutral-500 leading-relaxed">
                        Xoá VĨNH VIỄN tài khoản này cùng <strong>TOÀN BỘ</strong> xe cà phê bạn đang sở hữu — thực đơn, kho, khách hàng, ca làm, sổ quỹ và lịch sử đơn hàng.
                        <strong>Không thể khôi phục</strong> sau khi xoá.
                    </p>
                    <a href="{{ route('owner.settings.data-export') }}" class="inline-flex items-center gap-1.5 text-xs font-semibold text-amber-700 hover:text-amber-800 mt-2">
                        <i class="fa-solid fa-download"></i>Tải xuống dữ liệu trước khi xoá
                    </a>
                </div>

                <form method="POST" action="{{ route('profile.destroy') }}" x-data="{ confirmText: '' }"
                      onsubmit="return confirm('Xoá VĨNH VIỄN tài khoản và toàn bộ dữ liệu? Hành động này KHÔNG THỂ hoàn tác.')"
                      class="space-y-3">
                    @csrf @method('DELETE')

                    @if($user->google_id)
                        <p class="text-xs text-neutral-500 bg-neutral-50 rounded-lg px-3 py-2.5 leading-relaxed">
                            <i class="fa-brands fa-google mr-1"></i>Tài khoản này đăng nhập bằng Google, không cần nhập mật khẩu — gõ đúng cụm xác nhận bên dưới là đủ.
                        </p>
                    @else
                        <x-input type="password" name="delete_password" label="Nhập mật khẩu hiện tại để xác nhận" icon="fa-lock" required autocomplete="current-password" />
                    @endif

                    <div>
                        <label for="confirmation" class="block text-sm font-medium text-neutral-700 mb-2">
                            Gõ đúng cụm <span class="font-mono font-bold text-red-600">XOA TAI KHOAN</span> để xác nhận
                        </label>
                        <input type="text" name="confirmation" id="confirmation" x-model="confirmText" autocomplete="off"
                               placeholder="XOA TAI KHOAN"
                               class="w-full rounded-xl border border-neutral-300 bg-white text-neutral-900 py-3 px-4 text-base font-mono focus:border-red-400 focus:ring-2 focus:ring-red-500/20 focus:outline-none transition">
                        @error('confirmation')
                            <p class="mt-1.5 text-xs text-red-600 flex items-center gap-1"><i class="fa-solid fa-circle-exclamation"></i>{{ $message }}</p>
                        @enderror
                    </div>

                    <x-button variant="danger" icon="fa-trash" x-bind:disabled="confirmText !== 'XOA TAI KHOAN'">
                        Xoá vĩnh viễn tài khoản của tôi
                    </x-button>
                </form>
            </div>
        </div>
    @endif
</div>
