# Hướng dẫn Phát triển Giao diện & Quy chuẩn CSS (SportGo UI Kit)

Tài liệu này quy chuẩn hóa cách thiết kế giao diện và viết mã CSS/HTML trong dự án nhằm tránh việc chồng chéo, lặp lại mã và sai lệch màu sắc/kích thước giữa các thành viên trong nhóm phát triển.

---

## 1. Hệ thống biến CSS (Design Tokens)
**Bắt buộc:** Tuyệt đối không khai báo mã màu tĩnh (như `#ffffff`, `#1e293b`) hay kích thước font tĩnh (`font-size: 14px`) trong các tệp Vue cục bộ. Luôn sử dụng hệ thống biến CSS đồng bộ với cấu hình Database sau:

### Màu sắc chính:
* Màu chủ đạo: `var(--admin-primary)` (Nút bấm chính, trạng thái chọn, viền active)
* Màu văn bản chính: `var(--admin-text)` (Tự động đổi màu tương phản theo nền)
* Màu chữ phụ/nhạt: `var(--admin-muted)` (Dùng cho mô tả, nhãn phụ, chú thích)
* Màu đường viền: `var(--admin-border)` (Viền bảng, khung thẻ)
* Màu nền thẻ: `var(--admin-surface)` (Nền các card nội dung)
* Màu nền trang: `var(--admin-bg)` (Nền tổng thể của trang)

### Cỡ chữ tự động co giãn:
* Chữ rất nhỏ: `var(--admin-font-size-xs)` (11px)
* Chữ nhỏ: `var(--admin-font-size-sm)` (12px)
* Chữ thường: `var(--admin-font-size-base)` (14px)
* Tiêu đề nhỏ: `var(--admin-font-size-lg)` (16px)
* Tiêu đề phân mục: `var(--admin-font-size-xl)` (18px)
* Tiêu đề trang chính: `var(--admin-font-size-2xl)` (20px)

---

## 2. Thư viện Component dùng chung (UI Kit)
Để đồng bộ hóa giao diện và hạn chế lỗi viết thiếu class, toàn bộ thành viên bắt buộc sử dụng 4 Component UI cơ bản được dựng sẵn tại `resources/js/components/common/`:

### 2.1. Nút bấm (`<SgButton>`)
Thay thế hoàn toàn cho `<button class="btn btn-primary">`.
* **Sử dụng:**
  ```html
  <SgButton type="primary" @click="saveData">Lưu cấu hình</SgButton>
  <SgButton type="secondary" :disabled="isLoading">Hủy bỏ</SgButton>
  ```
* **Các thuộc tính (Props):**
  * `type`: `primary` (chủ đạo), `secondary` (phụ), `danger` (xóa/lỗi), `success` (thành công)
  * `loading`: `true/false` (hiển thị vòng xoay đang tải)
  * `disabled`: `true/false` (vô hiệu hóa nút)

### 2.2. Nhãn trạng thái (`<SgBadge>`)
Dùng để hiển thị trạng thái hóa đơn, lịch đặt sân, đơn đăng ký.
* **Sử dụng:**
  ```html
  <SgBadge type="success">Đã thanh toán</SgBadge>
  <SgBadge type="processing">Đang xử lý</SgBadge>
  <SgBadge type="danger">Đã hủy</SgBadge>
  ```
* **Các thuộc tính (Props):**
  * `type`: `success`, `warning`, `danger`, `processing`, `info`

### 2.3. Ô nhập liệu (`<SgInput>`)
* **Sử dụng:**
  ```html
  <SgInput v-model="username" placeholder="Nhập họ và tên..." />
  <SgInput v-model="searchQuery" placeholder="Tìm kiếm sân..." has-icon>
    <template #icon>
      <AppIcon name="search" size="14" />
    </template>
  </SgInput>
  ```

### 2.4. Khung nội dung (`<SgCard>`)
* **Sử dụng:**
  ```html
  <SgCard>
    <template #header>
      <h2>Cấu hình tài khoản</h2>
    </template>
    
    <div>Nội dung form thiết lập nằm ở đây...</div>
    
    <template #footer>
      <SgButton type="primary">Lưu lại</SgButton>
    </template>
  </SgCard>
  ```

---

## 3. Quy chuẩn làm việc nhóm (Workflow Rules)
1. **Hạn chế tối đa viết `<style scoped>` cục bộ:** Nếu chỉ căn lề hoặc chỉnh màu, hãy sử dụng các class tiện ích có sẵn thay vì tự viết thêm CSS.
2. **Không commit code lỗi biên dịch:** Trước khi đẩy code lên git (Push commits), bắt buộc chạy lệnh `npm run build` trên máy cá nhân để kiểm tra và đảm bảo không gây lỗi vỡ cấu trúc CSS hay cú pháp Javascript.
3. **Cập nhật CSS dùng chung:** Nếu phát hiện một khối giao diện xuất hiện lặp lại ở 3 trang trở lên, hãy thảo luận với nhóm để định nghĩa class đó vào tệp [portals.css](resources/css/portals.css) thay vì tự viết riêng lẻ.
4. **Không dùng `style` inline:** Tuyệt đối không viết `style="color: red; font-weight: bold"` trực tiếp vào template HTML. Mọi tùy chỉnh giao diện phải thông qua class CSS.

---

## 4. CSS Anti-patterns — Những thứ BỊ CẤM

Đây là danh sách các kỹ thuật CSS **bị nghiêm cấm sử dụng** trong toàn bộ dự án. Vi phạm sẽ bị yêu cầu sửa lại trước khi merge.

---

### 4.1. Không dùng font-weight > 600 (Không chữ đậm thô)

**Lý do:** Font weight quá cao (`700`, `800`, `900`, `950`) tạo ra cảm giác thô, nặng nề và thiếu nhất quán giữa các trang. Giao diện product-grade hiện đại ưu tiên sự thanh lịch, rõ ràng qua kích thước và màu sắc — không phải qua độ đậm.

```css
/* ❌ SAI — quá đậm, thô */
font-weight: 700;
font-weight: 900;
font-weight: 950;

/* ✅ ĐÚNG — vừa phải, thanh lịch */
font-weight: 400;  /* text thường */
font-weight: 500;  /* nhấn nhẹ */
font-weight: 600;  /* tiêu đề, nhãn quan trọng — giới hạn trên */
```

> **Ngoại lệ duy nhất:** Số liệu thống kê lớn (stat numbers) trong dashboard có thể dùng `font-weight: 700` để nhấn số, nhưng phải được xem xét và phê duyệt.

---

### 4.2. Không dùng `box-shadow`

**Lý do:** Box-shadow tạo ra độ sâu giả tạo, làm giao diện trông "nặng" và không nhất quán khi theme màu thay đổi (shadow tối trên nền sáng bị lộ rõ). Thay vào đó, dùng border và màu nền để tạo phân cách.

```css
/* ❌ SAI */
box-shadow: 0 4px 16px rgba(0, 0, 0, 0.1);
box-shadow: 0 8px 24px rgba(15, 23, 42, 0.05);

/* ✅ ĐÚNG — dùng border + nền để phân cách */
border: 1px solid var(--admin-border-soft);
background: var(--admin-surface);
```

> **Ngoại lệ duy nhất:** Modal và Drawer overlay có thể dùng shadow nhẹ `0 4px 16px rgba(0,0,0,0.08)` để tạo cảm giác nổi lên. Floating button có thể dùng shadow màu primary để tạo hiệu ứng glow. Phải giữ ở mức tối giản.

---

### 4.3. Không dùng `background: linear-gradient(...)` hay `background: radial-gradient(...)`

**Lý do:** Gradient làm màu sắc không đồng nhất, khó bảo trì khi màu chủ đạo thay đổi theo theme, và trông lỗi thời so với thiết kế phẳng (flat design) hiện đại của sản phẩm.

```css
/* ❌ SAI */
background: linear-gradient(180deg, #fff, #f0f9f0);
background: linear-gradient(135deg, var(--admin-primary), #15803d);

/* ✅ ĐÚNG — màu nền solid từ hệ thống biến */
background: var(--admin-surface);
background: var(--admin-bg-soft);
background: var(--admin-primary-soft);
```

> **Ngoại lệ duy nhất:** Nền trang tổng thể (`--admin-bg-gradient`) đã được định nghĩa sẵn trong biến hệ thống. Skeleton loading animation có thể dùng gradient để tạo hiệu ứng shimmer, nhưng phải đặt trong `@keyframes` riêng biệt.

---

### 4.4. Không dùng Emoji trong văn bản giao diện

**Lý do:** Emoji render khác nhau trên các hệ điều hành (Windows, macOS, Android), gây ra sự thiếu nhất quán về hình dạng và màu sắc. Ngoài ra emoji làm giảm tính chuyên nghiệp của giao diện quản trị.

```html
<!-- ❌ SAI -->
<span>✅ Đã xác nhận</span>
<p>📅 Lịch sân hôm nay</p>
<button>🗑️ Xóa</button>

<!-- ✅ ĐÚNG — dùng AppIcon component -->
<span><AppIcon name="checkCircle" size="14" /> Đã xác nhận</span>
<p><AppIcon name="calendar" size="14" /> Lịch sân hôm nay</p>
<button><AppIcon name="trash" size="14" /> Xóa</button>
```

Toàn bộ icon phải đến từ `<AppIcon name="..." />` được định nghĩa sẵn trong `resources/js/components/AppIcon.vue`.

---

### Tóm tắt nhanh (Quick Reference)

| Thứ | Trạng thái | Thay thế bằng |
|---|---|---|
| `font-weight: 700+` | ❌ Bị cấm | Tối đa `font-weight: 600` |
| `box-shadow: ...` | ❌ Bị cấm | `border: 1px solid var(--admin-border-soft)` |
| `linear-gradient(...)` | ❌ Bị cấm | `background: var(--admin-surface)` |
| Emoji trong text | ❌ Bị cấm | `<AppIcon name="..." />` |
| Màu hex tĩnh | ❌ Bị cấm | `var(--admin-primary)`, `var(--admin-text)`,... |
| `style="..."` inline | ❌ Bị cấm | Class CSS trong file `.vue` hoặc `portals.css` |
