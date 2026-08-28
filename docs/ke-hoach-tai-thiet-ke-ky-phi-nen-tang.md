# Kế hoạch tái thiết kế kỳ phí nền tảng SportGo

## 1. Mục tiêu

Thiết kế lại nghiệp vụ phí nền tảng để bảo đảm:

- Giá và ưu đãi được quản lý theo phiên bản, có ngày bắt đầu áp dụng rõ ràng.
- Mỗi khoảng thời gian sử dụng dịch vụ chỉ được ghi nhận một lần và không bị thay đổi ngược lịch sử.
- Phân biệt rõ trả trước, trả đúng hạn, gia hạn công nợ, miễn phí và khuyến mại.
- Chủ sân có thể thanh toán bằng số dư khả dụng của đúng cụm sân khi đáp ứng điều kiện an toàn.
- Khoản phải thu phí nền tảng được tính vào số tiền chủ sân được phép rút.
- Chấm dứt hợp đồng không xóa công nợ và không tạo khoảng thời gian sử dụng miễn phí ngoài chính sách.
- Các tác vụ tự động có khóa chống trùng, có lịch sử chạy và có khả năng phục hồi sau lỗi.
- Giao diện Admin/Owner sử dụng component, màu sắc, badge, bảng, form và modal hiện có của SportGo.

## 2. Phạm vi triển khai

### 2.1. Trong phạm vi

- Phiên bản bảng giá phí nền tảng.
- Bậc phí theo số sân của từng phiên bản.
- Quy tắc giảm giá trả trước theo số tháng.
- Kỳ dịch vụ tháng và kỳ lẻ theo ngày.
- Khoản phải thu, thanh toán và phân bổ thanh toán.
- Thỏa thuận gia hạn thanh toán có bảo đảm bằng số dư.
- Trial theo số ngày; miễn phí theo số kỳ; khuyến mại giảm phí.
- Thanh toán phí bằng số dư chủ sân hoặc chuyển khoản/SePay.
- Tạm giữ phí nền tảng khi tính số tiền được rút.
- Tích hợp phí nền tảng vào quy trình chấm dứt và hủy chấm dứt.
- Nhắc phí, quá hạn, giới hạn quyền và tác vụ tự động.
- Giao diện quản trị và giao diện chủ sân.
- Migration/backfill dữ liệu phí nền tảng hiện có.
- Unit, Feature, scheduler, command, API và browser test.

### 2.2. Ngoài phạm vi

- Thay đổi UUID PK/FK hiện có.
- Đổi cấu trúc route công khai không cần thiết.
- Thay đổi cách tính tiền booking, voucher người chơi hoặc phí cổng thanh toán.
- Xóa lịch sử ledger, payment, refund, withdrawal hoặc hồ sơ chấm dứt.
- Tự động cấp tín dụng không bảo đảm cho chủ sân.

## 3. Vấn đề đã xác nhận trong hệ thống hiện tại

1. Bậc phí đang được sửa trực tiếp; `effective_from` bị đặt lại thành thời điểm chỉnh sửa, chưa có bảng giá draft/scheduled/active/retired.
2. Admin không chọn được ngày bắt đầu phiên bản giá từ giao diện.
3. Kỳ tự động đã hủy vẫn giữ `automation_key`; bộ sinh kỳ bỏ kỳ đã hủy khi tìm mốc gần nhất nhưng vẫn chặn khóa tự động cũ, có thể làm mất kỳ.
4. Kỳ 3/6/9/12 tháng đang được dùng chung cho cả tạo tay và trả trước, chưa phân biệt thời hạn dịch vụ với điều khoản thanh toán.
5. Hạn thanh toán của kỳ admin tạo chưa có ràng buộc quan hệ với ngày bắt đầu/kết thúc.
6. Gia hạn trả sau 12 tháng có thể nhận nhầm giảm giá trả trước.
7. Trial 30 ngày mới xuất hiện ở nội dung marketing, chưa có dữ liệu backend để thực thi.
8. Nội dung marketing nói thu phí trên giao dịch, trong khi mã hiện tại tính theo số sân/tháng.
9. Kỳ 0 đồng ở trạng thái chưa thanh toán có thể bị tác vụ quá hạn coi là khoản nợ.
10. Tác vụ khóa quá hạn đang có khả năng xét cả kỳ `cancelled` vì chỉ loại `paid`.
11. Khi cụm sân vào chấm dứt và chuyển `locked`, bộ sinh kỳ tự động ngừng chạy.
12. Công thức số tiền được rút trong chấm dứt chưa trừ phí nền tảng.
13. Hủy yêu cầu chấm dứt có thể mở lại cụm sân ngay mà chưa quyết toán phí và chưa cần Admin duyệt khôi phục.
14. Chưa có test tích hợp giữa kỳ phí, số dư chủ sân và chấm dứt hợp đồng.

## 4. Nguyên tắc nghiệp vụ bắt buộc

### 4.1. Tách thời gian dịch vụ khỏi thời hạn thanh toán

- `Kỳ dịch vụ` trả lời: SportGo cung cấp dịch vụ cho cụm sân từ ngày nào đến ngày nào.
- `Khoản phải thu` trả lời: chủ sân phải trả bao nhiêu, hạn nào và đã trả bao nhiêu.
- `Thỏa thuận gia hạn` trả lời: SportGo cho phép khoản phải thu được trả muộn đến ngày nào.
- Không hủy hoặc gộp kỳ dịch vụ để biểu diễn việc trả muộn.

### 4.2. Một tháng là một phân bổ dịch vụ

- Chu kỳ chuẩn là tháng lịch từ ngày 1 đến cuối tháng.
- Kỳ đầu hoặc kỳ cuối không tròn tháng được tính theo ngày.
- Một lần trả trước 3/6/9/12 tháng vẫn phải có các phân bổ tháng con để khóa giá, số sân, discount và hoàn tiền chưa sử dụng chính xác.
- Không có hai phân bổ còn hiệu lực trùng ngày cho cùng một cụm sân.

### 4.3. Giá đã chốt không bị sửa ngược

- Mỗi kỳ lưu snapshot phiên bản giá, bậc phí, số sân, đơn giá và công thức.
- Phiên bản giá đã xuất bản không được chỉnh sửa.
- Tăng hoặc giảm giá chỉ áp dụng cho kỳ chưa phát hành bắt đầu từ ngày hiệu lực.
- Kỳ đã thanh toán hoặc đã được cấp miễn phí không tính lại khi giá thay đổi.

### 4.4. Hủy, miễn, giảm và xóa nợ là bốn nghiệp vụ khác nhau

- `Hủy/void`: chỉ dùng cho bản ghi sai, trùng hoặc không còn nghĩa vụ hợp lệ; phải có bản ghi thay thế nếu cần.
- `Miễn phí/waiver`: cung cấp dịch vụ với số phải trả bằng 0 theo chính sách hoặc phê duyệt.
- `Khuyến mại/discount`: giảm một phần hoặc toàn bộ phí nhưng vẫn giữ giá gốc và dòng giảm.
- `Xóa nợ/write-off`: dừng thu một khoản nợ đã phát sinh; cần quyền riêng và chứng từ, không đồng nghĩa đã thanh toán.

## 5. Mô hình trạng thái mục tiêu

### 5.1. Phiên bản bảng giá

`draft -> scheduled -> active -> retired`

- Draft được chỉnh sửa.
- Scheduled đã chốt nội dung, có ngày hiệu lực, chỉ được hủy lịch trước thời điểm khóa.
- Active không được sửa.
- Retired chỉ đọc và vẫn liên kết được với dữ liệu lịch sử.
- Tại một thời điểm chỉ có một phiên bản active.

### 5.2. Kỳ dịch vụ/khoản phải thu

`draft -> issued -> settled`

Nhánh ngoại lệ:

- `issued -> overdue`
- `draft/issued chưa có tiền -> voided`
- `issued/overdue -> written_off` khi có phê duyệt xóa nợ
- Kỳ 0 đồng chuyển thẳng `draft -> settled_zero`, không tạo giao dịch thanh toán.

### 5.3. Thỏa thuận gia hạn

`draft -> pending_owner_acceptance -> active -> fulfilled/expired/cancelled`

- Một cụm sân chỉ có tối đa một thỏa thuận active.
- Thỏa thuận phải có ngày thanh toán cụ thể, không dùng nội dung mơ hồ như “sau 3 tháng”.
- Quá hạn chỉ tính sau ngày cam kết.
- Thỏa thuận không thay đổi số tiền gốc và không được hưởng giảm trả trước.

## 6. Quy tắc tạo kỳ tự động

1. Scheduler chạy hằng ngày và xác định các cụm sân đủ điều kiện.
2. Với mỗi cụm sân, lấy ngày dịch vụ kế tiếp chưa được bao phủ.
3. Xác định trial/miễn phí/chấm dứt trước khi xác định tiền phải thu.
4. Chọn phiên bản giá hiệu lực tại ngày bắt đầu dịch vụ, không chọn theo thời điểm scheduler chạy.
5. Chốt số sân theo quy tắc được cấu hình và lưu snapshot.
6. Tạo kỳ tháng hoặc kỳ lẻ, sau đó tạo khoản phải thu tương ứng.
7. Dùng idempotency key theo `cluster + period_start + period_end + purpose`, có cơ chế replacement cho kỳ voided.
8. Tác vụ chạy lại không tạo bản ghi trùng.
9. Nếu thất bại một cụm sân, ghi log lỗi và tiếp tục cụm khác.
10. Có command preview/dry-run để Admin xem kỳ sẽ được tạo trước khi chạy thật.

## 7. Trả trước và giảm giá

- Các lựa chọn thời hạn trả trước được cấu hình theo phiên bản bảng giá.
- Công thức:

```text
phí_gốc = số_sân × đơn_giá_tháng × số_tháng
giảm_trả_trước = phí_gốc × tỷ_lệ_giảm_trả_trước
giảm_khuyến_mại = tính theo chính sách trên số tiền còn lại
số_phải_trả = max(0, phí_gốc - giảm_trả_trước - giảm_khuyến_mại - điều_chỉnh)
```

- Chỉ áp dụng giảm trả trước khi khoản tiền được thanh toán trước khi bắt đầu các tháng được hưởng ưu đãi.
- Khoản trả sau hoặc thỏa thuận công nợ không được hưởng giảm trả trước.
- Khi hoàn phần chưa sử dụng, hoàn theo số tiền ròng đã phân bổ cho từng tháng/ngày, không hoàn theo giá niêm yết.

## 8. Trial, miễn phí và khuyến mại

### 8.1. Trial theo ngày

- Trial bắt đầu khi cụm sân chính thức được kích hoạt, không bắt đầu ở bản nháp đăng ký.
- Chương trình trial có phiên bản, ngày hiệu lực và số ngày 30/90 hoặc giá trị khác.
- Cụm sân lưu snapshot ngày bắt đầu, ngày kết thúc và phiên bản chương trình.
- Trial đang chạy có thể được kéo dài nhưng không được rút ngắn.
- Trial đã hết không được mở ngược; dùng khuyến mại phí nếu muốn hỗ trợ.
- Một cụm sân chỉ hưởng trial một lần trừ ngoại lệ được phê duyệt.

### 8.2. Kỳ miễn phí 0 đồng

- Vẫn tạo kỳ dịch vụ để chứng minh thời gian được sử dụng.
- Không yêu cầu chủ sân thanh toán 0 đồng.
- Không tạo QR, payment hoặc phiếu thu.
- Hiển thị “Miễn phí – đã hoàn tất” và lý do.
- Tính là kỳ dịch vụ đã cung cấp nhưng không tính là kỳ đã thanh toán/trả trước.

### 8.3. Khuyến mại

- Có phạm vi cụm sân, nhóm chủ sân, phiên bản giá, ngày áp dụng, số kỳ và ngân sách.
- Khóa rõ có được cộng dồn với giảm trả trước hay không.
- Dòng giảm không làm số phải trả âm.
- Phần giảm thừa không chuyển thành số dư có thể rút nếu chính sách không quy định.
- Kỳ đã thanh toán không sửa đè; sử dụng adjustment/credit có nguồn gốc rõ ràng.

## 9. Gia hạn thanh toán ba tháng

### 9.1. Cách xử lý

- Không hủy kỳ tự động.
- Hệ thống tiếp tục tạo từng kỳ tháng.
- Các khoản phải thu được gắn vào một thỏa thuận với ngày thanh toán chung hoặc điều khoản Net-N đã được chọn rõ.
- Trước ngày cam kết: không overdue, không khóa do quá hạn, vẫn gửi nhắc trước hạn.
- Sau ngày cam kết: chuyển overdue và chạy chính sách dunning của phiên bản đã snapshot.

### 9.2. Kiểm soát rủi ro

- Mặc định là gia hạn có bảo đảm bằng số dư chủ sân.
- Không cho rút phần đã giữ cho phí nền tảng.
- Chỉ được rút phần dư sau tất cả nghĩa vụ booking/refund/dispute/withdrawal/fee.
- Nếu số dư không đủ, giữ số hiện có và theo dõi phần nợ thiếu.
- Tín dụng không bảo đảm phải là ngoại lệ được hai cấp phê duyệt, có hạn mức và tài liệu chấp thuận riêng.
- Không cho tạo thỏa thuận mới khi có thỏa thuận quá hạn.
- Admin tạo thỏa thuận không được đồng thời tự duyệt ngoại lệ vượt hạn mức.

## 10. Thanh toán bằng số dư chủ sân

Thứ tự xử lý đề xuất:

1. Khoản điều chỉnh chỉ dùng cho phí nền tảng.
2. Số dư an toàn của đúng cụm sân nếu chủ sân chọn hoặc đã bật tự động thanh toán.
3. Phần còn lại thanh toán qua chuyển khoản/SePay.

Số dư an toàn:

```text
số_dư_an_toàn = max(
  0,
  available_balance
  - future_booking_liability
  - pending_refund_liability
  - dispute_hold
  - termination_hold
  - các_khoản_giữ_ưu_tiên_khác
)
```

- Debit số dư và cập nhật khoản phải thu phải nằm trong cùng transaction, có `lockForUpdate` và idempotency key.
- Mọi debit/release/reversal có ledger trước/sau, actor và reference đến kỳ phí.
- Hoàn tiền trả về đúng nguồn thanh toán ban đầu trong giới hạn số đã trả từ nguồn đó.

## 11. Chấm dứt hợp đồng

### 11.1. Ngày chốt tính phí

- Chọn hủy/hoàn toàn bộ booking tương lai: chốt đến hết ngày cụm sân bị khóa.
- Chọn phục vụ booking đã có: chốt đến ngày hoàn thành booking cuối cùng được giữ lại.
- Chấm dứt song phương hoặc từ SportGo: dùng ngày hiệu lực đã thỏa thuận/thông báo.
- Sau ngày chốt, quyền chỉ để đối soát, refund, withdrawal và ký hồ sơ không phát sinh phí nền tảng thông thường.

### 11.2. Quyết toán

```text
số_tiền_ròng =
  số_dư_chủ_sân
  + hoàn_phí_trả_trước_chưa_sử_dụng
  - booking/refund/dispute_liability
  - phí_nền_tảng_chưa_thanh_toán
  - phí_lẻ_phát_sinh_đến_ngày_chốt
  - lệnh_rút_đang_xử_lý
```

- Chỉ được rút phần dương.
- Không sinh văn bản chấm dứt cuối khi còn nghĩa vụ tài chính chưa xử lý.
- Ngoại lệ: có biên bản xác nhận nợ/kế hoạch trả nợ riêng đã được phê duyệt và ký; khoản nợ tiếp tục tồn tại sau chấm dứt.
- Nút “xác nhận công nợ đã xử lý” phải yêu cầu số tiền, loại xử lý, chứng từ và người phê duyệt.

### 11.3. Hủy yêu cầu chấm dứt

- Chủ sân gửi và ký yêu cầu hủy; hệ thống chưa mở sân ngay.
- Hệ thống tính lại phí, booking/refund đã xử lý và các hành động không thể hoàn tác.
- Nợ phí phải được thanh toán hoặc có thỏa thuận gia hạn active.
- Admin duyệt khôi phục sau khi xem preview quyết toán.
- Phí chạy lại từ ngày cụm sân được kích hoạt lại; không tính phí cho thời gian chỉ có quyền đối soát.
- Các yêu cầu chấm dứt/hủy lặp lại được gắn cờ để Admin xem xét.

## 12. Chính sách quá hạn

- Hạn chuẩn được cấu hình theo phiên bản chính sách và snapshot vào khoản phải thu.
- Gợi ý mặc định:
  - Trước hạn 7 ngày: thông báo sắp đến hạn.
  - Sau hạn 1 ngày: trạng thái overdue.
  - Sau hạn 3 ngày: nhắc lần hai.
  - Sau hạn 7 ngày: giới hạn tạo booking mới, chỉnh giá, voucher, bài đăng và nhân sự.
  - Sau hạn 20 ngày: tăng mức hạn chế theo chính sách.
  - Sau hạn 30 ngày: chuyển xử lý thủ công/chấm dứt; không tự động xóa dữ liệu.
- Kỳ `voided`, `settled_zero`, `settled` và số còn phải trả bằng 0 không được đưa vào dunning.
- Thỏa thuận gia hạn active dùng ngày cam kết làm hạn, không dùng hạn cũ.
- Mỗi khoản phải thu giữ phiên bản dunning khi bắt đầu quá hạn.

## 13. Giao diện mục tiêu

### 13.1. Admin

Giữ layout, button, form control, table, badge, modal và màu SportGo hiện có; không tạo một design system mới.

- Tab “Bảng giá”: danh sách phiên bản với badge Draft/Sắp áp dụng/Đang dùng/Đã ngừng.
- Trang chi tiết phiên bản: bậc phí, quy tắc trả trước, trial/khuyến mại liên quan, preview số cụm sân bị ảnh hưởng.
- Tab “Kỳ phí”: bảng lọc theo cụm sân, trạng thái, nguồn tạo, ngày dịch vụ, hạn thanh toán và thỏa thuận.
- Drawer/modal chi tiết: phí gốc, từng dòng giảm, nguồn thanh toán, lịch sử trạng thái và log tự động.
- Hành động tách riêng: Điều chỉnh hạn, Tạo thỏa thuận gia hạn, Void và tạo bản thay thế, Miễn phí, Xóa nợ.
- Form gia hạn: ngày phải trả, các kỳ được áp dụng, hạn mức, lý do, tài liệu, preview số dư sẽ bị giữ.
- Màn chấm dứt: thêm thẻ công nợ phí, phí dự kiến đến ngày chốt, số giữ và số được rút.

### 13.2. Owner

- Tổng quan: kỳ đang dùng, ngày kết thúc trial, kỳ sắp tới, hạn thanh toán và tổng số bị giữ.
- Chi tiết kỳ: khoảng dịch vụ, snapshot giá/số sân, phí gốc, discount/promotion, số phải trả và lịch sử.
- Nút “Dùng số dư chủ sân” chỉ hiện khi có số dư an toàn.
- Tùy chọn tự động thanh toán bằng số dư theo từng cụm sân, mặc định tắt.
- Gia hạn được hiển thị bằng badge và ngày cam kết, không gọi là trả trước.
- Kỳ 0 đồng hiển thị “Miễn phí – đã hoàn tất”, không có nút thanh toán.
- Khi chấm dứt, hiển thị công thức quyết toán và ngày chốt trước khi ký.

## 14. Thay đổi dữ liệu dự kiến

Phần này cần phê duyệt schema trước khi tạo migration. Hướng ưu tiên là migration nhỏ, không drop/rename trường hiện có:

- Bảng phiên bản bảng giá.
- Liên kết bậc phí với phiên bản bảng giá.
- Bảng quy tắc giảm trả trước.
- Bảng chương trình trial/khuyến mại và phân bổ áp dụng.
- Bảng phân bổ kỳ dịch vụ theo tháng/ngày.
- Bảng thỏa thuận gia hạn và các khoản phải thu thuộc thỏa thuận.
- Bảng khoản tạm giữ phí nền tảng hoặc mở rộng owner wallet ledger theo cấu trúc hiện có.
- Trường replacement/void reason/state snapshot cần thiết trên ledger hiện tại.
- Trường ngày chốt phí và snapshot quyết toán trên hồ sơ chấm dứt.

Không thay UUID PK/FK và không xóa dữ liệu lịch sử.

## 15. API dự kiến

Ưu tiên mở rộng route hiện có, tránh đổi URL đang được frontend sử dụng.

- CRUD draft/schedule/cancel schedule cho phiên bản giá.
- Preview tác động trước khi schedule.
- Preview/tạo thỏa thuận gia hạn; owner accept; admin approve/cancel.
- Preview kỳ phí tự động và endpoint xem log chạy.
- Void/replace, waiver và write-off bằng endpoint tách riêng.
- Thanh toán kỳ bằng số dư; bật/tắt tự động thanh toán theo cụm sân.
- API quyết toán chấm dứt trả về phí còn nợ, phí dự kiến, số giữ và số được rút.
- Hủy chấm dứt tạo trạng thái chờ Admin duyệt thay vì mở sân ngay.

Mọi endpoint mutation phải có validation backend, permission, transaction, idempotency và audit log.

## 16. Kế hoạch migration/backfill

1. Tạo schema mới bằng các migration nhỏ, tên theo từng bảng/chức năng.
2. Tạo một phiên bản bảng giá lịch sử từ các bậc phí đang active.
3. Gắn ledger hiện tại vào snapshot/phiên bản phù hợp khi có thể; giữ nguyên số tiền và trạng thái.
4. Chuyển các ledger nhiều tháng hiện có thành dữ liệu lịch sử; không tách làm thay đổi số tiền đã thu.
5. Xử lý `automation_key` của ledger cancelled bằng cơ chế replacement, không xóa lịch sử.
6. Không tự động biến ledger pending 0 đồng thành đã trả nếu không xác định được lý do; xuất danh sách cần đối soát.
7. Chạy báo cáo kiểm tra trùng thời gian, khoảng trống, số tiền và số dư trước/sau migration.

## 17. Kế hoạch kiểm thử

### 17.1. Backend và dữ liệu

- `php -l` cho tất cả PHP thay đổi.
- `php artisan migrate:fresh --seed` trên DB test/dev được xác nhận an toàn.
- `php artisan test` và các nhóm test chuyên biệt.
- Kiểm tra số dư/ledger trước-sau transaction.
- Kiểm tra unique/idempotency khi command chạy đồng thời hoặc chạy lại.

### 17.2. Ma trận nghiệp vụ tối thiểu

1. Tạo kỳ tháng lần đầu và lần kế tiếp.
2. Scheduler chạy hai lần không tạo trùng.
3. Void kỳ tự động và tạo replacement không mất kỳ.
4. Thay giá tăng/giảm không đổi kỳ đã chốt.
5. Trả trước 3/6/12 tháng phân bổ đúng và chỉ nhận discount hợp lệ.
6. Gia hạn ba tháng vẫn tạo từng tháng, không nhận discount trả trước.
7. Đến trước/sau ngày cam kết chuyển overdue đúng.
8. Trial 30 ngày, trial 90 ngày và gia hạn trial đang chạy.
9. Kỳ miễn phí 0 đồng không tạo QR và không bị khóa.
10. Khuyến mại phần trăm/cố định, trần giảm và quy tắc cộng dồn.
11. Thanh toán toàn phần/một phần bằng số dư và chuyển khoản.
12. Không cho rút phần đang giữ cho booking/refund/phí.
13. Chấm dứt với hủy hết booking và với phục vụ booking cuối.
14. Chấm dứt khi còn nợ phí; không sinh văn bản cuối sai điều kiện.
15. Hủy chấm dứt; không mở sân trước khi quyết toán/Admin duyệt.
16. Quyền Admin/Staff/Owner và mã lỗi 403/409/422.

### 17.3. Frontend và trình duyệt

- `npm run build`.
- Kiểm thử ở `http://127.0.0.1:8000` bằng trình duyệt thật cho Admin và Owner.
- Kiểm tra responsive tối thiểu desktop và mobile hẹp.
- Kiểm tra bảng, form, modal/drawer, loading, empty, error, disabled và success state.
- Kiểm tra không hiển thị UUID/raw enum/raw JSON.
- Kiểm tra API error thực tế, không chỉ nút frontend bị disable.
- Lưu bằng chứng screenshot và danh sách route/role đã kiểm tra; không đánh đồng build thành browser pass.

## 18. Lộ trình commit và push

Mỗi commit chỉ chứa file liên quan đến một lát cắt, nội dung tiếng Việt rõ ràng:

1. `docs: lập kế hoạch tái thiết kế kỳ phí nền tảng`
2. `feat: bổ sung phiên bản bảng giá và quy tắc kỳ phí`
3. `feat: tách kỳ dịch vụ khỏi thỏa thuận gia hạn thanh toán`
4. `feat: hỗ trợ trial, miễn phí và khuyến mại phí nền tảng`
5. `feat: thanh toán phí bằng số dư và tạm giữ công nợ`
6. `feat: tích hợp phí nền tảng vào quyết toán chấm dứt`
7. `feat: thiết kế lại giao diện quản lý phí nền tảng`
8. `test: bổ sung kiểm thử tự động các luồng kỳ phí`
9. `fix: khắc phục lỗi phát hiện khi kiểm thử kỳ phí`

Trước mỗi commit:

- Xem `git diff` và `git status`.
- Không stage file test/rác/outputs không thuộc lát cắt.
- Chạy nhóm test phù hợp với lát cắt.
- Push đúng nhánh sau khi commit thành công.

## 19. Điều kiện nghiệm thu

- Không có kỳ dịch vụ trùng hoặc mất sau khi void/retry scheduler.
- Chủ sân và Admin luôn thấy ngày dịch vụ, hạn thanh toán, nguồn tạo và công thức tiền.
- Gia hạn công nợ không nhận ưu đãi trả trước.
- Kỳ 0 đồng không yêu cầu thanh toán và không bị dunning/khóa.
- Số tiền được rút luôn trừ đủ booking/refund/dispute/withdrawal/platform fee hold.
- Chấm dứt/hủy chấm dứt không xóa nợ hoặc tạo khoảng sử dụng miễn phí không chủ đích.
- Phiên bản giá mới không sửa lịch sử kỳ đã phát hành.
- Scheduler có test retry/idempotency và log quan sát được.
- API mutation có validation, permission, audit và mã lỗi phù hợp.
- Giao diện đồng nhất với SportGo và các luồng Admin/Owner chính vượt qua browser test.

## 20. Điểm cần phê duyệt trước khi code

Việc triển khai yêu cầu được phê duyệt rõ các thay đổi sau:

1. Được phép tạo migration/bảng/trường mới nhưng không drop/rename dữ liệu hiện có.
2. Được phép thay đổi state machine kỳ phí, gia hạn, waiver/write-off và hủy chấm dứt.
3. Được phép thay đổi công thức số tiền chủ sân được rút để trừ platform fee hold.
4. Được phép sửa policy evaluator/command quá hạn để loại kỳ voided, settled-zero và số dư nợ bằng 0.
5. Được phép mở rộng API hiện có theo hướng tương thích ngược.
6. Chốt chính sách ngày chốt phí khi chấm dứt như Mục 11.
7. Chốt gia hạn ba tháng mặc định có bảo đảm bằng số dư; tín dụng không bảo đảm chỉ là ngoại lệ hai cấp.

## 21. Nguồn tham khảo nghiệp vụ

- Stripe quản lý trial bằng ngày kết thúc chính xác và có invoice 0 đồng: <https://docs.stripe.com/billing/subscriptions/trials>
- Stripe tách billing-cycle anchor và proration: <https://docs.stripe.com/billing/subscriptions/billing-cycle>
- Stripe yêu cầu tạo Price mới thay vì sửa amount đã dùng: <https://docs.stripe.com/products-prices/manage-prices>
- Stripe lưu ý thứ tự discount cộng dồn ảnh hưởng kết quả: <https://docs.stripe.com/billing/subscriptions/coupons>
- Stripe Connect mô tả available/pending/reserve và yêu cầu khoản giữ có mục đích rõ ràng: <https://docs.stripe.com/connect/account-balances>
- Recurly tách ngày lập invoice khỏi Net terms/EOM terms và dunning: <https://docs.recurly.com/recurly-subscriptions/docs/automatic-invoicing-terms>
- Recurly version hóa dunning cho từng invoice: <https://docs.recurly.com/recurly-subscriptions/docs/dunning-management>
- Chargebee cho phép kéo dài trial đang chạy: <https://www.chargebee.com/docs/billing/2.0/subscriptions/trial_periods>
- Chargebee phân biệt coupon/manual discount với giá gốc: <https://www.chargebee.com/docs/billing/2.0/subscriptions/subscription-manual-discounts>

