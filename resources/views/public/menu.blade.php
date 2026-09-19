<!DOCTYPE html>
<html lang="vi" class="h-full" data-accent="{{ $location->accent_color ?? 'amber' }}">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1, viewport-fit=cover">
    <title>Thực đơn · {{ $location->name }}</title>
    <meta name="robots" content="noindex, nofollow">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <link rel="icon" type="image/svg+xml" href="/favicon.svg">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="h-full bg-neutral-50 text-neutral-900 antialiased overscroll-none">

<div x-data="publicMenuApp(
        @js($categories),
        '{{ route('public.menu.store', $location->public_token) }}',
        @js($loyaltyEnabled ?? false)
    )" class="min-h-full flex flex-col pb-28">

    {{-- Header — CỐ TÌNH để full-width (không bọc trong khung hẹp) — 1 thanh
         nav co cụm lại giữa màn hình rộng trên PC trông thiếu chuyên nghiệp,
         nội dung bên trong mới cần giới hạn độ rộng để dễ đọc. --}}
    <header class="bg-white border-b border-neutral-200 sticky top-0 z-20">
        <div class="max-w-3xl mx-auto px-4 py-4 flex items-center gap-2.5">
            <div class="w-9 h-9 rounded-xl bg-[var(--accent)] flex items-center justify-center text-white shrink-0">
                <i class="fa-solid fa-mug-hot"></i>
            </div>
            <div class="min-w-0">
                <div class="text-base font-bold leading-tight text-neutral-900 truncate">{{ $location->name }}</div>
                <div class="text-xs text-neutral-500">Xem thực đơn &amp; gửi yêu cầu gọi món</div>
            </div>
        </div>
    </header>

    {{-- Trạng thái đã gửi thành công — thay hẳn nội dung trang bằng màn cảm ơn --}}
    <template x-if="submitted">
        <div class="flex-1 flex flex-col items-center justify-center text-center px-6 py-16 max-w-3xl mx-auto w-full">
            <div class="w-16 h-16 rounded-2xl bg-emerald-100 text-emerald-600 flex items-center justify-center text-2xl mb-4">
                <i class="fa-solid fa-circle-check"></i>
            </div>
            <h2 class="text-lg font-bold text-neutral-900 mb-1">Đã gửi yêu cầu!</h2>
            <p class="text-sm text-neutral-500 max-w-xs">Nhân viên sẽ xác nhận trong ít phút. Vui lòng đợi tại quầy hoặc chờ nhân viên gọi tên nhé.</p>
            <button @click="reset()" class="mt-6 text-sm font-semibold text-[var(--accent-text)] underline">Gửi thêm yêu cầu khác</button>
        </div>
    </template>

    <template x-if="!submitted">
        <div class="flex-1 flex flex-col min-h-0">
            @if($categories->isEmpty())
                <div class="text-center py-20 px-6 text-neutral-400 text-sm">
                    <i class="fa-solid fa-mug-saucer text-3xl mb-3"></i>
                    <p>Quán chưa có món nào để hiện ở đây.</p>
                </div>
            @else
                {{-- Danh mục --}}
                <div class="flex gap-2 overflow-x-auto py-3 shrink-0 bg-neutral-50 sticky top-[65px] z-10">
                    <div class="max-w-3xl w-full mx-auto flex gap-2 px-4">
                        <template x-for="cat in categories" :key="cat.id">
                            <button @click="activeCategory = cat.id"
                                    :class="activeCategory === cat.id ? 'bg-[var(--accent)] text-white shadow-md shadow-[var(--accent)]/20' : 'bg-white text-neutral-700 border border-neutral-200'"
                                    class="px-4 py-2 rounded-full text-sm font-medium whitespace-nowrap shrink-0 transition">
                                <span x-text="cat.name"></span>
                            </button>
                        </template>
                    </div>
                </div>

                {{-- Món --}}
                <div class="px-4 pt-3 max-w-3xl w-full mx-auto grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 gap-3">
                    <template x-for="cat in categories" :key="cat.id">
                        <template x-if="activeCategory === cat.id">
                            <template x-for="product in cat.products" :key="product.id">
                                <button @click="openProduct(product)" type="button"
                                        class="bg-white rounded-2xl p-3 text-left border border-neutral-200 active:scale-95 lg:hover:-translate-y-0.5 lg:hover:shadow-md transition-all">
                                    <div class="w-full aspect-square rounded-xl bg-neutral-100 flex items-center justify-center overflow-hidden mb-2">
                                        <template x-if="product.image_url">
                                            <img :src="product.image_url" class="w-full h-full object-cover" alt="">
                                        </template>
                                        <template x-if="!product.image_url">
                                            <i class="fa-solid fa-mug-saucer text-2xl text-neutral-300"></i>
                                        </template>
                                    </div>
                                    <div class="text-sm font-semibold text-neutral-900 truncate" x-text="product.name"></div>
                                    <div class="text-xs text-[var(--accent-text)] font-bold mt-0.5" x-text="formatMoney(cheapestPrice(product)) + 'đ'"></div>
                                </button>
                            </template>
                        </template>
                    </template>
                </div>
            @endif
        </div>
    </template>

    {{-- Nút giỏ hàng nổi --}}
    <template x-if="!submitted && cartCount > 0">
        <button @click="showCart = true" type="button"
                class="fixed bottom-4 left-4 right-4 max-w-lg mx-auto z-30 bg-[var(--accent)] text-white rounded-2xl py-4 px-5 shadow-lg shadow-[var(--accent)]/30 flex items-center justify-between font-semibold">
            <span><i class="fa-solid fa-cart-shopping mr-2"></i><span x-text="cartCount"></span> món</span>
            <span x-text="formatMoney(cartTotal) + 'đ'"></span>
        </button>
    </template>

    {{-- Modal chọn size/tuỳ chọn --}}
    <div x-show="selectedProduct" x-cloak x-transition.opacity class="fixed inset-0 z-40 flex items-end sm:items-center justify-center bg-black/40" @click.self="selectedProduct = null">
        <div class="bg-white w-full sm:max-w-sm sm:rounded-2xl rounded-t-2xl max-h-[85vh] overflow-y-auto scrollbar-hide p-5" x-cloak x-show="selectedProduct" x-transition:enter="transition ease-out duration-200" x-transition:enter-start="translate-y-full sm:translate-y-0 sm:scale-95" x-transition:enter-end="translate-y-0 sm:scale-100">
            <template x-if="selectedProduct">
                <div>
                    <h3 class="text-base font-bold text-neutral-900 mb-3" x-text="selectedProduct.name"></h3>

                    <template x-if="selectedProduct.variants.length > 1">
                        <div class="mb-4">
                            <p class="text-xs font-semibold text-neutral-500 mb-2">Chọn size</p>
                            <div class="flex flex-wrap gap-2">
                                <template x-for="variant in selectedProduct.variants" :key="variant.id">
                                    <button @click="selectedVariant = variant" type="button"
                                            :class="selectedVariant?.id === variant.id ? 'bg-[var(--accent)] text-white border-[var(--accent)]' : 'bg-white text-neutral-700 border-neutral-200'"
                                            class="px-3 py-2 rounded-xl border text-sm font-medium transition">
                                        <span x-text="variant.name"></span> — <span x-text="formatMoney(variant.price) + 'đ'"></span>
                                    </button>
                                </template>
                            </div>
                        </div>
                    </template>

                    <template x-for="group in selectedProduct.modifier_groups" :key="group.id">
                        <div class="mb-4">
                            <p class="text-xs font-semibold text-neutral-500 mb-2">
                                <span x-text="group.name"></span>
                                <span x-show="group.is_required" class="text-red-500">*</span>
                            </p>
                            <div class="flex flex-wrap gap-2">
                                <template x-for="modifier in group.modifiers" :key="modifier.id">
                                    <button @click="toggleModifier(group, modifier)" type="button"
                                            :class="isModifierSelected(group, modifier) ? 'bg-[var(--accent)] text-white border-[var(--accent)]' : 'bg-white text-neutral-700 border-neutral-200'"
                                            class="px-3 py-2 rounded-xl border text-sm font-medium transition">
                                        <span x-text="modifier.name"></span>
                                        <template x-if="parseFloat(modifier.extra_price) > 0">
                                            <span x-text="' +' + formatMoney(modifier.extra_price)"></span>
                                        </template>
                                    </button>
                                </template>
                            </div>
                        </div>
                    </template>

                    <div class="flex items-center justify-between gap-4 mb-4">
                        <p class="text-xs font-semibold text-neutral-500">Số lượng</p>
                        <div class="flex items-center gap-3">
                            <button @click="quantity = Math.max(1, quantity - 1)" type="button" class="w-9 h-9 rounded-lg bg-neutral-100 text-neutral-700 font-bold">−</button>
                            <span class="w-6 text-center font-semibold" x-text="quantity"></span>
                            <button @click="quantity++" type="button" class="w-9 h-9 rounded-lg bg-neutral-100 text-neutral-700 font-bold">+</button>
                        </div>
                    </div>

                    <button @click="addToCart()" type="button" class="w-full py-3.5 rounded-xl bg-[var(--accent)] text-white font-semibold">
                        Thêm vào giỏ — <span x-text="formatMoney(currentLinePrice() * quantity) + 'đ'"></span>
                    </button>
                </div>
            </template>
        </div>
    </div>

    {{-- Modal giỏ hàng + gửi yêu cầu --}}
    <div x-show="showCart" x-cloak x-transition.opacity class="fixed inset-0 z-40 flex items-end sm:items-center justify-center bg-black/40" @click.self="showCart = false">
        <div class="bg-white w-full sm:max-w-sm sm:rounded-2xl rounded-t-2xl max-h-[85vh] overflow-y-auto scrollbar-hide p-5">
            <div class="flex items-center justify-between mb-4">
                <h3 class="text-base font-bold text-neutral-900">Giỏ hàng</h3>
                <button @click="showCart = false" type="button" class="text-neutral-400"><i class="fa-solid fa-xmark text-lg"></i></button>
            </div>

            <div class="space-y-3 mb-4">
                <template x-for="(line, index) in cart" :key="index">
                    <div class="flex items-center justify-between gap-2 border-b border-neutral-100 pb-3">
                        <div class="min-w-0">
                            <div class="text-sm font-semibold text-neutral-900 truncate" x-text="line.label"></div>
                            <div class="text-xs text-neutral-500" x-text="formatMoney(line.unit_price) + 'đ'"></div>
                        </div>
                        <div class="flex items-center gap-2 shrink-0">
                            <button @click="line.quantity > 1 ? line.quantity-- : cart.splice(index, 1)" type="button" class="w-7 h-7 rounded-lg bg-neutral-100 text-neutral-700 font-bold text-sm">−</button>
                            <span class="w-5 text-center text-sm font-semibold" x-text="line.quantity"></span>
                            <button @click="line.quantity++" type="button" class="w-7 h-7 rounded-lg bg-neutral-100 text-neutral-700 font-bold text-sm">+</button>
                        </div>
                    </div>
                </template>
                <template x-if="cart.length === 0">
                    <p class="text-sm text-neutral-400 text-center py-6">Giỏ hàng trống — bấm vào món để thêm nhé.</p>
                </template>
            </div>

            <template x-if="cart.length > 0">
                <div>
                    <div class="flex items-center justify-between text-sm font-bold text-neutral-900 mb-4">
                        <span>Tạm tính</span><span x-text="formatMoney(cartTotal) + 'đ'"></span>
                    </div>

                    <div class="space-y-3 mb-4">
                        <input x-model="customerName" type="text" maxlength="100" placeholder="Tên của bạn (để nhân viên gọi, không bắt buộc)"
                               class="w-full text-sm border border-neutral-200 rounded-xl px-3.5 py-2.5">
                        <template x-if="loyaltyEnabled">
                            <div>
                                <input x-model="customerPhone" type="tel" inputmode="numeric" maxlength="20" placeholder="Số điện thoại (để tự tích điểm)"
                                       class="w-full text-sm border border-neutral-200 rounded-xl px-3.5 py-2.5">
                            </div>
                        </template>
                        <input x-model="note" type="text" maxlength="150" placeholder="Ghi chú thêm (VD: ít đá, không đường...)"
                               class="w-full text-sm border border-neutral-200 rounded-xl px-3.5 py-2.5">
                    </div>

                    <p x-show="errorMessage" x-cloak class="text-xs text-red-600 mb-3" x-text="errorMessage"></p>

                    <button @click="submit()" :disabled="submitting" type="button"
                            class="w-full py-3.5 rounded-xl bg-[var(--accent)] text-white font-semibold disabled:opacity-50">
                        <span x-show="!submitting">Gửi yêu cầu gọi món</span>
                        <span x-show="submitting">Đang gửi...</span>
                    </button>
                </div>
            </template>
        </div>
    </div>
</div>

<script>
function publicMenuApp(categoriesData, submitUrl, loyaltyEnabled = false) {
    return {
        categories: categoriesData,
        activeCategory: categoriesData[0]?.id ?? null,
        loyaltyEnabled: loyaltyEnabled,
        selectedProduct: null,
        selectedVariant: null,
        selectedModifiers: [],
        quantity: 1,
        cart: [],
        showCart: false,
        customerName: '',
        customerPhone: '',
        note: '',
        submitting: false,
        submitted: false,
        errorMessage: null,

        formatMoney(value) {
            return Number(value ?? 0).toLocaleString('vi-VN');
        },

        cheapestPrice(product) {
            return Math.min(...product.variants.map(v => parseFloat(v.price)));
        },

        openProduct(product) {
            this.selectedProduct = product;
            this.selectedVariant = product.variants.find(v => v.is_default) ?? product.variants[0] ?? null;
            this.selectedModifiers = product.modifier_groups.flatMap(g => g.modifiers.filter(m => m.is_default));
            this.quantity = 1;
        },

        isModifierSelected(group, modifier) {
            return this.selectedModifiers.some(m => m.id === modifier.id);
        },

        toggleModifier(group, modifier) {
            if (group.allow_multiple) {
                if (this.isModifierSelected(group, modifier)) {
                    this.selectedModifiers = this.selectedModifiers.filter(m => m.id !== modifier.id);
                } else {
                    this.selectedModifiers.push(modifier);
                }
                return;
            }
            // Nhóm chỉ chọn 1 — bỏ hết lựa chọn cũ CÙNG NHÓM trước khi chọn mới.
            const groupModifierIds = group.modifiers.map(m => m.id);
            this.selectedModifiers = this.selectedModifiers.filter(m => !groupModifierIds.includes(m.id));
            this.selectedModifiers.push(modifier);
        },

        currentLinePrice() {
            const base = parseFloat(this.selectedVariant?.price ?? 0);
            const extra = this.selectedModifiers.reduce((sum, m) => sum + parseFloat(m.extra_price), 0);
            return base + extra;
        },

        addToCart() {
            if (!this.selectedVariant) return;
            const modifierIds = this.selectedModifiers.map(m => m.id).sort((a, b) => a - b);
            const label = this.selectedProduct.name + ' (' + this.selectedVariant.name + ')'
                + (this.selectedModifiers.length ? ' — ' + this.selectedModifiers.map(m => m.name).join(', ') : '');

            // Gộp vào dòng đã có nếu TRÙNG variant + đúng bộ tuỳ chọn — tránh
            // giỏ hàng bị chẻ vụn thành nhiều dòng giống hệt nhau khi khách
            // bấm thêm cùng 1 món/size/tuỳ chọn nhiều lần.
            const existing = this.cart.find(line =>
                line.variant_id === this.selectedVariant.id
                && JSON.stringify(line.modifier_ids) === JSON.stringify(modifierIds)
            );

            if (existing) {
                existing.quantity += this.quantity;
            } else {
                this.cart.push({
                    variant_id: this.selectedVariant.id,
                    modifier_ids: modifierIds,
                    quantity: this.quantity,
                    unit_price: this.currentLinePrice(),
                    label,
                });
            }

            this.selectedProduct = null;
        },

        get cartCount() {
            return this.cart.reduce((sum, l) => sum + l.quantity, 0);
        },
        get cartTotal() {
            return this.cart.reduce((sum, l) => sum + l.unit_price * l.quantity, 0);
        },

        submit() {
            if (this.cart.length === 0 || this.submitting) return;
            this.submitting = true;
            this.errorMessage = null;

            fetch(submitUrl, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                    'Accept': 'application/json',
                },
                body: JSON.stringify({
                    items: this.cart.map(l => ({ variant_id: l.variant_id, quantity: l.quantity, modifier_ids: l.modifier_ids })),
                    customer_name: this.customerName || null,
                    customer_phone: this.loyaltyEnabled && this.customerPhone ? this.customerPhone : null,
                    note: this.note || null,
                }),
            })
                .then(async (res) => {
                    const data = await res.json();
                    if (!res.ok) throw new Error(data.message || 'Có lỗi xảy ra, thử lại nhé.');
                    this.submitted = true;
                    this.showCart = false;
                })
                .catch((err) => { this.errorMessage = err.message; })
                .finally(() => { this.submitting = false; });
        },

        reset() {
            this.cart = [];
            this.customerName = '';
            this.customerPhone = '';
            this.note = '';
            this.submitted = false;
        },
    };
}
</script>
</body>
</html>
