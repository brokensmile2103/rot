// Chạy: node --test tests/js/*.test.mjs
// Kiểm thử logic polling yêu cầu gọi món QR (resources/js/qr-requests.js) bằng
// Alpine/fetch/timers GIẢ — không cần trình duyệt, không cần cài thêm gói nào.
import { test } from 'node:test';
import assert from 'node:assert/strict';
import { createQrStore, POLL_MS, IDLE_POLL_MS, MAX_BACKOFF_MS, MIN_GAP_MS } from '../../resources/js/qr-requests.js';

function harness({ config = {}, initialTitle = 'Đơn hàng · Rót' } = {}) {
    const effects = [];
    const nav = new Proxy({ pendingRequestCount: 0 }, {
        set(t, k, v) { t[k] = v; effects.forEach((fn) => fn()); return true; },
    });
    const Alpine = { store: () => nav, effect: (fn) => { effects.push(fn); fn(); } };

    const timers = [];
    let now = 1_000_000;
    let title = initialTitle;
    const calls = { fetch: [] };
    const state = { responses: [] };

    const deps = {
        fetch: async (url) => {
            calls.fetch.push(url);
            const next = state.responses.shift();
            if (next instanceof Error) throw next;
            return next;
        },
        now: () => now,
        origin: () => 'https://rot.test',
        getTitle: () => title,
        setTitle: (t) => { title = t; },
        onVisible: (fn) => { state.onVisible = fn; },
        onOnline: (fn) => { state.onOnline = fn; },
        setTimeout: (fn, ms) => { const t = { fn, ms, active: true }; timers.push(t); return t; },
        clearTimeout: (t) => { if (t) t.active = false; },
    };

    const store = createQrStore(Alpine, { locationId: 7, url: '/don-hang/yeu-cau/dang-cho', enabled: true, ...config }, deps);
    const json = (data, status = 200) => ({ ok: status < 400, status, json: async () => data });
    const payload = (o = {}) => ({ enabled: true, pending_count: 2, ...o });
    const lastTimer = () => [...timers].reverse().find((t) => t.active);

    return { store, nav, calls, state, json, payload, lastTimer, getTitle: () => title, advance: (ms) => { now += ms; } };
}

test('không có locationId → start() không làm gì, không gọi server', () => {
    const h = harness({ config: { locationId: null } });
    h.store.start();
    assert.equal(h.calls.fetch.length, 0);
    assert.equal(h.store.started, false);
});

test('poll cập nhật số yêu cầu chờ, gọi đúng URL và đặt lịch lần sau', async () => {
    const h = harness();
    h.state.responses.push(h.json(h.payload({ pending_count: 3 })));
    await h.store.poll();
    assert.equal(h.calls.fetch[0], 'https://rot.test/don-hang/yeu-cau/dang-cho');
    assert.equal(h.nav.pendingRequestCount, 3);
    assert.equal(h.lastTimer().ms, POLL_MS);
});

test('xử lý hết yêu cầu → số chờ về 0', async () => {
    const h = harness();
    h.state.responses.push(h.json(h.payload({ pending_count: 2 })));
    await h.store.poll();
    h.state.responses.push(h.json(h.payload({ pending_count: 0 })));
    await h.store.poll();
    assert.equal(h.nav.pendingRequestCount, 0);
});

test('tiêu đề tab: có tiền tố "(n)" khi có yêu cầu chờ và gỡ khi hết', async () => {
    const h = harness();
    h.state.responses.push(h.json(h.payload({ pending_count: 2 })));
    h.store.start();
    await new Promise((r) => setImmediate(r));
    assert.equal(h.getTitle(), '(2) Đơn hàng · Rót');
    h.nav.pendingRequestCount = 0;
    assert.equal(h.getTitle(), 'Đơn hàng · Rót');
});

test('tiêu đề gốc đã có tiền tố cũ thì bỏ tiền tố trước khi ghép lại', () => {
    const h = harness({ initialTitle: '(3) Đơn hàng · Rót' });
    h.store.start();
    assert.equal(h.store.baseTitle, 'Đơn hàng · Rót');
    assert.equal(h.getTitle(), 'Đơn hàng · Rót'); // số chờ trong store là 0 nên không còn tiền tố
});

test('401/419 (hết phiên) → dừng hẳn, không đặt lịch poll nữa', async () => {
    const h = harness();
    h.state.responses.push(h.json({}, 401));
    await h.store.poll();
    assert.equal(h.store.stopped, true);
    assert.equal(h.lastTimer(), undefined);
});

test('lỗi mạng → giãn dần (x2 mỗi lần, tối đa 2 phút) và phục hồi khi có mạng lại', async () => {
    const h = harness();
    h.state.responses.push(new Error('offline'));
    await h.store.poll();
    assert.equal(h.lastTimer().ms, POLL_MS * 2);

    h.state.responses.push(h.json({}, 500));
    await h.store.poll();
    assert.equal(h.lastTimer().ms, POLL_MS * 4);

    for (let i = 0; i < 6; i++) { h.state.responses.push(new Error('x')); await h.store.poll(); }
    assert.equal(h.lastTimer().ms, MAX_BACKOFF_MS);

    h.state.responses.push(h.json(h.payload()));
    await h.store.poll();
    assert.equal(h.store.failures, 0);
    assert.equal(h.lastTimer().ms, POLL_MS);
});

test('quán tắt đặt món QR → hỏi thưa 60 giây/lần; khi bật ở thiết bị khác thì chuyển sang nhịp nhanh', async () => {
    const h = harness({ config: { enabled: false } });
    h.state.responses.push(h.json({ enabled: false, pending_count: 0 }));
    await h.store.poll();
    assert.equal(h.lastTimer().ms, IDLE_POLL_MS);

    h.state.responses.push(h.json(h.payload()));
    await h.store.poll();
    assert.equal(h.lastTimer().ms, POLL_MS);
    assert.equal(h.nav.pendingRequestCount, 2);
});

test('quay lại tab → hỏi ngay; nhưng bỏ qua khi vừa hỏi xong hoặc đang chờ phản hồi', async () => {
    const h = harness();
    h.state.responses.push(h.json(h.payload()));
    await h.store.poll();
    const before = h.calls.fetch.length;

    h.store.pollSoon(); // vừa hỏi <3s trước
    assert.equal(h.calls.fetch.length, before);

    h.advance(MIN_GAP_MS + 1);
    h.state.responses.push(h.json(h.payload({ pending_count: 5 })));
    h.store.pollSoon();
    await new Promise((r) => setImmediate(r));
    assert.equal(h.calls.fetch.length, before + 1);
    assert.equal(h.nav.pendingRequestCount, 5);
});

test('không còn toast/thông báo nổi nào trong store', () => {
    const h = harness();
    assert.equal('toast' in h.store, false);
    assert.equal(typeof h.store.notify, 'undefined');
});
