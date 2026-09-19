# Lịch sử phiên bản — Rót

Chỉ ghi những thay đổi ảnh hưởng tới người dùng (tính năng mới, sửa lỗi quan trọng). Không ghi các thay đổi kỹ thuật nội bộ không ảnh hưởng tới cách sử dụng.

## v1.1.2 (hiện tại)

- **Đặt món qua QR**: Cài đặt → "Đặt món qua QR" — bật lên để có ngay mã QR/link dán tại xe, khách tự quét xem thực đơn và gửi yêu cầu gọi món (không thanh toán trực tiếp). Yêu cầu hiện ở trang "Đơn hàng" để nhân viên Nhận/Từ chối; bấm Nhận sẽ mở thẳng màn Order với giỏ hàng điền sẵn để kiểm tra lại trước khi tính tiền. Nếu quán đang bật "Khách hàng thân thiết", khách điền thêm SĐT lúc gửi yêu cầu sẽ tự động tra cứu/áp dụng tích điểm ngay khi nhân viên nhận đơn.
- **Badge số ở "Đơn hàng" và "Kho nguyên liệu"**: hiện số đơn trong ca và số nguyên liệu sắp hết ngay trên thanh điều hướng (cả PC lẫn mobile) — theo dõi nhanh mà không cần bấm vào từng trang.
- **Kết quả chốt ca rõ ràng hơn**: sau khi chốt ca, hiện hẳn 1 màn riêng báo chênh lệch quỹ tiền mặt (khớp/dư/thiếu), thay vì chỉ 1 dòng thông báo nhỏ dễ bỏ lỡ như trước.
- **Cài lên màn hình chính (PWA)**: mở nhanh như ứng dụng thật từ màn hình chính điện thoại, không cần mở trình duyệt rồi gõ lại địa chỉ mỗi lần bán hàng.

## v1.1.1

- **Bảng công**: trang mới (từ trang Nhân viên) tổng hợp theo tháng — số ca đã chốt, tổng giờ làm, lương ước tính cho từng nhân viên. Dùng đúng dữ liệu ca đã có sẵn, khớp với cách tính ở trang Báo cáo.
- **Báo cáo tổng hợp nhiều xe**: trang mới (từ trang Các xe cà phê) cộng dồn doanh thu/giá vốn/lợi nhuận/chi phí vận hành của tất cả các xe cùng 1 chủ quán, kèm bảng chi tiết từng xe.
- **Bàn giao ca nhanh hơn**: màn hình Mở ca giờ tự gợi ý sẵn "Tiền mặt đầu ca" theo đúng tiền cuối ca gần nhất đã đếm thực tế — vẫn phải xem/xác nhận lại, không tự động chấp nhận, không ảnh hưởng tới trách nhiệm từng người.
- **Tối ưu trang Báo cáo**: gộp "Lợi nhuận thực tế" và "Dự đoán doanh thu" vào 1 khối có thể thu gọn, đỡ rối mắt trên điện thoại — 3 số chính (Doanh thu/Giá vốn/Lợi nhuận) và bảng chi tiết từng món vẫn luôn hiện đầy đủ như cũ.

## v1.1.0

- **Lương nhân viên**: trang Nhân viên cho thiết lập lương theo giờ hoặc theo tháng cho từng người. Lương theo giờ tính đúng theo thời lượng từng ca đã chốt (giờ đóng ca trừ giờ mở ca) của chính nhân viên đó, không phải giờ hành chính cố định.
- **Chi phí mặt bằng**: trang Cài đặt có thêm mục "Chi phí vận hành" để khai chi phí mặt bằng hàng tháng (mặc định 0, quán không có mặt bằng cố định thì bỏ qua).
- **Lợi nhuận thực tế**: trang Báo cáo giờ tính thêm "Lợi nhuận thực tế" = Lợi nhuận − Chi phí nhân sự − Chi phí mặt bằng, phân bổ đúng theo tỉ lệ số ngày của kỳ Ngày/Tuần/Tháng đang xem. Chỉ hiện khi đã thiết lập ít nhất một trong hai khoản trên. Xuất CSV cũng có đủ các dòng chi phí này.

## v1.0.9

- **Lên đơn nhanh**: nút tia sét ở góc mỗi món trong màn Order — dành cho khách chỉ mua đúng 1 ly, trả tiền mặt vừa đủ. Món đơn giản (không phân size/tuỳ chọn) lên đơn thẳng ngay. Món có size hoặc tuỳ chọn (VD: Lượng đường) sẽ mở đúng 1 bước chọn (tự chọn sẵn mặc định, đổi được) rồi mới lên đơn — không còn đoán bừa mặc định cho món cần chọn thật.
- **Thông báo đơn hàng thành công**: cả luồng thanh toán bình thường và Lên đơn nhanh đều hiện thông báo ngắn ở đầu màn hình khi tạo đơn xong.
- **Sửa chi tiết nguyên liệu**: trang Kho nguyên liệu cho sửa trực tiếp cả "Tồn kho" và "Giá vốn TB", không chỉ tên/đơn vị — để sửa lại số liệu mẫu từ Thiết lập nhanh hoặc khớp lại theo kiểm kê thực tế, tách biệt với "Nhập kho" (chỉ dùng khi mua hàng thật).
- **Thiết lập nhanh**: nút ở màn hình "Mở ca" (chỉ chủ quán, chỉ hiện khi quán chưa có thực đơn thật) — tạo sẵn 2 món (Cà phê đá, Cà phê sữa) cùng đầy đủ 8 nguyên liệu trong kho, đúng công thức trừ kho, kèm tuỳ chọn "Lượng đường/Lượng sữa". Giúp quán mới có ngay dữ liệu mẫu để bán thử hoặc tham khảo cách thiết lập.
- **Xuất toàn bộ dữ liệu**: trang Cài đặt → "Xuất dữ liệu" — tải về 1 file ZIP gồm nhiều CSV (thực đơn, kho, khách hàng, ca làm, sổ quỹ, đơn hàng...) để tự sao lưu hoặc chuyển đi nơi khác.
- **Dự đoán doanh thu**: trang Báo cáo có thêm khối dự đoán doanh thu cho ngày/tuần/tháng sắp tới, tính dựa trên xu hướng bán hàng thực tế + quy luật ngày trong tuần (không phải số ước lượng tuỳ tiện).
- **Lịch sử nhập kho**: mỗi nguyên liệu có nút "Lịch sử" xem lại các lần nhập kho gần nhất (số lượng, tổng tiền, giá quy đổi, người nhập).
- Mặc định TẮT "In hoá đơn" cho quán mới tạo (trước đây mặc định BẬT) — quán cũ không bị ảnh hưởng.
- **Sửa lỗi**: đổi màu chủ đạo (Cài đặt → Giao diện) trước đây không áp dụng cho bóng đổ (shadow) ở nút chính, thẻ sổ quỹ và vài nơi khác — vẫn hiện màu cam mặc định dù đã đổi màu khác. Đã sửa để bóng đổ luôn khớp màu đã chọn.
- **Sửa lỗi giao diện**: giỏ hàng và vài trang có nội dung dài trước đây phải cuộn cả trang thay vì cuộn gọn trong từng khu vực — đã sửa lại cách tính chiều cao để mỗi khu vực (menu, giỏ hàng, nội dung trang) tự cuộn đúng phần của mình. Ẩn thanh cuộn ở mọi khu vực cuộn nội bộ (modal, menu, danh sách), chỉ giữ 1 thanh cuộn ở khu vực nội dung chính.
- **Sửa lỗi nghiêm trọng**: tài khoản nhân viên đăng nhập thành công nhưng vào bất kỳ trang nào trong khu vực bán hàng (Order, Mở ca/Chốt ca, Sổ quỹ) đều bị chặn "403 Forbidden" — do khu vực này bị áp nhầm quy tắc "chỉ chủ quán" thay vì "chủ quán và nhân viên". Nhân viên giờ dùng được bình thường.
- **Sửa lỗ hổng bảo mật**: bước thanh toán trước đây không kiểm tra món/tuỳ chọn có thuộc đúng quán đang bán hay không — 1 request bị chỉnh sửa thủ công (qua devtools) có thể tham chiếu tới món của quán khác (bản Cloud) hoặc xe khác (bản self-hosted có nhiều xe), gây trừ nhầm kho/đọc được giá của bên không liên quan. Đã chặn lại: sai quán là báo lỗi ngay, không cho tạo đơn.

## v1.0.8

- Xuất báo cáo lợi nhuận ra file CSV (mở được bằng Excel, đúng chuẩn số/dấu phân cách cho Excel tiếng Việt).

## v1.0.7

- Phân loại thực đơn theo mô hình Menu Engineering (Stars/Plowhorses/Puzzles/Dogs) trong trang Báo cáo.
- Công thức nguyên liệu tách riêng theo TỪNG SIZE (trước đó dùng chung công thức của size đầu tiên cho cả món).
- Cảnh báo khi 1 nguyên liệu vừa nằm trong công thức gốc, vừa nằm trong công thức của 1 tuỳ chọn đang gắn cho món đó — tránh trừ kho bị nhân đôi.
- Duy trì đăng nhập 30 ngày (trước đó chỉ 2 giờ).
- Chống bot đăng nhập (honeypot + token thời gian).
- Thêm ô "Ghi nhớ đăng nhập" ở màn hình đăng nhập.

## v1.0.6

- Cải thiện hiệu năng và sửa một số lỗi nhỏ.

## v1.0.5

- Hoá đơn điện tử — tích hợp SePay eInvoice, cấu hình tại Cài đặt → Hoá đơn điện tử.

## v1.0.4

- Báo cáo lợi nhuận xem được theo Ngày/Tuần/Tháng, so sánh % với kỳ liền trước.
- Định dạng tiền hiển thị đúng chuẩn Việt Nam (dấu chấm ngăn cách hàng nghìn) ở toàn bộ ứng dụng.

## v1.0.3

- Cải thiện hiệu năng và sửa một số lỗi nhỏ.

## v1.0.2

- Giảm giá/khuyến mãi cho đơn hàng (theo % hoặc theo số tiền, áp dụng cho cả đơn hoặc từng món).
- Lưu đơn nháp — giữ chỗ cho khách đang chờ, bán tiếp cho khách khác mà không mất nội dung đơn cũ.

## v1.0.1

- Cải thiện hiệu năng và sửa một số lỗi nhỏ.

## v1.0.0

- Phiên bản đầu tiên: cài đặt qua trình cài đặt web (`/install`), đăng nhập, quản lý thực đơn (danh mục/món/size, xoá mềm khôi phục được), bán hàng 1 màn hình, ca làm việc, sổ quỹ, quản lý nhân viên, quản lý kho nguyên liệu, báo cáo giá vốn/lợi nhuận chính xác theo đúng thời điểm bán.
