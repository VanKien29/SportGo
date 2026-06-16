# Báo Cáo Thiết Kế Database Dự Án SportGo

Báo cáo này được tự động trích xuất và tổng hợp từ các file migration hiện tại của dự án. Không bao gồm các giả định ngoài code.

==================================================
## PHẦN 1. TỔNG HỢP CÁC BẢNG
==================================================

| STT | Tên bảng | Module | Tác dụng chính | Mô tả | Các liên kết chính |
|---|---|---|---|---|---|
| 1 | users | Auth/RBAC | Lưu thông tin người dùng | Lưu tài khoản đăng nhập, trạng thái, và profile cơ bản | users (locked_by) |
| 2 | password_reset_tokens | Laravel | Đặt lại mật khẩu | Bảng chuẩn của Laravel cho reset password (email token) | Không FK |
| 3 | sessions | Laravel | Lưu session | Bảng quản lý session user đăng nhập | users (user_id) |
| 4 | cache | Laravel | Lưu cache | Bảng cache database driver của Laravel | Không FK |
| 5 | cache_locks | Laravel | Khóa cache | Quản lý lock của cache | Không FK |
| 6 | jobs | Laravel | Hàng đợi công việc | Quản lý background jobs (Queue) | Không FK |
| 7 | job_batches | Laravel | Lô công việc | Quản lý batch jobs | Không FK |
| 8 | failed_jobs | Laravel | Job thất bại | Lưu các queue job chạy lỗi | Không FK |
| 9 | audit_logs | System/Log | Lịch sử thao tác | Ghi nhận hành động nhạy cảm trong hệ thống | users (actor_id) |
| 10 | banners | System | Quản lý banner | Banner quảng cáo, hiển thị trang chủ | users (created_by), users (updated_by) |
| 11 | venue_clusters | Venue | Lưu cụm sân | Lưu thông tin 1 cơ sở sân bãi (địa chỉ, tọa độ, chủ sân) | users (locked_by), users (owner_id) |
| 12 | booking_configs | Booking | Cấu hình đặt sân | Cấu hình quy định đặt sân (thời gian tối thiểu, tiền cọc) cho cụm sân | venue_clusters (venue_cluster_id) |
| 13 | court_types | Venue | Lưu loại sân thể thao | Quản lý loại môn/sân (vd: sân cầu lông, sân bóng đá 7 người) | Không FK |
| 14 | venue_staff_assignments | Venue | Phân công nhân viên | Phân công nhân viên quản lý cụm sân hoặc loại sân cụ thể | users (assigned_by), court_types (court_type_id), users (user_id), venue_clusters (venue_cluster_id) |
| 15 | venue_courts | Venue | Lưu sân con thực tế | Các sân nhỏ bên trong 1 cụm sân để khách đặt | court_types (court_type_id), venue_clusters (venue_cluster_id) |
| 16 | community_posts | Community | Bài đăng cộng đồng | Người chơi đăng bài thảo luận tự do | users (author_id), users (reviewed_by) |
| 17 | venue_court_approval_requests | Venue | Xin duyệt tạo sân con | Lưu yêu cầu duyệt tạo sân con mới của chủ sân | court_types (court_type_id), users (requested_by), users (reviewed_by), venue_clusters (venue_cluster_id) |
| 18 | conversations | Chat | Cuộc hội thoại | Quản lý phòng chat (direct, post, venue) | users (created_by) |
| 19 | conversation_participants | Chat | Thành viên chat | Thành viên tham gia vào cuộc hội thoại | conversations (conversation_id), users (user_id) |
| 20 | partner_applications | System | Đơn đăng ký đối tác | Đơn xin làm chủ sân gửi cho admin duyệt | users (reviewed_by), users (user_id) |
| 21 | partner_application_courts | System | Môn thể thao đăng ký | Loại sân kinh doanh dự kiến của đơn đăng ký đối tác | court_types (court_type_id), partner_applications (partner_application_id) |
| 22 | roles | Auth/RBAC | Lưu các nhóm quyền | Lưu mã role để phân quyền (admin, venue_owner, customer...) | Không FK |
| 23 | bookings | Booking | Đơn đặt sân | Quản lý lịch đặt sân, giờ chơi, thanh toán, trạng thái | users (cancelled_by), users (created_by), users (customer_id) |
| 24 | community_post_comments | Community | Bình luận cộng đồng | Bình luận trong các bài đăng cộng đồng | community_post_comments (parent_id), community_posts (post_id), users (user_id) |
| 25 | community_post_likes | Community | Thích bài viết | Lượt thích bài đăng cộng đồng | community_posts (post_id), users (user_id) |
| 26 | complaints | System/Report | Khiếu nại | Khiếu nại về sân bãi, dịch vụ hoặc booking | users (assigned_to), bookings (booking_id), users (customer_id), users (resolved_by), venue_clusters (venue_cluster_id) |
| 27 | venue_posts | Community | Bài đăng chủ sân | Chủ sân đăng bài quảng bá, thông báo | users (author_id), users (reviewed_by), venue_clusters (venue_cluster_id) |
| 28 | favorite_venues | Venue | Sân yêu thích | Lưu danh sách cụm sân yêu thích của khách | users (user_id), venue_clusters (venue_cluster_id) |
| 29 | payments | Payment | Thanh toán | Quản lý giao dịch thanh toán của booking | bookings (booking_id) |
| 30 | holiday_prices | Booking | Bảng giá ngày lễ | Lưu giá đặc biệt áp dụng cho ngày lễ/sự kiện | court_types (court_type_id), venue_clusters (venue_cluster_id) |
| 31 | media | System | Quản lý file đính kèm | Quản lý tập tin, hình ảnh đa phương tiện (polymorphic) | Không FK |
| 32 | messages | Chat | Tin nhắn | Nội dung tin nhắn trong hội thoại | conversations (conversation_id), users (sender_id) |
| 33 | moderation_configs | System | Cấu hình kiểm duyệt | Cấu hình hệ thống (key-value) | users (updated_by) |
| 34 | notifications | System | Thông báo | Lưu thông báo gửi cho user | users (user_id) |
| 35 | payment_logs | Payment | Log giao dịch | Log chi tiết request/response từ cổng thanh toán | payments (payment_id) |
| 36 | permissions | Auth/RBAC | Lưu danh sách quyền | Lưu các quyền chi tiết (vd: booking.manage) để check logic | Không FK |
| 37 | platform_fee_tiers | Payment | Bậc phí nền tảng | Quản lý các gói thu phí nền tảng áp dụng cho chủ sân | Không FK |
| 38 | player_posts | Community | Bài tìm đối thủ/đội | Khách tìm kèo chơi chung, chia sẻ chi phí | users (author_id), bookings (booking_id) |
| 39 | hashtags | Community | Hashtag chung | Lưu các hashtag | Không FK |
| 40 | player_post_participants | Community | Xin tham gia kèo | Khách xin tham gia vào bài tìm đối/đội | player_posts (post_id), users (user_id) |
| 41 | player_preferences | Player | Hồ sơ người chơi | Lưu thông tin đánh giá trung bình của người chơi | users (user_id) |
| 42 | venue_platform_fee_ledgers | Payment | Công nợ phí nền tảng | Quản lý lịch sử và trạng thái đóng phí nền tảng của cụm sân | platform_fee_tiers (tier_id), venue_clusters (venue_cluster_id) |
| 43 | player_preferred_court_types | Player | Môn thể thao yêu thích | Người chơi chọn loại môn thể thao quan tâm | court_types (court_type_id), users (user_id) |
| 44 | player_ratings | Player | Đánh giá người chơi | Đánh giá trình độ/thái độ giữa những người chơi với nhau | player_posts (post_id), users (rated_user_id), users (rater_id) |
| 45 | post_hashtags | Community | Gắn hashtag vào bài | Liên kết hashtag với các loại bài viết | hashtags (hashtag_id) |
| 46 | price_slots | Booking | Bảng giá theo khung giờ | Lưu giá tiền theo khung giờ của loại sân trong cụm | court_types (court_type_id), venue_clusters (venue_cluster_id) |
| 47 | refunds | Payment | Hoàn tiền | Quản lý yêu cầu hoàn tiền cho thanh toán bị hủy | payments (payment_id), users (processed_by) |
| 48 | reports | System/Report | Báo cáo vi phạm | Quản lý báo cáo xấu, spam | users (reporter_id), users (reviewed_by) |
| 49 | reviews | System/Report | Đánh giá cụm sân | Khách đánh giá sau khi hoàn thành booking | bookings (booking_id) |
| 50 | role_permissions | Auth/RBAC | Gán quyền cho role | Cầu nối n-n giữa roles và permissions | permissions (permission_id), roles (role_id) |
| 51 | slot_locks | Booking | Khóa khung giờ | Giữ chỗ hoặc khóa khung giờ không cho đặt sân | bookings (booking_id), venue_courts (venue_court_id) |
| 52 | system_policies | System | Chính sách hệ thống | Điều khoản, chính sách (bảo mật, hoàn tiền, v.v.) | users (created_by), users (updated_by) |
| 53 | system_posts | Community | Bài viết hệ thống | Admin đăng thông báo, tin tức hệ thống | users (author_id) |
| 54 | user_permission_revokes | Auth/RBAC | Thu hồi quyền của user | Lưu các quyền bị thu hồi cụ thể của 1 user dù role có cấp | permissions (permission_id), users (revoked_by), users (user_id) |
| 55 | user_policy_acceptances | System | Chấp nhận chính sách | Ghi nhận user đã đồng ý phiên bản chính sách | system_policies (system_policy_id), users (user_id) |
| 56 | user_roles | Auth/RBAC | Gán role cho user | Cầu nối n-n giữa users và roles, có hỗ trợ scope theo system/venue | users (granted_by), roles (role_id), users (user_id) |
| 57 | verification_codes | System | Mã xác thực | OTP dùng cho email/sms đăng ký, quên mật khẩu | users (user_id) |
| 58 | personal_access_tokens | Auth/RBAC | Lưu token đăng nhập | Bảng chuẩn của Laravel Sanctum lưu access token | Không FK |
| 59 | system_bank_accounts | Payment | Tài khoản ngân hàng | Lưu thông tin TKNH hệ thống dùng để nhận thanh toán | Không FK |
| 60 | owner_wallets | Payment | Ví chủ sân | Quản lý số dư, tiền thu hộ của chủ sân | users (owner_id) |
| 61 | owner_wallet_ledgers | Payment | Sổ quỹ ví chủ sân | Ghi nhận biến động số dư chi tiết của ví chủ sân | owner_wallets (owner_wallet_id), users (owner_id), venue_clusters (venue_cluster_id), bookings (booking_id), payments (payment_id) |
| 62 | booking_items | Booking | Chi tiết sân/khung giờ trong booking | Lưu từng sân con và khung giờ cụ thể trong một booking (hỗ trợ đặt nhiều sân/slot) | bookings (booking_id), venue_courts (venue_court_id), venue_courts (requested_venue_court_id), users (court_changed_by) |
| 63 | owner_bank_accounts | Payment | Tài khoản nhận tiền chủ sân | Lưu TKNH của chủ sân dùng nhận tiền rút/đối soát | users (owner_id), partner_applications (partner_application_id), users (verified_by) |
| 64 | owner_withdrawal_requests | Payment | Yêu cầu rút tiền chủ sân | Quản lý yêu cầu rút tiền từ ví chủ sân | users (owner_id), owner_wallets (owner_wallet_id), owner_bank_accounts (owner_bank_account_id), users (reviewed_by), users (completed_by) |
| 65 | internal_receipts | Payment | Phiếu thu/chi nội bộ | Phiếu nội bộ cho phí nền tảng, rút tiền, hoàn tiền | users (issued_to_user_id), users (issued_by) |
| 66 | policy_action_bindings | Policy | Liên kết chính sách với action | Map chính sách hệ thống với module/action nghiệp vụ | Không FK |
| 67 | policy_rules | Policy | Luật chính sách hệ thống | Lưu rule có cấu trúc để backend evaluate | Không FK |
| 68 | venue_policy_rules | Policy | Luật chính sách riêng sân | Lưu rule riêng của sân khi chính sách cho phép override | Không FK |
| 69 | policy_evaluation_logs | Policy | Log áp dụng chính sách | Ghi nhận mỗi lần hệ thống evaluate rule | Không FK |
| 70 | ai_conversations | AI | Cuộc trò chuyện AI | Lưu lịch sử trò chuyện AI của user | Không FK |
| 71 | ai_messages | AI | Tin nhắn AI | Lưu message user/assistant/system trong cuộc trò chuyện AI | Không FK |
| 72 | ai_feedbacks | AI | Đánh giá AI | Lưu feedback của user cho câu trả lời AI | Không FK |
| 73 | user_wallets | Payment | Ví người dùng | Quản lý ví nội bộ của user (thanh toán, nhận hoàn tiền) | Không FK |
| 74 | user_wallet_ledgers | Payment | Sổ quỹ ví người dùng | Ghi nhận biến động số dư ví user | Không FK |
| 75 | user_payout_accounts | Payment | Tài khoản nhận tiền user | TKNH user dùng nhận tiền khi rút ví hoặc refund | Không FK |
| 76 | user_withdrawal_requests | Payment | Yêu cầu rút tiền user | Quản lý yêu cầu rút tiền từ ví user | Không FK |
| 77 | vouchers | Voucher | Mã giảm giá | Lưu voucher hệ thống và voucher sân | Không FK |
| 78 | voucher_scopes | Voucher | Phạm vi voucher | Giới hạn phạm vi áp dụng voucher (cụm sân, loại sân, loại booking) | Không FK |
| 79 | voucher_usages | Voucher | Lịch sử dùng voucher | Ghi nhận voucher đã áp dụng cho booking/payment nào | Không FK |
| 80 | backup_jobs | System | Job sao lưu dữ liệu | Lưu metadata và trạng thái các lần backup database | Không FK |
| 81 | amenities | Chưa phân loại | ... | ... | users (created_by), users (reviewed_by) |
| 82 | policy_rule_templates | Policy | Mẫu cấu hình rule | Danh mục template rule để admin tạo/cấu hình đúng loại chính sách và action code | Không FK |
| 83 | policy_override_constraints | Policy | Ràng buộc override chính sách sân | Giới hạn owner không được override rule hệ thống vượt khung cho phép | Không FK |
| 84 | policy_status_histories | Policy | Lịch sử trạng thái chính sách | Ghi nhận mỗi lần chính sách hệ thống đổi trạng thái/version | Không FK |
| 85 | refund_status_histories | Payment | Lịch sử trạng thái refund | Ghi nhận từng bước xử lý hoàn tiền (owner confirm, admin confirm, gateway) | Không FK |
| 86 | partner_application_documents | Partner | Tài liệu hồ sơ đối tác | File đính kèm hồ sơ: ảnh sân, CCCD, giấy phép, chứng từ ngân hàng | Không FK |
| 87 | partner_application_status_histories | Partner | Lịch sử trạng thái hồ sơ đối tác | Ghi nhận mỗi lần hồ sơ đối tác đổi trạng thái | Không FK |
| 88 | document_templates | Document | Template văn bản DOCX | Lưu template biểu mẫu theo loại và version, có render engine | Không FK |
| 89 | generated_documents | Document | Văn bản đã sinh | Văn bản sinh từ template, có snapshot render_data và file path | Không FK |
| 90 | generated_document_signatures | Document | Chữ ký văn bản | Chữ ký/xác nhận của owner và SportGo trên văn bản đã sinh | Không FK |
| 91 | partner_contracts | Contract | Hợp đồng đối tác | Hợp đồng giữa SportGo và chủ sân, link hồ sơ và văn bản đã ký | Không FK |
| 92 | partner_termination_requests | Termination | Yêu cầu chấm dứt hợp tác | Yêu cầu chấm dứt hợp tác: hai bên đồng ý hoặc đơn phương | Không FK |
| 93 | partner_termination_documents | Termination | Văn bản chấm dứt | Biên bản thanh lý, công văn đơn phương, đơn chấm dứt | Không FK |
| 94 | partner_termination_status_histories | Termination | Lịch sử trạng thái chấm dứt | Ghi nhận từng bước xử lý yêu cầu chấm dứt hợp tác | Không FK |
| 95 | partner_settlements | Settlement | Quyết toán công nợ | Kết quả quyết toán khi chấm dứt hợp tác: payable vs receivable | Không FK |
| 96 | partner_settlement_items | Settlement | Chi tiết quyết toán | Từng dòng cộng/trừ trong biên bản quyết toán | Không FK |
| 97 | venue_access_restrictions | Owner Restriction | Giới hạn quyền owner | Khóa/giới hạn quyền chủ sân trên cụm sân: limited hoặc blocked | Không FK |
| 98 | venue_cluster_amenities | Chưa phân loại | ... | ... | venue_clusters (venue_cluster_id), amenities (amenity_id) |
| 99 | contract_templates | Chưa phân loại | ... | ... | Không FK |
| 100 | partner_documents | Chưa phân loại | ... | ... | Không FK |
| 101 | contract_signatures | Chưa phân loại | ... | ... | partner_contracts (partner_contract_id) |
| 102 | partner_liquidations | Chưa phân loại | ... | ... | partner_contracts (partner_contract_id), partner_termination_requests (termination_request_id) |
| 103 | partner_histories | Chưa phân loại | ... | ... | Không FK |
| 104 | venue_location_change_requests | Chưa phân loại | ... | ... | venue_clusters (venue_cluster_id), users (requested_by), users (reviewed_by) |

==================================================
## PHẦN 2. CHI TIẾT CÁC BẢNG
==================================================

### MODULE: AUTH/RBAC

## Tên bảng: users

### 1. Mục đích bảng
Lưu trữ thông tin tài khoản người dùng, phục vụ đăng nhập, quản lý hồ sơ và là thực thể cốt lõi cho mọi nghiệp vụ như booking, chat, role.

### 2. Danh sách trường

| STT | Tên trường | Kiểu dữ liệu | Null | Default | Khóa/Ràng buộc | Mô tả tác dụng trường | Ví dụ |
|---|---|---|---|---|---|---|---|
| 1 | id | char | Không | - | PK | UUID định danh user | 10000000-0000-0000-0000-000000000001 |
| 2 | username | string | Không | - | - | Tên tài khoản dùng để đăng nhập | john_doe |
| 3 | full_name | string | Không | - | - | Họ tên hiển thị | John Doe |
| 4 | phone | string | Có | - | - | Số điện thoại chính | 0901234567 |
| 5 | email | string | Có | - | - | Email phụ dùng đăng nhập/reset pass | john@example.com |
| 6 | email_verified_at | timestamp | Có | - | - | Thời điểm xác thực email | 2026-06-15 18:00:00 |
| 7 | phone_verified_at | timestamp | Có | - | - | Thời điểm xác thực phone | null |
| 8 | password | string | Không | - | - | Mật khẩu đã hash | $2y$10$... |
| 9 | avatar_url | string | Có | - | - | Đường dẫn avatar hiện tại | /storage/avatar.jpg |
| 10 | bio | text | Có | - | - | Mô tả cá nhân do user tự nhập | Yêu thể thao, tìm kèo thứ 7 |
| 11 | status_reason | text | Có | - | - | Lý do khóa/hủy tài khoản | null |
| 12 | locked_at | timestamp | Có | - | - | Thời điểm bị khóa | null |
| 13 | locked_until | timestamp | Có | - | - | Thời điểm hết khóa tạm thời | null |
| 14 | locked_by | char | Có | - | FK | Admin thực hiện khóa tài khoản | null |
| 15 | remember_token | string | Có | - | - | Token remember me Laravel | abcxyz... |
| 16 | created_at | timestamp | Có | - | - | (Cần cập nhật) | - |
| 17 | updated_at | timestamp | Có | - | - | (Cần cập nhật) | - |

### 3. Khóa chính, khóa ngoại, index
- PK: id
- FK: locked_by -> users.id

### 4. Quan hệ với bảng khác
- users 1-n users qua locked_by
- users n-n roles qua user_roles
- users 1-n bookings qua bookings.customer_id
- Tham gia vào hầu hết các bảng khác trong hệ thống qua khóa ngoại.

### 5. Ví dụ bản ghi
```json
{
  "id": "10000000-0000-0000-0000-000000000001",
  "username": "admin123",
  "full_name": "Nguyễn Văn Admin",
  "email": "admin@sportgo.vn",
  "status": "active"
}
```

---
## Tên bảng: password_reset_tokens

### 1. Mục đích bảng
(Chưa có mô tả)

### 2. Danh sách trường

| STT | Tên trường | Kiểu dữ liệu | Null | Default | Khóa/Ràng buộc | Mô tả tác dụng trường | Ví dụ |
|---|---|---|---|---|---|---|---|
| 1 | email | string | Không | - | - | (Cần cập nhật) | - |
| 2 | token | string | Không | - | - | (Cần cập nhật) | - |
| 3 | created_at | timestamp | Có | - | - | (Cần cập nhật) | - |

### 3. Khóa chính, khóa ngoại, index
- PK: id

### 4. Quan hệ với bảng khác
(Chưa có)

### 5. Ví dụ bản ghi
```json
{}
```

---
## Tên bảng: sessions

### 1. Mục đích bảng
(Chưa có mô tả)

### 2. Danh sách trường

| STT | Tên trường | Kiểu dữ liệu | Null | Default | Khóa/Ràng buộc | Mô tả tác dụng trường | Ví dụ |
|---|---|---|---|---|---|---|---|
| 1 | id | string | Không | - | PK | (Cần cập nhật) | - |
| 2 | user_id | char | Có | - | FK | (Cần cập nhật) | - |
| 3 | ip_address | string | Có | - | - | (Cần cập nhật) | - |
| 4 | user_agent | text | Có | - | - | (Cần cập nhật) | - |
| 5 | payload | longText | Không | - | - | (Cần cập nhật) | - |
| 6 | last_activity | integer | Không | - | - | (Cần cập nhật) | - |

### 3. Khóa chính, khóa ngoại, index
- PK: id
- FK: user_id -> users.id

### 4. Quan hệ với bảng khác
(Chưa có)

### 5. Ví dụ bản ghi
```json
{}
```

---
## Tên bảng: cache

### 1. Mục đích bảng
(Chưa có mô tả)

### 2. Danh sách trường

| STT | Tên trường | Kiểu dữ liệu | Null | Default | Khóa/Ràng buộc | Mô tả tác dụng trường | Ví dụ |
|---|---|---|---|---|---|---|---|
| 1 | key | string | Không | - | - | (Cần cập nhật) | - |
| 2 | value | mediumText | Không | - | - | (Cần cập nhật) | - |
| 3 | expiration | bigInteger | Không | - | - | (Cần cập nhật) | - |

### 3. Khóa chính, khóa ngoại, index
- PK: id

### 4. Quan hệ với bảng khác
(Chưa có)

### 5. Ví dụ bản ghi
```json
{}
```

---
## Tên bảng: cache_locks

### 1. Mục đích bảng
(Chưa có mô tả)

### 2. Danh sách trường

| STT | Tên trường | Kiểu dữ liệu | Null | Default | Khóa/Ràng buộc | Mô tả tác dụng trường | Ví dụ |
|---|---|---|---|---|---|---|---|
| 1 | key | string | Không | - | - | (Cần cập nhật) | - |
| 2 | owner | string | Không | - | - | (Cần cập nhật) | - |
| 3 | expiration | bigInteger | Không | - | - | (Cần cập nhật) | - |

### 3. Khóa chính, khóa ngoại, index
- PK: id

### 4. Quan hệ với bảng khác
(Chưa có)

### 5. Ví dụ bản ghi
```json
{}
```

---
## Tên bảng: jobs

### 1. Mục đích bảng
(Chưa có mô tả)

### 2. Danh sách trường

| STT | Tên trường | Kiểu dữ liệu | Null | Default | Khóa/Ràng buộc | Mô tả tác dụng trường | Ví dụ |
|---|---|---|---|---|---|---|---|
| 1 | queue | string | Không | - | - | (Cần cập nhật) | - |
| 2 | payload | longText | Không | - | - | (Cần cập nhật) | - |
| 3 | attempts | unsignedSmallInteger | Không | - | - | (Cần cập nhật) | - |
| 4 | reserved_at | unsignedInteger | Có | - | - | (Cần cập nhật) | - |
| 5 | available_at | unsignedInteger | Không | - | - | (Cần cập nhật) | - |
| 6 | created_at | unsignedInteger | Không | - | - | (Cần cập nhật) | - |

### 3. Khóa chính, khóa ngoại, index
- PK: id

### 4. Quan hệ với bảng khác
(Chưa có)

### 5. Ví dụ bản ghi
```json
{}
```

---
## Tên bảng: job_batches

### 1. Mục đích bảng
(Chưa có mô tả)

### 2. Danh sách trường

| STT | Tên trường | Kiểu dữ liệu | Null | Default | Khóa/Ràng buộc | Mô tả tác dụng trường | Ví dụ |
|---|---|---|---|---|---|---|---|
| 1 | id | string | Không | - | PK | (Cần cập nhật) | - |
| 2 | name | string | Không | - | - | (Cần cập nhật) | - |
| 3 | total_jobs | integer | Không | - | - | (Cần cập nhật) | - |
| 4 | pending_jobs | integer | Không | - | - | (Cần cập nhật) | - |
| 5 | failed_jobs | integer | Không | - | - | (Cần cập nhật) | - |
| 6 | failed_job_ids | longText | Không | - | - | (Cần cập nhật) | - |
| 7 | options | mediumText | Có | - | - | (Cần cập nhật) | - |
| 8 | cancelled_at | integer | Có | - | - | (Cần cập nhật) | - |
| 9 | created_at | integer | Không | - | - | (Cần cập nhật) | - |
| 10 | finished_at | integer | Có | - | - | (Cần cập nhật) | - |

### 3. Khóa chính, khóa ngoại, index
- PK: id

### 4. Quan hệ với bảng khác
(Chưa có)

### 5. Ví dụ bản ghi
```json
{}
```

---
## Tên bảng: failed_jobs

### 1. Mục đích bảng
(Chưa có mô tả)

### 2. Danh sách trường

| STT | Tên trường | Kiểu dữ liệu | Null | Default | Khóa/Ràng buộc | Mô tả tác dụng trường | Ví dụ |
|---|---|---|---|---|---|---|---|
| 1 | uuid | string | Không | - | - | (Cần cập nhật) | - |
| 2 | connection | string | Không | - | - | (Cần cập nhật) | - |
| 3 | queue | string | Không | - | - | (Cần cập nhật) | - |
| 4 | payload | longText | Không | - | - | (Cần cập nhật) | - |
| 5 | exception | longText | Không | - | - | (Cần cập nhật) | - |
| 6 | failed_at | timestamp | Không | - | - | (Cần cập nhật) | - |

### 3. Khóa chính, khóa ngoại, index
- PK: id

### 4. Quan hệ với bảng khác
(Chưa có)

### 5. Ví dụ bản ghi
```json
{}
```

---
## Tên bảng: audit_logs

### 1. Mục đích bảng
Lịch sử kiểm toán, ghi lại mọi thao tác quan trọng (thêm/sửa/xóa bảng nhạy cảm) của bất kỳ ai.

### 2. Danh sách trường

| STT | Tên trường | Kiểu dữ liệu | Null | Default | Khóa/Ràng buộc | Mô tả tác dụng trường | Ví dụ |
|---|---|---|---|---|---|---|---|
| 1 | id | char | Không | - | PK | (Cần cập nhật) | - |
| 2 | actor_id | char | Có | - | FK | (Cần cập nhật) | - |
| 3 | action | string | Không | - | - | (Cần cập nhật) | - |
| 4 | entity_type | string | Không | - | - | (Cần cập nhật) | - |
| 5 | entity_id | string | Không | - | - | (Cần cập nhật) | - |
| 6 | old_values | json | Có | - | - | (Cần cập nhật) | - |
| 7 | new_values | json | Có | - | - | (Cần cập nhật) | - |
| 8 | context | string | Có | - | - | (Cần cập nhật) | - |
| 9 | ip_address | string | Có | - | - | (Cần cập nhật) | - |
| 10 | user_agent | string | Có | - | - | (Cần cập nhật) | - |
| 11 | created_at | timestamp | Có | - | - | (Cần cập nhật) | - |

### 3. Khóa chính, khóa ngoại, index
- PK: id
- FK: actor_id -> users.id

### 4. Quan hệ với bảng khác
(Chưa có)

### 5. Ví dụ bản ghi
(Chưa có)

---
## Tên bảng: banners

### 1. Mục đích bảng
(Chưa có mô tả)

### 2. Danh sách trường

| STT | Tên trường | Kiểu dữ liệu | Null | Default | Khóa/Ràng buộc | Mô tả tác dụng trường | Ví dụ |
|---|---|---|---|---|---|---|---|
| 1 | id | char | Không | - | PK | (Cần cập nhật) | - |
| 2 | title | string | Không | - | - | (Cần cập nhật) | - |
| 3 | image_path | string | Không | - | - | (Cần cập nhật) | - |
| 4 | link_url | string | Có | - | - | (Cần cập nhật) | - |
| 5 | position | string | Không | - | - | (Cần cập nhật) | - |
| 6 | sort_order | integer | Không | 0 | - | (Cần cập nhật) | - |
| 7 | is_active | boolean | Không | true | - | (Cần cập nhật) | - |
| 8 | starts_at | timestamp | Có | - | - | (Cần cập nhật) | - |
| 9 | ends_at | timestamp | Có | - | - | (Cần cập nhật) | - |
| 10 | created_by | char | Có | - | FK | (Cần cập nhật) | - |
| 11 | updated_by | char | Có | - | FK | (Cần cập nhật) | - |
| 12 | created_at | timestamp | Có | - | - | (Cần cập nhật) | - |
| 13 | updated_at | timestamp | Có | - | - | (Cần cập nhật) | - |

### 3. Khóa chính, khóa ngoại, index
- PK: id
- FK: created_by -> users.id, updated_by -> users.id

### 4. Quan hệ với bảng khác
(Chưa có)

### 5. Ví dụ bản ghi
(Chưa có)

---
## Tên bảng: venue_clusters

### 1. Mục đích bảng
Lưu trữ thông tin cơ sở kinh doanh (cụm sân) bao gồm tên, địa chỉ, chủ sở hữu, đánh giá và trạng thái duyệt.

### 2. Danh sách trường

| STT | Tên trường | Kiểu dữ liệu | Null | Default | Khóa/Ràng buộc | Mô tả tác dụng trường | Ví dụ |
|---|---|---|---|---|---|---|---|
| 1 | id | char | Không | - | PK | ID cụm sân | 20000000-... |
| 2 | owner_id | char | Không | - | FK | Chủ sân sở hữu cụm này | 10000000-... |
| 3 | name | string | Không | - | - | Tên cụm sân hiển thị cho user | Sân cầu lông 247 |
| 4 | slug | string | Không | - | - | Định danh URL/SEO | san-cau-long-247 |
| 5 | description | text | Có | - | - | Mô tả cụm sân, tiện ích | Sân mới xây... |
| 6 | phone_contact | string | Có | - | - | Số điện thoại liên hệ | 0988776655 |
| 7 | address | text | Không | - | - | Địa chỉ thực tế | Số 1 đường X |
| 8 | map_url | string | Có | - | - | Link Google Maps | https://goo.gl/... |
| 9 | amenities | json | Có | - | - | Danh sách tiện ích (wifi, bãi xe...) | ["wifi", "parking"] |
| 10 | status_reason | text | Có | - | - | Lý do khóa cụm sân | null |
| 11 | locked_at | timestamp | Có | - | - | Thời điểm bị khóa | null |
| 12 | locked_until | timestamp | Có | - | - | Thời điểm hết khóa tạm thời | null |
| 13 | locked_by | char | Có | - | FK | Admin khóa cụm sân | null |
| 14 | rating_count | unsignedInteger | Không | 0 | - | Số lượt review | 150 |
| 15 | created_at | timestamp | Có | - | - | (Cần cập nhật) | - |
| 16 | updated_at | timestamp | Có | - | - | (Cần cập nhật) | - |

### 3. Khóa chính, khóa ngoại, index
- PK: id
- FK: locked_by -> users.id, owner_id -> users.id

### 4. Quan hệ với bảng khác
- venue_clusters 1-n venue_courts qua venue_cluster_id
- venue_clusters 1-n bookings qua venue_cluster_id (denormalized)
- users 1-n venue_clusters qua owner_id

### 5. Ví dụ bản ghi
```json
{
  "id": "20000000-0000-0000-0000-000000000001",
  "owner_id": "10000000-0000-0000-0000-000000000002",
  "name": "Sân Cầu Lông Đăng Khoa",
  "latitude": 21.033,
  "longitude": 105.8,
  "status": "active"
}
```

---
## Tên bảng: booking_configs

### 1. Mục đích bảng
Cấu hình linh hoạt cho từng cụm sân (tiền cọc, thời gian đặt tối thiểu, chính sách hoàn tiền).

### 2. Danh sách trường

| STT | Tên trường | Kiểu dữ liệu | Null | Default | Khóa/Ràng buộc | Mô tả tác dụng trường | Ví dụ |
|---|---|---|---|---|---|---|---|
| 1 | venue_cluster_id | char | Không | - | FK | ID cụm sân | 2000... |
| 2 | min_duration_minutes | unsignedInteger | Không | 30 | - | Thời gian đặt tối thiểu (phút) | 60 |
| 3 | max_duration_minutes | unsignedInteger | Có | - | - | Thời gian đặt tối đa (phút) | null |
| 4 | slot_hold_minutes | unsignedInteger | Không | 20 | - | Giữ chỗ trước khi thanh toán (phút) | 15 |
| 5 | reminder_before_minutes | unsignedInteger | Không | 30 | - | Gửi nhắc nhở trước giờ chơi (phút) | 30 |
| 6 | allow_full_payment | boolean | Không | true | - | Cho phép thanh toán 100% | 1 |
| 7 | allow_deposit | boolean | Không | true | - | Cho phép cọc | 1 |
| 8 | allow_no_prepay | boolean | Không | true | - | Cho phép không trả trước | 0 |
| 9 | auto_approve_full_payment | boolean | Không | false | - | Tự duyệt khi thanh toán đủ | 1 |
| 10 | cancel_before_hours | unsignedInteger | Không | 0 | - | Số giờ tối thiểu báo hủy để hoàn | 24 |
| 11 | refund_percent | unsignedInteger | Không | 0 | - | Phần trăm hoàn tiền nếu hủy chuẩn | 100 |
| 12 | created_at | timestamp | Có | - | - | (Cần cập nhật) | - |
| 13 | updated_at | timestamp | Có | - | - | (Cần cập nhật) | - |

### 3. Khóa chính, khóa ngoại, index
- PK: id
- FK: venue_cluster_id -> venue_clusters.id

### 4. Quan hệ với bảng khác
- Liên kết 1-1 với venue_clusters.

### 5. Ví dụ bản ghi
```json
{
  "venue_cluster_id": "20000000-0000-0000-0000-000000000001",
  "min_duration_minutes": 60,
  "deposit_percent": 50.00,
  "cancel_before_hours": 24,
  "refund_percent": 100
}
```

---
## Tên bảng: court_types

### 1. Mục đích bảng
Lưu trữ danh mục các môn thể thao hoặc loại sân. Dùng cho cả hệ thống quản lý môn thể thao.

### 2. Danh sách trường

| STT | Tên trường | Kiểu dữ liệu | Null | Default | Khóa/Ràng buộc | Mô tả tác dụng trường | Ví dụ |
|---|---|---|---|---|---|---|---|
| 1 | name | string | Không | - | - | Tên môn / loại sân | Cầu lông |
| 2 | description | text | Có | - | - | Mô tả loại sân | Sân tiêu chuẩn |
| 3 | player_count | unsignedInteger | Không | 0 | - | Số người chơi tham khảo | 4 |
| 4 | is_active | boolean | Không | true | - | Còn áp dụng không | 1 |
| 5 | created_at | timestamp | Có | - | - | (Cần cập nhật) | - |
| 6 | updated_at | timestamp | Có | - | - | (Cần cập nhật) | - |
| 7 | deleted_at | timestamp | Có | - | - | Soft delete | null |

### 3. Khóa chính, khóa ngoại, index
- PK: id

### 4. Quan hệ với bảng khác
- Loại sân cha-con (parent_id)
- court_types 1-n venue_courts qua court_type_id

### 5. Ví dụ bản ghi
```json
{
  "id": 1,
  "name": "Sân Cầu Lông",
  "player_count": 4,
  "is_active": true
}
```

---
## Tên bảng: venue_staff_assignments

### 1. Mục đích bảng
Quản lý phân công nhân viên phục vụ, quản lý cho 1 cụm sân, hỗ trợ phân công theo từng loại sân nhỏ (scope).

### 2. Danh sách trường

| STT | Tên trường | Kiểu dữ liệu | Null | Default | Khóa/Ràng buộc | Mô tả tác dụng trường | Ví dụ |
|---|---|---|---|---|---|---|---|
| 1 | user_id | char | Không | - | FK | ID nhân viên | 1000... |
| 2 | venue_cluster_id | char | Không | - | FK | ID cụm sân làm việc | 2000... |
| 3 | court_type_id | unsignedBigInteger | Có | - | FK | Nếu quản lý loại sân thì điền ID môn | null |
| 4 | scope_key | string | Không | all | - | Key đặc biệt để phân biệt | all |
| 5 | assigned_by | char | Có | - | FK | Admin/chủ sân giao việc | null |
| 6 | created_at | timestamp | Có | - | - | (Cần cập nhật) | - |
| 7 | updated_at | timestamp | Có | - | - | (Cần cập nhật) | - |

### 3. Khóa chính, khóa ngoại, index
- PK: id
- FK: assigned_by -> users.id, court_type_id -> court_types.id, user_id -> users.id, venue_cluster_id -> venue_clusters.id

### 4. Quan hệ với bảng khác
- Kết nối nhân viên (users) với cụm sân (venue_clusters) và có thể lọc theo môn (court_types).

### 5. Ví dụ bản ghi
```json
{
  "user_id": "10000000-0000-0000-0000-000000000011",
  "venue_cluster_id": "20000000-0000-0000-0000-000000000001",
  "scope_type": "all_cluster",
  "status": "active"
}
```

---
## Tên bảng: venue_courts

### 1. Mục đích bảng
Lưu trữ thông tin các "sân con" nằm bên trong một cụm sân. Khách hàng thực tế đặt lịch trên các sân con này.

### 2. Danh sách trường

| STT | Tên trường | Kiểu dữ liệu | Null | Default | Khóa/Ràng buộc | Mô tả tác dụng trường | Ví dụ |
|---|---|---|---|---|---|---|---|
| 1 | id | char | Không | - | PK | ID sân con | 3000... |
| 2 | venue_cluster_id | char | Không | - | FK | ID cụm sân chứa sân này | 2000... |
| 3 | court_type_id | unsignedBigInteger | Không | - | FK | ID môn thể thao | 1 |
| 4 | name | string | Không | - | - | Tên gọi của sân con | Sân số 1 |
| 5 | sort_order | integer | Không | 0 | - | Thứ tự hiển thị UI | 1 |
| 6 | created_at | timestamp | Có | - | - | (Cần cập nhật) | - |
| 7 | updated_at | timestamp | Có | - | - | (Cần cập nhật) | - |
| 8 | deleted_at | timestamp | Có | - | - | Soft delete | null |

### 3. Khóa chính, khóa ngoại, index
- PK: id
- FK: court_type_id -> court_types.id, venue_cluster_id -> venue_clusters.id

### 4. Quan hệ với bảng khác
- Thuộc về venue_clusters và court_types
- 1-n với bookings qua venue_court_id

### 5. Ví dụ bản ghi
```json
{
  "id": "30000000-0000-0000-0000-000000000001",
  "venue_cluster_id": "20000000-0000-0000-0000-000000000001",
  "court_type_id": 1,
  "name": "Sân Thảm Xanh 01",
  "status": "active"
}
```

---
## Tên bảng: community_posts

### 1. Mục đích bảng
Lưu trữ bài đăng thảo luận tự do của người dùng trên trang cộng đồng.

### 2. Danh sách trường

| STT | Tên trường | Kiểu dữ liệu | Null | Default | Khóa/Ràng buộc | Mô tả tác dụng trường | Ví dụ |
|---|---|---|---|---|---|---|---|
| 1 | id | char | Không | - | PK | (Cần cập nhật) | - |
| 2 | author_id | char | Không | - | FK | (Cần cập nhật) | - |
| 3 | content | longText | Không | - | - | (Cần cập nhật) | - |
| 4 | reviewed_by | char | Có | - | FK | (Cần cập nhật) | - |
| 5 | reviewed_at | timestamp | Có | - | - | (Cần cập nhật) | - |
| 6 | status_reason | text | Có | - | - | (Cần cập nhật) | - |
| 7 | view_count | unsignedBigInteger | Không | 0 | - | (Cần cập nhật) | - |
| 8 | like_count | unsignedInteger | Không | 0 | - | (Cần cập nhật) | - |
| 9 | comment_count | unsignedInteger | Không | 0 | - | (Cần cập nhật) | - |
| 10 | created_at | timestamp | Có | - | - | (Cần cập nhật) | - |
| 11 | updated_at | timestamp | Có | - | - | (Cần cập nhật) | - |

### 3. Khóa chính, khóa ngoại, index
- PK: id
- FK: author_id -> users.id, reviewed_by -> users.id

### 4. Quan hệ với bảng khác
(Chưa có)

### 5. Ví dụ bản ghi
(Chưa có)

---
## Tên bảng: venue_court_approval_requests

### 1. Mục đích bảng
Khi chủ sân muốn tạo thêm sân con, họ gửi yêu cầu và admin duyệt trước khi sân hiện ra trên hệ thống.

### 2. Danh sách trường

| STT | Tên trường | Kiểu dữ liệu | Null | Default | Khóa/Ràng buộc | Mô tả tác dụng trường | Ví dụ |
|---|---|---|---|---|---|---|---|
| 1 | id | char | Không | - | PK | ID yêu cầu duyệt | 4000... |
| 2 | venue_cluster_id | char | Không | - | FK | Cụm sân muốn thêm | 2000... |
| 3 | court_type_id | unsignedBigInteger | Không | - | FK | Loại sân muốn thêm | 1 |
| 4 | name | string | Không | - | - | Tên sân con dự kiến | Sân số 2 |
| 5 | requested_by | char | Không | - | FK | Chủ sân gửi yêu cầu | 1000... |
| 6 | reviewed_by | char | Có | - | FK | Admin duyệt | null |
| 7 | status_reason | text | Có | - | - | Lý do từ chối | null |
| 8 | approved_venue_court_id | char | Có | - | - | ID sân con được sinh ra sau duyệt | null |
| 9 | reviewed_at | timestamp | Có | - | - | Thời điểm duyệt | null |
| 10 | created_at | timestamp | Có | - | - | (Cần cập nhật) | - |
| 11 | updated_at | timestamp | Có | - | - | (Cần cập nhật) | - |

### 3. Khóa chính, khóa ngoại, index
- PK: id
- FK: court_type_id -> court_types.id, requested_by -> users.id, reviewed_by -> users.id, venue_cluster_id -> venue_clusters.id

### 4. Quan hệ với bảng khác
- Khi duyệt xong sẽ tạo ra 1 record ở venue_courts và lưu ID vào approved_venue_court_id (logical reference).

### 5. Ví dụ bản ghi
```json
{
  "id": "40000000-0000-0000-0000-000000000001",
  "name": "Sân Cầu Lông VIP",
  "status": "pending",
  "requested_by": "10000000-0000-0000-0000-000000000002"
}
```

---
## Tên bảng: conversations

### 1. Mục đích bảng
Lưu trữ các phiên hội thoại (phòng chat).

### 2. Danh sách trường

| STT | Tên trường | Kiểu dữ liệu | Null | Default | Khóa/Ràng buộc | Mô tả tác dụng trường | Ví dụ |
|---|---|---|---|---|---|---|---|
| 1 | id | char | Không | - | PK | (Cần cập nhật) | - |
| 2 | reference_type | string | Có | - | - | (Cần cập nhật) | - |
| 3 | reference_id | string | Có | - | - | (Cần cập nhật) | - |
| 4 | title | string | Có | - | - | (Cần cập nhật) | - |
| 5 | created_by | char | Có | - | FK | (Cần cập nhật) | - |
| 6 | last_message_at | timestamp | Có | - | - | (Cần cập nhật) | - |
| 7 | created_at | timestamp | Có | - | - | (Cần cập nhật) | - |
| 8 | updated_at | timestamp | Có | - | - | (Cần cập nhật) | - |

### 3. Khóa chính, khóa ngoại, index
- PK: id
- FK: created_by -> users.id

### 4. Quan hệ với bảng khác
(Chưa có)

### 5. Ví dụ bản ghi
(Chưa có)

---
## Tên bảng: conversation_participants

### 1. Mục đích bảng
(Chưa có mô tả)

### 2. Danh sách trường

| STT | Tên trường | Kiểu dữ liệu | Null | Default | Khóa/Ràng buộc | Mô tả tác dụng trường | Ví dụ |
|---|---|---|---|---|---|---|---|
| 1 | conversation_id | char | Không | - | FK | (Cần cập nhật) | - |
| 2 | user_id | char | Không | - | FK | (Cần cập nhật) | - |
| 3 | last_read_at | timestamp | Có | - | - | (Cần cập nhật) | - |
| 4 | joined_at | timestamp | Không | - | - | (Cần cập nhật) | - |

### 3. Khóa chính, khóa ngoại, index
- PK: id
- FK: conversation_id -> conversations.id, user_id -> users.id

### 4. Quan hệ với bảng khác
(Chưa có)

### 5. Ví dụ bản ghi
(Chưa có)

---
## Tên bảng: partner_applications

### 1. Mục đích bảng
Lưu hồ sơ người dùng gửi lên xin trở thành chủ sân.

### 2. Danh sách trường

| STT | Tên trường | Kiểu dữ liệu | Null | Default | Khóa/Ràng buộc | Mô tả tác dụng trường | Ví dụ |
|---|---|---|---|---|---|---|---|
| 1 | id | char | Không | - | PK | (Cần cập nhật) | - |
| 2 | user_id | char | Không | - | FK | (Cần cập nhật) | - |
| 3 | business_name | string | Không | - | - | (Cần cập nhật) | - |
| 4 | tax_code | string | Có | - | - | (Cần cập nhật) | - |
| 5 | venue_name | string | Không | - | - | (Cần cập nhật) | - |
| 6 | venue_address | text | Không | - | - | (Cần cập nhật) | - |
| 7 | venue_map_url | string | Có | - | - | (Cần cập nhật) | - |
| 8 | reviewed_by | char | Có | - | FK | (Cần cập nhật) | - |
| 9 | status_reason | text | Có | - | - | (Cần cập nhật) | - |
| 10 | approved_venue_cluster_id | char | Có | - | - | (Cần cập nhật) | - |
| 11 | submitted_at | timestamp | Không | - | - | (Cần cập nhật) | - |
| 12 | reviewed_at | timestamp | Có | - | - | (Cần cập nhật) | - |
| 13 | created_at | timestamp | Có | - | - | (Cần cập nhật) | - |
| 14 | updated_at | timestamp | Có | - | - | (Cần cập nhật) | - |

### 3. Khóa chính, khóa ngoại, index
- PK: id
- FK: reviewed_by -> users.id, user_id -> users.id

### 4. Quan hệ với bảng khác
(Chưa có)

### 5. Ví dụ bản ghi
(Chưa có)

---
## Tên bảng: partner_application_courts

### 1. Mục đích bảng
(Chưa có mô tả)

### 2. Danh sách trường

| STT | Tên trường | Kiểu dữ liệu | Null | Default | Khóa/Ràng buộc | Mô tả tác dụng trường | Ví dụ |
|---|---|---|---|---|---|---|---|
| 1 | partner_application_id | char | Không | - | FK | (Cần cập nhật) | - |
| 2 | court_type_id | unsignedBigInteger | Không | - | FK | (Cần cập nhật) | - |
| 3 | name | string | Không | - | - | (Cần cập nhật) | - |
| 4 | sort_order | integer | Không | 0 | - | (Cần cập nhật) | - |
| 5 | created_at | timestamp | Có | - | - | (Cần cập nhật) | - |
| 6 | updated_at | timestamp | Có | - | - | (Cần cập nhật) | - |

### 3. Khóa chính, khóa ngoại, index
- PK: id
- FK: court_type_id -> court_types.id, partner_application_id -> partner_applications.id

### 4. Quan hệ với bảng khác
(Chưa có)

### 5. Ví dụ bản ghi
(Chưa có)

---
## Tên bảng: roles

### 1. Mục đích bảng
Lưu trữ danh mục các nhóm quyền (vai trò) dùng để phân quyền (RBAC) cho users.

### 2. Danh sách trường

| STT | Tên trường | Kiểu dữ liệu | Null | Default | Khóa/Ràng buộc | Mô tả tác dụng trường | Ví dụ |
|---|---|---|---|---|---|---|---|
| 1 | name | string | Không | - | - | Mã role duy nhất dùng trong code | venue_owner |
| 2 | display_name | string | Không | - | - | Tên role dễ đọc hiển thị UI | Chủ sân |
| 3 | description | text | Có | - | - | Mô tả quyền hạn của role | Quản lý cụm sân của mình |
| 4 | is_system | boolean | Không | false | - | Là role hệ thống mặc định, không xóa được | 1 |
| 5 | created_at | timestamp | Có | - | - | (Cần cập nhật) | - |
| 6 | updated_at | timestamp | Có | - | - | (Cần cập nhật) | - |

### 3. Khóa chính, khóa ngoại, index
- PK: id

### 4. Quan hệ với bảng khác
- roles n-n users qua user_roles
- roles n-n permissions qua role_permissions

### 5. Ví dụ bản ghi
```json
{
  "id": 1,
  "name": "venue_owner",
  "display_name": "Chủ sân",
  "is_system": true
}
```

---
## Tên bảng: bookings

### 1. Mục đích bảng
Lưu trữ toàn bộ thông tin đơn đặt sân (booking lẻ và cố định), ngày giờ chơi, tiền thanh toán.

### 2. Danh sách trường

| STT | Tên trường | Kiểu dữ liệu | Null | Default | Khóa/Ràng buộc | Mô tả tác dụng trường | Ví dụ |
|---|---|---|---|---|---|---|---|
| 1 | id | char | Không | - | PK | ID booking | 5000... |
| 2 | booking_code | string | Không | - | - | Mã booking dễ đọc (VD: BKG123) | BKG-ABC123X |
| 3 | customer_id | char | Có | - | FK | Khách đặt online (null = walk-in) | 1000... |
| 4 | venue_cluster_id | char | Không | - | - | Cụm sân (denormalized để filter) | 2000... |
| 5 | booking_date | date | Không | - | - | Ngày chơi | 2026-10-15 |
| 6 | recurring_group_code | string | Có | - | - | Mã nhóm đơn cố định | null |
| 7 | recurring_start_date | date | Có | - | - | (Cần cập nhật) | - |
| 8 | recurring_end_date | date | Có | - | - | (Cần cập nhật) | - |
| 9 | recurrence_interval | unsignedInteger | Có | - | - | Khoảng lặp | null |
| 10 | recurrence_days_of_week | json | Có | - | - | (Cần cập nhật) | - |
| 11 | recurrence_days_of_month | json | Có | - | - | (Cần cập nhật) | - |
| 12 | walk_in_name | string | Có | - | - | Tên khách vãng lai | Khách vãng lai |
| 13 | walk_in_phone | string | Có | - | - | SĐT khách vãng lai | 0911223344 |
| 14 | status_reason | text | Có | - | - | Lý do hủy/từ chối | Khách báo hủy |
| 15 | cancelled_by | char | Có | - | FK | (Cần cập nhật) | - |
| 16 | cancelled_at | timestamp | Có | - | - | (Cần cập nhật) | - |
| 17 | created_by | char | Có | - | FK | (Cần cập nhật) | - |
| 18 | reminder_sent_at | timestamp | Có | - | - | (Cần cập nhật) | - |
| 19 | created_at | timestamp | Có | - | - | (Cần cập nhật) | - |
| 20 | updated_at | timestamp | Có | - | - | (Cần cập nhật) | - |

### 3. Khóa chính, khóa ngoại, index
- PK: id
- FK: cancelled_by -> users.id, created_by -> users.id, customer_id -> users.id

### 4. Quan hệ với bảng khác
- 1-n với payments, refunds, complaints, reviews
- Gắn chặt với venue_courts và venue_clusters.

### 5. Ví dụ bản ghi
```json
{
  "booking_code": "BKG-261015",
  "booking_date": "2026-10-15",
  "start_time": "18:00:00",
  "end_time": "20:00:00",
  "total_price": 200000.00,
  "status": "confirmed"
}
```

---
## Tên bảng: community_post_comments

### 1. Mục đích bảng
(Chưa có mô tả)

### 2. Danh sách trường

| STT | Tên trường | Kiểu dữ liệu | Null | Default | Khóa/Ràng buộc | Mô tả tác dụng trường | Ví dụ |
|---|---|---|---|---|---|---|---|
| 1 | id | char | Không | - | PK | (Cần cập nhật) | - |
| 2 | post_id | char | Không | - | FK | (Cần cập nhật) | - |
| 3 | user_id | char | Không | - | FK | (Cần cập nhật) | - |
| 4 | content | longText | Không | - | - | (Cần cập nhật) | - |
| 5 | parent_id | char | Có | - | FK | (Cần cập nhật) | - |
| 6 | created_at | timestamp | Có | - | - | (Cần cập nhật) | - |
| 7 | updated_at | timestamp | Có | - | - | (Cần cập nhật) | - |

### 3. Khóa chính, khóa ngoại, index
- PK: id
- FK: parent_id -> community_post_comments.id, post_id -> community_posts.id, user_id -> users.id

### 4. Quan hệ với bảng khác
(Chưa có)

### 5. Ví dụ bản ghi
```json
{}
```

---
## Tên bảng: community_post_likes

### 1. Mục đích bảng
(Chưa có mô tả)

### 2. Danh sách trường

| STT | Tên trường | Kiểu dữ liệu | Null | Default | Khóa/Ràng buộc | Mô tả tác dụng trường | Ví dụ |
|---|---|---|---|---|---|---|---|
| 1 | post_id | char | Không | - | FK | (Cần cập nhật) | - |
| 2 | user_id | char | Không | - | FK | (Cần cập nhật) | - |
| 3 | created_at | timestamp | Có | - | - | (Cần cập nhật) | - |

### 3. Khóa chính, khóa ngoại, index
- PK: id
- FK: post_id -> community_posts.id, user_id -> users.id

### 4. Quan hệ với bảng khác
(Chưa có)

### 5. Ví dụ bản ghi
```json
{}
```

---
## Tên bảng: complaints

### 1. Mục đích bảng
(Chưa có mô tả)

### 2. Danh sách trường

| STT | Tên trường | Kiểu dữ liệu | Null | Default | Khóa/Ràng buộc | Mô tả tác dụng trường | Ví dụ |
|---|---|---|---|---|---|---|---|
| 1 | id | char | Không | - | PK | (Cần cập nhật) | - |
| 2 | booking_id | char | Có | - | FK | (Cần cập nhật) | - |
| 3 | venue_cluster_id | char | Có | - | FK | (Cần cập nhật) | - |
| 4 | customer_id | char | Không | - | FK | (Cần cập nhật) | - |
| 5 | content | text | Không | - | - | (Cần cập nhật) | - |
| 6 | assigned_to | char | Có | - | FK | (Cần cập nhật) | - |
| 7 | resolution_note | text | Có | - | - | (Cần cập nhật) | - |
| 8 | resolved_by | char | Có | - | FK | (Cần cập nhật) | - |
| 9 | resolved_at | timestamp | Có | - | - | (Cần cập nhật) | - |
| 10 | created_at | timestamp | Có | - | - | (Cần cập nhật) | - |
| 11 | updated_at | timestamp | Có | - | - | (Cần cập nhật) | - |

### 3. Khóa chính, khóa ngoại, index
- PK: id
- FK: assigned_to -> users.id, booking_id -> bookings.id, customer_id -> users.id, resolved_by -> users.id, venue_cluster_id -> venue_clusters.id

### 4. Quan hệ với bảng khác
(Chưa có)

### 5. Ví dụ bản ghi
(Chưa có)

---
## Tên bảng: venue_posts

### 1. Mục đích bảng
Lưu trữ các bài viết, thông báo, quảng bá do chủ sân đăng cho cụm sân của mình.

### 2. Danh sách trường

| STT | Tên trường | Kiểu dữ liệu | Null | Default | Khóa/Ràng buộc | Mô tả tác dụng trường | Ví dụ |
|---|---|---|---|---|---|---|---|
| 1 | id | char | Không | - | PK | (Cần cập nhật) | - |
| 2 | venue_cluster_id | char | Không | - | FK | (Cần cập nhật) | - |
| 3 | author_id | char | Không | - | FK | (Cần cập nhật) | - |
| 4 | content | longText | Không | - | - | (Cần cập nhật) | - |
| 5 | reviewed_by | char | Có | - | FK | (Cần cập nhật) | - |
| 6 | reviewed_at | timestamp | Có | - | - | (Cần cập nhật) | - |
| 7 | status_reason | text | Có | - | - | (Cần cập nhật) | - |
| 8 | view_count | unsignedBigInteger | Không | 0 | - | (Cần cập nhật) | - |
| 9 | like_count | unsignedInteger | Không | 0 | - | (Cần cập nhật) | - |
| 10 | comment_count | unsignedInteger | Không | 0 | - | (Cần cập nhật) | - |
| 11 | created_at | timestamp | Có | - | - | (Cần cập nhật) | - |
| 12 | updated_at | timestamp | Có | - | - | (Cần cập nhật) | - |

### 3. Khóa chính, khóa ngoại, index
- PK: id
- FK: author_id -> users.id, reviewed_by -> users.id, venue_cluster_id -> venue_clusters.id

### 4. Quan hệ với bảng khác
(Chưa có)

### 5. Ví dụ bản ghi
(Chưa có)

---
## Tên bảng: favorite_venues

### 1. Mục đích bảng
Lưu danh sách cụm sân yêu thích của người dùng.

### 2. Danh sách trường

| STT | Tên trường | Kiểu dữ liệu | Null | Default | Khóa/Ràng buộc | Mô tả tác dụng trường | Ví dụ |
|---|---|---|---|---|---|---|---|
| 1 | user_id | char | Không | - | FK | User yêu thích sân | 1000... |
| 2 | venue_cluster_id | char | Không | - | FK | Cụm sân được yêu thích | 2000... |
| 3 | created_at | timestamp | Có | - | - | (Cần cập nhật) | - |

### 3. Khóa chính, khóa ngoại, index
- PK: id
- FK: user_id -> users.id, venue_cluster_id -> venue_clusters.id

### 4. Quan hệ với bảng khác
- Cầu nối 1-n giữa users và venue_clusters.

### 5. Ví dụ bản ghi
```json
{
  "user_id": "10000000-0000-0000-0000-000000000001",
  "venue_cluster_id": "20000000-0000-0000-0000-000000000001"
}
```

---


### MODULE: BOOKING



## Tên bảng: payments

### 1. Mục đích bảng
Lưu trữ thông tin giao dịch thanh toán cho các booking.

### 2. Danh sách trường

| STT | Tên trường | Kiểu dữ liệu | Null | Default | Khóa/Ràng buộc | Mô tả tác dụng trường | Ví dụ |
|---|---|---|---|---|---|---|---|
| 1 | id | char | Không | - | PK | ID thanh toán | 7000... |
| 2 | payment_code | string | Không | - | - | Mã thanh toán nội bộ hệ thống | PAY-12345 |
| 3 | booking_id | char | Không | - | FK | ID Booking thanh toán cho | 5000... |
| 4 | method | string | Không | sepay | - | Phương thức (sepay...) | sepay |
| 5 | gateway_txn_id | string | Có | - | - | Mã GD từ cổng thanh toán trả về | SEPAY-999 |
| 6 | gateway_response | json | Có | - | - | Dữ liệu gốc từ gateway | {"status":"ok"} |
| 7 | paid_at | timestamp | Có | - | - | Thời điểm thanh toán thành công | 2026-06-15 |
| 8 | created_at | timestamp | Có | - | - | (Cần cập nhật) | - |
| 9 | updated_at | timestamp | Có | - | - | (Cần cập nhật) | - |

### 3. Khóa chính, khóa ngoại, index
- PK: id
- FK: booking_id -> bookings.id

### 4. Quan hệ với bảng khác
- 1 booking có thể có nhiều payments (cọc rồi thanh toán nốt).
- 1 payment thuộc về 1 system_bank_accounts.

---
## Tên bảng: holiday_prices

### 1. Mục đích bảng
Ghi đè giá ở bảng price_slots vào các ngày lễ hoặc ngày đặc biệt.

### 2. Danh sách trường

| STT | Tên trường | Kiểu dữ liệu | Null | Default | Khóa/Ràng buộc | Mô tả tác dụng trường | Ví dụ |
|---|---|---|---|---|---|---|---|
| 1 | id | char | Không | - | PK | (Cần cập nhật) | - |
| 2 | venue_cluster_id | char | Không | - | FK | (Cần cập nhật) | - |
| 3 | court_type_id | unsignedBigInteger | Không | - | FK | (Cần cập nhật) | - |
| 4 | holiday_date | date | Không | - | - | (Cần cập nhật) | - |
| 5 | start_time | time | Không | 00:00:00 | - | (Cần cập nhật) | - |
| 6 | end_time | time | Không | 23:59:59 | - | (Cần cập nhật) | - |
| 7 | note | string | Có | - | - | (Cần cập nhật) | - |
| 8 | is_active | boolean | Không | true | - | (Cần cập nhật) | - |
| 9 | created_at | timestamp | Có | - | - | (Cần cập nhật) | - |
| 10 | updated_at | timestamp | Có | - | - | (Cần cập nhật) | - |

### 3. Khóa chính, khóa ngoại, index
- PK: id
- FK: court_type_id -> court_types.id, venue_cluster_id -> venue_clusters.id

### 4. Quan hệ với bảng khác
(Chưa có)

### 5. Ví dụ bản ghi
(Chưa có)

---
## Tên bảng: media

### 1. Mục đích bảng
Sử dụng mô hình đa hình (Polymorphic) để lưu trữ mọi file đính kèm (ảnh sân, avatar, file báo cáo) của hệ thống.

### 2. Danh sách trường

| STT | Tên trường | Kiểu dữ liệu | Null | Default | Khóa/Ràng buộc | Mô tả tác dụng trường | Ví dụ |
|---|---|---|---|---|---|---|---|
| 1 | id | char | Không | - | PK | (Cần cập nhật) | - |
| 2 | mediable_type | string | Không | - | - | (Cần cập nhật) | - |
| 3 | mediable_id | string | Không | - | - | (Cần cập nhật) | - |
| 4 | collection | string | Không | default | - | (Cần cập nhật) | - |
| 5 | file_name | string | Không | - | - | (Cần cập nhật) | - |
| 6 | file_path | string | Không | - | - | (Cần cập nhật) | - |
| 7 | mime_type | string | Không | - | - | (Cần cập nhật) | - |
| 8 | file_size | unsignedBigInteger | Không | 0 | - | (Cần cập nhật) | - |
| 9 | sort_order | smallInteger | Không | 0 | - | (Cần cập nhật) | - |
| 10 | created_at | timestamp | Có | - | - | (Cần cập nhật) | - |

### 3. Khóa chính, khóa ngoại, index
- PK: id

### 4. Quan hệ với bảng khác
(Chưa có)

### 5. Ví dụ bản ghi
(Chưa có)

---
## Tên bảng: messages

### 1. Mục đích bảng
(Chưa có mô tả)

### 2. Danh sách trường

| STT | Tên trường | Kiểu dữ liệu | Null | Default | Khóa/Ràng buộc | Mô tả tác dụng trường | Ví dụ |
|---|---|---|---|---|---|---|---|
| 1 | id | char | Không | - | PK | (Cần cập nhật) | - |
| 2 | conversation_id | char | Không | - | FK | (Cần cập nhật) | - |
| 3 | sender_id | char | Có | - | FK | (Cần cập nhật) | - |
| 4 | content | text | Không | - | - | (Cần cập nhật) | - |
| 5 | is_system | boolean | Không | false | - | (Cần cập nhật) | - |
| 6 | reference_type | string | Có | - | - | (Cần cập nhật) | - |
| 7 | reference_id | string | Có | - | - | (Cần cập nhật) | - |
| 8 | created_at | timestamp | Có | - | - | (Cần cập nhật) | - |

### 3. Khóa chính, khóa ngoại, index
- PK: id
- FK: conversation_id -> conversations.id, sender_id -> users.id

### 4. Quan hệ với bảng khác
(Chưa có)

### 5. Ví dụ bản ghi
(Chưa có)

---

### MODULE: SYSTEM & REPORT



## Tên bảng: moderation_configs

### 1. Mục đích bảng
(Chưa có mô tả)

### 2. Danh sách trường

| STT | Tên trường | Kiểu dữ liệu | Null | Default | Khóa/Ràng buộc | Mô tả tác dụng trường | Ví dụ |
|---|---|---|---|---|---|---|---|
| 1 | key | string | Không | - | - | (Cần cập nhật) | - |
| 2 | value | text | Không | - | - | (Cần cập nhật) | - |
| 3 | description | text | Có | - | - | (Cần cập nhật) | - |
| 4 | updated_by | char | Có | - | FK | (Cần cập nhật) | - |
| 5 | created_at | timestamp | Có | - | - | (Cần cập nhật) | - |
| 6 | updated_at | timestamp | Có | - | - | (Cần cập nhật) | - |

### 3. Khóa chính, khóa ngoại, index
- PK: id
- FK: updated_by -> users.id

### 4. Quan hệ với bảng khác
(Chưa có)

### 5. Ví dụ bản ghi
(Chưa có)

---
## Tên bảng: notifications

### 1. Mục đích bảng
(Chưa có mô tả)

### 2. Danh sách trường

| STT | Tên trường | Kiểu dữ liệu | Null | Default | Khóa/Ràng buộc | Mô tả tác dụng trường | Ví dụ |
|---|---|---|---|---|---|---|---|
| 1 | id | char | Không | - | PK | (Cần cập nhật) | - |
| 2 | user_id | char | Không | - | FK | (Cần cập nhật) | - |
| 3 | type | string | Không | - | - | (Cần cập nhật) | - |
| 4 | title | string | Không | - | - | (Cần cập nhật) | - |
| 5 | body | text | Có | - | - | (Cần cập nhật) | - |
| 6 | reference_type | string | Có | - | - | (Cần cập nhật) | - |
| 7 | reference_id | string | Có | - | - | (Cần cập nhật) | - |
| 8 | data | json | Có | - | - | (Cần cập nhật) | - |
| 9 | is_read | boolean | Không | false | - | (Cần cập nhật) | - |
| 10 | read_at | timestamp | Có | - | - | (Cần cập nhật) | - |
| 11 | created_at | timestamp | Có | - | - | (Cần cập nhật) | - |

### 3. Khóa chính, khóa ngoại, index
- PK: id
- FK: user_id -> users.id

### 4. Quan hệ với bảng khác
(Chưa có)

### 5. Ví dụ bản ghi
(Chưa có)

---


### MODULE: SYSTEM (LARAVEL DEFAULT)

## Các bảng hệ thống Laravel

Các bảng này được Laravel tự động sinh ra hoặc sử dụng cho core framework:

1. **password_reset_tokens**: Bảng mặc định hỗ trợ cơ chế Reset Password của Laravel.
2. **sessions**: Bảng lưu trữ Session của user thay vì lưu trên file, phục vụ tính năng quản lý thiết bị đang đăng nhập.
3. **cache & cache_locks**: Bảng dùng làm Database Driver cho tính năng Cache của Laravel, bao gồm tính năng khóa (Atomic Locks).
4. **jobs, job_batches, failed_jobs**: Bảng Queue lưu trữ hàng đợi công việc nền (Background Jobs) như gửi Email, thông báo chậm, dọn dẹp data cũ.

### MODULE: BOOKING (BỔ SUNG)



## Tên bảng: payment_logs

### 1. Mục đích bảng
Lịch sử webhook, thay đổi trạng thái của cổng thanh toán.

### 2. Danh sách trường

| STT | Tên trường | Kiểu dữ liệu | Null | Default | Khóa/Ràng buộc | Mô tả tác dụng trường | Ví dụ |
|---|---|---|---|---|---|---|---|
| 1 | id | char | Không | - | PK | (Cần cập nhật) | - |
| 2 | payment_id | char | Không | - | FK | (Cần cập nhật) | - |
| 3 | event_type | string | Không | - | - | (Cần cập nhật) | - |
| 4 | request_payload | json | Có | - | - | (Cần cập nhật) | - |
| 5 | response_payload | json | Có | - | - | (Cần cập nhật) | - |
| 6 | status_before | string | Có | - | - | (Cần cập nhật) | - |
| 7 | status_after | string | Có | - | - | (Cần cập nhật) | - |
| 8 | gateway_txn_id | string | Có | - | - | (Cần cập nhật) | - |
| 9 | error_code | string | Có | - | - | (Cần cập nhật) | - |
| 10 | error_message | text | Có | - | - | (Cần cập nhật) | - |
| 11 | created_at | timestamp | Có | - | - | (Cần cập nhật) | - |

### 3. Khóa chính, khóa ngoại, index
- PK: id
- FK: payment_id -> payments.id

### 4. Quan hệ với bảng khác
(Chưa có)

### 5. Ví dụ bản ghi
(Chưa có)

---
## Tên bảng: permissions

### 1. Mục đích bảng
Lưu trữ danh sách các quyền cụ thể, chi tiết dùng để check logic trong code.

### 2. Danh sách trường

| STT | Tên trường | Kiểu dữ liệu | Null | Default | Khóa/Ràng buộc | Mô tả tác dụng trường | Ví dụ |
|---|---|---|---|---|---|---|---|
| 1 | code | string | Không | - | - | Mã quyền duy nhất check trong code | booking.manage |
| 2 | name | string | Không | - | - | Tên quyền hiển thị | Quản lý đặt sân |
| 3 | group_name | string | Không | - | - | Nhóm quyền để UI gom nhóm | booking |
| 4 | created_at | timestamp | Có | - | - | (Cần cập nhật) | - |

### 3. Khóa chính, khóa ngoại, index
- PK: id

### 4. Quan hệ với bảng khác
- permissions n-n roles qua role_permissions
- permissions 1-n user_permission_revokes qua user_permission_revokes.permission_id

### 5. Ví dụ bản ghi
```json
{
  "id": 1,
  "code": "booking.manage",
  "name": "Quản lý đặt sân",
  "group_name": "booking"
}
```

---
## Tên bảng: platform_fee_tiers

### 1. Mục đích bảng
(Chưa có mô tả)

### 2. Danh sách trường

| STT | Tên trường | Kiểu dữ liệu | Null | Default | Khóa/Ràng buộc | Mô tả tác dụng trường | Ví dụ |
|---|---|---|---|---|---|---|---|
| 1 | name | string | Không | - | - | (Cần cập nhật) | - |
| 2 | min_courts | unsignedInteger | Không | - | - | (Cần cập nhật) | - |
| 3 | max_courts | unsignedInteger | Có | - | - | (Cần cập nhật) | - |
| 4 | is_active | boolean | Không | true | - | (Cần cập nhật) | - |
| 5 | effective_from | timestamp | Có | - | - | (Cần cập nhật) | - |
| 6 | created_at | timestamp | Có | - | - | (Cần cập nhật) | - |
| 7 | updated_at | timestamp | Có | - | - | (Cần cập nhật) | - |

### 3. Khóa chính, khóa ngoại, index
- PK: id

### 4. Quan hệ với bảng khác
(Chưa có)

### 5. Ví dụ bản ghi
```json
{}
```

---
## Tên bảng: player_posts

### 1. Mục đích bảng
Bài đăng "Tìm kèo" hoặc "Ghép đội", bắt buộc phải gắn với một `booking_id` đã đặt thành công để tránh đăng bài ảo.

### 2. Danh sách trường

| STT | Tên trường | Kiểu dữ liệu | Null | Default | Khóa/Ràng buộc | Mô tả tác dụng trường | Ví dụ |
|---|---|---|---|---|---|---|---|
| 1 | id | char | Không | - | PK | (Cần cập nhật) | - |
| 2 | booking_id | char | Không | - | FK | (Cần cập nhật) | - |
| 3 | author_id | char | Không | - | FK | (Cần cập nhật) | - |
| 4 | title | string | Không | - | - | (Cần cập nhật) | - |
| 5 | description | text | Có | - | - | (Cần cập nhật) | - |
| 6 | needed_players | unsignedSmallInteger | Không | 1 | - | (Cần cập nhật) | - |
| 7 | status_reason | text | Có | - | - | (Cần cập nhật) | - |
| 8 | created_at | timestamp | Có | - | - | (Cần cập nhật) | - |
| 9 | updated_at | timestamp | Có | - | - | (Cần cập nhật) | - |

### 3. Khóa chính, khóa ngoại, index
- PK: id
- FK: author_id -> users.id, booking_id -> bookings.id

### 4. Quan hệ với bảng khác
(Chưa có)

### 5. Ví dụ bản ghi
(Chưa có)

---
## Tên bảng: hashtags

### 1. Mục đích bảng
(Chưa có mô tả)

### 2. Danh sách trường

| STT | Tên trường | Kiểu dữ liệu | Null | Default | Khóa/Ràng buộc | Mô tả tác dụng trường | Ví dụ |
|---|---|---|---|---|---|---|---|
| 1 | name | string | Không | - | - | (Cần cập nhật) | - |
| 2 | slug | string | Không | - | - | (Cần cập nhật) | - |
| 3 | created_at | timestamp | Có | - | - | (Cần cập nhật) | - |
| 4 | updated_at | timestamp | Có | - | - | (Cần cập nhật) | - |

### 3. Khóa chính, khóa ngoại, index
- PK: id

### 4. Quan hệ với bảng khác
(Chưa có)

### 5. Ví dụ bản ghi
```json
{}
```

---
## Tên bảng: player_post_participants

### 1. Mục đích bảng
Lưu những người dùng gửi yêu cầu tham gia vào "Bài tìm kèo" và trạng thái duyệt của chủ kèo.

### 2. Danh sách trường

| STT | Tên trường | Kiểu dữ liệu | Null | Default | Khóa/Ràng buộc | Mô tả tác dụng trường | Ví dụ |
|---|---|---|---|---|---|---|---|
| 1 | post_id | char | Không | - | FK | (Cần cập nhật) | - |
| 2 | user_id | char | Không | - | FK | (Cần cập nhật) | - |
| 3 | message | text | Có | - | - | (Cần cập nhật) | - |
| 4 | responded_at | timestamp | Có | - | - | (Cần cập nhật) | - |
| 5 | created_at | timestamp | Có | - | - | (Cần cập nhật) | - |
| 6 | updated_at | timestamp | Có | - | - | (Cần cập nhật) | - |

### 3. Khóa chính, khóa ngoại, index
- PK: id
- FK: post_id -> player_posts.id, user_id -> users.id

### 4. Quan hệ với bảng khác
(Chưa có)

### 5. Ví dụ bản ghi
(Chưa có)

---
## Tên bảng: player_preferences

### 1. Mục đích bảng
(Chưa có mô tả)

### 2. Danh sách trường

| STT | Tên trường | Kiểu dữ liệu | Null | Default | Khóa/Ràng buộc | Mô tả tác dụng trường | Ví dụ |
|---|---|---|---|---|---|---|---|
| 1 | id | char | Không | - | PK | (Cần cập nhật) | - |
| 2 | user_id | char | Không | - | FK | (Cần cập nhật) | - |
| 3 | player_rating_count | unsignedInteger | Không | 0 | - | (Cần cập nhật) | - |
| 4 | created_at | timestamp | Có | - | - | (Cần cập nhật) | - |
| 5 | updated_at | timestamp | Có | - | - | (Cần cập nhật) | - |

### 3. Khóa chính, khóa ngoại, index
- PK: id
- FK: user_id -> users.id

### 4. Quan hệ với bảng khác
(Chưa có)

### 5. Ví dụ bản ghi
```json
{}
```

---
## Tên bảng: venue_platform_fee_ledgers

### 1. Mục đích bảng
(Chưa có mô tả)

### 2. Danh sách trường

| STT | Tên trường | Kiểu dữ liệu | Null | Default | Khóa/Ràng buộc | Mô tả tác dụng trường | Ví dụ |
|---|---|---|---|---|---|---|---|
| 1 | id | char | Không | - | PK | (Cần cập nhật) | - |
| 2 | venue_cluster_id | char | Không | - | FK | (Cần cập nhật) | - |
| 3 | tier_id | unsignedBigInteger | Có | - | FK | (Cần cập nhật) | - |
| 4 | court_count | unsignedInteger | Không | - | - | (Cần cập nhật) | - |
| 5 | period_start | date | Không | - | - | (Cần cập nhật) | - |
| 6 | period_end | date | Không | - | - | (Cần cập nhật) | - |
| 7 | paid_at | timestamp | Có | - | - | (Cần cập nhật) | - |
| 8 | created_at | timestamp | Có | - | - | (Cần cập nhật) | - |
| 9 | updated_at | timestamp | Có | - | - | (Cần cập nhật) | - |

### 3. Khóa chính, khóa ngoại, index
- PK: id
- FK: tier_id -> platform_fee_tiers.id, venue_cluster_id -> venue_clusters.id

### 4. Quan hệ với bảng khác
(Chưa có)

### 5. Ví dụ bản ghi
```json
{}
```

---
## Tên bảng: player_preferred_court_types

### 1. Mục đích bảng
(Chưa có mô tả)

### 2. Danh sách trường

| STT | Tên trường | Kiểu dữ liệu | Null | Default | Khóa/Ràng buộc | Mô tả tác dụng trường | Ví dụ |
|---|---|---|---|---|---|---|---|
| 1 | user_id | char | Không | - | FK | (Cần cập nhật) | - |
| 2 | court_type_id | unsignedBigInteger | Không | - | FK | (Cần cập nhật) | - |
| 3 | sort_order | integer | Không | 0 | - | (Cần cập nhật) | - |
| 4 | created_at | timestamp | Có | - | - | (Cần cập nhật) | - |
| 5 | updated_at | timestamp | Có | - | - | (Cần cập nhật) | - |

### 3. Khóa chính, khóa ngoại, index
- PK: id
- FK: court_type_id -> court_types.id, user_id -> users.id

### 4. Quan hệ với bảng khác
(Chưa có)

### 5. Ví dụ bản ghi
```json
{}
```

---
## Tên bảng: player_ratings

### 1. Mục đích bảng
Lưu đánh giá (Rating) giữa người chơi với nhau sau khi tham gia kèo thành công, giúp xây dựng uy tín cá nhân.

### 2. Danh sách trường

| STT | Tên trường | Kiểu dữ liệu | Null | Default | Khóa/Ràng buộc | Mô tả tác dụng trường | Ví dụ |
|---|---|---|---|---|---|---|---|
| 1 | id | char | Không | - | PK | (Cần cập nhật) | - |
| 2 | rater_id | char | Không | - | FK | (Cần cập nhật) | - |
| 3 | rated_user_id | char | Không | - | FK | (Cần cập nhật) | - |
| 4 | post_id | char | Có | - | FK | (Cần cập nhật) | - |
| 5 | rating | unsignedTinyInteger | Không | - | - | (Cần cập nhật) | - |
| 6 | comment | text | Có | - | - | (Cần cập nhật) | - |
| 7 | tags | json | Có | - | - | (Cần cập nhật) | - |
| 8 | created_at | timestamp | Có | - | - | (Cần cập nhật) | - |
| 9 | updated_at | timestamp | Có | - | - | (Cần cập nhật) | - |

### 3. Khóa chính, khóa ngoại, index
- PK: id
- FK: post_id -> player_posts.id, rated_user_id -> users.id, rater_id -> users.id

### 4. Quan hệ với bảng khác
(Chưa có)

### 5. Ví dụ bản ghi
(Chưa có)

---
## Tên bảng: post_hashtags

### 1. Mục đích bảng
(Chưa có mô tả)

### 2. Danh sách trường

| STT | Tên trường | Kiểu dữ liệu | Null | Default | Khóa/Ràng buộc | Mô tả tác dụng trường | Ví dụ |
|---|---|---|---|---|---|---|---|
| 1 | hashtag_id | unsignedBigInteger | Không | - | FK | (Cần cập nhật) | - |
| 2 | post_type | string | Không | - | - | (Cần cập nhật) | - |
| 3 | post_id | string | Không | - | - | (Cần cập nhật) | - |
| 4 | created_at | timestamp | Có | - | - | (Cần cập nhật) | - |

### 3. Khóa chính, khóa ngoại, index
- PK: id
- FK: hashtag_id -> hashtags.id

### 4. Quan hệ với bảng khác
(Chưa có)

### 5. Ví dụ bản ghi
```json
{}
```

---
## Tên bảng: price_slots

### 1. Mục đích bảng
Lưu trữ bảng giá sân theo các khung giờ khác nhau của một cụm sân và loại môn.

### 2. Danh sách trường

| STT | Tên trường | Kiểu dữ liệu | Null | Default | Khóa/Ràng buộc | Mô tả tác dụng trường | Ví dụ |
|---|---|---|---|---|---|---|---|
| 1 | id | char | Không | - | PK | ID bảng giá | 6000... |
| 2 | venue_cluster_id | char | Không | - | FK | ID cụm sân | 2000... |
| 3 | court_type_id | unsignedBigInteger | Không | - | FK | ID môn thể thao | 1 |
| 4 | start_time | time | Không | - | - | Giờ bắt đầu khung giá | 17:00:00 |
| 5 | end_time | time | Không | - | - | Giờ kết thúc khung giá | 22:00:00 |
| 6 | apply_to_days | json | Có | - | - | Ngày áp dụng (T2-CN) | [1, 2, 3, 4, 5] |
| 7 | is_active | boolean | Không | true | - | Còn áp dụng không | 1 |
| 8 | created_at | timestamp | Có | - | - | (Cần cập nhật) | - |
| 9 | updated_at | timestamp | Có | - | - | (Cần cập nhật) | - |

### 3. Khóa chính, khóa ngoại, index
- PK: id
- FK: court_type_id -> court_types.id, venue_cluster_id -> venue_clusters.id

### 4. Quan hệ với bảng khác
- Thuộc cụm sân và loại sân, dùng để tính tiền khi khách đặt booking.

---
## Tên bảng: refunds

### 1. Mục đích bảng
Quản lý yêu cầu hoàn tiền khi booking bị hủy sau khi đã thanh toán.

### 2. Danh sách trường

| STT | Tên trường | Kiểu dữ liệu | Null | Default | Khóa/Ràng buộc | Mô tả tác dụng trường | Ví dụ |
|---|---|---|---|---|---|---|---|
| 1 | id | char | Không | - | PK | (Cần cập nhật) | - |
| 2 | payment_id | char | Không | - | FK | (Cần cập nhật) | - |
| 3 | booking_id | char | Không | - | - | (Cần cập nhật) | - |
| 4 | reason | text | Có | - | - | (Cần cập nhật) | - |
| 5 | status_reason | text | Có | - | - | (Cần cập nhật) | - |
| 6 | processed_by | char | Có | - | FK | (Cần cập nhật) | - |
| 7 | processed_at | timestamp | Có | - | - | (Cần cập nhật) | - |
| 8 | created_at | timestamp | Có | - | - | (Cần cập nhật) | - |
| 9 | updated_at | timestamp | Có | - | - | (Cần cập nhật) | - |

### 3. Khóa chính, khóa ngoại, index
- PK: id
- FK: payment_id -> payments.id, processed_by -> users.id

### 4. Quan hệ với bảng khác
(Chưa có)

### 5. Ví dụ bản ghi
(Chưa có)

---
## Tên bảng: reports

### 1. Mục đích bảng
(Chưa có mô tả)

### 2. Danh sách trường

| STT | Tên trường | Kiểu dữ liệu | Null | Default | Khóa/Ràng buộc | Mô tả tác dụng trường | Ví dụ |
|---|---|---|---|---|---|---|---|
| 1 | id | char | Không | - | PK | (Cần cập nhật) | - |
| 2 | reporter_id | char | Không | - | FK | (Cần cập nhật) | - |
| 3 | reportable_type | string | Không | - | - | (Cần cập nhật) | - |
| 4 | reportable_id | string | Không | - | - | (Cần cập nhật) | - |
| 5 | description | text | Có | - | - | (Cần cập nhật) | - |
| 6 | action_note | text | Có | - | - | (Cần cập nhật) | - |
| 7 | reviewed_by | char | Có | - | FK | (Cần cập nhật) | - |
| 8 | reviewed_at | timestamp | Có | - | - | (Cần cập nhật) | - |
| 9 | created_at | timestamp | Có | - | - | (Cần cập nhật) | - |

### 3. Khóa chính, khóa ngoại, index
- PK: id
- FK: reporter_id -> users.id, reviewed_by -> users.id

### 4. Quan hệ với bảng khác
(Chưa có)

### 5. Ví dụ bản ghi
(Chưa có)

---
## Tên bảng: reviews

### 1. Mục đích bảng
Người dùng đánh giá chất lượng của cụm sân sau khi hoàn thành một Booking.

### 2. Danh sách trường

| STT | Tên trường | Kiểu dữ liệu | Null | Default | Khóa/Ràng buộc | Mô tả tác dụng trường | Ví dụ |
|---|---|---|---|---|---|---|---|
| 1 | id | char | Không | - | PK | (Cần cập nhật) | - |
| 2 | booking_id | char | Không | - | FK | (Cần cập nhật) | - |
| 3 | customer_id | char | Không | - | - | (Cần cập nhật) | - |
| 4 | venue_cluster_id | char | Không | - | - | (Cần cập nhật) | - |
| 5 | rating | unsignedTinyInteger | Không | - | - | (Cần cập nhật) | - |
| 6 | comment | text | Có | - | - | (Cần cập nhật) | - |
| 7 | reply_content | text | Có | - | - | (Cần cập nhật) | - |
| 8 | replied_at | timestamp | Có | - | - | (Cần cập nhật) | - |
| 9 | is_visible | boolean | Không | true | - | (Cần cập nhật) | - |
| 10 | created_at | timestamp | Có | - | - | (Cần cập nhật) | - |
| 11 | updated_at | timestamp | Có | - | - | (Cần cập nhật) | - |

### 3. Khóa chính, khóa ngoại, index
- PK: id
- FK: booking_id -> bookings.id

### 4. Quan hệ với bảng khác
(Chưa có)

### 5. Ví dụ bản ghi
(Chưa có)

---
## Tên bảng: role_permissions

### 1. Mục đích bảng
Bảng trung gian n-n kết nối roles và permissions, định nghĩa 1 role có những quyền chi tiết nào.

### 2. Danh sách trường

| STT | Tên trường | Kiểu dữ liệu | Null | Default | Khóa/Ràng buộc | Mô tả tác dụng trường | Ví dụ |
|---|---|---|---|---|---|---|---|
| 1 | role_id | unsignedBigInteger | Không | - | FK | ID của role | 1 |
| 2 | permission_id | unsignedBigInteger | Không | - | FK | ID của quyền | 10 |

### 3. Khóa chính, khóa ngoại, index
- PK: id
- FK: permission_id -> permissions.id, role_id -> roles.id

### 4. Quan hệ với bảng khác
- Cầu nối role và permission.

### 5. Ví dụ bản ghi
```json
{
  "role_id": 1,
  "permission_id": 10
}
```

---
## Tên bảng: slot_locks

### 1. Mục đích bảng
Quản lý việc khóa khung giờ (lock slot) do chủ sân tự block lịch hoặc block tạm thời khi user đang ở màn hình thanh toán.

### 2. Danh sách trường

| STT | Tên trường | Kiểu dữ liệu | Null | Default | Khóa/Ràng buộc | Mô tả tác dụng trường | Ví dụ |
|---|---|---|---|---|---|---|---|
| 1 | id | char | Không | - | PK | (Cần cập nhật) | - |
| 2 | venue_cluster_id | char | Không | - | - | (Cần cập nhật) | - |
| 3 | venue_court_id | char | Có | - | FK | (Cần cập nhật) | - |
| 4 | booking_date | date | Không | - | - | (Cần cập nhật) | - |
| 5 | start_time | time | Không | - | - | (Cần cập nhật) | - |
| 6 | end_time | time | Không | - | - | (Cần cập nhật) | - |
| 7 | locked_by | string | Không | - | - | (Cần cập nhật) | - |
| 8 | booking_id | char | Có | - | FK | (Cần cập nhật) | - |
| 9 | expires_at | timestamp | Không | - | - | (Cần cập nhật) | - |
| 10 | created_at | timestamp | Có | - | - | (Cần cập nhật) | - |

### 3. Khóa chính, khóa ngoại, index
- PK: id
- FK: booking_id -> bookings.id, venue_court_id -> venue_courts.id

### 4. Quan hệ với bảng khác
(Chưa có)

### 5. Ví dụ bản ghi
(Chưa có)

---

### MODULE: PAYMENT & WALLET



## Tên bảng: system_policies

### 1. Mục đích bảng
(Chưa có mô tả)

### 2. Danh sách trường

| STT | Tên trường | Kiểu dữ liệu | Null | Default | Khóa/Ràng buộc | Mô tả tác dụng trường | Ví dụ |
|---|---|---|---|---|---|---|---|
| 1 | id | char | Không | - | PK | (Cần cập nhật) | - |
| 2 | key | string | Không | - | - | (Cần cập nhật) | - |
| 3 | version | unsignedInteger | Không | 1 | - | (Cần cập nhật) | - |
| 4 | title | string | Không | - | - | (Cần cập nhật) | - |
| 5 | content | longText | Không | - | - | (Cần cập nhật) | - |
| 6 | is_active | boolean | Không | true | - | (Cần cập nhật) | - |
| 7 | effective_from | timestamp | Có | - | - | (Cần cập nhật) | - |
| 8 | created_by | char | Có | - | FK | (Cần cập nhật) | - |
| 9 | updated_by | char | Có | - | FK | (Cần cập nhật) | - |
| 10 | created_at | timestamp | Có | - | - | (Cần cập nhật) | - |
| 11 | updated_at | timestamp | Có | - | - | (Cần cập nhật) | - |

### 3. Khóa chính, khóa ngoại, index
- PK: id
- FK: created_by -> users.id, updated_by -> users.id

### 4. Quan hệ với bảng khác
(Chưa có)

### 5. Ví dụ bản ghi
```json
{}
```

---
## Tên bảng: system_posts

### 1. Mục đích bảng
(Chưa có mô tả)

### 2. Danh sách trường

| STT | Tên trường | Kiểu dữ liệu | Null | Default | Khóa/Ràng buộc | Mô tả tác dụng trường | Ví dụ |
|---|---|---|---|---|---|---|---|
| 1 | id | char | Không | - | PK | (Cần cập nhật) | - |
| 2 | author_id | char | Có | - | FK | (Cần cập nhật) | - |
| 3 | title | string | Không | - | - | (Cần cập nhật) | - |
| 4 | slug | string | Không | - | - | (Cần cập nhật) | - |
| 5 | content | longText | Không | - | - | (Cần cập nhật) | - |
| 6 | thumbnail_path | string | Có | - | - | (Cần cập nhật) | - |
| 7 | published_at | timestamp | Có | - | - | (Cần cập nhật) | - |
| 8 | view_count | unsignedBigInteger | Không | 0 | - | (Cần cập nhật) | - |
| 9 | created_at | timestamp | Có | - | - | (Cần cập nhật) | - |
| 10 | updated_at | timestamp | Có | - | - | (Cần cập nhật) | - |

### 3. Khóa chính, khóa ngoại, index
- PK: id
- FK: author_id -> users.id

### 4. Quan hệ với bảng khác
(Chưa có)

### 5. Ví dụ bản ghi
```json
{}
```

---
## Tên bảng: user_permission_revokes

### 1. Mục đích bảng
Bảng quản lý việc "rút" một quyền cụ thể của 1 user nhất định, kể cả khi role của họ có cấp quyền đó. Hỗ trợ scope (phạm vi).

### 2. Danh sách trường

| STT | Tên trường | Kiểu dữ liệu | Null | Default | Khóa/Ràng buộc | Mô tả tác dụng trường | Ví dụ |
|---|---|---|---|---|---|---|---|
| 1 | user_id | char | Không | - | FK | User bị thu hồi quyền | 10000000-... |
| 2 | permission_id | unsignedBigInteger | Không | - | FK | Quyền bị thu hồi | 5 |
| 3 | scope_type | string | Không | - | - | Phạm vi (system hoặc venue) | venue |
| 4 | scope_id | char | Không | - | - | ID phạm vi thu hồi | aabbccdd-... |
| 5 | revoked_by | char | Có | - | FK | Người thực hiện thu hồi | null |
| 6 | reason | string | Có | - | - | Lý do thu hồi quyền | Vi phạm nội quy |
| 7 | created_at | timestamp | Có | - | - | (Cần cập nhật) | - |

### 3. Khóa chính, khóa ngoại, index
- PK: id
- FK: permission_id -> permissions.id, revoked_by -> users.id, user_id -> users.id

### 4. Quan hệ với bảng khác
- Liên kết với users, permissions và logical reference tới cụm sân qua scope_id.

### 5. Ví dụ bản ghi
```json
{
  "user_id": "10000000-0000-0000-0000-000000000001",
  "permission_id": 5,
  "scope_type": "system",
  "scope_id": "00000000-0000-0000-0000-000000000000",
  "reason": "Tạm khóa quyền chat"
}
```

---
## Tên bảng: user_policy_acceptances

### 1. Mục đích bảng
(Chưa có mô tả)

### 2. Danh sách trường

| STT | Tên trường | Kiểu dữ liệu | Null | Default | Khóa/Ràng buộc | Mô tả tác dụng trường | Ví dụ |
|---|---|---|---|---|---|---|---|
| 1 | user_id | char | Không | - | FK | (Cần cập nhật) | - |
| 2 | system_policy_id | char | Không | - | FK | (Cần cập nhật) | - |
| 3 | policy_version | string | Không | - | - | (Cần cập nhật) | - |
| 4 | accepted_at | timestamp | Không | - | - | (Cần cập nhật) | - |

### 3. Khóa chính, khóa ngoại, index
- PK: id
- FK: system_policy_id -> system_policies.id, user_id -> users.id

### 4. Quan hệ với bảng khác
(Chưa có)

### 5. Ví dụ bản ghi
```json
{}
```

---
## Tên bảng: user_roles

### 1. Mục đích bảng
Bảng trung gian n-n kết nối users và roles. Đặc biệt hỗ trợ phân quyền theo phạm vi (scope) để 1 user có thể làm chủ sân A nhưng không có quyền ở sân B.

### 2. Danh sách trường

| STT | Tên trường | Kiểu dữ liệu | Null | Default | Khóa/Ràng buộc | Mô tả tác dụng trường | Ví dụ |
|---|---|---|---|---|---|---|---|
| 1 | user_id | char | Không | - | FK | ID người dùng | 10000000-... |
| 2 | role_id | unsignedBigInteger | Không | - | FK | ID role được gán | 2 |
| 3 | scope_id | char | Không | 00000000-0000-0000-0000-000000000000 | - | ID của cụm sân (nếu scope là venue) | aabbccdd-... |
| 4 | granted_by | char | Có | - | FK | Người gán quyền | null |
| 5 | created_at | timestamp | Có | - | - | (Cần cập nhật) | - |

### 3. Khóa chính, khóa ngoại, index
- PK: id
- FK: granted_by -> users.id, role_id -> roles.id, user_id -> users.id

### 4. Quan hệ với bảng khác
- Cầu nối user và role. Logical reference tới venue_clusters thông qua scope_id.

### 5. Ví dụ bản ghi
```json
{
  "user_id": "10000000-0000-0000-0000-000000000001",
  "role_id": 2,
  "scope_type": "venue",
  "scope_id": "90000000-0000-0000-0000-000000000009"
}
```

---
## Tên bảng: verification_codes

### 1. Mục đích bảng
Lưu mã xác thực OTP dùng cho việc đăng ký, xác nhận số điện thoại, và quên mật khẩu.

### 2. Danh sách trường

| STT | Tên trường | Kiểu dữ liệu | Null | Default | Khóa/Ràng buộc | Mô tả tác dụng trường | Ví dụ |
|---|---|---|---|---|---|---|---|
| 1 | user_id | char | Có | - | FK | (Cần cập nhật) | - |
| 2 | identifier | string | Không | - | - | (Cần cập nhật) | - |
| 3 | type | string | Không | - | - | (Cần cập nhật) | - |
| 4 | channel | string | Không | - | - | (Cần cập nhật) | - |
| 5 | code | string | Không | - | - | (Cần cập nhật) | - |
| 6 | attempt_count | smallInteger | Không | - | - | (Cần cập nhật) | - |
| 7 | max_attempts | smallInteger | Không | - | - | (Cần cập nhật) | - |
| 8 | is_used | boolean | Không | - | - | (Cần cập nhật) | - |
| 9 | expires_at | timestamp | Không | - | - | (Cần cập nhật) | - |
| 10 | created_at | timestamp | Có | - | - | (Cần cập nhật) | - |

### 3. Khóa chính, khóa ngoại, index
- PK: id
- FK: user_id -> users.id

### 4. Quan hệ với bảng khác
(Chưa có)

### 5. Ví dụ bản ghi
(Chưa có)

---
## Tên bảng: personal_access_tokens

### 1. Mục đích bảng
Bảng chuẩn của gói Laravel Sanctum dùng để lưu trữ và xác thực token API của users.

### 2. Danh sách trường

| STT | Tên trường | Kiểu dữ liệu | Null | Default | Khóa/Ràng buộc | Mô tả tác dụng trường | Ví dụ |
|---|---|---|---|---|---|---|---|
| 1 | tokenable | morphs | Không | - | - | (Cần cập nhật) | - |
| 2 | name | text | Không | - | - | Tên token | android-app |
| 3 | token | string | Không | - | - | Chuỗi token đã hash | abc...xyz |
| 4 | abilities | text | Có | - | - | Phạm vi token | ["*"] |
| 5 | last_used_at | timestamp | Có | - | - | Thời điểm dùng cuối | 2026-06-15 |
| 6 | expires_at | timestamp | Có | - | - | Hạn chót token | null |
| 7 | created_at | timestamp | Có | - | - | (Cần cập nhật) | - |
| 8 | updated_at | timestamp | Có | - | - | (Cần cập nhật) | - |

### 3. Khóa chính, khóa ngoại, index
- PK: id

### 4. Quan hệ với bảng khác
- Polymorphic (tokenable) liên kết tới users.

### 5. Ví dụ bản ghi
```json
{
  "tokenable_type": "App\\Models\\User",
  "tokenable_id": "10000000-0000-0000-0000-000000000001",
  "name": "web-login",
  "token": "hashed_string"
}
```

---
*(Sẽ tiếp tục nối thêm phần Venue và Booking ở phần tiếp theo)*

---

### MODULE: VENUE



## Tên bảng: system_bank_accounts

### 1. Mục đích bảng
Quản lý danh sách các tài khoản ngân hàng của hệ thống (dùng để tích hợp tạo mã QR thanh toán qua SePay).

### 2. Danh sách trường

| STT | Tên trường | Kiểu dữ liệu | Null | Default | Khóa/Ràng buộc | Mô tả tác dụng trường | Ví dụ |
|---|---|---|---|---|---|---|---|
| 1 | id | char | Không | - | PK | (Cần cập nhật) | - |
| 2 | name | string | Không | Tài khoản nhận tiền hệ thống | - | (Cần cập nhật) | - |
| 3 | bank_name | string | Có | - | - | (Cần cập nhật) | - |
| 4 | bank_code | string | Không | - | - | (Cần cập nhật) | - |
| 5 | account_number | string | Không | - | - | (Cần cập nhật) | - |
| 6 | account_holder_name | string | Không | - | - | (Cần cập nhật) | - |
| 7 | is_default | boolean | Không | false | - | (Cần cập nhật) | - |
| 8 | created_at | timestamp | Có | - | - | (Cần cập nhật) | - |
| 9 | updated_at | timestamp | Có | - | - | (Cần cập nhật) | - |

### 3. Khóa chính, khóa ngoại, index
- PK: id

### 4. Quan hệ với bảng khác
(Chưa có)

### 5. Ví dụ bản ghi
(Chưa có)

---
## Tên bảng: owner_wallets

### 1. Mục đích bảng
Quản lý ví tiền của mỗi chủ sân. Tiền khách thanh toán vào TK hệ thống sẽ được cộng vào ví này (đóng vai trò như số dư thu hộ).

### 2. Danh sách trường

| STT | Tên trường | Kiểu dữ liệu | Null | Default | Khóa/Ràng buộc | Mô tả tác dụng trường | Ví dụ |
|---|---|---|---|---|---|---|---|
| 1 | id | char | Không | - | PK | (Cần cập nhật) | - |
| 2 | owner_id | char | Không | - | FK | (Cần cập nhật) | - |
| 3 | created_at | timestamp | Có | - | - | (Cần cập nhật) | - |
| 4 | updated_at | timestamp | Có | - | - | (Cần cập nhật) | - |

### 3. Khóa chính, khóa ngoại, index
- PK: id
- FK: owner_id -> users.id

### 4. Quan hệ với bảng khác
(Chưa có)

### 5. Ví dụ bản ghi
(Chưa có)

---
## Tên bảng: owner_wallet_ledgers

### 1. Mục đích bảng
Sổ phụ ghi chú từng biến động của ví chủ sân (cộng tiền do booking, trừ tiền do rút).

### 2. Danh sách trường

| STT | Tên trường | Kiểu dữ liệu | Null | Default | Khóa/Ràng buộc | Mô tả tác dụng trường | Ví dụ |
|---|---|---|---|---|---|---|---|
| 1 | id | char | Không | - | PK | (Cần cập nhật) | - |
| 2 | owner_wallet_id | char | Không | - | FK | (Cần cập nhật) | - |
| 3 | owner_id | char | Không | - | FK | (Cần cập nhật) | - |
| 4 | venue_cluster_id | char | Có | - | FK | (Cần cập nhật) | - |
| 5 | booking_id | char | Có | - | FK | (Cần cập nhật) | - |
| 6 | payment_id | char | Có | - | FK | (Cần cập nhật) | - |
| 7 | reference_code | string | Có | - | - | (Cần cập nhật) | - |
| 8 | description | text | Có | - | - | (Cần cập nhật) | - |
| 9 | metadata | json | Có | - | - | (Cần cập nhật) | - |
| 10 | created_at | timestamp | Có | - | - | (Cần cập nhật) | - |
| 11 | updated_at | timestamp | Có | - | - | (Cần cập nhật) | - |

### 3. Khóa chính, khóa ngoại, index
- PK: id
- FK: owner_wallet_id -> owner_wallets.id, owner_id -> users.id, venue_cluster_id -> venue_clusters.id, booking_id -> bookings.id, payment_id -> payments.id

### 4. Quan hệ với bảng khác
(Chưa có)

### 5. Ví dụ bản ghi
(Chưa có)

---
## Tên bảng: booking_items

### 1. Mục đích bảng
Lưu trữ từng sân con và khung giờ cụ thể trong một booking, phục vụ luồng đặt nhiều sân/nhiều slot trong cùng một đơn.

### 2. Danh sách trường

| STT | Tên trường | Kiểu dữ liệu | Null | Default | Khóa/Ràng buộc | Mô tả tác dụng trường | Ví dụ |
|---|---|---|---|---|---|---|---|
| 1 | id | char | Không | - | PK | UUID item | 40000000-... |
| 2 | booking_id | char | Không | - | FK | Đơn đặt sân cha | 50000000-... |
| 3 | venue_court_id | char | Không | - | FK | Sân con thực tế được gán | 30000000-... |
| 4 | requested_venue_court_id | char | Có | - | FK | Sân con khách yêu cầu ban đầu | null |
| 5 | start_time | time | Không | - | - | Giờ bắt đầu | 18:00:00 |
| 6 | end_time | time | Không | - | - | Giờ kết thúc | 20:00:00 |
| 7 | duration_minutes | unsignedInteger | Không | - | - | Thời lượng phút | 120 |
| 8 | court_changed_by | char | Có | - | FK | Người đổi sân | null |
| 9 | court_changed_at | timestamp | Có | - | - | Thời điểm đổi sân | null |
| 10 | court_changed_reason | text | Có | - | - | Lý do đổi sân | null |
| 11 | sort_order | unsignedInteger | Không | 0 | - | Thứ tự hiển thị | 1 |
| 12 | created_at | timestamp | Có | - | - | (Cần cập nhật) | - |
| 13 | updated_at | timestamp | Có | - | - | (Cần cập nhật) | - |

### 3. Khóa chính, khóa ngoại, index
- PK: id
- FK: booking_id -> bookings.id, venue_court_id -> venue_courts.id, requested_venue_court_id -> venue_courts.id, court_changed_by -> users.id

### 4. Quan hệ với bảng khác
- Thuộc bookings, venue_courts.
- Liên kết tới slot_locks qua slot_locks.booking_item_id.

### 5. Ví dụ bản ghi
```json
{
  "id": "40000000-0000-0000-0000-000000000001",
  "booking_id": "50000000-0000-0000-0000-000000000001",
  "venue_court_id": "30000000-0000-0000-0000-000000000001",
  "start_time": "18:00:00",
  "end_time": "20:00:00",
  "subtotal": 200000.00
}
```

**Lưu ý**: Migration cũng bổ sung `booking_item_id` (char(36), nullable, FK -> booking_items.id set null) vào bảng `slot_locks`.

---

---


### MODULE: PAYMENT (BỔ SUNG)



## Tên bảng: owner_bank_accounts

### 1. Mục đích bảng
Lưu thông tin tài khoản ngân hàng của chủ sân dùng để nhận tiền rút và đối soát.

### 2. Danh sách trường

| STT | Tên trường | Kiểu dữ liệu | Null | Default | Khóa/Ràng buộc | Mô tả tác dụng trường | Ví dụ |
|---|---|---|---|---|---|---|---|
| 1 | id | char | Không | - | PK | ID tài khoản | 60000000-... |
| 2 | owner_id | char | Không | - | FK | Chủ sân sở hữu | 10000000-... |
| 3 | partner_application_id | char | Có | - | FK | Hồ sơ đăng ký đã cung cấp TK này | null |
| 4 | bank_name | string | Không | - | - | Tên ngân hàng | Vietcombank |
| 5 | bank_code | string | Không | - | - | Mã ngân hàng | VCB |
| 6 | account_number | string | Không | - | - | Số tài khoản | 1234567890 |
| 7 | account_holder_name | string | Không | - | - | Tên chủ TK | Nguyễn Văn A |
| 8 | branch_name | string | Có | - | - | Chi nhánh | Chi nhánh HN |
| 9 | is_default | boolean | Không | false | - | TK nhận tiền mặc định | true |
| 10 | verified_by | char | Có | - | FK | Admin xác minh | null |
| 11 | verified_at | timestamp | Có | - | - | Thời điểm xác minh | null |
| 12 | rejected_reason | text | Có | - | - | Lý do từ chối | null |
| 13 | created_at | timestamp | Có | - | - | (Cần cập nhật) | - |
| 14 | updated_at | timestamp | Có | - | - | (Cần cập nhật) | - |

### 3. Khóa chính, khóa ngoại, index
- PK: id
- FK: owner_id -> users.id, partner_application_id -> partner_applications.id, verified_by -> users.id

### 4. Quan hệ với bảng khác
(Chưa có)

### 5. Ví dụ bản ghi
(Chưa có)

---
## Tên bảng: owner_withdrawal_requests

### 1. Mục đích bảng
Quản lý yêu cầu rút tiền từ ví chủ sân.

### 2. Danh sách trường

| STT | Tên trường | Kiểu dữ liệu | Null | Default | Khóa/Ràng buộc | Mô tả tác dụng trường | Ví dụ |
|---|---|---|---|---|---|---|---|
| 1 | id | char | Không | - | PK | ID yêu cầu | 70000000-... |
| 2 | request_code | string | Không | - | - | Mã yêu cầu rút tiền | WDR-001 |
| 3 | owner_id | char | Không | - | FK | Chủ sân yêu cầu | 10000000-... |
| 4 | owner_wallet_id | char | Không | - | FK | Ví owner bị giữ tiền | 80000000-... |
| 5 | owner_bank_account_id | char | Không | - | FK | TK nhận tiền owner chọn | 60000000-... |
| 6 | owner_note | text | Có | - | - | Ghi chú owner | null |
| 7 | reviewed_by | char | Có | - | FK | Admin duyệt/từ chối | null |
| 8 | reviewed_at | timestamp | Có | - | - | Thời điểm duyệt | null |
| 9 | review_note | text | Có | - | - | Ghi chú nội bộ | null |
| 10 | status_reason | text | Có | - | - | Lý do từ chối/hủy | null |
| 11 | completed_by | char | Có | - | FK | Admin xác nhận đã chuyển tiền | null |
| 12 | completed_at | timestamp | Có | - | - | Thời điểm hoàn tất | null |
| 13 | transfer_reference | string | Có | - | - | Mã giao dịch chuyển khoản thực tế | null |
| 14 | metadata | json | Có | - | - | Dữ liệu phụ | null |
| 15 | requested_at | timestamp | Không | - | - | Thời điểm gửi yêu cầu | 2026-06-01 |
| 16 | created_at | timestamp | Có | - | - | (Cần cập nhật) | - |
| 17 | updated_at | timestamp | Có | - | - | (Cần cập nhật) | - |

### 3. Khóa chính, khóa ngoại, index
- PK: id
- FK: owner_id -> users.id, owner_wallet_id -> owner_wallets.id, owner_bank_account_id -> owner_bank_accounts.id, reviewed_by -> users.id, completed_by -> users.id

### 4. Quan hệ với bảng khác
(Chưa có)

### 5. Ví dụ bản ghi
(Chưa có)

---
## Tên bảng: internal_receipts

### 1. Mục đích bảng
Lưu phiếu thu/chi nội bộ cho các nghiệp vụ tài chính (phí nền tảng, rút tiền, hoàn tiền, thanh toán).

### 2. Danh sách trường

| STT | Tên trường | Kiểu dữ liệu | Null | Default | Khóa/Ràng buộc | Mô tả tác dụng trường | Ví dụ |
|---|---|---|---|---|---|---|---|
| 1 | id | char | Không | - | PK | ID phiếu | 80000000-... |
| 2 | receipt_code | string | Không | - | - | Mã phiếu nội bộ | REC-001 |
| 3 | receiptable_type | string | Không | - | - | Loại đối tượng phát sinh phiếu | App\Models\OwnerWithdrawalRequest |
| 4 | receiptable_id | string | Không | - | - | ID đối tượng | 70000000-... |
| 5 | issued_to_user_id | char | Có | - | FK | User nhận phiếu | null |
| 6 | issued_by | char | Có | - | FK | Admin tạo phiếu | null |
| 7 | title | string | Không | - | - | Tiêu đề phiếu | Phiếu chi rút tiền |
| 8 | currency | string | Không | VND | - | Đơn vị tiền tệ | VND |
| 9 | issued_at | timestamp | Có | - | - | Thời điểm phát hành | 2026-06-01 |
| 10 | cancelled_at | timestamp | Có | - | - | Thời điểm hủy | null |
| 11 | cancel_reason | text | Có | - | - | Lý do hủy | null |
| 12 | file_path | string | Có | - | - | Đường dẫn file PDF/HTML | null |
| 13 | metadata | json | Có | - | - | Dữ liệu phụ render phiếu | null |
| 14 | created_at | timestamp | Có | - | - | (Cần cập nhật) | - |
| 15 | updated_at | timestamp | Có | - | - | (Cần cập nhật) | - |

### 3. Khóa chính, khóa ngoại, index
- PK: id
- FK: issued_to_user_id -> users.id, issued_by -> users.id

### 4. Quan hệ với bảng khác
(Chưa có)

### 5. Ví dụ bản ghi
(Chưa có)

---


### MODULE: POLICY



## Tên bảng: policy_action_bindings

### 1. Mục đích bảng
Map chính sách hệ thống với module/action nghiệp vụ (VD: `booking.cancel`, `refund.request`).

### 2. Danh sách trường

| STT | Tên trường | Kiểu dữ liệu | Null | Default | Khóa/Ràng buộc | Mô tả tác dụng trường | Ví dụ |
|---|---|---|---|---|---|---|---|
| 1 | id | char | Không | - | PK | UUID | ... |
| 2 | system_policy_id | char | Không | - | - | Chính sách được bind | ... |
| 3 | module | string | Không | - | - | Module nghiệp vụ | booking |
| 4 | action_code | string | Không | - | - | Mã action | booking.cancel |
| 5 | description | text | Có | - | - | Mô tả binding | null |
| 6 | is_active | boolean | Không | true | - | Binding có hiệu lực | 1 |
| 7 | created_at | timestamp | Có | - | - | (Cần cập nhật) | - |
| 8 | updated_at | timestamp | Có | - | - | (Cần cập nhật) | - |

### 3. Khóa chính, khóa ngoại, index
- PK: id

### 4. Quan hệ với bảng khác
(Chưa có)

### 5. Ví dụ bản ghi
(Chưa có)

---
## Tên bảng: policy_rules

### 1. Mục đích bảng
Lưu rule hệ thống có cấu trúc JSON để backend evaluate theo từng action.

### 2. Danh sách trường

| STT | Tên trường | Kiểu dữ liệu | Null | Default | Khóa/Ràng buộc | Mô tả tác dụng trường | Ví dụ |
|---|---|---|---|---|---|---|---|
| 1 | id | char | Không | - | PK | UUID | ... |
| 2 | system_policy_id | char | Không | - | - | Chính sách sở hữu rule | ... |
| 3 | action_code | string | Không | - | - | Action mà rule áp dụng | booking.cancel |
| 4 | rule_code | string | Không | - | - | Mã rule duy nhất trong policy | cancel_before_24h |
| 5 | rule_name | string | Không | - | - | Tên rule dễ đọc | Hủy trước 24 giờ |
| 6 | rule_type | string | Không | - | - | Loại evaluator | threshold |
| 7 | condition_json | json | Có | - | - | Điều kiện evaluate | {"min_hours_before": 24} |
| 8 | result_json | json | Có | - | - | Kết quả khi match | {"refund_percent": 100} |
| 9 | priority | integer | Không | 0 | - | Độ ưu tiên | 10 |
| 10 | is_active | boolean | Không | true | - | Rule có hiệu lực | 1 |
| 11 | created_at | timestamp | Có | - | - | (Cần cập nhật) | - |
| 12 | updated_at | timestamp | Có | - | - | (Cần cập nhật) | - |

### 3. Khóa chính, khóa ngoại, index
- PK: id

### 4. Quan hệ với bảng khác
(Chưa có)

### 5. Ví dụ bản ghi
(Chưa có)

---
## Tên bảng: venue_policy_rules

### 1. Mục đích bảng
Lưu rule riêng của sân, chỉ dùng khi chính sách hệ thống cho phép override (`is_overridable = true`).

### 2. Danh sách trường

| STT | Tên trường | Kiểu dữ liệu | Null | Default | Khóa/Ràng buộc | Mô tả tác dụng trường | Ví dụ |
|---|---|---|---|---|---|---|---|
| 1 | id | char | Không | - | PK | UUID | ... |
| 2 | venue_cluster_id | char | Không | - | - | Cụm sân cấu hình rule | 20000000-... |
| 3 | base_policy_rule_id | char | Có | - | - | Rule hệ thống được override | null |
| 4 | action_code | string | Không | - | - | Action áp dụng | booking.cancel |
| 5 | rule_code | string | Không | - | - | Mã rule sân | cancel_custom |
| 6 | rule_name | string | Không | - | - | Tên rule sân | Hủy trước 12 giờ |
| 7 | rule_type | string | Không | - | - | Loại evaluator | threshold |
| 8 | condition_json | json | Có | - | - | Điều kiện do owner cấu hình | {"min_hours_before": 12} |
| 9 | result_json | json | Có | - | - | Kết quả khi match | {"refund_percent": 80} |
| 10 | approved_by | char | Có | - | - | Admin duyệt | null |
| 11 | approved_at | timestamp | Có | - | - | Thời điểm duyệt | null |
| 12 | rejected_reason | text | Có | - | - | Lý do từ chối | null |
| 13 | created_by | char | Có | - | - | Owner/nhân viên tạo | null |
| 14 | created_at | timestamp | Có | - | - | (Cần cập nhật) | - |
| 15 | updated_at | timestamp | Có | - | - | (Cần cập nhật) | - |

### 3. Khóa chính, khóa ngoại, index
- PK: id

### 4. Quan hệ với bảng khác
(Chưa có)

### 5. Ví dụ bản ghi
(Chưa có)

---
## Tên bảng: policy_evaluation_logs

### 1. Mục đích bảng
Ghi nhận mỗi lần hệ thống evaluate rule, lưu input, output, actor, entity liên quan.

### 2. Danh sách trường

| STT | Tên trường | Kiểu dữ liệu | Null | Default | Khóa/Ràng buộc | Mô tả tác dụng trường | Ví dụ |
|---|---|---|---|---|---|---|---|
| 1 | id | char | Không | - | PK | UUID | ... |
| 2 | system_policy_id | char | Có | - | - | Chính sách đã evaluate | ... |
| 3 | policy_rule_id | char | Có | - | - | Rule hệ thống đã evaluate | ... |
| 4 | venue_policy_rule_id | char | Có | - | - | Rule sân đã evaluate | null |
| 5 | action_code | string | Không | - | - | Action được evaluate | booking.cancel |
| 6 | entity_type | string | Không | - | - | Loại đối tượng | booking |
| 7 | entity_id | string | Không | - | - | ID đối tượng | 50000000-... |
| 8 | input_data | json | Có | - | - | Dữ liệu đầu vào | {"hours_before": 30} |
| 9 | result_data | json | Có | - | - | Kết quả evaluate | {"allow": true, "refund_percent": 100} |
| 10 | evaluated_by_id | char | Có | - | - | User kích hoạt | null |
| 11 | created_at | timestamp | Có | - | - | Thời điểm evaluate | 2026-06-01 |

### 3. Khóa chính, khóa ngoại, index
- PK: id

### 4. Quan hệ với bảng khác
(Chưa có)

### 5. Ví dụ bản ghi
(Chưa có)

---


### MODULE: AI



## Tên bảng: ai_conversations

### 1. Mục đích bảng
Lưu cuộc trò chuyện AI của user.

### 2. Danh sách trường

| STT | Tên trường | Kiểu dữ liệu | Null | Default | Khóa/Ràng buộc | Mô tả tác dụng trường | Ví dụ |
|---|---|---|---|---|---|---|---|
| 1 | id | char | Không | - | PK | UUID | ... |
| 2 | user_id | char | Không | - | - | User sở hữu | 10000000-... |
| 3 | title | string | Có | - | - | Tiêu đề | Hỏi về đặt sân |
| 4 | deleted_at | timestamp | Có | - | - | Soft delete | null |
| 5 | created_at | timestamp | Có | - | - | (Cần cập nhật) | - |
| 6 | updated_at | timestamp | Có | - | - | (Cần cập nhật) | - |

### 3. Khóa chính, khóa ngoại, index
- PK: id

### 4. Quan hệ với bảng khác
(Chưa có)

### 5. Ví dụ bản ghi
(Chưa có)

---
## Tên bảng: ai_messages

### 1. Mục đích bảng
Lưu message user/assistant/system trong cuộc trò chuyện AI.

### 2. Danh sách trường

| STT | Tên trường | Kiểu dữ liệu | Null | Default | Khóa/Ràng buộc | Mô tả tác dụng trường | Ví dụ |
|---|---|---|---|---|---|---|---|
| 1 | id | char | Không | - | PK | UUID | ... |
| 2 | ai_conversation_id | char | Không | - | - | Cuộc trò chuyện chứa message | ... |
| 3 | content | longText | Không | - | - | Nội dung message | Sân nào gần nhất? |
| 4 | metadata | json | Có | - | - | Dữ liệu phụ (token, model) | null |
| 5 | deleted_at | timestamp | Có | - | - | Soft delete | null |
| 6 | created_at | timestamp | Có | - | - | (Cần cập nhật) | - |
| 7 | updated_at | timestamp | Có | - | - | (Cần cập nhật) | - |

### 3. Khóa chính, khóa ngoại, index
- PK: id

### 4. Quan hệ với bảng khác
(Chưa có)

### 5. Ví dụ bản ghi
(Chưa có)

---
## Tên bảng: ai_feedbacks

### 1. Mục đích bảng
Lưu feedback của user cho message AI (đánh giá chất lượng câu trả lời).

### 2. Danh sách trường

| STT | Tên trường | Kiểu dữ liệu | Null | Default | Khóa/Ràng buộc | Mô tả tác dụng trường | Ví dụ |
|---|---|---|---|---|---|---|---|
| 1 | id | char | Không | - | PK | UUID | ... |
| 2 | ai_message_id | char | Không | - | - | Message được đánh giá | ... |
| 3 | user_id | char | Không | - | - | User gửi feedback | 10000000-... |
| 4 | rating | tinyInteger | Có | - | - | Điểm đánh giá (1-5) | 4 |
| 5 | comment | text | Có | - | - | Góp ý | Trả lời chính xác |
| 6 | created_at | timestamp | Có | - | - | (Cần cập nhật) | - |
| 7 | updated_at | timestamp | Có | - | - | (Cần cập nhật) | - |

### 3. Khóa chính, khóa ngoại, index
- PK: id

### 4. Quan hệ với bảng khác
(Chưa có)

### 5. Ví dụ bản ghi
(Chưa có)

---


### MODULE: USER WALLET



## Tên bảng: user_wallets

### 1. Mục đích bảng
Quản lý ví nội bộ của user, dùng để thanh toán booking hoặc nhận tiền hoàn.

### 2. Danh sách trường

| STT | Tên trường | Kiểu dữ liệu | Null | Default | Khóa/Ràng buộc | Mô tả tác dụng trường | Ví dụ |
|---|---|---|---|---|---|---|---|
| 1 | id | char | Không | - | PK | UUID | ... |
| 2 | user_id | char | Không | - | - | User sở hữu ví (1 user 1 ví) | 10000000-... |
| 3 | created_at | timestamp | Có | - | - | (Cần cập nhật) | - |
| 4 | updated_at | timestamp | Có | - | - | (Cần cập nhật) | - |

### 3. Khóa chính, khóa ngoại, index
- PK: id

### 4. Quan hệ với bảng khác
(Chưa có)

### 5. Ví dụ bản ghi
(Chưa có)

---
## Tên bảng: user_wallet_ledgers

### 1. Mục đích bảng
Ghi nhận biến động số dư ví user (nguyên tắc kế toán kép: balance_before, balance_after).

### 2. Danh sách trường

| STT | Tên trường | Kiểu dữ liệu | Null | Default | Khóa/Ràng buộc | Mô tả tác dụng trường | Ví dụ |
|---|---|---|---|---|---|---|---|
| 1 | id | char | Không | - | PK | UUID | ... |
| 2 | user_wallet_id | char | Không | - | - | Ví được ghi biến động | ... |
| 3 | transaction_code | string | Không | - | - | Mã giao dịch nội bộ | UWLT-001 |
| 4 | reference_type | string | Có | - | - | Loại tham chiếu (booking, payment, refund) | payment |
| 5 | reference_id | string | Có | - | - | ID tham chiếu | 70000000-... |
| 6 | note | text | Có | - | - | Ghi chú | Thanh toán booking |
| 7 | created_by | char | Có | - | - | User/admin tạo | null |
| 8 | created_at | timestamp | Có | - | - | (Cần cập nhật) | - |
| 9 | updated_at | timestamp | Có | - | - | (Cần cập nhật) | - |

### 3. Khóa chính, khóa ngoại, index
- PK: id

### 4. Quan hệ với bảng khác
(Chưa có)

### 5. Ví dụ bản ghi
(Chưa có)

---
## Tên bảng: user_payout_accounts

### 1. Mục đích bảng
TKNH user dùng nhận tiền khi rút ví hoặc refund.

### 2. Danh sách trường

| STT | Tên trường | Kiểu dữ liệu | Null | Default | Khóa/Ràng buộc | Mô tả tác dụng trường | Ví dụ |
|---|---|---|---|---|---|---|---|
| 1 | id | char | Không | - | PK | UUID | ... |
| 2 | user_id | char | Không | - | - | User sở hữu | 10000000-... |
| 3 | bank_name | string | Không | - | - | Tên ngân hàng | Techcombank |
| 4 | bank_account_number | string | Không | - | - | Số tài khoản | 9876543210 |
| 5 | bank_account_holder | string | Không | - | - | Tên chủ TK | Trần Thị B |
| 6 | bank_branch | string | Có | - | - | Chi nhánh | null |
| 7 | is_default | boolean | Không | false | - | TK mặc định | true |
| 8 | created_at | timestamp | Có | - | - | (Cần cập nhật) | - |
| 9 | updated_at | timestamp | Có | - | - | (Cần cập nhật) | - |

### 3. Khóa chính, khóa ngoại, index
- PK: id

### 4. Quan hệ với bảng khác
(Chưa có)

### 5. Ví dụ bản ghi
(Chưa có)

---
## Tên bảng: user_withdrawal_requests

### 1. Mục đích bảng
Quản lý yêu cầu rút tiền từ ví user.

### 2. Danh sách trường

| STT | Tên trường | Kiểu dữ liệu | Null | Default | Khóa/Ràng buộc | Mô tả tác dụng trường | Ví dụ |
|---|---|---|---|---|---|---|---|
| 1 | id | char | Không | - | PK | UUID | ... |
| 2 | user_wallet_id | char | Không | - | - | Ví user bị giữ/trừ tiền | ... |
| 3 | user_id | char | Không | - | - | User yêu cầu rút tiền | 10000000-... |
| 4 | payout_account_id | char | Không | - | - | TK nhận tiền user chọn | ... |
| 5 | rejected_reason | text | Có | - | - | Lý do từ chối | null |
| 6 | approved_by | char | Có | - | - | Admin duyệt | null |
| 7 | paid_by | char | Có | - | - | Admin xác nhận chi trả | null |
| 8 | requested_at | timestamp | Không | - | - | Thời điểm gửi yêu cầu | 2026-06-01 |
| 9 | approved_at | timestamp | Có | - | - | Thời điểm duyệt | null |
| 10 | paid_at | timestamp | Có | - | - | Thời điểm chi trả | null |
| 11 | created_at | timestamp | Có | - | - | (Cần cập nhật) | - |
| 12 | updated_at | timestamp | Có | - | - | (Cần cập nhật) | - |

### 3. Khóa chính, khóa ngoại, index
- PK: id

### 4. Quan hệ với bảng khác
(Chưa có)

### 5. Ví dụ bản ghi
(Chưa có)

---


### MODULE: VOUCHER



## Tên bảng: vouchers

### 1. Mục đích bảng
Lưu voucher hệ thống và voucher sân.

### 2. Danh sách trường

| STT | Tên trường | Kiểu dữ liệu | Null | Default | Khóa/Ràng buộc | Mô tả tác dụng trường | Ví dụ |
|---|---|---|---|---|---|---|---|
| 1 | id | char | Không | - | PK | UUID | ... |
| 2 | code | string | Không | - | - | Mã voucher user nhập | SPORTGO10 |
| 3 | name | string | Không | - | - | Tên hiển thị | Giảm 10% lần đầu |
| 4 | description | text | Có | - | - | Mô tả | null |
| 5 | owner_id | char | Có | - | - | ID owner/cụm sân (nếu venue) | null |
| 6 | total_quantity | unsignedInteger | Có | - | - | Tổng số lượt phát hành | 1000 |
| 7 | used_quantity | unsignedInteger | Không | 0 | - | Số lượt đã dùng | 50 |
| 8 | per_user_limit | unsignedInteger | Có | - | - | Số lượt tối đa mỗi user | 1 |
| 9 | valid_from | dateTime | Có | - | - | Bắt đầu hiệu lực | 2026-06-01 |
| 10 | valid_to | dateTime | Có | - | - | Hết hiệu lực | 2026-12-31 |
| 11 | created_by | char | Có | - | - | Admin/owner tạo | null |
| 12 | created_at | timestamp | Có | - | - | (Cần cập nhật) | - |
| 13 | updated_at | timestamp | Có | - | - | (Cần cập nhật) | - |

### 3. Khóa chính, khóa ngoại, index
- PK: id

### 4. Quan hệ với bảng khác
(Chưa có)

### 5. Ví dụ bản ghi
(Chưa có)

---
## Tên bảng: voucher_scopes

### 1. Mục đích bảng
Giới hạn phạm vi áp dụng voucher (cụm sân, loại sân, loại booking).

### 2. Danh sách trường

| STT | Tên trường | Kiểu dữ liệu | Null | Default | Khóa/Ràng buộc | Mô tả tác dụng trường | Ví dụ |
|---|---|---|---|---|---|---|---|
| 1 | id | char | Không | - | PK | UUID | ... |
| 2 | voucher_id | char | Không | - | - | Voucher được giới hạn | ... |
| 3 | scope_id | string | Có | - | - | ID phạm vi (nullable khi all) | null |
| 4 | scope_key | string | Không | __all__ | - | Khóa ổn định unique | __all__ |
| 5 | created_at | timestamp | Có | - | - | (Cần cập nhật) | - |
| 6 | updated_at | timestamp | Có | - | - | (Cần cập nhật) | - |

### 3. Khóa chính, khóa ngoại, index
- PK: id

### 4. Quan hệ với bảng khác
(Chưa có)

### 5. Ví dụ bản ghi
(Chưa có)

---
## Tên bảng: voucher_usages

### 1. Mục đích bảng
Ghi nhận voucher đã áp dụng cho booking/payment nào.

### 2. Danh sách trường

| STT | Tên trường | Kiểu dữ liệu | Null | Default | Khóa/Ràng buộc | Mô tả tác dụng trường | Ví dụ |
|---|---|---|---|---|---|---|---|
| 1 | id | char | Không | - | PK | UUID | ... |
| 2 | voucher_id | char | Không | - | - | Voucher đã dùng | ... |
| 3 | user_id | char | Không | - | - | User dùng | 10000000-... |
| 4 | booking_id | char | Không | - | - | Booking áp dụng | 50000000-... |
| 5 | payment_id | char | Có | - | - | Payment liên quan | null |
| 6 | used_at | timestamp | Có | - | - | Thời điểm áp dụng | 2026-06-01 |
| 7 | created_at | timestamp | Có | - | - | (Cần cập nhật) | - |
| 8 | updated_at | timestamp | Có | - | - | (Cần cập nhật) | - |

### 3. Khóa chính, khóa ngoại, index
- PK: id

### 4. Quan hệ với bảng khác
(Chưa có)

### 5. Ví dụ bản ghi
(Chưa có)

---


### MODULE: SYSTEM (BỔ SUNG)



## Tên bảng: backup_jobs

### 1. Mục đích bảng
Lưu metadata và trạng thái các lần backup database.

### 2. Danh sách trường

| STT | Tên trường | Kiểu dữ liệu | Null | Default | Khóa/Ràng buộc | Mô tả tác dụng trường | Ví dụ |
|---|---|---|---|---|---|---|---|
| 1 | id | char | Không | - | PK | UUID | ... |
| 2 | backup_code | string | Không | - | - | Mã backup tra cứu | BKP-001 |
| 3 | file_name | string | Có | - | - | Tên file backup | sportgo_20260601.sql.gz |
| 4 | file_path | string | Có | - | - | Đường dẫn file | /backups/sportgo_20260601.sql.gz |
| 5 | disk | string | Có | - | - | Storage disk | local |
| 6 | size_bytes | unsignedBigInteger | Có | - | - | Dung lượng file | 104857600 |
| 7 | checksum | string | Có | - | - | Checksum kiểm tra | sha256:abc... |
| 8 | created_by | char | Có | - | - | Admin tạo backup | null |
| 9 | started_at | timestamp | Có | - | - | Thời điểm bắt đầu | 2026-06-01 |
| 10 | completed_at | timestamp | Có | - | - | Thời điểm hoàn tất | 2026-06-01 |
| 11 | error_message | text | Có | - | - | Lỗi nếu thất bại | null |
| 12 | retention_days | unsignedInteger | Có | - | - | Số ngày giữ file | 30 |
| 13 | created_at | timestamp | Có | - | - | (Cần cập nhật) | - |
| 14 | updated_at | timestamp | Có | - | - | (Cần cập nhật) | - |

### 3. Khóa chính, khóa ngoại, index
- PK: id

### 4. Quan hệ với bảng khác
(Chưa có)

### 5. Ví dụ bản ghi
(Chưa có)

---


### MODULE: POLICY BỔ SUNG (08/06/2026)



## Tên bảng: amenities

### 1. Mục đích bảng
(Chưa có mô tả)

### 2. Danh sách trường

| STT | Tên trường | Kiểu dữ liệu | Null | Default | Khóa/Ràng buộc | Mô tả tác dụng trường | Ví dụ |
|---|---|---|---|---|---|---|---|
| 1 | name | string | Không | - | - | (Cần cập nhật) | - |
| 2 | description | text | Có | - | - | (Cần cập nhật) | - |
| 3 | created_by | char | Có | - | FK | (Cần cập nhật) | - |
| 4 | reviewed_by | char | Có | - | FK | (Cần cập nhật) | - |
| 5 | reviewed_at | timestamp | Có | - | - | (Cần cập nhật) | - |
| 6 | status_reason | text | Có | - | - | (Cần cập nhật) | - |
| 7 | active_name | string | Có | - | - | (Cần cập nhật) | - |
| 8 | created_at | timestamp | Có | - | - | (Cần cập nhật) | - |
| 9 | updated_at | timestamp | Có | - | - | (Cần cập nhật) | - |
| 10 | deleted_at | timestamp | Có | - | - | (Cần cập nhật) | - |

### 3. Khóa chính, khóa ngoại, index
- PK: id
- FK: created_by -> users.id, reviewed_by -> users.id

### 4. Quan hệ với bảng khác
(Chưa có)

### 5. Ví dụ bản ghi
```json
{}
```

---
## Tên bảng: policy_rule_templates

### 1. Mục đích bảng
Lưu danh mục mẫu rule để admin cấu hình nhanh khi tạo policy rule mới. Template chứa sẵn action_code, schema JSON cho condition/result, mức rủi ro và khả năng override bởi sân.

### 2. Danh sách trường

| STT | Tên trường | Kiểu dữ liệu | Null | Default | Khóa/Ràng buộc | Mô tả tác dụng trường | Ví dụ |
|---|---|---|---|---|---|---|---|
| 1 | policy_type | string | Không | - | - | Loại chính sách: terms, booking_cancellation, refund, platform_fee, venue_policy... | refund |
| 2 | rule_code | string | Không | - | - | Mã rule duy nhất trong cùng loại chính sách | refund_percent_by_cancel_time |
| 3 | rule_name | string | Không | - | - | Tên hiển thị của rule | Hoàn tiền theo thời điểm hủy |
| 4 | description | text | Có | - | - | Mô tả chi tiết template rule | Template rule dùng để admin cấu hình... |
| 5 | action_code | string | Không | - | - | Mã action nghiệp vụ mà rule áp dụng | refund.request |
| 6 | condition_schema | json | Có | - | - | JSON Schema cho điều kiện rule | {"type": "object"} |
| 7 | result_schema | json | Có | - | - | JSON Schema cho kết quả rule | {"type": "object"} |
| 8 | is_venue_overridable | boolean | Không | false | - | Sân có được override rule này không | true |
| 9 | is_active | boolean | Không | true | - | Còn hiệu lực không | true |
| 10 | created_at | timestamp | Có | - | - | (Cần cập nhật) | - |
| 11 | updated_at | timestamp | Có | - | - | (Cần cập nhật) | - |

### 3. Khóa chính, khóa ngoại, index
- PK: id

### 4. Quan hệ với bảng khác
- Logical reference tới system_policies qua policy_type (cùng loại chính sách)
- Logical reference tới policy_rules qua rule_code (template cho rule thật)

### 5. Ví dụ bản ghi
```json
{
  "id": 3,
  "policy_type": "refund",
  "rule_code": "refund_percent_by_cancel_time",
  "rule_name": "Hoàn tiền theo thời điểm hủy",
  "action_code": "refund.request",
  "is_venue_overridable": true,
  "risk_level": "high"
}
```

---

---
## Tên bảng: policy_override_constraints

### 1. Mục đích bảng
Định nghĩa ràng buộc mà chủ sân phải tuân theo khi override chính sách hệ thống. Ví dụ: mức hoàn tiền tối thiểu 80%, chủ sân không được giảm dưới mức này.

### 2. Danh sách trường

| STT | Tên trường | Kiểu dữ liệu | Null | Default | Khóa/Ràng buộc | Mô tả tác dụng trường | Ví dụ |
|---|---|---|---|---|---|---|---|
| 1 | system_policy_id | char | Không | - | - | ID chính sách hệ thống | UUID-refund-policy |
| 2 | policy_rule_id | char | Có | - | - | ID rule cụ thể bị ràng buộc (nullable nếu áp dụng toàn bộ policy) | UUID-rule |
| 3 | rule_code | string | Không | - | - | Mã rule để lookup nhanh | refund_percent_by_cancel_time |
| 4 | constraint_key | string | Không | - | - | Mã ràng buộc duy nhất trong policy | refund_percent_minimum |
| 5 | constraint_name | string | Không | - | - | Tên hiển thị ràng buộc | Mức hoàn tiền tối thiểu |
| 6 | allowed_values | json | Có | - | - | Danh sách giá trị cho phép (cho exact_only) | [true] |
| 7 | message_vi | text | Không | - | - | Thông báo lỗi tiếng Việt khi vi phạm | Chính sách sân không được hoàn thấp hơn... |
| 8 | is_active | boolean | Không | true | - | Còn hiệu lực không | true |
| 9 | created_at | timestamp | Có | - | - | (Cần cập nhật) | - |
| 10 | updated_at | timestamp | Có | - | - | (Cần cập nhật) | - |

### 3. Khóa chính, khóa ngoại, index
- PK: id

### 4. Quan hệ với bảng khác
- Thuộc system_policies (1 policy có nhiều constraints)
- Tham chiếu policy_rules (nullable, nếu constraint cho 1 rule cụ thể)

### 5. Ví dụ bản ghi
```json
{
  "system_policy_id": "UUID-refund-policy",
  "rule_code": "refund_percent_by_cancel_time",
  "constraint_key": "refund_percent_minimum",
  "constraint_name": "Mức hoàn tiền tối thiểu",
  "comparison_direction": "venue_can_be_more_favorable_to_customer",
  "min_value": 80.00,
  "max_value": 100.00
}
```

---

---
## Tên bảng: policy_status_histories

### 1. Mục đích bảng
Ghi nhận lịch sử mỗi lần chính sách hệ thống đổi trạng thái (draft → active, active → inactive, thay đổi version...).

### 2. Danh sách trường

| STT | Tên trường | Kiểu dữ liệu | Null | Default | Khóa/Ràng buộc | Mô tả tác dụng trường | Ví dụ |
|---|---|---|---|---|---|---|---|
| 1 | system_policy_id | char | Không | - | - | ID chính sách | UUID-policy |
| 2 | old_status | string | Có | - | - | Trạng thái cũ (null khi tạo mới) | null |
| 3 | new_status | string | Không | - | - | Trạng thái mới | active |
| 4 | changed_by | char | Có | - | - | Admin thay đổi | UUID-admin |
| 5 | actor_type | string | Không | admin | - | Loại tác nhân: admin, system | admin |
| 6 | reason | text | Có | - | - | Lý do thay đổi | Publish chính sách hoàn tiền v1 |
| 7 | created_at | timestamp | Không | - | - | Thời điểm thay đổi | 2026-06-08 10:00:00 |

### 3. Khóa chính, khóa ngoại, index
- PK: id

### 4. Quan hệ với bảng khác
- Thuộc system_policies. Mỗi policy có nhiều status history records.

### 5. Ví dụ bản ghi
```json
{
  "system_policy_id": "UUID-refund-policy",
  "old_status": "draft",
  "new_status": "active",
  "changed_by": "UUID-admin",
  "actor_type": "admin",
  "reason": "Publish chính sách hoàn tiền v1"
}
```

---

---


### MODULE: REFUND BỔ SUNG (08/06/2026)



## Tên bảng: refund_status_histories

### 1. Mục đích bảng
Ghi nhận từng bước xử lý hoàn tiền: khách yêu cầu → owner xác nhận → admin xử lý → gateway hoàn → hoàn tất.

### 2. Danh sách trường

| STT | Tên trường | Kiểu dữ liệu | Null | Default | Khóa/Ràng buộc | Mô tả tác dụng trường | Ví dụ |
|---|---|---|---|---|---|---|---|
| 1 | refund_id | char | Không | - | - | ID refund | UUID-refund |
| 2 | old_status | string | Có | - | - | Trạng thái cũ | pending_owner_confirmation |
| 3 | new_status | string | Không | - | - | Trạng thái mới | owner_confirmed |
| 4 | changed_by | char | Có | - | - | Người thay đổi | UUID-owner |
| 5 | actor_type | string | Không | system | - | Loại tác nhân: customer, owner, admin, system | owner |
| 6 | reason | text | Có | - | - | Lý do/ghi chú | Chủ sân xác nhận yêu cầu hoàn tiền |
| 7 | metadata | json | Có | - | - | Dữ liệu bổ sung | {"confirm_method": "manual"} |
| 8 | created_at | timestamp | Không | - | - | Thời điểm thay đổi | 2026-06-08 10:00:00 |

### 3. Khóa chính, khóa ngoại, index
- PK: id

### 4. Quan hệ với bảng khác
- Thuộc refunds. Mỗi refund có nhiều status history records.

### 5. Ví dụ bản ghi
```json
{
  "refund_id": "UUID-refund",
  "old_status": "pending_owner_confirmation",
  "new_status": "owner_confirmed",
  "changed_by": "UUID-owner",
  "actor_type": "owner"
}
```

---

---


### MODULE: HỒ SƠ ĐỐI TÁC BỔ SUNG (08/06/2026)



## Tên bảng: partner_application_documents

### 1. Mục đích bảng
Lưu file/tài liệu đính kèm hồ sơ đối tác: ảnh mặt tiền sân, CCCD, giấy đăng ký kinh doanh, hợp đồng thuê mặt bằng, chứng từ ngân hàng. Mỗi file được admin review và đánh dấu verified/rejected.

### 2. Danh sách trường

| STT | Tên trường | Kiểu dữ liệu | Null | Default | Khóa/Ràng buộc | Mô tả tác dụng trường | Ví dụ |
|---|---|---|---|---|---|---|---|
| 1 | partner_application_id | char | Không | - | - | ID hồ sơ đối tác | UUID-app |
| 2 | media_id | char | Có | - | - | ID file media (nếu dùng bảng media) | UUID-media |
| 3 | document_type | string | Không | - | - | Loại tài liệu: venue_front_image, identity_front, business_registration... | identity_front |
| 4 | document_group | string | Không | - | - | Nhóm tài liệu: venue_images, identity_documents, business_documents, land_documents, bank_documents | identity_documents |
| 5 | title | string | Không | - | - | Tên hiển thị của tài liệu | CCCD mặt trước |
| 6 | description | text | Có | - | - | Mô tả chi tiết | File seed dùng để kiểm tra... |
| 7 | file_path | string | Có | - | - | Đường dẫn file trên storage | /seed/partner-applications/1/identity-front.jpg |
| 8 | reviewed_by | char | Có | - | - | Admin review file | UUID-admin |
| 9 | reviewed_at | timestamp | Có | - | - | Thời điểm review | 2026-06-05 10:00:00 |
| 10 | reject_reason | text | Có | - | - | Lý do từ chối file | Chứng từ không khớp tên... |
| 11 | sort_order | unsignedInteger | Không | 0 | - | Thứ tự hiển thị | 1 |
| 12 | created_at | timestamp | Có | - | - | (Cần cập nhật) | - |
| 13 | updated_at | timestamp | Có | - | - | (Cần cập nhật) | - |

### 3. Khóa chính, khóa ngoại, index
- PK: id

### 4. Quan hệ với bảng khác
- Thuộc partner_applications (1 hồ sơ có nhiều tài liệu)
- Tham chiếu media (file thực tế), users (admin review)

### 5. Ví dụ bản ghi
```json
{
  "partner_application_id": "UUID-app",
  "document_type": "identity_front",
  "document_group": "identity_documents",
  "title": "CCCD mặt trước",
  "file_path": "/seed/partner-applications/1/identity-front.jpg",
  "status": "verified",
  "reviewed_by": "UUID-admin"
}
```

---

---
## Tên bảng: partner_application_status_histories

### 1. Mục đích bảng
Ghi nhận lịch sử đổi trạng thái hồ sơ đối tác: submitted → reviewing → approved_pending_contract → contract_pending_owner_signature...

### 2. Danh sách trường

| STT | Tên trường | Kiểu dữ liệu | Null | Default | Khóa/Ràng buộc | Mô tả tác dụng trường | Ví dụ |
|---|---|---|---|---|---|---|---|
| 1 | partner_application_id | char | Không | - | - | ID hồ sơ đối tác | UUID-app |
| 2 | old_status | string | Có | - | - | Trạng thái cũ | submitted |
| 3 | new_status | string | Không | - | - | Trạng thái mới | reviewing |
| 4 | changed_by | char | Có | - | - | Người thay đổi | UUID-admin |
| 5 | actor_type | string | Không | admin | - | Loại tác nhân: admin, owner, system | admin |
| 6 | reason | text | Có | - | - | Lý do | Admin tiếp nhận hồ sơ |
| 7 | metadata | json | Có | - | - | Dữ liệu bổ sung | null |
| 8 | created_at | timestamp | Không | - | - | Thời điểm thay đổi | 2026-06-08 |

### 3. Khóa chính, khóa ngoại, index
- PK: id

### 4. Quan hệ với bảng khác
- Thuộc partner_applications.

### 5. Ví dụ bản ghi
```json
{
  "partner_application_id": "UUID-app",
  "old_status": "submitted",
  "new_status": "reviewing",
  "changed_by": "UUID-admin",
  "actor_type": "admin"
}
```

---

---


### MODULE: TEMPLATE VĂN BẢN (08/06/2026)



## Tên bảng: document_templates

### 1. Mục đích bảng
Lưu template DOCX cho các loại văn bản: đơn đăng ký đối tác, hợp đồng, đơn chấm dứt, biên bản thanh lý, công văn đơn phương, biên bản quyết toán. Mỗi loại có nhiều version, chỉ 1 version active.

### 2. Danh sách trường

| STT | Tên trường | Kiểu dữ liệu | Null | Default | Khóa/Ràng buộc | Mô tả tác dụng trường | Ví dụ |
|---|---|---|---|---|---|---|---|
| 1 | id | char | Không | - | PK | UUID template | UUID-template |
| 2 | template_code | string | Không | - | - | Mã template duy nhất | TPL-HD-V1 |
| 3 | document_type | string | Không | - | - | Loại văn bản | partner_contract |
| 4 | template_name | string | Không | - | - | Tên hiển thị | Mẫu hợp đồng đối tác v1 |
| 5 | version | unsignedInteger | Không | 1 | - | Phiên bản template | 1 |
| 6 | file_name | string | Không | - | - | Tên file gốc | hop-dong-doi-tac.docx |
| 7 | file_path | string | Không | - | - | Đường dẫn trên storage | document-templates/hop-dong-doi-tac.docx |
| 8 | mime_type | string | Không | application/vnd.openxmlformats-officedocument.wordprocessingml.document | - | MIME type | application/vnd.openxmlformats-officedocument... |
| 9 | storage_disk | string | Không | local | - | Disk storage Laravel | local |
| 10 | template_variables | json | Có | - | - | Danh sách biến placeholder | ["contract_code", "owner_full_name"] |
| 11 | required_fields | json | Có | - | - | Các field bắt buộc phải có khi render | ["owner_full_name", "business_name"] |
| 12 | is_active | boolean | Không | false | - | Đang được dùng cho văn bản mới | true |
| 13 | created_by | char | Có | - | - | Người tạo template | UUID-admin |
| 14 | uploaded_by | char | Có | - | - | Người upload file | UUID-admin |
| 15 | activated_at | timestamp | Có | - | - | Thời điểm kích hoạt | 2026-06-08 |
| 16 | replaced_template_id | char | Có | - | - | Template version cũ bị thay thế | UUID-old-template |
| 17 | note | text | Có | - | - | Ghi chú | Template ban đầu |
| 18 | created_at | timestamp | Có | - | - | (Cần cập nhật) | - |
| 19 | updated_at | timestamp | Có | - | - | (Cần cập nhật) | - |

### 3. Khóa chính, khóa ngoại, index
- PK: id

### 4. Quan hệ với bảng khác
- 1-n với generated_documents qua template_id
- Self-reference qua replaced_template_id (version cũ → mới)

### 5. Ví dụ bản ghi
```json
{
  "id": "UUID-template",
  "template_code": "TPL-HD-V1",
  "document_type": "partner_contract",
  "template_name": "Mẫu hợp đồng đối tác v1",
  "version": 1,
  "file_name": "hop-dong-doi-tac.docx",
  "status": "active",
  "is_active": true
}
```

---

---
## Tên bảng: generated_documents

### 1. Mục đích bảng
Lưu văn bản đã sinh từ template, bao gồm snapshot dữ liệu render, file path và trạng thái ký. Mỗi văn bản liên kết polymorphic hoặc FK trực tiếp tới hồ sơ/hợp đồng/chấm dứt/quyết toán.

### 2. Danh sách trường

| STT | Tên trường | Kiểu dữ liệu | Null | Default | Khóa/Ràng buộc | Mô tả tác dụng trường | Ví dụ |
|---|---|---|---|---|---|---|---|
| 1 | id | char | Không | - | PK | UUID văn bản | UUID-doc |
| 2 | document_code | string | Không | - | - | Mã văn bản duy nhất | DOC-HD-CG-001 |
| 3 | document_type | string | Không | - | - | Loại văn bản | partner_contract |
| 4 | template_id | char | Không | - | - | Template dùng để sinh | UUID-template |
| 5 | template_version | unsignedInteger | Không | - | - | Version template tại thời điểm sinh | 1 |
| 6 | reference_type | string | Có | - | - | Polymorphic type | App\Models\PartnerApplication |
| 7 | reference_id | string | Có | - | - | Polymorphic ID | UUID-app |
| 8 | entity_type | string | Có | - | - | Entity type bổ sung | null |
| 9 | entity_id | string | Có | - | - | Entity ID bổ sung | null |
| 10 | partner_application_id | char | Có | - | - | FK trực tiếp tới hồ sơ | UUID-app |
| 11 | partner_contract_id | char | Có | - | - | FK trực tiếp tới hợp đồng | UUID-contract |
| 12 | partner_termination_request_id | char | Có | - | - | FK trực tiếp tới yêu cầu chấm dứt | null |
| 13 | partner_settlement_id | char | Có | - | - | FK trực tiếp tới quyết toán | null |
| 14 | owner_id | char | Có | - | - | Chủ sân liên quan | UUID-owner |
| 15 | venue_cluster_id | char | Có | - | - | Cụm sân liên quan | UUID-cluster |
| 16 | title | string | Có | - | - | Tiêu đề văn bản | null |
| 17 | render_data | json | Không | - | - | Snapshot toàn bộ dữ liệu render | {"contract_code": "HD-CG-001", ...} |
| 18 | generated_file_media_id | char | Có | - | - | Media ID file đã sinh | UUID-media |
| 19 | signed_file_media_id | char | Có | - | - | Media ID file đã ký | UUID-media |
| 20 | final_file_media_id | char | Có | - | - | Media ID file hoàn tất | UUID-media |
| 21 | generated_file_path | string | Có | - | - | Path file đã sinh | generated-documents/HD-CG-001.docx |
| 22 | final_file_path | string | Có | - | - | Path file hoàn tất | null |
| 23 | file_hash | string | Có | - | - | Hash file để verify tính toàn vẹn | null |
| 24 | generated_by | char | Có | - | - | Người sinh văn bản | UUID-admin |
| 25 | generated_at | timestamp | Có | - | - | Thời điểm sinh | 2026-06-05 |
| 26 | locked_at | timestamp | Có | - | - | Thời điểm lock (không sửa được) | 2026-06-06 |
| 27 | completed_at | timestamp | Có | - | - | Thời điểm hoàn tất | 2026-06-07 |
| 28 | created_at | timestamp | Có | - | - | (Cần cập nhật) | - |
| 29 | updated_at | timestamp | Có | - | - | (Cần cập nhật) | - |

### 3. Khóa chính, khóa ngoại, index
- PK: id

### 4. Quan hệ với bảng khác
- Thuộc document_templates (template dùng để sinh)
- 1-n với generated_document_signatures (chữ ký trên văn bản)
- Tham chiếu partner_applications, partner_contracts, partner_termination_requests, partner_settlements, users, venue_clusters, media

### 5. Ví dụ bản ghi
```json
{
  "document_code": "DOC-HD-CG-001",
  "document_type": "partner_contract",
  "template_version": 1,
  "status": "completed",
  "render_data": {
    "contract_code": "HD-CG-001",
    "owner_full_name": "Nguyễn Văn Owner",
    "business_name": "Hộ kinh doanh SportGo Cầu Giấy"
  }
}
```

---

---
## Tên bảng: generated_document_signatures

### 1. Mục đích bảng
Lưu chữ ký/xác nhận của mỗi bên (owner, SportGo) trên văn bản đã sinh. Hỗ trợ nhiều phương thức ký: upload ảnh, vẽ tay, gõ xác nhận, OTP, ký số.

### 2. Danh sách trường

| STT | Tên trường | Kiểu dữ liệu | Null | Default | Khóa/Ràng buộc | Mô tả tác dụng trường | Ví dụ |
|---|---|---|---|---|---|---|---|
| 1 | id | char | Không | - | PK | UUID chữ ký | UUID-sig |
| 2 | generated_document_id | char | Không | - | - | ID văn bản | UUID-doc |
| 3 | signer_user_id | char | Có | - | - | User thực hiện ký | UUID-owner |
| 4 | signer_full_name | string | Không | - | - | Họ tên người ký | Nguyễn Văn Owner |
| 5 | signer_title | string | Có | - | - | Chức danh | Chủ sân |
| 6 | signer_organization | string | Có | - | - | Tổ chức | Hộ kinh doanh SportGo CG |
| 7 | signature_media_id | char | Có | - | - | Media ID ảnh chữ ký (nếu upload/vẽ) | null |
| 8 | signed_at | timestamp | Có | - | - | Thời điểm ký | 2026-06-06 |
| 9 | ip_address | string | Có | - | - | IP lúc ký | 192.168.1.1 |
| 10 | user_agent | string | Có | - | - | User Agent lúc ký | Mozilla/5.0... |
| 11 | reject_reason | text | Có | - | - | Lý do từ chối ký | null |
| 12 | created_at | timestamp | Có | - | - | (Cần cập nhật) | - |
| 13 | updated_at | timestamp | Có | - | - | (Cần cập nhật) | - |

### 3. Khóa chính, khóa ngoại, index
- PK: id

### 4. Quan hệ với bảng khác
- Thuộc generated_documents (1 văn bản có nhiều chữ ký)
- Tham chiếu users (người ký), media (ảnh chữ ký)

### 5. Ví dụ bản ghi
```json
{
  "generated_document_id": "UUID-doc",
  "signer_side": "owner",
  "signer_full_name": "Nguyễn Văn Owner",
  "signer_title": "Chủ sân",
  "signature_method": "typed_confirm",
  "signed_at": "2026-06-06T10:00:00",
  "status": "signed"
}
```

---

---


### MODULE: HỢP ĐỒNG ĐỐI TÁC (08/06/2026)



## Tên bảng: partner_contracts

### 1. Mục đích bảng
Lưu hợp đồng giữa SportGo và chủ sân. Mỗi hợp đồng được sinh từ hồ sơ đối tác đã duyệt, link tới văn bản đã ký. Status: draft → generated → pending_owner_signature → pending_sportgo_signature → signed_active.

### 2. Danh sách trường

| STT | Tên trường | Kiểu dữ liệu | Null | Default | Khóa/Ràng buộc | Mô tả tác dụng trường | Ví dụ |
|---|---|---|---|---|---|---|---|
| 1 | id | char | Không | - | PK | UUID hợp đồng | UUID-contract |
| 2 | contract_code | string | Không | - | - | Mã hợp đồng | HD-CG-001 |
| 3 | partner_application_id | char | Không | - | - | Hồ sơ đối tác sinh ra hợp đồng | UUID-app |
| 4 | owner_id | char | Không | - | - | Chủ sân ký hợp đồng | UUID-owner |
| 5 | venue_cluster_id | char | Có | - | - | Cụm sân (nếu đã duyệt tạo) | UUID-cluster |
| 6 | contract_title | string | Không | - | - | Tiêu đề hợp đồng | Hợp đồng hợp tác đối tác SportGo CG |
| 7 | generated_document_id | char | Có | - | - | Văn bản hợp đồng đã sinh | UUID-doc |
| 8 | generated_file_media_id | char | Có | - | - | Media ID file đã sinh | null |
| 9 | signed_file_media_id | char | Có | - | - | Media ID file đã ký | null |
| 10 | final_file_media_id | char | Có | - | - | Media ID file hoàn tất | null |
| 11 | generated_by | char | Có | - | - | Admin sinh hợp đồng | UUID-admin |
| 12 | approved_by | char | Có | - | - | Admin duyệt | UUID-admin |
| 13 | owner_signed_at | timestamp | Có | - | - | Thời điểm chủ sân ký | 2026-06-06 |
| 14 | sportgo_signed_at | timestamp | Có | - | - | Thời điểm SportGo ký | 2026-06-07 |
| 15 | effective_from | timestamp | Có | - | - | Ngày bắt đầu hiệu lực | 2026-06-07 |
| 16 | effective_to | timestamp | Có | - | - | Ngày hết hạn (null = không thời hạn) | null |
| 17 | terminated_at | timestamp | Có | - | - | Ngày chấm dứt sớm | null |
| 18 | note | text | Có | - | - | Ghi chú | null |
| 19 | created_at | timestamp | Có | - | - | (Cần cập nhật) | - |
| 20 | updated_at | timestamp | Có | - | - | (Cần cập nhật) | - |
| 21 | contract_template_id | foreignId | Không | - | - | (Cần cập nhật) | - |
| 22 | contract_number | string | Không | - | - | (Cần cập nhật) | - |
| 23 | status | string | Không | draft | - | Trạng thái: draft, generated, pending_owner_signature, pending_sportgo_signature, signed_active, cancelled, terminated | signed_active |
| 24 | generated_file_path | string | Có | - | - | (Cần cập nhật) | - |
| 25 | final_signed_file_path | string | Có | - | - | (Cần cập nhật) | - |
| 26 | completed_at | timestamp | Có | - | - | (Cần cập nhật) | - |
| 27 | deleted_at | timestamp | Có | - | - | (Cần cập nhật) | - |

### 3. Khóa chính, khóa ngoại, index
- PK: id

### 4. Quan hệ với bảng khác
- Thuộc partner_applications (1 hồ sơ → 1 hợp đồng)
- Tham chiếu users (owner, admin), venue_clusters, generated_documents
- 1-n với partner_termination_requests, partner_settlements

### 5. Ví dụ bản ghi
```json
{
  "contract_code": "HD-CG-001",
  "partner_application_id": "UUID-app",
  "owner_id": "UUID-owner",
  "contract_title": "Hợp đồng hợp tác đối tác SportGo Cầu Giấy",
  "status": "signed_active",
  "owner_signed_at": "2026-06-06T10:00:00",
  "sportgo_signed_at": "2026-06-07T10:00:00"
}
```

---

---


### MODULE: CHẤM DỨT HỢP TÁC (08/06/2026)



## Tên bảng: partner_termination_requests

### 1. Mục đích bảng
Quản lý yêu cầu chấm dứt hợp tác: hai bên đồng ý (mutual_agreement), đơn phương bởi owner (unilateral_by_owner) hoặc đơn phương bởi SportGo (unilateral_by_sportgo). Có thời gian chuyển tiếp trước khi thu quyền.

### 2. Danh sách trường

| STT | Tên trường | Kiểu dữ liệu | Null | Default | Khóa/Ràng buộc | Mô tả tác dụng trường | Ví dụ |
|---|---|---|---|---|---|---|---|
| 1 | id | char | Không | - | PK | UUID yêu cầu | UUID-term |
| 2 | termination_code | string | Không | - | - | Mã yêu cầu chấm dứt | TERM-MUTUAL-CG-001 |
| 3 | partner_contract_id | char | Không | - | - | Hợp đồng bị chấm dứt | UUID-contract |
| 4 | partner_application_id | char | Có | - | - | Hồ sơ đối tác gốc | UUID-app |
| 5 | owner_id | char | Không | - | - | Chủ sân | UUID-owner |
| 6 | venue_cluster_id | char | Có | - | - | Cụm sân liên quan | UUID-cluster |
| 7 | requested_by | char | Không | - | - | Người gửi yêu cầu | UUID-owner |
| 8 | requested_at | timestamp | Không | - | - | Thời điểm gửi | 2026-06-08 |
| 9 | reason | text | Không | - | - | Lý do chấm dứt | Hai bên thống nhất chấm dứt hợp tác... |
| 10 | requested_effective_date | date | Có | - | - | Ngày mong muốn hiệu lực | 2026-07-08 |
| 11 | approved_by | char | Có | - | - | Admin duyệt | UUID-admin |
| 12 | approved_at | timestamp | Có | - | - | Thời điểm duyệt | 2026-06-09 |
| 13 | reject_reason | text | Có | - | - | Lý do từ chối | null |
| 14 | effective_termination_date | timestamp | Có | - | - | Ngày chấm dứt thực tế | 2026-07-08 |
| 15 | transition_end_at | timestamp | Có | - | - | Hết thời gian chuyển tiếp | 2026-08-08 |
| 16 | owner_access_revoked_at | timestamp | Có | - | - | Thời điểm thu quyền owner | null |
| 17 | created_at | timestamp | Có | - | - | (Cần cập nhật) | - |
| 18 | updated_at | timestamp | Có | - | - | (Cần cập nhật) | - |
| 19 | type | string | Không | - | - | (Cần cập nhật) | - |
| 20 | status | string | Không | pending | - | Trạng thái: draft, submitted, reviewing, approved, pending_signature, settlement_processing, settlement_completed, transition_period, completed, rejected, cancelled | settlement_completed |

### 3. Khóa chính, khóa ngoại, index
- PK: id

### 4. Quan hệ với bảng khác
- Thuộc partner_contracts
- 1-n với partner_termination_documents, partner_termination_status_histories, partner_settlements
- Tham chiếu users, venue_clusters, partner_applications

### 5. Ví dụ bản ghi
```json
{
  "termination_code": "TERM-MUTUAL-CG-001",
  "termination_type": "mutual_agreement",
  "status": "settlement_completed",
  "reason": "Hai bên thống nhất chấm dứt hợp tác do thay đổi kế hoạch kinh doanh."
}
```

---

---
## Tên bảng: partner_termination_documents

### 1. Mục đích bảng
Lưu các văn bản liên quan đến yêu cầu chấm dứt: đơn chấm dứt, biên bản thanh lý, công văn đơn phương, biên bản quyết toán, file cuối cùng.

### 2. Danh sách trường

| STT | Tên trường | Kiểu dữ liệu | Null | Default | Khóa/Ràng buộc | Mô tả tác dụng trường | Ví dụ |
|---|---|---|---|---|---|---|---|
| 1 | partner_termination_request_id | char | Không | - | - | ID yêu cầu chấm dứt | UUID-term |
| 2 | generated_document_id | char | Có | - | - | Văn bản đã sinh từ template | UUID-doc |
| 3 | media_id | char | Có | - | - | File đính kèm bổ sung | null |
| 4 | file_path | string | Có | - | - | Đường dẫn file | null |
| 5 | generated_by | char | Có | - | - | Admin sinh văn bản | UUID-admin |
| 6 | generated_at | timestamp | Có | - | - | Thời điểm sinh | 2026-06-08 |
| 7 | created_at | timestamp | Có | - | - | (Cần cập nhật) | - |
| 8 | updated_at | timestamp | Có | - | - | (Cần cập nhật) | - |

### 3. Khóa chính, khóa ngoại, index
- PK: id

### 4. Quan hệ với bảng khác
- Thuộc partner_termination_requests
- Tham chiếu generated_documents, media, users

### 5. Ví dụ bản ghi
```json
{
  "partner_termination_request_id": "UUID-term",
  "document_type": "mutual_liquidation_minutes",
  "generated_document_id": "UUID-doc",
  "status": "completed"
}
```

---

---
## Tên bảng: partner_termination_status_histories

### 1. Mục đích bảng
Ghi nhận từng bước xử lý yêu cầu chấm dứt hợp tác: submitted → reviewing → approved → settlement_processing → completed.

### 2. Danh sách trường

| STT | Tên trường | Kiểu dữ liệu | Null | Default | Khóa/Ràng buộc | Mô tả tác dụng trường | Ví dụ |
|---|---|---|---|---|---|---|---|
| 1 | partner_termination_request_id | char | Không | - | - | ID yêu cầu chấm dứt | UUID-term |
| 2 | old_status | string | Có | - | - | Trạng thái cũ | submitted |
| 3 | new_status | string | Không | - | - | Trạng thái mới | reviewing |
| 4 | changed_by | char | Có | - | - | Người thay đổi | UUID-admin |
| 5 | actor_type | string | Không | admin | - | Loại tác nhân | admin |
| 6 | reason | text | Có | - | - | Lý do | Admin tiếp nhận yêu cầu |
| 7 | metadata | json | Có | - | - | Dữ liệu bổ sung | null |
| 8 | created_at | timestamp | Không | - | - | Thời điểm | 2026-06-08 |

### 3. Khóa chính, khóa ngoại, index
- PK: id

### 4. Quan hệ với bảng khác
- Thuộc partner_termination_requests.

### 5. Ví dụ bản ghi
```json
{
  "partner_termination_request_id": "UUID-term",
  "old_status": "submitted",
  "new_status": "reviewing",
  "changed_by": "UUID-admin",
  "actor_type": "admin"
}
```

---

---


### MODULE: QUYẾT TOÁN (08/06/2026)



## Tên bảng: partner_settlements

### 1. Mục đích bảng
Lưu kết quả quyết toán công nợ khi chấm dứt hợp tác. Tính toán: ví owner, phí nền tảng còn hoàn, phí nền tảng chưa đóng, phạt, điều chỉnh → ra final_payable_to_owner hoặc final_receivable_from_owner.

### 2. Danh sách trường

| STT | Tên trường | Kiểu dữ liệu | Null | Default | Khóa/Ràng buộc | Mô tả tác dụng trường | Ví dụ |
|---|---|---|---|---|---|---|---|
| 1 | id | char | Không | - | PK | UUID quyết toán | UUID-settle |
| 2 | settlement_code | string | Không | - | - | Mã quyết toán | SETTLE-CG-001 |
| 3 | partner_termination_request_id | char | Không | - | - | Yêu cầu chấm dứt | UUID-term |
| 4 | partner_contract_id | char | Không | - | - | Hợp đồng bị chấm dứt | UUID-contract |
| 5 | owner_id | char | Không | - | - | Chủ sân | UUID-owner |
| 6 | venue_cluster_id | char | Có | - | - | Cụm sân | UUID-cluster |
| 7 | calculated_by | char | Có | - | - | Admin tính toán | UUID-admin |
| 8 | approved_by | char | Có | - | - | Admin duyệt quyết toán | UUID-admin |
| 9 | approved_at | timestamp | Có | - | - | Thời điểm duyệt | 2026-06-09 |
| 10 | note | text | Có | - | - | Ghi chú quyết toán | Quyết toán mẫu cho test |
| 11 | created_at | timestamp | Có | - | - | (Cần cập nhật) | - |
| 12 | updated_at | timestamp | Có | - | - | (Cần cập nhật) | - |

### 3. Khóa chính, khóa ngoại, index
- PK: id

### 4. Quan hệ với bảng khác
- Thuộc partner_termination_requests (1 yêu cầu chấm dứt → 1 quyết toán)
- 1-n với partner_settlement_items (chi tiết từng dòng)
- Nếu final_payable_to_owner > 0 → tạo owner_withdrawal_requests tự động

### 5. Ví dụ bản ghi
```json
{
  "settlement_code": "SETTLE-CG-001",
  "owner_wallet_available_amount": 2000000.00,
  "platform_fee_remaining_refund_amount": 200000.00,
  "final_payable_to_owner": 2200000.00,
  "final_receivable_from_owner": 0.00,
  "status": "completed"
}
```

---

---
## Tên bảng: partner_settlement_items

### 1. Mục đích bảng
Lưu từng dòng chi tiết trong biên bản quyết toán: ví owner, rút tiền đang chờ, phí nền tảng hoàn, phí chưa đóng, phạt, điều chỉnh.

### 2. Danh sách trường

| STT | Tên trường | Kiểu dữ liệu | Null | Default | Khóa/Ràng buộc | Mô tả tác dụng trường | Ví dụ |
|---|---|---|---|---|---|---|---|
| 1 | partner_settlement_id | char | Không | - | - | ID quyết toán cha | UUID-settle |
| 2 | description | text | Không | - | - | Mô tả dòng | Số dư ví owner hiện tại |
| 3 | reference_type | string | Có | - | - | Polymorphic type | App\Models\OwnerWallet |
| 4 | reference_id | string | Có | - | - | Polymorphic ID | UUID-wallet |
| 5 | created_at | timestamp | Không | - | - | Thời điểm tạo | 2026-06-08 |

### 3. Khóa chính, khóa ngoại, index
- PK: id

### 4. Quan hệ với bảng khác
- Thuộc partner_settlements (1 quyết toán → nhiều items)

### 5. Ví dụ bản ghi
```json
{
  "partner_settlement_id": "UUID-settle",
  "item_type": "owner_wallet_balance",
  "description": "Số dư ví owner hiện tại",
  "amount": 2000000.00,
  "direction": "payable_to_owner"
}
```

---

---


### MODULE: GIỚI HẠN QUYỀN OWNER (08/06/2026)



## Tên bảng: venue_access_restrictions

### 1. Mục đích bảng
Giới hạn hoặc chặn quyền owner trên cụm sân. Ví dụ: quá hạn phí nền tảng → limited (hạn chế một số chức năng), chấm dứt hợp đồng → blocked (chặn toàn bộ).

### 2. Danh sách trường

| STT | Tên trường | Kiểu dữ liệu | Null | Default | Khóa/Ràng buộc | Mô tả tác dụng trường | Ví dụ |
|---|---|---|---|---|---|---|---|
| 1 | id | char | Không | - | PK | UUID restriction | UUID-restriction |
| 2 | venue_cluster_id | char | Không | - | - | Cụm sân bị giới hạn | UUID-cluster |
| 3 | reason | text | Không | - | - | Lý do giới hạn | Phí duy trì nền tảng quá hạn 7 ngày |
| 4 | starts_at | timestamp | Không | - | - | Bắt đầu hiệu lực | 2026-06-01 |
| 5 | ends_at | timestamp | Có | - | - | Kết thúc (null = vô thời hạn) | null |
| 6 | created_by | char | Có | - | - | Admin/system tạo | UUID-admin |
| 7 | created_at | timestamp | Có | - | - | (Cần cập nhật) | - |
| 8 | updated_at | timestamp | Có | - | - | (Cần cập nhật) | - |

### 3. Khóa chính, khóa ngoại, index
- PK: id

### 4. Quan hệ với bảng khác
- Thuộc venue_clusters (1 cụm sân có thể nhiều restriction)
- Tham chiếu users (admin tạo)

### 5. Ví dụ bản ghi
```json
{
  "venue_cluster_id": "UUID-cluster",
  "restriction_type": "platform_fee_overdue",
  "access_mode": "limited",
  "reason": "Phí duy trì nền tảng quá hạn 7 ngày",
  "starts_at": "2026-06-01T00:00:00",
  "status": "active"
}
```

---

==================================================
## CẬP NHẬT 08/06/2026 - TÁCH MIGRATION/SEEDER THEO TỪNG BẢNG
==================================================

Mục này chuẩn hóa DB nền cho các module chính sách hệ thống, chính sách sân override, refund, hồ sơ đối tác, template/văn bản, hợp đồng đối tác, chấm dứt hợp tác, quyết toán và khóa/giới hạn quyền owner. Nguyên tắc áp dụng: mỗi migration chỉ tạo hoặc sửa một bảng; mỗi seeder chỉ seed một bảng nghiệp vụ chính; dữ liệu seed phải idempotent và đủ trạng thái để test luồng thật.

### Bảng mới

| Bảng | Module | Mục đích | Migration | Seeder |
|---|---|---|---|---|
| `policy_rule_templates` | Chính sách hệ thống | Mẫu cấu hình rule để admin tạo rule nhanh | `create_policy_rule_templates_table` | `PolicyRuleTemplatesTableSeeder` |
| `policy_override_constraints` | Chính sách sân override | Ràng buộc để chính sách sân không vượt khung hệ thống | `create_policy_override_constraints_table` | `PolicyOverrideConstraintsTableSeeder` |
| `policy_status_histories` | Chính sách hệ thống | Lịch sử đổi trạng thái/version chính sách | `create_policy_status_histories_table` | `PolicyStatusHistoriesTableSeeder` |
| `refund_status_histories` | Refund | Lịch sử chuyển trạng thái refund | `create_refund_status_histories_table` | `RefundStatusHistoriesTableSeeder` |
| `partner_application_documents` | Hồ sơ đối tác | File/hồ sơ đính kèm của đơn đối tác | `create_partner_application_documents_table` | `PartnerApplicationDocumentsTableSeeder` |
| `partner_application_status_histories` | Hồ sơ đối tác | Lịch sử trạng thái hồ sơ đối tác | `create_partner_application_status_histories_table` | `PartnerApplicationStatusHistoriesTableSeeder` |
| `document_templates` | Template/văn bản | Template DOCX cho đơn, hợp đồng, thanh lý, công văn, quyết toán | `create_document_templates_table` | `DocumentTemplatesTableSeeder` |
| `generated_documents` | Template/văn bản | Văn bản đã sinh từ template, có `render_data` snapshot | `create_generated_documents_table` | `GeneratedDocumentsTableSeeder` |
| `generated_document_signatures` | Template/văn bản | Chữ ký/xác nhận của owner và SportGo trên văn bản | `create_generated_document_signatures_table` | `GeneratedDocumentSignaturesTableSeeder` |
| `partner_contracts` | Hợp đồng đối tác | Hợp đồng giữa SportGo và chủ sân | `create_partner_contracts_table` | `PartnerContractsTableSeeder` |
| `partner_termination_requests` | Chấm dứt hợp tác | Yêu cầu chấm dứt hợp tác hai bên/đơn phương | `create_partner_termination_requests_table` | `PartnerTerminationRequestsTableSeeder` |
| `partner_termination_documents` | Chấm dứt hợp tác | Văn bản thanh lý/công văn/quyết toán của yêu cầu chấm dứt | `create_partner_termination_documents_table` | `PartnerTerminationDocumentsTableSeeder` |
| `partner_termination_status_histories` | Chấm dứt hợp tác | Lịch sử trạng thái yêu cầu chấm dứt | `create_partner_termination_status_histories_table` | `PartnerTerminationStatusHistoriesTableSeeder` |
| `partner_settlements` | Quyết toán | Kết quả quyết toán khi chấm dứt hợp tác | `create_partner_settlements_table` | `PartnerSettlementsTableSeeder` |
| `partner_settlement_items` | Quyết toán | Từng dòng cộng/trừ trong quyết toán | `create_partner_settlement_items_table` | `PartnerSettlementItemsTableSeeder` |
| `venue_access_restrictions` | Khóa/giới hạn owner | Trạng thái quyền owner theo cụm sân: full/limited/transition/blocked | `create_venue_access_restrictions_table` | `VenueAccessRestrictionsTableSeeder` |

### Bảng cũ được bổ sung field

| Bảng | Field chính bổ sung | Migration | Seeder liên quan |
|---|---|---|---|
| `venue_policy_rules` | `submitted_by`, `submitted_at`, `reviewed_by`, `reviewed_at`, `reject_reason`, `effective_from`, `effective_to`, `constraint_check_result`; status thêm `pending_review`, `rejected`, `archived` | `add_review_fields_to_venue_policy_rules_table` | `VenuePolicyRulesSeeder` |
| `refunds` | `owner_confirmed_by`, `owner_confirmed_at`, `owner_confirm_note`, `admin_confirmed_by`, `admin_confirmed_at`, `completed_at`, `gateway_refund_txn_id`, `policy_id`, `policy_rule_id`, `policy_evaluation_log_id` | `add_refund_workflow_fields_to_refunds_table` | `RefundsTableSeeder` |
| `partner_applications` | Hồ sơ/snapshot `applicant_*`, `representative_*`, `business_*`, `venue_*`, `amenities`, `court_count_total`, `current_contract_id`; status workflow đối tác mới | `add_workflow_statuses_to_partner_applications_table`, `add_partner_profile_fields_to_partner_applications_table`, `add_contract_fields_to_partner_applications_table` | `PartnerApplicationsTableSeeder` |
| `partner_application_courts` | `court_type_name_snapshot`, `expected_court_count`, `note` | `add_snapshot_fields_to_partner_application_courts_table` | `PartnerApplicationCourtsTableSeeder` |
| `owner_withdrawal_requests` | `source`, `partner_settlement_id`, `partner_termination_request_id`, `auto_created` | `add_settlement_fields_to_owner_withdrawal_requests_table` | `OwnerWithdrawalRequestsTableSeeder` |

### Field/FK/index chính

- `policy_rule_templates`: field chính `policy_type`, `rule_code`, `rule_name`, `action_code`, `condition_schema`, `result_schema`, `is_venue_overridable`, `risk_level`, `is_active`; unique `policy_type/rule_code`; index `policy_type/is_active`, `action_code`.
- `policy_override_constraints`: field chính `system_policy_id`, `policy_rule_id`, `rule_code`, `constraint_key`, `constraint_name`, `comparison_direction`, `min_value`, `max_value`, `allowed_values`, `message_vi`, `is_active`; unique `system_policy_id/constraint_key`; FK `system_policy_id`, FK nullable `policy_rule_id`; index `rule_code/is_active`.
- `policy_status_histories`: FK `system_policy_id`, FK nullable `changed_by`; index `system_policy_id/created_at`.
- `refund_status_histories`: FK `refund_id`, FK nullable `changed_by`; index `refund_id/created_at`, `new_status`.
- `partner_application_documents`: FK `partner_application_id`, FK nullable `media_id`, `reviewed_by`; index `partner_application_id/document_group/status`.
- `partner_application_status_histories`: FK `partner_application_id`, FK nullable `changed_by`; index `partner_application_id/created_at`.
- `document_templates`: unique `document_type/version`, unique `template_code`; FK nullable `uploaded_by`.
- `generated_documents`: unique `document_code`; FK `template_id`; FK nullable `generated_by`; index `reference_type/reference_id`, `document_type/status`.
- `generated_document_signatures`: FK `generated_document_id`, FK nullable `signer_user_id`, `signature_media_id`; index `generated_document_id/signer_side/status`.
- `partner_contracts`: unique `contract_code`; FK tới `partner_applications`, `users`, `venue_clusters`, `generated_documents`; không cascade xóa hợp đồng.
- `partner_termination_requests`: unique `termination_code`; FK tới hợp đồng, hồ sơ, owner, cụm sân; index `partner_contract_id/status`, `owner_id/status`, `termination_type/status`.
- `partner_termination_documents`: FK tới yêu cầu chấm dứt, generated document, media; index `partner_termination_request_id/document_type`.
- `partner_settlements`: unique `settlement_code`; FK tới yêu cầu chấm dứt, hợp đồng, owner, cụm sân; index `partner_termination_request_id/status`, `owner_id/status`.
- `partner_settlement_items`: FK `partner_settlement_id`; index `partner_settlement_id/item_type`.
- `venue_access_restrictions`: FK `venue_cluster_id`, FK nullable `created_by`; index `venue_cluster_id/status`, `restriction_type/access_mode`.

### Luồng nghiệp vụ liên quan

- Chính sách hệ thống: admin tạo/rà rule, dùng template nếu cần, publish chính sách và ghi `policy_status_histories`.
- Chính sách sân override: owner gửi `venue_policy_rules`, hệ thống kiểm tra `policy_override_constraints`, admin duyệt hoặc từ chối.
- Refund: khách yêu cầu hoàn, owner xác nhận, admin xử lý/API hoàn tiền; refund `completed` mới được coi là xong và phải có xác nhận owner/admin.
- Hồ sơ đối tác: user gửi hồ sơ, nộp file đính kèm, admin chuyển trạng thái; duyệt xong sinh hợp đồng.
- Template/văn bản: template DOCX được seed từ bộ biểu mẫu; văn bản sinh ra lưu snapshot `render_data`; chữ ký lưu riêng theo từng bên.
- Hợp đồng: hợp đồng chỉ active khi đủ chữ ký owner và SportGo.
- Chấm dứt hợp tác: `mutual_agreement` phải có biên bản thanh lý; `unilateral_by_owner`/`unilateral_by_sportgo` phải có công văn/đơn chấm dứt; có thời gian chuyển tiếp trước khi chặn quyền.
- Quyết toán: settlement phải có items; nếu `final_payable_to_owner > 0` thì `OwnerWithdrawalRequestsTableSeeder` tạo withdrawal tự động với `source = partner_termination_settlement`, `auto_created = true`.
- Khóa/giới hạn owner: seed đủ case `full`, `limited`, `transition`, `blocked` để test quyền owner.

### Thứ tự migration đã chuẩn hóa

1. `create_policy_rule_templates_table`
2. `create_policy_override_constraints_table`
3. `add_review_fields_to_venue_policy_rules_table`
4. `create_policy_status_histories_table`
5. `add_refund_workflow_fields_to_refunds_table`
6. `create_refund_status_histories_table`
7. `add_workflow_statuses_to_partner_applications_table`
8. `add_partner_profile_fields_to_partner_applications_table`
9. `add_contract_fields_to_partner_applications_table`
10. `add_snapshot_fields_to_partner_application_courts_table`
11. `create_partner_application_documents_table`
12. `create_partner_application_status_histories_table`
13. `create_document_templates_table`
14. `create_generated_documents_table`
15. `create_generated_document_signatures_table`
16. `create_partner_contracts_table`
17. `create_partner_termination_requests_table`
18. `create_partner_termination_documents_table`
19. `create_partner_termination_status_histories_table`
20. `create_partner_settlements_table`
21. `create_partner_settlement_items_table`
22. `add_settlement_fields_to_owner_withdrawal_requests_table`
23. `create_venue_access_restrictions_table`

### Ràng buộc nghiệp vụ seed cần giữ

- Refund `completed` phải có `owner_confirmed_by`, `owner_confirmed_at`, `admin_confirmed_by`, `admin_confirmed_at`.
- Refund `pending_owner_confirmation` chưa được có owner/admin confirmed.
- Contract `signed_active` phải có đủ chữ ký owner và SportGo.
- Generated document `completed` phải có `render_data` snapshot.
- Termination `mutual_agreement` phải có `mutual_liquidation_minutes`.
- Termination `unilateral_by_owner` hoặc `unilateral_by_sportgo` phải có công văn/đơn chấm dứt.
- Termination có thời gian chuyển tiếp thì `transition_end_at` phải sau ngày tạo.
- Settlement phải có settlement items.
- Settlement có `final_payable_to_owner > 0` phải tạo withdrawal tự động.
- Owner access phải có data test `full`, `limited`, `transition`, `blocked`.

### Rà soát bổ sung 08/06/2026 - Logic DB và seeder sau khi audit

Các field bổ sung sau audit:

- `partner_applications`: bổ sung snapshot tài khoản nhận tiền của hồ sơ đối tác gồm `bank_name`, `bank_code`, `account_number`, `account_holder_name`, `bank_branch`, `bank_verification_status`. Các field này nullable/default để không làm vỡ dữ liệu cũ và dùng làm snapshot lúc nộp hồ sơ.
- `document_templates`: bổ sung `output_format`, `required_fields`, `created_by` để biết template sinh ra định dạng gì, cần dữ liệu nào và ai tạo template.
- `generated_documents`: bổ sung `entity_type`, `entity_id`, các FK nghiệp vụ `partner_application_id`, `partner_contract_id`, `partner_termination_request_id`, `partner_settlement_id`, `owner_id`, `venue_cluster_id`, `title`, các media id `generated_file_media_id`, `signed_file_media_id`, `final_file_media_id`, `file_hash`; thêm trạng thái `signed`.
- `generated_documents`: FK tới `partner_contracts`, `partner_termination_requests`, `partner_settlements` được tách sang migration riêng `add_business_foreign_keys_to_generated_documents_table` để tránh lỗi thứ tự tạo bảng.

Seeder được siết lại theo ràng buộc nghiệp vụ:

- `PartnerApplicationsTableSeeder`: đủ 9 trạng thái `submitted`, `reviewing`, `need_supplement`, `approved_pending_contract`, `contract_pending_owner_signature`, `contract_pending_sportgo_signature`, `completed`, `rejected`, `cancelled`; hồ sơ `completed` có `current_contract_id` trỏ tới hợp đồng `signed_active`.
- `DocumentTemplatesTableSeeder`: có template active cho đơn đăng ký đối tác, hợp đồng, đơn chấm dứt, biên bản thanh lý, công văn đơn phương, biên bản quyết toán; có `partner_contract` v2 inactive để test version.
- `GeneratedDocumentsTableSeeder`: có document cho đơn đăng ký, hợp đồng chờ owner ký, hợp đồng chờ SportGo ký, hợp đồng completed, đơn chấm dứt, biên bản thanh lý, công văn đơn phương, biên bản quyết toán; tất cả document `generated/signed/completed` có `template_id`, `template_version`, `render_data`.
- `GeneratedDocumentSignaturesTableSeeder`: hợp đồng active có đủ chữ ký owner và SportGo; công văn đơn phương có chữ ký bên phát hành; biên bản thanh lý có chữ ký hai bên.
- `PartnerTerminationRequestsTableSeeder`: có case `mutual_agreement`, `unilateral_by_owner`, `unilateral_by_sportgo`, `settlement_processing`, `transition_period`, `completed`.
- `PartnerTerminationDocumentsTableSeeder`: mutual có `mutual_liquidation_minutes`; unilateral có đơn/công văn chấm dứt; settlement có biên bản quyết toán.
- `PartnerSettlementsTableSeeder` và `PartnerSettlementItemsTableSeeder`: có case còn tiền trả owner, còn công nợ, đang quyết toán; settlement có item chi tiết và case payable tạo withdrawal tự động.
- `VenueAccessRestrictionsTableSeeder`: có đủ dữ liệu test `full`, `limited`, `transition`, `blocked`.

Kết quả kiểm bằng query sau seed:

- Không có refund `completed` thiếu owner/admin confirm.
- Không có admin confirm refund khi owner chưa confirm.
- Không có refund amount lớn hơn payment amount.
- Không có hồ sơ đối tác `completed` thiếu hợp đồng `signed_active`.
- Không có generated document `generated/signed/completed` thiếu `template_id`, `template_version`, `render_data`.
- Không có hợp đồng `signed_active` thiếu ngày ký owner/SportGo hoặc document completed.
- Không có termination `mutual_agreement` thiếu biên bản thanh lý.
- Không có termination đơn phương thiếu đơn/công văn chấm dứt.
- Không có termination `completed` thiếu settlement `completed`.
- Không có settlement thiếu item.
- Không có settlement payable đã duyệt/đã tạo payout thiếu withdrawal tự động.
# MODULE AMENITIES / TIỆN ÍCH SÂN

## Mục tiêu nghiệp vụ

SportGo dùng danh mục tiện ích chung của hệ thống để lọc/tìm sân theo tiện ích chuẩn. Chủ sân không tự tạo tiện ích riêng dùng ngay trên sân. Chủ sân chỉ được chọn tiện ích đã có trong danh mục chung và nhập mô tả riêng cho cụm sân của mình. Nếu tiện ích chưa có, chủ sân gửi yêu cầu thêm tiện ích với trạng thái `pending_review`; admin duyệt thì tiện ích chuyển thành `active` và dùng chung toàn hệ thống.

Không tạo bảng `venue_custom_amenities`.

## Bảng `amenities`

Mục đích:

- Lưu danh mục tiện ích chung của hệ thống.
- Admin/system thêm trực tiếp thì tiện ích mặc định `active`.
- Owner gửi yêu cầu thêm tiện ích thì trạng thái ban đầu là `pending_review`.
- Admin duyệt/từ chối/hủy/ngưng sử dụng phải ghi người xử lý, thời điểm và lý do nếu trạng thái là `rejected`, `inactive`, `cancelled`.

Field chính:

- `id`: khóa chính (bigint auto-increment).
- `name`: tên tiện ích hiển thị, ví dụ Wifi, Điều hòa, Bãi gửi xe.
- `description`: mô tả chung của tiện ích.
- `status`: enum `pending_review`, `active`, `rejected`, `inactive`, `cancelled`.
- `created_by`: user tạo tiện ích hoặc yêu cầu, nullable FK tới `users.id`.
- `reviewed_by`: admin duyệt/từ chối/hủy/ngưng, nullable FK tới `users.id`.
- `reviewed_at`: thời điểm xử lý.
- `status_reason`: lý do xử lý theo trạng thái:
  * `rejected`: Nội dung từ chối
  * `inactive`: Lý do ngưng sử dụng
  * `cancelled`: Lý do hủy
- `created_at`, `updated_at`, `deleted_at`.

Index/FK/Constraints:

- Virtual column `active_name` = `IF(status = 'active' AND deleted_at IS NULL, name, NULL)`.
- Unique index trên `active_name` đảm bảo không có 2 tiện ích active trùng tên.
- Check Constraint: `status NOT IN ('rejected', 'inactive', 'cancelled') OR status_reason IS NOT NULL` (khi status là rejected, inactive hoặc cancelled thì status_reason bắt buộc).
- FK `created_by`, `reviewed_by` tới `users.id` dùng `nullOnDelete`.

## Bảng `venue_cluster_amenities`

Mục đích:

- Gán tiện ích chung cho từng cụm sân.
- Lưu mô tả riêng của tiện ích tại cụm sân đó.
- Chỉ được gán tiện ích có `amenities.status = active`.

Field chính:

- `id`: khóa chính.
- `venue_cluster_id`: cụm sân được gán tiện ích, FK tới `venue_clusters.id`.
- `amenity_id`: tiện ích chung, FK tới `amenities.id`.
- `description`: mô tả riêng tại cụm sân.
- `is_visible`: có hiển thị tiện ích này trên trang chi tiết sân không (mặc định true).
- `created_at`, `updated_at`.

Index/FK/Triggers:

- Unique `venue_cluster_id + amenity_id`.
- Index `venue_cluster_id`, `amenity_id`.
- FK `venue_cluster_id` tới `venue_clusters.id` dùng cascade delete.
- FK `amenity_id` tới `amenities.id` dùng restrict delete.
- Database Triggers: BEFORE INSERT và BEFORE UPDATE triggers trên `venue_cluster_amenities` để kiểm tra tiện ích được gán có status = 'active' hay không. Nếu không, ném lỗi và từ chối hành động.

## Legacy `venue_clusters.amenities`

`venue_clusters.amenities` dạng JSON là field legacy/tạm tương thích với code cũ. Chức năng tiện ích mới không dùng JSON này làm nguồn chính. Seeder mới không seed thêm dữ liệu vào JSON này. Filter/search tiện ích phải dùng `amenities` và `venue_cluster_amenities` theo `amenity_id`.

## Seeder

- `AmenitiesTableSeeder`: seed 14 tiện ích active chuẩn gồm Wifi, Bãi gửi xe, Điều hòa, Phòng thay đồ, Nhà vệ sinh, Căng tin, Tủ gửi đồ, Cho thuê vợt, Cho thuê bóng, Đèn chiếu sáng, Mái che, Khu nghỉ chờ, Nước uống, Camera an ninh. Seeder cũng seed case `pending_review`, `rejected`, `inactive` để test workflow duyệt tiện ích. Sử dụng cơ chế khôi phục từ soft-delete và updateOrCreate để không trùng lặp record khi chạy lại.
- `VenueClusterAmenitiesTableSeeder`: gán tiện ích active cho 3 cụm sân demo, kèm mô tả riêng theo cụm sân. Seeder không gán tiện ích `pending_review`, `rejected`, `inactive`. Sử dụng updateOrCreate để tránh trùng lặp record.

## Luồng nghiệp vụ

- Admin thêm tiện ích: tạo record `amenities` với `status = active`.
- Owner gửi yêu cầu: tạo record `amenities` với `status = pending_review`, `created_by = owner id`.
- Admin duyệt: chuyển `status = active`, set `reviewed_by`, `reviewed_at = now`.
- Admin từ chối: chuyển `status = rejected`, bắt buộc nhập `status_reason` (Nội dung từ chối), set `reviewed_by`, `reviewed_at = now`.
- Admin ngưng sử dụng: chuyển `status = inactive`, bắt buộc nhập `status_reason` (Lý do ngưng sử dụng), set `reviewed_by`, `reviewed_at = now`.
- Admin hủy: chuyển `status = cancelled`, bắt buộc nhập `status_reason` (Lý do hủy), set `reviewed_by`, `reviewed_at = now`.
- Owner chọn tiện ích cho cụm sân: tạo/cập nhật `venue_cluster_amenities`, chỉ cho chọn tiện ích `active`, không cho trùng `venue_cluster_id + amenity_id`. Chủ sân chỉ được gán tiện ích cho cụm sân của mình.

---
## Tên bảng: venue_cluster_amenities

### 1. Mục đích bảng
(Chưa có mô tả)

### 2. Danh sách trường

| STT | Tên trường | Kiểu dữ liệu | Null | Default | Khóa/Ràng buộc | Mô tả tác dụng trường | Ví dụ |
|---|---|---|---|---|---|---|---|
| 1 | venue_cluster_id | char | Không | - | FK | (Cần cập nhật) | - |
| 2 | amenity_id | unsignedBigInteger | Không | - | FK | (Cần cập nhật) | - |
| 3 | description | text | Có | - | - | (Cần cập nhật) | - |
| 4 | is_visible | boolean | Không | true | - | (Cần cập nhật) | - |
| 5 | created_at | timestamp | Có | - | - | (Cần cập nhật) | - |
| 6 | updated_at | timestamp | Có | - | - | (Cần cập nhật) | - |

### 3. Khóa chính, khóa ngoại, index
- PK: id
- FK: venue_cluster_id -> venue_clusters.id, amenity_id -> amenities.id

### 4. Quan hệ với bảng khác
(Chưa có)

### 5. Ví dụ bản ghi
```json
{}
```

---
## Tên bảng: contract_templates

### 1. Mục đích bảng
(Chưa có mô tả)

### 2. Danh sách trường

| STT | Tên trường | Kiểu dữ liệu | Null | Default | Khóa/Ràng buộc | Mô tả tác dụng trường | Ví dụ |
|---|---|---|---|---|---|---|---|
| 1 | name | string | Không | - | - | (Cần cập nhật) | - |
| 2 | type | string | Không | partner_contract | - | (Cần cập nhật) | - |
| 3 | file_path | string | Không | - | - | (Cần cập nhật) | - |
| 4 | is_active | boolean | Không | true | - | (Cần cập nhật) | - |
| 5 | description | text | Có | - | - | (Cần cập nhật) | - |
| 6 | created_at | timestamp | Có | - | - | (Cần cập nhật) | - |
| 7 | updated_at | timestamp | Có | - | - | (Cần cập nhật) | - |
| 8 | deleted_at | timestamp | Có | - | - | (Cần cập nhật) | - |

### 3. Khóa chính, khóa ngoại, index
- PK: id

### 4. Quan hệ với bảng khác
(Chưa có)

### 5. Ví dụ bản ghi
```json
{}
```

---
## Tên bảng: partner_documents

### 1. Mục đích bảng
(Chưa có mô tả)

### 2. Danh sách trường

| STT | Tên trường | Kiểu dữ liệu | Null | Default | Khóa/Ràng buộc | Mô tả tác dụng trường | Ví dụ |
|---|---|---|---|---|---|---|---|
| 1 | partner_application_id | foreignUuid | Không | - | - | (Cần cập nhật) | - |
| 2 | type | string | Không | - | - | (Cần cập nhật) | - |
| 3 | file_path | string | Không | - | - | (Cần cập nhật) | - |
| 4 | file_name | string | Có | - | - | (Cần cập nhật) | - |
| 5 | created_at | timestamp | Có | - | - | (Cần cập nhật) | - |
| 6 | updated_at | timestamp | Có | - | - | (Cần cập nhật) | - |
| 7 | deleted_at | timestamp | Có | - | - | (Cần cập nhật) | - |

### 3. Khóa chính, khóa ngoại, index
- PK: id

### 4. Quan hệ với bảng khác
(Chưa có)

### 5. Ví dụ bản ghi
```json
{}
```

---
## Tên bảng: contract_signatures

### 1. Mục đích bảng
(Chưa có mô tả)

### 2. Danh sách trường

| STT | Tên trường | Kiểu dữ liệu | Null | Default | Khóa/Ràng buộc | Mô tả tác dụng trường | Ví dụ |
|---|---|---|---|---|---|---|---|
| 1 | partner_contract_id | char | Không | - | FK | (Cần cập nhật) | - |
| 2 | user_id | foreignUuid | Không | - | - | (Cần cập nhật) | - |
| 3 | sign_role | string | Không | - | - | (Cần cập nhật) | - |
| 4 | ip_address | string | Có | - | - | (Cần cập nhật) | - |
| 5 | user_agent | string | Có | - | - | (Cần cập nhật) | - |
| 6 | signed_at | timestamp | Không | - | - | (Cần cập nhật) | - |
| 7 | created_at | timestamp | Có | - | - | (Cần cập nhật) | - |
| 8 | updated_at | timestamp | Có | - | - | (Cần cập nhật) | - |

### 3. Khóa chính, khóa ngoại, index
- PK: id
- FK: partner_contract_id -> partner_contracts.id

### 4. Quan hệ với bảng khác
(Chưa có)

### 5. Ví dụ bản ghi
```json
{}
```

---
## Tên bảng: partner_liquidations

### 1. Mục đích bảng
(Chưa có mô tả)

### 2. Danh sách trường

| STT | Tên trường | Kiểu dữ liệu | Null | Default | Khóa/Ràng buộc | Mô tả tác dụng trường | Ví dụ |
|---|---|---|---|---|---|---|---|
| 1 | partner_contract_id | char | Không | - | FK | (Cần cập nhật) | - |
| 2 | termination_request_id | char | Không | - | FK | (Cần cập nhật) | - |
| 3 | file_path | string | Không | - | - | (Cần cập nhật) | - |
| 4 | status | string | Không | completed | - | (Cần cập nhật) | - |
| 5 | created_at | timestamp | Có | - | - | (Cần cập nhật) | - |
| 6 | updated_at | timestamp | Có | - | - | (Cần cập nhật) | - |

### 3. Khóa chính, khóa ngoại, index
- PK: id
- FK: partner_contract_id -> partner_contracts.id, termination_request_id -> partner_termination_requests.id

### 4. Quan hệ với bảng khác
(Chưa có)

### 5. Ví dụ bản ghi
```json
{}
```

---
## Tên bảng: partner_histories

### 1. Mục đích bảng
(Chưa có mô tả)

### 2. Danh sách trường

| STT | Tên trường | Kiểu dữ liệu | Null | Default | Khóa/Ràng buộc | Mô tả tác dụng trường | Ví dụ |
|---|---|---|---|---|---|---|---|
| 1 | partner_application_id | foreignUuid | Không | - | - | (Cần cập nhật) | - |
| 2 | action | string | Không | - | - | (Cần cập nhật) | - |
| 3 | actor_id | foreignUuid | Có | - | - | (Cần cập nhật) | - |
| 4 | old_values | json | Có | - | - | (Cần cập nhật) | - |
| 5 | new_values | json | Có | - | - | (Cần cập nhật) | - |
| 6 | ip_address | string | Có | - | - | (Cần cập nhật) | - |
| 7 | user_agent | string | Có | - | - | (Cần cập nhật) | - |
| 8 | created_at | timestamp | Có | - | - | (Cần cập nhật) | - |
| 9 | updated_at | timestamp | Có | - | - | (Cần cập nhật) | - |

### 3. Khóa chính, khóa ngoại, index
- PK: id

### 4. Quan hệ với bảng khác
(Chưa có)

### 5. Ví dụ bản ghi
```json
{}
```

---
## Tên bảng: venue_location_change_requests

### 1. Mục đích bảng
(Chưa có mô tả)

### 2. Danh sách trường

| STT | Tên trường | Kiểu dữ liệu | Null | Default | Khóa/Ràng buộc | Mô tả tác dụng trường | Ví dụ |
|---|---|---|---|---|---|---|---|
| 1 | id | char | Không | - | PK | (Cần cập nhật) | - |
| 2 | venue_cluster_id | char | Không | - | FK | (Cần cập nhật) | - |
| 3 | requested_by | char | Không | - | FK | (Cần cập nhật) | - |
| 4 | reviewed_by | char | Có | - | FK | (Cần cập nhật) | - |
| 5 | note | text | Có | - | - | (Cần cập nhật) | - |
| 6 | status_reason | text | Có | - | - | (Cần cập nhật) | - |
| 7 | new_address | string | Có | - | - | (Cần cập nhật) | - |
| 8 | new_province | string | Có | - | - | (Cần cập nhật) | - |
| 9 | new_ward | string | Có | - | - | (Cần cập nhật) | - |
| 10 | new_map_url | string | Có | - | - | (Cần cập nhật) | - |
| 11 | reviewed_at | timestamp | Có | - | - | (Cần cập nhật) | - |
| 12 | created_at | timestamp | Có | - | - | (Cần cập nhật) | - |
| 13 | updated_at | timestamp | Có | - | - | (Cần cập nhật) | - |

### 3. Khóa chính, khóa ngoại, index
- PK: id
- FK: venue_cluster_id -> venue_clusters.id, requested_by -> users.id, reviewed_by -> users.id

### 4. Quan hệ với bảng khác
(Chưa có)

### 5. Ví dụ bản ghi
```json
{}
```

---
