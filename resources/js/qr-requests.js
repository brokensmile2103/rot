/**
 * Polling số yêu cầu gọi món từ khách qua QR đang chờ nhận (v1.1.3).
 *
 * Trước đây yêu cầu của khách chỉ hiện ra khi nhân viên tự mở/tải lại trang
 * "Đơn hàng" — khách gửi xong mà không ai hay biết. Module này hỏi server định
 * kỳ (GET /don-hang/yeu-cau/dang-cho, xem OrderController::pendingCustomerRequests)
 * rồi cập nhật số yêu cầu đang chờ vào Alpine.store('nav').pendingRequestCount, từ đó
 * các nơi sau TỰ hiện/ẩn/đổi số, không cần tải lại trang:
 *   - badge xanh ở menu "Đơn hàng" (nhường chỗ badge tổng đơn khi hết yêu cầu chờ),
 *   - thanh thông báo xanh ở đầu màn Order (không đè lên giỏ hàng đang thao tác),
 *   - tiêu đề tab trình duyệt, vd "(2) Đơn hàng · Rót".
 * Cố ý KHÔNG có toast/popup nổi và KHÔNG có âm thanh — tránh che khu vực đang bán.
 *
 * Cố ý dùng polling thay vì WebSocket/SSE: bản tự host chạy được trên shared
 * hosting/cPanel giá rẻ (không có tiến trình chạy nền, giới hạn số kết nối giữ
 * lâu), 1 request nhẹ mỗi ~10 giây là quá đủ cho nhịp gọi món của 1 xe cà phê.
 *
 * Tách thành hàm nhận `deps` (fetch, timers...) để kiểm thử độc lập, không phụ
 * thuộc trực tiếp vào window/document thật.
 */

export const POLL_MS = 10_000; // quán ĐANG bật đặt món qua QR
export const IDLE_POLL_MS = 60_000; // quán đang TẮT — hỏi thưa, phòng khi chủ quán vừa bật ở thiết bị khác
export const MAX_BACKOFF_MS = 120_000; // lỗi mạng/máy chủ liên tiếp: giãn dần, tối đa 2 phút
export const MIN_GAP_MS = 3_000; // chống hỏi dồn dập khi tab liên tục ẩn/hiện

export function createQrStore(Alpine, config = {}, deps = {}) {
    const d = {
        fetch: (...args) => window.fetch(...args),
        now: () => Date.now(),
        origin: () => window.location.origin,
        getTitle: () => document.title,
        setTitle: (t) => {
            document.title = t;
        },
        onVisible: (fn) => document.addEventListener('visibilitychange', () => document.visibilityState === 'visible' && fn()),
        onOnline: (fn) => window.addEventListener('online', fn),
        setTimeout: (fn, ms) => setTimeout(fn, ms),
        clearTimeout: (id) => clearTimeout(id),
        ...deps,
    };

    return {
        locationId: config.locationId ?? null,
        url: config.url ?? '',
        enabled: !!config.enabled,

        started: false,
        stopped: false,
        inFlight: false,
        failures: 0,
        lastPollAt: 0,
        baseTitle: '',
        pollTimer: null,

        start() {
            if (this.started || !this.locationId || !this.url) {
                return;
            }
            this.started = true;

            // Tiêu đề tab dạng "(2) Đơn hàng · Rót" — thấy được số yêu cầu chờ ngay
            // cả khi đang ở tab/cửa sổ khác.
            this.baseTitle = d.getTitle().replace(/^\(\d+\)\s*/, '');
            Alpine.effect(() => {
                const n = Alpine.store('nav').pendingRequestCount;
                d.setTitle((n > 0 ? `(${n}) ` : '') + this.baseTitle);
            });

            // Quay lại tab/ứng dụng (mở lại PWA từ nền, bật màn hình điện thoại...)
            // hoặc có mạng lại → hỏi NGAY thay vì đợi hết chu kỳ, vì trình duyệt
            // thường làm chậm/đóng băng timer của tab đang ẩn.
            d.onVisible(() => this.pollSoon());
            d.onOnline(() => this.pollSoon());

            this.poll();
        },

        schedule(delay) {
            d.clearTimeout(this.pollTimer);
            if (this.stopped) {
                return;
            }
            this.pollTimer = d.setTimeout(() => this.poll(), delay);
        },

        pollSoon() {
            if (this.stopped || this.inFlight || d.now() - this.lastPollAt < MIN_GAP_MS) {
                return;
            }
            d.clearTimeout(this.pollTimer);
            this.poll();
        },

        async poll() {
            if (this.stopped || this.inFlight) {
                return;
            }
            this.inFlight = true;
            this.lastPollAt = d.now();
            let nextDelay = this.enabled ? POLL_MS : IDLE_POLL_MS;

            try {
                const res = await d.fetch(new URL(this.url, d.origin()).toString(), {
                    headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                    credentials: 'same-origin',
                    cache: 'no-store',
                });

                if (res.status === 401 || res.status === 419) {
                    // Hết phiên đăng nhập — dừng hẳn, không hỏi tiếp vô ích. Lần
                    // thao tác tiếp theo của người dùng sẽ tự đưa về trang đăng nhập.
                    this.stopped = true;
                    return;
                }
                if (!res.ok) {
                    throw new Error(`HTTP ${res.status}`);
                }

                const data = await res.json();
                this.failures = 0;
                this.apply(data);
                nextDelay = this.enabled ? POLL_MS : IDLE_POLL_MS;
            } catch (e) {
                // Mất mạng/máy chủ lỗi thoáng qua: im lặng, giãn dần rồi thử lại.
                this.failures += 1;
                nextDelay = Math.min(POLL_MS * 2 ** this.failures, MAX_BACKOFF_MS);
            } finally {
                this.inFlight = false;
                this.schedule(nextDelay);
            }
        },

        apply(data) {
            Alpine.store('nav').pendingRequestCount = Number(data.pending_count) || 0;
            this.enabled = !!data.enabled;
        },
    };
}
