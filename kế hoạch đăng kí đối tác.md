# SportGo — Kế hoạch hoàn thiện Module Đối tác (Partner)

## Bối cảnh

Nhánh hiện tại đã có code module Partner do GPT build trước đó, nhưng làm chưa đúng/chưa rõ ràng ở nhiều điểm. Đây là yêu cầu **rà soát lại toàn bộ và sửa cho khớp với đặc tả dưới đây** — không phải code lại từ đầu.

**Việc đầu tiên GPT phải làm**: tự đọc lại toàn bộ code Partner hiện có trong nhánh (migration, model, controller, route, component Vue liên quan) và đối chiếu với từng mục trong tài liệu này, sau đó báo cáo lại theo bảng:

| Mục | Đã có đúng chưa | Cần sửa gì |
|---|---|---|
| ... | ... | ... |

**Chỉ sau khi báo cáo xong mới được bắt đầu sửa code.** Không nhảy thẳng vào sửa khi chưa rà soát hết, để tránh sửa nhầm chỗ đã đúng hoặc bỏ sót chỗ sai.

**Quy tắc bắt buộc xuyên suốt:**
- Không tạo file rác — không tạo file demo/test rồi bỏ quên, không tạo file trùng tên kiểu `PartnerNew.vue`, `PartnerController2.php`. Sửa trực tiếp file hiện có hoặc xóa hẳn file thừa không dùng.
- Không tự ý commit/push. Sửa xong dừng lại để admin review.

---

## Phạm vi lần này

**Phải hoàn thiện:**
1. Xác minh số tài khoản ngân hàng tự động (chọn ngân hàng + nhập STK → tự biết có tồn tại không + tự fill tên chủ tài khoản)
2. Vị trí tự động lấy địa chỉ từ Google Maps (không nhập tay địa chỉ)
3. Validate tuổi đủ 18 từ CCCD/năm sinh, chặn nhập bậy
4. File Word phải là **bản đã điền dữ liệu thật** từ form (không phải file mẫu trống), trả về cả form lẫn file Word cho admin xem
5. Upload tài liệu phải lưu file thật trên storage, DB chỉ lưu đường dẫn, admin xem/tải được file gốc
6. Giao diện quản lý đối tác làm lại toàn bộ: danh sách đối tác → chi tiết đối tác (sân đang quản lý, hồ sơ, phụ lục) rõ ràng
7. Giao diện đăng ký đối tác làm lại hoàn toàn
8. Gửi mail cho owner sau khi admin duyệt/từ chối đơn (hiện tại đang thiếu)
9. Màn hình/luồng để owner ký hợp đồng sau khi được duyệt (hiện tại đang thiếu)

**Tạm thời KHÔNG làm — không tự ý thêm:**
- Luồng hủy đối tác / chấm dứt hợp đồng — để sau hoàn toàn, không động vào
- Bất kỳ nâng cấp nào khác ngoài 9 mục trên, dù có vẻ "tiện thể làm luôn"

---

# PHẦN A — XÁC THỰC & TIỆN ÍCH ĐẦU VÀO (mục 1, 2, 3)

## A1 — Xác minh số tài khoản ngân hàng

**Yêu cầu đúng phải đạt:** người dùng KHÔNG tự nhập tên chủ tài khoản. Luồng đúng là: chọn ngân hàng từ dropdown → nhập số tài khoản → hệ thống tự gọi API kiểm tra → trả về biết được tài khoản đó có tồn tại tại ngân hàng đó không, và tên chủ tài khoản là gì → tự động fill vào field tên (readonly).

- Dùng VietQR API: `GET https://api.vietqr.io/v2/banks` để lấy danh sách ngân hàng, cache lại (24h) để không gọi lại mỗi lần render form.
- Tra cứu tên chủ tài khoản: gọi đúng endpoint Account Lookup của VietQR. Trước khi code cứng, GPT phải tự kiểm tra tài liệu chính thức tại vietqr.io để xác nhận: endpoint chính xác, có cần thêm thông tin xác thực (client-id/api-key) hay không, giới hạn số lượt gọi miễn phí là bao nhiêu. Báo cáo lại các thông tin này trong bảng rà soát ở trên trước khi code, không tự đoán endpoint hoặc giả định nó miễn phí hoàn toàn nếu chưa xác nhận.
- Nếu sau khi đọc tài liệu mà việc tra tên chủ tài khoản miễn phí thực sự không khả dụng (chỉ có ở gói trả phí hoặc cần tích hợp qua cổng thanh toán trung gian), GPT phải dừng lại và báo cáo rõ phương án thay thế khả thi, không tự chế ra một API giả để "cho chạy được".
- Form: nút "Kiểm tra" sau khi nhập STK → gọi API → loading state → kết quả: nếu tồn tại, fill tên (readonly, có icon xác nhận đã verify); nếu không tồn tại hoặc lỗi, hiển thị lỗi rõ ràng, không cho submit form tới khi verify thành công.
- Lưu trạng thái đã verify + thời điểm verify vào bảng hồ sơ đối tác.

## A2 — Vị trí qua Google Maps

**Yêu cầu đúng phải đạt:** người dùng KHÔNG tự gõ địa chỉ. Chỉ chọn vị trí trên bản đồ, hệ thống tự lấy ra địa chỉ.

- Nhúng Google Maps vào form, cho phép chọn vị trí (click/kéo marker/tìm kiếm địa điểm).
- Sau khi chọn, dùng Geocoding API (reverse geocode) để tự động điền địa chỉ đầy đủ vào field hiển thị (readonly) — không có ô nhập tay địa chỉ song song.
- Lưu cả tọa độ (lat, lng) và địa chỉ text đã geocode.
- Nếu chưa có Google Maps API key trong `.env`, dừng lại và báo cáo cụ thể, không code với key giả.

## A3 — Validate tuổi đủ 18, chặn nhập bậy

- Field năm sinh/ngày sinh: validate cả frontend lẫn backend, tính tuổi tại thời điểm hiện tại, từ chối nếu dưới 18 — báo lỗi ngay khi nhập, không đợi submit toàn form.
- Số CCCD: validate đúng định dạng (12 chữ số), chặn ký tự không hợp lệ ngay khi gõ (không đợi submit mới validate).
- Áp dụng nguyên tắc này cho mọi field dễ "nhập bậy" trong form đăng ký (số điện thoại đúng định dạng VN, email đúng định dạng) — rà soát lại toàn bộ field, không chỉ riêng CCCD/năm sinh.

---

# PHẦN B — FILE WORD & TÀI LIỆU ĐÍNH KÈM (mục 4, 5)

## B1 — File Word phải có nội dung thật, không phải file mẫu trống

**Đây là điểm GPT làm sai cần sửa rõ:** "file mẫu" ở đây không phải là cho người dùng tải về 1 file Word trống để họ tự điền tay, mà là: sau khi người dùng điền xong toàn bộ form trên web, hệ thống **tự động lấy dữ liệu đã nhập, render vào file Word theo template, sinh ra file đã có đầy đủ nội dung**, rồi mới gửi cả 2 thứ cho admin xem: (a) dữ liệu form hiển thị trên UI, và (b) link tải file Word đã điền sẵn nội dung đó.

- Dùng thư viện `phpoffice/phpword` (kiểm tra đã có trong `composer.json` chưa, nếu chưa thêm vào).
- Khi submit form đăng ký thành công, trigger ngay việc sinh file Word từ dữ liệu vừa nhận (không phải lúc nào đó sau này mới sinh).
- File sinh ra lưu thật trên storage, liên kết vào bảng lưu hợp đồng/hồ sơ.
- Trang chi tiết đối tác (admin xem) phải có cả 2: xem dữ liệu trực tiếp trên UI VÀ nút tải file Word đã điền — không phải chỉ có 1 trong 2.

## B2 — Upload tài liệu lưu file thật

- File upload (CCCD, giấy phép kinh doanh, v.v.) lưu thật trên Laravel Storage, DB chỉ lưu `file_path` (đường dẫn tương đối).
- API riêng để admin xem/tải file thật: `GET /admin/partners/{partner}/documents/{document}/download`, có check quyền (`can:manage-partners`), không expose raw path công khai.
- Validate định dạng (pdf/jpg/png tùy loại) và dung lượng tối đa khi upload.
- Rà soát code hiện có: nếu GPT trước đây chỉ lưu file tạm hoặc lưu sai chỗ (ví dụ chỉ lưu tên file mà không thực sự ghi file lên storage), đây chính là lỗi cần sửa trong mục này.

---

# PHẦN C — GIAO DIỆN QUẢN LÝ ĐỐI TÁC (mục 6, 7)

## C1 — Giao diện đăng ký đối tác (làm lại hoàn toàn)

Form/wizard gồm:
- Thông tin cá nhân: họ tên, SĐT, email, ngày sinh (validate 18+ ngay khi nhập), CCCD
- Ngân hàng: chọn ngân hàng + nhập STK + nút Kiểm tra → tên tự fill (A1)
- Vị trí: bản đồ Google Maps chọn điểm → địa chỉ tự hiện (A2)
- Upload tài liệu: từng ô riêng theo loại giấy tờ, hiện trạng thái upload, cho xem lại/thay file trước khi submit
- Nút Submit chỉ bật khi: ngân hàng đã verify, vị trí đã chọn, tuổi hợp lệ, các tài liệu bắt buộc đã upload
- Sau submit: màn xác nhận "Hồ sơ đã gửi, chờ duyệt", không tự chuyển sang bước ký hợp đồng

## C2 — Màn hình danh sách đối tác (admin)

- Bảng: Tên | SĐT | Email | Trạng thái (badge tiếng Việt: Chờ duyệt / Đã duyệt / Từ chối / Đã ký hợp đồng) | Ngày đăng ký
- Filter theo trạng thái
- Click vào dòng → sang trang chi tiết

## C3 — Màn hình chi tiết đối tác (admin) — làm rõ ràng, đủ thông tin

Layout đồng nhất với pattern chi tiết đang dùng ở các module khác trong hệ thống (sidebar trái + tab phải).

### Sidebar trái
- Thông tin cơ bản, trạng thái hiện tại
- Nếu đang chờ duyệt: nút Duyệt (confirm dialog) và nút Từ chối (modal nhập lý do, required)
- Sau khi duyệt/từ chối: cập nhật UI tại chỗ, không reload trang, **đồng thời trigger gửi mail** (xem D)

### Tab "Hồ sơ"
- Toàn bộ thông tin đã đăng ký (cá nhân, ngân hàng đã verify, địa chỉ + mini-map)
- Nút tải file Word đã điền sẵn nội dung (B1)

### Tab "Tài liệu đính kèm"
- Danh sách document đã upload: loại, tên file, ngày upload, nút xem/tải file thật (B2)

### Tab "Sân quản lý"
- Danh sách sân thuộc đối tác (dùng đúng quan hệ Partner-Court đã có trong hệ thống nếu tồn tại; nếu hệ thống đã có sẵn bảng/quan hệ này, GPT phải tự tìm và dùng lại, không tạo bảng mới trùng lặp). Nếu chưa có sân nào, hiển thị trạng thái rỗng rõ ràng.

### Tab "Phụ lục / Hợp đồng"
- Danh sách bản hợp đồng đã sinh, mỗi bản: version, ngày sinh, nút tải file

## C4 — Giao diện ký hợp đồng (phía owner) — hiện đang thiếu, cần bổ sung

- Sau khi được duyệt, owner đăng nhập thấy khu vực riêng: xem lại nội dung hồ sơ đã đăng ký (hiển thị lại dữ liệu form, hoặc xem file Word đã sinh) + nút "Xác nhận ký hợp đồng"
- Bấm xác nhận → confirm dialog rõ ràng (hành động ràng buộc) → gọi API ký → cập nhật trạng thái `contract_signed` → màn xác nhận đã ký thành công

---

# PHẦN D — MAIL THÔNG BÁO (mục 8) — hiện đang thiếu, cần bổ sung

**Hiện trạng sai cần sửa:** admin duyệt đơn xong nhưng không có mail nào gửi về cho owner. Bổ sung:

- Khi admin bấm **Duyệt**: trigger gửi mail cho owner — nội dung: thông báo đã duyệt, hướng dẫn bước tiếp theo (đăng nhập để ký hợp đồng — link trực tiếp tới C4 nếu được)
- Khi admin bấm **Từ chối**: trigger gửi mail cho owner — nội dung: thông báo từ chối kèm lý do cụ thể đã nhập
- Tạo Mailable riêng (`PartnerApprovedMail`, `PartnerRejectedMail`) hoặc theo đúng convention mail đã có sẵn trong dự án (rà soát xem dự án đã có Mailable nào khác để theo cùng pattern)
- Gửi qua queue (`ShouldQueue`) nếu dự án đã cấu hình queue, tránh chặn request khi admin bấm duyệt/từ chối

---

## Sau khi hoàn thành

1. Trả lại bảng rà soát đầy đủ (đã có đúng chưa / cần sửa gì) cho từng mục A1, A2, A3, B1, B2, C1, C2, C3, C4, D
2. Liệt kê các file đã sửa/tạo mới (đường dẫn cụ thể)
3. Liệt kê package/thư viện mới đã thêm (nếu có) và biến môi trường mới cần khai báo trong `.env`
4. Nếu có mục nào không thể hoàn thiện do thiếu API key, thiếu thư viện, hoặc thiếu thông tin (ví dụ giới hạn thật của VietQR free tier), dừng lại và báo cáo cụ thể — không code bằng dữ liệu giả rồi báo "đã xong"
5. Không tự ý commit/push — dừng lại để admin review