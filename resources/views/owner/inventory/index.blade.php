@extends('layouts.app')
@section('title', 'Kho nguyên liệu · Rót')
@section('page-title', 'Kho nguyên liệu')
@section('content')
<div x-data="inventoryApp()" class="p-4 pb-24 md:p-8 max-w-3xl mx-auto">
    <div class="flex items-center gap-3 mb-5 md:mb-6">
        <div class="w-10 h-10 rounded-xl bg-[var(--accent-light)] text-[var(--accent-text)] flex items-center justify-center shrink-0">
            <i class="fa-solid fa-boxes-stacked"></i>
        </div>
        <h2 class="text-lg font-bold text-neutral-900">Kho nguyên liệu — {{ $location->name }}</h2>
    </div>

    <details class="bg-white rounded-2xl p-4 border border-neutral-200 shadow-sm mb-5">
        <summary class="text-[var(--accent-text)] font-semibold cursor-pointer flex items-center gap-2">
            <i class="fa-solid fa-plus"></i>Thêm nguyên liệu mới
        </summary>
        <form method="POST" action="{{ route('owner.inventory.store') }}" class="mt-4 space-y-3">
            @csrf
            <x-input name="name" label="Tên nguyên liệu" icon="fa-flask" placeholder="VD: Cà phê hạt, Sữa đặc, Ly nhựa L" required autocomplete="off" />
            <x-input name="unit" label="Đơn vị" icon="fa-ruler" placeholder="VD: g, ml, cái" required autocomplete="off" />
            <x-input type="number" name="low_stock_threshold" label="Cảnh báo khi tồn kho dưới (không bắt buộc)" icon="fa-triangle-exclamation" value="0" step="0.01" autocomplete="off" />

            <div class="pt-2 border-t border-neutral-100">
                <p class="text-xs text-neutral-500 mb-2"><i class="fa-solid fa-circle-info mr-1"></i>Nhập luôn tồn kho ban đầu để có giá vốn ngay (không bắt buộc — có thể nhập kho sau)</p>
                <div class="grid grid-cols-2 gap-3">
                    <x-input type="number" step="0.01" name="initial_quantity" label="Số lượng có sẵn" icon="fa-boxes-stacked" autocomplete="off" />
                    <x-input type="text" inputmode="numeric" class="money-input" name="initial_cost" label="Tổng tiền đã mua (đ)" icon="fa-money-bill" autocomplete="off" />
                </div>
            </div>

            <x-button icon="fa-plus">Thêm nguyên liệu</x-button>
        </form>
    </details>

    @if($trashedIngredients->isNotEmpty())
        <details class="bg-neutral-50 rounded-2xl p-4 border border-neutral-200 mb-5">
            <summary class="text-neutral-500 font-medium cursor-pointer flex items-center gap-2 text-sm">
                <i class="fa-solid fa-trash-can"></i>Đã xoá ({{ $trashedIngredients->count() }}) — bấm để khôi phục
            </summary>
            <div class="mt-3 space-y-2">
                @foreach($trashedIngredients as $ti)
                    <div class="flex items-center justify-between bg-white rounded-lg px-3 py-2 border border-neutral-200">
                        <span class="text-sm text-neutral-600">{{ $ti->name }} ({{ $ti->unit }})</span>
                        <form method="POST" action="{{ route('owner.inventory.restore', $ti->id) }}">
                            @csrf
                            <button class="text-xs px-3 py-1 rounded-lg bg-emerald-600 hover:bg-emerald-700 text-white font-medium">Khôi phục</button>
                        </form>
                    </div>
                @endforeach
            </div>
        </details>
    @endif

    <div class="space-y-2">
        @forelse($ingredients as $ing)
            <div class="bg-white rounded-xl px-4 py-3.5 border {{ $ing->isLowStock() ? 'border-red-200' : 'border-neutral-200' }} shadow-sm">
                <div class="flex items-center justify-between">
                    <div class="min-w-0">
                        <div class="text-neutral-900 text-sm font-semibold flex items-center gap-2">
                            {{ $ing->name }}
                            <button @click="editFor = editFor === {{ $ing->id }} ? null : {{ $ing->id }}" class="text-neutral-300 hover:text-neutral-500"><i class="fa-solid fa-pen text-xs"></i></button>
                            @if($ing->isLowStock())
                                <span class="text-xs px-2 py-0.5 rounded-full bg-red-50 text-red-600 font-medium">
                                    <i class="fa-solid fa-triangle-exclamation"></i> Sắp hết
                                </span>
                            @endif
                        </div>
                        <div class="text-neutral-500 text-xs mt-0.5">
                            Tồn: <strong class="text-neutral-700">{{ quantity($ing->current_stock) }} {{ $ing->unit }}</strong>
                            · Giá vốn TB: <strong class="text-neutral-700">{{ money($ing->avg_cost_per_unit, 0) }}đ/{{ $ing->unit }}</strong>
                        </div>
                    </div>

                    <div class="flex items-center gap-1.5 shrink-0">
                        <button @click="toggleHistory({{ $ing->id }})"
                                class="text-xs px-3 py-1.5 rounded-lg bg-neutral-100 hover:bg-neutral-200 text-neutral-600 font-medium transition">
                            <i class="fa-solid fa-clock-rotate-left mr-1"></i>Lịch sử
                        </button>
                        <button @click="stockInFor = stockInFor === {{ $ing->id }} ? null : {{ $ing->id }}"
                                class="text-xs px-3 py-1.5 rounded-lg bg-[var(--accent)] hover:bg-[var(--accent-hover)] text-white font-medium transition">
                            <i class="fa-solid fa-truck-ramp-box mr-1"></i>Nhập kho
                        </button>
                        <form method="POST" action="{{ route('owner.inventory.delete', $ing->id) }}" onsubmit="return confirm('Xoá {{ $ing->name }}? (có thể khôi phục lại sau)')">
                            @csrf @method('DELETE')
                            <button class="text-xs px-2.5 py-1.5 rounded-lg bg-red-50 text-red-600"><i class="fa-solid fa-trash-can"></i></button>
                        </form>
                    </div>
                </div>

                {{-- Sửa nguyên liệu — CHO SỬA TRỰC TIẾP cả tồn kho + giá vốn TB, không
                     chỉ tên/đơn vị/ngưỡng cảnh báo. Cần thiết vì Thiết lập nhanh tạo dữ
                     liệu MẪU (giá tham khảo, không phải giá thật của quán), và "Nhập
                     kho" chỉ CỘNG THÊM chứ không sửa lại được số liệu nền đã sai. --}}
                <form x-show="editFor === {{ $ing->id }}" x-cloak method="POST" action="{{ route('owner.inventory.update', $ing->id) }}"
                      class="mt-3 pt-3 border-t border-neutral-100 space-y-2">
                    @csrf @method('PUT')
                    <div class="grid grid-cols-2 gap-2">
                        <input name="name" id="edit-ingredient-name-{{ $ing->id }}" autocomplete="off" required value="{{ $ing->name }}" placeholder="Tên nguyên liệu"
                               class="rounded-lg border border-neutral-300 py-2 px-2.5 text-xs">
                        <input name="unit" id="edit-ingredient-unit-{{ $ing->id }}" autocomplete="off" required value="{{ $ing->unit }}" placeholder="Đơn vị"
                               class="rounded-lg border border-neutral-300 py-2 px-2.5 text-xs">
                    </div>
                    <div class="grid grid-cols-2 gap-2">
                        <div>
                            <label for="edit-ingredient-stock-{{ $ing->id }}" class="block text-[11px] text-neutral-400 mb-1">Tồn kho ({{ $ing->unit }})</label>
                            <input type="number" step="0.01" min="0" name="current_stock" id="edit-ingredient-stock-{{ $ing->id }}" autocomplete="off" required value="{{ (float) $ing->current_stock }}"
                                   class="w-full rounded-lg border border-neutral-300 py-2 px-2.5 text-xs">
                        </div>
                        <div>
                            <label for="edit-ingredient-cost-{{ $ing->id }}" class="block text-[11px] text-neutral-400 mb-1">Giá vốn TB (đ/{{ $ing->unit }})</label>
                            <input type="number" step="0.0001" min="0" name="avg_cost_per_unit" id="edit-ingredient-cost-{{ $ing->id }}" autocomplete="off" required value="{{ (float) $ing->avg_cost_per_unit }}"
                                   class="w-full rounded-lg border border-neutral-300 py-2 px-2.5 text-xs">
                        </div>
                    </div>
                    <div>
                        <label for="edit-ingredient-threshold-{{ $ing->id }}" class="block text-[11px] text-neutral-400 mb-1">Cảnh báo khi tồn kho dưới</label>
                        <input type="number" step="0.01" min="0" name="low_stock_threshold" id="edit-ingredient-threshold-{{ $ing->id }}" autocomplete="off" value="{{ (float) $ing->low_stock_threshold }}"
                               class="w-full rounded-lg border border-neutral-300 py-2 px-2.5 text-xs">
                    </div>
                    <p class="text-[11px] text-amber-700 bg-amber-50 rounded-lg px-2.5 py-2 leading-relaxed">
                        <i class="fa-solid fa-circle-info mr-1"></i>Sửa "Tồn kho"/"Giá vốn TB" tại đây KHÔNG tạo dòng trong Lịch sử nhập kho — chỉ dùng để sửa lại số liệu ban đầu bị sai hoặc khớp lại theo kiểm kê thực tế. Mua hàng thật thì dùng "Nhập kho".
                    </p>
                    <button class="w-full bg-[var(--accent)] hover:bg-[var(--accent-hover)] text-white rounded-lg py-2 text-xs font-semibold transition">Lưu thay đổi</button>
                </form>

                {{-- Lịch sử nhập kho — tải LƯỜI qua fetch() ngay khi mở panel
                     (xem toggleHistory() ở x-data phía trên), không còn được
                     Blade render sẵn cho MỌI nguyên liệu ngay từ đầu. Mới nhất
                     lên đầu, kèm giá vốn quy đổi mỗi lần (tổng tiền ÷ số
                     lượng) để thấy rõ giá mua thay đổi qua từng lần, dù
                     avg_cost_per_unit hiển thị ở trên luôn là bình quân gia
                     quyền. --}}
                <div x-show="historyFor === {{ $ing->id }}" x-cloak class="mt-3 pt-3 border-t border-neutral-100">
                    <template x-if="!stockInHistory[{{ $ing->id }}] || (stockInHistory[{{ $ing->id }}].loading && stockInHistory[{{ $ing->id }}].items.length === 0)">
                        <p class="text-xs text-neutral-400 text-center py-3"><i class="fa-solid fa-spinner fa-spin mr-1"></i>Đang tải...</p>
                    </template>
                    <template x-if="stockInHistory[{{ $ing->id }}] && !stockInHistory[{{ $ing->id }}].loading && stockInHistory[{{ $ing->id }}].items.length === 0">
                        <p class="text-xs text-neutral-400 text-center py-3">Chưa có lần nhập kho nào.</p>
                    </template>
                    <template x-if="stockInHistory[{{ $ing->id }}]?.items.length > 0">
                        <div class="max-h-64 overflow-y-auto scrollbar-hide space-y-1.5 pr-1">
                            <template x-for="stockIn in stockInHistory[{{ $ing->id }}].items" :key="stockIn.id">
                                <div class="flex items-center justify-between bg-neutral-50 rounded-lg px-3 py-2 text-xs">
                                    <div class="min-w-0">
                                        <div class="text-neutral-700 font-medium">
                                            <span x-text="'+' + stockIn.quantity"></span>
                                            <span class="text-neutral-400 font-normal" x-text="'· ' + stockIn.total_cost + ' (' + stockIn.unit_cost + ')'"></span>
                                        </div>
                                        <div class="text-neutral-400 mt-0.5 truncate">
                                            <span x-text="stockIn.created_at"></span> · <span x-text="stockIn.creator_name"></span>
                                            <template x-if="stockIn.note"><span x-text="' · ' + stockIn.note"></span></template>
                                        </div>
                                    </div>
                                </div>
                            </template>
                            <button x-show="stockInHistory[{{ $ing->id }}].nextPage" x-cloak
                                    @click="loadStockInHistory({{ $ing->id }}, stockInHistory[{{ $ing->id }}].nextPage)"
                                    :disabled="stockInHistory[{{ $ing->id }}].loading"
                                    class="w-full text-center text-xs text-neutral-500 hover:text-neutral-700 font-medium py-2 disabled:opacity-50">
                                <span x-show="!stockInHistory[{{ $ing->id }}].loading">Xem thêm</span>
                                <span x-show="stockInHistory[{{ $ing->id }}].loading"><i class="fa-solid fa-spinner fa-spin"></i></span>
                            </button>
                        </div>
                    </template>
                </div>

                <form x-show="stockInFor === {{ $ing->id }}" x-cloak method="POST" action="{{ route('owner.inventory.stock-in', $ing->id) }}"
                      class="grid grid-cols-2 gap-2 mt-3 pt-3 border-t border-neutral-100">
                    @csrf
                    <input type="number" step="0.01" name="quantity" id="stock-in-qty-{{ $ing->id }}" autocomplete="off" required placeholder="Số lượng ({{ $ing->unit }})"
                           class="rounded-xl border border-neutral-300 bg-white text-neutral-900 placeholder-neutral-400 py-2 px-3 text-sm focus:border-[var(--accent-ring)] focus:ring-1 focus:ring-[var(--accent-ring)] focus:outline-none">
                    <input type="text" inputmode="numeric" name="total_cost" id="stock-in-cost-{{ $ing->id }}" autocomplete="off" required placeholder="Tổng tiền (đ)"
                           class="money-input rounded-xl border border-neutral-300 bg-white text-neutral-900 placeholder-neutral-400 py-2 px-3 text-sm focus:border-[var(--accent-ring)] focus:ring-1 focus:ring-[var(--accent-ring)] focus:outline-none">
                    <input type="text" name="note" id="stock-in-note-{{ $ing->id }}" autocomplete="off" placeholder="Ghi chú (không bắt buộc)"
                           class="col-span-2 rounded-xl border border-neutral-300 bg-white text-neutral-900 placeholder-neutral-400 py-2 px-3 text-sm focus:border-[var(--accent-ring)] focus:ring-1 focus:ring-[var(--accent-ring)] focus:outline-none">
                    <button class="col-span-2 bg-neutral-900 hover:bg-neutral-800 text-white rounded-xl py-2 text-sm font-medium transition">Xác nhận nhập kho</button>
                </form>
            </div>
        @empty
            <div class="text-center py-10 text-neutral-400">
                <i class="fa-solid fa-boxes-stacked text-3xl mb-2 block"></i>
                <p class="text-sm">Chưa có nguyên liệu nào. Thêm nguyên liệu rồi gán vào công thức món trong mục Thực đơn.</p>
            </div>
        @endforelse
    </div>
    @if($ingredients->hasPages())
        <div class="mt-4">{{ $ingredients->links() }}</div>
    @endif
</div>

<script>
/**
 * Giữ trạng thái đóng/mở các panel (sửa/nhập kho/lịch sử) của TỪNG dòng
 * nguyên liệu, và tải LƯỜI (lazy) lịch sử nhập kho khi panel "Lịch sử"
 * được mở lần đầu — xem InventoryController::stockInHistory(). Cache lại
 * theo id nguyên liệu để đóng/mở lại KHÔNG gọi lại API thêm lần nữa.
 */
function inventoryApp() {
    return {
        stockInFor: null,
        editFor: null,
        historyFor: null,
        // { [ingredientId]: { items: [], nextPage: n|null, loading: bool } }
        stockInHistory: {},

        toggleHistory(ingredientId) {
            this.historyFor = this.historyFor === ingredientId ? null : ingredientId;
            if (this.historyFor && !this.stockInHistory[ingredientId]) {
                this.loadStockInHistory(ingredientId);
            }
        },

        async loadStockInHistory(ingredientId, page = 1) {
            if (!this.stockInHistory[ingredientId]) {
                this.stockInHistory[ingredientId] = { items: [], nextPage: 1, loading: false };
            }
            const state = this.stockInHistory[ingredientId];
            if (state.loading) return;
            state.loading = true;

            try {
                const url = '/quan-ly/kho/' + ingredientId + '/lich-su-nhap?page=' + page;
                const res = await fetch(url, {
                    headers: { 'Accept': 'application/json' },
                });
                const data = await res.json();
                state.items = page === 1 ? data.items : state.items.concat(data.items);
                state.nextPage = data.next_page;
            } catch (e) {
                // Lỗi tải lịch sử không chặn các thao tác khác trên trang.
            } finally {
                state.loading = false;
            }
        },
    };
}
</script>
@endsection
