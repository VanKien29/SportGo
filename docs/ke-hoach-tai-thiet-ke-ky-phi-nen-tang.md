# Kế hoạch tái thiết kế kỳ phí nền tảng SportGo

## 0. Trạng thái và cổng kiểm soát triển khai

Tài liệu được rà soát lại ngày 28/08/2026 sau khi đối chiếu code hiện có, phản hồi thao tác trực tiếp, benchmark Stripe/Chargebee/Recurly, tiêu chuẩn W3C/WCAG/GOV.UK/Nielsen Norman Group và checkpoint pháp lý Việt Nam liên quan.

- Rà soát vòng hai và tự kiểm tra mâu thuẫn đã hoàn tất; quyết định mặc định được khóa tại Mục 30.1 và có thể chuyển sang triển khai theo Mục 30.2.
- Các thay đổi code cục bộ đã có từ trước phải được đối chiếu lại với tài liệu này; không mặc nhiên coi bản thử cũ là đúng.
- Nếu dữ liệu thật hoặc hợp đồng hiện hành xung đột với quyết định làm thay đổi số tiền, quyền giữ/cấn trừ hoặc cách xuất hóa đơn, dừng đúng lát cắt đó và đưa ra bằng chứng thay vì tự suy đoán.
- Mục 22 trở đi là kết quả rà soát vòng hai và được ưu tiên nếu có điểm khác với mô tả cũ ở các mục trước.

## 1. Mục tiêu

Thiết kế lại nghiệp vụ phí nền tảng để bảo đảm:

- Giá và ưu đãi được quản lý theo phiên bản, có ngày bắt đầu áp dụng rõ ràng.
- Mỗi khoảng thời gian sử dụng dịch vụ chỉ được ghi nhận một lần và không bị thay đổi ngược lịch sử.
- Phân biệt rõ trả trước, trả đúng hạn, thỏa thuận trả chậm, miễn phí và khuyến mại.
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
- Thỏa thuận trả chậm có bảo đảm bằng số dư.
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
- `Thỏa thuận trả chậm` trả lời: SportGo cho phép khoản phải thu được trả muộn đến ngày nào.
- Không hủy hoặc gộp kỳ dịch vụ để biểu diễn việc trả muộn.

### 4.2. Chu kỳ dịch vụ, ngày phát hành và hạn thanh toán là ba khái niệm riêng

- `Mốc chu kỳ dịch vụ` xác định ngày bắt đầu một kỳ chuẩn; kỳ chuẩn kéo dài đến trước mốc tương ứng của tháng kế tiếp. Chỉ cho chọn ngày 1–28 để mọi tháng đều có ngày hợp lệ.
- `Phát hành trước kỳ` xác định hệ thống tạo khoản phải thu trước ngày bắt đầu dịch vụ bao nhiêu ngày.
- `Ngày đến hạn thanh toán` xác định ngày phải trả; đổi ngày này không làm tăng/giảm số ngày dịch vụ và không tạo proration.
- Nếu sản phẩm vẫn chọn tháng lịch, mốc chu kỳ dịch vụ là ngày 1 và kỳ chạy từ ngày 1 đến cuối tháng. Không được dùng nhãn “Ngày đóng kỳ” cho trường hạn thanh toán.
- Kỳ đầu, kỳ cuối hoặc kỳ cầu nối không tròn chu kỳ được tính theo ngày và phải hiển thị công thức trước khi xác nhận.
- Một lần trả trước 3/6/9/12 kỳ vẫn phải có các phân bổ kỳ con để khóa giá, số sân, discount và hoàn tiền chưa sử dụng chính xác.
- Không có hai phân bổ còn hiệu lực trùng ngày cho cùng một cụm sân.

### 4.3. Đổi mốc chu kỳ phải tự tạo kỳ cầu nối

- Không sửa trực tiếp kỳ đã phát hành và không để Admin tự tính kỳ lẻ.
- Khi mốc cũ đổi sang mốc mới, lấy ngày dịch vụ kế tiếp chưa được bao phủ và tự tạo một kỳ cầu nối đến ngày trước mốc mới gần nhất.
- Kỳ cầu nối dùng đơn giá của phiên bản có hiệu lực tại ngày bắt đầu kỳ cầu nối.
- Công thức mặc định theo ngày:

```text
phí_cầu_nối = phí_một_kỳ_đầy_đủ × số_ngày_cầu_nối / số_ngày_của_kỳ_tham_chiếu
```

- Một kỳ chuẩn luôn bằng đúng giá tháng, không thay đổi chỉ vì tháng có 28, 29, 30 hay 31 ngày.
- Nếu thay đổi tác động vào kỳ đã phát hành, không sửa đè số tiền. Hệ thống tạo dòng điều chỉnh debit/credit liên kết kỳ gốc hoặc buộc chọn ngày hiệu lực sau phần dịch vụ đã chốt.
- Trước khi lên lịch phải có preview cho ít nhất: kỳ cũ cuối cùng, kỳ cầu nối, kỳ mới đầu tiên, số ngày và số tiền chênh lệch.

### 4.4. Giá đã chốt không bị sửa ngược

- Mỗi kỳ lưu snapshot phiên bản giá, bậc phí, số sân, đơn giá và công thức.
- Phiên bản giá đã xuất bản không được chỉnh sửa.
- Tăng hoặc giảm giá chỉ áp dụng cho kỳ chưa phát hành bắt đầu từ ngày hiệu lực.
- Kỳ đã thanh toán hoặc đã được cấp miễn phí không tính lại khi giá thay đổi.

### 4.5. Hủy, miễn, giảm và xóa nợ là bốn nghiệp vụ khác nhau

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

`draft -> issued/pending -> paid`

Nhánh ngoại lệ:

- `issued/pending -> overdue`
- `draft/issued chưa có tiền -> voided`
- `issued/pending/overdue -> written_off` khi có phê duyệt xóa nợ
- Kỳ 0 đồng chuyển thẳng `draft -> settled_zero`, không tạo giao dịch thanh toán.

### 5.3. Thỏa thuận trả chậm có kiểm soát

`draft -> pending_owner_acceptance -> active -> fulfilled/expired/cancelled`

- Một cụm sân chỉ có tối đa một thỏa thuận `pending_owner_acceptance` hoặc `active` tại một thời điểm.
- Thỏa thuận phải có ngày thanh toán cụ thể, không dùng nội dung mơ hồ như “sau 3 tháng”.
- Quá hạn chỉ tính sau ngày cam kết.
- Thỏa thuận không thay đổi số tiền gốc và không được hưởng giảm trả trước.
- Giới hạn 1–3 là số kỳ được hoãn theo chính sách rủi ro, không phải số tháng dịch vụ được mua.
- Đề nghị chưa được chủ sân chấp thuận phải có thời điểm hết hạn và không được chặn bộ sinh kỳ vô thời hạn.

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

## 9. Thỏa thuận trả chậm từ một đến ba kỳ

### 9.1. Cách xử lý

- Không hủy kỳ tự động.
- Hệ thống tiếp tục tạo từng kỳ tháng.
- Các khoản phải thu được gắn vào một thỏa thuận với ngày thanh toán chung hoặc điều khoản Net-N đã được chọn rõ.
- Trước ngày cam kết: không overdue, không khóa do quá hạn, vẫn gửi nhắc trước hạn.
- Sau ngày cam kết: chuyển overdue và chạy chính sách dunning của phiên bản đã snapshot.

### 9.2. Kiểm soát rủi ro

- Mặc định là trả chậm có bảo đảm bằng số dư chủ sân.
- Không cho rút phần đã giữ cho phí nền tảng.
- Chỉ được rút phần dư sau tất cả nghĩa vụ booking/refund/dispute/withdrawal/fee.
- Luồng chuẩn chỉ được Owner chấp nhận khi số dư an toàn đủ bảo đảm 100% tổng nghĩa vụ. Nếu số dư thay đổi và không còn đủ tại lúc xác nhận thì trả `409/422`, không tạo hold một phần rồi âm thầm cấp tín dụng.
- Tín dụng không bảo đảm phải là ngoại lệ được hai cấp phê duyệt, có hạn mức và tài liệu chấp thuận riêng.
- Không cho tạo thỏa thuận mới khi có thỏa thuận quá hạn.
- Admin tạo thỏa thuận không được đồng thời tự duyệt ngoại lệ vượt hạn mức.

## 10. Thanh toán bằng số dư chủ sân

Thứ tự xử lý bản đầu:

1. Khoản điều chỉnh chỉ dùng cho phí nền tảng.
2. Nếu số dư an toàn của đúng cụm sân đủ toàn bộ, Owner có thể chọn hoặc bật tự động thanh toán bằng số dư.
3. Nếu không đủ toàn bộ, không trừ dở dang và thanh toán toàn bộ qua chuyển khoản/SePay.

Số dư an toàn:

```text
số_dư_an_toàn = max(
  0,
  available_balance
  - future_booking_liability
  - pending_refund_liability
  - dispute_hold
  - termination_hold
  - platform_fee_hold_khác
  - các_khoản_giữ_ưu_tiên_khác
)
```

- `available_balance` phải lấy từ wallet đã khóa. Nếu yêu cầu rút đang xử lý đã được chuyển khỏi `available_balance` thì không trừ lặp lại `pending_withdrawal_balance`; nếu mô hình dữ liệu khác thì công thức phải có adapter duy nhất và test đối soát.
- Bản đầu chỉ cho thanh toán toàn bộ số còn phải trả từ số dư. Nếu không đủ thì không debit dở dang và hướng dẫn chuyển khoản toàn bộ; thanh toán kết hợp chỉ mở khi đã có mô hình allocation/idempotency hoàn chỉnh.
- Debit số dư và cập nhật khoản phải thu phải nằm trong cùng transaction, có `lockForUpdate` và idempotency key.
- Mọi debit/release/reversal có ledger trước/sau, actor và reference đến kỳ phí.
- Hoàn tiền trả về đúng nguồn thanh toán ban đầu trong giới hạn số đã trả từ nguồn đó: phần trả bằng số dư quay lại đúng số dư cụm sân; phần chuyển khoản hoàn theo quy trình chuyển khoản. Chỉ ghi phần chuyển khoản vào số dư khi Owner đồng ý và kế toán/pháp lý phê duyệt quy tắc đó.

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
- Nợ phí phải được thanh toán hoặc có thỏa thuận trả chậm active.
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
- Kỳ `voided`, `settled_zero`, `paid` và số còn phải trả bằng 0 không được đưa vào dunning.
- Thỏa thuận trả chậm active dùng ngày cam kết làm hạn, không dùng hạn cũ.
- Mỗi khoản phải thu giữ phiên bản dunning khi bắt đầu quá hạn.

## 13. Giao diện mục tiêu

### 13.1. Admin

Giữ layout, button, form control, table, badge, modal và màu SportGo hiện có; không tạo một design system mới.

- Điều hướng cấp mô-đun gồm năm mục: `Phiên bản & bậc phí`, `Kỳ phí`, `Thỏa thuận trả chậm`, `Ưu đãi phí`, `Chính sách áp dụng`. Không tạo mục “Cài đặt nhắc phí” riêng nếu cùng dữ liệu đã nằm trong chính sách.
- Màn `Phiên bản & bậc phí` là trang danh sách, không nhúng toàn bộ form cấu hình. Bảng gồm: STT, mã/tên, ngày áp dụng, ngày kết thúc được suy ra, trạng thái, người cập nhật, thời điểm cập nhật và thao tác.
- Trang chi tiết phiên bản là trang đầy đủ vì có nhiều nhóm dữ liệu: thông tin chung, mốc chu kỳ, trial, bậc phí, giảm trả trước, thông báo và preview tác động.
- Màn `Kỳ phí` dùng một nguồn dữ liệu ledger, không tách thành nhiều bảng vật lý. Các tab chỉ là chế độ lọc: `Cần xử lý`, `Sắp tới`, `Đã hoàn tất`, `Lịch sử`; tiếp tục lọc được theo cụm sân, chủ sân, nguồn tạo, loại kỳ, trạng thái, ngày dịch vụ, hạn thanh toán và thỏa thuận.
- Chi tiết kỳ có thể dùng drawer khi chỉ xem nhanh; chuyển sang trang chi tiết khi có lịch sử, điều chỉnh hoặc chứng từ. Nội dung phải có phí gốc, từng dòng giảm, nguồn thanh toán, lịch sử trạng thái và log tự động.
- Màn `Thỏa thuận trả chậm` là trang danh sách riêng với mã, cụm sân/chủ sân, số kỳ hoãn, tổng nghĩa vụ, số đang giữ, hạn cam kết, trạng thái và thao tác. Có tìm kiếm/lọc theo mã, cụm sân, chủ sân, trạng thái và hạn trả.
- Màn `Ưu đãi phí` quản lý campaign promotion và phân bổ cụm sân; waiver/xóa nợ vẫn là hành động trên kỳ cụ thể, không gom vào campaign.
- Tạo thỏa thuận dùng modal nhiều bước vì luồng tạo ngắn nhưng có giao dịch tài chính: chọn cụm sân → xem các kỳ đủ điều kiện → chọn 1/2/3 kỳ bằng radio card → xem lại tiền/giữ số dư/hạn trả → gửi chủ sân xác nhận. Nếu sau này thêm hồ sơ, nhiều cấp duyệt hoặc tài liệu dài, chuyển thành trang tạo riêng thay vì kéo dài modal.
- Hành động kỳ phí tách rõ: `Điều chỉnh hạn`, `Tạo thỏa thuận trả chậm`, `Void và tạo bản thay thế`, `Miễn phí`, `Xóa nợ`; không dùng một nút hủy chung cho các ý nghĩa khác nhau.
- Form không bắt Admin “Lưu nháp” rồi mới “Lên lịch”. Có nút phụ `Lưu nháp` và nút chính `Xem trước & lên lịch`; nút chính gửi toàn bộ dữ liệu trong một transaction sau bước review, nhưng vẫn lưu lại dữ liệu nhập nếu API báo lỗi.
- Màn chấm dứt thêm thẻ công nợ phí, phí dự kiến đến ngày chốt, số giữ và số được rút.

### 13.2. Owner

- Tổng quan: kỳ đang dùng, ngày kết thúc trial, kỳ sắp tới, hạn thanh toán và tổng số bị giữ.
- Chi tiết kỳ: khoảng dịch vụ, snapshot giá/số sân, phí gốc, discount/promotion, số phải trả và lịch sử.
- Nút “Dùng số dư chủ sân” chỉ hiện khi có số dư an toàn.
- Tùy chọn tự động thanh toán bằng số dư theo từng cụm sân, mặc định tắt.
- Gia hạn được hiển thị bằng badge và ngày cam kết, không gọi là trả trước.
- Kỳ 0 đồng hiển thị “Miễn phí – đã hoàn tất”, không có nút thanh toán.
- Khi chấm dứt, hiển thị công thức quyết toán và ngày chốt trước khi ký.

### 13.3. Quy tắc thao tác theo trạng thái phiên bản

| Trạng thái | Xem | Sửa cấu hình | Đổi tên | Xóa | Hành động hợp lệ |
|---|---:|---:|---:|---:|---|
| Nháp | Có | Có | Có | Có điều kiện | Lưu nháp, xem trước và lên lịch, nhân bản |
| Sắp áp dụng | Có | Không | Không | Không | Hủy lịch về nháp nếu chưa đến thời điểm khóa, nhân bản |
| Đang áp dụng | Có | Không | Không | Không | Nhân bản thành nháp mới |
| Đã ngừng | Có | Không | Không | Không | Nhân bản thành nháp mới |

- Chỉ xóa nháp chưa từng được công bố, chưa có kỳ/notification/audit reference. Nếu đã có reference thì vô hiệu nháp hoặc giữ lịch sử, không xóa cứng.
- Ngày kết thúc của phiên bản được hệ thống suy ra là ngày trước phiên bản kế tiếp có hiệu lực; không cho nhập tay hai đầu mốc vì dễ tạo khoảng trống hoặc chồng lấn.
- Giai đoạn đầu chỉ cho một phiên bản `scheduled` để giảm lỗi vận hành. Mô hình dữ liệu vẫn phải hỗ trợ nhiều phiên bản lịch sử; chỉ mở nhiều lịch tương lai sau khi có nhu cầu thật và thuật toán kiểm tra toàn bộ timeline.

### 13.4. Thông báo, xác nhận và lỗi form

- Không dùng `window.alert`/`window.confirm`. Dùng `ConfirmModal` hiện có cho công bố giá, void, xóa nháp, chấp nhận thỏa thuận, thanh toán và các thao tác có hậu quả tiền/trạng thái.
- Lưu nháp thành công chỉ dùng toast/status message ngắn; không bật modal xác nhận và không chèn thêm một banner xanh cố định.
- Mỗi trang tối đa một banner cấp trang. Hướng dẫn trực tiếp cho trường nào đặt ngay dưới trường đó; lỗi có error summary ở đầu form và lỗi inline tại đúng trường.
- Nút xác nhận phải mô tả kết quả, ví dụ `Lên lịch áp dụng từ 05/10/2026`, không dùng `OK` chung chung.
- Modal review cho hành động tài chính/công bố hiển thị dữ liệu đã nhập theo nhóm và cho phép quay lại sửa mà không mất dữ liệu.
- Status message có `role="status"`/live region phù hợp, không tự lấy focus và không đọc lặp lại các thông báo không quan trọng.
- Modal phải có `role="dialog"`, `aria-modal`, tên truy cập được, giữ Tab/Shift+Tab trong modal, hỗ trợ Escape khi an toàn và trả focus về nút đã mở; dialog giao dịch khó đảo ngược đặt focus ban đầu vào hành động ít nguy hiểm hơn.

## 14. Thay đổi dữ liệu dự kiến

Người dùng đã cho phép sửa schema/migration/data cho phạm vi này. Quyền đó không thay thế bước preflight: phải kiểm kê migration hiện có, tạo migration nhỏ và không drop/rename trường hay UUID hiện có.

- Bảng phiên bản bảng giá.
- Liên kết bậc phí với phiên bản bảng giá.
- Bảng quy tắc giảm trả trước.
- Bảng chương trình trial/khuyến mại và phân bổ áp dụng.
- Bảng phân bổ kỳ dịch vụ theo tháng/ngày.
- Bảng thỏa thuận trả chậm và các khoản phải thu thuộc thỏa thuận.
- Bảng khoản tạm giữ phí nền tảng hoặc mở rộng owner wallet ledger theo cấu trúc hiện có.
- Trường replacement/void reason/state snapshot cần thiết trên ledger hiện tại.
- Trường ngày chốt phí và snapshot quyết toán trên hồ sơ chấm dứt.

Không thay UUID PK/FK và không xóa dữ liệu lịch sử.

## 15. API dự kiến

Ưu tiên mở rộng route hiện có, tránh đổi URL đang được frontend sử dụng.

- CRUD draft/schedule/cancel schedule cho phiên bản giá.
- Preview tác động trước khi schedule.
- Preview/tạo thỏa thuận trả chậm; owner accept; admin approve/cancel.
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
11. Thanh toán toàn phần bằng số dư; thiếu số dư không debit dở dang và chuyển khoản xử lý đúng.
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
3. `feat: tách kỳ dịch vụ khỏi thỏa thuận trả chậm`
4. `feat: hỗ trợ trial, miễn phí và khuyến mại phí nền tảng`
5. `feat: thanh toán phí bằng số dư và tạm giữ công nợ`
6. `feat: tích hợp phí nền tảng vào quyết toán chấm dứt`
7. `feat: thiết kế lại giao diện quản lý phí nền tảng`
8. `fix: khắc phục lỗi phát hiện khi kiểm thử kỳ phí`

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

## 20. Phạm vi quyền đã có và giới hạn còn giữ

Người dùng đã cho phép migration, data và triển khai toàn bộ mô-đun phí nền tảng. Trong phạm vi đó kế hoạch được phép:

1. Tạo bảng/trường mới bằng migration nhỏ, không drop/rename dữ liệu hiện có và không đổi UUID PK/FK.
2. Hoàn thiện state machine kỳ phí, thỏa thuận trả chậm, waiver/write-off và nhánh quyết toán chấm dứt theo đặc tả này.
3. Thay đổi công thức số tiền chủ sân được rút để trừ platform fee hold.
4. Sửa policy evaluator/command quá hạn để loại kỳ voided, settled-zero và số dư nợ bằng 0.
5. Mở rộng API theo hướng tương thích ngược; không đổi cấu trúc public route không cần thiết.

Không tự mở rộng sang sửa công thức booking/refund/withdrawal nói chung, auth/RBAC hay văn bản pháp lý ngoài phần tích hợp tối thiểu cần cho phí nền tảng. Những thay đổi đó chỉ được chạm khi có bằng chứng là dependency trực tiếp và phải báo rõ trong diff.

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
- Stripe mô tả proration debit/credit, ảnh hưởng của invoice chưa trả và API preview trước khi áp dụng: <https://docs.stripe.com/billing/subscriptions/prorations>
- Stripe dùng schedule/phases để lên lịch thay đổi tương lai thay vì sửa lịch sử: <https://docs.stripe.com/billing/subscriptions/subscription-schedules>
- Chargebee đổi ngày billing có thể sinh charge khi kéo dài hoặc credit khi rút ngắn: <https://www.chargebee.com/docs/billing/2.0/subscriptions/next-billing-date>
- Chargebee phân biệt adjustment credit cho invoice chưa trả và refundable credit cho invoice đã trả: <https://www.chargebee.com/docs/billing/2.0/subscriptions/proration>
- Chargebee yêu cầu preview các term trước khi xác nhận advance invoice và không sửa trực tiếp invoice đã phát hành: <https://www.chargebee.com/docs/billing/2.0/invoices-credit-notes-and-quotes/advance-invoices>
- Chargebee mô tả adjustment/refundable credit note: <https://www.chargebee.com/docs/billing/2.0/invoices-credit-notes-and-quotes/credit-notes>
- W3C WCAG 3.3.4 yêu cầu giao dịch tài chính có ít nhất cơ chế đảo ngược, kiểm tra lỗi hoặc xem lại/xác nhận: <https://www.w3.org/WAI/WCAG22/Understanding/error-prevention-legal-financial-data>
- W3C WCAG 3.3.1 yêu cầu chỉ rõ trường lỗi và mô tả lỗi bằng chữ: <https://www.w3.org/WAI/WCAG22/Understanding/error-identification>
- W3C WCAG 4.1.3 yêu cầu status message được nhận biết mà không ngắt thao tác không cần thiết: <https://www.w3.org/WAI/WCAG21/Understanding/status-messages>
- GOV.UK khuyên chỉ dùng select khi danh sách dài; với ít lựa chọn nên ưu tiên radio: <https://design-system.service.gov.uk/components/select/> và <https://design-system.service.gov.uk/components/radios/>
- GOV.UK khuyên dùng notification banner tiết chế, lỗi validation phải đặt ở error summary/field: <https://design-system.service.gov.uk/components/notification-banner/>
- GOV.UK khuyên có bước kiểm tra lại dữ liệu trước khi gửi và cho quay lại sửa: <https://design-system.service.gov.uk/patterns/check-answers/>
- Nielsen Norman Group khuyên nêu rõ khác biệt giữa các lựa chọn thay vì bắt người dùng tự suy luận: <https://www.nngroup.com/articles/explicit-differences/>
- WAI-ARIA APG quy định modal phải giữ focus bên trong, hỗ trợ Escape và trả focus về điểm gọi khi đóng: <https://www.w3.org/WAI/ARIA/apg/patterns/dialog-modal/>
- GOV.UK yêu cầu error summary liên kết tới đúng field và đồng thời có lỗi inline: <https://design-system.service.gov.uk/components/error-summary/>
- Nielsen Norman Group nêu các nguyên tắc hiển thị trạng thái, khớp ngôn ngữ người dùng, ngăn lỗi và ưu tiên nhận biết hơn ghi nhớ: <https://www.nngroup.com/articles/ten-usability-heuristics/>
- Bộ luật Dân sự 2015, Điều 421 là checkpoint pháp lý cho việc sửa đổi hợp đồng: các bên có thể thỏa thuận sửa đổi và hình thức sửa đổi phải theo hình thức hợp đồng ban đầu. Vì vậy email/notification không tự động thay thế phụ lục hoặc chấp thuận nếu hợp đồng SportGo đang yêu cầu: <https://vbpl.vn/TW/Pages/vbpq-toanvan.aspx?ItemID=95942>
- Nghị định 70/2025/NĐ-CP sửa quy định hóa đơn, chứng từ và có hiệu lực từ 01/06/2025. Trước production, kế toán phải ánh xạ rõ kỳ dịch vụ, thời điểm lập hóa đơn và chứng từ điều chỉnh/thay thế; ledger nội bộ không được coi mặc nhiên là hóa đơn hợp lệ: <https://vanban.chinhphu.vn/?docid=213179&lang=vi&pageid=27160>
- Nghị định 13/2023/NĐ-CP là checkpoint bảo vệ dữ liệu cá nhân cho danh sách người nhận, email, IP/user-agent và log chấp thuận; note nội bộ không được đưa vào notification Owner: <https://vanban.chinhphu.vn/?classid=0&docid=207759&pageid=27160>

Các nguồn sản phẩm/UX ở trên là benchmark, không phải ý kiến pháp lý cho hợp đồng B2B của SportGo. Các văn bản Việt Nam chỉ được dùng làm checkpoint, không đủ để kết luận toàn bộ tính tuân thủ. Thời hạn báo trước, quyền giữ tiền, write-off, xuất/điều chỉnh hóa đơn và điều khoản chấm dứt phải khớp hợp đồng/chính sách đã được người có thẩm quyền pháp lý-kế toán duyệt trước khi vận hành thật.

## 22. Đánh giá phản biện các đề xuất giao diện và nghiệp vụ

Các đề xuất của người dùng là đầu vào nghiên cứu, không mặc định là đặc tả cuối. Kết luận dưới đây dựa trên rủi ro tiền, khả năng truy vết và độ dễ dùng.

| Đề xuất | Đánh giá | Quyết định kế hoạch |
|---|---|---|
| Tách phiên bản thành màn danh sách | Hợp lý. Form hiện tại đặt chọn phiên bản, cấu hình, bậc phí và lọc trong cùng màn làm mất cấu trúc thông tin | Dùng list page + detail page; không dùng master-detail dày đặc trên một màn |
| Danh sách có ngày bắt đầu/kết thúc | Hợp lý nếu ngày kết thúc không cho nhập tùy ý | Hiển thị `effective_to` được suy ra từ phiên bản kế tiếp; backend kiểm tra timeline không chồng lấn |
| Draft được xem/sửa/xóa, bản đã công bố chỉ xem | Phù hợp với cách Stripe giữ lịch sử giá; cần thêm trạng thái scheduled | Draft được sửa/xóa có điều kiện; scheduled chỉ xem/hủy lịch; active/retired chỉ xem/nhân bản |
| Tạo thỏa thuận trong popup | Chỉ hợp lý khi form ngắn và có review. Modal quá dài sẽ lặp lại vấn đề hiện tại | Modal nhiều bước cho tạo mới; trang/drawer riêng cho chi tiết, lịch sử và xử lý ngoại lệ |
| Tách các bảng kỳ cũ, đã đóng, sắp tới, thỏa thuận | Hợp lý về cách nhìn nhưng không nên tạo các nguồn dữ liệu/bảng DB khác nhau | Một ledger, nhiều tab/filter đã định nghĩa sẵn; có badge số lượng và giữ bộ lọc trên URL |
| Đổi ngày 15 sang ngày 5 thì hệ thống tự cộng/trừ | Hợp lý nếu đây là mốc chu kỳ dịch vụ; không hợp lý nếu chỉ là hạn thanh toán | Tách ba trường thời gian. Chỉ đổi mốc dịch vụ mới sinh kỳ cầu nối/proration; đổi hạn thanh toán không đổi phí |
| Dùng số dư chủ sân thay vì tạo thêm credit | Hợp lý với tiền thực đã hoàn; không đủ để thay thế chứng từ điều chỉnh invoice | Không tạo một “ví credit” chung mới. Dùng adjustment trên kỳ chưa trả; tiền hoàn thực vào số dư chủ sân/nguồn gốc, có ledger và giới hạn rút |
| Chỉ chọn 1/2/3 tháng trả chậm | Giới hạn có thể hợp lý về rủi ro nhưng nhãn và cách thể hiện hiện tại sai nghĩa | Đổi thành `Số kỳ được hoãn`, radio 1/2/3; giới hạn lấy từ policy, không hard-code rải rác |
| Lưu xong mới lên lịch | Không hợp lý vì tạo thao tác thừa và dễ lệch dữ liệu | `Lưu nháp` và `Xem trước & lên lịch`; thao tác sau lưu + schedule nguyên tử sau review |
| Thông báo xanh/note lặp lại | Không hợp lý; người dùng dễ bỏ qua banner và màn hình trở nên ồn | Một banner cấp trang tối đa, hint inline, toast cho kết quả ngắn, error summary cho lỗi |
| Áp dụng giá mới phải thông báo Owner | Hợp lý và cần thiết để giảm tranh chấp | Thông báo lúc lên lịch và nhắc gần ngày áp dụng; activation không gửi trùng nếu không có nội dung mới |
| Admin cho dùng ba kỳ rồi mới đóng | Có thể hỗ trợ nhưng là cấp tín dụng, không phải trả trước; nếu không giữ tiền có rủi ro nợ | Mặc định chỉ cho thỏa thuận có bảo đảm bằng số dư an toàn; ngoại lệ không bảo đảm cần hai cấp phê duyệt ngoài luồng chuẩn |

## 23. Kết quả rà soát code và dữ liệu hiện tại vòng hai

Các điểm dưới đây được xác nhận trên code hiện có ngày 28/08/2026. Đây là danh sách phải xử lý hoặc có quyết định rõ trước khi coi mô-đun đạt nghiệp vụ.

### 23.1. Mức nghiêm trọng cao – có thể tính sai tiền hoặc tạo tranh chấp

1. `VenuePlatformFeeProfile.billing_anchor_day` đang được tạo mặc định là 1 nhưng `PlatformFeePeriodService` vẫn chốt kỳ bằng `endOfMonth()`. Hệ thống chưa thực sự hỗ trợ chu kỳ 15→14 hoặc chuyển mốc 15→5.
2. `PlatformFeePricingService` lấy mẫu số `daysInMonth` của ngày bắt đầu. Kỳ cầu nối đi qua hai tháng sẽ dùng sai kỳ tham chiếu nếu áp dụng mốc chu kỳ khác ngày 1.
3. `invoice_lead_days` nằm trong phiên bản và hiển thị trên UI, nhưng bộ sinh kỳ đang đọc `config('platform_fee.automatic_lead_days')`; thay cấu hình phiên bản không thay thời điểm tạo kỳ thực tế.
4. Phiên bản có thể hiệu lực giữa một kỳ. `planFor()` chọn theo ngày bắt đầu kỳ và kỳ tự động vẫn chạy đến cuối tháng, nên phiên bản mới có thể không tác động theo cách Admin/Owner hiểu từ nhãn ngày áp dụng.
5. Tổng quan Owner đang ước tính từ toàn bộ `PlatformFeeTier` active, không giới hạn đúng phiên bản giá hiệu lực; có thể trộn bậc của nhiều version.
6. Tổng quan Owner chỉ áp dụng `annual_discount_percent` cho 12 tháng, trong khi phiên bản có quy tắc 3/6/9/12; số preview có thể khác số backend tạo.
7. Trả trước chia theo tháng lịch. Nếu bắt đầu giữa tháng, lựa chọn “3 tháng” có thể tạo bốn dòng phân bổ; promotion còn bị tiêu thụ theo từng dòng thay vì theo định nghĩa kỳ được duyệt.
8. Đề nghị thỏa thuận hiện tạo các kỳ dự kiến trước khi Owner chấp thuận và chưa có `expires_at`/job hết hạn. Đề nghị bị bỏ quên có thể chặn scheduler vô thời hạn.
9. Hủy thỏa thuận active chưa trả có thể void toàn bộ kỳ ngay cả khi dịch vụ đã bắt đầu; điều này tạo khoảng sử dụng không thu phí.
10. Khi cụm sân vào trạng thái chấm dứt/khóa, scheduler chỉ nhận `active` nên ngừng tạo kỳ; trường phí lẻ chấm dứt hiện chưa được tạo thành nghĩa vụ thực.
11. Phần hoàn trả trước chưa sử dụng ở chấm dứt mới có nguy cơ chỉ là số tổng hợp; phải có ledger/debit-credit/wallet movement thật thì mới quyết toán được.
12. Kiểm tra overlap chủ yếu ở tầng ứng dụng. Nếu auto, trả trước và thỏa thuận chạy đồng thời, hai transaction có thể cùng vượt qua kiểm tra trước khi insert nếu không có khóa theo cụm sân và ràng buộc dữ liệu tương ứng.
13. Trial hiện giữ timestamp `addDays(N)-1 second` nhưng service period chỉ lưu ngày bao gồm cả hai đầu; activation giữa ngày có nguy cơ diễn giải 30×24 giờ thành 31 ngày lịch hoặc tạo điểm giao không nhất quán. Phải chốt một quy ước ngày và test off-by-one.
14. `OwnerWalletService` cộng tiền booking online vào `available_balance` ngay khi payment paid, kể cả booking tương lai. `PlatformFeeWalletService::withdrawableAmount()` hiện chỉ trừ platform-fee hold, chưa trừ booking tương lai/refund/dispute; do đó cùng một khoản tiền có thể bị dùng trả phí/rút rồi thiếu nguồn hoàn booking.
15. Auto-pay hiện chạy ngay trong lúc phát hành kỳ, có thể sớm hơn hạn nhiều ngày. Lỗi thiếu số dư bị catch bỏ qua không có event/log riêng; sau đó notification “kỳ mới” vẫn có thể ghi số cần trả 0 nếu auto-pay thành công. Owner không nhận được diễn giải đúng trạng thái thanh toán.
16. Kỳ thường chỉ được tạo hold khi đã overdue, chưa đáp ứng quyết định “dịch vụ đã bắt đầu nhưng chưa trả thì giữ nghĩa vụ”. Ngược lại `ensureLedgerHold()` luôn ghi toàn bộ outstanding dù wallet có thể không đủ, nên số hiển thị “đã giữ” có thể lớn hơn tiền thực sự bảo đảm.
17. Job tạo kỳ gọi cùng một notification cho kỳ trial/khuyến mại 0 đồng, kỳ pending và kỳ vừa auto-paid. Nội dung hiện dùng câu “số tiền cần thanh toán”, không phân nhánh 0 đồng/đã trả nên dễ gây tranh chấp hoặc tạo nút hành động sai.

### 23.2. Mức trung bình – sai ngữ nghĩa, thiếu kiểm soát hoặc UX gây lỗi

1. `due_day` đang được dùng như ngày đến hạn, không phải ngày chu kỳ; nhãn cũ “Ngày đóng kỳ” gây hiểu nhầm.
2. Ngày bắt đầu thỏa thuận được nhập tùy ý nhưng backend chuẩn hóa bằng `startOfMonth()`; dữ liệu lưu có thể khác điều Admin nhập mà không báo lỗi.
3. Form thỏa thuận chưa có preview API cho từng kỳ, giá, promotion, tiền bị giữ và phần còn rút được.
4. `approved_by` có thể trùng người đề nghị; chưa phải cơ chế hai cấp cho tín dụng không bảo đảm.
5. Owner acceptance chỉ lưu người/thời điểm; thiếu revision điều khoản, snapshot tiền, IP và user agent để xử lý tranh chấp.
6. Promotion chưa có quy tắc rõ `applies_to_deferred`; thỏa thuận trả chậm có thể vô tình hưởng khuyến mại vốn chỉ dành cho trả thường/trả trước.
7. Job thông báo phiên bản dùng khóa tương đương `user + type + plan`; hủy lịch rồi lên lịch lại cùng plan có thể không gửi nội dung/ngày hiệu lực mới.
8. Chưa có run log bền vững và dry-run endpoint cho scheduler dù kế hoạch cũ đã yêu cầu.
9. Admin đang phải xử lý form thỏa thuận, KPI, bộ lọc và bảng ledger trong cùng trang; cấu trúc thao tác bị lẫn.
10. Một số chuỗi tiếng Việt trong `AdminVenuePlatformFees.vue` có dấu hiệu lỗi encoding/mojibake.
11. Có hai nguồn cấu hình nhắc/khóa phí: `platform_fee_settings` và policy `platform_fee`; cần một nguồn chuẩn duy nhất.
12. Các thao tác quan trọng vẫn còn `window.confirm`, không nhất quán với `ConfirmModal` của hệ thống.
13. Validation plan hiện chỉ giới hạn độ dài mã/tên; chưa ép trim/min/uppercase/pattern, chưa có optimistic revision và endpoint schedule chỉ nhận ngày nên UI buộc gọi update rồi schedule thành hai mutation.
14. Danh sách plan chưa có search/date/pagination; danh sách arrangement cho nhận status chuỗi tùy ý và thiếu lọc mã/Owner/hạn.
15. Xóa draft mới kiểm tra ledger nhưng plan có tier child dùng foreign key `restrictOnDelete`; draft có bậc phí có thể lỗi DB thay vì trả 409 dễ hiểu nếu service không xóa child trong transaction.
16. Hủy arrangement không yêu cầu lý do, không phân biệt người hủy/nhánh trước-sau dịch vụ và mã arrangement tạo bằng `max(id)+1` cần kiểm tra cạnh tranh đồng thời.
17. Permission resolver đang gom mọi mutation plan ngoài create vào `platform_fee.update`; xóa draft và schedule/cancel schedule chưa lần lượt dùng `platform_fee.delete`/`platform_fee.process`, nên quyền thao tác chưa khớp mức hậu quả.
18. `platform-fees:generate` chỉ tạo tối đa một kỳ thiếu cho mỗi cụm sân trong một lần chạy hằng ngày. Sau downtime dài, hệ thống có thể mất nhiều ngày mới lấp đủ timeline; chưa có bounded catch-up loop hoặc báo cáo backlog.
19. Command áp chính sách chạy mỗi phút nhưng lịch chưa có `withoutOverlapping()`/`onOneServer()`, đồng thời quét toàn bộ cụm sân và query từng cụm. Có rủi ro chạy chồng, N+1 và cập nhật trạng thái cạnh tranh khi dữ liệu lớn.
20. Môi trường local hiện dùng `QUEUE_CONNECTION=sync`; email/job chạy ngay không chứng minh được queue worker, retry, failed-job và độ bền production. Job thông báo phiên bản còn gửi mail trực tiếp trong một job lớn, chưa có delivery log riêng cho từng Owner/channel.
21. `limited` và `blocked` restriction đều đang ép `venue_clusters.status = locked`. Nếu middleware đọc status thay vì `access_mode`, mức “hạn chế” có thể trở thành khóa toàn phần; cần kiểm thử quyền cụ thể, không chỉ nhìn bản ghi restriction.

### 23.3. Khoảng trống kiểm thử hiện tại

- Test tracked chủ yếu bao phủ tier/ledger cũ và còn kỳ vọng `platform_fee_settings`.
- Chưa có bộ test tracked hoàn chỉnh cho version schedule/activate/cancel, chuyển anchor, trial-to-anchor, arrangement expiry, hold, termination proration và notification revision.
- Chưa có concurrency test chứng minh auto/prepay/arrangement không tạo khoảng ngày chồng lấn.
- Chưa có browser matrix được lưu bằng chứng cho cả Admin và Owner trên các route mới.

### 23.4. Thành phần hiện có cần tái sử dụng và phần còn thiếu

Đã có thể tái sử dụng:

- Các bảng `platform_fee_plan_versions`, `platform_fee_prepay_discount_rules`, `platform_fee_service_periods`, `venue_platform_fee_profiles`, promotion/assignment, arrangement/link, wallet hold và các trường settlement trên ledger/termination.
- API Admin cho plan, tier, ledger, arrangement; API Owner cho overview, prepay, arrangement, payment và pay-from-balance.
- Component `ConfirmModal`, `SaaSFilterBar`, `SaaSTable`, `PaginationBar`, `AdminFilterPanel`, badge/card và style dashboard hiện có.

Không tạo lại các cấu trúc này. Cần migration bổ sung nhỏ hoặc mở rộng code cho các thiếu hụt đã xác nhận:

- Plan chưa có revision và billing anchor; `effective_to` chưa được bảo vệ như giá trị suy ra.
- Arrangement chưa có `expires_at`, accepted snapshot/IP/user-agent và status history; `service_months`/`credit_limit` đang mang tên dễ hiểu sai nhưng phải giữ tương thích, có thể bổ sung alias/field mới thay vì rename phá vỡ.
- Promotion chưa khai báo `applies_to_deferred` và cách áp vào kỳ cầu nối.
- Hold chưa tách original/remaining/consumed và chưa có movement reference đầy đủ.
- Chưa có bảng adjustment/refund liên kết kỳ, automation run/result và notification outbox/revision.
- Service period chưa có replacement reference tường minh; công thức bridge có thể snapshot JSON nhưng các trường dùng lọc/đối soát phải có cột/index phù hợp.
- `ConfirmModal` hiện có role cơ bản nhưng chưa có tên truy cập gắn với heading, Escape, focus trap, focus return, trạng thái loading và chặn double-submit; phải nâng component dùng chung trước khi dùng cho giao dịch phí.
- Điều hướng hiện mới có ba mục và arrangement đang nằm trong màn ledger; cần route/list page riêng nhưng giữ redirect tương thích cho link cũ.

## 24. Đặc tả nghiệp vụ mục tiêu sau rà soát

### 24.1. Ba trục ngày tháng độc lập

| Khái niệm | Ý nghĩa | Có làm đổi tiền dịch vụ? |
|---|---|---:|
| Mốc chu kỳ dịch vụ | Chia thời gian sử dụng thành các kỳ đầy đủ | Có, nếu đổi mốc phải tính kỳ cầu nối |
| Số ngày phát hành trước | Scheduler tạo khoản phải thu trước kỳ bao nhiêu ngày | Không |
| Hạn thanh toán | Ngày cuối cùng Owner phải thanh toán trước khi overdue | Không |

- Mốc chu kỳ chỉ nhận 1–28.
- Hạn thanh toán phải được tính bằng quy tắc rõ, ưu tiên `N ngày sau ngày phát hành` hoặc một ngày cố định không sớm hơn ngày phát hành. Không dùng cùng một trường cho cả hai kiểu.
- Bản đầu chọn ngày đến hạn cố định theo yêu cầu vận hành: `due_day` 1–28. `due_date` là lần xuất hiện đầu tiên của ngày đó không trước `period_start`; ngày phát hành phải trước hoặc bằng `due_date`. Ví dụ kỳ bắt đầu ngày 1 và `due_day = 5` thì hạn là ngày 5 cùng tháng; kỳ bắt đầu ngày 15 thì hạn là ngày 5 tháng kế tiếp.

### 24.2. Thay đổi phiên bản giá tăng/giảm liên tục

- Mỗi kỳ chọn phiên bản theo `period_start` và lưu snapshot. Một kỳ đã issued/paid/prepaid không bị tính lại khi phiên bản sau tăng hay giảm.
- Phiên bản mới không áp ngược vào dịch vụ đã chốt. Nếu tăng rồi giảm rồi tăng, timeline vẫn tuần tự; mỗi kỳ chỉ gắn đúng một version.
- Giảm giá tương lai không tạo tiền thừa cho kỳ cũ. Tăng giá tương lai không truy thu kỳ cũ.
- Nếu phát hiện sai giá trên kỳ issued chưa trả, tạo adjustment debit/credit liên kết kỳ gốc. Không sửa số tiền lịch sử và không tạo khoản rút tiền tự do.
- Nếu kỳ đã trả và dịch vụ đã dùng, không điều chỉnh vì thay chính sách sau đó. Nếu kỳ đã trả nhưng phần dịch vụ tương lai bị hủy/chấm dứt, hoàn phần ròng chưa dùng theo phân bổ.
- Scheduled version phải có preview số cụm sân, kỳ đầu dự kiến, thay đổi tiền và notification. Hủy lịch tạo revision mới và không làm mất lịch sử thông báo.

### 24.3. Thuật toán kỳ cầu nối/proration

Khi đổi mốc chu kỳ hoặc kết thúc trial giữa kỳ:

1. Tìm `next_uncovered_date` của từng cụm sân.
2. Tìm cửa sổ chu kỳ mới chứa ngày đó: từ mốc mới gần nhất trước/đúng ngày đó đến ngày trước mốc mới kế tiếp.
3. Kỳ cầu nối là `next_uncovered_date` đến cuối cửa sổ trên.
4. Nếu kỳ cầu nối bao phủ toàn bộ cửa sổ, thu đúng giá một kỳ đầy đủ; nếu chỉ là một phần, dùng công thức actual-day:

```text
daily_denominator_days = số ngày của toàn bộ cửa sổ chu kỳ mới chứa kỳ cầu nối
bridge_base_amount = giá_kỳ_đầy_đủ × bridge_service_days / daily_denominator_days
```

5. Làm tròn VND ở cấp dòng theo một quy tắc duy nhất; snapshot cả tử số, mẫu số, giá, số sân, version và rounding rule.
6. Preview phải hiện kỳ cũ cuối, kỳ cầu nối, kỳ mới đầu, khoảng trống/chồng lấn bằng 0 và tổng chênh lệch.
7. Kỳ đã phát hành không bị cắt/sửa. Nếu ngày hiệu lực rơi vào phần đã chốt, hệ thống dời áp dụng tới `next_uncovered_date` hoặc yêu cầu adjustment riêng.

Ví dụ đổi mốc 15 sang 5: nếu ngày chưa được bao phủ kế tiếp là 15/09, cửa sổ mốc mới là 05/09–04/10; kỳ cầu nối là 15/09–04/10 và mẫu số là số ngày của 05/09–04/10. Sau đó kỳ đầy đủ chạy 05/10–04/11.

### 24.4. Trial và thay đổi 30 lên 90 ngày

- Trial là số ngày chính xác từ thời điểm cụm sân được kích hoạt đủ điều kiện, không phải số kỳ.
- Tăng mặc định từ 30 lên 90 áp dụng cho cụm sân mới. Cụm sân đang trial có thể được kéo dài đến 90 nếu policy ghi rõ; không được rút ngắn và không mở lại trial đã hết.
- Kỳ trial vẫn được tạo 0 đồng ở `settled_zero` để chứng minh thời gian dịch vụ; Owner không phải thực hiện thanh toán 0 đồng.
- Từ ngày sau trial đến mốc chu kỳ kế tiếp là kỳ cầu nối có phí theo ngày. Nếu muốn miễn luôn phần cầu nối, phải tạo promotion/waiver hiển thị rõ; không im lặng cho free vì “chưa tới kỳ”.
- Kéo dài trial phải xử lý các kỳ tương lai đã issued: kỳ chưa trả/chưa bắt đầu được void-replace có audit; kỳ đã trả phải tạo refund/credit thực cho phần bị trial bao phủ, không xóa tiền.

### 24.5. Trả trước 1/3/6/9/12 kỳ

- Danh sách kỳ được phép lấy từ rule active của phiên bản, không hard-code ở frontend.
- Kỳ cầu nối sau trial/đổi anchor được tách riêng và mặc định không hưởng giảm theo số kỳ. Sau kỳ cầu nối, đúng N kỳ đầy đủ mới nhận tỷ lệ N kỳ.
- Thứ tự tính mặc định: giá gốc → giảm trả trước → promotion hệ thống nếu `stackable_with_prepay` → waiver/adjustment → số ròng.
- Chỉ chốt giảm trả trước khi payment đã thành công trước ngày bắt đầu các kỳ được giảm. Tạo QR nhưng chưa trả không đủ điều kiện khóa ưu đãi vô thời hạn; payment intent phải hết hạn.
- Mỗi kỳ con lưu số tiền ròng phân bổ. Giá tương lai tăng/giảm không sửa các phân bổ đã trả.
- Chấm dứt hoàn phần chưa dùng theo số ròng đã phân bổ, không theo giá niêm yết và không hoàn phần promotion thành tiền mặt.

### 24.6. Adjustment, tiền hoàn và số dư chủ sân

Không tạo thêm một loại “credit” chung chung cho Owner. Cần tách ba bản chất:

| Loại | Dùng để làm gì | Có rút được? |
|---|---|---:|
| Adjustment credit | Giảm số còn phải trả của kỳ sai/chưa thanh toán | Không; chỉ bù nghĩa vụ phí nền tảng được liên kết |
| Refund tiền đã thu | Hoàn tiền thật cho phần dịch vụ chưa dùng/hủy hợp lệ | Vào nguồn thanh toán cũ hoặc số dư chủ sân; chỉ rút theo công thức số dư an toàn |
| Promotional credit/discount | Hỗ trợ thương mại do hệ thống tài trợ | Không chuyển thành tiền và không rút |

- Nếu Owner trả bằng số dư chủ sân, refund hợp lệ quay lại số dư của đúng cụm sân.
- Nếu trả qua chuyển khoản, bản đầu hoàn theo quy trình chuyển khoản và lưu reference. Chỉ chuyển thành số dư chủ sân khi Owner đồng ý rõ và kế toán/pháp lý phê duyệt chính sách; không âm thầm đổi nguồn tiền.
- UI hiển thị `Điều chỉnh giảm`, `Hoàn tiền`, `Khuyến mại`, không gom thành một nhãn “credit”.

### 24.7. Thỏa thuận trả chậm

- Đây là thay đổi hạn thu tiền, không phải mua dịch vụ ba tháng và không nhận giảm trả trước.
- Admin không chọn ngày bắt đầu tùy ý rồi để backend âm thầm sửa. Hệ thống tự lấy các kỳ liên tiếp đủ điều kiện kế tiếp; Admin chỉ chọn 1/2/3 kỳ và hạn cam kết.
- Luồng chuẩn:
  1. Chọn cụm sân.
  2. Backend trả preview các kỳ liên tiếp chưa phân bổ, version/tier/promotion dự kiến và số tiền.
  3. Chọn `Số kỳ được hoãn` bằng radio 1/2/3.
  4. Nhập hạn cam kết và lý do; xem tiền sẽ giữ, số dư còn rút được.
  5. Admin gửi đề nghị, chưa chặn scheduler bằng kỳ giả.
  6. Owner xem điều khoản, xác nhận checkbox và chấp nhận/từ chối.
  7. Khi chấp nhận, transaction revalidate giá/số sân/promotion/số dư, tạo hoặc liên kết kỳ, tạo hold và active arrangement.
- Proposal mặc định hết hạn sau 48 giờ; con số phải cấu hình trong policy. Hết hạn tự động không phát sinh nợ/hold và không chặn kỳ bình thường.
- Owner acceptance lưu revision, snapshot danh sách kỳ/tổng tiền/hạn, IP, user agent, user và thời điểm. Chỉ yêu cầu OTP nếu sản phẩm xác định đây là văn bản thỏa thuận điện tử có giá trị ký; nếu không, authenticated confirmation + audit là đủ.
- Sau khi dịch vụ bắt đầu, không được cancel để void phần đã dùng. Hủy thỏa thuận chỉ bỏ điều khoản trả chậm; kỳ đã dùng trở về hạn chuẩn/overdue và hold được đối soát. Kỳ chưa bắt đầu có thể release/replace.
- Nếu dữ liệu thay đổi giữa preview và accept, API trả 409 với preview mới; không tự chấp nhận số tiền khác.
- Promotion chỉ áp dụng nếu rule có `applies_to_deferred = true`. Mặc định false để tránh dùng nhầm ưu đãi trả đúng/trả trước.

### 24.8. Tạm giữ, thanh toán và rút tiền

- Dùng nhãn `Số dư chủ sân`, không gọi đây là ví điện tử. Hold là bút toán hạn chế số được rút trong hệ thống, không được mô tả như tiền ký quỹ/escrow tại ngân hàng nếu sản phẩm không có cấu trúc pháp lý đó.
- Hold là hạn chế rút, không phải giao dịch thanh toán và không làm kỳ thành paid.
- Thỏa thuận trả chậm có bảo đảm: giữ toàn bộ nghĩa vụ dự kiến khi Owner chấp nhận.
- Kỳ thường: khi dịch vụ bắt đầu mà chưa trả, giữ `min(số dư an toàn, số còn phải trả)`. Phần chưa bảo đảm vẫn là nợ và UI phải tách `Đã giữ`/`Chưa bảo đảm`; không được ghi hold danh nghĩa lớn hơn tiền an toàn. Có thể cấu hình giữ sớm hơn nếu hợp đồng nêu rõ, nhưng UI phải nói thời điểm.
- Owner không được rút phần held. Khi thanh toán phí bằng số dư, service phải loại hold của chính kỳ đó khỏi phép trừ hai lần rồi chuyển hold thành debit nguyên tử.
- Thứ tự số dư an toàn phải trừ booking tương lai, refund, dispute, termination và platform-fee hold. Pending withdrawal chỉ trừ nếu tiền đó còn nằm trong `available_balance`; code hiện tại đã chuyển nó ra nên không được trừ lặp.
- Bản đầu thanh toán từ số dư theo kiểu toàn phần: nếu số dư an toàn không đủ toàn bộ `amount_remaining` thì không debit. UI báo số thiếu và hướng dẫn chuyển khoản; partial allocation/split tender để pha sau.
- Auto-pay bằng số dư mặc định tắt theo từng cụm sân; trước khi bật phải giải thích thứ tự ưu tiên và cho tắt đối với kỳ chưa đến thời điểm thu. Bản đầu chạy auto-pay vào ngày đến hạn, không chạy ngay lúc phát hành; kỳ được thông báo trước, thành công/thất bại đều có log và notification riêng.
- Nếu auto-pay thất bại, kỳ vẫn tồn tại và chuyển khoản vẫn dùng được; không retry dồn dập. Chỉ retry theo policy/idempotency key và không debit sau khi Owner đã bắt đầu payment khác.
- Quyền cấn trừ phí từ số dư, thời điểm hold và quyền rút phần còn lại phải được ghi trong điều khoản Owner đã chấp thuận; checkbox bật auto-pay là bằng chứng cấu hình, không tự thay thế điều khoản nền nếu hợp đồng chưa cho phép cấn trừ.

### 24.9. Chấm dứt và hủy chấm dứt

- Ngày cutoff phải được chốt từ phương án xử lý booking tương lai. Bộ tính quyết toán tạo kỳ cuối/proration đến cutoff, không dựa vào việc scheduler chỉ thấy cụm sân `active`.
- Kỳ unpaid cắt qua cutoff được điều chỉnh còn đúng phần dịch vụ đã dùng. Kỳ paid/prepaid cắt qua cutoff tạo refund phần ròng chưa dùng.
- Mọi con số summary phải có movement/ledger thực; không chỉ ghi `platform_fee_accrued_amount` hoặc `prepaid_refund_amount` trên hồ sơ.
- Hold cho nghĩa vụ phí tồn tại đến khi payment/write-off/approved debt arrangement hoàn tất.
- Không sinh tài liệu chấm dứt cuối nếu còn nợ, refund chưa xử lý hoặc movement chưa đối soát, trừ ngoại lệ có văn bản xác nhận nợ riêng.
- Nếu Owner yêu cầu chấm dứt rồi hủy để né phí, thời gian dịch vụ đã dùng vẫn tính phí. Việc hủy yêu cầu không xóa kỳ/hold; Admin chỉ mở lại sau preview quyết toán và phê duyệt.

### 24.10. Miễn phí và khuyến mại

- `Trial`, `waiver`, `promotion` và `write-off` là bốn nguồn khác nhau, mỗi nguồn có mã/lý do/actor/budget/snapshot.
- Miễn phí N kỳ vẫn tạo từng kỳ 0 đồng `settled_zero`; không tạo nút “Thanh toán 0đ”.
- Promotion phần trăm áp dụng vào base đủ điều kiện sau giảm trả trước nếu được cộng dồn; fixed discount không làm tổng âm.
- Mỗi promotion phải khai báo: khoảng thời gian, đối tượng, số kỳ tối đa, ngân sách, stacking, áp dụng trả trước, áp dụng trả chậm và xử lý kỳ cầu nối.
- Nâng chương trình 30→90 ngày là thay đổi version chương trình, không sửa đè snapshot cũ. Chính sách phải chọn rõ có gia hạn các trial đang active hay chỉ áp dụng enrollment mới.

### 24.11. Số sân con và chuyển bậc giữa kỳ

Mô hình hiện tại là volume pricing: số sân quyết định một đơn giá rồi đơn giá đó áp cho toàn bộ sân. Giữ mô hình này để không mở rộng sang graduated pricing, nhưng phải chặn nghịch lý “thêm sân mà tổng phí giảm”.

- Giá đơn vị của bậc nhiều sân được phép bằng hoặc thấp hơn bậc trước.
- Tại mỗi ranh `N` sân phải thỏa:

```text
N × đơn_giá_bậc_mới >= (N - 1) × đơn_giá_bậc_trước
```

- Preview bậc hiển thị tổng phí ở đầu/cuối mỗi khoảng và cảnh báo ngay khi tổng giảm ở ranh.
- Kỳ đã phát hành lưu snapshot số sân. Kích hoạt thêm sân giữa kỳ tạo adjustment cho phần thời gian còn lại: credit phần còn lại theo số sân/bậc cũ và debit theo số sân/bậc mới; chỉ ghi net sau khi preview.
- Owner chủ động ngừng một sân giữa kỳ có hiệu lực tính phí từ kỳ kế tiếp, không hoàn ngược phần kỳ đang dùng. Trường hợp Admin buộc đóng, lỗi hệ thống hoặc chấm dứt dùng quy trình adjustment/waiver riêng có lý do.
- Không cho bật/tắt sân liên tục để né phí: lưu effective billing event và idempotency theo sự kiện trạng thái sân.
- Nếu kỳ chưa bắt đầu nhưng đã issued, thay đổi số sân làm void-replace kỳ tương lai trong transaction và gửi lại thông báo số tiền; nếu đã paid/prepaid thì tạo adjustment/refund chứ không sửa snapshot.

### 24.12. Quy ước ngày, timezone và làm tròn

- Mọi ngày nghiệp vụ tính theo `Asia/Ho_Chi_Minh`; timestamp audit vẫn lưu đầy đủ timezone/UTC theo quy ước dự án.
- Service period dùng hai ngày bao gồm cả hai đầu: `service_days = diffInDays(start, end) + 1`; ngày tiếp theo chưa bao phủ luôn là `end + 1 ngày`.
- Trial trong bản này là ngày lịch, không phải đúng 24×N giờ: trial 30 ngày bắt đầu từ ngày local của activation và kết thúc cuối ngày thứ 30 (`start_date + 29 ngày`). Cách này khớp engine kỳ theo ngày và phải được nói rõ ở UI/hợp đồng.
- Không chuyển timestamp trial sang date bằng cách giữ `addDays(N)-1 second` rồi cộng thêm một ngày theo date nếu chưa kiểm tra off-by-one.
- Tiền VND lưu ở độ chính xác hiện có nhưng kết quả thu/hoàn làm tròn half-up tới đồng ở cấp line item; tổng bằng tổng các line đã làm tròn để UI, ledger và chứng từ không lệch 1 đồng.
- Test bắt buộc ngày cuối tháng, năm nhuận và activation sát 00:00/23:59 để bắt lỗi lệch ngày.

### 24.13. Một nguồn sự thật cho từng loại cấu hình

| Nhóm cấu hình | Nguồn chuẩn |
|---|---|
| Giá, bậc số sân, trial, anchor, phát hành, ngày đến hạn, giảm trả trước, báo trước | Phiên bản bảng giá |
| Cảnh báo/quá hạn/hạn chế/chuyển xử lý | Policy `platform_fee` có version |
| Campaign giảm phí, scope, budget, stacking | Promotion và assignment |
| Ngoại lệ một kỳ/cụm sân | Waiver/adjustment có approval và audit |

- Endpoint/UI `platform_fee_settings` cũ chỉ giữ tương thích đọc trong giai đoạn chuyển tiếp; không còn là nơi ghi cấu hình độc lập.
- Không sao chép cùng threshold sang plan, config file và policy. Service đọc qua một facade/configuration service duy nhất và snapshot rule thực tế vào ledger khi cần.
- UI `Chính sách áp dụng` chỉ hiển thị nhắc/quá hạn/hạn chế; không lặp lại field version hoặc promotion.

## 25. Phiên bản, thông báo và tác vụ tự động

### 25.1. Vòng đời phiên bản và thông báo Owner

1. Admin tạo/nhân bản draft.
2. Backend validate toàn bộ cấu hình và tạo preview ảnh hưởng.
3. Admin xem lại, chọn ngày hiệu lực và xác nhận `Xem trước & lên lịch`.
4. Một transaction lưu nội dung cuối, revision, lịch áp dụng và outbox event.
5. Worker gửi notification/email cho Owner có cụm sân bị ảnh hưởng: ngày hiệu lực, tóm tắt thay đổi, kỳ đầu dự kiến, ảnh hưởng mốc/proration và link chi tiết.
6. Scheduler activation chuyển scheduled→active và retired version cũ trong transaction.
7. Có nhắc gần ngày hiệu lực theo policy; không gửi lại cùng event revision.

- Khóa idempotency thông báo là `event_type + plan_id + revision + recipient_id`, không chỉ `plan_id`.
- Hủy lịch và lên lại tạo revision/event mới; nội dung cũ vẫn giữ audit và notification mới không bị `firstOrCreate` chặn.
- Chỉ gửi Owner bị ảnh hưởng; không gửi hàng loạt tài khoản không có cụm sân active/termination-serving.
- Notification thất bại được retry và ghi trạng thái, không rollback việc schedule; Admin nhìn thấy số gửi thành công/thất bại.
- Notification kỳ phí phân nhánh theo kết quả: kỳ 0 đồng là `Đã miễn phí`, auto-pay thành công là `Đã thanh toán bằng số dư`, kỳ còn nợ mới dùng `Cần thanh toán`. Link hành động và số tiền còn lại phải khớp trạng thái sau transaction.
- Mỗi người nhận/channel có outbox/delivery record riêng; không gửi toàn bộ email trực tiếp trong một job cha. Note nội bộ không được đưa vào body gửi Owner.
- Notification không đồng nghĩa Owner đã chấp thuận thay đổi. Nếu hợp đồng hiện hành cho phép SportGo thay giá sau thời gian báo trước thì dùng chế độ `notice_only`; nếu hợp đồng yêu cầu đồng thuận/phụ lục thì version không được tự áp chỉ bằng notification và phải đi qua luồng acceptance/document riêng.
- Bản triển khai kỹ thuật này mặc định `notice_only` theo cơ chế policy hiện có, nhưng nội dung UI/email không được dùng câu “chủ sân đã đồng ý”. Trước production cần người phụ trách hợp đồng xác nhận chế độ này phù hợp điều khoản đang ký.

### 25.2. Scheduler kỳ phí

- Chạy hằng ngày theo timezone hệ thống và dùng `invoice_lead_days` của version/profile hiệu lực.
- Xử lý theo cụm sân trong transaction riêng, khóa cụm sân/profile trước khi tính `next_uncovered_date`.
- Một lần chạy phải lấp đủ các kỳ đến horizon bằng vòng lặp có giới hạn cấu hình, không chỉ tạo một kỳ mỗi ngày. Nếu vượt giới hạn thì ghi backlog cảnh báo thay vì im lặng.
- Có `dry-run` trả về will-create/will-skip/reason/amount/version/period mà không ghi DB.
- Có bảng run log: job id, mốc thời gian nghiệp vụ, bắt đầu/kết thúc, created/skipped/failed, reason và retry count.
- Idempotency key không tái dùng mù quáng sau void; replacement có revision và reference kỳ cũ.
- Có job: activate plan, generate period, expire proposal/payment intent, send reminders, mark overdue/apply restrictions, reconcile payment, release/consume hold và termination cutoff.
- Mỗi job có command chạy tay an toàn cho một cụm sân và tham số `--as-of` trên môi trường test để kiểm thử mốc thời gian mà không đổi clock máy.
- Scheduler ghi timezone rõ, dùng `withoutOverlapping()` và `onOneServer()` cho mutation job. Scan lớn dùng `chunkById`; trạng thái `limited` phải được enforcement theo action, không được vô tình đồng nghĩa khóa toàn bộ cluster.
- QA queue phải chạy ít nhất một lần với connection bất đồng bộ (`database` hoặc driver production), worker thật, retry và failed job; kết quả với `sync` chỉ được ghi là kiểm thử logic local.

## 26. Ma trận validation bắt buộc

Validation phải có ở backend; frontend chỉ giúp phản hồi sớm. Không tự cắt, làm tròn hoặc chuẩn hóa giá trị gây khác với dữ liệu người dùng nhập mà không thông báo.

### 26.1. Phiên bản và bậc phí

| Trường/quy tắc | Validation và thông báo mong muốn |
|---|---|
| Mã phiên bản | Bắt buộc, trim, uppercase, 3–50 ký tự, chỉ chữ số/chữ cái/gạch ngang, unique; đã công bố thì immutable |
| Tên phiên bản | Bắt buộc, trim, 3–150 ký tự; lỗi nói rõ min/max |
| Ghi chú | Không bắt buộc, tối đa 1.000 ký tự |
| Trial days | Số nguyên 0–365; 0 nghĩa là không trial, phải hiện diễn giải |
| Mốc chu kỳ | Số nguyên 1–28 |
| Phát hành trước | Số nguyên 0–28; phải dùng thật trong scheduler |
| Payment term/hạn | Số nguyên trong giới hạn policy; ngày tính ra không trước ngày phát hành |
| Thông báo trước | Số nguyên 1–180; ngày hiệu lực không sớm hơn ngày hiện tại + số ngày báo trước, trừ quyền khẩn cấp có audit riêng |
| Timeline | Không chồng lấn; ngày kết thúc suy ra; giai đoạn đầu chỉ một scheduled |
| Bậc đầu | `min_courts = 1` |
| Khoảng bậc | Liên tục, không hở, không chồng; mỗi số sân thuộc đúng một bậc; chỉ bậc cuối được `max_courts = null` |
| Tên bậc | Bắt buộc, 2–100 ký tự, unique trong version |
| Giá | Số nguyên VND > 0, có trần cấu hình để chặn nhập nhầm thêm số 0; không nhận số âm/thập phân |
| Tổng phí tại ranh bậc | Không được giảm khi tăng từ `N-1` lên `N` sân; unit price bậc sau được bằng hoặc thấp hơn bậc trước |
| Discount months | Chỉ các lựa chọn policy cho phép, unique, active rõ ràng |
| Discount percent | 0–100, tối đa 2 chữ số thập phân; tỷ lệ theo kỳ dài hơn không được thấp hơn kỳ ngắn hơn nếu policy dùng logic khuyến khích trả trước; cho phép bằng nhau |
| Schedule | Chỉ khi có ít nhất một bậc hợp lệ, preview thành công, ngày hợp lệ và không có conflict |
| Delete | Chỉ draft, chưa có reference; 409 nếu trạng thái/reference đã đổi |

### 26.2. Thỏa thuận trả chậm

| Trường/quy tắc | Validation và thông báo mong muốn |
|---|---|
| Cụm sân | Tồn tại, Owner active, có sân con, trạng thái cho phép cung cấp dịch vụ |
| Conflict | Không có arrangement pending/active/overdue khác và không overlap prepay/waiver/termination cutoff |
| Số kỳ hoãn | Bắt buộc, thuộc policy cho phép 1–3; UI dùng radio card |
| Kỳ áp dụng | Do server xác định từ các kỳ liên tiếp đủ điều kiện; không nhận ngày tự do rồi âm thầm `startOfMonth()` |
| Hạn cam kết | Sau kỳ cuối; bản đầu tối đa 30 ngày sau kỳ cuối; ngày phải hiển thị rõ |
| Lý do | Bắt buộc, trim, 10–1.000 ký tự |
| Ghi chú nội bộ | Không bắt buộc, tối đa 1.000 ký tự; không gửi Owner nếu là dữ liệu nội bộ |
| Bảo đảm | Số dư an toàn đủ toàn bộ nghĩa vụ đối với luồng chuẩn; preview số giữ và số còn rút được |
| Proposal expiry | Bắt buộc, mặc định policy 48 giờ; không được ở quá khứ |
| Accept | Revalidate version/tier/court/promotion/balance/status; 409 và preview mới nếu khác |
| Cancel/reject | Trước accept giải phóng proposal; sau service start không void dịch vụ đã dùng |

### 26.3. Kỳ phí, thanh toán, miễn/giảm và chấm dứt

| Thao tác | Validation cốt lõi |
|---|---|
| Auto-generate | Cluster/profile hợp lệ, không overlap/gap, version/tier tồn tại, idempotency, promotion budget còn |
| Pay from balance | Đúng Owner/cụm sân, kỳ payable, amount_remaining > 0, số dư an toàn đủ toàn bộ ở bản đầu, không double debit, hold riêng được consume đúng; thiếu thì không debit một phần |
| Auto-pay | Cấu hình đúng cụm, đến hạn, kỳ chưa có payment đang xử lý, số dư an toàn đủ toàn bộ; idempotent và có log thành công/thất bại |
| Hold kỳ thường | Chỉ từ service start khi còn nợ; amount = min(safe balance, remaining); tách secured/unsecured, không giả đã thu tiền |
| Transfer payment | Payment code unique, amount đúng/partial policy, không xác nhận trùng webhook, account active |
| Waive | Chỉ người có quyền, reason + evidence, amount không vượt remaining, audit; không giả paid |
| Promotion | Thời gian/scope/budget/cycles/stacking hợp lệ; lock assignment/budget khi consume |
| Void/replace | Không dùng để xóa dịch vụ đã cung cấp hoặc payment đã đối soát; reason bắt buộc, replacement link rõ |
| Write-off | Quyền và phê duyệt riêng, reason/evidence, không giải thích là paid |
| Termination cutoff | Date hợp lệ theo booking policy, không sau booking cuối cần phục vụ, preview charge/refund/hold; movement phải tạo được |

### 26.4. Promotion campaign

| Trường/quy tắc | Validation và thông báo mong muốn |
|---|---|
| Mã/tên | Mã uppercase 3–50 ký tự unique; tên 3–150; published thì immutable |
| Loại/giá trị | `percent` 0–100 hoặc `fixed` > 0; fixed có trần và không làm số ròng âm |
| Khoảng ngày | Bắt đầu ≤ kết thúc; không áp retro vào kỳ đã issued |
| Số kỳ | Số nguyên 1–36; assignment không âm và không consume hai lần |
| Scope | Hoặc toàn bộ cụm sân, hoặc có ít nhất một assignment; không vừa all vừa danh sách mâu thuẫn |
| Budget | Null nghĩa là không giới hạn có chủ đích; nếu có phải > 0 và không thấp hơn spent; khóa khi consume |
| Stacking | Khai báo riêng với prepay, deferred và bridge; mặc định false cho deferred/bridge |
| Lifecycle | Draft sửa/xóa; scheduled/active chỉ xem/dừng/clone; dừng không sửa kỳ đã snapshot |

### 26.5. Chuẩn phản hồi API và lỗi giao diện

- `422`: dữ liệu đầu vào sai; trả lỗi theo field và error summary.
- `403`: sai quyền/scope; không ẩn lỗi bằng nút disabled duy nhất.
- `404`: bản ghi không tồn tại hoặc không thuộc phạm vi được xem theo chính sách bảo mật.
- `409`: trạng thái/timeline/số dư/version thay đổi, duplicate/idempotency conflict hoặc dữ liệu preview đã cũ.
- UI không hiển thị thành công trước response thật; mutation thất bại giữ dữ liệu form và focus tới error summary/field đầu tiên.

### 26.6. Quyền thao tác

- `platform_fee.view`: xem list/detail/dashboard count/run log không chứa dữ liệu nhạy cảm ngoài phạm vi.
- `platform_fee.create`: tạo draft và tạo đề nghị thỏa thuận.
- `platform_fee.update`: sửa draft/tier/rule; không tự bao gồm quyền công bố.
- `platform_fee.delete`: xóa draft đủ điều kiện.
- `platform_fee.process`: preview/schedule/cancel schedule, xử lý kỳ, reminder, arrangement và settlement.
- Owner chỉ xem/thao tác kỳ, số dư và thỏa thuận của cụm sân thuộc mình; backend kiểm tra ownership cho mọi id trực tiếp, không dựa vào việc UI ẩn nút.
- Không thêm permission code mới nếu các mã hiện có đủ biểu đạt; sửa resolver theo route/action và test 403 từng vai trò Admin/System Staff/Owner.

## 27. Luồng UI/UX chi tiết

### 27.1. Danh sách phiên bản

- Header: tiêu đề, mô tả một câu, nút `Tạo bản nháp`.
- Bộ lọc một hàng: tìm mã/tên, trạng thái, khoảng ngày áp dụng; filter phản ánh trên URL và có `Xóa bộ lọc`.
- Bảng desktop; card list responsive trên mobile. Mỗi hàng có menu hành động đúng trạng thái, không hiện nút vô hiệu hàng loạt.
- Empty state theo ngữ cảnh: chưa có dữ liệu khác với không có kết quả lọc.
- Tạo draft: modal nhỏ chỉ chọn `Tạo trống` hoặc `Nhân bản từ phiên bản`; sau đó chuyển sang trang chi tiết.

### 27.2. Chi tiết phiên bản

- Breadcrumb quay lại danh sách; header có mã, tên, badge trạng thái và summary ngày.
- Các section theo thứ tự quyết định: thông tin chung → thời gian/chu kỳ → trial → bậc phí → trả trước/promotion → thông báo → ảnh hưởng.
- Draft có sticky action bar: `Lưu nháp`, `Xem trước & lên lịch`; có cảnh báo thay đổi chưa lưu khi rời trang.
- Active/retired là read-only summary, không render input disabled như một form chết; dùng description list/table rõ ràng.
- Preview schedule có so sánh phiên bản đang dùng và bản mới: trường thay đổi, Owner/cụm sân ảnh hưởng, kỳ cầu nối, kỳ đầu, tăng/giảm tiền và lịch gửi thông báo.

### 27.3. Danh sách và chi tiết kỳ phí

- KPI ngắn: tổng cần thu, quá hạn, đang giữ, lỗi tự động; không lặp KPI trên từng tab.
- Tab là preset filter, không ẩn khả năng tìm kiếm. Mỗi row hiện cụm sân, Owner, dịch vụ từ–đến, hạn, số gốc/giảm/còn lại, nguồn và trạng thái.
- Chi tiết giải thích tiền theo thứ tự tính, payment allocation, hold, version snapshot, promotion, arrangement và audit timeline.
- Nút `Thanh toán bằng số dư` hiển thị số dư sẽ trừ và số dư an toàn còn lại trước khi confirm.
- Nếu số dư không đủ toàn bộ, nút không được ngụ ý có thể trừ một phần: hiển thị số còn thiếu và CTA `Thanh toán chuyển khoản`. Không cho click rồi mới báo lỗi chung chung.

### 27.4. Tạo và quản lý thỏa thuận trả chậm

- Bước 1: combobox tìm cụm sân theo tên/mã/Owner; sau chọn hiện court count, tình trạng nợ và số dư an toàn.
- Bước 2: radio card `Hoãn 1 kỳ`, `Hoãn 2 kỳ`, `Hoãn 3 kỳ`, mỗi card có khoảng dịch vụ và tổng dự kiến; không dùng dropdown khó so sánh.
- Bước 3: hạn cam kết, lý do, note nội bộ; hint nêu đây không phải trả trước và không được giảm kỳ hạn.
- Bước 4: review danh sách kỳ, giá/discount/promotion, tổng nghĩa vụ, số giữ, số còn rút, hạn và thời điểm proposal hết hiệu lực.
- Sau gửi, bảng chuyển sang `Chờ chủ sân`; Admin xem được notification delivery. Owner có card hành động riêng, không bị lẫn với kỳ phải thanh toán.

### 27.5. Owner

- Dashboard ưu tiên hành động: khoản cần trả, kỳ sắp tới, thỏa thuận chờ xác nhận, số đang giữ và trial còn lại.
- Kỳ phí có nhãn phân biệt `Dịch vụ`, `Hạn thanh toán`, `Nguồn giảm`; không dùng “kỳ 3 tháng” cho arrangement.
- Thỏa thuận Owner phải xem từng kỳ và tổng hold trước nút `Đồng ý thỏa thuận`; từ chối yêu cầu lý do ngắn nếu policy cần.
- Thông báo thay đổi giá dẫn tới trang so sánh dễ đọc; không chỉ gửi câu “bảng giá đã thay đổi”.
- Cài đặt auto-pay ghi rõ `Tự động trừ toàn bộ vào ngày đến hạn nếu số dư an toàn đủ`; hiển thị kỳ gần nhất sẽ bị trừ và cho confirm khi bật.
- Mobile: action bar không che nội dung, bảng chuyển card, modal cuộn trong thân và nút giữ ở footer; test ở chiều rộng 360/390px.

### 27.6. Tích hợp dashboard

Dashboard chỉ hiển thị tín hiệu và hành động cần ưu tiên, không nhúng form quản trị phí:

- Admin `Cần xử lý ngay`: số kỳ quá hạn, thỏa thuận chờ xử lý, job phí thất bại và phiên bản sắp áp dụng; mỗi card mở đúng route cùng query filter.
- Admin tài chính: thêm phí nền tảng đã thu/còn phải thu/đang giữ theo kỳ đang chọn, nhưng không trộn vào “phí sàn booking” nếu hai nguồn doanh thu khác nhau.
- Owner `Cần xử lý hôm nay`: khoản phí đến hạn/quá hạn và thỏa thuận chờ xác nhận. Nếu không có việc thì không chiếm một card rỗng cố định.
- Owner sidebar tài chính: `Phí nền tảng đang giữ` và `Số dư có thể rút`, kèm link giải thích công thức.
- Dữ liệu dashboard lấy từ endpoint tổng hợp dùng cùng status/amount service với màn kỳ phí, không tự tính lại ở Vue.
- Chỉ hiển thị theo cụm sân đang chọn của Owner; Admin card tôn trọng permission `dashboard.view` và quyền xem phí nền tảng.

### 27.7. Ưu đãi phí

- Trang danh sách campaign có mã/tên, loại và giá trị giảm, thời gian, scope, số kỳ, ngân sách đã dùng/tổng, stacking và trạng thái.
- Draft được tạo/sửa/xóa; campaign đã công bố chỉ xem, dừng hoặc nhân bản. Form có preview một kỳ mẫu để Admin thấy thứ tự giảm.
- Chi tiết có tab `Cấu hình`, `Cụm sân được áp dụng`, `Lịch sử sử dụng`; assignment hỗ trợ tìm theo tên/mã/Owner và import chọn nhiều có review.
- Owner chỉ thấy ưu đãi đã được áp/được thông báo cho mình, không thấy ngân sách nội bộ.
- Không dùng promotion để biểu diễn waiver/xóa nợ; hai nghiệp vụ đó vẫn nằm trong detail kỳ cùng reason/evidence.

## 28. Điều chỉnh mô hình dữ liệu và API cần thiết sau audit

Phải tái kiểm kê migration hiện có trước khi tạo migration bổ sung; không drop/rename UUID hay dữ liệu lịch sử.

### 28.1. Dữ liệu tối thiểu cần có

- Plan version: revision, billing anchor, issue rule, payment term rule, notice rule, published/scheduled/activated/cancelled actor/timestamp.
- Tier/prepay rule: version ownership, sort order, currency/VND rounding snapshot.
- Service period: kỳ tham chiếu đầy đủ, numerator/denominator, calculation formula/version, source, replacement reference.
- Ledger adjustment/refund: type, amount, original ledger/payment, reason, actor, evidence, wallet movement.
- Arrangement: proposal revision, expires_at, accepted snapshot/IP/user-agent, security mode, due date, status history.
- Hold: source type/id, amount original/remaining, release/consume reason and movement reference.
- Balance obligation/hold: nguồn booking/refund/dispute/termination/fee, secured amount và unsecured amount; một service duy nhất tính `safe_balance` để payment, withdrawal, arrangement và dashboard không lệch nhau.
- Notification outbox/log: event revision, recipient, channel, content snapshot, delivery/retry.
- Automation run: type, as_of, dry_run, counters, cluster result/reason, error.
- Termination settlement: cutoff, charge/refund calculation snapshot and links đến movements thực.

### 28.2. API theo màn hình

- Version list/detail/create/update/delete/clone/preview-schedule/schedule/cancel-schedule.
- Preview schedule phải là read-only và trả warning/conflict; schedule nhận `expected_revision` để tránh ghi đè.
- Period list có pagination/filter/tab counts; detail có calculation/audit; preview wallet payment và pay endpoint tách riêng.
- Preview wallet payment trả `recorded_balance`, từng nhóm hold/liability, `safe_balance`, số sẽ debit, số còn thiếu và `can_pay_full`; mutation dùng `expected_ledger_revision`/idempotency key.
- Arrangement list/detail/preview/create/accept/reject/cancel; create không phát sinh kỳ cuối cùng trước owner accept.
- Promotion list/detail/draft/publish/stop/clone/assignment/preview; pricing quote dùng cùng evaluator, không tự tính riêng ở frontend.
- Scheduler dry-run/run-log endpoint chỉ dành quyền phù hợp; command nội bộ vẫn là nguồn vận hành chính.
- Owner overview phải dùng cùng quote service với create API để số preview không lệch số thu thật.

## 29. Ma trận kiểm thử mở rộng và bằng chứng bắt buộc

### 29.1. Tính tiền và timeline

1. Mốc 1, 5, 15, 28 qua tháng 28/29/30/31 ngày.
2. Đổi 15→5 và 5→15; preview không gap/overlap, tổng cầu nối đúng tử/mẫu.
3. Trial kết thúc trước/đúng/sau anchor; 30→90 cho enrollment mới, active và expired.
4. Giá tăng→giảm→tăng qua nhiều kỳ; mỗi kỳ giữ đúng snapshot, không retro-credit/debit.
5. Price effective giữa kỳ đã issued; kỳ hiện tại giữ cũ, kỳ đủ điều kiện kế tiếp dùng mới.
6. Bậc phí thay đổi do thêm/bớt sân ngay trước scheduler; snapshot và preview thống nhất.
7. Thêm sân giữa kỳ trong/cross tier tạo adjustment đúng; ngừng sân do Owner chỉ giảm từ kỳ sau; ranh bậc không làm tổng giảm.
8. Trả trước 1/3/6/9/12, bridge riêng, discount/promotion stacking, allocation/refund unused.
9. Kỳ miễn phí/promotion 100% tự settled-zero, không payment/dunning.

### 29.2. Thỏa thuận, hold, thanh toán và rút

1. Preview 1/2/3 kỳ và hạn; không cho 0/4, date sai, cluster conflict.
2. Proposal không accept hết hạn tự động, không chặn kỳ thường.
3. Giá/court/balance thay đổi trước accept trả 409 và preview mới.
4. Accept đủ số dư thì hold 100%; thiếu số dư thì không active arrangement và không tạo hold/kỳ giả.
5. Dịch vụ bắt đầu chưa trả: hold kỳ thường đúng `min(safe, remaining)`, tách phần chưa bảo đảm; thanh toán từ số dư consume hold không trừ hai lần.
6. Thanh toán số dư toàn phần; thiếu thì không debit dở dang; chuyển khoản và webhook retry không double paid.
7. Cancel trước accept, sau accept trước dịch vụ, sau dịch vụ bắt đầu và sau thanh toán một phần.
8. Promotion mặc định không áp deferred; bật policy thì áp đúng một lần.

### 29.3. Chấm dứt và quyết toán

1. Cutoff giữa kỳ unpaid tạo adjustment giảm đúng phần chưa dùng.
2. Cutoff giữa kỳ paid/prepaid tạo refund movement đúng net allocation.
3. Cụm sân `termination_processing` vẫn tạo cutoff period nhưng không sinh kỳ sau cutoff.
4. Không hoàn promotion thành tiền; refund từ số dư quay về đúng cụm.
5. Không sinh tài liệu cuối khi còn debt/refund/hold; ngoại lệ có approval/audit.
6. Hủy chấm dứt không xóa phí đã dùng và không mở sân trước Admin duyệt.

### 29.4. Scheduler, concurrency và notification

1. Chạy cùng command hai lần và hai process đồng thời không tạo trùng.
2. Auto/prepay/arrangement tranh chấp cùng cluster chỉ một transaction thắng; bên còn lại nhận 409/retry an toàn.
3. Void rồi generate lại tạo replacement revision, không mất kỳ.
4. Dry-run không ghi DB; run log phản ánh created/skipped/failed đúng.
5. Schedule/cancel/reschedule/activate gửi đúng event revision, đúng Owner, không gửi trùng; lỗi email retry được.
6. Dunning loại paid/settled-zero/voided/write-off/remaining=0 và dùng hạn arrangement khi active.
7. Down scheduler nhiều kỳ rồi chạy lại: bounded catch-up lấp đủ horizon, không cần chờ mỗi ngày một kỳ và không overlap.
8. Auto-pay không chạy lúc phát hành, chạy đúng hạn; đủ số dư paid một lần, thiếu số dư giữ nguyên nợ và gửi thông báo thất bại đúng nội dung.
9. Chạy queue `database` với worker thật: delivery retry/failed log đúng; kết quả queue `sync` không được dùng làm bằng chứng production.
10. Job restriction chạy đồng thời không chồng; `limited` chỉ chặn đúng action policy, `blocked` mới khóa toàn phần.

### 29.5. Browser QA trực tiếp

Phải kiểm thử trên `http://127.0.0.1:8000` bằng session Admin và Owner thật, không chỉ Puppeteer headless/build:

- Admin list phiên bản: filter/pagination/empty/loading/error, tạo nháp, sửa, validation, xóa draft, clone, preview và schedule.
- Active/retired không sửa được cả UI lẫn gọi API trực tiếp; scheduled cancel đúng.
- Modal review/ConfirmModal không dùng browser confirm; focus, ESC, close, loading và double-click được kiểm tra.
- Kiểm tra keyboard-only: thứ tự Tab, focus trap, trả focus, Enter/Space, liên kết error summary tới field và không chỉ dùng màu để báo trạng thái.
- Arrangement list/modal: tìm cluster, radio 1/2/3, preview, lỗi từng trường, gửi Owner, expiry/cancel.
- Owner nhận notification, mở đúng link, xem version/arrangement; accept/reject và payment bằng số dư.
- Owner xem số dư held/withdrawable trước-sau payment; không có nút thanh toán kỳ 0 đồng.
- Responsive desktop và 360/390px: không tràn ngang ngoài bảng có chủ đích, action không che nội dung, modal không vỡ.
- Mỗi route/role ghi URL, dữ liệu test, thao tác, kết quả API/UI và screenshot. Không gọi “browser pass” nếu chỉ mở được trang.

## 30. Cổng triển khai và thứ tự thực hiện sau khi duyệt kế hoạch

### 30.1. Cổng trước code

Chỉ chuyển sang code khi hoàn thành:

- Không còn mâu thuẫn giữa các mục 4–13 và 22–29.
- Không còn trường cấu hình được hiển thị nhưng engine không sử dụng hoặc engine dùng global config khác nguồn.
- Có inventory migration/API/UI hiện có để tận dụng, không tạo bảng/component trùng.

Các mặc định triển khai đã được chọn sau rà soát, trừ khi phát hiện xung đột dữ liệu/hợp đồng thực tế:

| Quyết định | Mặc định triển khai |
|---|---|
| Chu kỳ dịch vụ | Tháng lịch, anchor ngày 1; vẫn hỗ trợ engine chuyển anchor bằng kỳ cầu nối để không khóa cứng tương lai |
| Ngày đến hạn | Ngày cố định 1–28; mặc định 5, tính theo quy tắc tại Mục 24.1 |
| Phát hành | Theo `invoice_lead_days` của version; mặc định 7 ngày trước kỳ |
| Báo trước phiên bản | Theo `notice_days`; mặc định 30 ngày; quyền khẩn cấp chưa mở trong bản này |
| Proration | Actual-day theo cửa sổ chu kỳ chứa phần dịch vụ, làm tròn half-up tới VND ở từng dòng |
| Trả chậm | 1–3 kỳ, bảo đảm 100% bằng số dư an toàn, proposal hết hạn 48 giờ, hạn tối đa 30 ngày sau kỳ cuối |
| Thanh toán số dư | Toàn phần ở bản đầu; thiếu không debit dở dang; auto-pay mặc định tắt và chạy vào ngày đến hạn |
| Hold kỳ thường | Bắt đầu từ ngày dịch vụ chạy, tối đa bằng số dư an toàn; hiển thị riêng phần nợ chưa bảo đảm |
| Credit/refund | Adjustment chỉ bù kỳ; refund theo nguồn đã trả, phần trả từ số dư quay về đúng cụm sân; promotion không rút |
| Trial 30→90 | Áp dụng enrollment mới; Admin được kéo dài trial đang active bằng hành động riêng, không tự mở lại trial đã hết |
| Nhiều scheduled version | Bản đầu chỉ một scheduled; lịch sử không giới hạn |
| Queue | Local có thể `sync`; nghiệm thu tự động bắt buộc thêm một lượt `database` + worker/retry/failed-job |
| Pháp lý/kế toán | `notice_only`, quyền giữ tiền, hóa đơn và hoàn khác nguồn là cổng duyệt trước production, không suy ra chỉ từ code |

### 30.2. Thứ tự code

1. Sửa nguồn sự thật về policy/time semantics và bổ sung validation/preview service dùng chung.
2. Hoàn thiện version timeline, notification outbox/revision và scheduler activation.
3. Hoàn thiện period engine theo anchor, proration, idempotency/lock và run log.
4. Hoàn thiện prepay/promotion/adjustment/refund và thanh toán số dư/hold.
5. Sửa arrangement proposal→accept→active→fulfilled/expired/cancelled.
6. Tích hợp cutoff/settlement thật vào termination.
7. Thiết kế lại list/detail/modal Admin và luồng Owner bằng component/CSS hiện có.
8. Chạy migration/seed/test/build, sau đó browser QA Admin/Owner; sửa lỗi và chạy lại regression.

### 30.3. Quy tắc commit/push

- Commit production code theo lát cắt, nội dung tiếng Việt rõ ràng.
- Không đưa file test, ảnh QA, output, cache hoặc file rác vào Git theo yêu cầu người dùng; test có thể chạy cục bộ và được báo bằng lệnh/kết quả.
- Không stage các thay đổi không liên quan đã tồn tại trong worktree.
- Mỗi lát cắt chỉ push sau khi diff check và test tương ứng pass; commit sửa lỗi browser dùng tiền tố `fix:` và nêu đúng lỗi đã sửa.
