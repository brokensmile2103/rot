@extends('layouts.app')
@section('title', 'Pha chế · Rót')
@section('page-title', 'Hàng chờ pha chế')
@section('content')
<div x-data="prepQueue(@js(route('pos.prep.data', [], false)))" class="p-4 pb-24 md:p-8 max-w-5xl mx-auto">
    <div class="flex items-center gap-3 mb-5">
        <div class="w-10 h-10 rounded-xl bg-[var(--accent-light)] text-[var(--accent-text)] flex items-center justify-center shrink-0">
            <i class="fa-solid fa-list-check"></i>
        </div>
        <div class="min-w-0">
            <h2 class="text-lg font-bold text-neutral-900">Hàng chờ pha chế</h2>
            <p class="text-xs text-neutral-500">Đơn cũ nhất ở đầu. Bấm <strong>Xong</strong> khi làm xong để gọi số khách. Tự cập nhật mỗi vài giây.</p>
        </div>
        <span class="ml-auto shrink-0 text-xs font-bold px-3 py-1.5 rounded-full bg-[var(--accent)] text-white" x-show="waiting.length > 0" x-cloak>
            <span x-text="waiting.length"></span> đơn chờ
        </span>
    </div>

    <template x-if="loaded && !enabled">
        <div class="bg-amber-50 border border-amber-200 text-amber-800 text-sm rounded-xl px-4 py-3 mb-5">
            Hàng chờ pha chế đang <strong>tắt</strong> — đơn mới sẽ không vào đây.
            @if(auth()->user()?->isOwner())
                Bật ở <a href="{{ route('owner.settings.index') }}" class="underline font-semibold">Cài đặt → Hàng chờ pha chế &amp; gọi số</a>.
            @else
                Nhờ chủ quán bật trong Cài đặt.
            @endif
        </div>
    </template>

    <template x-if="loaded && waiting.length === 0">
        <div class="text-center py-14 text-neutral-400">
            <i class="fa-solid fa-mug-hot text-3xl mb-2 block"></i>
            <p class="text-sm">Không có đơn nào đang chờ.</p>
        </div>
    </template>

    <div class="grid sm:grid-cols-2 lg:grid-cols-3 gap-3">
        <template x-for="order in waiting" :key="order.id">
            <div class="bg-white rounded-2xl border-2 shadow-sm p-4 flex flex-col"
                 :class="order.waiting_minutes >= 10 ? 'border-red-300' : (order.waiting_minutes >= 5 ? 'border-amber-300' : 'border-neutral-200')">
                <div class="flex items-center gap-2 mb-3">
                    <span class="text-3xl font-extrabold text-neutral-900" x-text="'#' + (order.number ?? order.id)"></span>
                    <span class="text-xs px-2 py-0.5 rounded-full bg-neutral-100 text-neutral-600 font-medium" x-text="order.order_type === 'ngoi_lai' ? 'Ngồi lại' : 'Mang đi'"></span>
                    <span class="ml-auto text-xs font-semibold"
                          :class="order.waiting_minutes >= 10 ? 'text-red-600' : (order.waiting_minutes >= 5 ? 'text-amber-600' : 'text-neutral-400')"
                          x-text="order.completed_at + ' · ' + order.waiting_minutes + ' phút'"></span>
                </div>
                <ul class="space-y-1.5 flex-1">
                    <template x-for="(item, idx) in order.items" :key="idx">
                        <li class="text-sm">
                            <span class="font-bold text-[var(--accent-text)]" x-text="item.quantity + '×'"></span>
                            <span class="font-semibold text-neutral-900" x-text="item.name"></span>
                            <template x-if="item.modifiers.length">
                                <div class="text-xs text-neutral-500 ml-5" x-text="item.modifiers.join(', ')"></div>
                            </template>
                        </li>
                    </template>
                </ul>
                <p x-show="order.note" class="text-xs italic text-neutral-500 mt-2" x-text="'“' + order.note + '”'"></p>
                <button @click="markDone(order)" :disabled="busyId === order.id"
                        class="mt-4 w-full bg-emerald-600 hover:bg-emerald-700 text-white rounded-xl py-3 font-bold flex items-center justify-center gap-2 transition disabled:opacity-50">
                    <i class="fa-solid" :class="busyId === order.id ? 'fa-spinner fa-spin' : 'fa-bell'"></i>Xong — gọi số
                </button>
            </div>
        </template>
    </div>

    <template x-if="done.length > 0">
        <div class="mt-8">
            <div class="text-xs font-bold text-neutral-500 uppercase tracking-wider mb-2">Vừa xong (15 phút gần nhất)</div>
            <div class="flex flex-wrap gap-2">
                <template x-for="order in done" :key="order.id">
                    <button @click="undo(order)" title="Bấm để đưa lại vào hàng chờ"
                            class="px-3 py-2 rounded-xl bg-emerald-50 border border-emerald-200 text-emerald-800 text-sm font-bold hover:bg-emerald-100 transition">
                        <span x-text="'#' + (order.number ?? order.id)"></span>
                        <i class="fa-solid fa-rotate-left text-xs ml-1 text-emerald-500"></i>
                    </button>
                </template>
            </div>
        </div>
    </template>
</div>

<script>
function prepQueue(dataUrl) {
    return {
        enabled: true,
        loaded: false,
        waiting: [],
        done: [],
        busyId: null,
        timer: null,

        init() {
            this.refresh();
            this.timer = setInterval(() => { if (!document.hidden) this.refresh(); }, 8000);
        },

        async refresh() {
            try {
                const res = await fetch(dataUrl, { headers: { 'Accept': 'application/json' } });
                if (!res.ok) return;
                const data = await res.json();
                const before = new Set(this.waiting.map(o => o.id));
                this.enabled = data.enabled;
                this.waiting = data.waiting;
                this.done = data.done;
                // Rung nhẹ khi có đơn MỚI vào hàng chờ (không phát âm thanh — xe bán ngoài trời ồn).
                if (this.loaded && data.waiting.some(o => !before.has(o.id)) && navigator.vibrate) navigator.vibrate(30);
                this.loaded = true;
            } catch (e) {
                // Mất mạng tạm thời — giữ nguyên danh sách đang hiện, lần sau thử lại.
            }
        },

        async post(url) {
            const res = await fetch(url, {
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                    'Accept': 'application/json',
                },
            });
            return res.ok;
        },

        async markDone(order) {
            if (this.busyId) return;
            this.busyId = order.id;
            try {
                await this.post(`/pha-che/${order.id}/xong`);
                await this.refresh();
            } finally {
                this.busyId = null;
            }
        },

        async undo(order) {
            if (!confirm(`Đưa đơn #${order.number ?? order.id} trở lại hàng chờ?`)) return;
            await this.post(`/pha-che/${order.id}/hoan-tac`);
            await this.refresh();
        },
    };
}
</script>
@endsection
