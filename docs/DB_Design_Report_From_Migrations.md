# Báo Cáo Thiết Kế Database Dự Án SportGo

Báo cáo này được tự động trích xuất và tổng hợp từ các file migration hiện tại của dự án. Không bao gồm các giả định ngoài code.

==================================================
## PHẦN 1. TỔNG HỢP CÁC BẢNG
==================================================

| STT | Tên bảng | Module | Tác dụng chính | Mô tả | Các liên kết chính |
|---|---|---|---|---|---|
| 1 | users | Auth/RBAC | Lưu thông tin người dùng | Lưu tài khoản đăng nhập, trạng thái, và profile cơ bản | roles (user_roles), bookings (customer_id), audit_logs (actor_id) |
| 2 | roles | Auth/RBAC | Lưu các nhóm quyền | Lưu mã role để phân quyền (admin, venue_owner, customer...) | users (user_roles), permissions (role_permissions) |
| 3 | user_roles | Auth/RBAC | Gán role cho user | Cầu nối n-n giữa users và roles, có hỗ trợ scope theo system/venue | users.id, roles.id |
| 4 | permissions | Auth/RBAC | Lưu danh sách quyền | Lưu các quyền chi tiết (vd: booking.manage) để check logic | roles (role_permissions) |
| 5 | role_permissions | Auth/RBAC | Gán quyền cho role | Cầu nối n-n giữa roles và permissions | roles.id, permissions.id |
| 6 | user_permission_revokes | Auth/RBAC | Thu hồi quyền của user | Lưu các quyền bị thu hồi cụ thể của 1 user dù role có cấp | users.id, permissions.id |
| 7 | personal_access_tokens | Auth/RBAC | Lưu token đăng nhập | Bảng chuẩn của Laravel Sanctum lưu access token | users (morphs) |
| 8 | venue_clusters | Venue | Lưu cụm sân | Lưu thông tin 1 cơ sở sân bãi (địa chỉ, tọa độ, chủ sân) | users.owner_id, venue_courts.venue_cluster_id, bookings.venue_cluster_id |
| 9 | court_types | Venue | Lưu loại sân thể thao | Quản lý loại môn/sân (vd: sân cầu lông, sân bóng đá 7 người) | court_types.parent_id, venue_courts.court_type_id |
| 10 | venue_courts | Venue | Lưu sân con thực tế | Các sân nhỏ bên trong 1 cụm sân để khách đặt | venue_clusters.id, court_types.id, bookings.venue_court_id |
| 11 | venue_staff_assignments | Venue | Phân công nhân viên | Phân công nhân viên quản lý cụm sân hoặc loại sân cụ thể | users.id, venue_clusters.id, court_types.id |
| 12 | venue_court_approval_requests | Venue | Xin duyệt tạo sân con | Lưu yêu cầu duyệt tạo sân con mới của chủ sân | users.id, venue_clusters.id, court_types.id |
| 13 | favorite_venues | Venue | Sân yêu thích | Lưu danh sách cụm sân yêu thích của khách | users.id, venue_clusters.id |
| 14 | booking_configs | Booking | Cấu hình đặt sân | Cấu hình quy định đặt sân (thời gian tối thiểu, tiền cọc) cho cụm sân | venue_clusters.id |
| 15 | bookings | Booking | Đơn đặt sân | Quản lý lịch đặt sân, giờ chơi, thanh toán, trạng thái | users.customer_id, venue_courts.id, venue_clusters.id |
| 16 | price_slots | Booking | Bảng giá theo khung giờ | Lưu giá tiền theo khung giờ của loại sân trong cụm | venue_clusters.id, court_types.id |
| 17 | holiday_prices | Booking | Bảng giá ngày lễ | Lưu giá đặc biệt áp dụng cho ngày lễ/sự kiện | venue_clusters.id, court_types.id |
| 18 | slot_locks | Booking | Khóa khung giờ | Giữ chỗ hoặc khóa khung giờ không cho đặt sân | venue_courts.id, venue_clusters.id, bookings.id |
| 19 | payments | Payment | Thanh toán | Quản lý giao dịch thanh toán của booking | bookings.id, system_bank_accounts.id |
| 20 | payment_logs | Payment | Log giao dịch | Log chi tiết request/response từ cổng thanh toán | payments.id |
| 21 | refunds | Payment | Hoàn tiền | Quản lý yêu cầu hoàn tiền cho thanh toán bị hủy | payments.id, users.processed_by |
| 22 | system_bank_accounts | Payment | Tài khoản ngân hàng | Lưu thông tin TKNH hệ thống dùng để nhận thanh toán | payments.system_bank_account_id |
| 23 | owner_wallets | Payment | Ví chủ sân | Quản lý số dư, tiền thu hộ của chủ sân | users.owner_id |
| 24 | owner_wallet_ledgers | Payment | Sổ quỹ ví chủ sân | Ghi nhận biến động số dư chi tiết của ví chủ sân | owner_wallets.id, users.id, bookings.id |
| 25 | platform_fee_tiers | Payment | Bậc phí nền tảng | Quản lý các gói thu phí nền tảng áp dụng cho chủ sân | venue_platform_fee_ledgers.tier_id |
| 26 | venue_platform_fee_ledgers | Payment | Công nợ phí nền tảng | Quản lý lịch sử và trạng thái đóng phí nền tảng của cụm sân | venue_clusters.id, platform_fee_tiers.id |
| 27 | community_posts | Community | Bài đăng cộng đồng | Người chơi đăng bài thảo luận tự do | users.author_id |
| 28 | community_post_comments | Community | Bình luận cộng đồng | Bình luận trong các bài đăng cộng đồng | community_posts.id, users.user_id |
| 29 | community_post_likes | Community | Thích bài viết | Lượt thích bài đăng cộng đồng | community_posts.id, users.user_id |
| 30 | venue_posts | Community | Bài đăng chủ sân | Chủ sân đăng bài quảng bá, thông báo | venue_clusters.id, users.author_id |
| 31 | player_posts | Community | Bài tìm đối thủ/đội | Khách tìm kèo chơi chung, chia sẻ chi phí | bookings.id, users.author_id |
| 32 | player_post_participants | Community | Xin tham gia kèo | Khách xin tham gia vào bài tìm đối/đội | player_posts.id, users.user_id |
| 33 | hashtags | Community | Hashtag chung | Lưu các hashtag | post_hashtags.hashtag_id |
| 34 | post_hashtags | Community | Gắn hashtag vào bài | Liên kết hashtag với các loại bài viết | hashtags.id (logical với bài) |
| 35 | system_posts | Community | Bài viết hệ thống | Admin đăng thông báo, tin tức hệ thống | users.author_id |
| 36 | player_preferences | Player | Hồ sơ người chơi | Lưu thông tin đánh giá trung bình của người chơi | users.user_id |
| 37 | player_preferred_court_types | Player | Môn thể thao yêu thích | Người chơi chọn loại môn thể thao quan tâm | users.user_id, court_types.id |
| 38 | player_ratings | Player | Đánh giá người chơi | Đánh giá trình độ/thái độ giữa những người chơi với nhau | users.rater_id, users.rated_user_id, player_posts.id |
| 39 | conversations | Chat | Cuộc hội thoại | Quản lý phòng chat (direct, post, venue) | users.created_by |
| 40 | conversation_participants | Chat | Thành viên chat | Thành viên tham gia vào cuộc hội thoại | conversations.id, users.user_id |
| 41 | messages | Chat | Tin nhắn | Nội dung tin nhắn trong hội thoại | conversations.id, users.sender_id |
| 42 | banners | System | Quản lý banner | Banner quảng cáo, hiển thị trang chủ | users.created_by |
| 43 | media | System | Quản lý file đính kèm | Quản lý tập tin, hình ảnh đa phương tiện (polymorphic) | Liên kết logic đa hình |
| 44 | system_policies | System | Chính sách hệ thống | Điều khoản, chính sách (bảo mật, hoàn tiền, v.v.) | users.created_by |
| 45 | user_policy_acceptances | System | Chấp nhận chính sách | Ghi nhận user đã đồng ý phiên bản chính sách | users.id, system_policies.id |
| 46 | moderation_configs | System | Cấu hình kiểm duyệt | Cấu hình hệ thống (key-value) | users.updated_by |
| 47 | verification_codes | System | Mã xác thực | OTP dùng cho email/sms đăng ký, quên mật khẩu | users.user_id |
| 48 | partner_applications | System | Đơn đăng ký đối tác | Đơn xin làm chủ sân gửi cho admin duyệt | users.user_id |
| 49 | partner_application_courts | System | Môn thể thao đăng ký | Loại sân kinh doanh dự kiến của đơn đăng ký đối tác | partner_applications.id, court_types.id |
| 50 | audit_logs | System/Log | Lịch sử thao tác | Ghi nhận hành động nhạy cảm trong hệ thống | users.actor_id |
| 51 | reports | System/Report| Báo cáo vi phạm | Quản lý báo cáo xấu, spam | users.reporter_id |
| 52 | complaints | System/Report| Khiếu nại | Khiếu nại về sân bãi, dịch vụ hoặc booking | users.customer_id, bookings.id |
| 53 | reviews | System/Report| Đánh giá cụm sân | Khách đánh giá sau khi hoàn thành booking | bookings.id, venue_clusters.id |
| 54 | notifications | System | Thông báo | Lưu thông báo gửi cho user | users.user_id |
| 55 | password_reset_tokens | Laravel | Đặt lại mật khẩu | Bảng chuẩn của Laravel cho reset password (email token) | Không FK |
| 56 | sessions | Laravel | Lưu session | Bảng quản lý session user đăng nhập | users.id |
| 57 | cache | Laravel | Lưu cache | Bảng cache database driver của Laravel | Không FK |
| 58 | cache_locks | Laravel | Khóa cache | Quản lý lock của cache | Không FK |
| 59 | jobs | Laravel | Hàng đợi công việc | Quản lý background jobs (Queue) | Không FK |
| 60 | job_batches | Laravel | Lô công việc | Quản lý batch jobs | Không FK |
| 61 | failed_jobs | Laravel | Job thất bại | Lưu các queue job chạy lỗi | Không FK |
| 62 | booking_items | Booking | Chi tiết sân/khung giờ trong booking | Lưu từng sân con và khung giờ cụ thể trong một booking (hỗ trợ đặt nhiều sân/slot) | bookings.id, venue_courts.id |
| 63 | owner_bank_accounts | Payment | Tài khoản nhận tiền chủ sân | Lưu TKNH của chủ sân dùng nhận tiền rút/đối soát | users.owner_id, partner_applications.id |
| 64 | owner_withdrawal_requests | Payment | Yêu cầu rút tiền chủ sân | Quản lý yêu cầu rút tiền từ ví chủ sân | users.owner_id, owner_wallets.id, owner_bank_accounts.id |
| 65 | internal_receipts | Payment | Phiếu thu/chi nội bộ | Phiếu nội bộ cho phí nền tảng, rút tiền, hoàn tiền | users.issued_to_user_id, users.issued_by |
| 66 | policy_action_bindings | Policy | Liên kết chính sách với action | Map chính sách hệ thống với module/action nghiệp vụ | system_policies.id |
| 67 | policy_rules | Policy | Luật chính sách hệ thống | Lưu rule có cấu trúc để backend evaluate | system_policies.id |
| 68 | venue_policy_rules | Policy | Luật chính sách riêng sân | Lưu rule riêng của sân khi chính sách cho phép override | venue_clusters.id, policy_rules.id |
| 69 | policy_evaluation_logs | Policy | Log áp dụng chính sách | Ghi nhận mỗi lần hệ thống evaluate rule | system_policies.id, policy_rules.id, venue_policy_rules.id |
| 70 | ai_conversations | AI | Cuộc trò chuyện AI | Lưu lịch sử trò chuyện AI của user | users.user_id |
| 71 | ai_messages | AI | Tin nhắn AI | Lưu message user/assistant/system trong cuộc trò chuyện AI | ai_conversations.id |
| 72 | ai_feedbacks | AI | Đánh giá AI | Lưu feedback của user cho câu trả lời AI | ai_messages.id, users.user_id |
| 73 | user_wallets | Payment | Ví người dùng | Quản lý ví nội bộ của user (thanh toán, nhận hoàn tiền) | users.user_id |
| 74 | user_wallet_ledgers | Payment | Sổ quỹ ví người dùng | Ghi nhận biến động số dư ví user | user_wallets.id |
| 75 | user_payout_accounts | Payment | Tài khoản nhận tiền user | TKNH user dùng nhận tiền khi rút ví hoặc refund | users.user_id |
| 76 | user_withdrawal_requests | Payment | Yêu cầu rút tiền user | Quản lý yêu cầu rút tiền từ ví user | user_wallets.id, user_payout_accounts.id |
| 77 | vouchers | Voucher | Mã giảm giá | Lưu voucher hệ thống và voucher sân | users.created_by |
| 78 | voucher_scopes | Voucher | Phạm vi voucher | Giới hạn phạm vi áp dụng voucher (cụm sân, loại sân, loại booking) | vouchers.id |
| 79 | voucher_usages | Voucher | Lịch sử dùng voucher | Ghi nhận voucher đã áp dụng cho booking/payment nào | vouchers.id, users.user_id, bookings.id |
| 80 | backup_jobs | System | Job sao lưu dữ liệu | Lưu metadata và trạng thái các lần backup database | users.created_by |
| 81 | policy_rule_templates | Policy | Mẫu cấu hình rule | Danh mục template rule để admin tạo/cấu hình đúng loại chính sách và action code | logical: system_policies.policy_type, policy_rules.rule_code |
| 82 | policy_override_constraints | Policy | Ràng buộc override chính sách sân | Giới hạn owner không được override rule hệ thống vượt khung cho phép | system_policies.id, policy_rules.id |
| 83 | policy_status_histories | Policy | Lịch sử trạng thái chính sách | Ghi nhận mỗi lần chính sách hệ thống đổi trạng thái/version | system_policies.id, users.changed_by |
| 84 | refund_status_histories | Payment | Lịch sử trạng thái refund | Ghi nhận từng bước xử lý hoàn tiền (owner confirm, admin confirm, gateway) | refunds.id, users.changed_by |
| 85 | partner_application_documents | Partner | Tài liệu hồ sơ đối tác | File đính kèm hồ sơ: ảnh sân, CCCD, giấy phép, chứng từ ngân hàng | partner_applications.id, media.id, users.reviewed_by |
| 86 | partner_application_status_histories | Partner | Lịch sử trạng thái hồ sơ đối tác | Ghi nhận mỗi lần hồ sơ đối tác đổi trạng thái | partner_applications.id, users.changed_by |
| 87 | document_templates | Document | Template văn bản DOCX | Lưu template biểu mẫu theo loại và version, có render engine | users.uploaded_by, document_templates.replaced_template_id |
| 88 | generated_documents | Document | Văn bản đã sinh | Văn bản sinh từ template, có snapshot render_data và file path | document_templates.id, users.generated_by, media.id |
| 89 | generated_document_signatures | Document | Chữ ký văn bản | Chữ ký/xác nhận của owner và SportGo trên văn bản đã sinh | generated_documents.id, users.signer_user_id, media.signature_media_id |
| 90 | partner_contracts | Contract | Hợp đồng đối tác | Hợp đồng giữa SportGo và chủ sân, link hồ sơ và văn bản đã ký | partner_applications.id, users.owner_id, venue_clusters.id, generated_documents.id |
| 91 | partner_termination_requests | Termination | Yêu cầu chấm dứt hợp tác | Yêu cầu chấm dứt hợp tác: hai bên đồng ý hoặc đơn phương | partner_contracts.id, users.owner_id, venue_clusters.id |
| 92 | partner_termination_documents | Termination | Văn bản chấm dứt | Biên bản thanh lý, công văn đơn phương, đơn chấm dứt | partner_termination_requests.id, generated_documents.id, media.id |
| 93 | partner_termination_status_histories | Termination | Lịch sử trạng thái chấm dứt | Ghi nhận từng bước xử lý yêu cầu chấm dứt hợp tác | partner_termination_requests.id, users.changed_by |
| 94 | partner_settlements | Settlement | Quyết toán công nợ | Kết quả quyết toán khi chấm dứt hợp tác: payable vs receivable | partner_termination_requests.id, partner_contracts.id, users.owner_id |
| 95 | partner_settlement_items | Settlement | Chi tiết quyết toán | Từng dòng cộng/trừ trong biên bản quyết toán | partner_settlements.id |
| 96 | venue_access_restrictions | Owner Restriction | Giới hạn quyền owner | Khóa/giới hạn quyền chủ sân trên cụm sân: limited hoặc blocked | venue_clusters.id, users.created_by |

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
| 1 | id | char(36) | Không | - | PK | UUID định danh user | 10000000-0000-0000-0000-000000000001 |
| 2 | username | varchar(50) | Không | - | Unique | Tên tài khoản dùng để đăng nhập | john_doe |
| 3 | full_name | varchar(255) | Không | - | - | Họ tên hiển thị | John Doe |
| 4 | phone | varchar(20) | Có | null | Unique | Số điện thoại chính | 0901234567 |
| 5 | email | varchar(255) | Có | null | Unique | Email phụ dùng đăng nhập/reset pass | john@example.com |
| 6 | google_id | varchar(255) | Có | null | Unique | ID đăng nhập qua Google | 10101010101 |
| 7 | email_verified_at | timestamp | Có | null | - | Thời điểm xác thực email | 2026-06-15 18:00:00 |
| 8 | phone_verified_at | timestamp | Có | null | - | Thời điểm xác thực phone | null |
| 9 | password | varchar(255) | Không | - | - | Mật khẩu đã hash | $2y$10$... |
| 10 | avatar_url | varchar(500) | Có | null | - | Đường dẫn avatar hiện tại | /storage/avatar.jpg |
| 11 | bio | text | Có | null | - | Mô tả cá nhân do user tự nhập | Yêu thể thao, tìm kèo thứ 7 |
| 12 | status | enum | Không | pending_verify | Index | Trạng thái: pending_verify, active, locked, deactivated | active |
| 13 | verification_channel | enum | Không | email | - | Kênh nhận mã xác thực (email/sms) | email |
| 14 | lock_type | enum | Có | null | - | Kiểu khóa (temporary, permanent, auto) | null |
| 15 | status_reason | text | Có | null | - | Lý do khóa/hủy tài khoản | null |
| 16 | locked_at | timestamp | Có | null | - | Thời điểm bị khóa | null |
| 17 | locked_until | timestamp | Có | null | Index | Thời điểm hết khóa tạm thời | null |
| 18 | locked_by | char(36) | Có | null | FK | Admin thực hiện khóa tài khoản | null |
| 19 | remember_token | varchar(100) | Có | null | - | Token remember me Laravel | abcxyz... |

### 3. Khóa chính, khóa ngoại, index
- PK: id
- Unique: username, phone, email, google_id
- FK: locked_by -> users.id (on delete set null)
- Index: status, locked_until

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

## Tên bảng: roles

### 1. Mục đích bảng
Lưu trữ danh mục các nhóm quyền (vai trò) dùng để phân quyền (RBAC) cho users.

### 2. Danh sách trường

| STT | Tên trường | Kiểu dữ liệu | Null | Default | Khóa/Ràng buộc | Mô tả tác dụng trường | Ví dụ |
|---|---|---|---|---|---|---|---|
| 1 | id | bigint | Không | auto | PK | ID tự tăng | 1 |
| 2 | name | varchar(50) | Không | - | Unique | Mã role duy nhất dùng trong code | venue_owner |
| 3 | display_name | varchar(100) | Không | - | - | Tên role dễ đọc hiển thị UI | Chủ sân |
| 4 | description | text | Có | null | - | Mô tả quyền hạn của role | Quản lý cụm sân của mình |
| 5 | is_system | boolean | Không | false | Index | Là role hệ thống mặc định, không xóa được | 1 |

### 3. Khóa chính, khóa ngoại, index
- PK: id
- Unique: name
- Index: is_system

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

## Tên bảng: user_roles

### 1. Mục đích bảng
Bảng trung gian n-n kết nối users và roles. Đặc biệt hỗ trợ phân quyền theo phạm vi (scope) để 1 user có thể làm chủ sân A nhưng không có quyền ở sân B.

### 2. Danh sách trường

| STT | Tên trường | Kiểu dữ liệu | Null | Default | Khóa/Ràng buộc | Mô tả tác dụng trường | Ví dụ |
|---|---|---|---|---|---|---|---|
| 1 | id | bigint | Không | auto | PK | ID tự tăng | 1 |
| 2 | user_id | char(36) | Không | - | FK | ID người dùng | 10000000-... |
| 3 | role_id | bigint | Không | - | FK | ID role được gán | 2 |
| 4 | scope_type | enum | Không | system | Index | Phạm vi (system hoặc venue) | venue |
| 5 | scope_id | char(36) | Không | 0000... | Index | ID của cụm sân (nếu scope là venue) | aabbccdd-... |
| 6 | granted_by | char(36) | Có | null | FK | Người gán quyền | null |

### 3. Khóa chính, khóa ngoại, index
- PK: id
- Unique: user_id, role_id, scope_type, scope_id (user_roles_scope_unique)
- FK: user_id -> users.id (cascade), role_id -> roles.id (cascade), granted_by -> users.id (set null)
- Index: scope_type, scope_id

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

## Tên bảng: permissions

### 1. Mục đích bảng
Lưu trữ danh sách các quyền cụ thể, chi tiết dùng để check logic trong code.

### 2. Danh sách trường

| STT | Tên trường | Kiểu dữ liệu | Null | Default | Khóa/Ràng buộc | Mô tả tác dụng trường | Ví dụ |
|---|---|---|---|---|---|---|---|
| 1 | id | bigint | Không | auto | PK | ID tự tăng | 1 |
| 2 | code | varchar(100) | Không | - | Unique | Mã quyền duy nhất check trong code | booking.manage |
| 3 | name | varchar(255) | Không | - | - | Tên quyền hiển thị | Quản lý đặt sân |
| 4 | group_name | varchar(50) | Không | - | Index | Nhóm quyền để UI gom nhóm | booking |

### 3. Khóa chính, khóa ngoại, index
- PK: id
- Unique: code
- Index: group_name

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

## Tên bảng: role_permissions

### 1. Mục đích bảng
Bảng trung gian n-n kết nối roles và permissions, định nghĩa 1 role có những quyền chi tiết nào.

### 2. Danh sách trường

| STT | Tên trường | Kiểu dữ liệu | Null | Default | Khóa/Ràng buộc | Mô tả tác dụng trường | Ví dụ |
|---|---|---|---|---|---|---|---|
| 1 | role_id | bigint | Không | - | PK, FK | ID của role | 1 |
| 2 | permission_id | bigint | Không | - | PK, FK | ID của quyền | 10 |

### 3. Khóa chính, khóa ngoại, index
- PK: (role_id, permission_id)
- FK: role_id -> roles.id (cascade), permission_id -> permissions.id (cascade)

### 4. Quan hệ với bảng khác
- Cầu nối role và permission.

### 5. Ví dụ bản ghi
```json
{
  "role_id": 1,
  "permission_id": 10
}
```

## Tên bảng: user_permission_revokes

### 1. Mục đích bảng
Bảng quản lý việc "rút" một quyền cụ thể của 1 user nhất định, kể cả khi role của họ có cấp quyền đó. Hỗ trợ scope (phạm vi).

### 2. Danh sách trường

| STT | Tên trường | Kiểu dữ liệu | Null | Default | Khóa/Ràng buộc | Mô tả tác dụng trường | Ví dụ |
|---|---|---|---|---|---|---|---|
| 1 | id | bigint | Không | auto | PK | ID tự tăng | 1 |
| 2 | user_id | char(36) | Không | - | FK | User bị thu hồi quyền | 10000000-... |
| 3 | permission_id | bigint | Không | - | FK | Quyền bị thu hồi | 5 |
| 4 | scope_type | enum | Không | system | Index | Phạm vi (system hoặc venue) | venue |
| 5 | scope_id | char(36) | Không | 0000... | Index | ID phạm vi thu hồi | aabbccdd-... |
| 6 | revoked_by | char(36) | Có | null | FK | Người thực hiện thu hồi | null |
| 7 | reason | varchar(255) | Có | null | - | Lý do thu hồi quyền | Vi phạm nội quy |

### 3. Khóa chính, khóa ngoại, index
- PK: id
- Unique: user_id, permission_id, scope_type, scope_id (user_permission_revokes_scope_unique)
- FK: user_id -> users.id, permission_id -> permissions.id, revoked_by -> users.id
- Index: scope_type, scope_id

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

## Tên bảng: personal_access_tokens

### 1. Mục đích bảng
Bảng chuẩn của gói Laravel Sanctum dùng để lưu trữ và xác thực token API của users.

### 2. Danh sách trường

| STT | Tên trường | Kiểu dữ liệu | Null | Default | Khóa/Ràng buộc | Mô tả tác dụng trường | Ví dụ |
|---|---|---|---|---|---|---|---|
| 1 | id | bigint | Không | auto | PK | ID tự tăng | 1 |
| 2 | tokenable_type | varchar(255) | Không | - | Index | Class model (User) | App\Models\User |
| 3 | tokenable_id | char(36) | Không | - | Index | ID của User | 10000000-... |
| 4 | name | text | Không | - | - | Tên token | android-app |
| 5 | token | varchar(64) | Không | - | Unique | Chuỗi token đã hash | abc...xyz |
| 6 | abilities | text | Có | null | - | Phạm vi token | ["*"] |
| 7 | last_used_at | timestamp | Có | null | - | Thời điểm dùng cuối | 2026-06-15 |
| 8 | expires_at | timestamp | Có | null | Index | Hạn chót token | null |

### 3. Khóa chính, khóa ngoại, index
- PK: id
- Unique: token
- Index: tokenable_type, tokenable_id, expires_at

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
### MODULE: VENUE

## Tên bảng: venue_clusters

### 1. Mục đích bảng
Lưu trữ thông tin cơ sở kinh doanh (cụm sân) bao gồm tên, địa chỉ, chủ sở hữu, đánh giá và trạng thái duyệt.

### 2. Danh sách trường

| STT | Tên trường | Kiểu dữ liệu | Null | Default | Khóa/Ràng buộc | Mô tả tác dụng trường | Ví dụ |
|---|---|---|---|---|---|---|---|
| 1 | id | char(36) | Không | - | PK | ID cụm sân | 20000000-... |
| 2 | owner_id | char(36) | Không | - | FK | Chủ sân sở hữu cụm này | 10000000-... |
| 3 | name | varchar(255) | Không | - | Index | Tên cụm sân hiển thị cho user | Sân cầu lông 247 |
| 4 | slug | varchar(255) | Không | - | Unique | Định danh URL/SEO | san-cau-long-247 |
| 5 | description | text | Có | null | - | Mô tả cụm sân, tiện ích | Sân mới xây... |
| 6 | phone_contact | varchar(20) | Có | null | - | Số điện thoại liên hệ | 0988776655 |
| 7 | address | text | Không | - | - | Địa chỉ thực tế | Số 1 đường X |
| 8 | map_url | varchar(1000)| Có | null | - | Link Google Maps | https://goo.gl/... |
| 9 | latitude | decimal(10,7)| Không | - | Index | Vĩ độ để tìm sân gần đây | 21.028511 |
| 10 | longitude | decimal(10,7)| Không | - | Index | Kinh độ để tìm sân gần đây | 105.804817 |
| 11 | amenities | json | Có | null | - | Danh sách tiện ích (wifi, bãi xe...) | ["wifi", "parking"] |
| 12 | status | enum | Không | pending | Index | Trạng thái (pending, active, locked)| active |
| 13 | status_reason | text | Có | null | - | Lý do khóa cụm sân | null |
| 14 | locked_at | timestamp | Có | null | - | Thời điểm bị khóa | null |
| 15 | locked_until | timestamp | Có | null | Index | Thời điểm hết khóa tạm thời | null |
| 16 | locked_by | char(36) | Có | null | FK | Admin khóa cụm sân | null |
| 17 | rating_avg | decimal(3,2) | Không | 0.00 | Index | Điểm trung bình sân | 4.80 |
| 18 | rating_count | unsigned int | Không | 0 | - | Số lượt review | 150 |

### 3. Khóa chính, khóa ngoại, index
- PK: id
- Unique: slug
- FK: owner_id -> users.id (restrict), locked_by -> users.id (set null)
- Index: name, status, rating_avg, locked_until, [latitude, longitude], [status, rating_avg]

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

## Tên bảng: court_types

### 1. Mục đích bảng
Lưu trữ danh mục các môn thể thao hoặc loại sân. Dùng cho cả hệ thống quản lý môn thể thao.

### 2. Danh sách trường

| STT | Tên trường | Kiểu dữ liệu | Null | Default | Khóa/Ràng buộc | Mô tả tác dụng trường | Ví dụ |
|---|---|---|---|---|---|---|---|
| 1 | id | bigint | Không | auto | PK | ID tự tăng | 1 |
| 2 | parent_id | bigint | Có | null | FK | Loại sân cha (gom nhóm bộ môn) | null |
| 3 | name | varchar(100) | Không | - | Unique | Tên môn / loại sân | Cầu lông |
| 4 | description | text | Có | null | - | Mô tả loại sân | Sân tiêu chuẩn |
| 5 | player_count | unsigned int | Không | 0 | - | Số người chơi tham khảo | 4 |
| 6 | is_active | boolean | Không | true | Index | Còn áp dụng không | 1 |
| 7 | deleted_at | timestamp | Có | null | - | Soft delete | null |

### 3. Khóa chính, khóa ngoại, index
- PK: id
- Unique: name
- FK: parent_id -> court_types.id (set null)
- Index: is_active

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

## Tên bảng: venue_courts

### 1. Mục đích bảng
Lưu trữ thông tin các "sân con" nằm bên trong một cụm sân. Khách hàng thực tế đặt lịch trên các sân con này.

### 2. Danh sách trường

| STT | Tên trường | Kiểu dữ liệu | Null | Default | Khóa/Ràng buộc | Mô tả tác dụng trường | Ví dụ |
|---|---|---|---|---|---|---|---|
| 1 | id | char(36) | Không | - | PK | ID sân con | 3000... |
| 2 | venue_cluster_id | char(36) | Không | - | FK | ID cụm sân chứa sân này | 2000... |
| 3 | court_type_id | bigint | Không | - | FK | ID môn thể thao | 1 |
| 4 | name | varchar(100) | Không | - | Index | Tên gọi của sân con | Sân số 1 |
| 5 | status | enum | Không | active | Index | Trạng thái (active, maintenance...) | active |
| 6 | sort_order | int | Không | 0 | - | Thứ tự hiển thị UI | 1 |
| 7 | deleted_at | timestamp | Có | null | - | Soft delete | null |

### 3. Khóa chính, khóa ngoại, index
- PK: id
- FK: venue_cluster_id -> venue_clusters.id (cascade), court_type_id -> court_types.id (restrict)
- Index: name, status, [venue_cluster_id, status]

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

## Tên bảng: venue_staff_assignments

### 1. Mục đích bảng
Quản lý phân công nhân viên phục vụ, quản lý cho 1 cụm sân, hỗ trợ phân công theo từng loại sân nhỏ (scope).

### 2. Danh sách trường

| STT | Tên trường | Kiểu dữ liệu | Null | Default | Khóa/Ràng buộc | Mô tả tác dụng trường | Ví dụ |
|---|---|---|---|---|---|---|---|
| 1 | id | bigint | Không | auto | PK | ID tự tăng | 1 |
| 2 | user_id | char(36) | Không | - | FK | ID nhân viên | 1000... |
| 3 | venue_cluster_id | char(36) | Không | - | FK | ID cụm sân làm việc | 2000... |
| 4 | scope_type | enum | Không | all_cluster| Index | Quản lý cả cụm hay 1 loại sân | all_cluster |
| 5 | court_type_id | bigint | Có | null | FK | Nếu quản lý loại sân thì điền ID môn | null |
| 6 | scope_key | varchar(50) | Không | all | Index | Key đặc biệt để phân biệt | all |
| 7 | assigned_by | char(36) | Có | null | FK | Admin/chủ sân giao việc | null |
| 8 | status | enum | Không | active | Index | Trạng thái (active/inactive) | active |

### 3. Khóa chính, khóa ngoại, index
- PK: id
- Unique: user_id, venue_cluster_id, scope_key
- FK: user_id, venue_cluster_id, court_type_id, assigned_by
- Index: scope_type, scope_key, status

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

## Tên bảng: venue_court_approval_requests

### 1. Mục đích bảng
Khi chủ sân muốn tạo thêm sân con, họ gửi yêu cầu và admin duyệt trước khi sân hiện ra trên hệ thống.

### 2. Danh sách trường

| STT | Tên trường | Kiểu dữ liệu | Null | Default | Khóa/Ràng buộc | Mô tả tác dụng trường | Ví dụ |
|---|---|---|---|---|---|---|---|
| 1 | id | char(36) | Không | - | PK | ID yêu cầu duyệt | 4000... |
| 2 | venue_cluster_id | char(36) | Không | - | FK | Cụm sân muốn thêm | 2000... |
| 3 | court_type_id | bigint | Không | - | FK | Loại sân muốn thêm | 1 |
| 4 | name | varchar(100) | Không | - | - | Tên sân con dự kiến | Sân số 2 |
| 5 | status | enum | Không | pending | Index | Trạng thái duyệt (pending, approved) | pending |
| 6 | requested_by | char(36) | Không | - | FK | Chủ sân gửi yêu cầu | 1000... |
| 7 | reviewed_by | char(36) | Có | null | FK | Admin duyệt | null |
| 8 | status_reason | text | Có | null | - | Lý do từ chối | null |
| 9 | approved_venue_court_id| char(36) | Có | null | Index | ID sân con được sinh ra sau duyệt | null |
| 10 | reviewed_at | timestamp | Có | null | - | Thời điểm duyệt | null |

### 3. Khóa chính, khóa ngoại, index
- PK: id
- FK: venue_cluster_id, court_type_id, requested_by, reviewed_by
- Index: status, approved_venue_court_id

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

## Tên bảng: favorite_venues

### 1. Mục đích bảng
Lưu danh sách cụm sân yêu thích của người dùng.

### 2. Danh sách trường

| STT | Tên trường | Kiểu dữ liệu | Null | Default | Khóa/Ràng buộc | Mô tả tác dụng trường | Ví dụ |
|---|---|---|---|---|---|---|---|
| 1 | id | bigint | Không | auto | PK | ID tự tăng | 1 |
| 2 | user_id | char(36) | Không | - | FK | User yêu thích sân | 1000... |
| 3 | venue_cluster_id | char(36) | Không | - | FK | Cụm sân được yêu thích | 2000... |

### 3. Khóa chính, khóa ngoại, index
- PK: id
- Unique: user_id, venue_cluster_id
- FK: user_id -> users.id (cascade), venue_cluster_id -> venue_clusters.id (cascade)

### 4. Quan hệ với bảng khác
- Cầu nối 1-n giữa users và venue_clusters.

### 5. Ví dụ bản ghi
```json
{
  "user_id": "10000000-0000-0000-0000-000000000001",
  "venue_cluster_id": "20000000-0000-0000-0000-000000000001"
}
```

### MODULE: BOOKING

## Tên bảng: booking_configs

### 1. Mục đích bảng
Cấu hình linh hoạt cho từng cụm sân (tiền cọc, thời gian đặt tối thiểu, chính sách hoàn tiền).

### 2. Danh sách trường

| STT | Tên trường | Kiểu dữ liệu | Null | Default | Khóa/Ràng buộc | Mô tả tác dụng trường | Ví dụ |
|---|---|---|---|---|---|---|---|
| 1 | venue_cluster_id | char(36) | Không | - | PK, FK | ID cụm sân | 2000... |
| 2 | min_duration_minutes | unsigned int | Không | 30 | - | Thời gian đặt tối thiểu (phút) | 60 |
| 3 | max_duration_minutes | unsigned int | Có | null | - | Thời gian đặt tối đa (phút) | null |
| 4 | slot_hold_minutes | unsigned int | Không | 20 | - | Giữ chỗ trước khi thanh toán (phút) | 15 |
| 5 | reminder_before_minutes| unsigned int | Không | 30 | - | Gửi nhắc nhở trước giờ chơi (phút) | 30 |
| 6 | allow_full_payment | boolean | Không | true | - | Cho phép thanh toán 100% | 1 |
| 7 | allow_deposit | boolean | Không | true | - | Cho phép cọc | 1 |
| 8 | allow_no_prepay | boolean | Không | true | - | Cho phép không trả trước | 0 |
| 9 | auto_approve_full_payment| boolean| Không | false | - | Tự duyệt khi thanh toán đủ | 1 |
| 10 | deposit_percent | decimal(5,2)| Có | null | - | Phần trăm cọc | 30.00 |
| 11 | cancel_before_hours | unsigned int | Không | 0 | - | Số giờ tối thiểu báo hủy để hoàn | 24 |
| 12 | refund_percent | unsigned int | Không | 0 | - | Phần trăm hoàn tiền nếu hủy chuẩn | 100 |

### 3. Khóa chính, khóa ngoại, index
- PK: venue_cluster_id
- FK: venue_cluster_id -> venue_clusters.id (cascade)

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

## Tên bảng: bookings

### 1. Mục đích bảng
Lưu trữ toàn bộ thông tin đơn đặt sân (booking lẻ và cố định), ngày giờ chơi, tiền thanh toán.

### 2. Danh sách trường

| STT | Tên trường | Kiểu dữ liệu | Null | Default | Khóa/Ràng buộc | Mô tả tác dụng trường | Ví dụ |
|---|---|---|---|---|---|---|---|
| 1 | id | char(36) | Không | - | PK | ID booking | 5000... |
| 2 | booking_code | varchar(30) | Không | - | Unique | Mã booking dễ đọc (VD: BKG123) | BKG-ABC123X |
| 3 | customer_id | char(36) | Có | null | FK | Khách đặt online (null = walk-in) | 1000... |
| 4 | venue_court_id | char(36) | Không | - | FK | Sân con thực tế chơi | 3000... |
| 5 | requested_venue_court_id| char(36) | Có | null | FK | Sân con lúc khách yêu cầu đặt | 3000... |
| 6 | venue_cluster_id | char(36) | Không | - | Index | Cụm sân (denormalized để filter) | 2000... |
| 7 | booking_date | date | Không | - | Index | Ngày chơi | 2026-10-15 |
| 8 | start_time | time | Không | - | Index | Giờ bắt đầu | 18:00:00 |
| 9 | end_time | time | Không | - | Index | Giờ kết thúc | 20:00:00 |
| 10 | duration_minutes | unsigned int | Không | - | - | Thời lượng (phút) | 120 |
| 11 | total_price | decimal(12,2)| Không | 0.00 | - | Tổng tiền sân | 200000 |
| 12 | payment_option | enum | Không | no_prepay | - | Kiểu thanh toán (full, deposit...) | deposit |
| 13 | required_payment_amount| decimal(12,2)| Không | 0.00 | - | Tiền cần đóng ngay | 100000 |
| 14 | source | enum | Không | online | - | Nguồn đặt (online, counter) | online |
| 15 | booking_type | enum | Không | single | Index | Lẻ (single) hay cố định (recurring) | single |
| 16 | recurring_group_code| varchar(30) | Có | null | Index | Mã nhóm đơn cố định | null |
| 17 | recurrence_interval | unsigned int | Có | null | - | Khoảng lặp | null |
| 18 | status | enum | Không | pending_approval| Index | Trạng thái (confirmed, checked_in..) | confirmed |
| 19 | walk_in_name | varchar(255) | Có | null | - | Tên khách vãng lai | Khách vãng lai |
| 20 | walk_in_phone | varchar(20) | Có | null | - | SĐT khách vãng lai | 0911223344 |
| 21 | status_reason | text | Có | null | - | Lý do hủy/từ chối | Khách báo hủy |

*(Có thêm các trường phụ: cancelled_by, court_changed_by, reminder_sent_at)*

### 3. Khóa chính, khóa ngoại, index
- PK: id
- Unique: booking_code
- FK: customer_id (users.id), venue_court_id, requested_venue_court_id, cancelled_by, court_changed_by, created_by
- Index: Nhiều index gom nhóm (vd: venue_court_id + booking_date + start_time + end_time) để query trống lịch.

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

## Tên bảng: price_slots

### 1. Mục đích bảng
Lưu trữ bảng giá sân theo các khung giờ khác nhau của một cụm sân và loại môn.

### 2. Danh sách trường

| STT | Tên trường | Kiểu dữ liệu | Null | Default | Khóa/Ràng buộc | Mô tả tác dụng trường | Ví dụ |
|---|---|---|---|---|---|---|---|
| 1 | id | char(36) | Không | - | PK | ID bảng giá | 6000... |
| 2 | venue_cluster_id | char(36) | Không | - | FK | ID cụm sân | 2000... |
| 3 | court_type_id | bigint | Không | - | FK | ID môn thể thao | 1 |
| 4 | booking_type | enum | Không | all | Index | Áp dụng cho đơn lẻ hay cố định | all |
| 5 | start_time | time | Không | - | Index | Giờ bắt đầu khung giá | 17:00:00 |
| 6 | end_time | time | Không | - | Index | Giờ kết thúc khung giá | 22:00:00 |
| 7 | price | decimal(12,2)| Không | 0.00 | - | Giá mỗi giờ (hoặc slot) | 120000.00 |
| 8 | apply_to_days | json | Có | null | - | Ngày áp dụng (T2-CN) | [1, 2, 3, 4, 5] |
| 9 | is_active | boolean | Không | true | Index | Còn áp dụng không | 1 |

### 3. Khóa chính, khóa ngoại, index
- PK: id
- FK: venue_cluster_id, court_type_id
- Index: start_time, end_time, [venue_cluster_id, court_type_id, booking_type, is_active]

### 4. Quan hệ với bảng khác
- Thuộc cụm sân và loại sân, dùng để tính tiền khi khách đặt booking.

## Tên bảng: holiday_prices

### 1. Mục đích bảng
Ghi đè giá ở bảng price_slots vào các ngày lễ hoặc ngày đặc biệt.

### 2. Danh sách trường (Tóm tắt)
- **id, venue_cluster_id, court_type_id**: Định danh và FK
- **date_type**: (holiday, special_date)
- **holiday_date**: (date) Ngày nghỉ lễ cụ thể.
- **start_time, end_time, price**: Giá trong khung giờ lễ.
- **is_active**: Có áp dụng không.

## Tên bảng: slot_locks

### 1. Mục đích bảng
Quản lý việc khóa khung giờ (lock slot) do chủ sân tự block lịch hoặc block tạm thời khi user đang ở màn hình thanh toán.

### 2. Danh sách trường (Tóm tắt)
- **id, venue_cluster_id, venue_court_id**: Xác định sân bị khóa.
- **lock_scope**: (court, cluster) Khóa 1 sân con hay khóa nguyên cụm sân.
- **booking_date, start_time, end_time**: Thời gian bị khóa.
- **locked_by**: ID user/session giữ chỗ.
- **lock_type**: (auto, manual) Khóa hệ thống tự tạo khi chờ thanh toán, hoặc chủ sân tự tạo.
- **expires_at**: Thời điểm hết hạn giữ chỗ nếu là auto lock.

---
*(Sẽ tiếp tục nối thêm phần Payment và Community ở phần tiếp theo)*
### MODULE: PAYMENT & WALLET

## Tên bảng: payments

### 1. Mục đích bảng
Lưu trữ thông tin giao dịch thanh toán cho các booking.

### 2. Danh sách trường

| STT | Tên trường | Kiểu dữ liệu | Null | Default | Khóa/Ràng buộc | Mô tả tác dụng trường | Ví dụ |
|---|---|---|---|---|---|---|---|
| 1 | id | char(36) | Không | - | PK | ID thanh toán | 7000... |
| 2 | payment_code | varchar(50) | Không | - | Unique | Mã thanh toán nội bộ hệ thống | PAY-12345 |
| 3 | booking_id | char(36) | Không | - | FK | ID Booking thanh toán cho | 5000... |
| 4 | system_bank_account_id| char(36) | Có | null | FK | Tài khoản NH hệ thống nhận tiền | 8000... |
| 5 | amount | decimal(12,2)| Không | - | - | Số tiền thanh toán đợt này | 100000.00 |
| 6 | payment_kind | enum | Không | partial | - | Loại thanh toán (full, deposit, partial)| deposit |
| 7 | method | varchar(50) | Không | sepay | Index | Phương thức (sepay...) | sepay |
| 8 | gateway_txn_id | varchar(100) | Có | null | Unique | Mã GD từ cổng thanh toán trả về | SEPAY-999 |
| 9 | gateway_response | json | Có | null | - | Dữ liệu gốc từ gateway | {"status":"ok"} |
| 10| status | enum | Không | pending | Index | Trạng thái (pending, paid, failed, refunded) | paid |
| 11| paid_at | timestamp | Có | null | Index | Thời điểm thanh toán thành công | 2026-06-15 |

### 3. Khóa chính, khóa ngoại, index
- PK: id
- Unique: payment_code, gateway_txn_id
- FK: booking_id -> bookings.id, system_bank_account_id -> system_bank_accounts.id
- Index: method, status, paid_at, [booking_id, status]

### 4. Quan hệ với bảng khác
- 1 booking có thể có nhiều payments (cọc rồi thanh toán nốt).
- 1 payment thuộc về 1 system_bank_accounts.

## Tên bảng: payment_logs

### 1. Mục đích bảng
Lịch sử webhook, thay đổi trạng thái của cổng thanh toán.

### 2. Danh sách trường (Tóm tắt)
- **id, payment_id**: Xác định log của payment nào.
- **event_type**: Loại sự kiện (webhook_received, status_changed).
- **request_payload, response_payload**: Data JSON thô.
- **status_before, status_after**: Theo dõi đổi trạng thái.

## Tên bảng: refunds

### 1. Mục đích bảng
Quản lý yêu cầu hoàn tiền khi booking bị hủy sau khi đã thanh toán.

### 2. Danh sách trường (Tóm tắt)
- **id, payment_id, booking_id**: Liên kết về thanh toán gốc và booking.
- **amount**: Số tiền cần hoàn (tùy % theo cấu hình).
- **reason**: Lý do hoàn.
- **status**: Trạng thái (pending_confirmation, processing, completed, failed, rejected).
- **processed_by**: Admin xử lý thủ công (do không hoàn tự động).

## Tên bảng: system_bank_accounts

### 1. Mục đích bảng
Quản lý danh sách các tài khoản ngân hàng của hệ thống (dùng để tích hợp tạo mã QR thanh toán qua SePay).

### 2. Danh sách trường (Tóm tắt)
- **id, name**: Tên gọi gợi nhớ.
- **bank_name, bank_code, account_number, account_holder_name**: Thông tin TKNH thực tế.
- **status, is_default**: Trạng thái và TK mặc định dùng để tạo QR thanh toán.

## Tên bảng: owner_wallets

### 1. Mục đích bảng
Quản lý ví tiền của mỗi chủ sân. Tiền khách thanh toán vào TK hệ thống sẽ được cộng vào ví này (đóng vai trò như số dư thu hộ).

### 2. Danh sách trường (Tóm tắt)
- **id, owner_id**: Ví thuộc về user chủ sân.
- **available_balance**: Số dư có thể rút.
- **pending_withdrawal_balance**: Số dư đang treo do lệnh rút tiền.
- **total_earned**: Tổng tiền hệ thống đã thu hộ từ trước tới nay.
- **total_withdrawn**: Tổng tiền chủ sân đã rút ra thành công.

## Tên bảng: owner_wallet_ledgers

### 1. Mục đích bảng
Sổ phụ ghi chú từng biến động của ví chủ sân (cộng tiền do booking, trừ tiền do rút).

### 2. Danh sách trường (Tóm tắt)
- **id, owner_wallet_id, owner_id**: Liên kết ví.
- **venue_cluster_id, booking_id, payment_id**: Giao dịch sinh ra biến động.
- **type**: (credit, debit, hold, release).
- **amount, balance_before, balance_after**: Lưu lại số dư tại thời điểm đó (nguyên tắc kế toán kép).

## Tên bảng: platform_fee_tiers & venue_platform_fee_ledgers

### 1. Mục đích bảng
Quản lý các gói thu phí (SaaS) mà nền tảng thu của chủ sân dựa trên số lượng sân con, và sổ cái theo dõi tình trạng thanh toán gói phí của từng cụm sân theo tháng/năm.

### MODULE: COMMUNITY & POSTS

## Tên bảng: community_posts

### 1. Mục đích bảng
Lưu trữ bài đăng thảo luận tự do của người dùng trên trang cộng đồng.

### 2. Danh sách trường (Tóm tắt)
- **id, author_id**: Người đăng.
- **content**: Nội dung bài đăng.
- **status**: (pending_review, published, rejected, hidden) Kiểm duyệt.
- **view_count, like_count, comment_count**: Bộ đếm tương tác (denormalized).

## Tên bảng: community_post_comments & community_post_likes

### 1. Mục đích bảng
Lưu bình luận và lượt thích (Like) của bài đăng cộng đồng. Comment có `parent_id` để tạo thread reply.

## Tên bảng: venue_posts

### 1. Mục đích bảng
Lưu trữ các bài viết, thông báo, quảng bá do chủ sân đăng cho cụm sân của mình.

### 2. Danh sách trường (Tóm tắt)
- **id, venue_cluster_id, author_id**: Liên kết sân và người viết.
- **content, status**: Tương tự community_posts.

## Tên bảng: player_posts

### 1. Mục đích bảng
Bài đăng "Tìm kèo" hoặc "Ghép đội", bắt buộc phải gắn với một `booking_id` đã đặt thành công để tránh đăng bài ảo.

### 2. Danh sách trường (Tóm tắt)
- **id, booking_id, author_id**: Bài đăng gắn với booking nào.
- **title, description**: Tiêu đề và mô tả trình độ/yêu cầu.
- **needed_players, cost_per_player**: Số lượng cần thêm, giá chia mỗi người.
- **status**: (open, full, closed, cancelled).

## Tên bảng: player_post_participants

### 1. Mục đích bảng
Lưu những người dùng gửi yêu cầu tham gia vào "Bài tìm kèo" và trạng thái duyệt của chủ kèo.

### 2. Danh sách trường (Tóm tắt)
- **post_id, user_id**: Ai xin vào kèo nào.
- **status**: (pending, approved, rejected, cancelled).
- **message**: Tin nhắn chào hỏi khi xin vào.

## Tên bảng: player_ratings

### 1. Mục đích bảng
Lưu đánh giá (Rating) giữa người chơi với nhau sau khi tham gia kèo thành công, giúp xây dựng uy tín cá nhân.

### 2. Danh sách trường (Tóm tắt)
- **id, rater_id, rated_user_id**: Ai đánh giá ai.
- **post_id**: Đánh giá dựa trên kèo chơi chung nào.
- **rating, comment, tags**: Điểm và nhận xét (VD: "Chơi nhiệt tình", "Bùng kèo").

## Tên bảng: hashtags & post_hashtags

### 1. Mục đích bảng
Quản lý các hashtag gắn vào các bài đăng (cộng đồng, tìm kèo, v.v.). Liên kết `post_hashtags` là dạng hình thái đa hình logic (`post_type` và `post_id`).

### MODULE: CHAT

## Tên bảng: conversations

### 1. Mục đích bảng
Lưu trữ các phiên hội thoại (phòng chat).

### 2. Danh sách trường (Tóm tắt)
- **id, type**: Kiểu chat (direct cá nhân, player_post chat nhóm tìm kèo, venue_contact chat với chủ sân).
- **reference_type, reference_id**: Lưu ID đối tượng liên kết tới chat.
- **title**: Tiêu đề nhóm chat.

## Tên bảng: conversation_participants

### 1. Mục đích bảng
Lưu trữ danh sách những người dùng có trong 1 conversation.
- **conversation_id, user_id**
- **last_read_at**: Đánh dấu thời điểm đọc tin cuối để hiện Unread.

## Tên bảng: messages

### 1. Mục đích bảng
Lưu nội dung tin nhắn trong phòng chat.
- **id, conversation_id, sender_id**
- **content**
- **is_system**: Đánh dấu tin nhắn hệ thống (VD: "A đã tham gia nhóm").

---
*(Sẽ tiếp tục nối thêm phần System & Config ở phần tiếp theo)*
### MODULE: SYSTEM & REPORT

## Tên bảng: media

### 1. Mục đích bảng
Sử dụng mô hình đa hình (Polymorphic) để lưu trữ mọi file đính kèm (ảnh sân, avatar, file báo cáo) của hệ thống.

### 2. Danh sách trường (Tóm tắt)
- **id**: PK
- **mediable_type, mediable_id**: Polymorphic liên kết với model (ví dụ: `App\Models\VenueCluster`, ID: `2000...`).
- **collection**: Nhóm file (ví dụ: `avatar`, `gallery`).
- **file_name, file_path**: Tên và đường dẫn vật lý (S3/local).
- **mime_type, file_size**: Metadata.

## Tên bảng: banners

### 1. Mục đích bảng
Lưu thông tin banner quảng cáo/sự kiện để hiển thị linh động trên trang chủ hoặc ứng dụng.
- **image_path**: Link ảnh banner.
- **link_url**: Nơi chuyển hướng khi bấm.
- **position, sort_order**: Vị trí đặt và thứ tự.
- **is_active, starts_at, ends_at**: Kiểm soát thời gian chạy banner.

## Tên bảng: partner_applications

### 1. Mục đích bảng
Lưu hồ sơ người dùng gửi lên xin trở thành chủ sân.

### 2. Danh sách trường (Tóm tắt)
- **user_id**: Khách gửi đơn.
- **business_name, tax_code**: Thông tin kinh doanh.
- **venue_name, venue_address, venue_latitude, venue_longitude**: Thông tin cụm sân dự kiến tạo.
- **status**: (pending, reviewing, approved, rejected).
- **approved_venue_cluster_id**: Khi duyệt xong sẽ lưu ID cụm sân thật mới tạo.

## Tên bảng: partner_application_courts

### 1. Mục đích bảng
Lưu danh sách môn thể thao mà chủ sân đăng ký trong đơn xin làm đối tác.
- **partner_application_id, court_type_id**

## Tên bảng: system_policies & user_policy_acceptances

### 1. Mục đích bảng
- `system_policies`: Lưu trữ các điều khoản, chính sách hoạt động, có đánh version.
- `user_policy_acceptances`: Ghi nhận người dùng nào đã bấm "Đồng ý" với phiên bản chính sách nào (để phục vụ tính pháp lý, giải quyết khiếu nại).

## Tên bảng: verification_codes

### 1. Mục đích bảng
Lưu mã xác thực OTP dùng cho việc đăng ký, xác nhận số điện thoại, và quên mật khẩu.

### 2. Danh sách trường (Tóm tắt)
- **user_id**: Khóa ngoại (có thể null nếu chưa tạo tài khoản lúc đăng ký).
- **identifier**: (VD: "nguyenvana@gmail.com").
- **type**: Mục đích mã (register, reset_password...).
- **channel**: (email, sms).
- **code**: Chuỗi mã sinh ra.
- **expires_at**: Thời điểm hết hạn.

## Tên bảng: moderation_configs

### 1. Mục đích bảng
Lưu các cấu hình hệ thống dạng Key-Value (vd: tỷ lệ hoa hồng tối đa, giới hạn file đính kèm).
- **key**: Khóa chính (string).
- **value, value_type**: Giá trị lưu dạng text và kiểu dữ liệu gốc để parse.

## Tên bảng: audit_logs

### 1. Mục đích bảng
Lịch sử kiểm toán, ghi lại mọi thao tác quan trọng (thêm/sửa/xóa bảng nhạy cảm) của bất kỳ ai.

### 2. Danh sách trường (Tóm tắt)
- **actor_id**: Người thực hiện (admin/hệ thống).
- **action**: Mã thao tác (vd: `venue.locked`).
- **entity_type, entity_id**: Đối tượng bị thay đổi.
- **old_values, new_values**: JSON lưu thay đổi trước và sau.
- **ip_address, user_agent**: Thông tin thiết bị.

## Tên bảng: complaints

### 1. Mục đích bảng
Lưu khiếu nại của khách hàng đối với sân hoặc booking (VD: sân đóng cửa, phục vụ kém).
- **complaint_type**: (venue, system).
- **booking_id, venue_cluster_id**: Liên quan tới booking/sân nào.
- **customer_id, content**: Người khiếu nại và nội dung.
- **status**: Quá trình xử lý (open, processing, resolved).

## Tên bảng: reports

### 1. Mục đích bảng
Hệ thống Report nội dung xấu (spam, vi phạm) dành cho Community Posts, Player Posts hoặc bình luận.
- **reporter_id**: Người báo cáo.
- **reportable_type, reportable_id**: Đối tượng bị báo cáo.
- **reason**: Lý do vi phạm chuẩn mực.

## Tên bảng: reviews

### 1. Mục đích bảng
Người dùng đánh giá chất lượng của cụm sân sau khi hoàn thành một Booking.

### 2. Danh sách trường (Tóm tắt)
- **booking_id**: (Unique) Một booking chỉ được review 1 lần.
- **customer_id, venue_cluster_id**: Denormalized để query nhanh.
- **rating**: Điểm (1-5).
- **comment**: Nội dung khen/chê.
- **reply_content**: Chủ sân phản hồi.

## Tên bảng: notifications

### 1. Mục đích bảng
Gửi thông báo đẩy (Notification) vào trung tâm thông báo của user trên app/web.
- **user_id, type, title, body**.
- **reference_type, reference_id**: Trỏ tới màn hình cần chuyển hướng khi click.
- **is_read, read_at**: Đánh dấu đã đọc.

### MODULE: SYSTEM (LARAVEL DEFAULT)

## Các bảng hệ thống Laravel

Các bảng này được Laravel tự động sinh ra hoặc sử dụng cho core framework:

1. **password_reset_tokens**: Bảng mặc định hỗ trợ cơ chế Reset Password của Laravel.
2. **sessions**: Bảng lưu trữ Session của user thay vì lưu trên file, phục vụ tính năng quản lý thiết bị đang đăng nhập.
3. **cache & cache_locks**: Bảng dùng làm Database Driver cho tính năng Cache của Laravel, bao gồm tính năng khóa (Atomic Locks).
4. **jobs, job_batches, failed_jobs**: Bảng Queue lưu trữ hàng đợi công việc nền (Background Jobs) như gửi Email, thông báo chậm, dọn dẹp data cũ.

### MODULE: BOOKING (BỔ SUNG)

## Tên bảng: booking_items

### 1. Mục đích bảng
Lưu trữ từng sân con và khung giờ cụ thể trong một booking, phục vụ luồng đặt nhiều sân/nhiều slot trong cùng một đơn.

### 2. Danh sách trường

| STT | Tên trường | Kiểu dữ liệu | Null | Default | Khóa/Ràng buộc | Mô tả tác dụng trường | Ví dụ |
|---|---|---|---|---|---|---|---|
| 1 | id | char(36) | Không | - | PK | UUID item | 40000000-... |
| 2 | booking_id | char(36) | Không | - | FK | Đơn đặt sân cha | 50000000-... |
| 3 | venue_court_id | char(36) | Không | - | FK | Sân con thực tế được gán | 30000000-... |
| 4 | requested_venue_court_id | char(36) | Có | null | FK | Sân con khách yêu cầu ban đầu | null |
| 5 | start_time | time | Không | - | Index | Giờ bắt đầu | 18:00:00 |
| 6 | end_time | time | Không | - | Index | Giờ kết thúc | 20:00:00 |
| 7 | duration_minutes | unsigned int | Không | - | - | Thời lượng phút | 120 |
| 8 | unit_price | decimal(12,2) | Không | 0.00 | - | Đơn giá/giờ tại thời điểm đặt | 100000.00 |
| 9 | subtotal | decimal(12,2) | Không | 0.00 | - | Thành tiền | 200000.00 |
| 10 | court_changed_by | char(36) | Có | null | FK | Người đổi sân | null |
| 11 | court_changed_at | timestamp | Có | null | - | Thời điểm đổi sân | null |
| 12 | court_changed_reason | text | Có | null | - | Lý do đổi sân | null |
| 13 | sort_order | unsigned int | Không | 0 | - | Thứ tự hiển thị | 1 |

### 3. Khóa chính, khóa ngoại, index
- PK: id
- FK: booking_id -> bookings.id (cascade), venue_court_id -> venue_courts.id (restrict), requested_venue_court_id -> venue_courts.id (set null), court_changed_by -> users.id (set null)
- Index: [booking_id, sort_order], [venue_court_id, start_time, end_time]

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

### MODULE: PAYMENT (BỔ SUNG)

## Tên bảng: owner_bank_accounts

### 1. Mục đích bảng
Lưu thông tin tài khoản ngân hàng của chủ sân dùng để nhận tiền rút và đối soát.

### 2. Danh sách trường

| STT | Tên trường | Kiểu dữ liệu | Null | Default | Khóa/Ràng buộc | Mô tả tác dụng trường | Ví dụ |
|---|---|---|---|---|---|---|---|
| 1 | id | char(36) | Không | - | PK | ID tài khoản | 60000000-... |
| 2 | owner_id | char(36) | Không | - | FK | Chủ sân sở hữu | 10000000-... |
| 3 | partner_application_id | char(36) | Có | null | FK | Hồ sơ đăng ký đã cung cấp TK này | null |
| 4 | bank_name | varchar(100) | Không | - | - | Tên ngân hàng | Vietcombank |
| 5 | bank_code | varchar(50) | Không | - | - | Mã ngân hàng | VCB |
| 6 | account_number | varchar(50) | Không | - | - | Số tài khoản | 1234567890 |
| 7 | account_holder_name | varchar(150) | Không | - | - | Tên chủ TK | Nguyễn Văn A |
| 8 | branch_name | varchar(150) | Có | null | - | Chi nhánh | Chi nhánh HN |
| 9 | status | enum | Không | pending | Index | Trạng thái xác minh (pending, active, rejected, inactive) | active |
| 10 | is_default | boolean | Không | false | Index | TK nhận tiền mặc định | true |
| 11 | verified_by | char(36) | Có | null | FK | Admin xác minh | null |
| 12 | verified_at | timestamp | Có | null | - | Thời điểm xác minh | null |
| 13 | rejected_reason | text | Có | null | - | Lý do từ chối | null |

### 3. Khóa chính, khóa ngoại, index
- PK: id
- Unique: [owner_id, bank_code, account_number]
- FK: owner_id -> users.id (restrict), partner_application_id -> partner_applications.id (set null), verified_by -> users.id (set null)
- Index: [owner_id, status], [status, is_default], partner_application_id

## Tên bảng: owner_withdrawal_requests

### 1. Mục đích bảng
Quản lý yêu cầu rút tiền từ ví chủ sân.

### 2. Danh sách trường

| STT | Tên trường | Kiểu dữ liệu | Null | Default | Khóa/Ràng buộc | Mô tả tác dụng trường | Ví dụ |
|---|---|---|---|---|---|---|---|
| 1 | id | char(36) | Không | - | PK | ID yêu cầu | 70000000-... |
| 2 | request_code | varchar(30) | Không | - | Unique | Mã yêu cầu rút tiền | WDR-001 |
| 3 | owner_id | char(36) | Không | - | FK | Chủ sân yêu cầu | 10000000-... |
| 4 | owner_wallet_id | char(36) | Không | - | FK | Ví owner bị giữ tiền | 80000000-... |
| 5 | owner_bank_account_id | char(36) | Không | - | FK | TK nhận tiền owner chọn | 60000000-... |
| 6 | amount | decimal(14,2) | Không | - | - | Số tiền yêu cầu rút | 5000000.00 |
| 7 | status | enum | Không | pending | Index | pending, reviewing, approved, rejected, completed, cancelled | pending |
| 8 | owner_note | text | Có | null | - | Ghi chú owner | null |
| 9 | reviewed_by | char(36) | Có | null | FK | Admin duyệt/từ chối | null |
| 10 | reviewed_at | timestamp | Có | null | - | Thời điểm duyệt | null |
| 11 | review_note | text | Có | null | - | Ghi chú nội bộ | null |
| 12 | status_reason | text | Có | null | - | Lý do từ chối/hủy | null |
| 13 | completed_by | char(36) | Có | null | FK | Admin xác nhận đã chuyển tiền | null |
| 14 | completed_at | timestamp | Có | null | - | Thời điểm hoàn tất | null |
| 15 | transfer_reference | varchar(100) | Có | null | - | Mã giao dịch chuyển khoản thực tế | null |
| 16 | metadata | json | Có | null | - | Dữ liệu phụ | null |
| 17 | requested_at | timestamp | Không | CURRENT | - | Thời điểm gửi yêu cầu | 2026-06-01 |

### 3. Khóa chính, khóa ngoại, index
- PK: id
- Unique: request_code
- FK: owner_id -> users.id (restrict), owner_wallet_id -> owner_wallets.id (restrict), owner_bank_account_id -> owner_bank_accounts.id (restrict), reviewed_by -> users.id (set null), completed_by -> users.id (set null)
- Index: [owner_id, status], [status, requested_at], owner_wallet_id, owner_bank_account_id

## Tên bảng: internal_receipts

### 1. Mục đích bảng
Lưu phiếu thu/chi nội bộ cho các nghiệp vụ tài chính (phí nền tảng, rút tiền, hoàn tiền, thanh toán).

### 2. Danh sách trường

| STT | Tên trường | Kiểu dữ liệu | Null | Default | Khóa/Ràng buộc | Mô tả tác dụng trường | Ví dụ |
|---|---|---|---|---|---|---|---|
| 1 | id | char(36) | Không | - | PK | ID phiếu | 80000000-... |
| 2 | receipt_code | varchar(40) | Không | - | Unique | Mã phiếu nội bộ | REC-001 |
| 3 | receipt_type | enum | Không | - | Index | platform_fee, withdrawal, refund, payment | withdrawal |
| 4 | receiptable_type | varchar(100) | Không | - | Index | Loại đối tượng phát sinh phiếu | App\Models\OwnerWithdrawalRequest |
| 5 | receiptable_id | varchar(100) | Không | - | Index | ID đối tượng | 70000000-... |
| 6 | issued_to_user_id | char(36) | Có | null | FK | User nhận phiếu | null |
| 7 | issued_by | char(36) | Có | null | FK | Admin tạo phiếu | null |
| 8 | title | varchar(255) | Không | - | - | Tiêu đề phiếu | Phiếu chi rút tiền |
| 9 | amount | decimal(14,2) | Không | 0.00 | - | Số tiền trên phiếu | 5000000.00 |
| 10 | currency | varchar(10) | Không | VND | - | Đơn vị tiền tệ | VND |
| 11 | status | enum | Không | issued | Index | draft, issued, cancelled | issued |
| 12 | issued_at | timestamp | Có | null | Index | Thời điểm phát hành | 2026-06-01 |
| 13 | cancelled_at | timestamp | Có | null | - | Thời điểm hủy | null |
| 14 | cancel_reason | text | Có | null | - | Lý do hủy | null |
| 15 | file_path | varchar(500) | Có | null | - | Đường dẫn file PDF/HTML | null |
| 16 | metadata | json | Có | null | - | Dữ liệu phụ render phiếu | null |

### 3. Khóa chính, khóa ngoại, index
- PK: id
- Unique: receipt_code
- FK: issued_to_user_id -> users.id (set null), issued_by -> users.id (set null)
- Index: [receiptable_type, receiptable_id], [receipt_type, status], issued_at

---

### MODULE: POLICY

## Tên bảng: policy_action_bindings

### 1. Mục đích bảng
Map chính sách hệ thống với module/action nghiệp vụ (VD: `booking.cancel`, `refund.request`).

### 2. Danh sách trường

| STT | Tên trường | Kiểu dữ liệu | Null | Default | Khóa/Ràng buộc | Mô tả tác dụng trường | Ví dụ |
|---|---|---|---|---|---|---|---|
| 1 | id | char(36) | Không | - | PK | UUID | ... |
| 2 | system_policy_id | char(36) | Không | - | FK | Chính sách được bind | ... |
| 3 | module | varchar(50) | Không | - | Index | Module nghiệp vụ | booking |
| 4 | action_code | varchar(100) | Không | - | Index | Mã action | booking.cancel |
| 5 | description | text | Có | null | - | Mô tả binding | null |
| 6 | is_active | boolean | Không | true | Index | Binding có hiệu lực | 1 |

### 3. Khóa chính, khóa ngoại, index
- PK: id
- Unique: [system_policy_id, action_code]
- FK: system_policy_id -> system_policies.id (cascade)
- Index: [module, action_code], is_active

## Tên bảng: policy_rules

### 1. Mục đích bảng
Lưu rule hệ thống có cấu trúc JSON để backend evaluate theo từng action.

### 2. Danh sách trường

| STT | Tên trường | Kiểu dữ liệu | Null | Default | Khóa/Ràng buộc | Mô tả tác dụng trường | Ví dụ |
|---|---|---|---|---|---|---|---|
| 1 | id | char(36) | Không | - | PK | UUID | ... |
| 2 | system_policy_id | char(36) | Không | - | FK | Chính sách sở hữu rule | ... |
| 3 | action_code | varchar(100) | Không | - | Index | Action mà rule áp dụng | booking.cancel |
| 4 | rule_code | varchar(100) | Không | - | Unique (với policy_id) | Mã rule duy nhất trong policy | cancel_before_24h |
| 5 | rule_name | varchar(255) | Không | - | - | Tên rule dễ đọc | Hủy trước 24 giờ |
| 6 | rule_type | varchar(50) | Không | - | Index | Loại evaluator | threshold |
| 7 | decision_key | varchar(100) | Có | null | Index | Key quyết định output | refund_percent |
| 8 | conflict_group | varchar(100) | Có | null | Index | Nhóm xung đột rule | booking_cancel |
| 9 | condition_json | json | Có | null | - | Điều kiện evaluate | {"min_hours_before": 24} |
| 10 | result_json | json | Có | null | - | Kết quả khi match | {"refund_percent": 100} |
| 11 | constraint_json | json | Có | null | - | Ràng buộc bổ sung | null |
| 12 | allowed_override_json | json | Có | null | - | Phạm vi cho phép sân override | null |
| 13 | priority | int | Không | 0 | Index | Độ ưu tiên | 10 |
| 14 | is_active | boolean | Không | true | Index | Rule có hiệu lực | 1 |
| 15 | created_by | char(36) | Có | null | FK | Admin tạo rule | null |
| 16 | updated_by | char(36) | Có | null | FK | Admin cập nhật rule | null |

### 3. Khóa chính, khóa ngoại, index
- PK: id
- Unique: [system_policy_id, rule_code]
- FK: system_policy_id -> system_policies.id (cascade), created_by -> users.id (set null), updated_by -> users.id (set null)
- Index: [action_code, is_active], [rule_type, priority], [action_code, rule_type, is_active, priority], [action_code, decision_key, conflict_group]

## Tên bảng: venue_policy_rules

### 1. Mục đích bảng
Lưu rule riêng của sân, chỉ dùng khi chính sách hệ thống cho phép override (`is_overridable = true`).

### 2. Danh sách trường

| STT | Tên trường | Kiểu dữ liệu | Null | Default | Khóa/Ràng buộc | Mô tả tác dụng trường | Ví dụ |
|---|---|---|---|---|---|---|---|
| 1 | id | char(36) | Không | - | PK | UUID | ... |
| 2 | venue_cluster_id | char(36) | Không | - | FK | Cụm sân cấu hình rule | 20000000-... |
| 3 | base_policy_rule_id | char(36) | Có | null | FK | Rule hệ thống được override | null |
| 4 | action_code | varchar(100) | Không | - | Index | Action áp dụng | booking.cancel |
| 5 | rule_code | varchar(100) | Không | - | - | Mã rule sân | cancel_custom |
| 6 | rule_name | varchar(255) | Không | - | - | Tên rule sân | Hủy trước 12 giờ |
| 7 | rule_type | varchar(50) | Không | - | - | Loại evaluator | threshold |
| 8 | condition_json | json | Có | null | - | Điều kiện do owner cấu hình | {"min_hours_before": 12} |
| 9 | result_json | json | Có | null | - | Kết quả khi match | {"refund_percent": 80} |
| 10 | status | enum | Không | draft | Index | draft, active, inactive, rejected | active |
| 11 | approved_by | char(36) | Có | null | FK | Admin duyệt | null |
| 12 | approved_at | timestamp | Có | null | - | Thời điểm duyệt | null |
| 13 | rejected_reason | text | Có | null | - | Lý do từ chối | null |
| 14 | created_by | char(36) | Có | null | FK | Owner/nhân viên tạo | null |
| 15 | updated_by | char(36) | Có | null | FK | Người cập nhật | null |

### 3. Khóa chính, khóa ngoại, index
- PK: id
- FK: venue_cluster_id -> venue_clusters.id (cascade), base_policy_rule_id -> policy_rules.id (set null), approved_by -> users.id (set null), created_by -> users.id (set null), updated_by -> users.id (set null)
- Index: [venue_cluster_id, status], [action_code, status], base_policy_rule_id

## Tên bảng: policy_evaluation_logs

### 1. Mục đích bảng
Ghi nhận mỗi lần hệ thống evaluate rule, lưu input, output, actor, entity liên quan.

### 2. Danh sách trường

| STT | Tên trường | Kiểu dữ liệu | Null | Default | Khóa/Ràng buộc | Mô tả tác dụng trường | Ví dụ |
|---|---|---|---|---|---|---|---|
| 1 | id | char(36) | Không | - | PK | UUID | ... |
| 2 | system_policy_id | char(36) | Có | null | FK | Chính sách đã evaluate | ... |
| 3 | policy_rule_id | char(36) | Có | null | FK | Rule hệ thống đã evaluate | ... |
| 4 | venue_policy_rule_id | char(36) | Có | null | FK | Rule sân đã evaluate | null |
| 5 | action_code | varchar(100) | Không | - | Index | Action được evaluate | booking.cancel |
| 6 | entity_type | varchar(100) | Không | - | Index | Loại đối tượng | booking |
| 7 | entity_id | varchar(100) | Không | - | Index | ID đối tượng | 50000000-... |
| 8 | input_data | json | Có | null | - | Dữ liệu đầu vào | {"hours_before": 30} |
| 9 | result_data | json | Có | null | - | Kết quả evaluate | {"allow": true, "refund_percent": 100} |
| 10 | policy_version_snapshot | json | Có | null | - | Snapshot version policy | null |
| 11 | rule_snapshot | json | Có | null | - | Snapshot rule tại thời điểm evaluate | null |
| 12 | evaluated_by_type | enum | Không | system | Index | Loại actor | system |
| 13 | evaluated_by_id | char(36) | Có | null | FK | User kích hoạt | null |
| 14 | created_at | timestamp | Có | null | Index | Thời điểm evaluate | 2026-06-01 |

### 3. Khóa chính, khóa ngoại, index
- PK: id
- FK: system_policy_id -> system_policies.id (set null), policy_rule_id -> policy_rules.id (set null), venue_policy_rule_id -> venue_policy_rules.id (set null), evaluated_by_id -> users.id (set null)
- Index: [action_code, created_at], [entity_type, entity_id], system_policy_id, policy_rule_id, venue_policy_rule_id, [evaluated_by_type, created_at], [action_code, entity_type, entity_id, created_at]

---

### MODULE: AI

## Tên bảng: ai_conversations

### 1. Mục đích bảng
Lưu cuộc trò chuyện AI của user.

### 2. Danh sách trường

| STT | Tên trường | Kiểu dữ liệu | Null | Default | Khóa/Ràng buộc | Mô tả tác dụng trường | Ví dụ |
|---|---|---|---|---|---|---|---|
| 1 | id | char(36) | Không | - | PK | UUID | ... |
| 2 | user_id | char(36) | Không | - | FK | User sở hữu | 10000000-... |
| 3 | title | varchar(255) | Có | null | - | Tiêu đề | Hỏi về đặt sân |
| 4 | status | enum | Không | active | Index | active, archived, deleted | active |
| 5 | deleted_at | timestamp | Có | null | Index | Soft delete | null |

### 3. Khóa chính, khóa ngoại, index
- PK: id
- FK: user_id -> users.id (cascade)
- Index: [user_id, status], deleted_at

## Tên bảng: ai_messages

### 1. Mục đích bảng
Lưu message user/assistant/system trong cuộc trò chuyện AI.

### 2. Danh sách trường

| STT | Tên trường | Kiểu dữ liệu | Null | Default | Khóa/Ràng buộc | Mô tả tác dụng trường | Ví dụ |
|---|---|---|---|---|---|---|---|
| 1 | id | char(36) | Không | - | PK | UUID | ... |
| 2 | ai_conversation_id | char(36) | Không | - | FK | Cuộc trò chuyện chứa message | ... |
| 3 | role | enum | Không | - | Index | user, assistant, system | user |
| 4 | content | longText | Không | - | - | Nội dung message | Sân nào gần nhất? |
| 5 | metadata | json | Có | null | - | Dữ liệu phụ (token, model) | null |
| 6 | deleted_at | timestamp | Có | null | Index | Soft delete | null |

### 3. Khóa chính, khóa ngoại, index
- PK: id
- FK: ai_conversation_id -> ai_conversations.id (cascade)
- Index: [ai_conversation_id, created_at], role, deleted_at

## Tên bảng: ai_feedbacks

### 1. Mục đích bảng
Lưu feedback của user cho message AI (đánh giá chất lượng câu trả lời).

### 2. Danh sách trường

| STT | Tên trường | Kiểu dữ liệu | Null | Default | Khóa/Ràng buộc | Mô tả tác dụng trường | Ví dụ |
|---|---|---|---|---|---|---|---|
| 1 | id | char(36) | Không | - | PK | UUID | ... |
| 2 | ai_message_id | char(36) | Không | - | FK | Message được đánh giá | ... |
| 3 | user_id | char(36) | Không | - | FK | User gửi feedback | 10000000-... |
| 4 | rating | tinyInteger | Có | null | Index | Điểm đánh giá (1-5) | 4 |
| 5 | comment | text | Có | null | - | Góp ý | Trả lời chính xác |

### 3. Khóa chính, khóa ngoại, index
- PK: id
- Unique: [ai_message_id, user_id]
- FK: ai_message_id -> ai_messages.id (cascade), user_id -> users.id (cascade)
- Index: rating

---

### MODULE: USER WALLET

## Tên bảng: user_wallets

### 1. Mục đích bảng
Quản lý ví nội bộ của user, dùng để thanh toán booking hoặc nhận tiền hoàn.

### 2. Danh sách trường

| STT | Tên trường | Kiểu dữ liệu | Null | Default | Khóa/Ràng buộc | Mô tả tác dụng trường | Ví dụ |
|---|---|---|---|---|---|---|---|
| 1 | id | char(36) | Không | - | PK | UUID | ... |
| 2 | user_id | char(36) | Không | - | Unique, FK | User sở hữu ví (1 user 1 ví) | 10000000-... |
| 3 | balance | decimal(14,2) | Không | 0.00 | - | Số dư có thể sử dụng | 500000.00 |
| 4 | locked_balance | decimal(14,2) | Không | 0.00 | - | Số dư đang bị giữ/chờ xử lý | 0.00 |
| 5 | status | enum | Không | active | Index | active, locked, suspended | active |

### 3. Khóa chính, khóa ngoại, index
- PK: id
- Unique: user_id
- FK: user_id -> users.id (restrict)
- Index: status

## Tên bảng: user_wallet_ledgers

### 1. Mục đích bảng
Ghi nhận biến động số dư ví user (nguyên tắc kế toán kép: balance_before, balance_after).

### 2. Danh sách trường

| STT | Tên trường | Kiểu dữ liệu | Null | Default | Khóa/Ràng buộc | Mô tả tác dụng trường | Ví dụ |
|---|---|---|---|---|---|---|---|
| 1 | id | char(36) | Không | - | PK | UUID | ... |
| 2 | user_wallet_id | char(36) | Không | - | FK | Ví được ghi biến động | ... |
| 3 | transaction_code | varchar(50) | Không | - | Unique | Mã giao dịch nội bộ | UWLT-001 |
| 4 | type | enum | Không | - | Index | deposit, payment, refund, withdrawal, adjustment | payment |
| 5 | direction | enum | Không | - | - | credit hoặc debit | debit |
| 6 | amount | decimal(14,2) | Không | - | - | Số tiền biến động | 100000.00 |
| 7 | balance_before | decimal(14,2) | Không | - | - | Số dư trước | 500000.00 |
| 8 | balance_after | decimal(14,2) | Không | - | - | Số dư sau | 400000.00 |
| 9 | reference_type | varchar(100) | Có | null | Index | Loại tham chiếu (booking, payment, refund) | payment |
| 10 | reference_id | varchar(100) | Có | null | Index | ID tham chiếu | 70000000-... |
| 11 | status | enum | Không | completed | Index | pending, completed, failed, cancelled | completed |
| 12 | note | text | Có | null | - | Ghi chú | Thanh toán booking |
| 13 | created_by | char(36) | Có | null | FK | User/admin tạo | null |

### 3. Khóa chính, khóa ngoại, index
- PK: id
- Unique: transaction_code
- FK: user_wallet_id -> user_wallets.id (restrict), created_by -> users.id (set null)
- Index: [user_wallet_id, created_at], [reference_type, reference_id], [type, status]

## Tên bảng: user_payout_accounts

### 1. Mục đích bảng
TKNH user dùng nhận tiền khi rút ví hoặc refund.

### 2. Danh sách trường

| STT | Tên trường | Kiểu dữ liệu | Null | Default | Khóa/Ràng buộc | Mô tả tác dụng trường | Ví dụ |
|---|---|---|---|---|---|---|---|
| 1 | id | char(36) | Không | - | PK | UUID | ... |
| 2 | user_id | char(36) | Không | - | FK | User sở hữu | 10000000-... |
| 3 | bank_name | varchar(100) | Không | - | - | Tên ngân hàng | Techcombank |
| 4 | bank_account_number | varchar(50) | Không | - | - | Số tài khoản | 9876543210 |
| 5 | bank_account_holder | varchar(150) | Không | - | - | Tên chủ TK | Trần Thị B |
| 6 | bank_branch | varchar(150) | Có | null | - | Chi nhánh | null |
| 7 | is_default | boolean | Không | false | Index | TK mặc định | true |
| 8 | status | enum | Không | active | Index | active, inactive | active |

### 3. Khóa chính, khóa ngoại, index
- PK: id
- Unique: [user_id, bank_account_number]
- FK: user_id -> users.id (restrict)
- Index: [user_id, status], [status, is_default]

## Tên bảng: user_withdrawal_requests

### 1. Mục đích bảng
Quản lý yêu cầu rút tiền từ ví user.

### 2. Danh sách trường

| STT | Tên trường | Kiểu dữ liệu | Null | Default | Khóa/Ràng buộc | Mô tả tác dụng trường | Ví dụ |
|---|---|---|---|---|---|---|---|
| 1 | id | char(36) | Không | - | PK | UUID | ... |
| 2 | user_wallet_id | char(36) | Không | - | FK | Ví user bị giữ/trừ tiền | ... |
| 3 | user_id | char(36) | Không | - | FK | User yêu cầu rút tiền | 10000000-... |
| 4 | payout_account_id | char(36) | Không | - | FK | TK nhận tiền user chọn | ... |
| 5 | amount | decimal(14,2) | Không | - | - | Số tiền yêu cầu rút | 200000.00 |
| 6 | status | enum | Không | pending | Index | pending, approved, rejected, paid, cancelled | pending |
| 7 | rejected_reason | text | Có | null | - | Lý do từ chối | null |
| 8 | approved_by | char(36) | Có | null | FK | Admin duyệt | null |
| 9 | paid_by | char(36) | Có | null | FK | Admin xác nhận chi trả | null |
| 10 | requested_at | timestamp | Không | CURRENT | - | Thời điểm gửi yêu cầu | 2026-06-01 |
| 11 | approved_at | timestamp | Có | null | - | Thời điểm duyệt | null |
| 12 | paid_at | timestamp | Có | null | - | Thời điểm chi trả | null |

### 3. Khóa chính, khóa ngoại, index
- PK: id
- FK: user_wallet_id -> user_wallets.id (restrict), user_id -> users.id (restrict), payout_account_id -> user_payout_accounts.id (restrict), approved_by -> users.id (set null), paid_by -> users.id (set null)
- Index: [user_id, status], [status, requested_at]

---

### MODULE: VOUCHER

## Tên bảng: vouchers

### 1. Mục đích bảng
Lưu voucher hệ thống và voucher sân.

### 2. Danh sách trường

| STT | Tên trường | Kiểu dữ liệu | Null | Default | Khóa/Ràng buộc | Mô tả tác dụng trường | Ví dụ |
|---|---|---|---|---|---|---|---|
| 1 | id | char(36) | Không | - | PK | UUID | ... |
| 2 | code | varchar(50) | Không | - | Unique | Mã voucher user nhập | SPORTGO10 |
| 3 | name | varchar(255) | Không | - | - | Tên hiển thị | Giảm 10% lần đầu |
| 4 | description | text | Có | null | - | Mô tả | null |
| 5 | owner_type | enum | Không | - | Index | system hoặc venue | system |
| 6 | owner_id | char(36) | Có | null | Index | ID owner/cụm sân (nếu venue) | null |
| 7 | funded_by | enum | Không | - | Index | system hoặc venue (bên chịu tiền giảm) | system |
| 8 | stacking_rule | enum | Không | exclusive | - | exclusive, allow_with_system, allow_with_venue | exclusive |
| 9 | discount_type | enum | Không | - | - | percent hoặc fixed | percent |
| 10 | discount_value | decimal(12,2) | Không | - | - | Giá trị giảm | 10.00 |
| 11 | max_discount_amount | decimal(12,2) | Có | null | - | Mức giảm tối đa | 50000.00 |
| 12 | min_order_amount | decimal(12,2) | Không | 0.00 | - | Giá trị đơn tối thiểu | 100000.00 |
| 13 | total_quantity | unsigned int | Có | null | - | Tổng số lượt phát hành | 1000 |
| 14 | used_quantity | unsigned int | Không | 0 | - | Số lượt đã dùng | 50 |
| 15 | per_user_limit | unsigned int | Có | null | - | Số lượt tối đa mỗi user | 1 |
| 16 | valid_from | dateTime | Có | null | Index | Bắt đầu hiệu lực | 2026-06-01 |
| 17 | valid_to | dateTime | Có | null | Index | Hết hiệu lực | 2026-12-31 |
| 18 | status | enum | Không | draft | Index | draft, active, inactive, expired | active |
| 19 | created_by | char(36) | Có | null | FK | Admin/owner tạo | null |

### 3. Khóa chính, khóa ngoại, index
- PK: id
- Unique: code
- FK: created_by -> users.id (set null)
- Index: [owner_type, owner_id], [status, valid_from, valid_to], funded_by

## Tên bảng: voucher_scopes

### 1. Mục đích bảng
Giới hạn phạm vi áp dụng voucher (cụm sân, loại sân, loại booking).

### 2. Danh sách trường

| STT | Tên trường | Kiểu dữ liệu | Null | Default | Khóa/Ràng buộc | Mô tả tác dụng trường | Ví dụ |
|---|---|---|---|---|---|---|---|
| 1 | id | char(36) | Không | - | PK | UUID | ... |
| 2 | voucher_id | char(36) | Không | - | FK | Voucher được giới hạn | ... |
| 3 | scope_type | enum | Không | - | Index | all, venue_cluster, court_type, booking_type | all |
| 4 | scope_id | varchar(100) | Có | null | Index | ID phạm vi (nullable khi all) | null |
| 5 | scope_key | varchar(120) | Không | __all__ | Unique (với voucher_id, scope_type) | Khóa ổn định unique | __all__ |

### 3. Khóa chính, khóa ngoại, index
- PK: id
- Unique: [voucher_id, scope_type, scope_key]
- FK: voucher_id -> vouchers.id (cascade)
- Index: [scope_type, scope_id]

## Tên bảng: voucher_usages

### 1. Mục đích bảng
Ghi nhận voucher đã áp dụng cho booking/payment nào.

### 2. Danh sách trường

| STT | Tên trường | Kiểu dữ liệu | Null | Default | Khóa/Ràng buộc | Mô tả tác dụng trường | Ví dụ |
|---|---|---|---|---|---|---|---|
| 1 | id | char(36) | Không | - | PK | UUID | ... |
| 2 | voucher_id | char(36) | Không | - | FK | Voucher đã dùng | ... |
| 3 | user_id | char(36) | Không | - | FK | User dùng | 10000000-... |
| 4 | booking_id | char(36) | Không | - | FK, Index | Booking áp dụng | 50000000-... |
| 5 | payment_id | char(36) | Có | null | FK | Payment liên quan | null |
| 6 | discount_amount | decimal(12,2) | Không | 0.00 | - | Số tiền giảm thực tế | 50000.00 |
| 7 | used_at | timestamp | Có | null | - | Thời điểm áp dụng | 2026-06-01 |
| 8 | status | enum | Không | applied | Index | applied, cancelled, refunded | applied |

### 3. Khóa chính, khóa ngoại, index
- PK: id
- Unique: [voucher_id, user_id, booking_id]
- FK: voucher_id -> vouchers.id (restrict), user_id -> users.id (restrict), booking_id -> bookings.id (restrict), payment_id -> payments.id (set null)
- Index: [voucher_id, status], [user_id, voucher_id], booking_id

**Lưu ý**: Migration cũng bổ sung các field snapshot discount vào bảng `bookings`: `original_amount`, `discount_amount`, `system_discount_amount`, `venue_discount_amount`, `final_amount`, `voucher_id` (FK -> vouchers.id), `voucher_code_snapshot`.

---

### MODULE: SYSTEM (BỔ SUNG)

## Tên bảng: backup_jobs

### 1. Mục đích bảng
Lưu metadata và trạng thái các lần backup database.

### 2. Danh sách trường

| STT | Tên trường | Kiểu dữ liệu | Null | Default | Khóa/Ràng buộc | Mô tả tác dụng trường | Ví dụ |
|---|---|---|---|---|---|---|---|
| 1 | id | char(36) | Không | - | PK | UUID | ... |
| 2 | backup_code | varchar(50) | Không | - | Unique | Mã backup tra cứu | BKP-001 |
| 3 | file_name | varchar(255) | Có | null | - | Tên file backup | sportgo_20260601.sql.gz |
| 4 | file_path | varchar(1000) | Có | null | - | Đường dẫn file | /backups/sportgo_20260601.sql.gz |
| 5 | disk | varchar(100) | Có | null | - | Storage disk | local |
| 6 | size_bytes | unsigned bigint | Có | null | - | Dung lượng file | 104857600 |
| 7 | checksum | varchar(128) | Có | null | - | Checksum kiểm tra | sha256:abc... |
| 8 | type | enum | Không | manual | Index | manual hoặc auto | manual |
| 9 | status | enum | Không | pending | Index | pending, running, completed, failed | completed |
| 10 | created_by | char(36) | Có | null | FK | Admin tạo backup | null |
| 11 | started_at | timestamp | Có | null | - | Thời điểm bắt đầu | 2026-06-01 |
| 12 | completed_at | timestamp | Có | null | Index | Thời điểm hoàn tất | 2026-06-01 |
| 13 | error_message | text | Có | null | - | Lỗi nếu thất bại | null |
| 14 | retention_days | unsigned int | Có | null | - | Số ngày giữ file | 30 |

### 3. Khóa chính, khóa ngoại, index
- PK: id
- Unique: backup_code
- FK: created_by -> users.id (set null)
- Index: [type, status], created_at, completed_at

---
**Ghi chú cuối báo cáo:**
- Báo cáo này phản ánh 100% nguyên trạng từ file migration (tổng cộng 80 bảng).
- Một số bảng như `payments` có field `system_bank_account_id` đã được update và loại bỏ cổng thanh toán qua BankHub để thay thế hoàn toàn bởi cấu hình mới (`2026_05_29_000003...` và `2026_05_29_000004...`).
- Các liên kết như `mediable_type` / `reference_type` đều là *Liên kết logic đa hình (Polymorphic)* được validate ở mức Service của Laravel chứ không có khóa ngoại (Foreign Key) cứng trên MySQL.
- Bảng `slot_locks` có thêm field `booking_item_id` (FK -> booking_items.id) từ migration 2026-05-30.
- Bảng `bookings` có thêm các field snapshot discount (original_amount, discount_amount, system_discount_amount, venue_discount_amount, final_amount, voucher_id, voucher_code_snapshot) từ migration 2026-06-01.
- Bảng `payments` có thêm wallet_amount, gateway_amount, user_wallet_id, user_wallet_ledger_id từ migration 2026-06-01. Method enum mở rộng: sepay, bank_transfer, cash, wallet, mixed, vnpay, momo, zalopay.
- Bảng `refunds` có thêm customer_id, refund_destination, user_wallet_id, user_wallet_ledger_id, user_payout_account_id, owner_wallet_ledger_id, owner_confirmed_by/at/note, admin_confirmed_by/at, gateway_refund_txn_id từ migration 2026-06-01.
- Bảng `owner_wallet_ledgers` có thêm direction, status, reference_type, reference_id, transaction_code, note từ migration 2026-06-01.
- Bảng `community_post_comments` có thêm moderation fields từ migration 2026-05-30.
- Bảng `system_policies` có thêm policy_type, is_overridable, priority, status, effective_to, published_at, published_by, replaced_policy_id, require_reaccept, change_summary.
- Bảng `policy_rules` có thêm decision_key, conflict_group, constraint_json, allowed_override_json, created_by, updated_by.
- Bảng `venue_policy_rules` có thêm updated_by.
- Bảng `policy_evaluation_logs` có thêm policy_version_snapshot, rule_snapshot.
- Bảng `user_policy_acceptances` có thêm ip_address, user_agent.
- Bảng `audit_logs` có thêm actor_type, module, metadata, reason, policy_id, policy_rule_id, policy_evaluation_log_id, request_id, severity.

==================================================
## PHẦN BỔ SUNG. THIẾT KẾ CHỨC NĂNG ADMIN THEO DB HIỆN TẠI
==================================================

Phần này thay thế đoạn mô tả cũ bị lỗi encoding. Nội dung dưới đây chỉ tóm tắt trạng thái DB sau các migration ngày 2026-05-30 và được phần cập nhật 2026-06-01 mở rộng thêm.

### 1. Duyệt hồ sơ đăng ký làm chủ sân

DB sử dụng:

| Bảng | Vai trò |
|---|---|
| `partner_applications` | Hồ sơ đăng ký làm chủ sân. |
| `partner_application_courts` | Loại sân/môn thể thao đăng ký kinh doanh. |
| `owner_bank_accounts` | Tài khoản nhận tiền của owner, có thể liên kết hồ sơ đăng ký. |
| `venue_clusters` | Cụm sân được tạo sau khi duyệt. |
| `venue_courts` | Sân con ban đầu sau khi duyệt. |
| `media` | Giấy tờ/file đính kèm. |
| `audit_logs` | Lịch sử duyệt/từ chối. |
| `notifications` | Thông báo kết quả. |

### 2. Rút tiền chủ sân

DB sử dụng:

| Bảng | Vai trò |
|---|---|
| `owner_wallets` | Số dư ví chủ sân. |
| `owner_wallet_ledgers` | Biến động ví chủ sân. |
| `owner_bank_accounts` | Tài khoản nhận tiền owner chọn. |
| `owner_withdrawal_requests` | Yêu cầu rút tiền của owner. |
| `internal_receipts` | Phiếu chi nội bộ. |
| `audit_logs` | Lịch sử approve/reject/complete. |

Luồng tối thiểu: owner tạo yêu cầu rút tiền, hệ thống giữ tiền bằng số dư pending/ledger hold, admin duyệt hoặc từ chối, admin xác nhận đã chuyển tiền, hệ thống ghi ledger và phiếu nội bộ.

### 3. Sao kê phí duy trì nền tảng

DB sử dụng:

| Bảng | Vai trò |
|---|---|
| `platform_fee_tiers` | Bậc phí theo số sân con. |
| `venue_platform_fee_ledgers` | Sổ phí duy trì theo cụm sân/kỳ. |
| `system_bank_accounts` | Tài khoản hệ thống nhận phí. |
| `media` | Bằng chứng thanh toán. |
| `internal_receipts` | Phiếu thu nội bộ. |
| `venue_clusters` | Cụm sân có thể bị khóa nếu quá hạn phí. |
| `audit_logs` | Lịch sử xác nhận/từ chối/khóa cụm. |

Các field bổ sung quan trọng: `period_months`, `due_date`, `payment_proof_media_id`, `payment_proof_status`, `payment_confirmed_by`, `payment_rejected_by`, `payment_reject_reason`, `locked_venue_at`, `internal_receipt_id`.

### 4. Hoàn tiền booking

DB sử dụng:

| Bảng | Vai trò |
|---|---|
| `refunds` | Yêu cầu hoàn tiền. |
| `payments` | Payment gốc cần hoàn. |
| `bookings` | Booking liên quan. |
| `user_wallets` | Ví user nhận hoàn nếu hoàn về ví. |
| `user_payout_accounts` | Tài khoản ngân hàng user nếu hoàn về bank. |
| `owner_wallet_ledgers` | Ledger trừ doanh thu owner nếu nghiệp vụ yêu cầu. |
| `audit_logs` | Lịch sử owner/admin xử lý hoàn tiền. |

Chi tiết liên kết refund sau migration 2026-06-01 được mô tả ở phần cập nhật mới nhất bên dưới.

### 5. Report, complaint và moderation

DB sử dụng:

| Bảng | Vai trò |
|---|---|
| `reports` | Báo cáo vi phạm. |
| `complaints` | Khiếu nại. |
| `community_posts`, `venue_posts`, `community_post_comments` | Nội dung cần kiểm duyệt. |
| `media` | Bằng chứng. |
| `audit_logs` | Lịch sử xử lý. |
| `notifications` | Thông báo kết quả. |

Sau migration 2026-05-30, `community_post_comments` có thêm field moderation cơ bản để ẩn/duyệt/từ chối bình luận.

### 6. Thanh toán booking và thu hộ owner

DB sử dụng:

| Bảng | Vai trò |
|---|---|
| `payments` | Attempt thanh toán booking. |
| `payment_logs` | Log gateway/webhook. |
| `system_bank_accounts` | Tài khoản hệ thống nhận tiền. |
| `owner_wallets` | Ví owner nhận doanh thu booking online. |
| `owner_wallet_ledgers` | Ledger cộng doanh thu owner. |
| `audit_logs` | Log hành động nhạy cảm. |

Sau migration 2026-06-01, `payments` có thêm `wallet_amount`, `gateway_amount`, `user_wallet_id`, `user_wallet_ledger_id` để hỗ trợ thanh toán ví/gateway/mixed.

### 7. Booking runtime và booking items

DB hiện có cả:

- `bookings`: lưu thông tin tổng quan booking, trạng thái, source, booking_type, tổng tiền, thông tin thanh toán.
- `booking_items`: lưu từng sân con/khung giờ trong một booking.

Các cột runtime trên `bookings` vẫn được giữ để tương thích luồng hiện tại, trong khi `booking_items` phục vụ luồng nhiều sân/nhiều slot chi tiết hơn.
==================================================
## PHẦN CẬP NHẬT SAU MIGRATION 2026-06-01
==================================================

Phần này bổ sung thiết kế database cho các chức năng nhóm đã chốt ngày 01/06/2026. Hướng triển khai ưu tiên ít phá hệ thống hiện tại: giữ các bảng ví chủ sân đang có, chỉ thêm bảng mới và mở rộng bảng cũ bằng field nullable/default an toàn.

### 1. Nhóm Policy Rule

#### Bảng tận dụng

| Bảng | Vai trò |
|---|---|
| `system_policies` | Lưu text chính sách hệ thống, phiên bản, trạng thái hiệu lực. |
| `user_policy_acceptances` | Lưu việc user đã chấp nhận chính sách. |
| `audit_logs` | Ghi lại các hành động nhạy cảm do policy/rule chi phối. |

#### Bảng/field mở rộng

`system_policies` được bổ sung:

| Field | Ý nghĩa |
|---|---|
| `policy_type` | Nhóm nghiệp vụ của policy, ví dụ refund, moderation, account, platform_fee, terms. |
| `is_overridable` | Cho phép sân cấu hình rule override trong phạm vi hệ thống cho phép. |
| `priority` | Độ ưu tiên khi nhiều chính sách cùng áp dụng. |
| `status` | Vòng đời policy: draft, active, inactive, archived. |

#### Bảng mới

| Bảng | Mục đích |
|---|---|
| `policy_action_bindings` | Map chính sách với module/action như `booking.cancel`, `refund.request`, `venue.lock_due_fee`. |
| `policy_rules` | Lưu rule hệ thống có cấu trúc để backend evaluate. |
| `venue_policy_rules` | Lưu rule riêng của sân, chỉ dùng khi policy cho phép override. |
| `policy_evaluation_logs` | Lưu mỗi lần hệ thống áp dụng rule: input, output, actor, entity liên quan. |

#### Quan hệ chính

- `system_policies` 1-n `policy_action_bindings`.
- `system_policies` 1-n `policy_rules`.
- `venue_clusters` 1-n `venue_policy_rules`.
- `policy_rules` 1-n `venue_policy_rules` qua `base_policy_rule_id`.
- `policy_rules`/`venue_policy_rules` 1-n `policy_evaluation_logs`.
- `audit_logs` có thể tham chiếu `policy_id`, `policy_rule_id`, `policy_evaluation_log_id`.
- `policy_rules` unique theo `system_policy_id + rule_code` để một policy không có hai rule trùng mã.

#### Luồng nghiệp vụ

1. Admin tạo text chính sách trong `system_policies`.
2. Admin bind policy với nhiều action trong `policy_action_bindings`.
3. Admin cấu hình rule hệ thống trong `policy_rules`.
4. Owner chỉ cấu hình rule sân qua form, lưu vào `venue_policy_rules`.
5. Backend evaluator chạy rule theo `action_code`.
6. Kết quả evaluate được lưu vào `policy_evaluation_logs`.
7. Nếu action làm thay đổi dữ liệu/quyền/tài chính thì ghi `audit_logs`.

#### Ghi chú nghiệp vụ

- Text chính sách dùng để người đọc.
- Rule chính sách dùng để hệ thống chạy.
- Chính sách hệ thống là luật khung bắt buộc.
- Chính sách sân chỉ override khi `system_policies.is_overridable = true`.
- Nếu rule sân xung đột rule hệ thống thì rule hệ thống thắng.
- AI không được quyết định các luồng tài chính/quyền hạn.

### 2. Nhóm Audit Log nghiệp vụ

#### Bảng mở rộng

`audit_logs` được bổ sung:

| Field | Ý nghĩa |
|---|---|
| `actor_type` | Loại actor: user, owner, venue_staff, admin, super_admin, system. |
| `module` | Module nghiệp vụ: auth, booking, payment, policy, voucher... |
| `metadata` | JSON ngữ cảnh bổ sung. |
| `reason` | Lý do thao tác như khóa, từ chối, hủy, hoàn tiền. |
| `policy_id` | Chính sách chi phối hành động nếu có. |
| `policy_rule_id` | Rule chi phối hành động nếu có. |
| `policy_evaluation_log_id` | Lần evaluate tạo ra hành động nếu có. |
| `request_id` | ID request để trace log cùng một request. |
| `severity` | Mức độ: info, warning, critical. |

#### Luồng nghiệp vụ

- Chỉ log action thay đổi dữ liệu hoặc ảnh hưởng nghiệp vụ/quyền/tài chính.
- Không log toàn bộ thao tác xem list/detail để tránh phình DB.
- `old_values` và `new_values` lưu snapshot bản ghi trước/sau thao tác.
- `metadata` lưu input phụ như số report, số người report, số ngày quá hạn, mã giao dịch.

#### Lưu ý

- Audit log không phải backup.
- Nếu mất database thì audit log cũng mất.
- Backup thật phải lưu file backup ngoài DB/storage riêng.

### 3. Nhóm AI History

#### Bảng mới

| Bảng | Mục đích |
|---|---|
| `ai_conversations` | Lưu cuộc trò chuyện AI của user. |
| `ai_messages` | Lưu message user/assistant/system trong cuộc trò chuyện AI. |
| `ai_feedbacks` | Lưu feedback của user cho message AI, dùng sau nếu cần đánh giá chất lượng. |

#### Field chính

| Bảng | Field chính |
|---|---|
| `ai_conversations` | `user_id`, `title`, `status`, `deleted_at`. |
| `ai_messages` | `ai_conversation_id`, `role`, `content`, `metadata`, `deleted_at`. |
| `ai_feedbacks` | `ai_message_id`, `user_id`, `rating`, `comment`. |

#### Luồng nghiệp vụ

1. User hỏi AI.
2. Message user và assistant được lưu vào `ai_messages`.
3. User xóa lịch sử thì soft delete conversation/message.
4. Hành động xóa sẽ được service ghi `audit_logs` sau này.

#### Nguyên tắc

- AI chỉ gợi ý/hỗ trợ người dùng.
- AI không tự hoàn tiền, khóa user, sửa dữ liệu, duyệt/từ chối.
- Không trộn AI history với chat người dùng/chủ sân trong `conversations/messages`.

### 4. Nhóm User Wallet và Owner Wallet

#### Bảng tận dụng

| Bảng | Vai trò |
|---|---|
| `owner_wallets` | Ví chủ sân hiện có, giữ nguyên. |
| `owner_wallet_ledgers` | Sổ biến động ví chủ sân, giữ và mở rộng. |
| `owner_bank_accounts` | Tài khoản nhận tiền của chủ sân. |
| `owner_withdrawal_requests` | Yêu cầu rút tiền của chủ sân. |

#### Bảng mới cho ví user

| Bảng | Mục đích |
|---|---|
| `user_wallets` | Ví nội bộ của user. |
| `user_wallet_ledgers` | Lịch sử biến động ví user. |
| `user_payout_accounts` | Tài khoản ngân hàng user dùng nhận tiền khi rút/refund. |
| `user_withdrawal_requests` | Yêu cầu rút tiền từ ví user. |

#### Field chính

| Bảng | Field chính |
|---|---|
| `user_wallets` | `user_id`, `balance`, `locked_balance`, `status`. |
| `user_wallet_ledgers` | `transaction_code`, `type`, `direction`, `amount`, `balance_before`, `balance_after`, `reference_type`, `reference_id`, `status`. |
| `user_payout_accounts` | `bank_name`, `bank_account_number`, `bank_account_holder`, `bank_branch`, `is_default`, `status`. |
| `user_withdrawal_requests` | `user_wallet_id`, `payout_account_id`, `amount`, `status`, `approved_by`, `paid_by`, `requested_at`, `approved_at`, `paid_at`. |

#### Mở rộng `owner_wallet_ledgers`

| Field | Ý nghĩa |
|---|---|
| `direction` | credit/debit để đối soát rõ chiều biến động. |
| `status` | pending/completed/failed/cancelled. |
| `reference_type` | Loại đối tượng tham chiếu như booking, payment, refund, withdrawal. |
| `reference_id` | ID đối tượng tham chiếu. |
| `transaction_code` | Mã giao dịch ví nội bộ. |
| `note` | Ghi chú nghiệp vụ ngắn. |

#### Luồng ví user

1. User có ví trong `user_wallets`.
2. User thanh toán booking bằng ví.
3. Nếu ví thiếu tiền, service sau này tạo payment gateway cho phần còn lại.
4. Refund có thể hoàn về ví user hoặc về `user_payout_accounts`.
5. User rút tiền từ ví qua `user_withdrawal_requests`.
6. Mọi biến động tiền ghi `user_wallet_ledgers` với `balance_before` và `balance_after`.

#### Luồng ví chủ sân

1. Booking online paid thì cộng doanh thu vào `owner_wallets`.
2. `owner_wallet_ledgers.venue_cluster_id` tiếp tục dùng để đối soát theo cụm sân.
3. Refund completed có thể trừ ví chủ sân nếu nghiệp vụ yêu cầu.
4. Owner tạo `owner_withdrawal_requests`.
5. Admin xử lý rút tiền.
6. Mọi biến động tiền ghi ledger và audit log.

### 5. Nhóm Voucher

#### Bảng mới

| Bảng | Mục đích |
|---|---|
| `vouchers` | Lưu voucher hệ thống và voucher sân. |
| `voucher_scopes` | Giới hạn phạm vi áp dụng voucher. |
| `voucher_usages` | Ghi nhận voucher đã áp dụng cho booking/payment nào. |

#### Field chính `vouchers`

| Field | Ý nghĩa |
|---|---|
| `owner_type` | system hoặc venue. |
| `owner_id` | ID owner/cụm sân sở hữu voucher nếu là voucher sân. |
| `funded_by` | Bên chịu tiền giảm: system hoặc venue. |
| `stacking_rule` | exclusive, allow_with_system, allow_with_venue. |
| `discount_type` | percent hoặc fixed. |
| `discount_value` | Giá trị giảm. |
| `max_discount_amount` | Mức giảm tối đa. |
| `min_order_amount` | Giá trị đơn tối thiểu. |
| `total_quantity`, `used_quantity`, `per_user_limit` | Kiểm soát số lượng dùng. |
| `valid_from`, `valid_to`, `status` | Kiểm soát hiệu lực. |

`voucher_scopes` có thêm `scope_key` để unique ổn định cả trường hợp `scope_id` null, ví dụ scope `all`.

`voucher_usages` unique theo `voucher_id + user_id + booking_id` để tránh một user áp cùng một voucher nhiều lần vào cùng một booking.

#### Snapshot discount trên `bookings`

| Field | Ý nghĩa |
|---|---|
| `original_amount` | Tổng tiền trước khi áp voucher. |
| `discount_amount` | Tổng tiền giảm. |
| `system_discount_amount` | Phần giảm nền tảng chịu. |
| `venue_discount_amount` | Phần giảm chủ sân/cụm sân chịu. |
| `final_amount` | Số tiền cuối sau giảm. |
| `voucher_id` | Voucher chính nếu chỉ cho một voucher/booking. |
| `voucher_code_snapshot` | Mã voucher tại thời điểm đặt. |

#### Luồng nghiệp vụ

1. Admin tạo voucher hệ thống, `funded_by=system`.
2. Owner tạo voucher sân, `funded_by=venue`.
3. Backend validate thời hạn, scope, số lượng, per-user limit.
4. Khi áp dụng thành công, ghi snapshot trên `bookings` và ghi `voucher_usages`.
5. Booking bị hủy/refund thì cập nhật `voucher_usages.status`.

#### Lưu ý

- Voucher hệ thống là nền tảng chịu tiền giảm.
- Voucher sân là chủ sân/cụm sân chịu tiền giảm.
- Snapshot discount bắt buộc để đối soát đơn cũ nếu voucher sau này bị sửa/xóa.
- Nếu sau này cho nhiều voucher/booking thì `voucher_usages` là nguồn chính.

### 6. Nhóm Backup Data

#### Bảng mới

| Bảng | Mục đích |
|---|---|
| `backup_jobs` | Lưu metadata file backup và trạng thái job backup. |

#### Field chính

| Field | Ý nghĩa |
|---|---|
| `backup_code` | Mã backup để admin tra cứu. |
| `file_name`, `file_path`, `disk` | Vị trí file backup ngoài DB. |
| `size_bytes`, `checksum` | Dung lượng và checksum kiểm tra file. |
| `type` | manual hoặc auto. |
| `status` | pending, running, completed, failed. |
| `created_by` | Admin tạo backup thủ công. |
| `started_at`, `completed_at`, `error_message` | Trạng thái chạy job. |
| `retention_days` | Số ngày giữ file backup. |

#### Luồng nghiệp vụ

1. Admin tạo backup thủ công.
2. Hệ thống tạo file backup ngoài DB/storage riêng.
3. Metadata file được ghi vào `backup_jobs`.
4. Admin xem danh sách và tải file backup.
5. Hành động tạo/tải backup cần ghi `audit_logs`.
6. Giai đoạn hiện tại chưa thiết kế restore tự động.

### 7. Seeder mới sau cập nhật 2026-06-01

| Seeder | Nội dung |
|---|---|
| `PolicyRulesTableSeeder` | Seed policy hệ thống, action bindings và policy rules mẫu cho refund, report, account lock, venue fee, first login policy. |
| `VouchersTableSeeder` | Seed voucher hệ thống `SPORTGO10` và voucher sân demo `VENUE20` nếu có cụm sân active. |
| `UserWalletsTableSeeder` | Tạo ví user demo, tài khoản nhận tiền user demo và ledger khởi tạo. |

Không seed backup thật vì backup phải trỏ tới file thật ngoài DB. Không bắt buộc seed AI chat vì AI không tham gia quyết định nghiệp vụ.

### 8. Bảng tận dụng sau cập nhật

- `system_policies`
- `user_policy_acceptances`
- `audit_logs`
- `owner_wallets`
- `owner_wallet_ledgers`
- `owner_bank_accounts`
- `owner_withdrawal_requests`
- `payments`
- `payment_logs`
- `refunds`
- `bookings`
- `booking_items`
- `venue_clusters`
- `system_bank_accounts`
- `internal_receipts`
- `media`

### 9. Không làm SEO

Không thêm bảng SEO. Các field hiện có như `venue_clusters.slug` chỉ giữ để phục vụ URL/định danh hiện tại.

### 10. Bổ sung liên kết payments/refunds sau review quan hệ 2026-06-01

Sau khi rà soát luồng booking, refund, wallet và payment, DB bổ sung thêm migration `2026_06_01_000007_extend_payments_and_refunds_for_wallet_relations` để tránh thiếu liên kết khi triển khai service sau này.

#### Mở rộng `payments`

| Field | Ý nghĩa |
|---|---|
| `wallet_amount` | Phần tiền thanh toán bằng ví user. |
| `gateway_amount` | Phần tiền thanh toán qua gateway/chuyển khoản. |
| `user_wallet_id` | Ví user dùng cho payment nếu method là wallet hoặc mixed. |
| `user_wallet_ledger_id` | Ledger debit ví user liên quan payment. |

`payments.method` bổ sung khả năng lưu `wallet` và `mixed` để đối soát trường hợp:

- User thanh toán toàn bộ bằng ví.
- User thanh toán một phần bằng ví, phần còn lại qua gateway.

#### Mở rộng `refunds`

| Field | Ý nghĩa |
|---|---|
| `customer_id` | User nhận hoàn tiền, denormalized từ booking. |
| `refund_destination` | Đích hoàn tiền: original_payment, user_wallet, bank_account. |
| `user_wallet_id` | Ví user nhận tiền hoàn nếu hoàn về ví. |
| `user_wallet_ledger_id` | Ledger credit ví user khi hoàn tiền vào ví. |
| `user_payout_account_id` | Tài khoản ngân hàng user nhận tiền nếu hoàn về bank. |
| `owner_wallet_ledger_id` | Ledger debit ví owner nếu refund làm giảm doanh thu owner. |
| `owner_confirmed_by`, `owner_confirmed_at`, `owner_confirm_note` | Chủ sân/nhân viên sân xác nhận hoàn tiền. |
| `admin_confirmed_by`, `admin_confirmed_at` | Admin xác nhận refund hoàn tất. |
| `gateway_refund_txn_id` | Mã giao dịch hoàn tiền từ gateway nếu có. |

#### Quan hệ bổ sung

- `payments.user_wallet_id` -> `user_wallets.id`.
- `payments.user_wallet_ledger_id` -> `user_wallet_ledgers.id`.
- `refunds.customer_id` -> `users.id`.
- `refunds.user_wallet_id` -> `user_wallets.id`.
- `refunds.user_wallet_ledger_id` -> `user_wallet_ledgers.id`.
- `refunds.user_payout_account_id` -> `user_payout_accounts.id`.
- `refunds.owner_wallet_ledger_id` -> `owner_wallet_ledgers.id`.
- `refunds.owner_confirmed_by` -> `users.id`.
- `refunds.admin_confirmed_by` -> `users.id`.

Các FK trên dùng `onDelete set null` để không cascade mất dữ liệu tài chính/log khi tài khoản hoặc ledger liên quan bị xử lý.

### 11. Ma trận liên hệ các luồng bắt buộc

| Luồng nghiệp vụ | Bảng chính | Bảng liên quan | FK/reference chính | Trạng thái |
|---|---|---|---|---|
| `booking.cancel` theo policy | `bookings` | `policy_action_bindings`, `system_policies`, `policy_rules`, `venue_policy_rules`, `policy_evaluation_logs`, `audit_logs`, `refunds` | `action_code=booking.cancel`, `bookings.venue_cluster_id`, `policy_evaluation_logs.entity_type/entity_id`, `audit_logs.policy_*` | OK |
| `refund.request` / owner confirm / admin confirm | `refunds` | `bookings`, `payments`, `policy_rules`, `policy_evaluation_logs`, `user_wallets`, `user_wallet_ledgers`, `user_payout_accounts`, `owner_wallet_ledgers`, `audit_logs` | `refunds.booking_id`, `payment_id`, `customer_id`, `user_wallet_ledger_id`, `owner_wallet_ledger_id` | OK |
| Booking thanh toán bằng ví user | `payments` | `user_wallets`, `user_wallet_ledgers`, `bookings`, `audit_logs` | `payments.user_wallet_id`, `payments.user_wallet_ledger_id`, `user_wallet_ledgers.reference_type/reference_id` | OK |
| Booking paid cộng doanh thu owner | `owner_wallet_ledgers` | `bookings`, `payments`, `venue_clusters`, `owner_wallets`, `audit_logs` | `owner_wallet_ledgers.booking_id`, `payment_id`, `venue_cluster_id`, `reference_type/reference_id` | OK |
| User rút tiền | `user_withdrawal_requests` | `user_wallets`, `user_payout_accounts`, `user_wallet_ledgers`, `audit_logs` | `user_wallet_id`, `payout_account_id`, ledger pending/locked balance | OK |
| Owner rút tiền | `owner_withdrawal_requests` | `owner_wallets`, `owner_bank_accounts`, `owner_wallet_ledgers`, `audit_logs` | `owner_wallet_id`, `owner_bank_account_id`, ledger hold/release/debit | OK |
| Voucher áp dụng booking/payment | `vouchers` | `voucher_scopes`, `voucher_usages`, `bookings`, `payments`, `audit_logs` | `voucher_usages.voucher_id/user_id/booking_id/payment_id`, `bookings.voucher_id`, snapshot amount | OK |
| Venue policy override system policy | `venue_policy_rules` | `venue_clusters`, `policy_rules`, `system_policies`, `policy_evaluation_logs` | `venue_cluster_id`, `base_policy_rule_id`, `system_policies.is_overridable` | OK |
| System tự khóa user/cụm sân | `policy_evaluation_logs` | `reports`, `complaints`, `venue_platform_fee_ledgers`, `users`, `venue_clusters`, `audit_logs` | `input_data`, `result_data`, `audit_logs.actor_type=system`, `policy_*` | OK |
| First login accept policy | `user_policy_acceptances` | `users`, `system_policies`, `policy_action_bindings`, `audit_logs` | `user_id`, `system_policy_id`, `policy_version`, `action_code=first_login.accept_policy` | OK |
| Backup | `backup_jobs` | `users`, `audit_logs` | `backup_jobs.created_by`, audit action tạo/tải backup | OK |

### 12. Ghi chú đối soát và ràng buộc

- Tiền dùng `decimal`, không dùng float/double.
- Ledger ví user và owner đều có `balance_before`, `balance_after`.
- Các bảng tài chính/ledger dùng FK `restrict` hoặc `set null`, không dùng cascade làm mất dữ liệu đối soát.
- `reference_type/reference_id` dùng để trỏ về `booking`, `payment`, `refund`, `withdrawal` khi cần đối soát rộng.
- `voucher_usages` là nguồn chính nếu sau này hỗ trợ nhiều voucher trên một booking.
- `voucher_scopes.scope_key` dùng để tránh duplicate scope `all` do SQL cho phép nhiều giá trị null trong unique index.
- Snapshot discount trên `bookings` đảm bảo đơn cũ không bị sai khi voucher bị sửa/xóa.
- `policy_evaluation_logs.input_data/result_data` phải lưu đủ dữ liệu tính toán như số report, unique reporter, số giờ trước giờ chơi, số ngày quá hạn.
- `audit_logs` tham chiếu được policy/rule/evaluation log để giải thích vì sao hệ thống gợi ý hoặc thực hiện hành động.
- Audit log không phải backup; backup thật nằm ở file ngoài DB và metadata trong `backup_jobs`.
- AI chỉ lưu lịch sử trò chuyện, không có quyền thao tác nghiệp vụ.
- Khi xung đột rule, service sau này phải ưu tiên rule hệ thống trước rule sân.

## Cập nhật 01/06/2026 - Admin Policy và Role Permission

### Migration bổ sung

Migration `2026_06_01_000008_extend_policy_management_fields.php` bổ sung các field còn thiếu cho module Admin Policy Management:

- `system_policies`: `effective_to`, `published_at`, `published_by`, `replaced_policy_id`, `require_reaccept`, `change_summary`.
- `policy_rules`: `decision_key`, `conflict_group`, `constraint_json`, `allowed_override_json`, `created_by`, `updated_by`.
- `venue_policy_rules`: `updated_by`.
- `policy_evaluation_logs`: `policy_version_snapshot`, `rule_snapshot`.
- `user_policy_acceptances`: `ip_address`, `user_agent`.

### Ràng buộc và index bổ sung

- `system_policies.published_by` tham chiếu `users.id`.
- `system_policies.replaced_policy_id` tham chiếu version chính sách cũ trong `system_policies.id`.
- `policy_rules.created_by`, `policy_rules.updated_by` tham chiếu `users.id`.
- `venue_policy_rules.updated_by` tham chiếu `users.id`.
- Thêm index lookup cho publish/conflict rule: `policy_rules(action_code, rule_type, is_active, priority)` và `policy_rules(action_code, decision_key, conflict_group)`.
- Thêm index tra cứu log áp dụng policy: `policy_evaluation_logs(action_code, entity_type, entity_id, created_at)`.

### Module backend/UI dùng các bảng hiện có

- Admin Policy Management dùng `system_policies`, `policy_action_bindings`, `policy_rules`, `venue_policy_rules`, `policy_evaluation_logs`, `notifications`, `audit_logs`.
- Admin Role Permission Management dùng `roles`, `permissions`, `role_permissions`, `user_roles`, `audit_logs`.
- Permission seed bổ sung: `policy.view`, `policy.create`, `policy.update`, `policy.publish`, `policy.rule.manage`, `role.create`, `role.update`, `role.delete`, `role.permission.manage`.

### MODULE: POLICY BỔ SUNG (08/06/2026)

## Tên bảng: policy_rule_templates

### 1. Mục đích bảng
Lưu danh mục mẫu rule để admin cấu hình nhanh khi tạo policy rule mới. Template chứa sẵn action_code, schema JSON cho condition/result, mức rủi ro và khả năng override bởi sân.

### 2. Danh sách trường

| STT | Tên trường | Kiểu dữ liệu | Null | Default | Khóa/Ràng buộc | Mô tả tác dụng trường | Ví dụ |
|---|---|---|---|---|---|---|---|
| 1 | id | bigint | Không | auto | PK | ID tự tăng | 1 |
| 2 | policy_type | varchar(50) | Không | - | Unique (cùng rule_code) | Loại chính sách: terms, booking_cancellation, refund, platform_fee, venue_policy... | refund |
| 3 | rule_code | varchar(100) | Không | - | Unique (cùng policy_type) | Mã rule duy nhất trong cùng loại chính sách | refund_percent_by_cancel_time |
| 4 | rule_name | varchar(255) | Không | - | - | Tên hiển thị của rule | Hoàn tiền theo thời điểm hủy |
| 5 | description | text | Có | null | - | Mô tả chi tiết template rule | Template rule dùng để admin cấu hình... |
| 6 | action_code | varchar(100) | Không | - | Index | Mã action nghiệp vụ mà rule áp dụng | refund.request |
| 7 | condition_schema | json | Có | null | - | JSON Schema cho điều kiện rule | {"type": "object"} |
| 8 | result_schema | json | Có | null | - | JSON Schema cho kết quả rule | {"type": "object"} |
| 9 | is_venue_overridable | boolean | Không | false | - | Sân có được override rule này không | true |
| 10 | risk_level | enum | Không | medium | - | Mức rủi ro: low, medium, high, critical | high |
| 11 | is_active | boolean | Không | true | Index | Còn hiệu lực không | true |

### 3. Khóa chính, khóa ngoại, index
- PK: id
- Unique: (policy_type, rule_code)
- Index: (policy_type, is_active), action_code

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

## Tên bảng: policy_override_constraints

### 1. Mục đích bảng
Định nghĩa ràng buộc mà chủ sân phải tuân theo khi override chính sách hệ thống. Ví dụ: mức hoàn tiền tối thiểu 80%, chủ sân không được giảm dưới mức này.

### 2. Danh sách trường

| STT | Tên trường | Kiểu dữ liệu | Null | Default | Khóa/Ràng buộc | Mô tả tác dụng trường | Ví dụ |
|---|---|---|---|---|---|---|---|
| 1 | id | bigint | Không | auto | PK | ID tự tăng | 1 |
| 2 | system_policy_id | char(36) | Không | - | FK, Unique (cùng constraint_key) | ID chính sách hệ thống | UUID-refund-policy |
| 3 | policy_rule_id | char(36) | Có | null | FK | ID rule cụ thể bị ràng buộc (nullable nếu áp dụng toàn bộ policy) | UUID-rule |
| 4 | rule_code | varchar(100) | Không | - | Index | Mã rule để lookup nhanh | refund_percent_by_cancel_time |
| 5 | constraint_key | varchar(100) | Không | - | Unique (cùng system_policy_id) | Mã ràng buộc duy nhất trong policy | refund_percent_minimum |
| 6 | constraint_name | varchar(255) | Không | - | - | Tên hiển thị ràng buộc | Mức hoàn tiền tối thiểu |
| 7 | comparison_direction | enum | Không | - | - | Hướng so sánh: exact_only, venue_can_be_more_favorable_to_customer, venue_can_be_stricter_for_safety, venue_can_only_choose_within_range | venue_can_be_more_favorable_to_customer |
| 8 | min_value | decimal(12,2) | Có | null | - | Giá trị tối thiểu cho phép | 80.00 |
| 9 | max_value | decimal(12,2) | Có | null | - | Giá trị tối đa cho phép | 100.00 |
| 10 | allowed_values | json | Có | null | - | Danh sách giá trị cho phép (cho exact_only) | [true] |
| 11 | message_vi | text | Không | - | - | Thông báo lỗi tiếng Việt khi vi phạm | Chính sách sân không được hoàn thấp hơn... |
| 12 | is_active | boolean | Không | true | - | Còn hiệu lực không | true |

### 3. Khóa chính, khóa ngoại, index
- PK: id
- Unique: (system_policy_id, constraint_key)
- FK: system_policy_id → system_policies.id (restrict), policy_rule_id → policy_rules.id (set null)
- Index: (rule_code, is_active)

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

## Tên bảng: policy_status_histories

### 1. Mục đích bảng
Ghi nhận lịch sử mỗi lần chính sách hệ thống đổi trạng thái (draft → active, active → inactive, thay đổi version...).

### 2. Danh sách trường

| STT | Tên trường | Kiểu dữ liệu | Null | Default | Khóa/Ràng buộc | Mô tả tác dụng trường | Ví dụ |
|---|---|---|---|---|---|---|---|
| 1 | id | bigint | Không | auto | PK | ID tự tăng | 1 |
| 2 | system_policy_id | char(36) | Không | - | FK, Index | ID chính sách | UUID-policy |
| 3 | old_status | varchar(50) | Có | null | - | Trạng thái cũ (null khi tạo mới) | null |
| 4 | new_status | varchar(50) | Không | - | - | Trạng thái mới | active |
| 5 | changed_by | char(36) | Có | null | FK | Admin thay đổi | UUID-admin |
| 6 | actor_type | varchar(50) | Không | admin | - | Loại tác nhân: admin, system | admin |
| 7 | reason | text | Có | null | - | Lý do thay đổi | Publish chính sách hoàn tiền v1 |
| 8 | created_at | timestamp | Không | CURRENT | - | Thời điểm thay đổi | 2026-06-08 10:00:00 |

### 3. Khóa chính, khóa ngoại, index
- PK: id
- FK: system_policy_id → system_policies.id (restrict), changed_by → users.id (set null)
- Index: (system_policy_id, created_at)

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

### MODULE: REFUND BỔ SUNG (08/06/2026)

## Tên bảng: refund_status_histories

### 1. Mục đích bảng
Ghi nhận từng bước xử lý hoàn tiền: khách yêu cầu → owner xác nhận → admin xử lý → gateway hoàn → hoàn tất.

### 2. Danh sách trường

| STT | Tên trường | Kiểu dữ liệu | Null | Default | Khóa/Ràng buộc | Mô tả tác dụng trường | Ví dụ |
|---|---|---|---|---|---|---|---|
| 1 | id | bigint | Không | auto | PK | ID tự tăng | 1 |
| 2 | refund_id | char(36) | Không | - | FK, Index | ID refund | UUID-refund |
| 3 | old_status | varchar(50) | Có | null | - | Trạng thái cũ | pending_owner_confirmation |
| 4 | new_status | varchar(50) | Không | - | Index | Trạng thái mới | owner_confirmed |
| 5 | changed_by | char(36) | Có | null | FK | Người thay đổi | UUID-owner |
| 6 | actor_type | varchar(50) | Không | system | - | Loại tác nhân: customer, owner, admin, system | owner |
| 7 | reason | text | Có | null | - | Lý do/ghi chú | Chủ sân xác nhận yêu cầu hoàn tiền |
| 8 | metadata | json | Có | null | - | Dữ liệu bổ sung | {"confirm_method": "manual"} |
| 9 | created_at | timestamp | Không | CURRENT | - | Thời điểm thay đổi | 2026-06-08 10:00:00 |

### 3. Khóa chính, khóa ngoại, index
- PK: id
- FK: refund_id → refunds.id (restrict), changed_by → users.id (set null)
- Index: (refund_id, created_at), new_status

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

### MODULE: HỒ SƠ ĐỐI TÁC BỔ SUNG (08/06/2026)

## Tên bảng: partner_application_documents

### 1. Mục đích bảng
Lưu file/tài liệu đính kèm hồ sơ đối tác: ảnh mặt tiền sân, CCCD, giấy đăng ký kinh doanh, hợp đồng thuê mặt bằng, chứng từ ngân hàng. Mỗi file được admin review và đánh dấu verified/rejected.

### 2. Danh sách trường

| STT | Tên trường | Kiểu dữ liệu | Null | Default | Khóa/Ràng buộc | Mô tả tác dụng trường | Ví dụ |
|---|---|---|---|---|---|---|---|
| 1 | id | bigint | Không | auto | PK | ID tự tăng | 1 |
| 2 | partner_application_id | char(36) | Không | - | FK, Index | ID hồ sơ đối tác | UUID-app |
| 3 | media_id | char(36) | Có | null | FK | ID file media (nếu dùng bảng media) | UUID-media |
| 4 | document_type | varchar(100) | Không | - | Index | Loại tài liệu: venue_front_image, identity_front, business_registration... | identity_front |
| 5 | document_group | varchar(100) | Không | - | Index | Nhóm tài liệu: venue_images, identity_documents, business_documents, land_documents, bank_documents | identity_documents |
| 6 | title | varchar(255) | Không | - | - | Tên hiển thị của tài liệu | CCCD mặt trước |
| 7 | description | text | Có | null | - | Mô tả chi tiết | File seed dùng để kiểm tra... |
| 8 | file_path | varchar(1000) | Có | null | - | Đường dẫn file trên storage | /seed/partner-applications/1/identity-front.jpg |
| 9 | status | enum | Không | uploaded | Index | Trạng thái: uploaded, verified, rejected | verified |
| 10 | reviewed_by | char(36) | Có | null | FK | Admin review file | UUID-admin |
| 11 | reviewed_at | timestamp | Có | null | - | Thời điểm review | 2026-06-05 10:00:00 |
| 12 | reject_reason | text | Có | null | - | Lý do từ chối file | Chứng từ không khớp tên... |
| 13 | sort_order | unsigned int | Không | 0 | - | Thứ tự hiển thị | 1 |

### 3. Khóa chính, khóa ngoại, index
- PK: id
- FK: partner_application_id → partner_applications.id (restrict), media_id → media.id (set null), reviewed_by → users.id (set null)
- Index: (partner_application_id, document_group), (document_type, status)

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

## Tên bảng: partner_application_status_histories

### 1. Mục đích bảng
Ghi nhận lịch sử đổi trạng thái hồ sơ đối tác: submitted → reviewing → approved_pending_contract → contract_pending_owner_signature...

### 2. Danh sách trường

| STT | Tên trường | Kiểu dữ liệu | Null | Default | Khóa/Ràng buộc | Mô tả tác dụng trường | Ví dụ |
|---|---|---|---|---|---|---|---|
| 1 | id | bigint | Không | auto | PK | ID tự tăng | 1 |
| 2 | partner_application_id | char(36) | Không | - | FK, Index | ID hồ sơ đối tác | UUID-app |
| 3 | old_status | varchar(50) | Có | null | - | Trạng thái cũ | submitted |
| 4 | new_status | varchar(50) | Không | - | - | Trạng thái mới | reviewing |
| 5 | changed_by | char(36) | Có | null | FK | Người thay đổi | UUID-admin |
| 6 | actor_type | varchar(50) | Không | admin | - | Loại tác nhân: admin, owner, system | admin |
| 7 | reason | text | Có | null | - | Lý do | Admin tiếp nhận hồ sơ |
| 8 | metadata | json | Có | null | - | Dữ liệu bổ sung | null |
| 9 | created_at | timestamp | Không | CURRENT | - | Thời điểm thay đổi | 2026-06-08 |

### 3. Khóa chính, khóa ngoại, index
- PK: id
- FK: partner_application_id → partner_applications.id (restrict), changed_by → users.id (set null)
- Index: (partner_application_id, created_at)

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

### MODULE: TEMPLATE VĂN BẢN (08/06/2026)

## Tên bảng: document_templates

### 1. Mục đích bảng
Lưu template DOCX cho các loại văn bản: đơn đăng ký đối tác, hợp đồng, đơn chấm dứt, biên bản thanh lý, công văn đơn phương, biên bản quyết toán. Mỗi loại có nhiều version, chỉ 1 version active.

### 2. Danh sách trường

| STT | Tên trường | Kiểu dữ liệu | Null | Default | Khóa/Ràng buộc | Mô tả tác dụng trường | Ví dụ |
|---|---|---|---|---|---|---|---|
| 1 | id | char(36) | Không | - | PK | UUID template | UUID-template |
| 2 | template_code | varchar(100) | Không | - | Unique | Mã template duy nhất | TPL-HD-V1 |
| 3 | document_type | varchar(100) | Không | - | Unique (cùng version), Index | Loại văn bản | partner_contract |
| 4 | template_name | varchar(255) | Không | - | - | Tên hiển thị | Mẫu hợp đồng đối tác v1 |
| 5 | version | unsigned int | Không | 1 | Unique (cùng document_type) | Phiên bản template | 1 |
| 6 | file_name | varchar(255) | Không | - | - | Tên file gốc | hop-dong-doi-tac.docx |
| 7 | file_path | varchar(1000) | Không | - | - | Đường dẫn trên storage | document-templates/hop-dong-doi-tac.docx |
| 8 | output_format | enum | Không | docx | - | Định dạng đầu ra: docx, pdf | docx |
| 9 | mime_type | varchar(150) | Không | application/vnd... | - | MIME type | application/vnd.openxmlformats-officedocument... |
| 10 | storage_disk | varchar(50) | Không | local | - | Disk storage Laravel | local |
| 11 | template_variables | json | Có | null | - | Danh sách biến placeholder | ["contract_code", "owner_full_name"] |
| 12 | required_fields | json | Có | null | - | Các field bắt buộc phải có khi render | ["owner_full_name", "business_name"] |
| 13 | render_engine | enum | Không | docx_placeholder | - | Engine render: docx_placeholder, manual_upload, pdf_static | docx_placeholder |
| 14 | status | enum | Không | draft | Index | Trạng thái: draft, active, inactive, archived | active |
| 15 | is_active | boolean | Không | false | Index | Đang được dùng cho văn bản mới | true |
| 16 | created_by | char(36) | Có | null | FK | Người tạo template | UUID-admin |
| 17 | uploaded_by | char(36) | Có | null | FK | Người upload file | UUID-admin |
| 18 | activated_at | timestamp | Có | null | - | Thời điểm kích hoạt | 2026-06-08 |
| 19 | replaced_template_id | char(36) | Có | null | FK (self) | Template version cũ bị thay thế | UUID-old-template |
| 20 | note | text | Có | null | - | Ghi chú | Template ban đầu |

### 3. Khóa chính, khóa ngoại, index
- PK: id
- Unique: template_code, (document_type, version)
- FK: created_by → users.id (set null), uploaded_by → users.id (set null), replaced_template_id → document_templates.id (set null)
- Index: (document_type, status, is_active)

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

## Tên bảng: generated_documents

### 1. Mục đích bảng
Lưu văn bản đã sinh từ template, bao gồm snapshot dữ liệu render, file path và trạng thái ký. Mỗi văn bản liên kết polymorphic hoặc FK trực tiếp tới hồ sơ/hợp đồng/chấm dứt/quyết toán.

### 2. Danh sách trường

| STT | Tên trường | Kiểu dữ liệu | Null | Default | Khóa/Ràng buộc | Mô tả tác dụng trường | Ví dụ |
|---|---|---|---|---|---|---|---|
| 1 | id | char(36) | Không | - | PK | UUID văn bản | UUID-doc |
| 2 | document_code | varchar(50) | Không | - | Unique | Mã văn bản duy nhất | DOC-HD-CG-001 |
| 3 | document_type | varchar(100) | Không | - | Index | Loại văn bản | partner_contract |
| 4 | template_id | char(36) | Không | - | FK | Template dùng để sinh | UUID-template |
| 5 | template_version | unsigned int | Không | - | - | Version template tại thời điểm sinh | 1 |
| 6 | reference_type | varchar(100) | Có | null | Index | Polymorphic type | App\Models\PartnerApplication |
| 7 | reference_id | varchar(100) | Có | null | Index | Polymorphic ID | UUID-app |
| 8 | entity_type | varchar(100) | Có | null | Index | Entity type bổ sung | null |
| 9 | entity_id | varchar(100) | Có | null | Index | Entity ID bổ sung | null |
| 10 | partner_application_id | char(36) | Có | null | FK, Index | FK trực tiếp tới hồ sơ | UUID-app |
| 11 | partner_contract_id | char(36) | Có | null | FK, Index | FK trực tiếp tới hợp đồng | UUID-contract |
| 12 | partner_termination_request_id | char(36) | Có | null | FK, Index | FK trực tiếp tới yêu cầu chấm dứt | null |
| 13 | partner_settlement_id | char(36) | Có | null | FK, Index | FK trực tiếp tới quyết toán | null |
| 14 | owner_id | char(36) | Có | null | FK, Index | Chủ sân liên quan | UUID-owner |
| 15 | venue_cluster_id | char(36) | Có | null | FK, Index | Cụm sân liên quan | UUID-cluster |
| 16 | title | varchar(255) | Có | null | - | Tiêu đề văn bản | null |
| 17 | status | enum | Không | generated | Index | Trạng thái: draft, generated, pending_owner_signature, pending_sportgo_signature, signed, completed, cancelled | completed |
| 18 | render_data | json | Không | - | - | Snapshot toàn bộ dữ liệu render | {"contract_code": "HD-CG-001", ...} |
| 19 | generated_file_media_id | char(36) | Có | null | FK | Media ID file đã sinh | UUID-media |
| 20 | signed_file_media_id | char(36) | Có | null | FK | Media ID file đã ký | UUID-media |
| 21 | final_file_media_id | char(36) | Có | null | FK | Media ID file hoàn tất | UUID-media |
| 22 | generated_file_path | varchar(1000) | Có | null | - | Path file đã sinh | generated-documents/HD-CG-001.docx |
| 23 | final_file_path | varchar(1000) | Có | null | - | Path file hoàn tất | null |
| 24 | file_hash | varchar(128) | Có | null | - | Hash file để verify tính toàn vẹn | null |
| 25 | generated_by | char(36) | Có | null | FK | Người sinh văn bản | UUID-admin |
| 26 | generated_at | timestamp | Có | null | - | Thời điểm sinh | 2026-06-05 |
| 27 | locked_at | timestamp | Có | null | - | Thời điểm lock (không sửa được) | 2026-06-06 |
| 28 | completed_at | timestamp | Có | null | - | Thời điểm hoàn tất | 2026-06-07 |

### 3. Khóa chính, khóa ngoại, index
- PK: id
- Unique: document_code
- FK: template_id → document_templates.id (restrict), partner_application_id → partner_applications.id (set null), owner_id → users.id (set null), venue_cluster_id → venue_clusters.id (set null), generated_file_media_id/signed_file_media_id/final_file_media_id → media.id (set null), generated_by → users.id (set null)
- FK bổ sung (migration 000024): partner_contract_id → partner_contracts.id (set null), partner_termination_request_id → partner_termination_requests.id (set null), partner_settlement_id → partner_settlements.id (set null)
- Index: (reference_type, reference_id), (entity_type, entity_id), partner_application_id, partner_contract_id, partner_termination_request_id, partner_settlement_id, (owner_id, venue_cluster_id), (document_type, status)

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

## Tên bảng: generated_document_signatures

### 1. Mục đích bảng
Lưu chữ ký/xác nhận của mỗi bên (owner, SportGo) trên văn bản đã sinh. Hỗ trợ nhiều phương thức ký: upload ảnh, vẽ tay, gõ xác nhận, OTP, ký số.

### 2. Danh sách trường

| STT | Tên trường | Kiểu dữ liệu | Null | Default | Khóa/Ràng buộc | Mô tả tác dụng trường | Ví dụ |
|---|---|---|---|---|---|---|---|
| 1 | id | char(36) | Không | - | PK | UUID chữ ký | UUID-sig |
| 2 | generated_document_id | char(36) | Không | - | FK, Index | ID văn bản | UUID-doc |
| 3 | signer_side | enum | Không | - | Index | Bên ký: owner, sportgo, witness, system | owner |
| 4 | signer_user_id | char(36) | Có | null | FK | User thực hiện ký | UUID-owner |
| 5 | signer_full_name | varchar(255) | Không | - | - | Họ tên người ký | Nguyễn Văn Owner |
| 6 | signer_title | varchar(255) | Có | null | - | Chức danh | Chủ sân |
| 7 | signer_organization | varchar(255) | Có | null | - | Tổ chức | Hộ kinh doanh SportGo CG |
| 8 | signature_method | enum | Không | typed_confirm | - | Phương thức ký: uploaded_image, drawn, typed_confirm, otp_confirm, digital | typed_confirm |
| 9 | signature_media_id | char(36) | Có | null | FK | Media ID ảnh chữ ký (nếu upload/vẽ) | null |
| 10 | signed_at | timestamp | Có | null | - | Thời điểm ký | 2026-06-06 |
| 11 | ip_address | varchar(45) | Có | null | - | IP lúc ký | 192.168.1.1 |
| 12 | user_agent | varchar(500) | Có | null | - | User Agent lúc ký | Mozilla/5.0... |
| 13 | status | enum | Không | pending | Index | Trạng thái: pending, signed, rejected, cancelled | signed |
| 14 | reject_reason | text | Có | null | - | Lý do từ chối ký | null |

### 3. Khóa chính, khóa ngoại, index
- PK: id
- FK: generated_document_id → generated_documents.id (restrict), signer_user_id → users.id (set null), signature_media_id → media.id (set null)
- Index: (generated_document_id, signer_side, status)

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

### MODULE: HỢP ĐỒNG ĐỐI TÁC (08/06/2026)

## Tên bảng: partner_contracts

### 1. Mục đích bảng
Lưu hợp đồng giữa SportGo và chủ sân. Mỗi hợp đồng được sinh từ hồ sơ đối tác đã duyệt, link tới văn bản đã ký. Status: draft → generated → pending_owner_signature → pending_sportgo_signature → signed_active.

### 2. Danh sách trường

| STT | Tên trường | Kiểu dữ liệu | Null | Default | Khóa/Ràng buộc | Mô tả tác dụng trường | Ví dụ |
|---|---|---|---|---|---|---|---|
| 1 | id | char(36) | Không | - | PK | UUID hợp đồng | UUID-contract |
| 2 | contract_code | varchar(50) | Không | - | Unique | Mã hợp đồng | HD-CG-001 |
| 3 | partner_application_id | char(36) | Không | - | FK, Index | Hồ sơ đối tác sinh ra hợp đồng | UUID-app |
| 4 | owner_id | char(36) | Không | - | FK, Index | Chủ sân ký hợp đồng | UUID-owner |
| 5 | venue_cluster_id | char(36) | Có | null | FK | Cụm sân (nếu đã duyệt tạo) | UUID-cluster |
| 6 | contract_title | varchar(255) | Không | - | - | Tiêu đề hợp đồng | Hợp đồng hợp tác đối tác SportGo CG |
| 7 | status | enum | Không | draft | Index | Trạng thái: draft, generated, pending_owner_signature, pending_sportgo_signature, signed_active, cancelled, terminated | signed_active |
| 8 | generated_document_id | char(36) | Có | null | FK | Văn bản hợp đồng đã sinh | UUID-doc |
| 9 | generated_file_media_id | char(36) | Có | null | - | Media ID file đã sinh | null |
| 10 | signed_file_media_id | char(36) | Có | null | - | Media ID file đã ký | null |
| 11 | final_file_media_id | char(36) | Có | null | - | Media ID file hoàn tất | null |
| 12 | generated_by | char(36) | Có | null | FK | Admin sinh hợp đồng | UUID-admin |
| 13 | approved_by | char(36) | Có | null | FK | Admin duyệt | UUID-admin |
| 14 | owner_signed_at | timestamp | Có | null | - | Thời điểm chủ sân ký | 2026-06-06 |
| 15 | sportgo_signed_at | timestamp | Có | null | - | Thời điểm SportGo ký | 2026-06-07 |
| 16 | effective_from | timestamp | Có | null | - | Ngày bắt đầu hiệu lực | 2026-06-07 |
| 17 | effective_to | timestamp | Có | null | - | Ngày hết hạn (null = không thời hạn) | null |
| 18 | terminated_at | timestamp | Có | null | - | Ngày chấm dứt sớm | null |
| 19 | note | text | Có | null | - | Ghi chú | null |

### 3. Khóa chính, khóa ngoại, index
- PK: id
- Unique: contract_code
- FK: partner_application_id → partner_applications.id (restrict), owner_id → users.id (restrict), venue_cluster_id → venue_clusters.id (set null), generated_document_id → generated_documents.id (restrict), generated_by/approved_by → users.id (set null)
- Index: (partner_application_id, status), (owner_id, status)

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

### MODULE: CHẤM DỨT HỢP TÁC (08/06/2026)

## Tên bảng: partner_termination_requests

### 1. Mục đích bảng
Quản lý yêu cầu chấm dứt hợp tác: hai bên đồng ý (mutual_agreement), đơn phương bởi owner (unilateral_by_owner) hoặc đơn phương bởi SportGo (unilateral_by_sportgo). Có thời gian chuyển tiếp trước khi thu quyền.

### 2. Danh sách trường

| STT | Tên trường | Kiểu dữ liệu | Null | Default | Khóa/Ràng buộc | Mô tả tác dụng trường | Ví dụ |
|---|---|---|---|---|---|---|---|
| 1 | id | char(36) | Không | - | PK | UUID yêu cầu | UUID-term |
| 2 | termination_code | varchar(50) | Không | - | Unique | Mã yêu cầu chấm dứt | TERM-MUTUAL-CG-001 |
| 3 | partner_contract_id | char(36) | Không | - | FK, Index | Hợp đồng bị chấm dứt | UUID-contract |
| 4 | partner_application_id | char(36) | Có | null | FK | Hồ sơ đối tác gốc | UUID-app |
| 5 | owner_id | char(36) | Không | - | FK, Index | Chủ sân | UUID-owner |
| 6 | venue_cluster_id | char(36) | Có | null | FK | Cụm sân liên quan | UUID-cluster |
| 7 | termination_type | enum | Không | - | Index | Loại chấm dứt: mutual_agreement, unilateral_by_owner, unilateral_by_sportgo | mutual_agreement |
| 8 | requested_by | char(36) | Không | - | FK | Người gửi yêu cầu | UUID-owner |
| 9 | requested_at | timestamp | Không | CURRENT | - | Thời điểm gửi | 2026-06-08 |
| 10 | reason | text | Không | - | - | Lý do chấm dứt | Hai bên thống nhất chấm dứt hợp tác... |
| 11 | requested_effective_date | date | Có | null | - | Ngày mong muốn hiệu lực | 2026-07-08 |
| 12 | status | enum | Không | draft | Index | Trạng thái: draft, submitted, reviewing, approved, pending_signature, settlement_processing, settlement_completed, transition_period, completed, rejected, cancelled | settlement_completed |
| 13 | approved_by | char(36) | Có | null | FK | Admin duyệt | UUID-admin |
| 14 | approved_at | timestamp | Có | null | - | Thời điểm duyệt | 2026-06-09 |
| 15 | reject_reason | text | Có | null | - | Lý do từ chối | null |
| 16 | effective_termination_date | timestamp | Có | null | - | Ngày chấm dứt thực tế | 2026-07-08 |
| 17 | transition_end_at | timestamp | Có | null | - | Hết thời gian chuyển tiếp | 2026-08-08 |
| 18 | owner_access_revoked_at | timestamp | Có | null | - | Thời điểm thu quyền owner | null |

### 3. Khóa chính, khóa ngoại, index
- PK: id
- Unique: termination_code
- FK: partner_contract_id → partner_contracts.id (restrict), partner_application_id → partner_applications.id (set null), owner_id → users.id (restrict), venue_cluster_id → venue_clusters.id (set null), requested_by → users.id (restrict), approved_by → users.id (set null)
- Index: (partner_contract_id, status), (owner_id, status), (termination_type, status)

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

## Tên bảng: partner_termination_documents

### 1. Mục đích bảng
Lưu các văn bản liên quan đến yêu cầu chấm dứt: đơn chấm dứt, biên bản thanh lý, công văn đơn phương, biên bản quyết toán, file cuối cùng.

### 2. Danh sách trường

| STT | Tên trường | Kiểu dữ liệu | Null | Default | Khóa/Ràng buộc | Mô tả tác dụng trường | Ví dụ |
|---|---|---|---|---|---|---|---|
| 1 | id | bigint | Không | auto | PK | ID tự tăng | 1 |
| 2 | partner_termination_request_id | char(36) | Không | - | FK, Index | ID yêu cầu chấm dứt | UUID-term |
| 3 | generated_document_id | char(36) | Có | null | FK | Văn bản đã sinh từ template | UUID-doc |
| 4 | document_type | enum | Không | - | Index | Loại: owner_termination_request, mutual_liquidation_minutes, unilateral_notice, settlement_minutes, final_termination_file | mutual_liquidation_minutes |
| 5 | media_id | char(36) | Có | null | FK | File đính kèm bổ sung | null |
| 6 | file_path | varchar(1000) | Có | null | - | Đường dẫn file | null |
| 7 | status | enum | Không | generated | - | Trạng thái: generated, pending_signature, signed, completed, cancelled | completed |
| 8 | generated_by | char(36) | Có | null | FK | Admin sinh văn bản | UUID-admin |
| 9 | generated_at | timestamp | Có | null | - | Thời điểm sinh | 2026-06-08 |

### 3. Khóa chính, khóa ngoại, index
- PK: id
- FK: partner_termination_request_id → partner_termination_requests.id (restrict), generated_document_id → generated_documents.id (restrict), media_id → media.id (set null), generated_by → users.id (set null)
- Index: (partner_termination_request_id, document_type)

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

## Tên bảng: partner_termination_status_histories

### 1. Mục đích bảng
Ghi nhận từng bước xử lý yêu cầu chấm dứt hợp tác: submitted → reviewing → approved → settlement_processing → completed.

### 2. Danh sách trường

| STT | Tên trường | Kiểu dữ liệu | Null | Default | Khóa/Ràng buộc | Mô tả tác dụng trường | Ví dụ |
|---|---|---|---|---|---|---|---|
| 1 | id | bigint | Không | auto | PK | ID tự tăng | 1 |
| 2 | partner_termination_request_id | char(36) | Không | - | FK, Index | ID yêu cầu chấm dứt | UUID-term |
| 3 | old_status | varchar(50) | Có | null | - | Trạng thái cũ | submitted |
| 4 | new_status | varchar(50) | Không | - | - | Trạng thái mới | reviewing |
| 5 | changed_by | char(36) | Có | null | FK | Người thay đổi | UUID-admin |
| 6 | actor_type | varchar(50) | Không | admin | - | Loại tác nhân | admin |
| 7 | reason | text | Có | null | - | Lý do | Admin tiếp nhận yêu cầu |
| 8 | metadata | json | Có | null | - | Dữ liệu bổ sung | null |
| 9 | created_at | timestamp | Không | CURRENT | - | Thời điểm | 2026-06-08 |

### 3. Khóa chính, khóa ngoại, index
- PK: id
- FK: partner_termination_request_id → partner_termination_requests.id (restrict), changed_by → users.id (set null)
- Index: (partner_termination_request_id, created_at)

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

### MODULE: QUYẾT TOÁN (08/06/2026)

## Tên bảng: partner_settlements

### 1. Mục đích bảng
Lưu kết quả quyết toán công nợ khi chấm dứt hợp tác. Tính toán: ví owner, phí nền tảng còn hoàn, phí nền tảng chưa đóng, phạt, điều chỉnh → ra final_payable_to_owner hoặc final_receivable_from_owner.

### 2. Danh sách trường

| STT | Tên trường | Kiểu dữ liệu | Null | Default | Khóa/Ràng buộc | Mô tả tác dụng trường | Ví dụ |
|---|---|---|---|---|---|---|---|
| 1 | id | char(36) | Không | - | PK | UUID quyết toán | UUID-settle |
| 2 | settlement_code | varchar(50) | Không | - | Unique | Mã quyết toán | SETTLE-CG-001 |
| 3 | partner_termination_request_id | char(36) | Không | - | FK, Index | Yêu cầu chấm dứt | UUID-term |
| 4 | partner_contract_id | char(36) | Không | - | FK | Hợp đồng bị chấm dứt | UUID-contract |
| 5 | owner_id | char(36) | Không | - | FK, Index | Chủ sân | UUID-owner |
| 6 | venue_cluster_id | char(36) | Có | null | FK | Cụm sân | UUID-cluster |
| 7 | owner_wallet_available_amount | decimal(14,2) | Không | 0 | - | Số dư ví owner khả dụng | 2000000.00 |
| 8 | owner_wallet_pending_amount | decimal(14,2) | Không | 0 | - | Số dư ví owner đang chờ rút | 0.00 |
| 9 | platform_fee_remaining_refund_amount | decimal(14,2) | Không | 0 | - | Phí nền tảng đã đóng thừa cần hoàn | 200000.00 |
| 10 | unpaid_platform_fee_amount | decimal(14,2) | Không | 0 | - | Phí nền tảng chưa đóng | 0.00 |
| 11 | penalty_amount | decimal(14,2) | Không | 0 | - | Tiền phạt (nếu đơn phương) | 0.00 |
| 12 | adjustment_amount | decimal(14,2) | Không | 0 | - | Điều chỉnh thủ công bởi admin | 0.00 |
| 13 | final_payable_to_owner | decimal(14,2) | Không | 0 | - | Số tiền cuối cùng trả cho owner | 2200000.00 |
| 14 | final_receivable_from_owner | decimal(14,2) | Không | 0 | - | Số tiền owner còn nợ SportGo | 0.00 |
| 15 | status | enum | Không | draft | Index | Trạng thái: draft, calculated, pending_approval, approved, payout_created, completed, cancelled | completed |
| 16 | calculated_by | char(36) | Có | null | FK | Admin tính toán | UUID-admin |
| 17 | approved_by | char(36) | Có | null | FK | Admin duyệt quyết toán | UUID-admin |
| 18 | approved_at | timestamp | Có | null | - | Thời điểm duyệt | 2026-06-09 |
| 19 | note | text | Có | null | - | Ghi chú quyết toán | Quyết toán mẫu cho test |

### 3. Khóa chính, khóa ngoại, index
- PK: id
- Unique: settlement_code
- FK: partner_termination_request_id → partner_termination_requests.id (restrict), partner_contract_id → partner_contracts.id (restrict), owner_id → users.id (restrict), venue_cluster_id → venue_clusters.id (set null), calculated_by/approved_by → users.id (set null)
- Index: (partner_termination_request_id, status), (owner_id, status)

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

## Tên bảng: partner_settlement_items

### 1. Mục đích bảng
Lưu từng dòng chi tiết trong biên bản quyết toán: ví owner, rút tiền đang chờ, phí nền tảng hoàn, phí chưa đóng, phạt, điều chỉnh.

### 2. Danh sách trường

| STT | Tên trường | Kiểu dữ liệu | Null | Default | Khóa/Ràng buộc | Mô tả tác dụng trường | Ví dụ |
|---|---|---|---|---|---|---|---|
| 1 | id | bigint | Không | auto | PK | ID tự tăng | 1 |
| 2 | partner_settlement_id | char(36) | Không | - | FK, Index | ID quyết toán cha | UUID-settle |
| 3 | item_type | enum | Không | - | Index | Loại: owner_wallet_balance, pending_withdrawal, platform_fee_remaining_refund, unpaid_platform_fee, penalty, adjustment | owner_wallet_balance |
| 4 | description | text | Không | - | - | Mô tả dòng | Số dư ví owner hiện tại |
| 5 | amount | decimal(14,2) | Không | - | - | Số tiền | 2000000.00 |
| 6 | direction | enum | Không | - | - | Hướng: payable_to_owner hoặc receivable_from_owner | payable_to_owner |
| 7 | reference_type | varchar(100) | Có | null | - | Polymorphic type | App\Models\OwnerWallet |
| 8 | reference_id | varchar(100) | Có | null | - | Polymorphic ID | UUID-wallet |
| 9 | created_at | timestamp | Không | CURRENT | - | Thời điểm tạo | 2026-06-08 |

### 3. Khóa chính, khóa ngoại, index
- PK: id
- FK: partner_settlement_id → partner_settlements.id (restrict)
- Index: (partner_settlement_id, item_type)

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

### MODULE: GIỚI HẠN QUYỀN OWNER (08/06/2026)

## Tên bảng: venue_access_restrictions

### 1. Mục đích bảng
Giới hạn hoặc chặn quyền owner trên cụm sân. Ví dụ: quá hạn phí nền tảng → limited (hạn chế một số chức năng), chấm dứt hợp đồng → blocked (chặn toàn bộ).

### 2. Danh sách trường

| STT | Tên trường | Kiểu dữ liệu | Null | Default | Khóa/Ràng buộc | Mô tả tác dụng trường | Ví dụ |
|---|---|---|---|---|---|---|---|
| 1 | id | char(36) | Không | - | PK | UUID restriction | UUID-restriction |
| 2 | venue_cluster_id | char(36) | Không | - | FK, Index | Cụm sân bị giới hạn | UUID-cluster |
| 3 | restriction_type | enum | Không | - | Index | Loại: platform_fee_overdue, contract_termination, admin_manual | platform_fee_overdue |
| 4 | access_mode | enum | Không | limited | Index | Mức độ: full, limited, transition, blocked | limited |
| 5 | reason | text | Không | - | - | Lý do giới hạn | Phí duy trì nền tảng quá hạn 7 ngày |
| 6 | starts_at | timestamp | Không | - | - | Bắt đầu hiệu lực | 2026-06-01 |
| 7 | ends_at | timestamp | Có | null | - | Kết thúc (null = vô thời hạn) | null |
| 8 | created_by | char(36) | Có | null | FK | Admin/system tạo | UUID-admin |
| 9 | status | enum | Không | active | Index | Trạng thái: active, expired, cancelled | active |

### 3. Khóa chính, khóa ngoại, index
- PK: id
- FK: venue_cluster_id → venue_clusters.id (restrict), created_by → users.id (set null)
- Index: (venue_cluster_id, status), (restriction_type, access_mode)

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
