# SportGo

SportGo là nền tảng quản lý và đặt sân thể thao, tập trung vào các nghiệp vụ vận hành sân, đặt lịch, thanh toán, ví tài chính, chính sách hệ thống và quản trị đối tác. Dự án được xây dựng theo mô hình Laravel API kết hợp Vue SPA, phục vụ ba nhóm người dùng chính: khách đặt sân, chủ sân và quản trị viên hệ thống.

## Mục Tiêu Dự Án

SportGo hướng tới việc gom các thao tác vận hành sân thể thao vào một hệ thống thống nhất:

- Khách hàng tìm sân, xem chi tiết cụm sân, đặt lịch và theo dõi booking.
- Chủ sân quản lý cụm sân, sân con, bảng giá, lịch sân, booking tại quầy, booking cố định, nhân viên, voucher, ví và phí nền tảng.
- Admin quản lý người dùng, phân quyền, cụm sân, loại sân, tiện ích, đơn đối tác, hợp đồng, thanh toán, hoàn tiền, rút tiền, chính sách, báo cáo và kiểm duyệt nội dung.

## Chức Năng Chính

### Khách hàng

- Đăng ký, đăng nhập, quên mật khẩu và đăng nhập Google.
- Xem danh sách sân, chi tiết cụm sân và thông tin sân con.
- Đặt sân theo ngày, khung giờ và loại sân.
- Theo dõi chi tiết booking, trạng thái thanh toán và lịch sử đặt sân.

### Chủ sân

- Dashboard tổng quan vận hành.
- Quản lý cụm sân, vị trí bản đồ, ảnh/media và tiện ích.
- Quản lý sân con, loại sân, layout sân và trạng thái hoạt động.
- Cấu hình bảng giá theo ngày thường, ngày lễ/ngày đặc biệt và loại booking.
- Cấu hình đặt sân: thời lượng, giữ slot, nhắc lịch, thanh toán đủ, đặt cọc hoặc thu sau.
- Booking tại quầy, booking cố định và quản lý lịch sân.
- Khóa lịch/sân theo thời gian bảo trì hoặc lý do vận hành.
- Quản lý nhân viên sân, ví chủ sân, lịch sử giao dịch và yêu cầu rút tiền.
- Theo dõi phí nền tảng, chính sách áp dụng, hồ sơ đối tác và hợp đồng.

### Admin

- Dashboard quản trị hệ thống.
- Quản lý tài khoản, nhân sự admin, vai trò và quyền.
- Quản lý cụm sân, loại sân, tiện ích, banner và voucher hệ thống.
- Duyệt đơn đối tác, hồ sơ cụm sân, hợp đồng, tài liệu và quy trình chấm dứt hợp tác.
- Theo dõi thanh toán booking, hoàn tiền, rút tiền và xuất dữ liệu tài chính.
- Quản lý chính sách hệ thống, chính sách hủy/hoàn tiền, phí nền tảng, kiểm duyệt và báo cáo.
- Kiểm duyệt bài viết, khiếu nại, báo cáo nội dung và nhật ký audit.

## Công Nghệ Sử Dụng

### Backend

- PHP 8.3
- Laravel 12
- Laravel Sanctum cho xác thực API/token
- Laravel Socialite cho đăng nhập Google
- Eloquent ORM, Migration, Seeder, Queue, Mail, Validation
- PHPUnit cho feature/unit test
- Laravel Pint, Pail, Tinker trong môi trường phát triển

### Frontend

- Vue 3
- Vue Router 4
- Vite 8
- Tailwind CSS 4
- Laravel Vite Plugin
- CSS module theo từng khu vực giao diện admin/owner/client

### Database và tích hợp

- MySQL
- Sepay webhook/API cho luồng thanh toán/chuyển khoản
- Queue database driver
- Session/cache database driver
- Storage local/public cho file, media và tài liệu sinh ra

## Cấu Trúc Thư Mục

```text
app/
  Enums/                 Enum nghiệp vụ
  Http/Controllers/Api/  API cho Auth, Admin, Owner, Player, Payment, Public
  Models/                Eloquent models
  Services/              Service xử lý nghiệp vụ
  Console/Commands/      Lệnh tự động xử lý chính sách, lock slot, role...

database/
  migrations/            Schema database
  seeders/               Dữ liệu mẫu cho demo và test

resources/
  css/                   CSS global, admin, owner
  js/
    components/          Component dùng chung
    config/              Cấu hình navigation/route label
    router/              Vue Router
    services/            API client services
    stores/              Auth/session state
    views/               Màn hình client, admin, owner

routes/
  api.php                API routes
  web.php                Web entry

tests/
  Feature/               Feature tests theo nghiệp vụ
  Unit/                  Unit tests
```

## Yêu Cầu Môi Trường

- PHP >= 8.3
- Composer
- Node.js và npm
- MySQL
- Extension PHP phổ biến cho Laravel: PDO, mbstring, openssl, tokenizer, xml, ctype, json, fileinfo

## Cài Đặt

1. Cài dependency PHP:

```bash
composer install
```

2. Cài dependency frontend:

```bash
npm install
```

3. Tạo file môi trường:

```bash
cp .env.example .env
php artisan key:generate
```

4. Cấu hình database trong `.env`:

```env
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=sportgo
DB_USERNAME=root
DB_PASSWORD=
```

5. Chạy migration và seed dữ liệu mẫu:

```bash
php artisan migrate --seed
```

6. Tạo symbolic link storage nếu cần hiển thị file public:

```bash
php artisan storage:link
```

## Chạy Dự Án

Chạy Laravel server:

```bash
php artisan serve
```

Chạy Vite dev server:

```bash
npm run dev
```

Hoặc chạy đồng thời server Laravel và Vite:

```bash
npm run start
```

Build production assets:

```bash
npm run build
```

## Lệnh Hữu Ích

Chạy toàn bộ test:

```bash
php artisan test
```

Chạy một file test cụ thể:

```bash
php artisan test tests/Feature/OwnerBookingConfigTest.php
```

Refresh database và seed lại:

```bash
php artisan migrate:fresh --seed
```

Kiểm tra route:

```bash
php artisan route:list
```

Xóa cache cấu hình:

```bash
php artisan optimize:clear
```

## Tài Khoản Seed Mẫu

Seeder tạo sẵn một số tài khoản demo, tất cả dùng mật khẩu:

```text
12345678
```

Một số tài khoản thường dùng:

| Vai trò | Username | Email |
| --- | --- | --- |
| Super Admin | `superadmin` | `superadmin@sportgo.vn` |
| Admin | `admin` | `admin@sportgo.vn` |
| Tài chính | `finance` | `finance@sportgo.vn` |
| Quản lý chính sách | `policy_manager` | `policy@sportgo.vn` |
| Chủ sân | `owner` | `owner@sportgo.vn` |
| Nhân viên sân | `venuestaff` | `venuestaff@sportgo.vn` |
| Người dùng | `user` | `user@sportgo.vn` |

## Biến Môi Trường Thanh Toán

Các luồng chuyển khoản/thanh toán sử dụng cấu hình Sepay trong `.env`:

```env
SEPAY_WEBHOOK_API_KEY=
SEPAY_API_TOKEN=
SEPAY_API_BASE_URL=https://userapi.sepay.vn/v2
SEPAY_QR_BASE_URL=https://qr.sepay.vn/img
```

Khi chạy local, có thể để trống token nếu chỉ kiểm thử giao diện hoặc dữ liệu seed. Với webhook thật, cần cấu hình key/token đúng môi trường tích hợp.

## Kiểm Thử

Dự án có các feature test cho nhiều nhóm nghiệp vụ như booking, thanh toán Sepay, cấu hình giá, cấu hình đặt sân, khóa lịch, ví chủ sân, vận hành tài chính admin, chính sách, kiểm duyệt và quản lý sân.

Database test dùng cấu hình trong `phpunit.xml`, mặc định là:

```env
DB_DATABASE=sportgo_test
```

Trước khi chạy test liên quan database, cần đảm bảo database test tồn tại và user MySQL có quyền tạo/xóa bảng.

## Ghi Chú Phát Triển

- Backend ưu tiên xử lý nghiệp vụ trong service/controller rõ ràng, model khai báo relationship đầy đủ để tránh lỗi eager-load.
- Frontend chia theo khu vực `admin`, `owner`, `clients`; các màn hình admin/owner dùng layout riêng và navigation cấu hình tập trung.
- Các migration và seeder đang phục vụ nhiều luồng nghiệp vụ; khi merge nhánh cần kiểm tra `migrate:fresh --seed` để bắt lỗi lệch schema sớm.
- Không commit file `.env`, `storage`, `vendor`, `node_modules` hoặc build artifact không cần thiết.

## Trạng Thái Dự Án

SportGo đang trong giai đoạn phát triển tính năng. Các module chính đã có nền tảng vận hành, nhưng trước khi triển khai production cần rà soát thêm bảo mật, quyền truy cập, logging, queue worker, cấu hình mail, backup database và tích hợp thanh toán thật.
