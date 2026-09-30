# ☕ Rót v1.2.0 — Phần mềm quản lý xe/quầy cà phê

**Bán hàng · Kho nguyên liệu · Ca làm việc · Báo cáo lợi nhuận · Sổ doanh thu — trong 1 màn hình duy nhất**

![PHP](https://img.shields.io/badge/PHP-%3E%3D8.3-777BB4?logo=php&logoColor=white)
![Laravel](https://img.shields.io/badge/Laravel-13-FF2D20?logo=laravel&logoColor=white)
![Tailwind CSS](https://img.shields.io/badge/Tailwind_CSS-38BDF8?logo=tailwindcss&logoColor=white)
![Alpine.js](https://img.shields.io/badge/Alpine.js-8BC0D0?logo=alpinedotjs&logoColor=white)

[Tính năng](#-tính-năng-nổi-bật) · [Cài đặt](#-cài-đặt) · [Rót Cloud](#-không-muốn-tự-vận-hành-server-dùng-rót-cloud) · [Đổi phiên bản](#nâng-cấp-lên-bản-mới)

---

Rót là phần mềm quản lý bán hàng dành riêng cho xe/quầy cà phê, xe nước, quán nước nhỏ và vừa: bán hàng, ca làm việc, sổ quỹ, thực đơn, kho nguyên liệu, nhân viên, báo cáo giá vốn/lợi nhuận và sổ doanh thu cho hộ kinh doanh — tất cả trong một giao diện gọn nhẹ, dùng tốt trên điện thoại ngay tại quầy.

Đây là bản mã nguồn dành cho hình thức **self-hosted** — bạn tự cài đặt và vận hành trên hosting/VPS của riêng mình, toàn quyền kiểm soát dữ liệu. Nếu không muốn tự lo phần hạ tầng, xem [Rót Cloud](#-không-muốn-tự-vận-hành-server-dùng-rót-cloud) bên dưới.

## 🚀 Không muốn tự vận hành server? Dùng Rót Cloud

Nếu bạn không rành kỹ thuật, không muốn tự thuê VPS, tự cấu hình LEMP stack, tự lo backup và cập nhật — **[Rót Cloud](http://rot.inithtml.com/)** là bản đầy đủ tính năng của Rót, vận hành sẵn cho bạn:

- **Dùng ngay, không cài đặt** — đăng ký là bán hàng được, không đụng tới dòng lệnh nào.
- **Không lo hạ tầng** — máy chủ, backup, HTTPS, cập nhật phiên bản mới đều do đội ngũ Rót lo, quán chỉ việc bán hàng.
- **Quản lý nhiều xe/chi nhánh trong 1 tài khoản** — báo cáo tổng hợp doanh thu, giá vốn, lợi nhuận của toàn bộ hệ thống, không phải cộng tay từng xe.
- **Luôn là bản mới nhất** — mọi tính năng và bản vá đều tự động có ngay khi phát hành, không cần tự `git pull` rồi build lại.
- **Dữ liệu vẫn luôn là của bạn** — có sẵn tính năng xuất toàn bộ dữ liệu quán ra file bất cứ lúc nào, không sợ bị khoá.

> Bản self-hosted trong repo này phù hợp nếu bạn muốn tự kiểm soát 100% hạ tầng và dữ liệu, hoặc cần tuỳ biến sâu vào mã nguồn. Với đa số quán muốn vận hành nhanh, ít lo kỹ thuật, **Rót Cloud** là lựa chọn tiết kiệm thời gian hơn nhiều.

## ✨ Tính năng nổi bật

- **Bán hàng 1 màn hình** — giỏ hàng, size/topping tuỳ chọn, giữ đơn nháp cho khách đang chờ, "Lên đơn nhanh" cho món quen thuộc chỉ 1 chạm.
- **Kho nguyên liệu & giá vốn tự động** — giá vốn mỗi món tính theo đúng công thức pha chế, cập nhật tự động theo phương pháp **bình quân gia quyền** mỗi lần nhập kho — không cần tự nhớ giá nhập gần nhất. Mọi lần sửa tay tồn kho/giá vốn đều được ghi vào **nhật ký điều chỉnh kho** (số trước/sau, lý do, người sửa, thời điểm) kèm tổng giá trị thiếu hụt/dư ước tính.
- **Chốt ca minh bạch** — tự tính tiền mặt lý thuyết trong ca, đối chiếu với tiền đếm thực tế, báo ngay khớp/dư/thiếu quỹ; sổ quỹ ghi lại đầy đủ mọi khoản chi/nạp/rút.
- **Báo cáo lợi nhuận thực** — doanh thu là **số tiền thực nhận** (đã trừ giảm giá món, giảm giá cả đơn và điểm đổi, khớp đúng số Chốt ca), trừ giá vốn, lương nhân viên và chi phí mặt bằng để ra lợi nhuận thực tế.
- **Giờ cao điểm** — bảng nhiệt giờ × thứ trong tuần (xem 4/8/13 tuần, theo số đơn hoặc doanh thu) cho biết khung giờ, ngày nào đông khách nhất để xếp ca và chuẩn bị nguyên liệu.
- **Sổ doanh thu & ngưỡng thuế** — tự lập Sổ doanh thu bán hàng hóa, dịch vụ (mẫu S1a-HKD) cho hộ kinh doanh, in/lưu PDF hoặc tải CSV; nhập thêm doanh thu bán ngoài Rót; theo dõi doanh thu năm (cộng dồn mọi xe) so với ngưỡng miễn thuế kèm dự báo ngày chạm ngưỡng. Xem [chi tiết bên dưới](#-sổ-doanh-thu--ngưỡng-thuế).
- **Menu Engineering** — tự phân loại món theo mô hình Ngôi sao/Bò kéo/Câu đố/Chó (Kasavana & Smith), biết ngay món nào nên đẩy mạnh, món nào nên xem lại.
- **Dự đoán doanh thu** — ước tính doanh thu tuần/tháng tới dựa trên xu hướng và mùa vụ theo ngày trong tuần, có khoảng tin cậy rõ ràng, từ chối dự đoán nếu dữ liệu chưa đủ tin cậy.
- **Khách hàng thân thiết** — tích điểm/đổi điểm theo đúng số tiền thực trả, tự hoàn tác chính xác khi huỷ/sửa đơn.
- **Đặt món qua QR** — khách quét mã tại xe, tự chọn món và gửi yêu cầu; nhân viên nhận là lên thẳng màn Order để kiểm tra và tính tiền. Yêu cầu mới tự hiện sau khoảng 10 giây (huy hiệu xanh ở mục Đơn hàng, thanh thông báo ở màn Order, số chờ trên tiêu đề tab) — không cần tải lại trang, không popup, không âm thanh.
- **Hoá đơn điện tử & VietQR** — kết nối SePay eInvoice để xuất hoá đơn điện tử, và tạo mã VietQR nhận chuyển khoản ngay trên hoá đơn — không qua cổng trung gian, không mất phí %.
- **Nhân viên & bảng công** — mỗi nhân viên có ca làm việc riêng, tự động tổng hợp giờ làm và lương ước tính theo tháng.
- **Quản lý nhiều xe** — 1 chủ quán vận hành nhiều xe/chi nhánh, xem báo cáo cộng dồn toàn hệ thống.
- **PWA** — cài thẳng ra màn hình chính điện thoại, mở nhanh như app thật.

## ⚠️ Đọc trước khi làm gì khác

Đây là **source code thuần** — chưa cài dependency, chưa build asset. Cần làm đủ các bước ở mục "Cài đặt" bên dưới theo đúng thứ tự.

Tailwind CSS cần **build** thành file CSS tĩnh, không tự có hiệu lực khi sửa code. Sau khi sửa bất kỳ file `resources/views/*.blade.php` nào, chạy lại:

```bash
npm run build
```

Bỏ qua bước này sẽ khiến giao diện hiển thị sai/vỡ dù code hoàn toàn đúng. Lúc đang phát triển, chạy `npm run dev` (giữ chạy nền) để tự rebuild mỗi khi sửa file.

## Yêu cầu môi trường

- PHP >= 8.3 với các extension: `pdo_mysql`, `mbstring`, `openssl`, `tokenizer`, `ctype`
- MySQL >= 5.7 (hoặc MariaDB tương đương)
- Composer (chỉ cần lúc build, không cần trên hosting đích)
- Node.js + npm (chỉ cần lúc build asset, không cần trên hosting đích)

## 📦 Cài đặt

### Cách 1 — Shared Hosting / cPanel (khuyến nghị cho khách hàng phổ thông)

Hosting giá rẻ thường không có SSH/Composer, nên build sẵn ở máy dev rồi upload trọn gói:

1. Trên máy có Composer + Node:
   ```bash
   composer install --optimize-autoloader --no-dev
   npm install
   npm run build
   ```
2. Nén toàn bộ thư mục (bao gồm `vendor/` và `public/build/` vừa tạo ra) thành 1 file `.zip`.
3. Upload lên hosting, giải nén.
4. Trỏ **Document Root** của domain vào thư mục `public/`.
5. Cấp quyền ghi cho `storage/` và `bootstrap/cache/` (thường 755 hoặc 775 tuỳ hosting).
6. Mở trình duyệt, truy cập domain → hệ thống tự chuyển đến `/install` → làm theo từng bước trên màn hình (không cần gõ lệnh).

**Nếu hosting bắt buộc thư mục gốc là `public_html`** và không cho trỏ vào thư mục con: đặt toàn bộ code ngoài `public_html`, chỉ copy nội dung thư mục `public/` vào `public_html/`, rồi sửa 2 dòng `require` trong `public_html/index.php` để trỏ đúng đường dẫn tới `../rot/vendor/autoload.php` và `../rot/bootstrap/app.php`.

### Cách 2 — VPS (dành cho ai rành kỹ thuật hơn)

```bash
git clone <repo-cua-ban> rot && cd rot
composer install --optimize-autoloader --no-dev
npm install && npm run build
cp .env.example .env
php artisan key:generate
```

Cấu hình Nginx trỏ root vào `public/`, PHP-FPM, sau đó mở domain → `/install` để cấu hình DB qua giao diện — không cần chạy `php artisan migrate` tay, trình cài đặt tự làm việc đó.

## Sau khi cài đặt

1. Đăng nhập bằng số điện thoại + mật khẩu vừa tạo ở bước cài đặt.
2. Ở màn hình **Mở ca**, nếu quán chưa có thực đơn thật, bấm **Thiết lập nhanh** để có ngay 2 món mẫu (Cà phê đá, Cà phê sữa) cùng đầy đủ nguyên liệu trong kho — hoặc tự vào **Thực đơn** để thêm món theo ý mình.
3. Mở ca, bắt đầu bán ở tab **Order**.

## 🧾 Sổ doanh thu & ngưỡng thuế

Từ 01/01/2026 mọi hộ kinh doanh phải ghi sổ kế toán (Thông tư 152/2025/TT-BTC); hộ trong ngưỡng miễn thuế chỉ cần Sổ doanh thu bán hàng hóa, dịch vụ (mẫu S1a-HKD). Rót lập sổ này từ dữ liệu bán hàng. Vào **Quản lý → Báo cáo → Sổ doanh thu & ngưỡng thuế** (chỉ tài khoản chủ quán) để:

- Xem, **in / lưu PDF** hoặc **tải CSV** sổ theo tháng hoặc cả năm — mỗi ngày có bán một dòng tổng doanh thu thực nhận, cộng theo từng tháng. Điền tên hộ kinh doanh và mã số thuế ở mục "Thông tin hộ kinh doanh"; địa điểm kinh doanh lấy từ địa chỉ của xe.
- **Nhập doanh thu bán ngoài Rót** (app giao đồ ăn, bán sỉ...) để sổ và mức theo dõi ngưỡng đủ mọi kênh bán.
- **Theo dõi ngưỡng miễn thuế**: doanh thu năm nay (cộng dồn tất cả xe của chủ quán) so với ngưỡng, kèm dự báo dựa trên nhịp bán 4 tuần gần nhất và ngày dự kiến chạm ngưỡng.

Ngưỡng mặc định là **1 tỷ đồng/năm** (Nghị định 141/2026/NĐ-CP). Quy định thuế thay đổi khá nhanh nên ngưỡng không viết cứng trong code — muốn đổi, thêm vào `.env` (hoặc sửa `config/tax.php`):

```env
TAX_EXEMPT_THRESHOLD=1000000000
```

Nếu server đang cache cấu hình, chạy `php artisan config:clear` để áp dụng.

> Rót chỉ **ghi chép và tính tham khảo**, không thay thế tư vấn thuế, không nộp tờ khai thay chủ hộ và không phải máy tính tiền kết nối cơ quan thuế. Hãy đối chiếu bản in với mẫu chính thức và hỏi cơ quan thuế/kế toán khi cần.

## 🔒 Bảo mật cần làm ngay khi lên production

- Đặt `APP_DEBUG=false` trong `.env` (mặc định trong `.env.example` đã là `false`).
- Bật HTTPS (Let's Encrypt miễn phí nếu dùng VPS; hosting cPanel thường có sẵn AutoSSL).
- Đổi mật khẩu MySQL mặc định, không dùng `root` không mật khẩu.
- Backup định kỳ (`mysqldump`, hoặc dùng tính năng **Xuất dữ liệu** trong app ở Cài đặt).

## Nâng cấp lên bản mới

Khi tải bản source mới hơn đè lên một cài đặt đã chạy `/install` trước đó, luôn chạy lại đủ 3 lệnh sau (không phụ thuộc phiên bản cụ thể):

```bash
composer install --optimize-autoloader --no-dev
npm install && npm run build
php artisan migrate
```

`php artisan migrate` chỉ áp dụng các migration MỚI, không đụng tới dữ liệu cũ (đơn hàng, tài khoản... giữ nguyên). Nên backup database trước khi nâng cấp:

```bash
mysqldump -u <user> -p <ten_database> > backup-truoc-nang-cap.sql
```

Nếu server đang bật cache cấu hình (`php artisan config:cache`), chạy lại lệnh đó sau khi nâng cấp hoặc sửa `.env`.

**Lưu ý khi lên v1.2.0:** doanh thu ở trang Báo cáo giờ là số tiền thực nhận. Nếu trước đây bạn dùng giảm giá cả đơn hoặc đổi điểm, doanh thu các kỳ cũ hiển thị sẽ thấp hơn trước — không mất dữ liệu, chỉ là số cũ đang tính dư.

Xem [CHANGELOG.md](CHANGELOG.md) để biết mỗi phiên bản có thay đổi gì.

## Self-host hay Rót Cloud?

| | **Tự host (repo này)** | **[Rót Cloud](http://rot.inithtml.com/)** |
|---|---|---|
| Cài đặt | Tự thuê hosting/VPS, tự cấu hình | Đăng ký là dùng được ngay |
| Cập nhật phiên bản mới | Tự `git pull` + build lại | Tự động, luôn mới nhất |
| Backup, HTTPS, hạ tầng | Tự lo | Đã có sẵn |
| Tuỳ biến mã nguồn | Toàn quyền sửa code | Không sửa được code |
| Quản lý nhiều xe/chi nhánh | Tự cấu hình từng nơi | Gộp báo cáo trong 1 tài khoản |
| Phù hợp với | Người rành kỹ thuật, muốn tự kiểm soát hạ tầng | Đa số quán muốn bán hàng ngay, ít lo vận hành |

## Về Font Awesome

Icon được build cùng Tailwind qua Vite (package `@fortawesome/fontawesome-free`) — tự host 100%, không phụ thuộc CDN ngoài. Không cần internet để icon hiển thị sau khi build xong.

## Bản quyền & Ghi công

**Rót** được phát triển và phân phối bởi **[Init HTML](https://inithtml.com/)**. Muốn dùng ngay không cần tự vận hành server? Xem **[Rót Cloud](http://rot.inithtml.com/)**.

Phần mềm này được xây dựng dựa trên các công nghệ và dịch vụ mã nguồn mở/công khai sau:

- **[Laravel](https://laravel.com/)** — PHP framework nền tảng cho toàn bộ backend
- **[MySQL](https://www.mysql.com/)** — hệ quản trị cơ sở dữ liệu
- **[Tailwind CSS](https://tailwindcss.com/)** — framework CSS cho toàn bộ giao diện
- **[Alpine.js](https://alpinejs.dev/)** — xử lý tương tác phía trình duyệt (giỏ hàng, modal, dropdown...)
- **[Font Awesome](https://fontawesome.com/)** — bộ icon, tự host qua npm (không phụ thuộc CDN)
- **[VietQR](https://vietqr.io/)** (chuẩn Napas) — sinh mã QR chuyển khoản ngân hàng, dùng Quick Link công khai, không cần đăng ký API key
- **[SePay eInvoice API](https://developer.sepay.vn/)** — tích hợp xuất hoá đơn điện tử (mỗi quán tự kết nối tài khoản riêng của mình)

Danh sách mã BIN ngân hàng (`config/banks.php`) đối chiếu qua nguồn dữ liệu mở [github.com/subiz/vietqr](https://github.com/subiz/vietqr).
