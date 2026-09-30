@extends('layouts.app')
@section('title', 'Nhân viên · Rót')
@section('page-title', 'Quản lý nhân viên')
@section('content')
<div x-data="{ resetFor: null, salaryFor: null }" class="p-4 pb-24 md:p-8 max-w-3xl mx-auto">
    <div class="flex items-center gap-3 mb-5 md:mb-6">
        <div class="w-10 h-10 rounded-xl bg-[var(--accent-light)] text-[var(--accent-text)] flex items-center justify-center shrink-0">
            <i class="fa-solid fa-users"></i>
        </div>
        <h2 class="text-lg font-bold text-neutral-900 flex-1">Nhân viên — {{ $location->name }}</h2>
        <a href="{{ route('owner.staff.timesheet') }}" class="text-xs px-3 py-2 rounded-lg bg-white border border-neutral-300 text-neutral-600 hover:bg-neutral-50 font-medium transition shrink-0">
            <i class="fa-solid fa-clock mr-1"></i>Bảng công
        </a>
    </div>

    <details class="bg-white rounded-2xl p-4 border border-neutral-200 shadow-sm mb-5">
        <summary class="text-[var(--accent-text)] font-semibold cursor-pointer flex items-center gap-2">
            <i class="fa-solid fa-user-plus"></i>Thêm nhân viên mới
        </summary>
        <form method="POST" action="{{ route('owner.staff.store') }}" class="mt-4 space-y-3">
            @csrf
            <x-input name="name" label="Tên nhân viên" icon="fa-user" required autocomplete="off" />
            <x-input name="phone" label="Số điện thoại (dùng để đăng nhập)" icon="fa-phone" required inputmode="tel" autocomplete="off" />
            <x-input type="password" name="password" label="Mật khẩu (tối thiểu 8 ký tự)" icon="fa-lock" required autocomplete="new-password" />
            <x-button icon="fa-user-plus">Thêm nhân viên</x-button>
        </form>
    </details>

    <div class="space-y-2">
        @forelse($staff as $s)
            <div class="bg-white rounded-xl px-4 py-3.5 border border-neutral-200 shadow-sm">
                <div class="flex items-center justify-between">
                    <div class="flex items-center gap-3 min-w-0">
                        <div class="w-10 h-10 rounded-full bg-neutral-100 text-neutral-500 flex items-center justify-center shrink-0">
                            <i class="fa-solid fa-user"></i>
                        </div>
                        <div class="min-w-0">
                            <div class="text-neutral-900 text-sm font-semibold truncate">{{ $s->name }}</div>
                            <div class="text-neutral-500 text-xs">{{ $s->phone }}</div>
                            @if($s->salary_type)
                                <div class="text-emerald-600 text-xs font-medium mt-0.5">
                                    <i class="fa-solid fa-sack-dollar mr-1"></i>{{ money($s->salary_amount, 0) }}đ{{ $s->salary_type === 'hourly' ? '/giờ' : '/tháng' }}
                                </div>
                            @endif
                        </div>
                    </div>
                    <span class="text-xs px-2.5 py-1 rounded-full font-medium shrink-0 {{ $s->is_active ? 'bg-emerald-50 text-emerald-700' : 'bg-neutral-100 text-neutral-500' }}">
                        {{ $s->is_active ? 'Đang hoạt động' : 'Đã khoá' }}
                    </span>
                </div>

                <div class="flex items-center gap-2 mt-3 pt-3 border-t border-neutral-100">
                    <form method="POST" action="{{ route('owner.staff.toggle', $s->id) }}">
                        @csrf
                        <button class="text-xs px-3 py-1.5 rounded-lg font-medium transition {{ $s->is_active ? 'bg-neutral-100 text-neutral-600 hover:bg-neutral-200' : 'bg-emerald-600 text-white hover:bg-emerald-700' }}">
                            <i class="fa-solid {{ $s->is_active ? 'fa-lock' : 'fa-lock-open' }} mr-1"></i>
                            {{ $s->is_active ? 'Khoá tài khoản' : 'Kích hoạt lại' }}
                        </button>
                    </form>

                    <button @click="resetFor = resetFor === {{ $s->id }} ? null : {{ $s->id }}"
                            class="text-xs px-3 py-1.5 rounded-lg bg-neutral-100 text-neutral-600 hover:bg-neutral-200 font-medium transition">
                        <i class="fa-solid fa-key mr-1"></i>Đổi mật khẩu
                    </button>

                    <button @click="salaryFor = salaryFor === {{ $s->id }} ? null : {{ $s->id }}"
                            class="text-xs px-3 py-1.5 rounded-lg bg-neutral-100 text-neutral-600 hover:bg-neutral-200 font-medium transition">
                        <i class="fa-solid fa-sack-dollar mr-1"></i>Lương
                    </button>

                    <form method="POST" action="{{ route('owner.staff.remove', $s->id) }}"
                          onsubmit="return confirm('Gỡ {{ $s->name }} khỏi xe này?')">
                        @csrf
                        @method('DELETE')
                        <button class="text-xs px-3 py-1.5 rounded-lg bg-red-50 text-red-600 hover:bg-red-100 font-medium transition">
                            <i class="fa-solid fa-user-minus mr-1"></i>Gỡ khỏi xe
                        </button>
                    </form>
                </div>

                <form x-show="resetFor === {{ $s->id }}" x-cloak method="POST" action="{{ route('owner.staff.reset-password', $s->id) }}"
                      class="flex gap-2 mt-3 pt-3 border-t border-neutral-100">
                    @csrf
                    <input type="password" name="password" id="reset-password-{{ $s->id }}" autocomplete="new-password" required minlength="8" placeholder="Mật khẩu mới (tối thiểu 8 ký tự)"
                           class="flex-1 rounded-xl border border-neutral-300 bg-white text-neutral-900 placeholder-neutral-400 py-2 px-3 text-sm focus:border-[var(--accent-ring)] focus:ring-1 focus:ring-[var(--accent-ring)] focus:outline-none">
                    <button class="bg-[var(--accent)] hover:bg-[var(--accent-hover)] text-white rounded-xl px-4 text-sm font-medium transition">Lưu</button>
                </form>

                <form x-show="salaryFor === {{ $s->id }}" x-cloak
                      method="POST" action="{{ route('owner.staff.salary', $s->id) }}"
                      class="space-y-2.5 mt-3 pt-3 border-t border-neutral-100">
                    @csrf @method('PUT')
                    <div class="flex gap-2">
                        <select name="salary_type" id="salary-type-{{ $s->id }}"
                                class="rounded-xl border border-neutral-300 bg-white text-neutral-900 py-2 px-3 text-sm focus:border-[var(--accent-ring)] focus:ring-1 focus:ring-[var(--accent-ring)] focus:outline-none">
                            <option value="" {{ ! $s->salary_type ? 'selected' : '' }}>Chưa thiết lập</option>
                            <option value="hourly" {{ $s->salary_type === 'hourly' ? 'selected' : '' }}>Theo giờ</option>
                            <option value="monthly" {{ $s->salary_type === 'monthly' ? 'selected' : '' }}>Theo tháng</option>
                        </select>
                        <input type="text" inputmode="numeric" class="money-input flex-1 rounded-xl border border-neutral-300 bg-white text-neutral-900 placeholder-neutral-400 py-2 px-3 text-sm focus:border-[var(--accent-ring)] focus:ring-1 focus:ring-[var(--accent-ring)] focus:outline-none"
                               name="salary_amount" id="salary-amount-{{ $s->id }}"
                               value="{{ old('salary_amount', (float) $s->salary_amount) }}" autocomplete="off" placeholder="Số tiền">
                    </div>
                    <p class="text-xs text-neutral-400 leading-relaxed">
                        Chọn "Theo giờ" thì nhập số tiền/giờ, "Theo tháng" thì nhập số tiền/tháng. Lương theo giờ tính đúng theo thời lượng từng ca đã chốt của nhân viên này. Dùng để tính "Lợi nhuận thực tế" ở trang Báo cáo.
                    </p>
                    <button class="bg-[var(--accent)] hover:bg-[var(--accent-hover)] text-white rounded-xl px-4 py-2 text-sm font-medium transition">Lưu lương</button>
                </form>
            </div>
        @empty
            <div class="text-center py-10 text-neutral-400">
                <i class="fa-solid fa-users text-3xl mb-2 block"></i>
                <p class="text-sm">Chưa có nhân viên nào — chỉ mình bạn quản lý xe này.</p>
            </div>
        @endforelse
    </div>
</div>
@endsection
