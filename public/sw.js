// Cố tình KHÔNG cache request nào — Rót là app bán hàng, dữ liệu (giá, tồn
// kho, ca đang mở, đơn hàng...) luôn phải mới nhất, hiện dữ liệu cũ từ cache
// có thể khiến nhân viên bán sai giá hoặc sai thông tin ca. Service worker
// này chỉ tồn tại để trình duyệt (đặc biệt Android/Chrome) cho phép "Cài đặt
// ứng dụng" lên màn hình chính — yêu cầu bắt buộc phải có sẵn 1 fetch handler
// nào đó, dù không làm gì thêm ngoài chuyển tiếp y nguyên ra mạng.
self.addEventListener('install', () => {
    self.skipWaiting();
});

self.addEventListener('activate', (event) => {
    event.waitUntil(self.clients.claim());
});

self.addEventListener('fetch', (event) => {
    event.respondWith(fetch(event.request));
});
