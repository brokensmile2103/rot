@extends('layouts.app')
@section('title', 'Order · Rót')
@section('page-title', 'Bán hàng')
@section('content')
<div x-data="posApp(@js($categories), @js($initialCart ?? []), @js($editingOrder?->id), @js($location->receipt_enabled), @js($location->hasBankAccount() ? ['bin' => $location->bank_bin, 'accountNo' => $location->bank_account_no, 'accountName' => $location->bank_account_name] : null), @js($editingOrder?->isDraft() ?? false), @js($location->loyalty_enabled), @js((float) $location->points_redeem_value), @js($editingOrder?->customer), @js(($acceptedRequest ?? null)?->customer_phone))" class="h-full flex flex-col">

    @isset($editingOrder)
        <div class="shrink-0 bg-neutral-900 text-white text-sm text-center py-2 flex items-center justify-center gap-3">
            <i class="fa-solid fa-pen"></i>
            <span>Đang sửa đơn #{{ $editingOrder->id }}</span>
            <a href="{{ route('pos.orders.index') }}" class="underline text-neutral-300 hover:text-white">Huỷ sửa, quay lại</a>
        </div>
    @endisset

    @isset($acceptedRequest)
        <div x-show="acceptedBannerVisible" x-cloak class="shrink-0 bg-blue-600 text-white text-sm text-center py-2 px-3 flex items-center justify-center gap-2 flex-wrap">
            <i class="fa-solid fa-qrcode"></i>
            <span>
                Đơn từ khách qua QR{{ $acceptedRequest->customer_name ? ' — '.$acceptedRequest->customer_name : '' }}
                @if($acceptedRequest->note)<span class="text-blue-100">({{ $acceptedRequest->note }})</span>@endif
                — kiểm tra lại trước khi tính tiền.
            </span>
            @if(($skippedRequestItems ?? 0) > 0)
                <span class="bg-blue-800 rounded-full px-2 py-0.5 text-xs">{{ $skippedRequestItems }} món đã bỏ qua vì không còn bán</span>
            @endif
        </div>
    @endisset

    {{-- Toast xác nhận đơn hàng thành công — dùng chung cho cả "Lên đơn nhanh" và thanh toán bình thường. --}}
    <div x-show="toastMessage" x-cloak x-transition
         class="fixed top-4 left-1/2 -translate-x-1/2 z-50 bg-neutral-900 text-white text-sm font-medium px-4 py-2.5 rounded-xl shadow-lg flex items-center gap-2">
        <i class="fa-solid fa-circle-check text-emerald-400"></i>
        <span x-text="toastMessage"></span>
    </div>

    <div class="flex-1 lg:flex min-h-0">

    {{-- Khu vực chọn món --}}
    <div class="flex-1 flex flex-col min-w-0 min-h-0 @container">
        {{-- Số yêu cầu QR đang chờ lấy từ Alpine.store('nav') (khởi tạo bởi layout, cập nhật
             bằng polling — resources/js/qr-requests.js) nên banner tự hiện/ẩn/đổi số
             ngay khi có khách gửi yêu cầu mà KHÔNG cần tải lại trang (tải lại sẽ làm
             mất giỏ hàng nhân viên đang nhập dở). --}}
        <a x-show="$store.nav.pendingRequestCount > 0" x-cloak href="{{ route('pos.orders.index') }}"
           class="shrink-0 bg-blue-50 border-b border-blue-200 text-blue-800 text-xs font-semibold text-center py-2 flex items-center justify-center gap-1.5">
            <i class="fa-solid fa-qrcode"></i><span><span x-text="$store.nav.pendingRequestCount"></span> yêu cầu mới từ khách (QR) — bấm để xem</span>
        </a>
        @if(($draftCount ?? 0) > 0)
            <a href="{{ route('pos.orders.index') }}" class="shrink-0 bg-amber-50 border-b border-amber-200 text-amber-800 text-xs font-semibold text-center py-2 flex items-center justify-center gap-1.5">
                <i class="fa-solid fa-clock"></i>{{ $draftCount }} đơn đang chờ — bấm để tiếp tục
            </a>
        @endif
        <div class="flex gap-2 overflow-x-auto px-3 md:px-6 py-3 shrink-0 bg-neutral-50 sticky top-0 z-10">
            <template x-for="cat in categories" :key="cat.id">
                <button @click="activeCategory = cat.id"
                        :class="activeCategory === cat.id ? 'bg-[var(--accent)] text-white shadow-md shadow-[var(--accent)]/20' : 'bg-white text-neutral-700 border border-neutral-200 hover:bg-neutral-100'"
                        class="px-4 py-2 rounded-full text-sm font-medium whitespace-nowrap shrink-0 transition">
                    <span x-text="cat.name"></span>
                </button>
            </template>
        </div>

        <div class="flex-1 overflow-y-auto scrollbar-hide px-3 md:px-6 pb-32 lg:pb-8">
            @if($showQuickSetupBanner ?? false)
                <div class="text-center py-16 px-4">
                    <div class="w-14 h-14 rounded-2xl bg-[var(--accent-light)] text-[var(--accent-text)] flex items-center justify-center mx-auto mb-4 text-xl">
                        <i class="fa-solid fa-mug-hot"></i>
                    </div>
                    <p class="text-sm font-semibold text-neutral-700 mb-1">Thực đơn của bạn chưa được thiết lập</p>
                    <p class="text-xs text-neutral-400 mb-4 max-w-xs mx-auto leading-relaxed">Thêm/sửa món thủ công ở trang Thực đơn, hoặc dùng "Thiết lập nhanh" để có ngay 1 bộ thực đơn mẫu hoàn chỉnh (2 món, đủ nguyên liệu, đúng công thức) — sẵn sàng bán ngay.</p>
                    @if(auth()->user()?->isOwner())
                        <a href="{{ route('owner.settings.index') }}" class="inline-flex items-center gap-2 rounded-xl bg-amber-600 hover:bg-amber-700 text-white py-2.5 px-4 text-sm font-semibold transition">
                            <i class="fa-solid fa-wand-magic-sparkles"></i>Thiết lập nhanh (dữ liệu mẫu)
                        </a>
                    @endif
                </div>
            @endif
            <div class="grid grid-cols-2 @sm:grid-cols-3 @2xl:grid-cols-4 @5xl:grid-cols-5 gap-3 mt-1">
                <template x-for="cat in categories" :key="cat.id">
                    <template x-if="activeCategory === cat.id">
                        <template x-for="product in cat.products" :key="product.id">
                            <div @click="tapProduct(product)" role="button" tabindex="0" @keydown.enter="tapProduct(product)"
                                    class="group relative bg-white rounded-2xl p-4 text-left cursor-pointer active:scale-95 lg:hover:-translate-y-0.5 lg:hover:shadow-lg lg:hover:shadow-neutral-300/50 transition-all border border-neutral-200">
                                <button type="button" @click.stop="quickOrder(product)" :disabled="quickOrderingId !== null"
                                        title="Lên đơn nhanh"
                                        class="absolute top-2.5 right-2.5 w-8 h-8 rounded-full bg-[var(--accent-light)] hover:bg-[var(--accent)] text-[var(--accent-text)] hover:text-white flex items-center justify-center shadow-sm transition z-10 disabled:opacity-50">
                                    <i class="fa-solid" :class="quickOrderingId === product.id ? 'fa-spinner fa-spin' : 'fa-bolt'"></i>
                                </button>
                                <template x-if="product.image_url">
                                    <img :src="product.image_url" class="w-full h-24 object-cover rounded-xl mb-3" alt="">
                                </template>
                                <template x-if="!product.image_url">
                                    <div class="w-10 h-10 rounded-xl bg-[var(--accent-light)] text-[var(--accent-text)] flex items-center justify-center mb-3 group-hover:bg-amber-200 transition">
                                        <i class="fa-solid fa-mug-hot"></i>
                                    </div>
                                </template>
                                <div class="text-base font-bold text-neutral-900 leading-tight pr-6" x-text="product.name"></div>

                                <template x-if="product.variants.length > 1">
                                    <div class="mt-1.5 space-y-0.5">
                                        <template x-for="v in product.variants" :key="v.id">
                                            <div class="flex items-center justify-between gap-2 text-xs">
                                                <span class="text-neutral-500 font-medium truncate" x-text="v.name"></span>
                                                <span class="text-[var(--accent-text)] font-bold shrink-0" x-text="formatPrice(v.price) + 'đ'"></span>
                                            </div>
                                        </template>
                                    </div>
                                </template>
                                <template x-if="product.variants.length <= 1">
                                    <div class="text-[var(--accent-text)] text-sm mt-1.5 font-bold" x-text="formatPrice(product.variants[0]?.price ?? 0) + 'đ'"></div>
                                </template>
                            </div>
                        </template>
                    </template>
                </template>
            </div>
        </div>
    </div>

    {{-- Giỏ hàng: panel cố định trên desktop/iPad, kéo mép trái để tuỳ chỉnh độ rộng --}}
    <aside x-data="resizablePanel('rot-cart-width', 320, 280, 480, 'left')" :style="`width: ${width}px`"
           class="hidden lg:flex lg:flex-col min-h-0 bg-white border-l border-neutral-200 shrink-0 relative">
        <div @mousedown="startResize($event)" @touchstart="startResize($event)"
             class="hidden lg:block absolute top-0 left-0 w-1.5 h-full cursor-col-resize hover:bg-[var(--accent)] transition z-10"
             :class="resizing && 'bg-[var(--accent)]'"></div>
        <div class="px-5 py-4 border-b border-neutral-200 flex items-center gap-2">
            <i class="fa-solid fa-bag-shopping text-[var(--accent-text)]"></i>
            <h3 class="text-neutral-900 font-bold">Giỏ hàng</h3>
            <span x-show="cartCount > 0" x-text="'(' + cartCount + ')'" class="text-neutral-500 text-sm"></span>
        </div>

        <div class="flex-1 overflow-y-auto scrollbar-hide px-5 py-3">
            <template x-if="cart.length === 0">
                <div class="text-center py-16 text-neutral-600">
                    <i class="fa-solid fa-mug-hot text-3xl mb-2 block"></i>
                    <p class="text-sm">Chạm vào món để thêm vào giỏ</p>
                </div>
            </template>
            <template x-for="(line, idx) in cart" :key="idx">
                <div class="py-3.5 border-b border-neutral-200">
                    <div class="text-neutral-900 font-semibold text-[15px] mb-2 truncate" x-text="lineInfo(line).product.name"></div>

                    <div class="flex items-center justify-between gap-3">
                        <div class="flex items-center gap-2 flex-wrap">
                            <template x-if="lineInfo(line).variants.length > 1">
                                <div class="flex gap-1">
                                    <template x-for="v in lineInfo(line).variants" :key="v.id">
                                        <button @click="switchLineVariant(idx, v)"
                                                :class="v.id === line.variant_id ? 'bg-[var(--accent)] text-white' : 'bg-neutral-100 text-neutral-600 hover:bg-neutral-200'"
                                                class="text-xs font-bold px-2.5 py-1.5 rounded-lg transition" x-text="v.name"></button>
                                    </template>
                                </div>
                            </template>
                            <span class="text-[var(--accent-text)] font-bold text-sm" x-text="formatPrice(line.unit_price) + 'đ'"></span>
                        </div>

                        <div class="flex items-center gap-2 shrink-0">
                            <button @click="changeQty(idx, -1)" class="w-9 h-9 rounded-lg bg-neutral-100 hover:bg-neutral-200 text-neutral-900 font-bold text-lg transition">−</button>
                            <span class="w-6 text-center text-neutral-900 font-semibold" x-text="line.quantity"></span>
                            <button @click="changeQty(idx, 1)" class="w-9 h-9 rounded-lg bg-neutral-100 hover:bg-neutral-200 text-neutral-900 font-bold text-lg transition">+</button>
                        </div>
                    </div>

                    <div class="flex items-center justify-between mt-2 gap-2">
                        <button x-show="lineInfo(line).product.modifier_groups.length > 0" @click="editLine(idx)"
                                class="text-xs text-neutral-400 hover:text-[var(--accent-text)] flex items-center gap-1 transition">
                            <i class="fa-solid fa-pen text-[9px]"></i>
                            <span x-text="lineInfo(line).modifierNames || 'Thêm topping'"></span>
                        </button>
                        <button @click="discountLineIdx = discountLineIdx === idx ? null : idx"
                                class="text-xs flex items-center gap-1 ml-auto transition"
                                :class="lineDiscountAmount(line) > 0 ? 'text-red-600 font-semibold' : 'text-neutral-400 hover:text-[var(--accent-text)]'">
                            <i class="fa-solid fa-tag text-[9px]"></i>
                            <template x-if="lineDiscountAmount(line) > 0">
                                <span x-text="'-' + formatPrice(lineDiscountAmount(line)) + 'đ'"></span>
                            </template>
                            <template x-if="lineDiscountAmount(line) === 0">
                                <span>Giảm giá</span>
                            </template>
                        </button>
                    </div>

                    <div x-show="discountLineIdx === idx" x-cloak class="flex gap-2 mt-2">
                        <div class="flex rounded-lg overflow-hidden border border-neutral-300 shrink-0">
                            <button type="button" @click="line.discount_type = 'percent'"
                                    :class="line.discount_type === 'percent' ? 'bg-[var(--accent)] text-white' : 'bg-white text-neutral-600'"
                                    class="px-2.5 py-1.5 text-xs font-bold transition">%</button>
                            <button type="button" @click="line.discount_type = 'amount'"
                                    :class="line.discount_type === 'amount' ? 'bg-[var(--accent)] text-white' : 'bg-white text-neutral-600'"
                                    class="px-2.5 py-1.5 text-xs font-bold transition border-l border-neutral-300">đ</button>
                        </div>
                        <input type="text" :id="`line-discount-${idx}`" :name="`line_discount_${idx}`" placeholder="0" inputmode="numeric"
                               :value="line.discount_type === 'amount' && line.discount_value ? Number(line.discount_value).toLocaleString('vi-VN') : (line.discount_value ?? '')"
                               @input="line.discount_value = $event.target.value.replace(/\D/g, '') ? Number($event.target.value.replace(/\D/g, '')) : null"
                               class="flex-1 rounded-lg border border-neutral-300 px-2 py-1.5 text-xs focus:border-[var(--accent-ring)] focus:outline-none">
                        <button type="button" x-show="line.discount_type" @click="line.discount_type = null; line.discount_value = null; mergeDuplicateLines()" class="text-neutral-400 px-1">
                            <i class="fa-solid fa-xmark text-xs"></i>
                        </button>
                    </div>
                </div>
            </template>
        </div>

        <div x-show="cart.length > 0" class="px-5 py-4 border-t border-neutral-200">
            <div class="flex items-center justify-between mb-3">
                <span class="text-neutral-500 text-sm font-medium">Tổng cộng</span>
                <span class="text-xl font-bold text-[var(--accent-text)]" x-text="formatPrice(cartTotal) + 'đ'"></span>
            </div>
            <div class="flex gap-2">
                <button x-show="!editingOrderId || isDraft" @click="saveDraft()" :disabled="savingDraft"
                        class="shrink-0 px-4 bg-neutral-100 hover:bg-neutral-200 text-neutral-700 rounded-xl py-3.5 font-semibold flex items-center justify-center gap-2 transition disabled:opacity-50">
                    <i class="fa-solid fa-clock"></i>
                </button>
                <button @click="checkoutOpen = true"
                        class="flex-1 bg-[var(--accent)] hover:bg-[var(--accent-hover)] text-white rounded-xl py-3.5 font-semibold flex items-center justify-center gap-2 transition">
                    <i class="fa-solid fa-credit-card"></i>Thanh toán
                </button>
            </div>
        </div>
    </aside>
    </div>

    {{-- Nút giỏ hàng nổi: chỉ hiện trên mobile/tablet nhỏ --}}
    <button x-show="cartCount > 0" @click="cartOpen = true"
            class="lg:hidden fixed left-3 right-3 bg-[var(--accent)] text-white rounded-2xl py-4 px-5 flex items-center justify-between shadow-lg shadow-[var(--accent)]/25 font-semibold z-20"
            style="bottom: calc(76px + env(safe-area-inset-bottom));">
        <span class="flex items-center gap-2"><i class="fa-solid fa-bag-shopping"></i><span x-text="cartCount + ' món'"></span></span>
        <span x-text="formatPrice(cartTotal) + 'đ'"></span>
    </button>

    {{-- Modal chọn size/topping --}}
    <div x-show="pickerProduct" x-cloak class="fixed inset-0 bg-black/60 z-30 flex items-end lg:items-center lg:justify-center" @click.self="pickerProduct = null; editingLineIndex = null; quickOrderMode = false">
        <div class="bg-white w-full lg:max-w-md lg:rounded-3xl rounded-t-3xl p-5 max-h-[85vh] overflow-y-auto scrollbar-hide shadow-2xl" style="padding-bottom: max(1.5rem, env(safe-area-inset-bottom));">
            <template x-if="pickerProduct">
                <div>
                    <div class="flex items-center gap-2 mb-4">
                        <template x-if="pickerProduct.image_url">
                            <img :src="pickerProduct.image_url" class="w-9 h-9 rounded-xl object-cover">
                        </template>
                        <template x-if="!pickerProduct.image_url">
                            <div class="w-9 h-9 rounded-xl bg-[var(--accent-light)] text-[var(--accent-text)] flex items-center justify-center"><i class="fa-solid fa-mug-hot"></i></div>
                        </template>
                        <h3 class="text-lg font-bold text-neutral-900" x-text="pickerProduct.name"></h3>
                        <span x-show="quickOrderMode" x-cloak class="ml-auto text-xs font-bold px-2.5 py-1 rounded-full bg-[var(--accent-light)] text-[var(--accent-text)] flex items-center gap-1">
                            <i class="fa-solid fa-bolt"></i>Lên đơn nhanh
                        </span>
                    </div>

                    <div class="mb-4" x-show="pickerProduct.variants.length > 1">
                        <div class="text-xs text-neutral-500 mb-2 font-bold uppercase tracking-wide">Chọn size</div>
                        <div class="flex gap-2 flex-wrap">
                            <template x-for="v in pickerProduct.variants" :key="v.id">
                                <button @click="selectedVariant = v"
                                        :class="selectedVariant?.id === v.id ? 'bg-[var(--accent)] text-white' : 'bg-neutral-100 text-neutral-700 border border-neutral-200'"
                                        class="px-4 py-2 rounded-xl text-sm transition">
                                    <span x-text="v.name"></span> · <span x-text="formatPrice(v.price)"></span>
                                </button>
                            </template>
                        </div>
                    </div>

                    <template x-for="group in pickerProduct.modifier_groups" :key="group.id">
                        <div class="mb-4">
                            <div class="text-xs text-neutral-500 mb-2 font-bold uppercase tracking-wide" x-text="group.name"></div>
                            <div class="flex flex-wrap gap-2">
                                <template x-for="m in group.modifiers" :key="m.id">
                                    <button @click="toggleModifier(group, m)"
                                            :class="isModifierSelected(m) ? 'bg-[var(--accent)] text-white' : 'bg-neutral-100 text-neutral-700 border border-neutral-200'"
                                            class="px-3 py-2 rounded-xl text-sm transition">
                                        <span x-text="m.name"></span>
                                        <template x-if="parseFloat(m.extra_price) > 0">
                                            <span x-text="' +' + formatPrice(m.extra_price)"></span>
                                        </template>
                                    </button>
                                </template>
                            </div>
                        </div>
                    </template>

                    <div class="flex items-center justify-between mb-5">
                        <div class="text-sm text-neutral-600 font-medium">Số lượng</div>
                        <div class="flex items-center gap-4">
                            <button @click="pickerQty = Math.max(1, pickerQty - 1)" class="w-11 h-11 rounded-xl bg-neutral-100 hover:bg-neutral-200 text-neutral-900 text-xl font-bold transition">−</button>
                            <span class="text-xl font-semibold w-6 text-center text-neutral-900" x-text="pickerQty"></span>
                            <button @click="pickerQty++" class="w-11 h-11 rounded-xl bg-neutral-100 hover:bg-neutral-200 text-neutral-900 text-xl font-bold transition">+</button>
                        </div>
                    </div>

                    <button @click="confirmAddToCart()"
                            class="w-full bg-[var(--accent)] hover:bg-[var(--accent-hover)] text-white rounded-xl py-3.5 font-semibold flex items-center justify-center gap-2 transition">
                        <i class="fa-solid" :class="quickOrderMode ? 'fa-bolt' : (editingLineIndex !== null ? 'fa-check' : 'fa-cart-plus')"></i>
                        <span x-text="quickOrderMode ? 'Lên đơn nhanh' : (editingLineIndex !== null ? 'Cập nhật' : 'Thêm vào giỏ')"></span>
                        · <span x-text="formatPrice(pickerLineTotal()) + 'đ'"></span>
                    </button>
                </div>
            </template>
        </div>
    </div>

    {{-- Drawer giỏ hàng: chỉ dùng trên mobile/tablet (desktop đã có panel cố định) --}}
    <div x-show="cartOpen" x-cloak class="lg:hidden fixed inset-0 bg-black/60 z-30 flex items-end" @click.self="cartOpen = false">
        <div class="bg-white w-full rounded-t-3xl p-5 max-h-[85vh] overflow-y-auto scrollbar-hide shadow-2xl" style="padding-bottom: max(1.5rem, env(safe-area-inset-bottom));">
            <div class="flex items-center gap-2 mb-4">
                <i class="fa-solid fa-bag-shopping text-[var(--accent-text)]"></i>
                <h3 class="text-lg font-bold text-neutral-900">Giỏ hàng</h3>
            </div>

            <template x-if="cart.length === 0">
                <p class="text-neutral-500 text-sm">Chưa có món nào.</p>
            </template>

            <template x-for="(line, idx) in cart" :key="idx">
                <div class="py-3.5 border-b border-neutral-200">
                    <div class="text-neutral-900 font-semibold text-[15px] mb-2 truncate" x-text="lineInfo(line).product.name"></div>

                    <div class="flex items-center justify-between gap-3">
                        <div class="flex items-center gap-2 flex-wrap">
                            <template x-if="lineInfo(line).variants.length > 1">
                                <div class="flex gap-1">
                                    <template x-for="v in lineInfo(line).variants" :key="v.id">
                                        <button @click="switchLineVariant(idx, v)"
                                                :class="v.id === line.variant_id ? 'bg-[var(--accent)] text-white' : 'bg-neutral-100 text-neutral-600'"
                                                class="text-xs font-bold px-2.5 py-1.5 rounded-lg transition" x-text="v.name"></button>
                                    </template>
                                </div>
                            </template>
                            <span class="text-[var(--accent-text)] font-bold text-sm" x-text="formatPrice(line.unit_price) + 'đ'"></span>
                        </div>

                        <div class="flex items-center gap-2 shrink-0">
                            <button @click="changeQty(idx, -1)" class="w-9 h-9 rounded-lg bg-neutral-100 text-neutral-900 font-bold text-lg">−</button>
                            <span class="w-6 text-center text-neutral-900 font-semibold" x-text="line.quantity"></span>
                            <button @click="changeQty(idx, 1)" class="w-9 h-9 rounded-lg bg-neutral-100 text-neutral-900 font-bold text-lg">+</button>
                        </div>
                    </div>

                    <div class="flex items-center justify-between mt-2 gap-2">
                        <button x-show="lineInfo(line).product.modifier_groups.length > 0" @click="editLine(idx)"
                                class="text-xs text-neutral-400 flex items-center gap-1">
                            <i class="fa-solid fa-pen text-[9px]"></i>
                            <span x-text="lineInfo(line).modifierNames || 'Thêm topping'"></span>
                        </button>
                        <button @click="discountLineIdx = discountLineIdx === idx ? null : idx"
                                class="text-xs flex items-center gap-1 ml-auto"
                                :class="lineDiscountAmount(line) > 0 ? 'text-red-600 font-semibold' : 'text-neutral-400'">
                            <i class="fa-solid fa-tag text-[9px]"></i>
                            <template x-if="lineDiscountAmount(line) > 0">
                                <span x-text="'-' + formatPrice(lineDiscountAmount(line)) + 'đ'"></span>
                            </template>
                            <template x-if="lineDiscountAmount(line) === 0">
                                <span>Giảm giá</span>
                            </template>
                        </button>
                    </div>

                    <div x-show="discountLineIdx === idx" x-cloak class="flex gap-2 mt-2">
                        <div class="flex rounded-lg overflow-hidden border border-neutral-300 shrink-0">
                            <button type="button" @click="line.discount_type = 'percent'"
                                    :class="line.discount_type === 'percent' ? 'bg-[var(--accent)] text-white' : 'bg-white text-neutral-600'"
                                    class="px-2.5 py-1.5 text-xs font-bold transition">%</button>
                            <button type="button" @click="line.discount_type = 'amount'"
                                    :class="line.discount_type === 'amount' ? 'bg-[var(--accent)] text-white' : 'bg-white text-neutral-600'"
                                    class="px-2.5 py-1.5 text-xs font-bold transition border-l border-neutral-300">đ</button>
                        </div>
                        <input type="text" :id="`line-discount-mobile-${idx}`" :name="`line_discount_mobile_${idx}`" placeholder="0" inputmode="numeric"
                               :value="line.discount_type === 'amount' && line.discount_value ? Number(line.discount_value).toLocaleString('vi-VN') : (line.discount_value ?? '')"
                               @input="line.discount_value = $event.target.value.replace(/\D/g, '') ? Number($event.target.value.replace(/\D/g, '')) : null"
                               class="flex-1 rounded-lg border border-neutral-300 px-2 py-1.5 text-xs focus:border-[var(--accent-ring)] focus:outline-none">
                        <button type="button" x-show="line.discount_type" @click="line.discount_type = null; line.discount_value = null; mergeDuplicateLines()" class="text-neutral-400 px-1">
                            <i class="fa-solid fa-xmark text-xs"></i>
                        </button>
                    </div>
                </div>
            </template>

            <div x-show="cart.length > 0">
                <div class="flex items-center justify-between mt-4 mb-4">
                    <span class="text-neutral-500 font-medium">Tổng cộng</span>
                    <span class="text-xl font-bold text-[var(--accent-text)]" x-text="formatPrice(cartTotal) + 'đ'"></span>
                </div>

                <div class="flex gap-2">
                    <button x-show="!editingOrderId || isDraft" @click="saveDraft()" :disabled="savingDraft"
                            class="shrink-0 px-4 bg-neutral-100 text-neutral-700 rounded-xl py-3.5 font-semibold flex items-center justify-center gap-2 disabled:opacity-50">
                        <i class="fa-solid fa-clock"></i>
                    </button>
                    <button @click="cartOpen = false; checkoutOpen = true"
                            class="flex-1 bg-[var(--accent)] text-white rounded-xl py-3.5 font-semibold flex items-center justify-center gap-2">
                        <i class="fa-solid fa-credit-card"></i>Thanh toán
                    </button>
                </div>
            </div>
        </div>
    </div>

    {{-- Modal thanh toán --}}
    <div x-show="checkoutOpen" x-cloak class="fixed inset-0 bg-black/60 z-40 flex items-end lg:items-center lg:justify-center" @click.self="checkoutOpen = false">
        <div class="bg-white w-full lg:max-w-md lg:rounded-3xl rounded-t-3xl p-5 max-h-[90vh] overflow-y-auto scrollbar-hide shadow-2xl" style="padding-bottom: max(1.5rem, env(safe-area-inset-bottom));">
            <div class="flex items-center gap-2 mb-4">
                <i class="fa-solid fa-credit-card text-[var(--accent-text)]"></i>
                <h3 class="text-lg font-bold text-neutral-900">Thanh toán</h3>
            </div>

            <div class="mb-4">
                <div class="text-xs text-neutral-500 mb-2 font-bold uppercase tracking-wide">Loại đơn</div>
                <div class="flex gap-2">
                    <button @click="orderType = 'mang_di'" :class="orderType === 'mang_di' ? 'bg-[var(--accent)] text-white' : 'bg-neutral-100 text-neutral-700 border border-neutral-200'" class="flex-1 py-3 rounded-xl font-medium flex items-center justify-center gap-2 transition">
                        <i class="fa-solid fa-bag-shopping"></i>Mang đi
                    </button>
                    <button @click="orderType = 'ngoi_lai'" :class="orderType === 'ngoi_lai' ? 'bg-[var(--accent)] text-white' : 'bg-neutral-100 text-neutral-700 border border-neutral-200'" class="flex-1 py-3 rounded-xl font-medium flex items-center justify-center gap-2 transition">
                        <i class="fa-solid fa-chair"></i>Ngồi lại
                    </button>
                </div>
            </div>

            <div class="mb-4" x-show="orderType === 'ngoi_lai'">
                <div class="text-xs text-neutral-500 mb-2 font-bold uppercase tracking-wide">Số khách</div>
                <div class="flex items-center gap-4">
                    <button @click="guestCount = Math.max(1, guestCount - 1)" class="w-11 h-11 rounded-xl bg-neutral-100 hover:bg-neutral-200 text-neutral-900 text-xl font-bold transition">−</button>
                    <span class="text-xl font-semibold w-6 text-center text-neutral-900" x-text="guestCount"></span>
                    <button @click="guestCount++" class="w-11 h-11 rounded-xl bg-neutral-100 hover:bg-neutral-200 text-neutral-900 text-xl font-bold transition">+</button>
                </div>
            </div>

            <div class="mb-4">
                <div class="text-xs text-neutral-500 mb-2 font-bold uppercase tracking-wide">Hình thức thanh toán</div>
                <div class="grid grid-cols-3 gap-2">
                    <button @click="paymentMethod = 'tien_mat'" :class="paymentMethod === 'tien_mat' ? 'bg-[var(--accent)] text-white' : 'bg-neutral-100 text-neutral-700 border border-neutral-200'" class="py-3 rounded-xl text-sm font-medium flex flex-col items-center gap-1 transition">
                        <i class="fa-solid fa-money-bill-wave"></i>Tiền mặt
                    </button>
                    <button @click="paymentMethod = 'chuyen_khoan'" :class="paymentMethod === 'chuyen_khoan' ? 'bg-[var(--accent)] text-white' : 'bg-neutral-100 text-neutral-700 border border-neutral-200'" class="py-3 rounded-xl text-sm font-medium flex flex-col items-center gap-1 transition">
                        <i class="fa-solid fa-building-columns"></i>Chuyển khoản
                    </button>
                    <button @click="paymentMethod = 'vi_dien_tu'" :class="paymentMethod === 'vi_dien_tu' ? 'bg-[var(--accent)] text-white' : 'bg-neutral-100 text-neutral-700 border border-neutral-200'" class="py-3 rounded-xl text-sm font-medium flex flex-col items-center gap-1 transition">
                        <i class="fa-solid fa-wallet"></i>Ví điện tử
                    </button>
                </div>
            </div>

            <template x-if="paymentMethod === 'tien_mat'">
                <div class="mb-4">
                    <div class="text-xs text-neutral-500 mb-2 font-bold uppercase tracking-wide">Khách đưa</div>
                    <input type="text" inputmode="numeric" id="cash-received-input" name="cash_received_display" autocomplete="off"
                           :value="cashReceived ? Number(cashReceived).toLocaleString('vi-VN') : ''"
                           @input="cashReceived = $event.target.value.replace(/\D/g, '') ? Number($event.target.value.replace(/\D/g, '')) : null"
                           class="w-full text-2xl font-bold text-center rounded-xl bg-neutral-100 border border-neutral-300 text-neutral-900 py-3 focus:border-[var(--accent-ring)] focus:outline-none">

                    <div class="flex flex-wrap gap-2 mt-3">
                        <button @click="cashReceived = finalTotal()" type="button"
                                class="text-xs font-bold px-3 py-1.5 rounded-lg bg-emerald-50 text-emerald-700 hover:bg-emerald-100 transition">
                            Đủ tiền
                        </button>
                        <template x-for="note in [20000, 50000, 100000, 200000, 500000]" :key="note">
                            <button @click="cashReceived = note" type="button"
                                    class="text-xs font-bold px-3 py-1.5 rounded-lg bg-neutral-100 text-neutral-700 hover:bg-neutral-200 transition"
                                    x-text="formatPrice(note) + 'đ'"></button>
                        </template>
                    </div>

                    <div class="text-center mt-3">
                        <div class="text-xs text-neutral-500 font-medium">Tiền thối lại</div>
                        <div class="text-3xl font-bold text-emerald-600" x-text="formatPrice(changeAmount()) + 'đ'"></div>
                    </div>
                </div>
            </template>

            <template x-if="(paymentMethod === 'chuyen_khoan' || paymentMethod === 'vi_dien_tu') && bankInfo">
                <div class="mb-4">
                    <div class="flex flex-col items-center bg-neutral-50 rounded-xl py-4 border border-neutral-200">
                        <img :src="qrCodeUrl()" alt="Mã QR chuyển khoản" class="w-48 h-48 object-contain rounded-lg bg-white p-2 border border-neutral-200">
                        <p class="text-xs text-neutral-500 mt-3 text-center px-4">Quét bằng app ngân hàng bất kỳ, MoMo hoặc ZaloPay — số tiền đã tự điền sẵn.</p>
                    </div>
                </div>
            </template>

            <div class="mb-4">
                <button @click="discountOpen = !discountOpen" type="button" class="text-xs text-[var(--accent-text)] font-semibold flex items-center gap-1.5">
                    <i class="fa-solid fa-tag"></i>
                    <span x-text="discountType ? 'Sửa giảm giá' : 'Thêm giảm giá'"></span>
                </button>

                <div x-show="discountOpen" x-cloak class="mt-2 flex gap-2">
                    <div class="flex rounded-xl overflow-hidden border border-neutral-300 shrink-0">
                        <button type="button" @click="discountType = 'percent'"
                                :class="discountType === 'percent' ? 'bg-[var(--accent)] text-white' : 'bg-white text-neutral-600'"
                                class="px-3 py-2 text-sm font-bold transition">%</button>
                        <button type="button" @click="discountType = 'amount'"
                                :class="discountType === 'amount' ? 'bg-[var(--accent)] text-white' : 'bg-white text-neutral-600'"
                                class="px-3 py-2 text-sm font-bold transition border-l border-neutral-300">đ</button>
                    </div>
                    <input type="text" id="discount-value-input" name="discount_value_display" autocomplete="off" placeholder="0" inputmode="numeric"
                           :value="discountType === 'amount' && discountValue ? Number(discountValue).toLocaleString('vi-VN') : (discountValue ?? '')"
                           @input="discountValue = discountType === 'amount'
                               ? ($event.target.value.replace(/\D/g, '') ? Number($event.target.value.replace(/\D/g, '')) : null)
                               : ($event.target.value.replace(/[^0-9]/g, '') ? Number($event.target.value.replace(/[^0-9]/g, '')) : null)"
                           class="flex-1 rounded-xl border border-neutral-300 px-3 py-2 text-sm focus:border-[var(--accent-ring)] focus:outline-none">
                    <button type="button" x-show="discountType" @click="discountType = null; discountValue = null" class="text-neutral-400 px-2">
                        <i class="fa-solid fa-xmark"></i>
                    </button>
                </div>
            </div>

            <template x-if="loyaltyEnabled">
                <div class="mb-4">
                    <label for="customer-phone-input" class="text-xs text-neutral-500 mb-2 font-bold uppercase tracking-wide flex items-center gap-1.5">
                        <i class="fa-solid fa-heart"></i>Khách hàng (không bắt buộc)
                    </label>
                    <input type="tel" id="customer-phone-input" name="customer_phone_display" x-model="customerPhone" @input="onCustomerPhoneInput()" inputmode="numeric"
                           placeholder="Số điện thoại khách hàng" autocomplete="off"
                           class="w-full rounded-xl border border-neutral-300 px-4 py-2.5 text-sm focus:border-[var(--accent-ring)] focus:outline-none">

                    <template x-if="lookingUpCustomer">
                        <p class="text-xs text-neutral-400 mt-1.5">Đang tra cứu...</p>
                    </template>

                    <template x-if="!lookingUpCustomer && customerLookedUp && customerFound">
                        <div class="mt-2 bg-emerald-50 border border-emerald-200 rounded-xl px-3 py-2.5">
                            <div class="flex items-center justify-between text-sm">
                                <span class="text-emerald-800 font-medium" x-text="customerName || 'Khách quen'"></span>
                                <span class="text-emerald-700 font-bold" x-text="formatPrice(customerPoints) + ' điểm'"></span>
                            </div>
                            <div class="flex items-center gap-2 mt-2">
                                <span class="text-xs text-neutral-500 shrink-0">Dùng điểm giảm giá:</span>
                                <input type="number" id="redeem-points-input" name="redeem_points_display" x-model.number="redeemPoints" min="0" :max="customerPoints" inputmode="numeric"
                                       class="flex-1 rounded-lg border border-neutral-300 px-2 py-1.5 text-xs focus:border-[var(--accent-ring)] focus:outline-none">
                            </div>
                            <p class="text-xs text-neutral-500 mt-1" x-show="pointsRedeemedValue() > 0">
                                Giảm thêm <span x-text="formatPrice(pointsRedeemedValue()) + 'đ'"></span>
                            </p>
                        </div>
                    </template>

                    <template x-if="!lookingUpCustomer && customerLookedUp && !customerFound">
                        <div class="mt-2">
                            <input type="text" id="customer-name-input" name="customer_name_display" x-model="customerName" placeholder="Tên khách (không bắt buộc, để tạo hồ sơ mới)" autocomplete="off"
                                   class="w-full rounded-xl border border-neutral-300 px-4 py-2 text-sm focus:border-[var(--accent-ring)] focus:outline-none">
                            <p class="text-xs text-neutral-400 mt-1">Khách mới — sẽ tự tạo hồ sơ và bắt đầu tích điểm từ đơn này.</p>
                        </div>
                    </template>
                </div>
            </template>

            <div class="mb-4 space-y-1.5">
                <template x-if="discountAmount() > 0 || pointsRedeemedValue() > 0">
                    <div>
                        <div class="flex items-center justify-between text-sm">
                            <span class="text-neutral-500">Tạm tính</span>
                            <span class="text-neutral-700" x-text="formatPrice(cartTotal) + 'đ'"></span>
                        </div>
                        <div class="flex items-center justify-between text-sm mt-1" x-show="discountAmount() > 0">
                            <span class="text-neutral-500">Giảm giá</span>
                            <span class="text-red-600" x-text="'-' + formatPrice(discountAmount()) + 'đ'"></span>
                        </div>
                        <div class="flex items-center justify-between text-sm mt-1" x-show="pointsRedeemedValue() > 0">
                            <span class="text-neutral-500">Dùng điểm</span>
                            <span class="text-red-600" x-text="'-' + formatPrice(pointsRedeemedValue()) + 'đ'"></span>
                        </div>
                    </div>
                </template>
                <div class="flex items-center justify-between text-lg pt-1">
                    <span class="text-neutral-500 font-medium">Tổng tiền</span>
                    <span class="font-bold text-neutral-900" x-text="formatPrice(finalTotal()) + 'đ'"></span>
                </div>
            </div>

            <button @click="submitOrder()" :disabled="submitting"
                    class="w-full bg-emerald-600 hover:bg-emerald-700 text-white rounded-xl py-4 font-bold text-lg disabled:opacity-50 flex items-center justify-center gap-2 transition">
                <i class="fa-solid fa-check" x-show="!submitting"></i>
                <i class="fa-solid fa-spinner fa-spin" x-show="submitting"></i>
                <span x-show="!submitting">Hoàn tất</span>
                <span x-show="submitting">Đang xử lý...</span>
            </button>
        </div>
    </div>

</div>

<script>
function posApp(categoriesData, initialCartData = [], editingOrderId = null, receiptEnabled = true, bankInfo = null, isDraft = false, loyaltyEnabled = false, pointsRedeemValue = 1000, existingCustomer = null, prefillCustomerPhone = null) {
    return {
        categories: categoriesData,
        activeCategory: categoriesData[0]?.id ?? null,
        cart: [],
        editingOrderId: editingOrderId,
        isDraft: isDraft,
        receiptEnabled: receiptEnabled,
        bankInfo: bankInfo,
        loyaltyEnabled: loyaltyEnabled,
        pointsRedeemValue: pointsRedeemValue,
        customerPhone: existingCustomer?.phone ?? prefillCustomerPhone ?? '',
        customerName: existingCustomer?.name ?? '',
        customerFound: !!existingCustomer,
        customerPoints: existingCustomer?.points ?? 0,
        customerLookedUp: !!existingCustomer,
        lookingUpCustomer: false,
        redeemPoints: 0,
        cartOpen: false,
        checkoutOpen: false,
        pickerProduct: null,
        selectedVariant: null,
        selectedModifiers: [],
        pickerQty: 1,
        editingLineIndex: null,
        orderType: 'mang_di',
        guestCount: 1,
        paymentMethod: 'tien_mat',
        cashReceived: null,
        discountOpen: false,
        discountType: null,
        discountValue: null,
        discountLineIdx: null,
        submitting: false,
        savingDraft: false,
        quickOrderingId: null,
        quickOrderMode: false,
        toastMessage: null,
        toastTimeout: null,
        acceptedBannerVisible: true,

        // Nạp sẵn giỏ hàng khi mở chế độ SỬA ĐƠN — tính lại unit_price từ dữ
        // liệu món/size/topping thật (không tin giá cũ lưu trong đơn, phòng khi
        // giá đã đổi từ lúc bán tới giờ vẫn hiển thị đúng giá cho việc sửa).
        init() {
            if (initialCartData.length) {
                this.cart = initialCartData.map(line => {
                    const product = this.findProductByVariantId(line.variant_id);
                    const variant = product?.variants.find(v => v.id === line.variant_id);
                    const allModifiers = product?.modifier_groups.flatMap(g => g.modifiers) ?? [];
                    const modifiers = allModifiers.filter(m => line.modifier_ids.includes(m.id));
                    const unitPrice = parseFloat(variant?.price ?? 0) + modifiers.reduce((s, m) => s + parseFloat(m.extra_price), 0);

                    return {
                        variant_id: line.variant_id,
                        modifier_ids: line.modifier_ids,
                        quantity: line.quantity,
                        unit_price: unitPrice,
                        discount_type: line.discount_type ?? null,
                        discount_value: line.discount_value ?? null,
                    };
                });
            }

            // Khách đã điền SĐT lúc gửi yêu cầu qua QR — tra cứu NGAY (không
            // debounce, đây là điền sẵn từ hệ thống chứ không phải người dùng
            // đang gõ) để nhân viên thấy luôn khách quen/điểm tích luỹ, khỏi
            // phải gõ lại tay đúng số khách vừa gửi.
            if (!existingCustomer && this.loyaltyEnabled && this.customerPhone) {
                this.lookupCustomer();
            }
        },

        get cartCount() {
            return this.cart.reduce((sum, l) => sum + l.quantity, 0);
        },
        get cartTotal() {
            return this.cart.reduce((sum, l) => sum + this.lineFinalTotal(l), 0);
        },

        /** Giá gốc của 1 dòng, chưa trừ giảm giá riêng của dòng đó. */
        lineGrossTotal(line) {
            return line.unit_price * line.quantity;
        },

        /** Số tiền giảm giá của RIÊNG dòng này (hiển thị trước — server luôn tính lại). */
        lineDiscountAmount(line) {
            if (!line.discount_type || !line.discount_value || line.discount_value <= 0) return 0;
            const gross = this.lineGrossTotal(line);
            if (line.discount_type === 'percent') {
                return Math.min(gross, gross * Math.min(line.discount_value, 100) / 100);
            }
            return Math.min(gross, line.discount_value);
        },

        lineFinalTotal(line) {
            return Math.max(0, this.lineGrossTotal(line) - this.lineDiscountAmount(line));
        },

        formatPrice(v) {
            return new Intl.NumberFormat('vi-VN').format(Math.round(v || 0));
        },

        /**
         * Sinh URL ảnh QR VietQR theo ĐÚNG số tiền hiện tại của giỏ hàng —
         * dùng dịch vụ Quick Link công khai của img.vietqr.io, không cần API
         * key. QR tự cập nhật lại nếu khách đổi món trước khi quét.
         */
        qrCodeUrl() {
            if (!this.bankInfo) return null;
            const params = new URLSearchParams({
                amount: Math.round(this.finalTotal()),
                addInfo: 'Thanh toan don hang',
                accountName: this.bankInfo.accountName || '',
            });
            return `https://img.vietqr.io/image/${this.bankInfo.bin}-${this.bankInfo.accountNo}-compact2.png?${params.toString()}`;
        },

        tapProduct(product) {
            this.editingLineIndex = null;
            const hasOptions = product.variants.length > 1 || product.modifier_groups.length > 0;

            if (!hasOptions) {
                this.addToCart(product, product.variants[0], [], 1);
                return;
            }

            this.pickerProduct = product;
            this.selectedVariant = product.variants[0];
            // Tự chọn sẵn các tuỳ chọn được đánh dấu "Mặc định" (VD: Đường 100%)
            // để bớt 1 thao tác — nhân viên chỉ cần bấm "Thêm vào giỏ" nếu khách
            // không yêu cầu gì khác.
            this.selectedModifiers = product.modifier_groups
                .flatMap(g => g.modifiers)
                .filter(m => m.is_default);
            this.pickerQty = 1;
        },

        /**
         * "Lên đơn nhanh" — dành cho khách chỉ mua ĐÚNG 1 ly, trả tiền mặt vừa
         * đủ (không thối lại). Bỏ qua HẲN giỏ hàng + modal thanh toán đầy đủ.
         *
         * - Món ĐƠN GIẢN (không phân size, không có tuỳ chọn): lên đơn THẲNG
         *   ngay khi bấm, không qua bước nào khác — nhanh tối đa.
         * - Món CÓ TUỲ CHỌN THẬT (nhiều size, hoặc có nhóm tuỳ chọn như "Lượng
         *   đường"): BẮT BUỘC mở lại đúng modal chọn size/topping — tự chọn
         *   sẵn size/tuỳ chọn mặc định như bình thường, khách muốn đổi vẫn đổi
         *   được — rồi mới lên đơn khi xác nhận. Tốn thêm ĐÚNG 1 bước so với
         *   trước đây (không còn đoán bừa mặc định cho món có lựa chọn), nhưng
         *   vẫn nhanh hơn NHIỀU so với luồng giỏ hàng + modal thanh toán đầy đủ.
         *
         * CỐ Ý không đụng tới this.cart — đây là 1 giao dịch ĐỘC LẬP, để
         * không làm xáo trộn đơn đang gộp dở cho khách khác trên cùng máy.
         */
        quickOrder(product) {
            const hasOptions = product.variants.length > 1 || product.modifier_groups.length > 0;

            if (!hasOptions) {
                this.submitQuickOrder(product, product.variants[0], [], 1);
                return;
            }

            this.editingLineIndex = null;
            this.quickOrderMode = true;
            this.pickerProduct = product;
            this.selectedVariant = product.variants.find(v => v.is_default) ?? product.variants[0];
            this.selectedModifiers = product.modifier_groups
                .flatMap(g => g.modifiers)
                .filter(m => m.is_default);
            this.pickerQty = 1;
        },

        /** Gửi thẳng 1 đơn tiền mặt/khách đưa đủ — dùng chung cho cả 2 nhánh của quickOrder() ở trên. */
        async submitQuickOrder(product, variant, modifiers, quantity) {
            if (this.quickOrderingId !== null) return;
            this.quickOrderingId = product.id;

            const receiptWindow = this.receiptEnabled ? window.open('', '_blank') : null;

            try {
                const res = await fetch('{{ route('pos.checkout') }}', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                        'Accept': 'application/json',
                    },
                    body: JSON.stringify({
                        order_type: 'mang_di',
                        guest_count: null,
                        payment_method: 'tien_mat',
                        cash_received: null,
                        discount_type: null,
                        discount_value: null,
                        customer_phone: null,
                        customer_name: null,
                        redeem_points: null,
                        items: [{
                            variant_id: variant.id,
                            quantity: quantity,
                            modifier_ids: modifiers.map(m => m.id),
                            discount_type: null,
                            discount_value: null,
                        }],
                    }),
                });

                if (!res.ok) {
                    receiptWindow?.close();
                    const err = await res.json();
                    alert(err.message || 'Không lên đơn được, thử lại nhé.');
                    return;
                }

                const result = await res.json();
                if (result.order_id && receiptWindow) {
                    receiptWindow.location.href = `/don-hang/${result.order_id}/in`;
                } else {
                    receiptWindow?.close();
                }

                if (navigator.vibrate) navigator.vibrate([20, 40, 20]);
                const total = (parseFloat(variant.price) + modifiers.reduce((s, m) => s + parseFloat(m.extra_price), 0)) * quantity;
                const qtyLabel = quantity > 1 ? ` x${quantity}` : '';
                this.showToast(`Đã bán ${product.name}${qtyLabel} — ${this.formatPrice(total)}đ`);
                // Cập nhật NGAY badge "Đơn hàng" trên menu — request này đi qua
                // fetch(), không có điều hướng trang nào để server tính lại
                // (xem view composer trong AppServiceProvider).
                Alpine.store('nav').orderBadgeCount++;
            } catch (e) {
                receiptWindow?.close();
                alert('Không kết nối được, kiểm tra mạng và thử lại.');
            } finally {
                this.quickOrderingId = null;
            }
        },

        /** Hiện toast báo thành công 2.5s rồi tự ẩn — dùng chung cho Lên đơn nhanh và thanh toán bình thường. */
        showToast(message) {
            this.toastMessage = message;
            clearTimeout(this.toastTimeout);
            this.toastTimeout = setTimeout(() => { this.toastMessage = null; }, 2500);
        },

        toggleModifier(group, modifier) {
            const idx = this.selectedModifiers.findIndex(m => m.id === modifier.id);

            if (idx >= 0) {
                this.selectedModifiers.splice(idx, 1);
                return;
            }

            if (!group.allow_multiple) {
                this.selectedModifiers = this.selectedModifiers.filter(m => m.modifier_group_id !== group.id);
            }

            this.selectedModifiers.push(modifier);
        },

        isModifierSelected(modifier) {
            return this.selectedModifiers.some(m => m.id === modifier.id);
        },

        pickerLineTotal() {
            const base = parseFloat(this.selectedVariant?.price ?? 0);
            const extra = this.selectedModifiers.reduce((s, m) => s + parseFloat(m.extra_price), 0);
            return (base + extra) * this.pickerQty;
        },

        confirmAddToCart() {
            if (this.quickOrderMode) {
                this.submitQuickOrder(this.pickerProduct, this.selectedVariant, this.selectedModifiers, this.pickerQty);
                this.quickOrderMode = false;
                this.pickerProduct = null;
                return;
            }

            if (this.editingLineIndex !== null) {
                const unitPrice = parseFloat(this.selectedVariant.price) +
                    this.selectedModifiers.reduce((s, m) => s + parseFloat(m.extra_price), 0);
                const label = this.pickerProduct.name + (this.selectedVariant.name ? ' (' + this.selectedVariant.name + ')' : '') +
                    (this.selectedModifiers.length ? ' - ' + this.selectedModifiers.map(m => m.name).join(', ') : '');

                this.cart[this.editingLineIndex] = {
                    variant_id: this.selectedVariant.id,
                    modifier_ids: this.selectedModifiers.map(m => m.id).sort((a, b) => a - b),
                    quantity: this.pickerQty,
                    unit_price: unitPrice,
                    label,
                };
                this.editingLineIndex = null;
            } else {
                this.addToCart(this.pickerProduct, this.selectedVariant, this.selectedModifiers, this.pickerQty);
            }

            this.pickerProduct = null;
        },

        /**
         * Mở lại modal chọn size/topping cho 1 dòng ĐÃ CÓ trong giỏ — để sửa nhanh
         * khi bấm nhầm size lúc order, không cần xoá dòng cũ rồi thêm lại từ đầu.
         */
        editLine(idx) {
            const line = this.cart[idx];
            const product = this.findProductByVariantId(line.variant_id);
            if (!product) return;

            this.pickerProduct = product;
            this.selectedVariant = product.variants.find(v => v.id === line.variant_id) ?? product.variants[0];
            this.selectedModifiers = product.modifier_groups
                .flatMap(g => g.modifiers)
                .filter(m => line.modifier_ids.includes(m.id));
            this.pickerQty = line.quantity;
            this.editingLineIndex = idx;
            this.cartOpen = false;
        },

        findProductByVariantId(variantId) {
            for (const cat of this.categories) {
                for (const product of cat.products) {
                    if (product.variants.some(v => v.id === variantId)) {
                        return product;
                    }
                }
            }
            return null;
        },

        /**
         * Tính sẵn mọi thông tin cần hiển thị cho 1 dòng giỏ hàng — dùng chung
         * cho cả panel desktop lẫn drawer mobile, tránh lặp code tính toán.
         */
        lineInfo(line) {
            const product = this.findProductByVariantId(line.variant_id) ?? { name: '?', variants: [], modifier_groups: [] };

            // Hiện kèm TÊN NHÓM cho rõ nghĩa — VD "Đường: 100%" thay vì chỉ "100%"
            // trơ trọi, dễ hiểu nhầm là số lượng hay giá.
            const modifierNames = product.modifier_groups
                .map(g => {
                    const picked = g.modifiers.filter(m => line.modifier_ids.includes(m.id)).map(m => m.name);
                    return picked.length ? g.name + ': ' + picked.join(', ') : null;
                })
                .filter(Boolean)
                .join(' · ');

            return { product, variants: product.variants, modifierNames };
        },

        /**
         * Đổi size NGAY LẬP TỨC cho 1 dòng trong giỏ — bấm là đổi, không mở popup,
         * không chặn luồng order. Giữ nguyên topping đang chọn, chỉ đổi phần giá
         * theo size mới.
         */
        switchLineVariant(idx, newVariant) {
            const line = this.cart[idx];
            const oldVariant = this.lineInfo(line).variants.find(v => v.id === line.variant_id);
            const modifierExtra = line.unit_price - parseFloat(oldVariant?.price ?? 0);

            line.variant_id = newVariant.id;
            line.unit_price = parseFloat(newVariant.price) + modifierExtra;

            this.mergeDuplicateLines();
            if (navigator.vibrate) navigator.vibrate(10);
        },

        /** Gộp lại nếu sau khi đổi size, 2 dòng trong giỏ trở thành trùng nhau y hệt. */
        mergeDuplicateLines() {
            const merged = [];
            for (const line of this.cart) {
                const key = line.variant_id + ':' + [...line.modifier_ids].sort((a, b) => a - b).join(',');
                const existing = merged.find(l => l._key === key);
                if (existing) {
                    existing.quantity += line.quantity;
                } else {
                    merged.push({ ...line, _key: key });
                }
            }
            this.cart = merged.map(({ _key, ...rest }) => rest);
        },

        addToCart(product, variant, modifiers, quantity) {
            const modifierIds = modifiers.map(m => m.id).sort((a, b) => a - b);

            // Nếu giỏ đã có đúng món + size + topping này rồi, CỘNG DỒN số lượng
            // vào dòng cũ thay vì tạo thêm 1 dòng mới trùng lặp trong giỏ hàng.
            const existingIndex = this.cart.findIndex(line =>
                line.variant_id === variant.id &&
                JSON.stringify([...line.modifier_ids].sort((a, b) => a - b)) === JSON.stringify(modifierIds)
            );

            if (existingIndex >= 0) {
                this.cart[existingIndex].quantity += quantity;
            } else {
                const unitPrice = parseFloat(variant.price) + modifiers.reduce((s, m) => s + parseFloat(m.extra_price), 0);
                const label = product.name + (variant.name ? ' (' + variant.name + ')' : '') +
                    (modifiers.length ? ' - ' + modifiers.map(m => m.name).join(', ') : '');

                this.cart.push({
                    variant_id: variant.id,
                    modifier_ids: modifierIds,
                    quantity,
                    unit_price: unitPrice,
                    label,
                    discount_type: null,
                    discount_value: null,
                });
            }

            if (navigator.vibrate) navigator.vibrate(15);
        },

        changeQty(idx, delta) {
            this.cart[idx].quantity += delta;
            if (this.cart[idx].quantity <= 0) this.cart.splice(idx, 1);
        },

        changeAmount() {
            if (this.paymentMethod !== 'tien_mat' || !this.cashReceived) return 0;
            return Math.max(0, this.cashReceived - this.finalTotal());
        },

        /** Tính số tiền giảm giá hiển thị — server luôn tính lại, đây chỉ để xem trước. */
        discountAmount() {
            if (!this.discountType || !this.discountValue || this.discountValue <= 0) return 0;
            if (this.discountType === 'percent') {
                return Math.min(this.cartTotal, this.cartTotal * Math.min(this.discountValue, 100) / 100);
            }
            return Math.min(this.cartTotal, this.discountValue);
        },

        finalTotal() {
            return Math.max(0, this.cartTotal - this.discountAmount() - this.pointsRedeemedValue());
        },

        /** Giá trị quy đổi ra tiền của số điểm định dùng — chỉ để xem trước, server luôn tính lại và chặn đúng giới hạn. */
        pointsRedeemedValue() {
            if (!this.loyaltyEnabled || !this.customerFound || !this.redeemPoints) return 0;
            const amountAfterDiscount = Math.max(0, this.cartTotal - this.discountAmount());
            const maxUsable = Math.floor(amountAfterDiscount / this.pointsRedeemValue);
            const actual = Math.max(0, Math.min(this.redeemPoints, this.customerPoints, maxUsable));
            return actual * this.pointsRedeemValue;
        },

        /** Tra cứu khách hàng theo SĐT — debounce nhẹ để không gọi API liên tục khi đang gõ. */
        lookupCustomerTimeout: null,
        onCustomerPhoneInput() {
            this.customerFound = false;
            this.customerLookedUp = false;
            clearTimeout(this.lookupCustomerTimeout);

            if (this.customerPhone.length < 9) return;

            this.lookupCustomerTimeout = setTimeout(() => this.lookupCustomer(), 400);
        },

        async lookupCustomer() {
            this.lookingUpCustomer = true;
            try {
                const res = await fetch(`{{ route('pos.customer.lookup') }}?phone=${encodeURIComponent(this.customerPhone)}`, {
                    headers: { 'Accept': 'application/json' },
                });
                const data = await res.json();
                this.customerFound = data.found;
                this.customerPoints = data.points ?? 0;
                if (data.found) this.customerName = data.name ?? '';
                this.customerLookedUp = true;
            } catch (e) {
                // Tra cứu lỗi không chặn thanh toán — chỉ đơn giản không hiện thông tin khách.
            } finally {
                this.lookingUpCustomer = false;
            }
        },

        /**
         * Lưu đơn nháp — giữ chỗ khi khách chờ lâu, để bán tiếp cho người khác.
         * Không cần chọn loại đơn/thanh toán, không mở modal — bấm là lưu ngay.
         * Nếu đang sửa lại 1 đơn nháp có sẵn, CẬP NHẬT đơn đó thay vì tạo mới.
         */
        async saveDraft() {
            if (this.savingDraft || this.cart.length === 0) return;

            // Ghi chú ngắn để nhận biết đơn này là của ai (VD: "Khách áo đỏ",
            // "Bàn ngoài sân") — không bắt buộc, bấm Huỷ hộp thoại vẫn lưu bình
            // thường không kèm ghi chú.
            const note = window.prompt('Ghi chú để nhận biết đơn này (không bắt buộc):', '');
            if (note === null) return;

            this.savingDraft = true;

            const isUpdatingDraft = this.editingOrderId !== null && this.isDraft;
            const url = isUpdatingDraft ? `/don-hang/${this.editingOrderId}/luu-nhap` : '{{ route('pos.draft.store') }}';

            try {
                const res = await fetch(url, {
                    method: isUpdatingDraft ? 'PUT' : 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                        'Accept': 'application/json',
                    },
                    body: JSON.stringify({
                        items: this.cart.map(l => ({
                            variant_id: l.variant_id,
                            quantity: l.quantity,
                            modifier_ids: l.modifier_ids,
                            discount_type: l.discount_type,
                            discount_value: l.discount_value,
                        })),
                        note: note || null,
                    }),
                });

                if (!res.ok) {
                    const err = await res.json();
                    alert(err.message || 'Không lưu được đơn nháp, thử lại nhé.');
                    return;
                }

                // Quay về màn Order (không phải danh sách đơn) để tiếp tục
                // bán ngay cho khách tiếp theo — đúng mục đích của "lưu nháp".
                window.location.href = '{{ route('pos.order') }}';
            } catch (e) {
                alert('Không kết nối được, kiểm tra mạng và thử lại.');
            } finally {
                this.savingDraft = false;
            }
        },

        async submitOrder() {
            if (this.submitting || this.cart.length === 0) return;
            this.submitting = true;

            // Chế độ sửa đơn: gọi API cập nhật (PUT), không cần cash_received
            // vì tiền thối chỉ có ý nghĩa lúc thu tiền ban đầu.
            const isEditing = this.editingOrderId !== null;
            const url = isEditing ? `/don-hang/${this.editingOrderId}` : '{{ route('pos.checkout') }}';

            // Mở tab trống NGAY LÚC BẤM (còn trong "cử chỉ người dùng") rồi mới
            // điền địa chỉ hoá đơn sau khi biết order_id — mở tab SAU khi await
            // fetch xong rất dễ bị Safari/trình duyệt chặn popup. Chỉ mở nếu
            // quán có bật in hoá đơn trong Cài đặt.
            const receiptWindow = (!isEditing && this.receiptEnabled) ? window.open('', '_blank') : null;

            try {
                const res = await fetch(url, {
                    method: isEditing ? 'PUT' : 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                        'Accept': 'application/json',
                    },
                    body: JSON.stringify({
                        order_type: this.orderType,
                        guest_count: this.orderType === 'ngoi_lai' ? this.guestCount : null,
                        payment_method: this.paymentMethod,
                        cash_received: this.cashReceived,
                        discount_type: this.discountType,
                        discount_value: this.discountValue,
                        customer_phone: this.loyaltyEnabled && this.customerPhone ? this.customerPhone : null,
                        customer_name: this.loyaltyEnabled && this.customerName ? this.customerName : null,
                        redeem_points: this.loyaltyEnabled ? this.redeemPoints : null,
                        items: this.cart.map(l => ({
                            variant_id: l.variant_id,
                            quantity: l.quantity,
                            modifier_ids: l.modifier_ids,
                            discount_type: l.discount_type,
                            discount_value: l.discount_value,
                        })),
                    }),
                });

                if (!res.ok) {
                    receiptWindow?.close();
                    const err = await res.json();
                    alert(err.message || 'Có lỗi xảy ra, thử lại nhé.');
                    this.submitting = false;
                    return;
                }

                if (isEditing) {
                    window.location.href = '{{ route('pos.orders.index') }}';
                    return;
                }

                // Điền URL hoá đơn thật vào tab đã mở sẵn từ trước.
                const result = await res.json();
                if (result.order_id && receiptWindow) {
                    receiptWindow.location.href = `/don-hang/${result.order_id}/in`;
                }

                const orderTotal = this.finalTotal();
                const itemCount = this.cartCount;

                this.cart = [];
                this.checkoutOpen = false;
                this.cashReceived = null;
                this.guestCount = 1;
                this.acceptedBannerVisible = false;
                if (navigator.vibrate) navigator.vibrate([20, 40, 20]);
                this.showToast(`Đã tạo đơn ${itemCount} món — ${this.formatPrice(orderTotal)}đ`);
                // Cập nhật NGAY badge "Đơn hàng" trên menu, cùng lý do như ở
                // submitQuickOrder() — đơn mới vừa tạo (isEditing đã return ở
                // nhánh trên, tới đây chắc chắn là đơn MỚI, không phải sửa).
                Alpine.store('nav').orderBadgeCount++;
            } catch (e) {
                receiptWindow?.close();
                alert('Không kết nối được, kiểm tra mạng và thử lại.');
            } finally {
                this.submitting = false;
            }
        },
    };
}
</script>
@endsection
