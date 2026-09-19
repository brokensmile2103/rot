import Alpine from 'alpinejs';

window.Alpine = Alpine;

/**
 * State + logic kéo-giãn chiều rộng cho các panel dạng sidebar (Sidebar quản lý,
 * panel Giỏ hàng...). Nhớ lại chiều rộng đã chọn qua localStorage, không cần
 * chọn lại mỗi lần mở app.
 *
 * direction: 'right'  = tay cầm nằm bên PHẢI panel, kéo sang phải để MỞ RỘNG
 *                        (dùng cho panel bên trái màn hình, VD: sidebar)
 *            'left'   = tay cầm nằm bên TRÁI panel, kéo sang trái để MỞ RỘNG
 *                        (dùng cho panel bên phải màn hình, VD: giỏ hàng)
 */
window.resizablePanel = function (storageKey, defaultWidth, min, max, direction = 'right') {
    return {
        width: parseInt(localStorage.getItem(storageKey)) || defaultWidth,
        resizing: false,

        startResize(e) {
            e.preventDefault();
            this.resizing = true;

            const getX = (ev) => (ev.touches ? ev.touches[0].clientX : ev.clientX);
            const startX = getX(e);
            const startWidth = this.width;

            const onMove = (ev) => {
                const delta = getX(ev) - startX;
                const signedDelta = direction === 'right' ? delta : -delta;
                this.width = Math.min(max, Math.max(min, startWidth + signedDelta));
            };

            const onEnd = () => {
                this.resizing = false;
                localStorage.setItem(storageKey, this.width);
                document.removeEventListener('mousemove', onMove);
                document.removeEventListener('mouseup', onEnd);
                document.removeEventListener('touchmove', onMove);
                document.removeEventListener('touchend', onEnd);
            };

            document.addEventListener('mousemove', onMove);
            document.addEventListener('mouseup', onEnd);
            document.addEventListener('touchmove', onMove, { passive: false });
            document.addEventListener('touchend', onEnd);
        },
    };
};

/**
 * Store dùng chung cho các badge số trên menu điều hướng (layouts/app —
 * xem components/nav-badge.blade.php). Khởi tạo giá trị ban đầu từ
 * window.__navBadgeCounts (in ra bởi layout, lấy từ view composer ở
 * AppServiceProvider) rồi TỰ CẬP NHẬT ngay tại client sau khi lên đơn
 * thành công (xem posApp() trong pos/order.blade.php) — không cần tải lại
 * trang, vì lên đơn ở màn Order đi qua fetch(), không có điều hướng nào để
 * server tính lại composer.
 */
Alpine.store('nav', {
    orderBadgeCount: window.__navBadgeCounts?.order ?? 0,
    lowStockBadgeCount: window.__navBadgeCounts?.lowStock ?? 0,
});

Alpine.start();

/**
 * Tự động định dạng phân cách nghìn (kiểu Việt Nam: dấu chấm) cho các ô nhập
 * tiền VNĐ khi đang gõ — CHỈ ảnh hưởng hiển thị, giá trị gửi lên server khi
 * submit form luôn là số sạch không dấu chấm (xử lý ở sự kiện 'submit' của
 * form cha, KHÔNG cần đổi gì ở phía backend).
 *
 * Cách dùng: thêm class "money-input" + đổi type="number" thành type="text"
 * inputmode="numeric" cho input tiền VNĐ (số lớn, không có phần thập phân
 * lẻ) — KHÔNG dùng cho số lượng/định lượng có thể có số thập phân (VD: định
 * lượng nguyên liệu 0.5g) vì việc format sẽ làm mất phần thập phân đó.
 */
function initMoneyInputs() {
    document.querySelectorAll('.money-input').forEach((input) => {
        if (input.dataset.moneyFormatted) return; // tránh gắn listener 2 lần
        input.dataset.moneyFormatted = '1';

        const format = () => {
            const cursorFromEnd = input.value.length - input.selectionStart;
            const digits = input.value.replace(/\D/g, '');
            input.value = digits ? Number(digits).toLocaleString('vi-VN') : '';
            const newPos = Math.max(0, input.value.length - cursorFromEnd);
            input.setSelectionRange(newPos, newPos);
        };

        // Định dạng sẵn giá trị ban đầu (nếu form hiện giá trị cũ khi sửa).
        if (input.value) {
            input.value = Number(input.value.replace(/\D/g, '')).toLocaleString('vi-VN');
        }

        input.addEventListener('input', format);

        // Trước khi form submit, trả input này về số sạch (bỏ dấu chấm) để
        // server nhận đúng giá trị số — KHÔNG đổi input.value hiển thị mãi
        // mãi, chỉ đổi đúng khoảnh khắc ngay trước khi gửi đi.
        const form = input.closest('form');
        if (form && !form.dataset.moneyCleanupBound) {
            form.dataset.moneyCleanupBound = '1';
            form.addEventListener('submit', () => {
                form.querySelectorAll('.money-input').forEach((el) => {
                    el.value = el.value.replace(/\D/g, '');
                });
            });
        }
    });
}

document.addEventListener('DOMContentLoaded', initMoneyInputs);
// Gọi lại sau khi Alpine render xong các khối động (x-show, x-for...) — các
// input tiền nằm trong đó chỉ thực sự có trong DOM sau khi Alpine khởi tạo.
document.addEventListener('alpine:initialized', initMoneyInputs);
