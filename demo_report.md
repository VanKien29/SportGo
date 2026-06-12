
# MÔ PHỎNG QUÁ TRÌNH KINH DOANH VÀ THANH LÝ CỦA ĐỐI TÁC

**Đối tác giả lập:** Nguyễn Văn Demo (Email: demo_owner_87199@example.com)
**Cụm sân:** Cụm sân bóng Demo
**Phí nền tảng đã đóng:** 12,000,000 VND (Chu kỳ 1 năm)
**Thời gian đã sử dụng:** 6 tháng (Còn dư 6 tháng)

---

## 1. SAU KHI ĐỐI TÁC KÝ HỢP ĐỒNG ĐIỆN TỬ
- **Trạng thái hợp đồng:** Đã ký (Active)
- **Quyền Chủ Sân (Role):** Đã cấp thành công (`venue_owner`)
- **Email gửi tự động:**
> Tiêu đề: Chào mừng bạn trở thành Đối tác của SportGo!
> Nội dung: Chào Nguyễn Văn Demo, Hợp đồng đối tác của bạn đã được ký kết thành công!...

---

## 2. KHI ĐỐI TÁC BỊ THANH LÝ HỢP ĐỒNG
Admin vừa duyệt Yêu cầu chấm dứt hợp đồng. Hệ thống lập tức xử lý các bước sau:

**A. Sinh biên bản thanh lý:**
- Loại tài liệu: `unilateral_notice`
- File được tự động sinh tại: `liquidations/UNI-CT-DEMO-87199-1781260559.docx`

**B. Hoàn tiền phí nền tảng:**
Dựa theo tỷ lệ ngày chưa dùng, hệ thống tính toán được số tiền hoàn là: **6,001,918 VND**
Giao dịch ghi nhận vào sổ cái ví:
- Giao dịch [CREDIT]: 6,001,918 VND | Lời dẫn: HoÃ n tiá» n phÃ­ há»‡ thá»‘ng chÆ°a sá» dá»¥ng (182.55833598988/ 365 ngÃ y) do cháº¥m dá»©t há»£p Ä‘á»“ng.
- Giao dịch [DEBIT]: 6,001,918 VND | Lời dẫn: RÃºt tiá» n tá»± Ä‘á»™ng do cháº¥m dá»©t há»£p Ä‘á»“ng.

**C. Lệnh rút tiền tự động:**
Hệ thống sinh 1 phiếu Rút tiền chờ duyệt với nội dung: *"Tự động rút tiền do thanh lý hợp đồng hợp tác"* chuyển về số tài khoản **VCB - 123456789**.

**D. Email gửi tự động:**
> Tiêu đề: Thông báo: Chấm dứt hợp đồng đối tác
> Nội dung: Yêu cầu chấm dứt của bạn đã được duyệt. Tiền hoàn cước đã được chuyển vào Lệnh rút tiền... Bạn sẽ bị khóa quyền truy cập sau đúng 1 tháng nữa.

---

## 3. ĐÚNG 1 THÁNG SAU (CRON JOB CHẠY)
- Hệ thống chạy ngầm: `php artisan app:revoke-expired-owner-roles`
- **Kết quả:** Kiểm tra lại quyền của tài khoản Nguyễn Văn Demo: ĐÃ BỊ XÓA QUYỀN TRUY CẬP (Thành công)
- **Chặn truy cập:** Chủ sân không còn bất kỳ quyền nào để thao tác vào Cụm sân này nữa.
