@extends('layouts.app')
@section('title', 'Cài đặt · Rót')
@section('page-title', 'Cài đặt')
@section('content')
<div x-data="{ einvoiceOpen: {{ $location->einvoice_enabled ? 'true' : 'false' }} }" class="p-4 pb-24 md:p-8 max-w-lg mx-auto space-y-5">
    <div class="flex items-center gap-3 mb-5 md:mb-6">
        <div class="w-10 h-10 rounded-xl bg-amber-100 text-amber-700 flex items-center justify-center shrink-0">
            <i class="fa-solid fa-palette"></i>
        </div>
        <h2 class="text-lg font-bold text-neutral-900">Cài đặt — {{ $location->name }}</h2>
    </div>

    @if ($errors->any())
        <div class="rounded-xl bg-red-50 border border-red-200 px-4 py-3 text-sm text-red-700">
            <p class="font-semibold mb-1">Có lỗi khi lưu:</p>
            <ul class="list-disc list-inside space-y-0.5">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    {{-- Giao diện --}}
    <form method="POST" action="{{ route('owner.settings.appearance') }}" class="bg-white rounded-2xl p-5 border border-neutral-200 shadow-sm space-y-6">
        @csrf @method('PUT')
        <p class="text-sm font-semibold text-neutral-700 flex items-center gap-2">
            <i class="fa-solid fa-palette text-neutral-400"></i>Giao diện
        </p>

        <div>
            <p class="block text-sm font-medium text-neutral-700 mb-3">Cỡ chữ</p>
            <div class="grid grid-cols-3 gap-2">
                @foreach(['sm' => 'Nhỏ', 'md' => 'Vừa', 'lg' => 'Lớn'] as $value => $label)
                    <label class="cursor-pointer">
                        <input type="radio" name="font_scale" value="{{ $value }}" class="peer hidden" {{ old('font_scale', $location->font_scale) === $value ? 'checked' : '' }}>
                        <span class="flex items-center justify-center py-3 rounded-xl border-2 border-neutral-200 peer-checked:border-amber-600 peer-checked:bg-amber-50 text-neutral-700 font-medium transition"
                              style="font-size: {{ ['sm' => '0.85rem', 'md' => '1rem', 'lg' => '1.2rem'][$value] }}">
                            {{ $label }}
                        </span>
                    </label>
                @endforeach
            </div>
            <p class="text-xs text-neutral-400 mt-2">Áp dụng cho toàn bộ giao diện bán hàng trên xe này, không ảnh hưởng xe khác.</p>
        </div>

        <div>
            <p class="block text-sm font-medium text-neutral-700 mb-3">Màu chủ đạo</p>
            <div class="grid grid-cols-6 gap-2">
                @php
                    $colorSwatch = [
                        'amber' => '#d97706', 'orange' => '#ea580c', 'blue' => '#2563eb',
                        'emerald' => '#059669', 'rose' => '#e11d48', 'violet' => '#7c3aed',
                    ];
                @endphp
                @foreach($accentColors as $color)
                    <label class="cursor-pointer">
                        <input type="radio" name="accent_color" value="{{ $color }}" class="peer hidden" {{ old('accent_color', $location->accent_color) === $color ? 'checked' : '' }}>
                        <span class="flex items-center justify-center w-full aspect-square rounded-xl border-2 border-transparent peer-checked:border-neutral-900 transition"
                              style="background-color: {{ $colorSwatch[$color] }}">
                            <i class="fa-solid fa-check text-white text-xs opacity-0 peer-checked:opacity-100"></i>
                        </span>
                    </label>
                @endforeach
            </div>
            <p class="text-xs text-neutral-400 mt-2">Đổi màu nút bấm, tab đang chọn, điểm nhấn... trong toàn bộ giao diện.</p>
        </div>

        <x-button icon="fa-floppy-disk">Lưu giao diện</x-button>
    </form>

    {{-- In hoá đơn --}}
    <form method="POST" action="{{ route('owner.settings.receipt') }}" class="bg-white rounded-2xl p-5 border border-neutral-200 shadow-sm space-y-5">
        @csrf @method('PUT')
        <p class="text-sm font-semibold text-neutral-700 flex items-center gap-2">
            <i class="fa-solid fa-print text-neutral-400"></i>In hoá đơn
        </p>

        <div>
            <p class="block text-sm font-medium text-neutral-700 mb-3">Khổ giấy in hoá đơn</p>
            <div class="grid grid-cols-2 gap-2">
                @foreach([['58', 'Khổ 58mm (nhỏ)'], ['80', 'Khổ 80mm (phổ biến)']] as [$value, $label])
                    <label class="cursor-pointer">
                        <input type="radio" name="receipt_paper_width" value="{{ $value }}" class="peer hidden" {{ (string) old('receipt_paper_width', $location->receipt_paper_width) === $value ? 'checked' : '' }}>
                        <span class="flex items-center justify-center py-3 rounded-xl border-2 border-neutral-200 peer-checked:border-[var(--accent)] peer-checked:bg-[var(--accent-light)] text-neutral-700 font-medium text-sm transition">
                            {{ $label }}
                        </span>
                    </label>
                @endforeach
            </div>
            <p class="text-xs text-neutral-400 mt-2">Chọn đúng khổ giấy máy in nhiệt đang dùng để hoá đơn in ra vừa khít, không bị cắt chữ.</p>
        </div>

        <div class="flex items-center justify-between py-1">
            <div>
                <p class="block text-sm font-medium text-neutral-700">Tự mở hoá đơn sau khi lên đơn</p>
                <p class="text-xs text-neutral-400 mt-1">Tắt nếu quán không cần in hoá đơn — tránh mở tab thừa mỗi lần bán hàng.</p>
            </div>
            <label class="relative inline-flex items-center cursor-pointer shrink-0 ml-4">
                <input type="checkbox" name="receipt_enabled" value="1" class="sr-only peer" {{ old('receipt_enabled', $location->receipt_enabled) ? 'checked' : '' }}>
                <div class="w-11 h-6 bg-neutral-200 rounded-full peer-checked:bg-[var(--accent)] transition-colors"></div>
                <div class="absolute left-1 top-1 w-4 h-4 bg-white rounded-full transition-transform peer-checked:translate-x-5"></div>
            </label>
        </div>

        <x-button icon="fa-floppy-disk">Lưu cài đặt in</x-button>
    </form>

    {{-- Tài khoản ngân hàng --}}
    <form method="POST" action="{{ route('owner.settings.bank') }}" class="bg-white rounded-2xl p-5 border border-neutral-200 shadow-sm space-y-4">
        @csrf @method('PUT')
        <p class="text-sm font-semibold text-neutral-700 flex items-center gap-2">
            <i class="fa-solid fa-building-columns text-neutral-400"></i>Tài khoản ngân hàng nhận chuyển khoản
        </p>
        <p class="text-xs text-neutral-400 -mt-2">Điền đủ để tự động hiện mã QR VietQR khi khách chọn "Chuyển khoản" lúc thanh toán — khách quét là ra đúng số tiền, không cần đọc số tài khoản.</p>

        <div class="space-y-3">
            <div>
                <label for="bank_bin" class="block text-xs text-neutral-500 mb-1.5">Ngân hàng</label>
                <select name="bank_bin" id="bank_bin" class="w-full rounded-xl border border-neutral-300 bg-white text-neutral-900 py-2.5 px-3 text-sm focus:border-[var(--accent)] focus:ring-1 focus:ring-[var(--accent)] focus:outline-none">
                    <option value="">— Chưa chọn —</option>
                    @foreach($banks as $bin => $bankName)
                        <option value="{{ $bin }}" {{ old('bank_bin', $location->bank_bin) === (string) $bin ? 'selected' : '' }}>{{ $bankName }}</option>
                    @endforeach
                </select>
            </div>
            <x-input name="bank_account_no" label="Số tài khoản" icon="fa-credit-card" value="{{ old('bank_account_no', $location->bank_account_no) }}" inputmode="numeric" placeholder="Chỉ nhập số" autocomplete="off" />
            <x-input name="bank_account_name" label="Tên chủ tài khoản" icon="fa-user" value="{{ old('bank_account_name', $location->bank_account_name) }}" placeholder="VIET HOA KHONG DAU, đúng như trên thẻ" autocomplete="off" />
        </div>

        <x-button icon="fa-floppy-disk">Lưu tài khoản ngân hàng</x-button>
    </form>

    {{-- Khách hàng thân thiết --}}
    <form method="POST" action="{{ route('owner.settings.loyalty') }}" class="bg-white rounded-2xl p-5 border border-neutral-200 shadow-sm space-y-4">
        @csrf @method('PUT')
        <div class="flex items-center justify-between">
            <p class="text-sm font-semibold text-neutral-700 flex items-center gap-2">
                <i class="fa-solid fa-heart text-neutral-400"></i>Khách hàng thân thiết / Tích điểm
            </p>
            <label class="relative inline-flex items-center cursor-pointer shrink-0">
                <input type="checkbox" name="loyalty_enabled" value="1" class="sr-only peer" {{ old('loyalty_enabled', $location->loyalty_enabled) ? 'checked' : '' }}>
                <div class="w-11 h-6 bg-neutral-200 rounded-full peer-checked:bg-[var(--accent)] transition-colors"></div>
                <div class="absolute left-1 top-1 w-4 h-4 bg-white rounded-full transition-transform peer-checked:translate-x-5"></div>
            </label>
        </div>
        <p class="text-xs text-neutral-400 -mt-2">Bật để có thể nhập SĐT khách hàng lúc thanh toán, tự tích điểm và cho phép dùng điểm giảm giá.</p>

        <div class="grid grid-cols-2 gap-3">
            <x-input type="text" inputmode="numeric" class="money-input" name="points_earn_rate" label="Bao nhiêu đồng = 1 điểm" icon="fa-coins" value="{{ old('points_earn_rate', (float) $location->points_earn_rate) }}" autocomplete="off" />
            <x-input type="text" inputmode="numeric" class="money-input" name="points_redeem_value" label="1 điểm = bao nhiêu đồng" icon="fa-tag" value="{{ old('points_redeem_value', (float) $location->points_redeem_value) }}" autocomplete="off" />
        </div>
        <p class="text-xs text-neutral-400">VD mặc định: chi 10.000đ được 1 điểm, 1 điểm đổi lại được 1.000đ giảm giá.</p>

        <x-button icon="fa-floppy-disk">Lưu cài đặt tích điểm</x-button>
    </form>

    {{-- Đặt món qua QR — khách quét mã tự chọn món, nhân viên duyệt lại rồi mới thành đơn thật. --}}
    <div class="bg-white rounded-2xl p-5 border border-neutral-200 shadow-sm space-y-4">
        <form method="POST" action="{{ route('owner.settings.qr-ordering') }}" class="space-y-4">
            @csrf @method('PUT')
            <div class="flex items-center justify-between">
                <p class="text-sm font-semibold text-neutral-700 flex items-center gap-2">
                    <i class="fa-solid fa-qrcode text-neutral-400"></i>Đặt món qua QR
                </p>
                <label class="relative inline-flex items-center cursor-pointer shrink-0">
                    <input type="checkbox" name="qr_ordering_enabled" value="1" class="sr-only peer" {{ old('qr_ordering_enabled', $location->qr_ordering_enabled) ? 'checked' : '' }}>
                    <div class="w-11 h-6 bg-neutral-200 rounded-full peer-checked:bg-[var(--accent)] transition-colors"></div>
                    <div class="absolute left-1 top-1 w-4 h-4 bg-white rounded-full transition-transform peer-checked:translate-x-5"></div>
                </label>
            </div>
            <p class="text-xs text-neutral-400 -mt-2">Bật để khách quét mã QR dán tại xe, tự xem thực đơn và gửi yêu cầu gọi món. Yêu cầu sẽ hiện ở "Đơn hàng" để nhân viên kiểm tra rồi mới tính tiền — khách KHÔNG thanh toán trực tiếp qua đây.</p>

            <x-button icon="fa-floppy-disk">Lưu cài đặt đặt món qua QR</x-button>
        </form>

        @if($location->qr_ordering_enabled)
            <div class="pt-4 border-t border-neutral-100 flex flex-col sm:flex-row items-center gap-4">
                <img src="https://api.qrserver.com/v1/create-qr-code/?size=180x180&data={{ urlencode($location->publicMenuUrl()) }}"
                     alt="Mã QR menu" width="140" height="140" class="rounded-xl border border-neutral-200 shrink-0">
                <div class="min-w-0 w-full space-y-2">
                    <p class="text-xs text-neutral-500 font-medium">Link menu — in mã QR trên hoặc dán link này lên xe/bàn:</p>
                    <div class="flex items-center gap-2">
                        <input type="text" readonly value="{{ $location->publicMenuUrl() }}" onclick="this.select()"
                               class="flex-1 min-w-0 text-xs bg-neutral-50 border border-neutral-200 rounded-lg px-3 py-2 text-neutral-600">
                        <a href="{{ $location->publicMenuUrl() }}" target="_blank" class="shrink-0 text-xs px-3 py-2 rounded-lg bg-neutral-100 hover:bg-neutral-200 text-neutral-700 font-semibold transition">
                            <i class="fa-solid fa-arrow-up-right-from-square"></i> Xem thử
                        </a>
                    </div>
                    <form method="POST" action="{{ route('owner.settings.qr-ordering.regenerate') }}" onsubmit="return confirm('Link/mã QR cũ sẽ ngừng hoạt động ngay. Chắc chắn đổi mã mới?')">
                        @csrf
                        <button class="text-xs text-red-600 hover:underline font-medium">
                            <i class="fa-solid fa-arrows-rotate mr-1"></i>Đổi link/mã QR mới
                        </button>
                    </form>
                </div>
            </div>
        @endif
    </div>

    {{-- Chi phí vận hành — hiện chỉ có mặt bằng, dùng để tính "Lợi nhuận thực tế" ở trang Báo cáo.
         CỐ TÌNH ghi rõ tên xe ngay trong tiêu đề (giống trang Nhân viên) — vì đây LÀ cài đặt RIÊNG
         cho từng xe (giống màu giao diện, thông tin ngân hàng...), không phải cài đặt chung cho cả
         tài khoản, để chủ quán có nhiều xe không hiểu nhầm là 1 số áp dụng chung cho tất cả các xe. --}}
    <form method="POST" action="{{ route('owner.settings.costs') }}" class="bg-white rounded-2xl p-5 border border-neutral-200 shadow-sm space-y-3">
        @csrf @method('PUT')
        <p class="text-sm font-semibold text-neutral-700 flex items-center gap-2">
            <i class="fa-solid fa-shop text-neutral-400"></i>Chi phí vận hành — {{ $location->name }}
        </p>
        <x-input type="text" inputmode="numeric" class="money-input" name="rent_cost" label="Chi phí mặt bằng / tháng (riêng cho xe này)" icon="fa-house" value="{{ old('rent_cost', (float) $location->rent_cost) }}" autocomplete="off" />
        <p class="text-xs text-neutral-400 leading-relaxed">
            Chỉ áp dụng cho <strong>{{ $location->name }}</strong> — quán có nhiều xe thì mỗi xe đặt riêng, không dùng chung 1 số. Không có mặt bằng cố định (xe đẩy thuần) thì để 0. Số này cùng lương nhân viên (đặt ở trang Nhân viên) sẽ được cộng vào Báo cáo của đúng xe này để tính "Lợi nhuận thực tế".
        </p>
        <x-button icon="fa-floppy-disk">Lưu chi phí mặt bằng</x-button>
    </form>

    {{-- Hoá đơn điện tử — thu gọn mặc định (giống Đổi mật khẩu ở Hồ sơ), vì có nhiều trường kỹ thuật --}}
    <div class="bg-white rounded-2xl p-5 border border-neutral-200 shadow-sm">
        <button type="button" @click="einvoiceOpen = !einvoiceOpen" class="text-sm font-semibold text-neutral-700 flex items-center gap-2 w-full">
            <i class="fa-solid fa-file-invoice text-neutral-400"></i>Hoá đơn điện tử (SePay eInvoice)
            @if($location->einvoice_enabled)
                <span class="text-xs px-2 py-0.5 rounded-full bg-emerald-50 text-emerald-700 font-medium">Đang bật</span>
            @endif
            <i class="fa-solid fa-chevron-down text-xs text-neutral-400 ml-auto transition" :class="einvoiceOpen && 'rotate-180'"></i>
        </button>

        <form x-show="einvoiceOpen" x-cloak method="POST" action="{{ route('owner.settings.einvoice') }}" class="space-y-4 mt-4 pt-4 border-t border-neutral-100">
            @csrf @method('PUT')

            <div class="flex items-center justify-between">
                <p class="text-sm text-neutral-600">Bật xuất hoá đơn điện tử tự động</p>
                <label class="relative inline-flex items-center cursor-pointer shrink-0">
                    <input type="checkbox" name="einvoice_enabled" value="1" class="sr-only peer" {{ old('einvoice_enabled', $location->einvoice_enabled) ? 'checked' : '' }}>
                    <div class="w-11 h-6 bg-neutral-200 rounded-full peer-checked:bg-[var(--accent)] transition-colors"></div>
                    <div class="absolute left-1 top-1 w-4 h-4 bg-white rounded-full transition-transform peer-checked:translate-x-5"></div>
                </label>
            </div>
            <p class="text-xs text-neutral-400 -mt-3">
                Tự động xuất hoá đơn điện tử qua <a href="https://sepay.vn" target="_blank" class="underline">SePay eInvoice</a> ngay sau khi thanh toán — cần tự đăng ký tài khoản với SePay trước, Rót chỉ gọi API hộ bằng thông tin bạn nhập dưới đây.
            </p>

            <div class="flex items-center gap-2 bg-amber-50 border border-amber-200 rounded-xl px-3 py-2.5">
                <input type="checkbox" name="einvoice_sandbox" id="einvoice_sandbox" value="1" class="w-4 h-4" {{ old('einvoice_sandbox', $location->einvoice_sandbox) ? 'checked' : '' }}>
                <label for="einvoice_sandbox" class="text-xs text-amber-800">
                    Dùng môi trường <strong>thử nghiệm (Sandbox)</strong> — bỏ tick khi đã sẵn sàng xuất hoá đơn thật.
                </label>
            </div>

            <div class="space-y-3">
                <x-input name="einvoice_client_id" label="Client ID" icon="fa-id-badge" value="{{ old('einvoice_client_id', $location->einvoice_client_id) }}" autocomplete="off" />
                <x-input type="password" name="einvoice_client_secret" label="Client Secret" icon="fa-key"
                         placeholder="{{ $location->einvoice_client_secret ? 'Đã lưu — để trống nếu không đổi' : '' }}" autocomplete="off" />
                <x-input name="einvoice_provider_account_id" label="Provider Account ID" icon="fa-building" value="{{ old('einvoice_provider_account_id', $location->einvoice_provider_account_id) }}" autocomplete="off" />
                <div class="grid grid-cols-2 gap-3">
                    <x-input name="einvoice_template_code" label="Mã mẫu hoá đơn" icon="fa-file-invoice" value="{{ old('einvoice_template_code', $location->einvoice_template_code) }}" autocomplete="off" placeholder="VD: 2" />
                    <x-input name="einvoice_invoice_series" label="Ký hiệu hoá đơn" icon="fa-hashtag" value="{{ old('einvoice_invoice_series', $location->einvoice_invoice_series) }}" autocomplete="off" placeholder="VD: C26TSE" />
                </div>
            </div>
            <p class="text-xs text-neutral-400">4 thông tin trên lấy từ tài khoản SePay eInvoice của bạn (mục API/Tích hợp). Lưu lại là hệ thống tự kiểm tra kết nối ngay.</p>
            <p class="text-xs text-amber-600">Lưu ý: Ký hiệu hoá đơn phải khớp năm hiện tại (VD năm {{ now()->format('Y') }} dùng ký hiệu bắt đầu bằng "C{{ now()->format('y') }}") — sai năm sẽ bị SePay từ chối.</p>

            <x-button variant="ghost" icon="fa-check">Lưu hoá đơn điện tử</x-button>
        </form>
    </div>

    {{-- Xuất dữ liệu --}}
    <div class="bg-white rounded-2xl p-5 border border-neutral-200 shadow-sm space-y-3">
        <p class="text-sm font-semibold text-neutral-700 flex items-center gap-2">
            <i class="fa-solid fa-download text-neutral-400"></i>Xuất dữ liệu
        </p>
        <p class="text-xs text-neutral-500 leading-relaxed">
            Tải xuống toàn bộ dữ liệu của "{{ $location->name }}" — thực đơn, kho nguyên liệu, khách hàng, ca làm việc, sổ quỹ và lịch sử đơn hàng — dưới dạng 1 file ZIP gồm nhiều CSV (mở được bằng Excel/Google Sheets). Dùng để sao lưu định kỳ hoặc mang dữ liệu đi nơi khác.
        </p>
        <a href="{{ route('owner.settings.data-export') }}"
           class="flex items-center justify-center gap-2 rounded-xl border border-neutral-300 bg-white text-neutral-700 hover:bg-neutral-50 py-3 px-4 text-sm font-semibold transition">
            <i class="fa-solid fa-file-zipper"></i>Tải xuống toàn bộ dữ liệu (.zip)
        </a>
    </div>
</div>
@endsection
