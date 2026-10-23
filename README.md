# Rót — Phần mềm quản lý bán hàng cho xe/quầy cà phê (tự host, multi-tenant)

Bán hàng một tay, tối ưu cho thao tác nhanh, giao diện sáng dễ đọc ngoài trời. Dành cho xe/quầy cà phê nhỏ lẻ, quán ăn nhỏ, tiệm làm đẹp. Một lần cài đặt phục vụ được nhiều quán: mỗi quán tự đăng ký tài khoản, dữ liệu tách riêng hoàn toàn.

## Tính năng
- **Bán hàng**: lên đơn một chạm, khách tự gọi món qua QR (menu công khai), số thứ tự đơn trong ngày + màn **Pha chế** (hàng chờ, bấm Xong để gọi số).
- **Thanh toán**: tiền mặt, chuyển khoản (mã VietQR, nội dung chuyển khoản tuỳ chỉnh hoặc để trống) hoặc kết hợp; **Chốt ca** có bộ đếm theo mệnh giá, tổng kết theo hình thức thanh toán, cảnh báo đơn nháp.
- **Kho**: nguyên liệu trừ theo công thức món, cảnh báo "Còn N / Hết nguyên liệu" ngay trên màn Order, gợi ý nhập hàng, kiểm kê hàng loạt, giá vốn bình quân.
- **Menu**: danh mục, món, biến thể, nhóm tuỳ chọn; thiết lập nhanh có dữ liệu mẫu cho cà phê, quán ăn nhỏ, tiệm tóc.
- **Báo cáo & sổ sách**: doanh thu, số đơn, trung bình/đơn, theo hình thức thanh toán và người bán; sổ doanh thu S1a cho hộ kinh doanh; xuất dữ liệu.
- **Phân quyền**: Super Admin (đồng thời là chủ quán đầu tiên, dùng được mọi tính năng bán hàng như tài khoản thường) · Chủ quán · Nhân viên. Nhiều quán/xe dưới một tài khoản chủ.
- **Super Admin** `/sieu-quan-tri`: danh sách quán, người dùng, khoá/mở quán và tài khoản, đặt lại mật khẩu, sửa 3 trang tĩnh Giới thiệu / Điều khoản / Chính sách bảo mật (lưu trong database, không cần sửa code). **Đóng/mở đăng ký công khai** tại `/sieu-quan-tri/cai-dat`: tắt đi thì trang đăng ký bị vô hiệu hoá và đăng nhập Google chỉ dành cho tài khoản đã có (dùng khi bạn chỉ tự dùng hoặc dùng trong nhóm nhỏ).
- **Đăng ký công khai** `/dang-ky` (số điện thoại hoặc Google), chống bot bằng honeypot + token HMAC, không cần reCAPTCHA.

## Kiến trúc dữ liệu
Không có bảng "tenant" riêng — mỗi **Location** (`locations`) chính là một tenant, dữ liệu được phân vùng theo `location_id` xuyên suốt hệ thống. `locations.is_active` được kiểm tra khi đăng nhập: quán bị khoá thì chủ quán/nhân viên không vào được.

## Chạy local
```bash
composer install
npm install && npm run build
cp .env.example .env              # sửa APP_URL, DB_*
php artisan key:generate
php artisan migrate
php artisan serve                 # mở http://localhost:8000/install để tạo Super Admin (cũng là chủ quán đầu tiên)
```
Yêu cầu PHP 8.3+, MySQL/MariaDB, Composer, Node.js.

Trình cài đặt `/install` chỉ chạy một lần: kiểm tra môi trường → kết nối database → tạo tài khoản Super Admin kèm quán đầu tiên, dùng được ngay. Người khác tự đăng ký qua `/dang-ky` — nếu chỉ tự dùng, vào **Super Admin → Cài đặt nền tảng** và tắt đăng ký.

## Triển khai production
- Document root trỏ vào `public/`.
- `.env`: `APP_ENV=production`, `APP_DEBUG=false`, `APP_URL=https://tên-miền-của-bạn`, `SESSION_SECURE_COOKIE=true` khi có HTTPS. **Không** commit `.env` hay khoá API thật vào git.
- Quên mật khẩu qua email dùng Resend: điền `RESEND_API_KEY` và `MAIL_FROM_ADDRESS` (địa chỉ thuộc tên miền bạn đã xác minh).
- Đăng nhập Google (tuỳ chọn): tạo OAuth client loại *Web application* tại Google Cloud Console, thêm redirect URI `https://example.com/auth/google/callback` (thay bằng tên miền của bạn), rồi điền `GOOGLE_CLIENT_ID`, `GOOGLE_CLIENT_SECRET`, `GOOGLE_REDIRECT_URI` trong `.env`.

### Cập nhật lên bản mới
Chép code mới lên (giữ nguyên `.env` và `storage/`), chạy `php artisan migrate --force` rồi `npm run build`. Dữ liệu cũ được giữ nguyên.

## Lưu ý khi vận hành công khai
- Nội dung **Điều khoản sử dụng** và **Chính sách bảo mật** seed sẵn trong migration chỉ là khung tham khảo, không phải văn bản pháp lý hoàn chỉnh. Hãy nhờ chuyên gia pháp lý rà soát và sửa trong giao diện Super Admin trước khi mở cho người dùng thật.
- Nếu không muốn ai tự đăng ký, tắt đăng ký trong Cài đặt nền tảng của Super Admin.
- `/dang-ky` hiện chưa xác minh số điện thoại/email và chưa giới hạn tốc độ đăng ký ngoài lớp chống bot; cân nhắc bổ sung nếu mở rộng quy mô.
- Mọi quán dùng đầy đủ tính năng, chưa có gói cước hay giới hạn.

## Kiểm thử
`php artisan test` (SQLite, unit test). Bộ test luồng bán hàng `tests/Feature/PosFlowTest.php` và quét trang `tests/Feature/PageCrawlTest.php` cần MySQL/MariaDB với một database trống riêng cho test: `DB_CONNECTION=mysql DB_DATABASE=rot_test ... php artisan test`.
