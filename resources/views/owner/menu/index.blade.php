@extends('layouts.app')
@section('title', 'Thực đơn · Rót')
@section('page-title', 'Quản lý thực đơn')
@section('content')
<div x-data="{
        editCat: null, editProduct: null, editVariant: null, addVariantFor: null, recipeOpen: null, priceCalcFor: null,
        formatPrice(v) { return new Intl.NumberFormat('vi-VN').format(Math.round(v || 0)); },
        /**
         * Máy tính giá bán theo % giá vốn mục tiêu (cost-plus pricing) —
         * công thức chuẩn ngành F&B: Giá đề xuất = Giá vốn / (% giá vốn mục tiêu).
         * VD: giá vốn 15.000đ, muốn giá vốn chiếm 30% giá bán -> 15.000 / 0.3 = 50.000đ.
         */
        suggestedPrice(cost, pct, rounding) {
            if (!pct || pct <= 0 || pct >= 100) return 0;
            let raw = cost / (pct / 100);
            if (rounding === 'round_1000') {
                raw = Math.round(raw / 1000) * 1000;
            } else if (rounding === 'psychological') {
                // Làm tròn LÊN nghìn gần nhất rồi trừ 100 (VD: 52.900đ) — không
                // làm tròn xuống vì sẽ ăn vào biên lợi nhuận mục tiêu đã đặt.
                raw = Math.ceil(raw / 1000) * 1000 - 100;
            } else {
                raw = Math.round(raw);
            }
            return Math.max(0, raw);
        },
    }" class="p-4 pb-24 md:p-8 max-w-3xl mx-auto">
    <div class="flex items-center gap-3 mb-5 md:mb-6">
        <div class="w-10 h-10 rounded-xl bg-[var(--accent-light)] text-[var(--accent-text)] flex items-center justify-center shrink-0">
            <i class="fa-solid fa-book-open"></i>
        </div>
        <h2 class="text-lg font-bold text-neutral-900">{{ $location->name }}</h2>
    </div>

    <div class="grid md:grid-cols-2 gap-4 mb-6">
        <details class="bg-white rounded-2xl p-4 border border-neutral-200 shadow-sm">
            <summary class="text-[var(--accent-text)] font-semibold cursor-pointer flex items-center gap-2">
                <i class="fa-solid fa-folder-plus"></i>Thêm danh mục
            </summary>
            <form method="POST" action="{{ route('owner.menu.category.store') }}" class="mt-3 flex gap-2">
                @csrf
                <input name="name" id="new-category-name" autocomplete="off" placeholder="VD: Trà, Đá xay..." required
                       class="flex-1 rounded-xl border border-neutral-300 bg-white text-neutral-900 placeholder-neutral-400 py-2.5 px-3 text-sm focus:border-[var(--accent-ring)] focus:ring-1 focus:ring-[var(--accent-ring)] focus:outline-none">
                <button class="bg-[var(--accent)] hover:bg-[var(--accent-hover)] text-white rounded-xl px-4 text-sm font-semibold transition">Thêm</button>
            </form>
        </details>

        <details class="bg-white rounded-2xl p-4 border border-neutral-200 shadow-sm">
            <summary class="text-[var(--accent-text)] font-semibold cursor-pointer flex items-center gap-2">
                <i class="fa-solid fa-mug-hot"></i>Thêm món
            </summary>
            <form method="POST" action="{{ route('owner.menu.product.store') }}" enctype="multipart/form-data" class="mt-3 space-y-2">
                @csrf
                <select name="category_id" required
                        class="w-full rounded-xl border border-neutral-300 bg-white text-neutral-900 py-2.5 px-3 text-sm focus:border-[var(--accent-ring)] focus:ring-1 focus:ring-[var(--accent-ring)] focus:outline-none">
                    @foreach($categories as $cat)
                        <option value="{{ $cat->id }}">{{ $cat->name }}</option>
                    @endforeach
                </select>
                <input name="name" id="new-product-name" autocomplete="off" placeholder="Tên món" required
                       class="w-full rounded-xl border border-neutral-300 bg-white text-neutral-900 placeholder-neutral-400 py-2.5 px-3 text-sm focus:border-[var(--accent-ring)] focus:ring-1 focus:ring-[var(--accent-ring)] focus:outline-none">
                <div class="flex gap-2">
                    <input name="variant_name" id="new-product-variant-name" autocomplete="off" placeholder="VD: Ly, Size M" required
                           class="flex-1 rounded-xl border border-neutral-300 bg-white text-neutral-900 placeholder-neutral-400 py-2.5 px-3 text-sm focus:border-[var(--accent-ring)] focus:ring-1 focus:ring-[var(--accent-ring)] focus:outline-none">
                    <input type="text" inputmode="numeric" name="price" id="new-product-price" autocomplete="off" placeholder="Giá" required
                           class="money-input w-28 rounded-xl border border-neutral-300 bg-white text-neutral-900 placeholder-neutral-400 py-2.5 px-3 text-sm focus:border-[var(--accent-ring)] focus:ring-1 focus:ring-[var(--accent-ring)] focus:outline-none">
                </div>
                <label class="flex items-center gap-2 text-xs text-neutral-500 border border-dashed border-neutral-300 rounded-xl py-2.5 px-3 cursor-pointer hover:border-[var(--accent-ring)]">
                    <i class="fa-solid fa-image"></i>
                    <span>Chọn ảnh món (không bắt buộc, tối đa 4MB)</span>
                    <input type="file" name="image" accept="image/*" class="hidden" onchange="this.parentElement.querySelector('span').textContent = this.files[0]?.name ?? 'Chọn ảnh món (không bắt buộc, tối đa 4MB)'">
                </label>
                <p class="text-xs text-neutral-400">Quên chọn size không sao — thêm size khác sau này ngay trong danh sách bên dưới, không cần xoá làm lại.</p>
                <button class="w-full bg-[var(--accent)] hover:bg-[var(--accent-hover)] text-white rounded-xl py-2.5 text-sm font-semibold transition">Thêm món</button>
            </form>
        </details>
    </div>

    @if($trashedProducts->isNotEmpty())
        <details class="bg-neutral-50 rounded-2xl p-4 border border-neutral-200 mb-5">
            <summary class="text-neutral-500 font-medium cursor-pointer flex items-center gap-2 text-sm">
                <i class="fa-solid fa-trash-can"></i>Món đã xoá ({{ $trashedProducts->count() }}) — bấm để khôi phục
            </summary>
            <div class="mt-3 space-y-2">
                @foreach($trashedProducts as $tp)
                    <div class="flex items-center justify-between bg-white rounded-lg px-3 py-2 border border-neutral-200">
                        <span class="text-sm text-neutral-600">{{ $tp->name }} <span class="text-neutral-400">({{ $tp->category->name }})</span></span>
                        <form method="POST" action="{{ route('owner.menu.product.restore', $tp->id) }}">
                            @csrf
                            <button class="text-xs px-3 py-1 rounded-lg bg-emerald-600 hover:bg-emerald-700 text-white font-medium">Khôi phục</button>
                        </form>
                    </div>
                @endforeach
            </div>
        </details>
    @endif

    @foreach($categories as $cat)
        <div class="mb-6">
            <div class="flex items-center gap-2 mb-2">
                <template x-if="editCat !== {{ $cat->id }}">
                    <div class="flex items-center gap-2">
                        <span class="text-xs font-bold text-neutral-400 uppercase tracking-wider">{{ $cat->name }}</span>
                        <button @click="editCat = {{ $cat->id }}" class="text-neutral-300 hover:text-neutral-500"><i class="fa-solid fa-pen text-xs"></i></button>
                    </div>
                </template>
                <form x-show="editCat === {{ $cat->id }}" x-cloak method="POST" action="{{ route('owner.menu.category.update', $cat->id) }}" class="flex gap-1.5 items-center">
                    @csrf @method('PUT')
                    <input name="name" id="edit-category-name-{{ $cat->id }}" autocomplete="off" value="{{ $cat->name }}" class="rounded-lg border border-neutral-300 py-1 px-2 text-xs">
                    <button class="text-xs px-2 py-1 rounded-lg bg-[var(--accent)] text-white">Lưu</button>
                    <button type="button" @click="editCat = null" class="text-xs text-neutral-400">Huỷ</button>
                </form>
            </div>

            <div class="grid sm:grid-cols-2 gap-2">
                @foreach($cat->products as $p)
                    <div class="bg-white rounded-xl px-4 py-3 border border-neutral-200 shadow-sm">
                        <div class="flex items-center justify-between gap-2">
                            <template x-if="editProduct !== {{ $p->id }}">
                                <div class="flex items-center gap-2 min-w-0">
                                    @if($p->image_url)
                                        <img src="{{ $p->image_url }}" class="w-9 h-9 rounded-lg object-cover shrink-0" alt="">
                                    @else
                                        <div class="w-9 h-9 rounded-lg bg-[var(--accent-light)] text-[var(--accent)] flex items-center justify-center shrink-0"><i class="fa-solid fa-mug-hot text-xs"></i></div>
                                    @endif
                                    <span class="text-neutral-900 text-sm font-semibold truncate">{{ $p->name }}</span>
                                    <button @click="editProduct = {{ $p->id }}" class="text-neutral-300 hover:text-neutral-500 shrink-0"><i class="fa-solid fa-pen text-xs"></i></button>
                                </div>
                            </template>
                            <form x-show="editProduct === {{ $p->id }}" x-cloak method="POST" action="{{ route('owner.menu.product.update', $p->id) }}" enctype="multipart/form-data" class="flex gap-1.5 items-center flex-1">
                                @csrf @method('PUT')
                                <input name="name" id="edit-product-name-{{ $p->id }}" autocomplete="off" value="{{ $p->name }}" class="flex-1 rounded-lg border border-neutral-300 py-1 px-2 text-xs">
                                <label class="text-neutral-400 hover:text-[var(--accent-text)] cursor-pointer shrink-0" title="Đổi ảnh">
                                    <i class="fa-solid fa-camera"></i>
                                    <input type="file" name="image" accept="image/*" class="hidden" onchange="this.form.submit()">
                                </label>
                                <button class="text-xs px-2 py-1 rounded-lg bg-[var(--accent)] text-white shrink-0">Lưu</button>
                            </form>

                            <div class="flex items-center gap-1.5 shrink-0">
                                <form method="POST" action="{{ route('owner.menu.product.toggle', $p->id) }}">
                                    @csrf
                                    <button class="text-xs px-2.5 py-1 rounded-lg font-medium transition {{ $p->is_available ? 'bg-emerald-50 text-emerald-700' : 'bg-neutral-100 text-neutral-500' }}">
                                        <i class="fa-solid {{ $p->is_available ? 'fa-eye' : 'fa-eye-slash' }}"></i>
                                    </button>
                                </form>
                                <form method="POST" action="{{ route('owner.menu.product.delete', $p->id) }}" onsubmit="return confirm('Xoá món {{ $p->name }}? (có thể khôi phục lại sau)')">
                                    @csrf @method('DELETE')
                                    <button class="text-xs px-2.5 py-1 rounded-lg bg-red-50 text-red-600"><i class="fa-solid fa-trash-can"></i></button>
                                </form>
                            </div>
                        </div>

                        {{-- Danh sách size/variant --}}
                        <div class="mt-2.5 space-y-1.5">
                            @foreach($p->variants as $v)
                                @php $estCost = $v->estimatedCost(); @endphp
                                <div class="bg-neutral-50 rounded-lg px-2.5 py-1.5">
                                    <div class="flex items-center justify-between text-xs">
                                        <template x-if="editVariant !== {{ $v->id }}">
                                            <div class="flex items-center gap-1.5">
                                                <span class="text-neutral-700 font-medium">{{ $v->name }}</span>
                                                <span class="text-[var(--accent-text)] font-semibold">{{ money($v->price) }}đ</span>
                                                <button @click="editVariant = {{ $v->id }}" class="text-neutral-300 hover:text-neutral-500"><i class="fa-solid fa-pen"></i></button>
                                            </div>
                                        </template>
                                        <form x-show="editVariant === {{ $v->id }}" x-cloak method="POST" action="{{ route('owner.menu.variant.update', $v->id) }}" class="flex gap-1 items-center">
                                            @csrf @method('PUT')
                                            <input name="name" id="edit-variant-name-{{ $v->id }}" autocomplete="off" value="{{ $v->name }}" class="w-16 rounded border border-neutral-300 py-0.5 px-1.5 text-xs">
                                            <input type="text" inputmode="numeric" name="price" id="edit-variant-price-{{ $v->id }}" autocomplete="off" value="{{ (float) $v->price }}" class="w-20 rounded border border-neutral-300 py-0.5 px-1.5 text-xs money-input">
                                            <button class="px-2 py-0.5 rounded bg-[var(--accent)] text-white text-xs">Lưu</button>
                                        </form>
                                        <div class="flex items-center gap-1.5 shrink-0">
                                            @if($estCost > 0)
                                                <button @click="priceCalcFor = priceCalcFor === {{ $v->id }} ? null : {{ $v->id }}"
                                                        class="{{ 'text-neutral-300 hover:text-emerald-600' }}" title="Máy tính giá bán">
                                                    <i class="fa-solid fa-calculator"></i>
                                                </button>
                                            @endif
                                            <form method="POST" action="{{ route('owner.menu.variant.delete', $v->id) }}">
                                                @csrf @method('DELETE')
                                                <button class="text-red-400 hover:text-red-600"><i class="fa-solid fa-xmark"></i></button>
                                            </form>
                                        </div>
                                    </div>

                                    @if($estCost > 0)
                                        <div x-show="priceCalcFor === {{ $v->id }}" x-cloak
                                             x-data="{ pct: 30, rounding: 'round_1000', cost: {{ $estCost }} }"
                                             class="mt-2 pt-2 border-t border-neutral-200 space-y-1.5">
                                            <div class="flex items-center justify-between text-[11px] text-neutral-500">
                                                <span>Giá vốn size này</span>
                                                <span class="font-semibold text-neutral-700" x-text="formatPrice(cost) + 'đ'"></span>
                                            </div>
                                            <div class="flex items-center gap-1.5">
                                                <span class="text-[11px] text-neutral-500 shrink-0">Giá vốn chiếm</span>
                                                <input type="number" id="price-calc-pct-{{ $v->id }}" name="price_calc_pct_{{ $v->id }}" x-model.number="pct" min="1" max="95" autocomplete="off" class="w-14 rounded border border-neutral-300 py-1 px-1.5 text-xs text-center">
                                                <span class="text-[11px] text-neutral-500">% giá bán</span>
                                            </div>
                                            <select id="price-calc-rounding-{{ $v->id }}" name="price_calc_rounding_{{ $v->id }}" x-model="rounding" class="w-full rounded border border-neutral-300 py-1 px-1.5 text-xs bg-white">
                                                <option value="none">Không làm tròn</option>
                                                <option value="round_1000">Làm tròn nghìn (VD: 50.000đ)</option>
                                                <option value="psychological">Giá tâm lý (VD: 49.900đ)</option>
                                            </select>
                                            <div class="flex items-center justify-between bg-emerald-50 rounded-lg px-2.5 py-1.5">
                                                <span class="text-[11px] text-emerald-700">Giá đề xuất</span>
                                                <span class="font-bold text-emerald-700" x-text="formatPrice(suggestedPrice(cost, pct, rounding)) + 'đ'"></span>
                                            </div>
                                            <button type="button"
                                                    @click="editVariant = {{ $v->id }}; priceCalcFor = null;
                                                             $nextTick(() => {
                                                                 const el = document.getElementById('edit-variant-price-{{ $v->id }}');
                                                                 el.value = suggestedPrice(cost, pct, rounding);
                                                                 el.dispatchEvent(new Event('input', { bubbles: true }));
                                                             })"
                                                    class="w-full text-xs py-1.5 rounded-lg bg-emerald-600 hover:bg-emerald-700 text-white font-medium transition">
                                                Dùng giá này
                                            </button>
                                        </div>
                                    @endif

                                    {{-- Công thức nguyên liệu — RIÊNG cho từng size, vì lượng nguyên liệu
                                         thường khác nhau giữa các size (Size L dùng nhiều cà phê/đường hơn
                                         Size M chẳng hạn) — không dùng chung 1 công thức cho cả món. --}}
                                    <button @click="recipeOpen = recipeOpen === {{ $v->id }} ? null : {{ $v->id }}"
                                            class="text-xs text-neutral-500 font-medium mt-2 pt-2 border-t border-neutral-200 flex items-center gap-1 w-full">
                                        <i class="fa-solid fa-flask"></i>
                                        Công thức ({{ $v->recipes->count() }})
                                        <i class="fa-solid fa-chevron-down text-[10px] transition ml-auto" :class="recipeOpen === {{ $v->id }} && 'rotate-180'"></i>
                                    </button>

                                    <div x-show="recipeOpen === {{ $v->id }}" x-cloak class="mt-2 space-y-1.5">
                                        @foreach($v->recipes as $r)
                                            <div class="flex items-center justify-between text-xs">
                                                <span class="text-neutral-600">{{ $r->ingredient?->name ?? '(nguyên liệu đã xoá)' }} — {{ quantity($r->quantity) }} {{ $r->ingredient?->unit }}</span>
                                                <form method="POST" action="{{ route('owner.menu.recipe.delete', $r->id) }}">
                                                    @csrf @method('DELETE')
                                                    <button class="text-red-500 hover:text-red-700"><i class="fa-solid fa-xmark"></i></button>
                                                </form>
                                            </div>
                                        @endforeach

                                        @if($ingredients->isNotEmpty())
                                            <form method="POST" action="{{ route('owner.menu.recipe.store', $v->id) }}" class="flex gap-1.5 pt-1.5">
                                                @csrf
                                                <select name="ingredient_id" required class="flex-1 rounded-lg border border-neutral-300 bg-white text-neutral-900 py-1.5 px-2 text-xs">
                                                    @foreach($ingredients as $ing)
                                                        <option value="{{ $ing->id }}">{{ $ing->name }} ({{ $ing->unit }})</option>
                                                    @endforeach
                                                </select>
                                                <input type="number" step="0.0001" name="quantity" id="new-recipe-qty-{{ $v->id }}" autocomplete="off" placeholder="SL" required
                                                       class="w-16 rounded-lg border border-neutral-300 bg-white text-neutral-900 py-1.5 px-2 text-xs">
                                                <button class="bg-[var(--accent)] hover:bg-[var(--accent-hover)] text-white rounded-lg px-2.5 text-xs">+</button>
                                            </form>
                                        @else
                                            <p class="text-xs text-neutral-400">Chưa có nguyên liệu nào — thêm ở trang Kho trước.</p>
                                        @endif
                                    </div>
                                </div>
                            @endforeach

                            <button @click="addVariantFor = addVariantFor === {{ $p->id }} ? null : {{ $p->id }}"
                                    class="text-xs text-[var(--accent-text)] font-medium flex items-center gap-1">
                                <i class="fa-solid fa-plus"></i>Thêm size khác
                            </button>
                            <form x-show="addVariantFor === {{ $p->id }}" x-cloak method="POST" action="{{ route('owner.menu.variant.store', $p->id) }}" class="flex gap-1.5">
                                @csrf
                                <input name="name" id="new-variant-name-{{ $p->id }}" autocomplete="off" placeholder="VD: Size L" required class="flex-1 rounded-lg border border-neutral-300 py-1.5 px-2 text-xs">
                                <input type="text" inputmode="numeric" name="price" id="new-variant-price-{{ $p->id }}" autocomplete="off" placeholder="Giá" required class="w-20 rounded-lg border border-neutral-300 py-1.5 px-2 text-xs money-input">
                                <button class="px-3 rounded-lg bg-[var(--accent)] text-white text-xs">Thêm</button>
                            </form>
                        </div>

                        @php $conflicts = $p->ingredientConflicts(); @endphp
                        @if(!empty($conflicts))
                            <div class="mt-2.5 pt-2.5 border-t border-neutral-100">
                                <div class="bg-amber-50 border border-amber-200 rounded-lg px-3 py-2">
                                    <p class="text-xs text-amber-800 font-semibold flex items-center gap-1.5">
                                        <i class="fa-solid fa-triangle-exclamation"></i>Trừ kho có thể bị nhân đôi
                                    </p>
                                    <p class="text-xs text-amber-700 mt-1 leading-relaxed">
                                        @foreach($conflicts as $c)
                                            "{{ $c['ingredient'] }}" đang có trong CẢ công thức gốc (size {{ $c['variant'] }}) LẪN tuỳ chọn gắn cho món này @if(!$loop->last), @endif
                                        @endforeach
                                        — mỗi lần bán sẽ trừ kho ở CẢ 2 nơi cộng lại. Nếu tuỳ chọn đã đại diện đủ lượng nguyên liệu đó, hãy xoá nó khỏi công thức gốc bên trên.
                                    </p>
                                </div>
                            </div>
                        @endif

                        {{-- Nhóm tuỳ chọn áp dụng cho món (Lượng đường, Topping...) --}}
                        @if($allModifierGroups->isNotEmpty())
                            <div class="mt-2.5 pt-2.5 border-t border-neutral-100">
                                <div class="text-[10px] font-bold text-neutral-400 uppercase tracking-wider mb-1.5">Tuỳ chọn áp dụng</div>
                                <div class="flex flex-wrap gap-1.5">
                                    @php $attachedIds = $p->modifierGroups->pluck('id'); @endphp
                                    @foreach($allModifierGroups as $mg)
                                        @if($attachedIds->contains($mg->id))
                                            <form method="POST" action="{{ route('owner.menu.modifier-group.detach', [$p->id, $mg->id]) }}">
                                                @csrf @method('DELETE')
                                                <button class="text-xs px-2.5 py-1 rounded-full bg-[var(--accent)] text-white font-medium">
                                                    {{ $mg->name }} <i class="fa-solid fa-xmark ml-0.5"></i>
                                                </button>
                                            </form>
                                        @else
                                            <form method="POST" action="{{ route('owner.menu.modifier-group.attach', $p->id) }}">
                                                @csrf
                                                <input type="hidden" name="modifier_group_id" value="{{ $mg->id }}">
                                                <button class="text-xs px-2.5 py-1 rounded-full bg-neutral-100 text-neutral-500 hover:bg-neutral-200 font-medium">
                                                    + {{ $mg->name }}
                                                </button>
                                            </form>
                                        @endif
                                    @endforeach
                                </div>
                            </div>
                        @endif
                    </div>
                @endforeach
            </div>
        </div>
    @endforeach
</div>
@endsection
