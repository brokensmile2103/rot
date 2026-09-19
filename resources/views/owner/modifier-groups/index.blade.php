@extends('layouts.app')
@section('title', 'Tuỳ chọn món · Rót')
@section('page-title', 'Tuỳ chọn món')
@section('content')
<div x-data="{ recipeOpen: null }" class="p-4 pb-24 md:p-8 max-w-3xl mx-auto">
    <div class="flex items-center gap-3 mb-2">
        <div class="w-10 h-10 rounded-xl bg-[var(--accent-light)] text-[var(--accent-text)] flex items-center justify-center shrink-0">
            <i class="fa-solid fa-sliders"></i>
        </div>
        <h2 class="text-lg font-bold text-neutral-900">Tuỳ chọn món — {{ $location->name }}</h2>
    </div>
    <p class="text-xs text-neutral-500 mb-5 leading-relaxed">
        Tạo các nhóm như "Lượng đường", "Topping", "Đá" ở đây <strong>1 lần duy nhất</strong>, dùng lại được cho nhiều món khác nhau —
        sang trang <a href="{{ route('owner.menu.index') }}" class="text-[var(--accent-text)] underline">Thực đơn</a> để chọn món nào áp dụng nhóm nào.
    </p>

    <details class="bg-white rounded-2xl p-4 border border-neutral-200 shadow-sm mb-5">
        <summary class="text-[var(--accent-text)] font-semibold cursor-pointer flex items-center gap-2">
            <i class="fa-solid fa-plus"></i>Tạo nhóm tuỳ chọn mới
        </summary>
        <form method="POST" action="{{ route('owner.modifier-groups.store') }}" class="mt-4 space-y-3">
            @csrf
            <x-input name="name" label="Tên nhóm" icon="fa-tag" placeholder="VD: Lượng đường, Topping, Đá" required autocomplete="off" id="new-group-name" />
            <div class="flex gap-4 text-sm text-neutral-600">
                <label class="flex items-center gap-2">
                    <input type="checkbox" name="is_required" value="1" class="rounded border-neutral-300 text-[var(--accent-text)] focus:ring-[var(--accent-ring)]">
                    Bắt buộc chọn
                </label>
                <label class="flex items-center gap-2">
                    <input type="checkbox" name="allow_multiple" value="1" class="rounded border-neutral-300 text-[var(--accent-text)] focus:ring-[var(--accent-ring)]">
                    Cho chọn nhiều mục cùng lúc
                </label>
            </div>
            <p class="text-xs text-neutral-400">VD: "Lượng đường" nên bắt buộc chọn + chỉ 1 mức. "Topping" không bắt buộc + cho chọn nhiều.</p>
            <x-button icon="fa-plus">Tạo nhóm</x-button>
        </form>
    </details>

    @php $trashedGroups = $groups->filter(fn($g) => $g->trashed()); @endphp
    @if($trashedGroups->isNotEmpty())
        <details class="bg-neutral-50 rounded-2xl p-4 border border-neutral-200 mb-5">
            <summary class="text-neutral-500 font-medium cursor-pointer flex items-center gap-2 text-sm">
                <i class="fa-solid fa-trash-can"></i>Nhóm đã xoá ({{ $trashedGroups->count() }})
            </summary>
            <div class="mt-3 space-y-2">
                @foreach($trashedGroups as $tg)
                    <div class="flex items-center justify-between bg-white rounded-lg px-3 py-2 border border-neutral-200">
                        <span class="text-sm text-neutral-600">{{ $tg->name }}</span>
                        <form method="POST" action="{{ route('owner.modifier-groups.restore', $tg->id) }}">
                            @csrf
                            <button class="text-xs px-3 py-1 rounded-lg bg-emerald-600 hover:bg-emerald-700 text-white font-medium">Khôi phục</button>
                        </form>
                    </div>
                @endforeach
            </div>
        </details>
    @endif

    @forelse($groups->reject(fn($g) => $g->trashed()) as $group)
        <div class="bg-white rounded-2xl p-4 border border-neutral-200 shadow-sm mb-4">
            <div class="flex items-center justify-between mb-1">
                <div class="flex items-center gap-2">
                    <h3 class="text-neutral-900 font-bold">{{ $group->name }}</h3>
                    @if($group->is_required)
                        <span class="text-[10px] font-bold text-[var(--accent-text)] bg-[var(--accent-light)] rounded-full px-2 py-0.5">Bắt buộc</span>
                    @endif
                    @if($group->allow_multiple)
                        <span class="text-[10px] font-bold text-blue-700 bg-blue-100 rounded-full px-2 py-0.5">Chọn nhiều</span>
                    @endif
                </div>
                <form method="POST" action="{{ route('owner.modifier-groups.delete', $group->id) }}" onsubmit="return confirm('Xoá nhóm {{ $group->name }}?')">
                    @csrf @method('DELETE')
                    <button class="text-xs px-2.5 py-1 rounded-lg bg-red-50 text-red-600"><i class="fa-solid fa-trash-can"></i></button>
                </form>
            </div>
            <p class="text-xs text-neutral-400 mb-3">Đang áp dụng cho {{ $group->products->count() }} món</p>

            <div class="space-y-1.5">
                @foreach($group->modifiers->reject(fn($m) => $m->trashed()) as $mod)
                    <div class="bg-neutral-50 rounded-lg px-3 py-2">
                        <div class="flex items-center justify-between text-sm">
                            <span class="text-neutral-700 font-medium flex items-center gap-1.5">
                                {{ $mod->name }}
                                @if($mod->extra_price > 0)
                                    <span class="text-[var(--accent-text)] font-semibold">+{{ money($mod->extra_price) }}đ</span>
                                @endif
                                @if($mod->is_default)
                                    <span class="text-[10px] font-bold text-[var(--accent-text)] bg-[var(--accent-light)] rounded-full px-2 py-0.5">Mặc định</span>
                                @endif
                            </span>
                            <div class="flex items-center gap-2">
                                <form method="POST" action="{{ route('owner.modifier-groups.modifier.toggle-default', $mod->id) }}" title="{{ $mod->is_default ? 'Bỏ mặc định' : 'Đặt làm mặc định' }}">
                                    @csrf
                                    <button class="text-xs {{ $mod->is_default ? 'text-[var(--accent)]' : 'text-neutral-300 hover:text-[var(--accent)]' }}">
                                        <i class="fa-solid fa-star"></i>
                                    </button>
                                </form>
                                <button @click="recipeOpen = recipeOpen === {{ $mod->id }} ? null : {{ $mod->id }}" class="text-xs text-neutral-400 hover:text-[var(--accent-text)]">
                                    <i class="fa-solid fa-flask"></i> {{ $mod->modifierRecipes->count() }}
                                </button>
                                <form method="POST" action="{{ route('owner.modifier-groups.modifier.delete', $mod->id) }}">
                                    @csrf @method('DELETE')
                                    <button class="text-red-400 hover:text-red-600 text-xs"><i class="fa-solid fa-xmark"></i></button>
                                </form>
                            </div>
                        </div>

                        <div x-show="recipeOpen === {{ $mod->id }}" x-cloak class="mt-2 pt-2 border-t border-neutral-200 space-y-1.5">
                            <p class="text-[11px] text-neutral-400">Nguyên liệu bị trừ kho khi khách chọn "{{ $mod->name }}" (không bắt buộc):</p>
                            @foreach($mod->modifierRecipes as $r)
                                <div class="flex items-center justify-between text-xs">
                                    <span class="text-neutral-600">{{ $r->ingredient?->name ?? '(đã xoá)' }} — {{ (float) $r->quantity }} {{ $r->ingredient?->unit }}</span>
                                    <form method="POST" action="{{ route('owner.modifier-groups.modifier.recipe.delete', $r->id) }}">
                                        @csrf @method('DELETE')
                                        <button class="text-red-500 hover:text-red-700"><i class="fa-solid fa-xmark"></i></button>
                                    </form>
                                </div>
                            @endforeach

                            @if($ingredients->isNotEmpty())
                                <form method="POST" action="{{ route('owner.modifier-groups.modifier.recipe.store', $mod->id) }}" class="flex gap-1.5">
                                    @csrf
                                    <select name="ingredient_id" required class="flex-1 rounded-lg border border-neutral-300 bg-white text-neutral-900 py-1.5 px-2 text-xs">
                                        @foreach($ingredients as $ing)
                                            <option value="{{ $ing->id }}">{{ $ing->name }} ({{ $ing->unit }})</option>
                                        @endforeach
                                    </select>
                                    <input type="number" step="0.0001" name="quantity" id="recipe-qty-{{ $mod->id }}" autocomplete="off" placeholder="SL" required class="w-16 rounded-lg border border-neutral-300 py-1.5 px-2 text-xs">
                                    <button class="bg-[var(--accent)] hover:bg-[var(--accent-hover)] text-white rounded-lg px-2.5 text-xs">+</button>
                                </form>
                            @endif
                        </div>
                    </div>
                @endforeach
            </div>

            <form method="POST" action="{{ route('owner.modifier-groups.modifier.store', $group->id) }}" class="flex flex-wrap gap-1.5 mt-2 items-center">
                @csrf
                <input name="name" id="new-modifier-name-{{ $group->id }}" autocomplete="off" placeholder="VD: 50%, Trân châu..." required class="flex-1 min-w-[100px] rounded-lg border border-neutral-300 py-1.5 px-2 text-xs">
                <input type="text" inputmode="numeric" name="extra_price" id="new-modifier-price-{{ $group->id }}" autocomplete="off" placeholder="Phụ thu (đ)" class="w-24 rounded-lg border border-neutral-300 py-1.5 px-2 text-xs money-input">
                <label class="flex items-center gap-1 text-xs text-neutral-500 shrink-0">
                    <input type="checkbox" name="is_default" value="1" class="rounded border-neutral-300 text-[var(--accent-text)] focus:ring-[var(--accent-ring)]">
                    Mặc định
                </label>
                <button class="bg-neutral-900 hover:bg-neutral-800 text-white rounded-lg px-3 py-1.5 text-xs">Thêm</button>
            </form>
        </div>
    @empty
        <div class="text-center py-10 text-neutral-400">
            <i class="fa-solid fa-sliders text-3xl mb-2 block"></i>
            <p class="text-sm">Chưa có nhóm tuỳ chọn nào. Tạo "Lượng đường" hoặc "Topping" ở trên để bắt đầu.</p>
        </div>
    @endforelse
</div>

@if(session('focus_group'))
    <script>
        // Tự focus lại ô "Tên" ngay sau khi thêm — gõ Enter liên tục để thêm
        // nhiều mục nhanh (VD: 0%, 30%, 50%, 70%, 100% cho "Lượng đường") mà
        // không cần bấm chuột lại mỗi lần.
        document.getElementById('new-modifier-name-{{ session('focus_group') }}')?.focus();
    </script>
@endif
@endsection
