# Báo Cáo Thiết Kế Database Dự Án SportGo

Tài liệu này được cập nhật tự động từ các file migration trong `database/migrations`, kết hợp mô tả cột từ `comment(...)` và mô tả nghiệp vụ suy ra từ tên bảng/cột trong code.

- Ngày cập nhật: 2026-06-22 10:02:01 (Asia/Saigon)
- Số file migration đã đọc: 132
- Số bảng trong thiết kế hiện tại: 109

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
| 8 | failed_jobs | Laravel | Job thất bại | Lưu các queue job chạy lỗi để có thể điều tra và retry khi cần. | Không FK |
| 9 | audit_logs | System/Log | Lịch sử thao tác | Ghi nhận hành động nhạy cảm trong hệ thống | users (actor_id), system_policies (policy_id), policy_rules (policy_rule_id), policy_evaluation_logs (policy_evaluation_log_id) |
| 10 | banners | System | Quản lý banner | Banner quảng cáo, hiển thị trang chủ | users (created_by), users (updated_by) |
| 11 | venue_clusters | Venue | Lưu cụm sân | Lưu thông tin 1 cơ sở sân bãi (địa chỉ, tọa độ, chủ sân) | users (locked_by), users (owner_id) |
| 12 | booking_configs | Booking | Cấu hình đặt sân | Cấu hình quy định đặt sân (thời gian tối thiểu, tiền cọc) cho cụm sân | venue_clusters (venue_cluster_id) |
| 13 | court_types | Venue | Lưu loại sân thể thao | Quản lý loại môn/sân (vd: sân cầu lông, sân bóng đá 7 người) | court_types (parent_id) |
| 14 | venue_staff_assignments | Venue | Phân công nhân viên | Phân công nhân viên quản lý cụm sân hoặc loại sân cụ thể | users (assigned_by), court_types (court_type_id), users (user_id), venue_clusters (venue_cluster_id) |
| 15 | venue_courts | Venue | Lưu sân con thực tế | Các sân nhỏ bên trong 1 cụm sân để khách đặt | court_types (court_type_id), venue_clusters (venue_cluster_id) |
| 16 | community_posts | Community | Bài đăng cộng đồng | Người chơi đăng bài thảo luận tự do | users (author_id), users (reviewed_by) |
| 17 | venue_court_approval_requests | Venue | Xin duyệt tạo sân con | Lưu yêu cầu duyệt tạo sân con mới của chủ sân | court_types (court_type_id), users (requested_by), users (reviewed_by), venue_clusters (venue_cluster_id) |
| 18 | conversations | Chat | Cuộc hội thoại | Quản lý phòng chat (direct, post, venue) | users (created_by) |
| 19 | conversation_participants | Chat | Thành viên chat | Thành viên tham gia vào cuộc hội thoại | conversations (conversation_id), users (user_id) |
| 20 | partner_applications | System | Đơn đăng ký đối tác | Đơn xin làm chủ sân gửi cho admin duyệt | users (reviewed_by), users (user_id) |
| 21 | partner_application_courts | System | Môn thể thao đăng ký | Loại sân kinh doanh dự kiến của đơn đăng ký đối tác | court_types (court_type_id), partner_applications (partner_application_id) |
| 22 | roles | Auth/RBAC | Lưu các nhóm quyền | Lưu mã role để phân quyền (admin, venue_owner, customer...) | Không FK |
| 23 | bookings | Booking | Đơn đặt sân | Quản lý lịch đặt sân, giờ chơi, thanh toán, trạng thái | users (cancelled_by), users (created_by), users (customer_id), venue_courts (venue_court_id), venue_courts (requested_venue_court_id), users (court_changed_by), vouchers (voucher_id) |
| 24 | community_post_comments | Community | Bình luận cộng đồng | Bình luận trong các bài đăng cộng đồng | community_post_comments (parent_id), community_posts (post_id), users (user_id), users (reviewed_by) |
| 25 | community_post_likes | Community | Thích bài viết | Lượt thích bài đăng cộng đồng | community_posts (post_id), users (user_id) |
| 26 | complaints | System/Report | Khiếu nại | Khiếu nại về sân bãi, dịch vụ hoặc booking | users (assigned_to), bookings (booking_id), users (customer_id), users (resolved_by), venue_clusters (venue_cluster_id) |
| 27 | venue_posts | Community | Bài đăng chủ sân | Chủ sân đăng bài quảng bá, thông báo | users (author_id), users (reviewed_by), venue_clusters (venue_cluster_id) |
| 28 | favorite_venues | Venue | Sân yêu thích | Lưu danh sách cụm sân yêu thích của khách | users (user_id), venue_clusters (venue_cluster_id) |
| 29 | payments | Payment | Thanh toán | Quản lý giao dịch thanh toán của booking | bookings (booking_id), system_bank_accounts (system_bank_account_id), user_wallets (user_wallet_id), user_wallet_ledgers (user_wallet_ledger_id) |
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
| 42 | venue_platform_fee_ledgers | Payment | Công nợ phí nền tảng | Quản lý lịch sử và trạng thái đóng phí nền tảng của cụm sân | platform_fee_tiers (tier_id), venue_clusters (venue_cluster_id), media (payment_proof_media_id), users (payment_confirmed_by), users (payment_rejected_by), internal_receipts (internal_receipt_id) |
| 43 | player_preferred_court_types | Player | Môn thể thao yêu thích | Người chơi chọn loại môn thể thao quan tâm | court_types (court_type_id), users (user_id) |
| 44 | player_ratings | Player | Đánh giá người chơi | Đánh giá trình độ/thái độ giữa những người chơi với nhau | player_posts (post_id), users (rated_user_id), users (rater_id) |
| 45 | post_hashtags | Community | Gắn hashtag vào bài | Liên kết hashtag với các loại bài viết | hashtags (hashtag_id) |
| 46 | price_slots | Booking | Bảng giá theo khung giờ | Lưu giá tiền theo khung giờ của loại sân trong cụm | court_types (court_type_id), venue_clusters (venue_cluster_id) |
| 47 | refunds | Payment | Hoàn tiền | Quản lý yêu cầu hoàn tiền cho thanh toán bị hủy | payments (payment_id), users (processed_by), users (customer_id), user_wallets (user_wallet_id), user_wallet_ledgers (user_wallet_ledger_id), user_payout_accounts (user_payout_account_id), owner_wallet_ledgers (owner_wallet_ledger_id), users (owner_confirmed_by), users (admin_confirmed_by), system_policies (policy_id), policy_rules (policy_rule_id), policy_evaluation_logs (policy_evaluation_log_id) |
| 48 | reports | System/Report | Báo cáo vi phạm | Quản lý báo cáo xấu, spam | users (reporter_id), users (reviewed_by), violation_types (violation_type_id) |
| 49 | reviews | System/Report | Đánh giá cụm sân | Khách đánh giá sau khi hoàn thành booking | bookings (booking_id) |
| 50 | role_permissions | Auth/RBAC | Gán quyền cho role | Cầu nối n-n giữa roles và permissions | permissions (permission_id), roles (role_id) |
| 51 | slot_locks | Booking | Khóa khung giờ | Giữ chỗ hoặc khóa khung giờ không cho đặt sân | bookings (booking_id), venue_courts (venue_court_id), booking_items (booking_item_id) |
| 52 | system_policies | System | Chính sách hệ thống | Điều khoản, chính sách (bảo mật, hoàn tiền, v.v.) | users (created_by), users (updated_by), users (published_by), system_policies (replaced_policy_id) |
| 53 | system_posts | Community | Bài viết hệ thống | Admin đăng thông báo, tin tức hệ thống | users (author_id) |
| 54 | user_permission_revokes | Auth/RBAC | Thu hồi quyền của user | Lưu các quyền bị thu hồi cụ thể của 1 user dù role có cấp | permissions (permission_id), users (revoked_by), users (user_id) |
| 55 | user_policy_acceptances | System | Chấp nhận chính sách | Ghi nhận user đã đồng ý phiên bản chính sách | system_policies (system_policy_id), users (user_id) |
| 56 | user_roles | Auth/RBAC | Gán role cho user | Cầu nối n-n giữa users và roles, có hỗ trợ scope theo system/venue | users (granted_by), roles (role_id), users (user_id) |
| 57 | verification_codes | System | Mã xác thực | OTP dùng cho email/sms đăng ký, quên mật khẩu | users (user_id) |
| 58 | personal_access_tokens | Auth/RBAC | Lưu token đăng nhập | Bảng chuẩn của Laravel Sanctum lưu access token | Không FK |
| 59 | system_bank_accounts | Payment | Tài khoản ngân hàng | Lưu thông tin TKNH hệ thống dùng để nhận thanh toán | Không FK |
| 60 | owner_wallets | Payment | Ví chủ sân | Quản lý số dư, tiền thu hộ của chủ sân | venue_clusters (venue_cluster_id), users (owner_id) |
| 61 | owner_wallet_ledgers | Payment | Sổ quỹ ví chủ sân | Ghi nhận biến động số dư chi tiết của ví chủ sân | owner_wallets (owner_wallet_id), users (owner_id), venue_clusters (venue_cluster_id), bookings (booking_id), payments (payment_id) |
| 62 | booking_items | Booking | Chi tiết sân/khung giờ trong booking | Lưu từng sân con và khung giờ cụ thể trong một booking (hỗ trợ đặt nhiều sân/slot) | bookings (booking_id), venue_courts (venue_court_id), venue_courts (requested_venue_court_id), users (court_changed_by) |
| 63 | owner_bank_accounts | Payment | Tài khoản nhận tiền chủ sân | Lưu TKNH của chủ sân dùng nhận tiền rút/đối soát | users (owner_id), partner_applications (partner_application_id), users (verified_by) |
| 64 | owner_withdrawal_requests | Payment | Yêu cầu rút tiền chủ sân | Quản lý yêu cầu rút tiền từ ví chủ sân | users (owner_id), owner_wallets (owner_wallet_id), owner_bank_accounts (owner_bank_account_id), users (reviewed_by), users (completed_by), partner_settlements (partner_settlement_id), partner_termination_requests (partner_termination_request_id) |
| 65 | internal_receipts | Payment | Phiếu thu/chi nội bộ | Phiếu nội bộ cho phí nền tảng, rút tiền, hoàn tiền | users (issued_to_user_id), users (issued_by) |
| 66 | policy_action_bindings | Policy | Liên kết chính sách với action | Map chính sách hệ thống với module/action nghiệp vụ | system_policies (system_policy_id) |
| 67 | policy_rules | Policy | Luật chính sách hệ thống | Lưu rule có cấu trúc để backend evaluate | system_policies (system_policy_id), users (created_by), users (updated_by) |
| 68 | venue_policy_rules | Policy | Luật chính sách riêng sân | Lưu rule riêng của sân khi chính sách cho phép override | venue_clusters (venue_cluster_id), policy_rules (base_policy_rule_id), users (approved_by), users (created_by), users (updated_by) |
| 69 | policy_evaluation_logs | Policy | Log áp dụng chính sách | Ghi nhận mỗi lần hệ thống evaluate rule | system_policies (system_policy_id), policy_rules (policy_rule_id), venue_policy_rules (venue_policy_rule_id), users (evaluated_by_id) |
| 70 | ai_conversations | AI | Cuộc trò chuyện AI | Lưu lịch sử trò chuyện AI của user | users (user_id) |
| 71 | ai_messages | AI | Tin nhắn AI | Lưu message user/assistant/system trong cuộc trò chuyện AI | ai_conversations (ai_conversation_id) |
| 72 | ai_feedbacks | AI | Đánh giá AI | Lưu feedback của user cho câu trả lời AI | ai_messages (ai_message_id), users (user_id) |
| 73 | user_wallets | Payment | Ví người dùng | Quản lý ví nội bộ của user (thanh toán, nhận hoàn tiền) | users (user_id) |
| 74 | user_wallet_ledgers | Payment | Sổ quỹ ví người dùng | Ghi nhận biến động số dư ví user | user_wallets (user_wallet_id), users (created_by) |
| 75 | user_payout_accounts | Payment | Tài khoản nhận tiền user | TKNH user dùng nhận tiền khi rút ví hoặc refund | users (user_id) |
| 76 | user_withdrawal_requests | Payment | Yêu cầu rút tiền user | Quản lý yêu cầu rút tiền từ ví user | user_wallets (user_wallet_id), users (user_id), user_payout_accounts (payout_account_id), users (approved_by), users (paid_by) |
| 77 | vouchers | Voucher | Mã giảm giá | Lưu voucher hệ thống và voucher sân | users (created_by) |
| 78 | voucher_scopes | Voucher | Phạm vi voucher | Giới hạn phạm vi áp dụng voucher (cụm sân, loại sân, loại booking) | vouchers (voucher_id) |
| 79 | voucher_usages | Voucher | Lịch sử dùng voucher | Ghi nhận voucher đã áp dụng cho booking/payment nào | vouchers (voucher_id), users (user_id), bookings (booking_id), payments (payment_id) |
| 80 | backup_jobs | System | Job sao lưu dữ liệu | Lưu metadata và trạng thái các lần backup database | users (created_by) |
| 81 | amenities | Venue | Danh mục tiện ích sân | Lưu danh mục tiện ích như bãi xe, wifi, phòng tắm để gắn với cụm sân. | users (created_by), users (reviewed_by) |
| 82 | policy_rule_templates | Policy | Mẫu cấu hình rule | Danh mục template rule để admin tạo/cấu hình đúng loại chính sách và action code | Không FK |
| 83 | policy_override_constraints | Policy | Ràng buộc override chính sách sân | Giới hạn owner không được override rule hệ thống vượt khung cho phép | system_policies (system_policy_id), policy_rules (policy_rule_id) |
| 84 | policy_status_histories | Policy | Lịch sử trạng thái chính sách | Ghi nhận mỗi lần chính sách hệ thống đổi trạng thái/version | system_policies (system_policy_id), users (changed_by) |
| 85 | refund_status_histories | Payment | Lịch sử trạng thái refund | Ghi nhận từng bước xử lý hoàn tiền (owner confirm, admin confirm, gateway) | refunds (refund_id), users (changed_by) |
| 86 | partner_application_documents | Partner | Tài liệu hồ sơ đối tác | File đính kèm hồ sơ: ảnh sân, CCCD, giấy phép, chứng từ ngân hàng | partner_applications (partner_application_id), media (media_id), users (reviewed_by) |
| 87 | partner_application_status_histories | Partner | Lịch sử trạng thái hồ sơ đối tác | Ghi nhận mỗi lần hồ sơ đối tác đổi trạng thái | partner_applications (partner_application_id), users (changed_by) |
| 88 | document_templates | Document | Template văn bản DOCX | Lưu template biểu mẫu theo loại và version, có render engine | users (created_by), users (uploaded_by), document_templates (replaced_template_id) |
| 89 | generated_documents | Document | Văn bản đã sinh | Văn bản sinh từ template, có snapshot render_data và file path | document_templates (template_id), partner_applications (partner_application_id), users (owner_id), venue_clusters (venue_cluster_id), media (generated_file_media_id), media (signed_file_media_id), media (final_file_media_id), users (generated_by), partner_contracts (partner_contract_id), partner_termination_requests (partner_termination_request_id), partner_settlements (partner_settlement_id) |
| 90 | generated_document_signatures | Document | Chữ ký văn bản | Chữ ký/xác nhận của owner và SportGo trên văn bản đã sinh | generated_documents (generated_document_id), users (signer_user_id), media (signature_media_id) |
| 91 | partner_contracts | Contract | Hợp đồng đối tác | Hợp đồng giữa SportGo và chủ sân, link hồ sơ và văn bản đã ký | partner_applications (partner_application_id), users (owner_id), venue_clusters (venue_cluster_id), generated_documents (generated_document_id), users (generated_by), users (approved_by) |
| 92 | partner_termination_requests | Termination | Yêu cầu chấm dứt hợp tác | Yêu cầu chấm dứt hợp tác: hai bên đồng ý hoặc đơn phương | partner_contracts (partner_contract_id), partner_applications (partner_application_id), users (owner_id), venue_clusters (venue_cluster_id), users (requested_by), users (approved_by) |
| 93 | partner_termination_documents | Termination | Văn bản chấm dứt | Biên bản thanh lý, công văn đơn phương, đơn chấm dứt | partner_termination_requests (partner_termination_request_id), generated_documents (generated_document_id), media (media_id), users (generated_by) |
| 94 | partner_termination_status_histories | Termination | Lịch sử trạng thái chấm dứt | Ghi nhận từng bước xử lý yêu cầu chấm dứt hợp tác | partner_termination_requests (partner_termination_request_id), users (changed_by) |
| 95 | partner_settlements | Settlement | Quyết toán công nợ | Kết quả quyết toán khi chấm dứt hợp tác: payable vs receivable | partner_termination_requests (partner_termination_request_id), partner_contracts (partner_contract_id), users (owner_id), venue_clusters (venue_cluster_id), users (calculated_by), users (approved_by) |
| 96 | partner_settlement_items | Settlement | Chi tiết quyết toán | Từng dòng cộng/trừ trong biên bản quyết toán | partner_settlements (partner_settlement_id) |
| 97 | venue_access_restrictions | Owner Restriction | Giới hạn quyền owner | Khóa/giới hạn quyền chủ sân trên cụm sân: limited hoặc blocked | venue_clusters (venue_cluster_id), users (created_by) |
| 98 | venue_cluster_amenities | Venue | Gắn tiện ích cho cụm sân | Bảng trung gian liên kết cụm sân với tiện ích đang hiển thị cho khách. | venue_clusters (venue_cluster_id), amenities (amenity_id) |
| 99 | contract_templates | Contract | Mẫu hợp đồng | Lưu file template hợp đồng đối tác, trạng thái hoạt động và mô tả dùng khi sinh hợp đồng. | Không FK |
| 100 | partner_documents | Partner | Tài liệu đối tác | Lưu tài liệu/file đối tác tải lên trong quá trình đăng ký và quản lý hồ sơ. | partner_applications (partner_application_id) |
| 101 | contract_signatures | Contract | Chữ ký hợp đồng | Ghi nhận người ký, vai trò ký và thông tin phiên ký của hợp đồng đối tác. | users (user_id), partner_contracts (partner_contract_id) |
| 102 | partner_liquidations | Termination | Thanh lý hợp đồng đối tác | Lưu hồ sơ thanh lý hợp đồng khi chấm dứt hợp tác với đối tác. | partner_contracts (partner_contract_id), partner_termination_requests (termination_request_id) |
| 103 | partner_histories | Partner | Lịch sử hồ sơ đối tác | Ghi lại lịch sử thay đổi hồ sơ đối tác để phục vụ audit và theo dõi workflow. | partner_applications (partner_application_id), users (actor_id) |
| 104 | venue_location_change_requests | Venue | Yêu cầu đổi địa chỉ sân | Lưu yêu cầu chủ sân gửi để thay đổi địa chỉ, khu vực và map URL của cụm sân. | venue_clusters (venue_cluster_id), users (requested_by), users (reviewed_by) |
| 105 | violation_types | Moderation | Danh mục loại vi phạm | Danh mục nhóm vi phạm nội dung/hành vi và điểm mặc định dùng trong kiểm duyệt. | Không FK |
| 106 | severity_levels | Moderation | Danh mục mức độ vi phạm | Danh mục cấp độ nghiêm trọng và khoảng điểm dùng để phân loại vi phạm. | Không FK |
| 107 | moderation_thresholds | Moderation | Ngưỡng xử lý kiểm duyệt | Cấu hình ngưỡng cảnh báo và hành động tự động theo chính sách kiểm duyệt. | system_policies (system_policy_id) |
| 108 | violation_records | Moderation | Lịch sử vi phạm | Lưu từng lần ghi nhận vi phạm của đối tượng bị báo cáo hoặc bị hệ thống xử lý. | Không FK |
| 109 | user_lock_logs | Auth/RBAC | Lịch sử khóa tài khoản | Ghi lại lịch sử khóa/mở khóa tài khoản, người thực hiện và lý do. | users (user_id), users (locked_by) |

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
| 1 | id | char(36) | Không | - | PK | UUID định danh user, dùng làm khóa chính và tham chiếu từ các bảng quyền, booking, chat, bài viết. | 10000000-0000-0000-0000-000000000001 |
| 2 | username | string(50) | Không | - | UNIQUE | Tên tài khoản dùng để đăng nhập, khác với họ tên hiển thị; phải unique. | Ví dụ SportGo |
| 3 | full_name | string(255) | Không | - | - | Họ tên hiển thị trong hồ sơ, booking, chat, đánh giá. | Ví dụ SportGo |
| 4 | phone | string(20) | Có | - | UNIQUE | Số điện thoại chính khi đăng ký thường và đặt sân; Google login có thể chưa có phone. | 0901234567 |
| 5 | email | string(255) | Có | - | UNIQUE | Email phụ nhưng vẫn dùng đăng nhập, nhận mã xác thực và reset mật khẩu; unique khi có giá trị. | user@sportgo.vn |
| 6 | google_id | string(255) | Có | - | UNIQUE | ID tham chiếu đối tượng liên quan trong nghiệp vụ. | 1 |
| 7 | email_verified_at | timestamp | Có | - | - | Thời điểm email được xác thực; dùng để biết user đã xác thực email chưa. | 2026-06-22 09:00:00 |
| 8 | phone_verified_at | timestamp | Có | - | - | Thời điểm phone được xác thực; chuẩn bị cho phase SMS. | 2026-06-22 09:00:00 |
| 9 | password | string(255) | Không | - | - | Mật khẩu đã hash, không lưu plain text. | example |
| 10 | avatar_url | string(500) | Có | - | - | Đường dẫn avatar hiện tại của user; file chi tiết có thể lưu thêm trong media. | https://sportgo.vn/example |
| 11 | bio | text | Có | - | - | Mô tả cá nhân do user tự nhập, thay cho các field chơi thể thao mơ hồ như field vị trí ưa thích cũ. | example |
| 12 | status | enum(pending_verify, active, locked, deactivated) | Không | pending_verify | INDEX | Trạng thái tài khoản: pending_verify chờ xác thực, active được dùng, locked bị khóa, deactivated ngừng dùng. | pending_verify |
| 13 | is_locked | boolean | Không | false | - | Cờ nhanh kiểm tra khóa, đồng bộ với status=locked | true |
| 14 | verification_channel | enum(email, sms) | Không | email | - | Kênh user chọn để nhận mã xác thực: email hiện làm trước, sms để phase sau. | example |
| 15 | lock_type | enum(temporary, permanent, auto) | Có | - | - | Kiểu khóa tài khoản: temporary theo thời hạn, permanent vĩnh viễn, auto do cấu hình tự động. | temporary |
| 16 | status_reason | text | Có | - | - | Lý do khóa/hủy/ngừng tài khoản để hiển thị cho user và phục vụ audit. | active |
| 17 | locked_at | timestamp | Có | - | - | Thời điểm tài khoản bị khóa. | 2026-06-22 09:00:00 |
| 18 | locked_until | timestamp | Có | - | INDEX | Thời điểm hết khóa tạm thời; null khi không khóa hoặc khóa vĩnh viễn tùy lock_type. | 2026-06-22 09:00:00 |
| 19 | locked_by | char(36) | Có | - | FK | Admin/nhân viên khóa tài khoản, trỏ users.id. | 10000000-0000-0000-0000-000000000001 |
| 20 | remember_token | string(100) | Có | - | - | Token remember me mặc định của Laravel. | example |
| 21 | created_at | timestamp | Có | - | - | Thời điểm tạo bản ghi. | 2026-06-22 09:00:00 |
| 22 | updated_at | timestamp | Có | - | - | Thời điểm cập nhật bản ghi gần nhất. | 2026-06-22 09:00:00 |

### 3. Khóa chính, khóa ngoại, index
- PK: id
- FK: locked_by -> users.id (on delete: set null)
- UNIQUE: users_username_unique (username)
- UNIQUE: users_phone_unique (phone)
- UNIQUE: users_email_unique (email)
- INDEX: users_status_index (status)
- INDEX: users_locked_until_index (locked_until)
- UNIQUE: users_google_id_unique (google_id)

### 4. Quan hệ với bảng khác
- users n-1 users qua locked_by.

### 5. Ví dụ bản ghi
```json
{
    "id": "10000000-0000-0000-0000-000000000001",
    "username": "Ví dụ SportGo",
    "full_name": "Ví dụ SportGo",
    "phone": "0901234567",
    "email": "user@sportgo.vn",
    "google_id": 1,
    "email_verified_at": "2026-06-22 09:00:00",
    "phone_verified_at": "2026-06-22 09:00:00"
}
```

---

### MODULE: LARAVEL

## Tên bảng: password_reset_tokens

### 1. Mục đích bảng
Bảng chuẩn của Laravel cho reset password (email token)

### 2. Danh sách trường

| STT | Tên trường | Kiểu dữ liệu | Null | Default | Khóa/Ràng buộc | Mô tả tác dụng trường | Ví dụ |
|---|---|---|---|---|---|---|---|
| 1 | email | string(255) | Không | - | PK | Địa chỉ email dùng để đăng nhập, liên hệ hoặc nhận thông báo. | 1 |
| 2 | token | string(255) | Không | - | - | Token đã hash hoặc chuỗi xác thực dùng một lần. | example |
| 3 | created_at | timestamp | Có | - | - | Thời điểm tạo bản ghi. | 2026-06-22 09:00:00 |

### 3. Khóa chính, khóa ngoại, index
- PK: email

### 4. Quan hệ với bảng khác
- Không có khóa ngoại trực tiếp trong migration.

### 5. Ví dụ bản ghi
```json
{
    "email": 1,
    "token": "example",
    "created_at": "2026-06-22 09:00:00"
}
```

---

## Tên bảng: sessions

### 1. Mục đích bảng
Bảng quản lý session user đăng nhập

### 2. Danh sách trường

| STT | Tên trường | Kiểu dữ liệu | Null | Default | Khóa/Ràng buộc | Mô tả tác dụng trường | Ví dụ |
|---|---|---|---|---|---|---|---|
| 1 | id | string(255) | Không | - | PK | Khóa chính định danh duy nhất của bản ghi. | 1 |
| 2 | user_id | char(36) | Có | - | FK, INDEX | Khóa ngoại tham chiếu bảng `users`, dùng để liên kết bản ghi với dữ liệu liên quan. | 10000000-0000-0000-0000-000000000001 |
| 3 | ip_address | string(45) | Có | - | - | Địa chỉ IP tại thời điểm thực hiện thao tác. | example |
| 4 | user_agent | text | Có | - | - | Thông tin trình duyệt/thiết bị tại thời điểm thao tác. | example |
| 5 | payload | longText | Không | - | - | Nội dung payload thô dùng cho queue/session/cache. | example |
| 6 | last_activity | integer | Không | - | INDEX | Trường dữ liệu phục vụ nghiệp vụ của bảng `sessions`. | 1 |

### 3. Khóa chính, khóa ngoại, index
- PK: id
- FK: user_id -> users.id (on delete: cascade)
- INDEX: sessions_user_id_index (user_id)
- INDEX: sessions_last_activity_index (last_activity)

### 4. Quan hệ với bảng khác
- sessions n-1 users qua user_id.

### 5. Ví dụ bản ghi
```json
{
    "id": 1,
    "user_id": "10000000-0000-0000-0000-000000000001",
    "ip_address": "example",
    "user_agent": "example",
    "payload": "example",
    "last_activity": 1
}
```

---

## Tên bảng: cache

### 1. Mục đích bảng
Bảng cache database driver của Laravel

### 2. Danh sách trường

| STT | Tên trường | Kiểu dữ liệu | Null | Default | Khóa/Ràng buộc | Mô tả tác dụng trường | Ví dụ |
|---|---|---|---|---|---|---|---|
| 1 | key | string(255) | Không | - | PK | Trường dữ liệu phục vụ nghiệp vụ của bảng `cache`. | 1 |
| 2 | value | mediumText | Không | - | - | Dữ liệu có cấu trúc hoặc nội dung dài phục vụ xử lý nghiệp vụ. | example |
| 3 | expiration | bigInteger | Không | - | INDEX | Trường dữ liệu phục vụ nghiệp vụ của bảng `cache`. | 1 |

### 3. Khóa chính, khóa ngoại, index
- PK: key
- INDEX: cache_expiration_index (expiration)

### 4. Quan hệ với bảng khác
- Không có khóa ngoại trực tiếp trong migration.

### 5. Ví dụ bản ghi
```json
{
    "key": 1,
    "value": "example",
    "expiration": 1
}
```

---

## Tên bảng: cache_locks

### 1. Mục đích bảng
Quản lý lock của cache

### 2. Danh sách trường

| STT | Tên trường | Kiểu dữ liệu | Null | Default | Khóa/Ràng buộc | Mô tả tác dụng trường | Ví dụ |
|---|---|---|---|---|---|---|---|
| 1 | key | string(255) | Không | - | PK | Trường dữ liệu phục vụ nghiệp vụ của bảng `cache_locks`. | 1 |
| 2 | owner | string(255) | Không | - | - | Trường dữ liệu phục vụ nghiệp vụ của bảng `cache_locks`. | example |
| 3 | expiration | bigInteger | Không | - | INDEX | Trường dữ liệu phục vụ nghiệp vụ của bảng `cache_locks`. | 1 |

### 3. Khóa chính, khóa ngoại, index
- PK: key
- INDEX: cache_locks_expiration_index (expiration)

### 4. Quan hệ với bảng khác
- Không có khóa ngoại trực tiếp trong migration.

### 5. Ví dụ bản ghi
```json
{
    "key": 1,
    "owner": "example",
    "expiration": 1
}
```

---

## Tên bảng: jobs

### 1. Mục đích bảng
Quản lý background jobs (Queue)

### 2. Danh sách trường

| STT | Tên trường | Kiểu dữ liệu | Null | Default | Khóa/Ràng buộc | Mô tả tác dụng trường | Ví dụ |
|---|---|---|---|---|---|---|---|
| 1 | id | unsignedBigInteger | Không | - | PK | Khóa chính định danh duy nhất của bản ghi. | 1 |
| 2 | queue | string(255) | Không | - | INDEX | Trường dữ liệu phục vụ nghiệp vụ của bảng `jobs`. | example |
| 3 | payload | longText | Không | - | - | Nội dung payload thô dùng cho queue/session/cache. | example |
| 4 | attempts | unsignedSmallInteger | Không | - | - | Trường dữ liệu phục vụ nghiệp vụ của bảng `jobs`. | 1 |
| 5 | reserved_at | unsignedInteger | Có | - | - | Thời điểm xảy ra sự kiện `reserved_at`. | 2026-06-22 09:00:00 |
| 6 | available_at | unsignedInteger | Không | - | - | Thời điểm xảy ra sự kiện `available_at`. | 2026-06-22 09:00:00 |
| 7 | created_at | unsignedInteger | Không | - | - | Thời điểm tạo bản ghi. | 2026-06-22 09:00:00 |

### 3. Khóa chính, khóa ngoại, index
- PK: id
- INDEX: jobs_queue_index (queue)

### 4. Quan hệ với bảng khác
- Không có khóa ngoại trực tiếp trong migration.

### 5. Ví dụ bản ghi
```json
{
    "id": 1,
    "queue": "example",
    "payload": "example",
    "attempts": 1,
    "reserved_at": "2026-06-22 09:00:00",
    "available_at": "2026-06-22 09:00:00",
    "created_at": "2026-06-22 09:00:00"
}
```

---

## Tên bảng: job_batches

### 1. Mục đích bảng
Quản lý batch jobs

### 2. Danh sách trường

| STT | Tên trường | Kiểu dữ liệu | Null | Default | Khóa/Ràng buộc | Mô tả tác dụng trường | Ví dụ |
|---|---|---|---|---|---|---|---|
| 1 | id | string(255) | Không | - | PK | Khóa chính định danh duy nhất của bản ghi. | 1 |
| 2 | name | string(255) | Không | - | - | Tên/tiêu đề hiển thị hoặc dùng trong quản trị. | Ví dụ SportGo |
| 3 | total_jobs | integer | Không | - | - | Số lượng/tổng số dùng cho thống kê hoặc giới hạn nghiệp vụ. | 1 |
| 4 | pending_jobs | integer | Không | - | - | Trường dữ liệu phục vụ nghiệp vụ của bảng `job_batches`. | 1 |
| 5 | failed_jobs | integer | Không | - | - | Trường dữ liệu phục vụ nghiệp vụ của bảng `job_batches`. | 1 |
| 6 | failed_job_ids | longText | Không | - | - | Dữ liệu có cấu trúc hoặc nội dung dài phục vụ xử lý nghiệp vụ. | example |
| 7 | options | mediumText | Có | - | - | Dữ liệu có cấu trúc hoặc nội dung dài phục vụ xử lý nghiệp vụ. | example |
| 8 | cancelled_at | integer | Có | - | - | Thời điểm xảy ra sự kiện `cancelled_at`. | 2026-06-22 09:00:00 |
| 9 | created_at | integer | Không | - | - | Thời điểm tạo bản ghi. | 2026-06-22 09:00:00 |
| 10 | finished_at | integer | Có | - | - | Thời điểm xảy ra sự kiện `finished_at`. | 2026-06-22 09:00:00 |

### 3. Khóa chính, khóa ngoại, index
- PK: id

### 4. Quan hệ với bảng khác
- Không có khóa ngoại trực tiếp trong migration.

### 5. Ví dụ bản ghi
```json
{
    "id": 1,
    "name": "Ví dụ SportGo",
    "total_jobs": 1,
    "pending_jobs": 1,
    "failed_jobs": 1,
    "failed_job_ids": "example",
    "options": "example",
    "cancelled_at": "2026-06-22 09:00:00"
}
```

---

## Tên bảng: failed_jobs

### 1. Mục đích bảng
Lưu dữ liệu nghiệp vụ cho bảng `failed_jobs` theo schema migration hiện tại.

### 2. Danh sách trường

| STT | Tên trường | Kiểu dữ liệu | Null | Default | Khóa/Ràng buộc | Mô tả tác dụng trường | Ví dụ |
|---|---|---|---|---|---|---|---|
| 1 | id | unsignedBigInteger | Không | - | PK | Khóa chính định danh duy nhất của bản ghi. | 1 |
| 2 | uuid | string(255) | Không | - | UNIQUE | Trường dữ liệu phục vụ nghiệp vụ của bảng `failed_jobs`. | example |
| 3 | connection | string(255) | Không | - | - | Trường dữ liệu phục vụ nghiệp vụ của bảng `failed_jobs`. | example |
| 4 | queue | string(255) | Không | - | - | Trường dữ liệu phục vụ nghiệp vụ của bảng `failed_jobs`. | example |
| 5 | payload | longText | Không | - | - | Nội dung payload thô dùng cho queue/session/cache. | example |
| 6 | exception | longText | Không | - | - | Dữ liệu có cấu trúc hoặc nội dung dài phục vụ xử lý nghiệp vụ. | example |
| 7 | failed_at | timestamp | Không | CURRENT_TIMESTAMP | - | Thời điểm xảy ra sự kiện `failed_at`. | 2026-06-22 09:00:00 |

### 3. Khóa chính, khóa ngoại, index
- PK: id
- UNIQUE: failed_jobs_uuid_unique (uuid)
- INDEX: failed_jobs_connection_queue_failed_at_index (connection, queue, failed_at)

### 4. Quan hệ với bảng khác
- Không có khóa ngoại trực tiếp trong migration.

### 5. Ví dụ bản ghi
```json
{
    "id": 1,
    "uuid": "example",
    "connection": "example",
    "queue": "example",
    "payload": "example",
    "exception": "example",
    "failed_at": "2026-06-22 09:00:00"
}
```

---

### MODULE: SYSTEM/LOG

## Tên bảng: audit_logs

### 1. Mục đích bảng
Lịch sử kiểm toán, ghi lại mọi thao tác quan trọng (thêm/sửa/xóa bảng nhạy cảm) của bất kỳ ai.

### 2. Danh sách trường

| STT | Tên trường | Kiểu dữ liệu | Null | Default | Khóa/Ràng buộc | Mô tả tác dụng trường | Ví dụ |
|---|---|---|---|---|---|---|---|
| 1 | id | char(36) | Không | - | PK | Khóa chính định danh duy nhất của bản ghi. | 10000000-0000-0000-0000-000000000001 |
| 2 | actor_id | char(36) | Có | - | FK | User thực hiện hành động nhạy cảm; nullable nếu do hệ thống tự động. | 10000000-0000-0000-0000-000000000001 |
| 3 | actor_type | enum(user, owner, venue_staff, admin, super_admin, system) | Có | - | INDEX | Loại actor thực hiện hành động; actor_id nullable nếu system. | user |
| 4 | action | string(100) | Không | - | INDEX | Mã hành động như user.locked, venue.locked, report.resolved. | example |
| 5 | module | string(50) | Có | - | INDEX | Module nghiệp vụ như auth, booking, payment, policy. | example |
| 6 | entity_type | string(100) | Không | - | - | Loại đối tượng bị tác động; logical reference. | default |
| 7 | entity_id | string(100) | Không | - | - | ID đối tượng bị tác động; logical reference. | 1 |
| 8 | old_values | json | Có | - | - | JSON dữ liệu trước khi thay đổi. | {"key":"value"} |
| 9 | new_values | json | Có | - | - | JSON dữ liệu sau khi thay đổi. | {"key":"value"} |
| 10 | metadata | json | Có | - | - | Dữ liệu ngữ cảnh bổ sung cho audit. | {"key":"value"} |
| 11 | reason | text | Có | - | - | Lý do thao tác, đặc biệt cho từ chối/khóa/hủy. | Nội dung mẫu |
| 12 | policy_id | char(36) | Có | - | FK, INDEX | Chính sách chi phối hành động nếu có. | 10000000-0000-0000-0000-000000000001 |
| 13 | policy_rule_id | char(36) | Có | - | FK, INDEX | Rule chi phối hành động nếu có. | 10000000-0000-0000-0000-000000000001 |
| 14 | policy_evaluation_log_id | char(36) | Có | - | FK, INDEX | Lần evaluate policy tạo ra hành động nếu có. | 10000000-0000-0000-0000-000000000001 |
| 15 | request_id | string(100) | Có | - | INDEX | ID request để trace log cùng một request. | 1 |
| 16 | severity | enum(info, warning, critical) | Không | info | INDEX | Mức độ nghiêm trọng của audit log. | example |
| 17 | context | string(50) | Có | - | INDEX | Ngữ cảnh thao tác như admin, moderation, payment. | example |
| 18 | ip_address | string(45) | Có | - | - | IP của người thực hiện nếu có. | example |
| 19 | user_agent | string(500) | Có | - | - | User agent/thiết bị của người thực hiện nếu có. | example |
| 20 | created_at | timestamp | Có | - | INDEX | Thời điểm ghi audit log. | 2026-06-22 09:00:00 |

### 3. Khóa chính, khóa ngoại, index
- PK: id
- FK: actor_id -> users.id (on delete: set null)
- FK: policy_id -> system_policies.id (on delete: set null)
- FK: policy_rule_id -> policy_rules.id (on delete: set null)
- FK: policy_evaluation_log_id -> policy_evaluation_logs.id (on delete: set null)
- INDEX: audit_logs_entity_type_entity_id_index (entity_type, entity_id)
- INDEX: audit_logs_action_index (action)
- INDEX: audit_logs_context_index (context)
- INDEX: audit_logs_created_at_index (created_at)
- INDEX: audit_logs_actor_type_index (actor_type)
- INDEX: audit_logs_module_index (module)
- INDEX: audit_logs_policy_id_index (policy_id)
- INDEX: audit_logs_policy_rule_id_index (policy_rule_id)
- INDEX: audit_logs_policy_eval_id_index (policy_evaluation_log_id)
- INDEX: audit_logs_request_id_index (request_id)
- INDEX: audit_logs_severity_index (severity)

### 4. Quan hệ với bảng khác
- audit_logs n-1 users qua actor_id.
- audit_logs n-1 system_policies qua policy_id.
- audit_logs n-1 policy_rules qua policy_rule_id.
- audit_logs n-1 policy_evaluation_logs qua policy_evaluation_log_id.

### 5. Ví dụ bản ghi
```json
{
    "id": "10000000-0000-0000-0000-000000000001",
    "actor_id": "10000000-0000-0000-0000-000000000001",
    "actor_type": "user",
    "action": "example",
    "module": "example",
    "entity_type": "default",
    "entity_id": 1,
    "old_values": {
        "key": "value"
    }
}
```

---

### MODULE: SYSTEM

## Tên bảng: banners

### 1. Mục đích bảng
Banner quảng cáo, hiển thị trang chủ

### 2. Danh sách trường

| STT | Tên trường | Kiểu dữ liệu | Null | Default | Khóa/Ràng buộc | Mô tả tác dụng trường | Ví dụ |
|---|---|---|---|---|---|---|---|
| 1 | id | char(36) | Không | - | PK | Khóa chính định danh duy nhất của bản ghi. | 10000000-0000-0000-0000-000000000001 |
| 2 | title | string(255) | Không | - | - | Tiêu đề banner để admin quản lý và FE có thể hiển thị. | Ví dụ SportGo |
| 3 | image_path | string(1000) | Không | - | - | Đường dẫn ảnh banner đã upload; không lưu binary. | /storage/example.pdf |
| 4 | link_url | string(1000) | Có | - | - | URL hoặc deep link khi user bấm banner. | https://sportgo.vn/example |
| 5 | position | string(50) | Không | - | INDEX | Vị trí hiển thị banner như home. | example |
| 6 | sort_order | integer | Không | 0 | INDEX | Thứ tự hiển thị banner. | 1 |
| 7 | is_active | boolean | Không | true | INDEX | Banner có đang bật hay không. | true |
| 8 | starts_at | timestamp | Có | - | INDEX | Thời điểm bắt đầu hiển thị banner. | 2026-06-22 09:00:00 |
| 9 | ends_at | timestamp | Có | - | INDEX | Thời điểm kết thúc hiển thị banner. | 2026-06-22 09:00:00 |
| 10 | created_by | char(36) | Có | - | FK | Admin tạo banner. | 10000000-0000-0000-0000-000000000001 |
| 11 | updated_by | char(36) | Có | - | FK | Admin cập nhật banner. | 10000000-0000-0000-0000-000000000001 |
| 12 | created_at | timestamp | Có | - | - | Thời điểm tạo bản ghi. | 2026-06-22 09:00:00 |
| 13 | updated_at | timestamp | Có | - | - | Thời điểm cập nhật bản ghi gần nhất. | 2026-06-22 09:00:00 |

### 3. Khóa chính, khóa ngoại, index
- PK: id
- FK: created_by -> users.id (on delete: set null)
- FK: updated_by -> users.id (on delete: set null)
- INDEX: banners_position_index (position)
- INDEX: banners_is_active_index (is_active)
- INDEX: banners_sort_order_index (sort_order)
- INDEX: banners_starts_at_index (starts_at)
- INDEX: banners_ends_at_index (ends_at)

### 4. Quan hệ với bảng khác
- banners n-1 users qua created_by.
- banners n-1 users qua updated_by.

### 5. Ví dụ bản ghi
```json
{
    "id": "10000000-0000-0000-0000-000000000001",
    "title": "Ví dụ SportGo",
    "image_path": "/storage/example.pdf",
    "link_url": "https://sportgo.vn/example",
    "position": "example",
    "sort_order": 1,
    "is_active": true,
    "starts_at": "2026-06-22 09:00:00"
}
```

---

### MODULE: VENUE

## Tên bảng: venue_clusters

### 1. Mục đích bảng
Lưu trữ thông tin cơ sở kinh doanh (cụm sân) bao gồm tên, địa chỉ, chủ sở hữu, đánh giá và trạng thái duyệt.

### 2. Danh sách trường

| STT | Tên trường | Kiểu dữ liệu | Null | Default | Khóa/Ràng buộc | Mô tả tác dụng trường | Ví dụ |
|---|---|---|---|---|---|---|---|
| 1 | id | char(36) | Không | - | PK | Khóa chính định danh duy nhất của bản ghi. | 10000000-0000-0000-0000-000000000001 |
| 2 | owner_id | char(36) | Không | - | FK | Chủ sân sở hữu cụm này, trỏ users.id. | 10000000-0000-0000-0000-000000000001 |
| 3 | name | string(255) | Không | - | INDEX | Tên cụm sân hiển thị cho user. | Ví dụ SportGo |
| 4 | slug | string(255) | Không | - | UNIQUE | Định danh URL/SEO duy nhất của cụm sân. | example |
| 5 | description | text | Có | - | - | Mô tả cụm sân, tiện ích hoặc ghi chú. | Nội dung mẫu |
| 6 | phone_contact | string(20) | Có | - | - | Số điện thoại liên hệ của cụm sân. | 0901234567 |
| 7 | province | string(255) | Có | - | - | Tỉnh/Thành phố | example |
| 8 | ward | string(255) | Có | - | - | Xã/Phường | example |
| 9 | address | text | Không | - | - | Địa chỉ sân để hiển thị và mở Google Maps. | example |
| 10 | map_url | string(1000) | Có | - | - | Link Google Maps lưu lại từ form đăng ký/quản lý sân. | https://sportgo.vn/example |
| 11 | latitude | decimal(10,7) | Không | - | - | Vĩ độ dùng để tìm sân gần vị trí hiện tại. | 1 |
| 12 | longitude | decimal(10,7) | Không | - | - | Kinh độ dùng để tìm sân gần vị trí hiện tại. | 1 |
| 13 | amenities | json | Có | - | - | JSON danh sách tiện ích như bãi xe, đèn, phòng tắm. | {"key":"value"} |
| 14 | status | enum(pending, active, locked) | Không | pending | INDEX | Trạng thái cụm: pending chờ duyệt, active hoạt động, locked bị khóa. | pending |
| 15 | status_reason | text | Có | - | - | Lý do khóa cụm sân để chủ sân biết. | active |
| 16 | locked_at | timestamp | Có | - | - | Thời điểm cụm sân bị khóa. | 2026-06-22 09:00:00 |
| 17 | locked_until | timestamp | Có | - | INDEX | Thời điểm hết khóa tạm thời của cụm sân. | 2026-06-22 09:00:00 |
| 18 | locked_by | char(36) | Có | - | FK | Admin/nhân viên khóa cụm sân. | 10000000-0000-0000-0000-000000000001 |
| 19 | rating_avg | decimal(3,2) | Không | 0 | INDEX | Điểm trung bình sân, tính từ reviews. | 1 |
| 20 | rating_count | unsignedInteger | Không | 0 | - | Số lượt review sân. | 1 |
| 21 | created_at | timestamp | Có | - | - | Thời điểm tạo bản ghi. | 2026-06-22 09:00:00 |
| 22 | updated_at | timestamp | Có | - | - | Thời điểm cập nhật bản ghi gần nhất. | 2026-06-22 09:00:00 |

### 3. Khóa chính, khóa ngoại, index
- PK: id
- FK: locked_by -> users.id (on delete: set null)
- FK: owner_id -> users.id (on delete: restrict)
- UNIQUE: venue_clusters_slug_unique (slug)
- INDEX: venue_clusters_latitude_longitude_index (latitude, longitude)
- INDEX: venue_clusters_status_rating_avg_index (status, rating_avg)
- INDEX: venue_clusters_name_index (name)
- INDEX: venue_clusters_status_index (status)
- INDEX: venue_clusters_rating_avg_index (rating_avg)
- INDEX: venue_clusters_locked_until_index (locked_until)

### 4. Quan hệ với bảng khác
- venue_clusters n-1 users qua locked_by.
- venue_clusters n-1 users qua owner_id.

### 5. Ví dụ bản ghi
```json
{
    "id": "10000000-0000-0000-0000-000000000001",
    "owner_id": "10000000-0000-0000-0000-000000000001",
    "name": "Ví dụ SportGo",
    "slug": "example",
    "description": "Nội dung mẫu",
    "phone_contact": "0901234567",
    "province": "example",
    "ward": "example"
}
```

---

### MODULE: BOOKING

## Tên bảng: booking_configs

### 1. Mục đích bảng
Cấu hình linh hoạt cho từng cụm sân (tiền cọc, thời gian đặt tối thiểu, chính sách hoàn tiền).

### 2. Danh sách trường

| STT | Tên trường | Kiểu dữ liệu | Null | Default | Khóa/Ràng buộc | Mô tả tác dụng trường | Ví dụ |
|---|---|---|---|---|---|---|---|
| 1 | venue_cluster_id | char(36) | Không | - | PK, FK | Khóa ngoại tham chiếu bảng `venue_clusters`, dùng để liên kết bản ghi với dữ liệu liên quan. | 10000000-0000-0000-0000-000000000001 |
| 2 | min_duration_minutes | unsignedInteger | Không | 30 | - | Trường dữ liệu phục vụ nghiệp vụ của bảng `booking_configs`. | 1 |
| 3 | max_duration_minutes | unsignedInteger | Có | - | - | Trường dữ liệu phục vụ nghiệp vụ của bảng `booking_configs`. | 1 |
| 4 | slot_hold_minutes | unsignedInteger | Không | 20 | - | Trường dữ liệu phục vụ nghiệp vụ của bảng `booking_configs`. | 1 |
| 5 | reminder_before_minutes | unsignedInteger | Không | 30 | - | Trường dữ liệu phục vụ nghiệp vụ của bảng `booking_configs`. | 1 |
| 6 | allow_full_payment | boolean | Không | true | - | Cờ bật/tắt cho hành vi hoặc trạng thái tương ứng. | true |
| 7 | allow_deposit | boolean | Không | true | - | Cờ bật/tắt cho hành vi hoặc trạng thái tương ứng. | true |
| 8 | allow_no_prepay | boolean | Không | true | - | Cờ bật/tắt cho hành vi hoặc trạng thái tương ứng. | true |
| 9 | auto_approve_full_payment | boolean | Không | false | - | Cờ bật/tắt cho hành vi hoặc trạng thái tương ứng. | true |
| 10 | deposit_percent | decimal(5,2) | Có | - | - | Trường dữ liệu phục vụ nghiệp vụ của bảng `booking_configs`. | 1 |
| 11 | cancel_before_hours | unsignedInteger | Không | 0 | - | Trường dữ liệu phục vụ nghiệp vụ của bảng `booking_configs`. | 1 |
| 12 | refund_percent | unsignedInteger | Không | 0 | - | Trường dữ liệu phục vụ nghiệp vụ của bảng `booking_configs`. | 1 |
| 13 | created_at | timestamp | Có | - | - | Thời điểm tạo bản ghi. | 2026-06-22 09:00:00 |
| 14 | updated_at | timestamp | Có | - | - | Thời điểm cập nhật bản ghi gần nhất. | 2026-06-22 09:00:00 |

### 3. Khóa chính, khóa ngoại, index
- PK: venue_cluster_id
- FK: venue_cluster_id -> venue_clusters.id (on delete: cascade)

### 4. Quan hệ với bảng khác
- booking_configs n-1 venue_clusters qua venue_cluster_id.

### 5. Ví dụ bản ghi
```json
{
    "venue_cluster_id": "10000000-0000-0000-0000-000000000001",
    "min_duration_minutes": 1,
    "max_duration_minutes": 1,
    "slot_hold_minutes": 1,
    "reminder_before_minutes": 1,
    "allow_full_payment": true,
    "allow_deposit": true,
    "allow_no_prepay": true
}
```

---

### MODULE: VENUE

## Tên bảng: court_types

### 1. Mục đích bảng
Lưu trữ danh mục các môn thể thao hoặc loại sân. Dùng cho cả hệ thống quản lý môn thể thao.

### 2. Danh sách trường

| STT | Tên trường | Kiểu dữ liệu | Null | Default | Khóa/Ràng buộc | Mô tả tác dụng trường | Ví dụ |
|---|---|---|---|---|---|---|---|
| 1 | id | unsignedBigInteger | Không | - | PK | Khóa chính định danh duy nhất của bản ghi. | 1 |
| 2 | parent_id | unsignedBigInteger | Có | - | FK | ID của loại sân cha (để gom nhóm theo bộ môn) | 1 |
| 3 | name | string(100) | Không | - | UNIQUE | Tên loại sân như Badminton court, Football 7-a-side; admin quản lý. | Ví dụ SportGo |
| 4 | description | text | Có | - | - | Mô tả ngắn loại sân để admin/FE hiển thị. | Nội dung mẫu |
| 5 | player_count | unsignedInteger | Không | 0 | - | Số người chơi tham khảo cho loại sân. | 1 |
| 6 | is_active | boolean | Không | true | INDEX | Loại sân còn được chủ sân chọn hay không. | true |
| 7 | default_layout_w | double | Có | - | - | Default width of court on layout canvas (px/decimeters) | 1 |
| 8 | default_layout_h | double | Có | - | - | Default height of court on layout canvas (px/decimeters) | 1 |
| 9 | created_at | timestamp | Có | - | - | Thời điểm tạo bản ghi. | 2026-06-22 09:00:00 |
| 10 | updated_at | timestamp | Có | - | - | Thời điểm cập nhật bản ghi gần nhất. | 2026-06-22 09:00:00 |
| 11 | deleted_at | timestamp | Có | - | - | Thời điểm xóa mềm; null nghĩa là bản ghi còn hiệu lực. | 2026-06-22 09:00:00 |

### 3. Khóa chính, khóa ngoại, index
- PK: id
- FK: parent_id -> court_types.id (on delete: set null)
- UNIQUE: court_types_name_unique (name)
- INDEX: court_types_is_active_index (is_active)

### 4. Quan hệ với bảng khác
- court_types n-1 court_types qua parent_id.

### 5. Ví dụ bản ghi
```json
{
    "id": 1,
    "parent_id": 1,
    "name": "Ví dụ SportGo",
    "description": "Nội dung mẫu",
    "player_count": 1,
    "is_active": true,
    "default_layout_w": 1,
    "default_layout_h": 1
}
```

---

## Tên bảng: venue_staff_assignments

### 1. Mục đích bảng
Quản lý phân công nhân viên phục vụ, quản lý cho 1 cụm sân, hỗ trợ phân công theo từng loại sân nhỏ (scope).

### 2. Danh sách trường

| STT | Tên trường | Kiểu dữ liệu | Null | Default | Khóa/Ràng buộc | Mô tả tác dụng trường | Ví dụ |
|---|---|---|---|---|---|---|---|
| 1 | id | unsignedBigInteger | Không | - | PK | Khóa chính định danh duy nhất của bản ghi. | 1 |
| 2 | user_id | char(36) | Không | - | FK | Khóa ngoại tham chiếu bảng `users`, dùng để liên kết bản ghi với dữ liệu liên quan. | 10000000-0000-0000-0000-000000000001 |
| 3 | venue_cluster_id | char(36) | Không | - | FK, INDEX | Khóa ngoại tham chiếu bảng `venue_clusters`, dùng để liên kết bản ghi với dữ liệu liên quan. | 10000000-0000-0000-0000-000000000001 |
| 4 | scope_type | enum(all_cluster, court_type) | Không | all_cluster | INDEX | Loại hoặc nhóm phân loại của bản ghi. | all_cluster |
| 5 | court_type_id | unsignedBigInteger | Có | - | FK, INDEX | Khóa ngoại tham chiếu bảng `court_types`, dùng để liên kết bản ghi với dữ liệu liên quan. | 1 |
| 6 | scope_key | string(50) | Không | all | INDEX | Trường dữ liệu phục vụ nghiệp vụ của bảng `venue_staff_assignments`. | example |
| 7 | assigned_by | char(36) | Có | - | FK | Khóa ngoại tham chiếu bảng `users`, dùng để liên kết bản ghi với dữ liệu liên quan. | 10000000-0000-0000-0000-000000000001 |
| 8 | status | enum(active, inactive) | Không | active | INDEX | Trạng thái xử lý hoặc vòng đời của bản ghi. | active |
| 9 | created_at | timestamp | Có | - | - | Thời điểm tạo bản ghi. | 2026-06-22 09:00:00 |
| 10 | updated_at | timestamp | Có | - | - | Thời điểm cập nhật bản ghi gần nhất. | 2026-06-22 09:00:00 |

### 3. Khóa chính, khóa ngoại, index
- PK: id
- FK: assigned_by -> users.id (on delete: set null)
- FK: court_type_id -> court_types.id (on delete: set null)
- FK: user_id -> users.id (on delete: cascade)
- FK: venue_cluster_id -> venue_clusters.id (on delete: cascade)
- UNIQUE: venue_staff_assignments_unique (user_id, venue_cluster_id, scope_key)
- INDEX: venue_staff_assignments_venue_cluster_id_foreign (venue_cluster_id)
- INDEX: venue_staff_assignments_court_type_id_foreign (court_type_id)
- INDEX: venue_staff_assignments_scope_type_index (scope_type)
- INDEX: venue_staff_assignments_scope_key_index (scope_key)
- INDEX: venue_staff_assignments_status_index (status)

### 4. Quan hệ với bảng khác
- venue_staff_assignments n-1 users qua assigned_by.
- venue_staff_assignments n-1 court_types qua court_type_id.
- venue_staff_assignments n-1 users qua user_id.
- venue_staff_assignments n-1 venue_clusters qua venue_cluster_id.

### 5. Ví dụ bản ghi
```json
{
    "id": 1,
    "user_id": "10000000-0000-0000-0000-000000000001",
    "venue_cluster_id": "10000000-0000-0000-0000-000000000001",
    "scope_type": "all_cluster",
    "court_type_id": 1,
    "scope_key": "example",
    "assigned_by": "10000000-0000-0000-0000-000000000001",
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
| 1 | id | char(36) | Không | - | PK | Khóa chính định danh duy nhất của bản ghi. | 10000000-0000-0000-0000-000000000001 |
| 2 | venue_cluster_id | char(36) | Không | - | FK | Cụm sân chứa sân con này. | 10000000-0000-0000-0000-000000000001 |
| 3 | court_type_id | unsignedBigInteger | Không | - | FK | Loại sân của sân con. | 1 |
| 4 | name | string(100) | Không | - | INDEX | Tên sân con hiển thị trong lịch đặt sân. | Ví dụ SportGo |
| 5 | status | enum(active, maintenance, inactive) | Không | active | INDEX | Trạng thái sân con: active cho đặt, maintenance bảo trì, inactive không hoạt động. | active |
| 6 | sort_order | integer | Không | 0 | - | Thứ tự hiển thị sân con trong cụm sân. | 1 |
| 7 | layout_x | double | Có | - | - | X position on layout canvas | 1 |
| 8 | layout_y | double | Có | - | - | Y position on layout canvas | 1 |
| 9 | layout_w | double | Có | - | - | Width of court on layout canvas | 1 |
| 10 | layout_h | double | Có | - | - | Height of court on layout canvas | 1 |
| 11 | layout_rotation | integer | Không | 0 | - | Rotation in degrees (0-359) | 1 |
| 12 | created_at | timestamp | Có | - | - | Thời điểm tạo bản ghi. | 2026-06-22 09:00:00 |
| 13 | updated_at | timestamp | Có | - | - | Thời điểm cập nhật bản ghi gần nhất. | 2026-06-22 09:00:00 |
| 14 | deleted_at | timestamp | Có | - | - | Thời điểm xóa mềm; null nghĩa là bản ghi còn hiệu lực. | 2026-06-22 09:00:00 |

### 3. Khóa chính, khóa ngoại, index
- PK: id
- FK: court_type_id -> court_types.id (on delete: restrict)
- FK: venue_cluster_id -> venue_clusters.id (on delete: cascade)
- INDEX: venue_courts_venue_cluster_id_status_index (venue_cluster_id, status)
- INDEX: venue_courts_name_index (name)
- INDEX: venue_courts_status_index (status)

### 4. Quan hệ với bảng khác
- venue_courts n-1 court_types qua court_type_id.
- venue_courts n-1 venue_clusters qua venue_cluster_id.

### 5. Ví dụ bản ghi
```json
{
    "id": "10000000-0000-0000-0000-000000000001",
    "venue_cluster_id": "10000000-0000-0000-0000-000000000001",
    "court_type_id": 1,
    "name": "Ví dụ SportGo",
    "status": "active",
    "sort_order": 1,
    "layout_x": 1,
    "layout_y": 1
}
```

---

### MODULE: COMMUNITY

## Tên bảng: community_posts

### 1. Mục đích bảng
Lưu trữ bài đăng thảo luận tự do của người dùng trên trang cộng đồng.

### 2. Danh sách trường

| STT | Tên trường | Kiểu dữ liệu | Null | Default | Khóa/Ràng buộc | Mô tả tác dụng trường | Ví dụ |
|---|---|---|---|---|---|---|---|
| 1 | id | char(36) | Không | - | PK | Khóa chính định danh duy nhất của bản ghi. | 10000000-0000-0000-0000-000000000001 |
| 2 | author_id | char(36) | Không | - | FK | User tạo bài đăng tự do. | 10000000-0000-0000-0000-000000000001 |
| 3 | content | longText | Không | - | - | Nội dung bài đăng cộng đồng. | Nội dung mẫu |
| 4 | status | enum(pending_review, published, rejected, hidden) | Không | pending_review | INDEX | Trạng thái kiểm duyệt bài cộng đồng. | pending_review |
| 5 | reviewed_by | char(36) | Có | - | FK | Admin/nhân viên kiểm duyệt bài. | 10000000-0000-0000-0000-000000000001 |
| 6 | reviewed_at | timestamp | Có | - | - | Thời điểm kiểm duyệt bài. | 2026-06-22 09:00:00 |
| 7 | status_reason | text | Có | - | - | Lý do từ chối hoặc ẩn bài. | active |
| 8 | view_count | unsignedBigInteger | Không | 0 | - | Số lượt xem bài cộng đồng. | 1 |
| 9 | like_count | unsignedInteger | Không | 0 | - | Số lượt thích tổng hợp từ community_post_likes. | 1 |
| 10 | comment_count | unsignedInteger | Không | 0 | - | Số bình luận tổng hợp từ community_post_comments. | 1 |
| 11 | created_at | timestamp | Có | - | - | Thời điểm tạo bản ghi. | 2026-06-22 09:00:00 |
| 12 | updated_at | timestamp | Có | - | - | Thời điểm cập nhật bản ghi gần nhất. | 2026-06-22 09:00:00 |

### 3. Khóa chính, khóa ngoại, index
- PK: id
- FK: author_id -> users.id (on delete: restrict)
- FK: reviewed_by -> users.id (on delete: set null)
- INDEX: community_posts_status_index (status)
- INDEX: community_posts_status_created_at_index (status, created_at)

### 4. Quan hệ với bảng khác
- community_posts n-1 users qua author_id.
- community_posts n-1 users qua reviewed_by.

### 5. Ví dụ bản ghi
```json
{
    "id": "10000000-0000-0000-0000-000000000001",
    "author_id": "10000000-0000-0000-0000-000000000001",
    "content": "Nội dung mẫu",
    "status": "pending_review",
    "reviewed_by": "10000000-0000-0000-0000-000000000001",
    "reviewed_at": "2026-06-22 09:00:00",
    "status_reason": "active",
    "view_count": 1
}
```

---

### MODULE: VENUE

## Tên bảng: venue_court_approval_requests

### 1. Mục đích bảng
Khi chủ sân muốn tạo thêm sân con, họ gửi yêu cầu và admin duyệt trước khi sân hiện ra trên hệ thống.

### 2. Danh sách trường

| STT | Tên trường | Kiểu dữ liệu | Null | Default | Khóa/Ràng buộc | Mô tả tác dụng trường | Ví dụ |
|---|---|---|---|---|---|---|---|
| 1 | id | char(36) | Không | - | PK | Khóa chính định danh duy nhất của bản ghi. | 10000000-0000-0000-0000-000000000001 |
| 2 | venue_cluster_id | char(36) | Không | - | FK | Khóa ngoại tham chiếu bảng `venue_clusters`, dùng để liên kết bản ghi với dữ liệu liên quan. | 10000000-0000-0000-0000-000000000001 |
| 3 | court_type_id | unsignedBigInteger | Không | - | FK | Khóa ngoại tham chiếu bảng `court_types`, dùng để liên kết bản ghi với dữ liệu liên quan. | 1 |
| 4 | name | string(100) | Không | - | - | Tên/tiêu đề hiển thị hoặc dùng trong quản trị. | Ví dụ SportGo |
| 5 | status | enum(pending, approved, rejected, cancelled) | Không | pending | INDEX | Trạng thái xử lý hoặc vòng đời của bản ghi. | pending |
| 6 | requested_by | char(36) | Không | - | FK | Khóa ngoại tham chiếu bảng `users`, dùng để liên kết bản ghi với dữ liệu liên quan. | 10000000-0000-0000-0000-000000000001 |
| 7 | reviewed_by | char(36) | Có | - | FK | Khóa ngoại tham chiếu bảng `users`, dùng để liên kết bản ghi với dữ liệu liên quan. | 10000000-0000-0000-0000-000000000001 |
| 8 | status_reason | text | Có | - | - | Lý do/ghi chú giải thích cho trạng thái hoặc quyết định xử lý. | active |
| 9 | approved_venue_court_id | char(36) | Có | - | INDEX | ID tham chiếu đối tượng liên quan trong nghiệp vụ. | 10000000-0000-0000-0000-000000000001 |
| 10 | reviewed_at | timestamp | Có | - | - | Thời điểm xảy ra sự kiện `reviewed_at`. | 2026-06-22 09:00:00 |
| 11 | created_at | timestamp | Có | - | - | Thời điểm tạo bản ghi. | 2026-06-22 09:00:00 |
| 12 | updated_at | timestamp | Có | - | - | Thời điểm cập nhật bản ghi gần nhất. | 2026-06-22 09:00:00 |

### 3. Khóa chính, khóa ngoại, index
- PK: id
- FK: court_type_id -> court_types.id (on delete: restrict)
- FK: requested_by -> users.id (on delete: cascade)
- FK: reviewed_by -> users.id (on delete: set null)
- FK: venue_cluster_id -> venue_clusters.id (on delete: cascade)
- INDEX: venue_court_approval_requests_approved_venue_court_id_index (approved_venue_court_id)
- INDEX: venue_court_approval_requests_status_index (status)

### 4. Quan hệ với bảng khác
- venue_court_approval_requests n-1 court_types qua court_type_id.
- venue_court_approval_requests n-1 users qua requested_by.
- venue_court_approval_requests n-1 users qua reviewed_by.
- venue_court_approval_requests n-1 venue_clusters qua venue_cluster_id.

### 5. Ví dụ bản ghi
```json
{
    "id": "10000000-0000-0000-0000-000000000001",
    "venue_cluster_id": "10000000-0000-0000-0000-000000000001",
    "court_type_id": 1,
    "name": "Ví dụ SportGo",
    "status": "pending",
    "requested_by": "10000000-0000-0000-0000-000000000001",
    "reviewed_by": "10000000-0000-0000-0000-000000000001",
    "status_reason": "active"
}
```

---

### MODULE: CHAT

## Tên bảng: conversations

### 1. Mục đích bảng
Lưu trữ các phiên hội thoại (phòng chat).

### 2. Danh sách trường

| STT | Tên trường | Kiểu dữ liệu | Null | Default | Khóa/Ràng buộc | Mô tả tác dụng trường | Ví dụ |
|---|---|---|---|---|---|---|---|
| 1 | id | char(36) | Không | - | PK | Khóa chính định danh duy nhất của bản ghi. | 10000000-0000-0000-0000-000000000001 |
| 2 | type | enum(direct, player_post, venue_contact) | Không | - | INDEX | Loại chat: direct, player_post hoặc venue_contact. | direct |
| 3 | reference_type | string(100) | Có | - | - | Loại đối tượng chat gắn vào; logical reference. | default |
| 4 | reference_id | string(100) | Có | - | - | ID đối tượng chat gắn vào. | 1 |
| 5 | title | string(255) | Có | - | - | Tiêu đề chat để hiển thị trong danh sách. | Ví dụ SportGo |
| 6 | created_by | char(36) | Có | - | FK | User tạo conversation. | 10000000-0000-0000-0000-000000000001 |
| 7 | last_message_at | timestamp | Có | - | INDEX | Thời điểm tin nhắn cuối để sắp xếp danh sách chat. | 2026-06-22 09:00:00 |
| 8 | created_at | timestamp | Có | - | - | Thời điểm tạo bản ghi. | 2026-06-22 09:00:00 |
| 9 | updated_at | timestamp | Có | - | - | Thời điểm cập nhật bản ghi gần nhất. | 2026-06-22 09:00:00 |

### 3. Khóa chính, khóa ngoại, index
- PK: id
- FK: created_by -> users.id (on delete: set null)
- INDEX: conversations_type_index (type)
- INDEX: conversations_reference_type_reference_id_index (reference_type, reference_id)
- INDEX: conversations_last_message_at_index (last_message_at)

### 4. Quan hệ với bảng khác
- conversations n-1 users qua created_by.

### 5. Ví dụ bản ghi
```json
{
    "id": "10000000-0000-0000-0000-000000000001",
    "type": "direct",
    "reference_type": "default",
    "reference_id": 1,
    "title": "Ví dụ SportGo",
    "created_by": "10000000-0000-0000-0000-000000000001",
    "last_message_at": "2026-06-22 09:00:00",
    "created_at": "2026-06-22 09:00:00"
}
```

---

## Tên bảng: conversation_participants

### 1. Mục đích bảng
Thành viên tham gia vào cuộc hội thoại

### 2. Danh sách trường

| STT | Tên trường | Kiểu dữ liệu | Null | Default | Khóa/Ràng buộc | Mô tả tác dụng trường | Ví dụ |
|---|---|---|---|---|---|---|---|
| 1 | id | unsignedBigInteger | Không | - | PK | Khóa chính định danh duy nhất của bản ghi. | 1 |
| 2 | conversation_id | char(36) | Không | - | FK | Khóa ngoại tham chiếu bảng `conversations`, dùng để liên kết bản ghi với dữ liệu liên quan. | 10000000-0000-0000-0000-000000000001 |
| 3 | user_id | char(36) | Không | - | FK, INDEX | Khóa ngoại tham chiếu bảng `users`, dùng để liên kết bản ghi với dữ liệu liên quan. | 10000000-0000-0000-0000-000000000001 |
| 4 | last_read_at | timestamp | Có | - | - | Thời điểm xảy ra sự kiện `last_read_at`. | 2026-06-22 09:00:00 |
| 5 | joined_at | timestamp | Không | CURRENT_TIMESTAMP | - | Thời điểm xảy ra sự kiện `joined_at`. | 2026-06-22 09:00:00 |

### 3. Khóa chính, khóa ngoại, index
- PK: id
- FK: conversation_id -> conversations.id (on delete: cascade)
- FK: user_id -> users.id (on delete: cascade)
- UNIQUE: conversation_participants_conversation_id_user_id_unique (conversation_id, user_id)
- INDEX: conversation_participants_user_id_index (user_id)

### 4. Quan hệ với bảng khác
- conversation_participants n-1 conversations qua conversation_id.
- conversation_participants n-1 users qua user_id.

### 5. Ví dụ bản ghi
```json
{
    "id": 1,
    "conversation_id": "10000000-0000-0000-0000-000000000001",
    "user_id": "10000000-0000-0000-0000-000000000001",
    "last_read_at": "2026-06-22 09:00:00",
    "joined_at": "2026-06-22 09:00:00"
}
```

---

### MODULE: SYSTEM

## Tên bảng: partner_applications

### 1. Mục đích bảng
Lưu hồ sơ người dùng gửi lên xin trở thành chủ sân.

### 2. Danh sách trường

| STT | Tên trường | Kiểu dữ liệu | Null | Default | Khóa/Ràng buộc | Mô tả tác dụng trường | Ví dụ |
|---|---|---|---|---|---|---|---|
| 1 | id | char(36) | Không | - | PK | Khóa chính định danh duy nhất của bản ghi. | 10000000-0000-0000-0000-000000000001 |
| 2 | user_id | char(36) | Không | - | FK | User gửi hồ sơ đăng ký làm chủ sân. | 10000000-0000-0000-0000-000000000001 |
| 3 | applicant_full_name | string(255) | Có | - | - | Tên/tiêu đề hiển thị hoặc dùng trong quản trị. | Ví dụ SportGo |
| 4 | applicant_phone | string(30) | Có | - | - | Trường dữ liệu phục vụ nghiệp vụ của bảng `partner_applications`. | 0901234567 |
| 5 | applicant_email | string(255) | Có | - | - | Trường dữ liệu phục vụ nghiệp vụ của bảng `partner_applications`. | user@sportgo.vn |
| 6 | applicant_birth_date | date | Có | - | - | Ngay sinh nguoi dang ky, dung de kiem tra du 18 tuoi. | 2026-06-22 |
| 7 | applicant_address | text | Có | - | - | Trường dữ liệu phục vụ nghiệp vụ của bảng `partner_applications`. | example |
| 8 | applicant_type | string(50) | Có | - | - | Loại hoặc nhóm phân loại của bản ghi. | default |
| 9 | representative_name | string(255) | Có | - | - | Tên/tiêu đề hiển thị hoặc dùng trong quản trị. | Ví dụ SportGo |
| 10 | representative_identity_type | string(50) | Có | - | - | Loại hoặc nhóm phân loại của bản ghi. | default |
| 11 | representative_identity_number | string(50) | Có | - | - | Trường dữ liệu phục vụ nghiệp vụ của bảng `partner_applications`. | example |
| 12 | representative_identity_issued_date | date | Có | - | - | Trường dữ liệu phục vụ nghiệp vụ của bảng `partner_applications`. | 2026-06-22 |
| 13 | representative_identity_issued_place | string(255) | Có | - | - | Trường dữ liệu phục vụ nghiệp vụ của bảng `partner_applications`. | example |
| 14 | representative_position | string(150) | Có | - | - | Trường dữ liệu phục vụ nghiệp vụ của bảng `partner_applications`. | example |
| 15 | business_name | string(255) | Không | - | - | Tên đơn vị/cá nhân kinh doanh sân. | Ví dụ SportGo |
| 16 | tax_code | string(50) | Có | - | - | Mã số thuế nếu có. | CODE-001 |
| 17 | business_code | string(100) | Có | - | - | Trường dữ liệu phục vụ nghiệp vụ của bảng `partner_applications`. | CODE-001 |
| 18 | business_license_number | string(100) | Có | - | - | Trường dữ liệu phục vụ nghiệp vụ của bảng `partner_applications`. | example |
| 19 | business_address | text | Có | - | - | Trường dữ liệu phục vụ nghiệp vụ của bảng `partner_applications`. | example |
| 20 | business_representative_name | string(255) | Có | - | - | Tên/tiêu đề hiển thị hoặc dùng trong quản trị. | Ví dụ SportGo |
| 21 | business_representative_position | string(150) | Có | - | - | Trường dữ liệu phục vụ nghiệp vụ của bảng `partner_applications`. | example |
| 22 | venue_name | string(255) | Không | - | - | Tên cụm sân dự kiến tạo khi duyệt hồ sơ. | Ví dụ SportGo |
| 23 | venue_address | text | Không | - | - | Địa chỉ cụm sân nhập trong form đăng ký. | example |
| 24 | venue_province | string(100) | Có | - | - | Trường dữ liệu phục vụ nghiệp vụ của bảng `partner_applications`. | example |
| 25 | venue_district | string(100) | Có | - | - | Trường dữ liệu phục vụ nghiệp vụ của bảng `partner_applications`. | example |
| 26 | venue_ward | string(100) | Có | - | - | Trường dữ liệu phục vụ nghiệp vụ của bảng `partner_applications`. | example |
| 27 | venue_map_url | string(1000) | Có | - | - | Link Google Maps user dán trong form. | https://sportgo.vn/example |
| 28 | venue_latitude | decimal(10,7) | Không | - | - | Vĩ độ cụm sân. | 1 |
| 29 | venue_longitude | decimal(10,7) | Không | - | - | Kinh độ cụm sân. | 1 |
| 30 | venue_phone | string(30) | Có | - | - | Trường dữ liệu phục vụ nghiệp vụ của bảng `partner_applications`. | 0901234567 |
| 31 | venue_email | string(255) | Có | - | - | Trường dữ liệu phục vụ nghiệp vụ của bảng `partner_applications`. | user@sportgo.vn |
| 32 | venue_description | text | Có | - | - | Mô tả hoặc ghi chú bổ sung cho bản ghi. | Nội dung mẫu |
| 33 | expected_opening_hours | string(255) | Có | - | - | Trường dữ liệu phục vụ nghiệp vụ của bảng `partner_applications`. | example |
| 34 | parking_info | text | Có | - | - | Trường dữ liệu phục vụ nghiệp vụ của bảng `partner_applications`. | example |
| 35 | amenities | json | Có | - | - | Dữ liệu có cấu trúc hoặc nội dung dài phục vụ xử lý nghiệp vụ. | {"key":"value"} |
| 36 | court_count_total | unsignedInteger | Không | 0 | - | Số lượng/tổng số dùng cho thống kê hoặc giới hạn nghiệp vụ. | 1 |
| 37 | base_price_per_hour | unsignedInteger | Không | 0 | - | Gia co ban/gio cua cum san khi dang ky doi tac. | 100000 |
| 38 | bank_name | string(150) | Có | - | - | Tên/tiêu đề hiển thị hoặc dùng trong quản trị. | Ví dụ SportGo |
| 39 | bank_code | string(50) | Có | - | - | Trường dữ liệu phục vụ nghiệp vụ của bảng `partner_applications`. | CODE-001 |
| 40 | account_number | string(50) | Có | - | - | Số lượng/tổng số dùng cho thống kê hoặc giới hạn nghiệp vụ. | 1 |
| 41 | account_holder_name | string(255) | Có | - | - | Số lượng/tổng số dùng cho thống kê hoặc giới hạn nghiệp vụ. | 1 |
| 42 | bank_branch | string(255) | Có | - | - | Trường dữ liệu phục vụ nghiệp vụ của bảng `partner_applications`. | example |
| 43 | bank_verification_status | enum(pending, verified, rejected) | Không | pending | - | Trạng thái xử lý hoặc vòng đời của bản ghi. | pending |
| 44 | bank_verified_at | timestamp | Có | - | - | Thoi diem tai khoan ngan hang duoc xac minh tu dong. | 2026-06-22 09:00:00 |
| 45 | status | enum(pending, reviewing, approved, rejected, cancelled, draft, submitted, need_supplement, approved_pending_contract, contract_pending_owner_signature, contract_pending_sportgo_signature, completed) | Không | submitted | INDEX | Trạng thái hồ sơ. | pending |
| 46 | reviewed_by | char(36) | Có | - | FK | Admin/nhân viên duyệt hồ sơ. | 10000000-0000-0000-0000-000000000001 |
| 47 | status_reason | text | Có | - | - | Lý do từ chối/hủy. | active |
| 48 | approved_venue_cluster_id | char(36) | Có | - | INDEX | ID cụm sân được tạo sau khi duyệt; logical. | 10000000-0000-0000-0000-000000000001 |
| 49 | current_contract_id | char(36) | Có | - | INDEX | ID tham chiếu đối tượng liên quan trong nghiệp vụ. | 10000000-0000-0000-0000-000000000001 |
| 50 | submitted_at | timestamp | Không | CURRENT_TIMESTAMP | INDEX | Thời điểm user gửi hồ sơ. | 2026-06-22 09:00:00 |
| 51 | reviewed_at | timestamp | Có | - | - | Thời điểm admin xử lý hồ sơ. | 2026-06-22 09:00:00 |
| 52 | terminated_at | timestamp | Có | - | - | Thời điểm chấm dứt hợp đồng hợp tác | 2026-06-22 09:00:00 |
| 53 | created_at | timestamp | Có | - | - | Thời điểm tạo bản ghi. | 2026-06-22 09:00:00 |
| 54 | updated_at | timestamp | Có | - | - | Thời điểm cập nhật bản ghi gần nhất. | 2026-06-22 09:00:00 |

### 3. Khóa chính, khóa ngoại, index
- PK: id
- FK: reviewed_by -> users.id (on delete: set null)
- FK: user_id -> users.id (on delete: cascade)
- INDEX: partner_applications_user_id_status_index (user_id, status)
- INDEX: partner_applications_status_index (status)
- INDEX: partner_applications_submitted_at_index (submitted_at)
- INDEX: partner_applications_approved_venue_cluster_id_index (approved_venue_cluster_id)
- INDEX: partner_applications_current_contract_index (current_contract_id)

### 4. Quan hệ với bảng khác
- partner_applications n-1 users qua reviewed_by.
- partner_applications n-1 users qua user_id.

### 5. Ví dụ bản ghi
```json
{
    "id": "10000000-0000-0000-0000-000000000001",
    "user_id": "10000000-0000-0000-0000-000000000001",
    "applicant_full_name": "Ví dụ SportGo",
    "applicant_phone": "0901234567",
    "applicant_email": "user@sportgo.vn",
    "applicant_birth_date": "2026-06-22",
    "applicant_address": "example",
    "applicant_type": "default"
}
```

---

## Tên bảng: partner_application_courts

### 1. Mục đích bảng
Loại sân kinh doanh dự kiến của đơn đăng ký đối tác

### 2. Danh sách trường

| STT | Tên trường | Kiểu dữ liệu | Null | Default | Khóa/Ràng buộc | Mô tả tác dụng trường | Ví dụ |
|---|---|---|---|---|---|---|---|
| 1 | id | unsignedBigInteger | Không | - | PK | Khóa chính định danh duy nhất của bản ghi. | 1 |
| 2 | partner_application_id | char(36) | Không | - | FK | Khóa ngoại tham chiếu bảng `partner_applications`, dùng để liên kết bản ghi với dữ liệu liên quan. | 10000000-0000-0000-0000-000000000001 |
| 3 | court_type_id | unsignedBigInteger | Không | - | FK | Khóa ngoại tham chiếu bảng `court_types`, dùng để liên kết bản ghi với dữ liệu liên quan. | 1 |
| 4 | court_type_name_snapshot | string(255) | Có | - | - | Tên/tiêu đề hiển thị hoặc dùng trong quản trị. | default |
| 5 | expected_court_count | unsignedInteger | Không | 1 | - | Số lượng/tổng số dùng cho thống kê hoặc giới hạn nghiệp vụ. | 1 |
| 6 | note | text | Có | - | - | Mô tả hoặc ghi chú bổ sung cho bản ghi. | Nội dung mẫu |
| 7 | name | string(100) | Không | - | - | Tên/tiêu đề hiển thị hoặc dùng trong quản trị. | Ví dụ SportGo |
| 8 | sort_order | integer | Không | 0 | - | Thứ tự sắp xếp khi hiển thị. | 1 |
| 9 | created_at | timestamp | Có | - | - | Thời điểm tạo bản ghi. | 2026-06-22 09:00:00 |
| 10 | updated_at | timestamp | Có | - | - | Thời điểm cập nhật bản ghi gần nhất. | 2026-06-22 09:00:00 |

### 3. Khóa chính, khóa ngoại, index
- PK: id
- FK: court_type_id -> court_types.id (on delete: restrict)
- FK: partner_application_id -> partner_applications.id (on delete: cascade)

### 4. Quan hệ với bảng khác
- partner_application_courts n-1 court_types qua court_type_id.
- partner_application_courts n-1 partner_applications qua partner_application_id.

### 5. Ví dụ bản ghi
```json
{
    "id": 1,
    "partner_application_id": "10000000-0000-0000-0000-000000000001",
    "court_type_id": 1,
    "court_type_name_snapshot": "default",
    "expected_court_count": 1,
    "note": "Nội dung mẫu",
    "name": "Ví dụ SportGo",
    "sort_order": 1
}
```

---

### MODULE: AUTH/RBAC

## Tên bảng: roles

### 1. Mục đích bảng
Lưu trữ danh mục các nhóm quyền (vai trò) dùng để phân quyền (RBAC) cho users.

### 2. Danh sách trường

| STT | Tên trường | Kiểu dữ liệu | Null | Default | Khóa/Ràng buộc | Mô tả tác dụng trường | Ví dụ |
|---|---|---|---|---|---|---|---|
| 1 | id | unsignedBigInteger | Không | - | PK | Khóa chính định danh duy nhất của bản ghi. | 1 |
| 2 | name | string(50) | Không | - | UNIQUE | Mã role duy nhất để xử lý phân quyền trong code. | Ví dụ SportGo |
| 3 | display_name | string(100) | Không | - | - | Tên role dễ đọc để hiển thị trong màn quản trị. | Ví dụ SportGo |
| 4 | description | text | Có | - | - | Mô tả role này được phép làm gì. | Nội dung mẫu |
| 5 | is_system | boolean | Không | false | INDEX | Đánh dấu role hệ thống mặc định, không nên sửa/xóa tùy tiện. | true |
| 6 | created_at | timestamp | Có | - | - | Thời điểm tạo bản ghi. | 2026-06-22 09:00:00 |
| 7 | updated_at | timestamp | Có | - | - | Thời điểm cập nhật bản ghi gần nhất. | 2026-06-22 09:00:00 |

### 3. Khóa chính, khóa ngoại, index
- PK: id
- UNIQUE: roles_name_unique (name)
- INDEX: roles_is_system_index (is_system)

### 4. Quan hệ với bảng khác
- Không có khóa ngoại trực tiếp trong migration.

### 5. Ví dụ bản ghi
```json
{
    "id": 1,
    "name": "Ví dụ SportGo",
    "display_name": "Ví dụ SportGo",
    "description": "Nội dung mẫu",
    "is_system": true,
    "created_at": "2026-06-22 09:00:00",
    "updated_at": "2026-06-22 09:00:00"
}
```

---

### MODULE: BOOKING

## Tên bảng: bookings

### 1. Mục đích bảng
Lưu trữ toàn bộ thông tin đơn đặt sân (booking lẻ và cố định), ngày giờ chơi, tiền thanh toán.

### 2. Danh sách trường

| STT | Tên trường | Kiểu dữ liệu | Null | Default | Khóa/Ràng buộc | Mô tả tác dụng trường | Ví dụ |
|---|---|---|---|---|---|---|---|
| 1 | id | char(36) | Không | - | PK | Khóa chính định danh duy nhất của bản ghi. | 10000000-0000-0000-0000-000000000001 |
| 2 | booking_code | string(30) | Không | - | UNIQUE | Mã booking dễ đọc để user/chủ sân tra cứu. | CODE-001 |
| 3 | customer_id | char(36) | Có | - | FK | User đặt online; nullable vì booking tại quầy không bắt buộc tài khoản. | 10000000-0000-0000-0000-000000000001 |
| 4 | venue_court_id | char(36) | Có | - | FK | Sân con thực tế được gán cho buổi chơi. | 10000000-0000-0000-0000-000000000001 |
| 5 | requested_venue_court_id | char(36) | Có | - | FK | Sân con khách yêu cầu ban đầu. | 10000000-0000-0000-0000-000000000001 |
| 6 | venue_cluster_id | char(36) | Không | - | INDEX | Cụm sân denormalized từ venue_courts để lọc booking/dashboard nhanh. | 10000000-0000-0000-0000-000000000001 |
| 7 | booking_date | date | Không | - | INDEX | Ngày chơi. | 2026-06-22 |
| 8 | start_time | time | Có | - | - | Giờ bắt đầu booking. | 08:00:00 |
| 9 | end_time | time | Có | - | - | Giờ kết thúc booking. | 08:00:00 |
| 10 | duration_minutes | unsignedInteger | Có | - | - | Tổng thời lượng booking tính bằng phút. | 1 |
| 11 | total_price | decimal(12,2) | Không | 0 | - | Tổng tiền = SUM(booking_items.subtotal). | 100000 |
| 12 | original_amount | decimal(12,2) | Có | - | - | Tổng tiền trước khi áp voucher/discount. | 100000 |
| 13 | discount_amount | decimal(12,2) | Không | 0 | - | Tổng tiền được giảm. | 100000 |
| 14 | system_discount_amount | decimal(12,2) | Không | 0 | - | Phần giảm do nền tảng chịu. | 100000 |
| 15 | venue_discount_amount | decimal(12,2) | Không | 0 | - | Phần giảm do chủ sân/cụm sân chịu. | 100000 |
| 16 | final_amount | decimal(12,2) | Có | - | - | Số tiền cuối cùng sau voucher/discount. | 100000 |
| 17 | voucher_id | char(36) | Có | - | FK, INDEX | Voucher chính áp dụng nếu chỉ cho một voucher/booking. | 10000000-0000-0000-0000-000000000001 |
| 18 | voucher_code_snapshot | string(50) | Có | - | - | Snapshot mã voucher tại thời điểm đặt. | CODE-001 |
| 19 | payment_option | enum(full_payment, deposit, no_prepay) | Không | no_prepay | - | Kiểu thanh toán user chọn. | example |
| 20 | required_payment_amount | decimal(12,2) | Không | 0 | - | Số tiền tối thiểu cần thanh toán. | 100000 |
| 21 | source | enum(online, counter) | Không | online | - | Nguồn booking: online hoặc counter. | example |
| 22 | booking_type | enum(single, recurring) | Không | single | - | Kiểu booking: single=đặt lẻ; recurring=đặt cố định. | single |
| 23 | recurring_group_code | string(30) | Có | - | - | Mã nhóm đơn đặt cố định. | CODE-001 |
| 24 | recurring_start_date | date | Có | - | - | Ngày bắt đầu của rule đặt cố định. | 2026-06-22 |
| 25 | recurring_end_date | date | Có | - | - | Ngày kết thúc của rule đặt cố định. | 2026-06-22 |
| 26 | recurrence_type | enum(daily, weekly, monthly) | Có | - | - | Kiểu lặp của đơn cố định. | daily |
| 27 | recurrence_interval | unsignedInteger | Có | - | - | Khoảng lặp theo recurrence_type. | 1 |
| 28 | recurrence_days_of_week | json | Có | - | - | Dữ liệu có cấu trúc hoặc nội dung dài phục vụ xử lý nghiệp vụ. | {"key":"value"} |
| 29 | recurrence_days_of_month | json | Có | - | - | Dữ liệu có cấu trúc hoặc nội dung dài phục vụ xử lý nghiệp vụ. | {"key":"value"} |
| 30 | status | enum(pending_approval, pending_payment, confirmed, checked_in, completed, cancelled, expired, rejected) | Không | pending_approval | INDEX | Trạng thái booking. | pending_approval |
| 31 | walk_in_name | string(255) | Có | - | - | Tên khách tại quầy khi customer_id null. | Ví dụ SportGo |
| 32 | walk_in_phone | string(20) | Có | - | - | Số điện thoại khách tại quầy. | 0901234567 |
| 33 | status_reason | text | Có | - | - | Lý do hủy/từ chối/hết hiệu lực booking. | active |
| 34 | cancelled_by | char(36) | Có | - | FK | User/admin/chủ sân thực hiện hủy booking. | 10000000-0000-0000-0000-000000000001 |
| 35 | cancelled_at | timestamp | Có | - | - | Thời điểm booking bị hủy. | 2026-06-22 09:00:00 |
| 36 | created_by | char(36) | Có | - | FK | Người tạo booking. | 10000000-0000-0000-0000-000000000001 |
| 37 | court_changed_by | char(36) | Có | - | FK | Chủ sân/nhân viên đổi sân. | 10000000-0000-0000-0000-000000000001 |
| 38 | court_changed_at | timestamp | Có | - | - | Thời điểm đổi sân con. | 2026-06-22 09:00:00 |
| 39 | court_changed_reason | text | Có | - | - | Lý do đổi sân. | Nội dung mẫu |
| 40 | reminder_sent_at | timestamp | Có | - | - | Thời điểm hệ thống đã gửi nhắc lịch. | 2026-06-22 09:00:00 |
| 41 | created_at | timestamp | Có | - | - | Thời điểm tạo bản ghi. | 2026-06-22 09:00:00 |
| 42 | updated_at | timestamp | Có | - | - | Thời điểm cập nhật bản ghi gần nhất. | 2026-06-22 09:00:00 |

### 3. Khóa chính, khóa ngoại, index
- PK: id
- FK: cancelled_by -> users.id (on delete: set null)
- FK: created_by -> users.id (on delete: set null)
- FK: customer_id -> users.id (on delete: set null)
- FK: venue_court_id -> venue_courts.id (on delete: restrict)
- FK: requested_venue_court_id -> venue_courts.id (on delete: set null)
- FK: court_changed_by -> users.id (on delete: set null)
- FK: voucher_id -> vouchers.id (on delete: set null)
- UNIQUE: bookings_booking_code_unique (booking_code)
- INDEX: bookings_customer_id_created_at_index (customer_id, created_at)
- INDEX: bookings_venue_cluster_id_booking_date_status_index (venue_cluster_id, booking_date, status)
- INDEX: bookings_type_group_index (booking_type, recurring_group_code)
- INDEX: bookings_group_date_index (recurring_group_code, booking_date)
- INDEX: bookings_booking_date_index (booking_date)
- INDEX: bookings_status_index (status)
- INDEX: bookings_venue_cluster_id_index (venue_cluster_id)
- INDEX: bookings_court_date_time_index (venue_court_id, booking_date, start_time, end_time)
- INDEX: bookings_voucher_id_index (voucher_id)

### 4. Quan hệ với bảng khác
- bookings n-1 users qua cancelled_by.
- bookings n-1 users qua created_by.
- bookings n-1 users qua customer_id.
- bookings n-1 venue_courts qua venue_court_id.
- bookings n-1 venue_courts qua requested_venue_court_id.
- bookings n-1 users qua court_changed_by.
- bookings n-1 vouchers qua voucher_id.

### 5. Ví dụ bản ghi
```json
{
    "id": "10000000-0000-0000-0000-000000000001",
    "booking_code": "CODE-001",
    "customer_id": "10000000-0000-0000-0000-000000000001",
    "venue_court_id": "10000000-0000-0000-0000-000000000001",
    "requested_venue_court_id": "10000000-0000-0000-0000-000000000001",
    "venue_cluster_id": "10000000-0000-0000-0000-000000000001",
    "booking_date": "2026-06-22",
    "start_time": "08:00:00"
}
```

---

### MODULE: COMMUNITY

## Tên bảng: community_post_comments

### 1. Mục đích bảng
Bình luận trong các bài đăng cộng đồng

### 2. Danh sách trường

| STT | Tên trường | Kiểu dữ liệu | Null | Default | Khóa/Ràng buộc | Mô tả tác dụng trường | Ví dụ |
|---|---|---|---|---|---|---|---|
| 1 | id | char(36) | Không | - | PK | Khóa chính định danh duy nhất của bản ghi. | 10000000-0000-0000-0000-000000000001 |
| 2 | post_id | char(36) | Không | - | FK | Bài cộng đồng được bình luận. | 10000000-0000-0000-0000-000000000001 |
| 3 | user_id | char(36) | Không | - | FK | User viết bình luận. | 10000000-0000-0000-0000-000000000001 |
| 4 | content | longText | Không | - | - | Nội dung bình luận. | Nội dung mẫu |
| 5 | parent_id | char(36) | Có | - | FK | Bình luận cha nếu là trả lời. | 10000000-0000-0000-0000-000000000001 |
| 6 | status | enum(visible, hidden) | Không | visible | - | Trạng thái bình luận. | visible |
| 7 | reviewed_by | char(36) | Có | - | FK, INDEX | Admin/moderator xử lý bình luận. | 10000000-0000-0000-0000-000000000001 |
| 8 | reviewed_at | timestamp | Có | - | - | Thời điểm xử lý bình luận. | 2026-06-22 09:00:00 |
| 9 | status_reason | text | Có | - | - | Lý do ẩn/khôi phục bình luận. | active |
| 10 | created_at | timestamp | Có | - | - | Thời điểm tạo bản ghi. | 2026-06-22 09:00:00 |
| 11 | updated_at | timestamp | Có | - | - | Thời điểm cập nhật bản ghi gần nhất. | 2026-06-22 09:00:00 |

### 3. Khóa chính, khóa ngoại, index
- PK: id
- FK: parent_id -> community_post_comments.id (on delete: set null)
- FK: post_id -> community_posts.id (on delete: cascade)
- FK: user_id -> users.id (on delete: cascade)
- FK: reviewed_by -> users.id (on delete: set null)
- INDEX: community_post_comments_post_id_status_created_at_index (post_id, status, created_at)
- INDEX: community_post_comments_reviewed_by_index (reviewed_by)

### 4. Quan hệ với bảng khác
- community_post_comments n-1 community_post_comments qua parent_id.
- community_post_comments n-1 community_posts qua post_id.
- community_post_comments n-1 users qua user_id.
- community_post_comments n-1 users qua reviewed_by.

### 5. Ví dụ bản ghi
```json
{
    "id": "10000000-0000-0000-0000-000000000001",
    "post_id": "10000000-0000-0000-0000-000000000001",
    "user_id": "10000000-0000-0000-0000-000000000001",
    "content": "Nội dung mẫu",
    "parent_id": "10000000-0000-0000-0000-000000000001",
    "status": "visible",
    "reviewed_by": "10000000-0000-0000-0000-000000000001",
    "reviewed_at": "2026-06-22 09:00:00"
}
```

---

## Tên bảng: community_post_likes

### 1. Mục đích bảng
Lượt thích bài đăng cộng đồng

### 2. Danh sách trường

| STT | Tên trường | Kiểu dữ liệu | Null | Default | Khóa/Ràng buộc | Mô tả tác dụng trường | Ví dụ |
|---|---|---|---|---|---|---|---|
| 1 | id | unsignedBigInteger | Không | - | PK | Khóa chính định danh duy nhất của bản ghi. | 1 |
| 2 | post_id | char(36) | Không | - | FK | Bài cộng đồng được thích.; VD: 10000000-0000-0000-0000-000000000001 | 10000000-0000-0000-0000-000000000001 |
| 3 | user_id | char(36) | Không | - | FK | User bấm thích.; VD: 10000000-0000-0000-0000-000000000001 | 10000000-0000-0000-0000-000000000001 |
| 4 | created_at | timestamp | Có | - | - | Thời điểm bấm thích.; VD: 2026-06-15 18:00:00 | 2026-06-22 09:00:00 |

### 3. Khóa chính, khóa ngoại, index
- PK: id
- FK: post_id -> community_posts.id (on delete: cascade)
- FK: user_id -> users.id (on delete: cascade)
- UNIQUE: community_post_likes_post_id_user_id_unique (post_id, user_id)

### 4. Quan hệ với bảng khác
- community_post_likes n-1 community_posts qua post_id.
- community_post_likes n-1 users qua user_id.

### 5. Ví dụ bản ghi
```json
{
    "id": 1,
    "post_id": "10000000-0000-0000-0000-000000000001",
    "user_id": "10000000-0000-0000-0000-000000000001",
    "created_at": "2026-06-22 09:00:00"
}
```

---

### MODULE: SYSTEM/REPORT

## Tên bảng: complaints

### 1. Mục đích bảng
Khiếu nại về sân bãi, dịch vụ hoặc booking

### 2. Danh sách trường

| STT | Tên trường | Kiểu dữ liệu | Null | Default | Khóa/Ràng buộc | Mô tả tác dụng trường | Ví dụ |
|---|---|---|---|---|---|---|---|
| 1 | id | char(36) | Không | - | PK | Khóa chính định danh duy nhất của bản ghi. | 10000000-0000-0000-0000-000000000001 |
| 2 | complaint_type | enum(venue, system) | Không | - | INDEX | Loại khiếu nại: venue với sân hoặc system với hệ thống. | venue |
| 3 | booking_id | char(36) | Có | - | FK | Booking liên quan nếu khiếu nại phát sinh từ booking. | 10000000-0000-0000-0000-000000000001 |
| 4 | venue_cluster_id | char(36) | Có | - | FK | Cụm sân liên quan nếu là khiếu nại với sân. | 10000000-0000-0000-0000-000000000001 |
| 5 | customer_id | char(36) | Không | - | FK | User gửi khiếu nại. | 10000000-0000-0000-0000-000000000001 |
| 6 | content | text | Không | - | - | Nội dung khiếu nại. | Nội dung mẫu |
| 7 | status | enum(open, processing, resolved, rejected, closed) | Không | open | INDEX | Trạng thái khiếu nại. | open |
| 8 | assigned_to | char(36) | Có | - | FK | Nhân viên/admin được gán xử lý khiếu nại. | 10000000-0000-0000-0000-000000000001 |
| 9 | resolve_note | text | Có | - | - | Ghi chú giải quyết khiếu nại. | Nội dung mẫu |
| 10 | status_reason | text | Có | - | - | Lý do/ghi chú giải thích cho trạng thái hoặc quyết định xử lý. | active |
| 11 | resolved_by | char(36) | Có | - | FK | Người xử lý xong khiếu nại. | 10000000-0000-0000-0000-000000000001 |
| 12 | resolved_at | timestamp | Có | - | - | Thời điểm xử lý xong. | 2026-06-22 09:00:00 |
| 13 | created_at | timestamp | Có | - | - | Thời điểm tạo bản ghi. | 2026-06-22 09:00:00 |
| 14 | updated_at | timestamp | Có | - | - | Thời điểm cập nhật bản ghi gần nhất. | 2026-06-22 09:00:00 |

### 3. Khóa chính, khóa ngoại, index
- PK: id
- FK: assigned_to -> users.id (on delete: set null)
- FK: booking_id -> bookings.id (on delete: set null)
- FK: customer_id -> users.id (on delete: restrict)
- FK: resolved_by -> users.id (on delete: set null)
- FK: venue_cluster_id -> venue_clusters.id (on delete: set null)
- INDEX: complaints_complaint_type_index (complaint_type)
- INDEX: complaints_status_index (status)
- INDEX: complaints_status_created_at_index (status, created_at)

### 4. Quan hệ với bảng khác
- complaints n-1 users qua assigned_to.
- complaints n-1 bookings qua booking_id.
- complaints n-1 users qua customer_id.
- complaints n-1 users qua resolved_by.
- complaints n-1 venue_clusters qua venue_cluster_id.

### 5. Ví dụ bản ghi
```json
{
    "id": "10000000-0000-0000-0000-000000000001",
    "complaint_type": "venue",
    "booking_id": "10000000-0000-0000-0000-000000000001",
    "venue_cluster_id": "10000000-0000-0000-0000-000000000001",
    "customer_id": "10000000-0000-0000-0000-000000000001",
    "content": "Nội dung mẫu",
    "status": "open",
    "assigned_to": "10000000-0000-0000-0000-000000000001"
}
```

---

### MODULE: COMMUNITY

## Tên bảng: venue_posts

### 1. Mục đích bảng
Lưu trữ các bài viết, thông báo, quảng bá do chủ sân đăng cho cụm sân của mình.

### 2. Danh sách trường

| STT | Tên trường | Kiểu dữ liệu | Null | Default | Khóa/Ràng buộc | Mô tả tác dụng trường | Ví dụ |
|---|---|---|---|---|---|---|---|
| 1 | id | char(36) | Không | - | PK | Khóa chính định danh duy nhất của bản ghi. | 10000000-0000-0000-0000-000000000001 |
| 2 | venue_cluster_id | char(36) | Không | - | FK | Khóa ngoại tham chiếu bảng `venue_clusters`, dùng để liên kết bản ghi với dữ liệu liên quan. | 10000000-0000-0000-0000-000000000001 |
| 3 | author_id | char(36) | Không | - | FK | Khóa ngoại tham chiếu bảng `users`, dùng để liên kết bản ghi với dữ liệu liên quan. | 10000000-0000-0000-0000-000000000001 |
| 4 | content | longText | Không | - | - | Nội dung chính của bản ghi. | Nội dung mẫu |
| 5 | status | enum(pending_review, published, rejected, hidden) | Không | pending_review | INDEX | Trạng thái xử lý hoặc vòng đời của bản ghi. | pending_review |
| 6 | reviewed_by | char(36) | Có | - | FK | Khóa ngoại tham chiếu bảng `users`, dùng để liên kết bản ghi với dữ liệu liên quan. | 10000000-0000-0000-0000-000000000001 |
| 7 | reviewed_at | timestamp | Có | - | - | Thời điểm xảy ra sự kiện `reviewed_at`. | 2026-06-22 09:00:00 |
| 8 | status_reason | text | Có | - | - | Lý do/ghi chú giải thích cho trạng thái hoặc quyết định xử lý. | active |
| 9 | view_count | unsignedBigInteger | Không | 0 | - | Số lượng/tổng số dùng cho thống kê hoặc giới hạn nghiệp vụ. | 1 |
| 10 | like_count | unsignedInteger | Không | 0 | - | Số lượng/tổng số dùng cho thống kê hoặc giới hạn nghiệp vụ. | 1 |
| 11 | comment_count | unsignedInteger | Không | 0 | - | Số lượng/tổng số dùng cho thống kê hoặc giới hạn nghiệp vụ. | 1 |
| 12 | created_at | timestamp | Có | - | - | Thời điểm tạo bản ghi. | 2026-06-22 09:00:00 |
| 13 | updated_at | timestamp | Có | - | - | Thời điểm cập nhật bản ghi gần nhất. | 2026-06-22 09:00:00 |

### 3. Khóa chính, khóa ngoại, index
- PK: id
- FK: author_id -> users.id (on delete: restrict)
- FK: reviewed_by -> users.id (on delete: set null)
- FK: venue_cluster_id -> venue_clusters.id (on delete: cascade)
- INDEX: venue_posts_venue_cluster_id_status_index (venue_cluster_id, status)
- INDEX: venue_posts_status_index (status)

### 4. Quan hệ với bảng khác
- venue_posts n-1 users qua author_id.
- venue_posts n-1 users qua reviewed_by.
- venue_posts n-1 venue_clusters qua venue_cluster_id.

### 5. Ví dụ bản ghi
```json
{
    "id": "10000000-0000-0000-0000-000000000001",
    "venue_cluster_id": "10000000-0000-0000-0000-000000000001",
    "author_id": "10000000-0000-0000-0000-000000000001",
    "content": "Nội dung mẫu",
    "status": "pending_review",
    "reviewed_by": "10000000-0000-0000-0000-000000000001",
    "reviewed_at": "2026-06-22 09:00:00",
    "status_reason": "active"
}
```

---

### MODULE: VENUE

## Tên bảng: favorite_venues

### 1. Mục đích bảng
Lưu danh sách cụm sân yêu thích của người dùng.

### 2. Danh sách trường

| STT | Tên trường | Kiểu dữ liệu | Null | Default | Khóa/Ràng buộc | Mô tả tác dụng trường | Ví dụ |
|---|---|---|---|---|---|---|---|
| 1 | id | unsignedBigInteger | Không | - | PK | Khóa chính định danh duy nhất của bản ghi. | 1 |
| 2 | user_id | char(36) | Không | - | FK | User yêu thích cụm sân.; VD: 10000000-0000-0000-0000-000000000001 | 10000000-0000-0000-0000-000000000001 |
| 3 | venue_cluster_id | char(36) | Không | - | FK | Cụm sân được user yêu thích.; VD: 10000000-0000-0000-0000-000000000001 | 10000000-0000-0000-0000-000000000001 |
| 4 | created_at | timestamp | Có | - | - | Thời điểm user thêm sân vào danh sách yêu thích.; VD: 2026-06-15 18:00:00 | 2026-06-22 09:00:00 |

### 3. Khóa chính, khóa ngoại, index
- PK: id
- FK: user_id -> users.id (on delete: cascade)
- FK: venue_cluster_id -> venue_clusters.id (on delete: cascade)
- UNIQUE: favorite_venues_user_id_venue_cluster_id_unique (user_id, venue_cluster_id)

### 4. Quan hệ với bảng khác
- favorite_venues n-1 users qua user_id.
- favorite_venues n-1 venue_clusters qua venue_cluster_id.

### 5. Ví dụ bản ghi
```json
{
    "id": 1,
    "user_id": "10000000-0000-0000-0000-000000000001",
    "venue_cluster_id": "10000000-0000-0000-0000-000000000001",
    "created_at": "2026-06-22 09:00:00"
}
```

---

### MODULE: PAYMENT

## Tên bảng: payments

### 1. Mục đích bảng
Lưu trữ thông tin giao dịch thanh toán cho các booking.

### 2. Danh sách trường

| STT | Tên trường | Kiểu dữ liệu | Null | Default | Khóa/Ràng buộc | Mô tả tác dụng trường | Ví dụ |
|---|---|---|---|---|---|---|---|
| 1 | id | char(36) | Không | - | PK | Khóa chính định danh duy nhất của bản ghi. | 10000000-0000-0000-0000-000000000001 |
| 2 | payment_code | string(50) | Không | - | UNIQUE | Mã thanh toán nội bộ của hệ thống. | CODE-001 |
| 3 | booking_id | char(36) | Không | - | FK | Booking được thanh toán. | 10000000-0000-0000-0000-000000000001 |
| 4 | system_bank_account_id | char(36) | Có | - | FK, INDEX | Tài khoản hệ thống nhận tiền cho payment này. | 10000000-0000-0000-0000-000000000001 |
| 5 | user_wallet_id | char(36) | Có | - | FK, INDEX | Ví user dùng trong payment nếu thanh toán bằng ví hoặc mixed. | 10000000-0000-0000-0000-000000000001 |
| 6 | user_wallet_ledger_id | char(36) | Có | - | FK, INDEX | Ledger debit ví user liên quan payment này. | 10000000-0000-0000-0000-000000000001 |
| 7 | amount | decimal(12,2) | Không | - | - | Số tiền của lần thanh toán này. | 100000 |
| 8 | wallet_amount | decimal(12,2) | Không | 0 | - | Phần tiền thanh toán bằng ví user. | 100000 |
| 9 | gateway_amount | decimal(12,2) | Không | 0 | - | Phần tiền thanh toán qua gateway/chuyển khoản. | 100000 |
| 10 | payment_kind | enum(full, deposit, partial) | Không | partial | - | Loại thanh toán. | example |
| 11 | method | enum(50) | Không | sepay | INDEX | Phương thức thanh toán/ghi nhận tiền. | example |
| 12 | gateway_txn_id | string(100) | Có | - | UNIQUE | Mã giao dịch từ cổng thanh toán. | 1 |
| 13 | gateway_response | json | Có | - | - | JSON phản hồi từ gateway. | {"key":"value"} |
| 14 | status | enum(pending, paid, failed, refunded) | Không | pending | INDEX | Trạng thái payment. | pending |
| 15 | paid_at | timestamp | Có | - | INDEX | Thời điểm thanh toán thành công. | 2026-06-22 09:00:00 |
| 16 | created_at | timestamp | Có | - | - | Thời điểm tạo bản ghi. | 2026-06-22 09:00:00 |
| 17 | updated_at | timestamp | Có | - | - | Thời điểm cập nhật bản ghi gần nhất. | 2026-06-22 09:00:00 |

### 3. Khóa chính, khóa ngoại, index
- PK: id
- FK: booking_id -> bookings.id (on delete: restrict)
- FK: system_bank_account_id -> system_bank_accounts.id (on delete: set null)
- FK: user_wallet_id -> user_wallets.id (on delete: set null)
- FK: user_wallet_ledger_id -> user_wallet_ledgers.id (on delete: set null)
- UNIQUE: payments_payment_code_unique (payment_code)
- UNIQUE: payments_gateway_txn_id_unique (gateway_txn_id)
- INDEX: payments_booking_id_status_index (booking_id, status)
- INDEX: payments_status_created_at_index (status, created_at)
- INDEX: payments_method_index (method)
- INDEX: payments_status_index (status)
- INDEX: payments_paid_at_index (paid_at)
- INDEX: payments_system_bank_account_id_index (system_bank_account_id)
- INDEX: payments_user_wallet_id_index (user_wallet_id)
- INDEX: payments_user_wallet_ledger_id_index (user_wallet_ledger_id)

### 4. Quan hệ với bảng khác
- payments n-1 bookings qua booking_id.
- payments n-1 system_bank_accounts qua system_bank_account_id.
- payments n-1 user_wallets qua user_wallet_id.
- payments n-1 user_wallet_ledgers qua user_wallet_ledger_id.

### 5. Ví dụ bản ghi
```json
{
    "id": "10000000-0000-0000-0000-000000000001",
    "payment_code": "CODE-001",
    "booking_id": "10000000-0000-0000-0000-000000000001",
    "system_bank_account_id": "10000000-0000-0000-0000-000000000001",
    "user_wallet_id": "10000000-0000-0000-0000-000000000001",
    "user_wallet_ledger_id": "10000000-0000-0000-0000-000000000001",
    "amount": 100000,
    "wallet_amount": 100000
}
```

---

### MODULE: BOOKING

## Tên bảng: holiday_prices

### 1. Mục đích bảng
Ghi đè giá ở bảng price_slots vào các ngày lễ hoặc ngày đặc biệt.

### 2. Danh sách trường

| STT | Tên trường | Kiểu dữ liệu | Null | Default | Khóa/Ràng buộc | Mô tả tác dụng trường | Ví dụ |
|---|---|---|---|---|---|---|---|
| 1 | id | char(36) | Không | - | PK | Khóa chính định danh duy nhất của bản ghi. | 10000000-0000-0000-0000-000000000001 |
| 2 | venue_cluster_id | char(36) | Không | - | FK | Cụm sân áp dụng giá đặc biệt. | 10000000-0000-0000-0000-000000000001 |
| 3 | court_type_id | unsignedBigInteger | Không | - | FK | Loại sân áp dụng giá đặc biệt. | 1 |
| 4 | date_type | enum(holiday, special_date) | Không | holiday | INDEX | Loại ngày: holiday hoặc special_date. | holiday |
| 5 | booking_type | enum(all, single, recurring) | Không | all | INDEX | Kiểu booking áp dụng giá. | all |
| 6 | holiday_date | date | Không | - | - | Ngày cụ thể áp dụng giá đặc biệt. | 2026-06-22 |
| 7 | start_time | time | Không | 00:00:00 | INDEX | Giờ bắt đầu khung giá. | 08:00:00 |
| 8 | end_time | time | Không | 23:59:59 | INDEX | Giờ kết thúc khung giá. | 08:00:00 |
| 9 | price | decimal(12,2) | Không | 0 | - | Giá đặc biệt. | 100000 |
| 10 | note | string(255) | Có | - | - | Ghi chú lý do. | Nội dung mẫu |
| 11 | is_active | boolean | Không | true | INDEX | Giá ngày lễ còn được áp dụng hay không. | true |
| 12 | created_at | timestamp | Có | - | - | Thời điểm tạo bản ghi. | 2026-06-22 09:00:00 |
| 13 | updated_at | timestamp | Có | - | - | Thời điểm cập nhật bản ghi gần nhất. | 2026-06-22 09:00:00 |

### 3. Khóa chính, khóa ngoại, index
- PK: id
- FK: court_type_id -> court_types.id (on delete: restrict)
- FK: venue_cluster_id -> venue_clusters.id (on delete: cascade)
- UNIQUE: holiday_prices_unique (venue_cluster_id, court_type_id, holiday_date, start_time, end_time, booking_type)
- INDEX: holiday_prices_lookup_index (venue_cluster_id, court_type_id, holiday_date, booking_type, is_active)
- INDEX: holiday_prices_date_type_index (date_type)
- INDEX: holiday_prices_booking_type_index (booking_type)
- INDEX: holiday_prices_start_time_index (start_time)
- INDEX: holiday_prices_end_time_index (end_time)
- INDEX: holiday_prices_is_active_index (is_active)

### 4. Quan hệ với bảng khác
- holiday_prices n-1 court_types qua court_type_id.
- holiday_prices n-1 venue_clusters qua venue_cluster_id.

### 5. Ví dụ bản ghi
```json
{
    "id": "10000000-0000-0000-0000-000000000001",
    "venue_cluster_id": "10000000-0000-0000-0000-000000000001",
    "court_type_id": 1,
    "date_type": "holiday",
    "booking_type": "all",
    "holiday_date": "2026-06-22",
    "start_time": "08:00:00",
    "end_time": "08:00:00"
}
```

---

### MODULE: SYSTEM

## Tên bảng: media

### 1. Mục đích bảng
Sử dụng mô hình đa hình (Polymorphic) để lưu trữ mọi file đính kèm (ảnh sân, avatar, file báo cáo) của hệ thống.

### 2. Danh sách trường

| STT | Tên trường | Kiểu dữ liệu | Null | Default | Khóa/Ràng buộc | Mô tả tác dụng trường | Ví dụ |
|---|---|---|---|---|---|---|---|
| 1 | id | char(36) | Không | - | PK | Khóa chính định danh duy nhất của bản ghi. | 10000000-0000-0000-0000-000000000001 |
| 2 | mediable_type | string(100) | Không | - | - | Loại đối tượng sở hữu file; polymorphic. | default |
| 3 | mediable_id | string(100) | Không | - | - | ID đối tượng sở hữu file. | 1 |
| 4 | collection | string(50) | Không | default | INDEX | Nhóm file theo nghiệp vụ. | example |
| 5 | file_name | string(255) | Không | - | - | Tên file hiển thị/gốc. | Ví dụ SportGo |
| 6 | file_path | string(500) | Không | - | - | Đường dẫn hoặc storage key. | /storage/example.pdf |
| 7 | mime_type | string(100) | Không | - | INDEX | Loại file để validate ảnh/pdf. | default |
| 8 | file_size | unsignedBigInteger | Không | 0 | - | Dung lượng file tính bằng byte. | 1 |
| 9 | sort_order | smallInteger | Không | 0 | - | Thứ tự hiển thị. | 1 |
| 10 | created_at | timestamp | Có | - | - | Thời điểm upload file. | 2026-06-22 09:00:00 |

### 3. Khóa chính, khóa ngoại, index
- PK: id
- INDEX: media_collection_index (collection)
- INDEX: media_mime_type_index (mime_type)
- INDEX: media_mediable_collection_index (mediable_type, mediable_id, collection)
- INDEX: media_mediable_type_mediable_id_index (mediable_type, mediable_id)

### 4. Quan hệ với bảng khác
- Không có khóa ngoại trực tiếp trong migration.

### 5. Ví dụ bản ghi
```json
{
    "id": "10000000-0000-0000-0000-000000000001",
    "mediable_type": "default",
    "mediable_id": 1,
    "collection": "example",
    "file_name": "Ví dụ SportGo",
    "file_path": "/storage/example.pdf",
    "mime_type": "default",
    "file_size": 1
}
```

---

### MODULE: CHAT

## Tên bảng: messages

### 1. Mục đích bảng
Nội dung tin nhắn trong hội thoại

### 2. Danh sách trường

| STT | Tên trường | Kiểu dữ liệu | Null | Default | Khóa/Ràng buộc | Mô tả tác dụng trường | Ví dụ |
|---|---|---|---|---|---|---|---|
| 1 | id | char(36) | Không | - | PK | Khóa chính định danh duy nhất của bản ghi. | 10000000-0000-0000-0000-000000000001 |
| 2 | conversation_id | char(36) | Không | - | FK | Conversation chứa tin nhắn. | 10000000-0000-0000-0000-000000000001 |
| 3 | sender_id | char(36) | Có | - | FK | User gửi tin nhắn; nullable cho tin nhắn hệ thống. | 10000000-0000-0000-0000-000000000001 |
| 4 | content | text | Không | - | - | Nội dung tin nhắn. | Nội dung mẫu |
| 5 | is_system | boolean | Không | false | INDEX | Đánh dấu tin nhắn hệ thống. | true |
| 6 | reference_type | string(100) | Có | - | - | Loại đối tượng đính kèm; logical reference. | default |
| 7 | reference_id | string(100) | Có | - | - | ID đối tượng đính kèm. | 1 |
| 8 | created_at | timestamp | Có | - | INDEX | Thời điểm gửi tin nhắn. | 2026-06-22 09:00:00 |

### 3. Khóa chính, khóa ngoại, index
- PK: id
- FK: conversation_id -> conversations.id (on delete: cascade)
- FK: sender_id -> users.id (on delete: set null)
- INDEX: messages_conversation_id_created_at_index (conversation_id, created_at)
- INDEX: messages_reference_type_reference_id_index (reference_type, reference_id)
- INDEX: messages_created_at_index (created_at)
- INDEX: messages_is_system_index (is_system)

### 4. Quan hệ với bảng khác
- messages n-1 conversations qua conversation_id.
- messages n-1 users qua sender_id.

### 5. Ví dụ bản ghi
```json
{
    "id": "10000000-0000-0000-0000-000000000001",
    "conversation_id": "10000000-0000-0000-0000-000000000001",
    "sender_id": "10000000-0000-0000-0000-000000000001",
    "content": "Nội dung mẫu",
    "is_system": true,
    "reference_type": "default",
    "reference_id": 1,
    "created_at": "2026-06-22 09:00:00"
}
```

---

### MODULE: SYSTEM

## Tên bảng: moderation_configs

### 1. Mục đích bảng
Cấu hình hệ thống (key-value)

### 2. Danh sách trường

| STT | Tên trường | Kiểu dữ liệu | Null | Default | Khóa/Ràng buộc | Mô tả tác dụng trường | Ví dụ |
|---|---|---|---|---|---|---|---|
| 1 | key | string(100) | Không | - | PK | Trường dữ liệu phục vụ nghiệp vụ của bảng `moderation_configs`. | 1 |
| 2 | value | text | Không | - | - | Giá trị cấu hình lưu dạng text. | example |
| 3 | value_type | enum(string, integer, float, boolean, json) | Không | string | - | Kiểu dữ liệu của value. | string |
| 4 | description | text | Có | - | - | Mô tả cấu hình. | Nội dung mẫu |
| 5 | updated_by | char(36) | Có | - | FK | Admin/nhân viên cập nhật cấu hình. | 10000000-0000-0000-0000-000000000001 |
| 6 | created_at | timestamp | Có | - | - | Thời điểm tạo bản ghi. | 2026-06-22 09:00:00 |
| 7 | updated_at | timestamp | Có | - | - | Thời điểm cập nhật bản ghi gần nhất. | 2026-06-22 09:00:00 |

### 3. Khóa chính, khóa ngoại, index
- PK: key
- FK: updated_by -> users.id (on delete: set null)

### 4. Quan hệ với bảng khác
- moderation_configs n-1 users qua updated_by.

### 5. Ví dụ bản ghi
```json
{
    "key": 1,
    "value": "example",
    "value_type": "string",
    "description": "Nội dung mẫu",
    "updated_by": "10000000-0000-0000-0000-000000000001",
    "created_at": "2026-06-22 09:00:00",
    "updated_at": "2026-06-22 09:00:00"
}
```

---

## Tên bảng: notifications

### 1. Mục đích bảng
Lưu thông báo gửi cho user

### 2. Danh sách trường

| STT | Tên trường | Kiểu dữ liệu | Null | Default | Khóa/Ràng buộc | Mô tả tác dụng trường | Ví dụ |
|---|---|---|---|---|---|---|---|
| 1 | id | char(36) | Không | - | PK | Khóa chính định danh duy nhất của bản ghi. | 10000000-0000-0000-0000-000000000001 |
| 2 | user_id | char(36) | Không | - | FK | Người nhận thông báo. | 10000000-0000-0000-0000-000000000001 |
| 3 | type | string(50) | Không | - | INDEX | Loại thông báo. | default |
| 4 | title | string(255) | Không | - | - | Tiêu đề thông báo. | Ví dụ SportGo |
| 5 | body | text | Có | - | - | Nội dung ngắn. | example |
| 6 | reference_type | string(100) | Có | - | - | Loại đối tượng điều hướng; logical reference. | default |
| 7 | reference_id | string(100) | Có | - | - | ID đối tượng điều hướng. | 1 |
| 8 | data | json | Có | - | - | JSON dữ liệu phụ. | {"key":"value"} |
| 9 | is_read | boolean | Không | false | INDEX | Đánh dấu user đã đọc. | true |
| 10 | read_at | timestamp | Có | - | - | Thời điểm user đọc. | 2026-06-22 09:00:00 |
| 11 | created_at | timestamp | Có | - | INDEX | Thời điểm tạo thông báo. | 2026-06-22 09:00:00 |

### 3. Khóa chính, khóa ngoại, index
- PK: id
- FK: user_id -> users.id (on delete: cascade)
- INDEX: notifications_type_index (type)
- INDEX: notifications_is_read_index (is_read)
- INDEX: notifications_created_at_index (created_at)
- INDEX: notifications_reference_type_reference_id_index (reference_type, reference_id)
- INDEX: notifications_user_id_is_read_created_at_index (user_id, is_read, created_at)

### 4. Quan hệ với bảng khác
- notifications n-1 users qua user_id.

### 5. Ví dụ bản ghi
```json
{
    "id": "10000000-0000-0000-0000-000000000001",
    "user_id": "10000000-0000-0000-0000-000000000001",
    "type": "default",
    "title": "Ví dụ SportGo",
    "body": "example",
    "reference_type": "default",
    "reference_id": 1,
    "data": {
        "key": "value"
    }
}
```

---

### MODULE: PAYMENT

## Tên bảng: payment_logs

### 1. Mục đích bảng
Lịch sử webhook, thay đổi trạng thái của cổng thanh toán.

### 2. Danh sách trường

| STT | Tên trường | Kiểu dữ liệu | Null | Default | Khóa/Ràng buộc | Mô tả tác dụng trường | Ví dụ |
|---|---|---|---|---|---|---|---|
| 1 | id | char(36) | Không | - | PK | Khóa chính định danh duy nhất của bản ghi. | 10000000-0000-0000-0000-000000000001 |
| 2 | payment_id | char(36) | Không | - | FK | Payment mà log này thuộc về. | 10000000-0000-0000-0000-000000000001 |
| 3 | event_type | string(50) | Không | - | INDEX | Loại sự kiện. | default |
| 4 | request_payload | json | Có | - | - | JSON payload gửi đi. | {"key":"value"} |
| 5 | response_payload | json | Có | - | - | JSON phản hồi từ gateway. | {"key":"value"} |
| 6 | status_before | string(20) | Có | - | - | Trạng thái payment trước. | active |
| 7 | status_after | string(20) | Có | - | - | Trạng thái payment sau. | active |
| 8 | gateway_txn_id | string(100) | Có | - | INDEX | Mã giao dịch gateway. | 1 |
| 9 | error_code | string(100) | Có | - | INDEX | Mã lỗi nếu có. | CODE-001 |
| 10 | error_message | text | Có | - | - | Thông điệp lỗi chi tiết. | example |
| 11 | created_at | timestamp | Có | - | INDEX | Thời điểm ghi log. | 2026-06-22 09:00:00 |

### 3. Khóa chính, khóa ngoại, index
- PK: id
- FK: payment_id -> payments.id (on delete: restrict)
- INDEX: payment_logs_payment_id_created_at_index (payment_id, created_at)
- INDEX: payment_logs_event_type_index (event_type)
- INDEX: payment_logs_gateway_txn_id_index (gateway_txn_id)
- INDEX: payment_logs_error_code_index (error_code)
- INDEX: payment_logs_created_at_index (created_at)

### 4. Quan hệ với bảng khác
- payment_logs n-1 payments qua payment_id.

### 5. Ví dụ bản ghi
```json
{
    "id": "10000000-0000-0000-0000-000000000001",
    "payment_id": "10000000-0000-0000-0000-000000000001",
    "event_type": "default",
    "request_payload": {
        "key": "value"
    },
    "response_payload": {
        "key": "value"
    },
    "status_before": "active",
    "status_after": "active",
    "gateway_txn_id": 1
}
```

---

### MODULE: AUTH/RBAC

## Tên bảng: permissions

### 1. Mục đích bảng
Lưu trữ danh sách các quyền cụ thể, chi tiết dùng để check logic trong code.

### 2. Danh sách trường

| STT | Tên trường | Kiểu dữ liệu | Null | Default | Khóa/Ràng buộc | Mô tả tác dụng trường | Ví dụ |
|---|---|---|---|---|---|---|---|
| 1 | id | unsignedBigInteger | Không | - | PK | Khóa chính định danh duy nhất của bản ghi. | 1 |
| 2 | code | string(100) | Không | - | UNIQUE | Mã quyền duy nhất. | CODE-001 |
| 3 | name | string(255) | Không | - | - | Tên quyền dễ đọc. | Ví dụ SportGo |
| 4 | group_name | string(50) | Không | - | INDEX | Nhóm quyền để FE gom theo module. | Ví dụ SportGo |
| 5 | created_at | timestamp | Có | - | - | Thời điểm tạo bản ghi. | 2026-06-22 09:00:00 |

### 3. Khóa chính, khóa ngoại, index
- PK: id
- UNIQUE: permissions_code_unique (code)
- INDEX: permissions_group_name_index (group_name)

### 4. Quan hệ với bảng khác
- Không có khóa ngoại trực tiếp trong migration.

### 5. Ví dụ bản ghi
```json
{
    "id": 1,
    "code": "CODE-001",
    "name": "Ví dụ SportGo",
    "group_name": "Ví dụ SportGo",
    "created_at": "2026-06-22 09:00:00"
}
```

---

### MODULE: PAYMENT

## Tên bảng: platform_fee_tiers

### 1. Mục đích bảng
Quản lý các gói thu phí nền tảng áp dụng cho chủ sân

### 2. Danh sách trường

| STT | Tên trường | Kiểu dữ liệu | Null | Default | Khóa/Ràng buộc | Mô tả tác dụng trường | Ví dụ |
|---|---|---|---|---|---|---|---|
| 1 | id | unsignedBigInteger | Không | - | PK | Khóa chính định danh duy nhất của bản ghi. | 1 |
| 2 | name | string(50) | Không | - | UNIQUE | Tên bậc phí. | Ví dụ SportGo |
| 3 | min_courts | unsignedInteger | Không | - | INDEX | Số sân con tối thiểu. | 1 |
| 4 | max_courts | unsignedInteger | Có | - | INDEX | Số sân con tối đa; null = không giới hạn. | 1 |
| 5 | price_per_court_month | decimal(12,2) | Không | 0 | - | Giá/sân/tháng. | 100000 |
| 6 | annual_discount_percent | decimal(5,2) | Không | 0 | - | Phần trăm giảm khi đóng theo năm. | 1 |
| 7 | is_active | boolean | Không | true | INDEX | Bậc phí còn áp dụng. | true |
| 8 | effective_from | timestamp | Có | - | INDEX | Thời điểm bắt đầu hiệu lực. | 2026-06-22 09:00:00 |
| 9 | created_at | timestamp | Có | - | - | Thời điểm tạo bản ghi. | 2026-06-22 09:00:00 |
| 10 | updated_at | timestamp | Có | - | - | Thời điểm cập nhật bản ghi gần nhất. | 2026-06-22 09:00:00 |

### 3. Khóa chính, khóa ngoại, index
- PK: id
- UNIQUE: platform_fee_tiers_name_unique (name)
- INDEX: platform_fee_tiers_min_courts_index (min_courts)
- INDEX: platform_fee_tiers_max_courts_index (max_courts)
- INDEX: platform_fee_tiers_is_active_index (is_active)
- INDEX: platform_fee_tiers_effective_from_index (effective_from)

### 4. Quan hệ với bảng khác
- Không có khóa ngoại trực tiếp trong migration.

### 5. Ví dụ bản ghi
```json
{
    "id": 1,
    "name": "Ví dụ SportGo",
    "min_courts": 1,
    "max_courts": 1,
    "price_per_court_month": 100000,
    "annual_discount_percent": 1,
    "is_active": true,
    "effective_from": "2026-06-22 09:00:00"
}
```

---

### MODULE: COMMUNITY

## Tên bảng: player_posts

### 1. Mục đích bảng
Bài đăng "Tìm kèo" hoặc "Ghép đội", bắt buộc phải gắn với một `booking_id` đã đặt thành công để tránh đăng bài ảo.

### 2. Danh sách trường

| STT | Tên trường | Kiểu dữ liệu | Null | Default | Khóa/Ràng buộc | Mô tả tác dụng trường | Ví dụ |
|---|---|---|---|---|---|---|---|
| 1 | id | char(36) | Không | - | PK | Khóa chính định danh duy nhất của bản ghi. | 10000000-0000-0000-0000-000000000001 |
| 2 | booking_id | char(36) | Không | - | FK | Booking bắt buộc gắn với bài tuyển. | 10000000-0000-0000-0000-000000000001 |
| 3 | author_id | char(36) | Không | - | FK | Người tạo bài tuyển. | 10000000-0000-0000-0000-000000000001 |
| 4 | title | string(255) | Không | - | INDEX | Tiêu đề bài tuyển. | Ví dụ SportGo |
| 5 | description | text | Có | - | - | Nội dung mô tả buổi giao lưu. | Nội dung mẫu |
| 6 | needed_players | unsignedSmallInteger | Không | 1 | - | Số người cần tuyển thêm. | 1 |
| 7 | cost_per_player | decimal(12,2) | Có | - | - | Chi phí/người chỉ để hiển thị. | 1 |
| 8 | status | enum(open, full, closed, cancelled) | Không | open | INDEX | Trạng thái bài tuyển. | open |
| 9 | status_reason | text | Có | - | - | Lý do đóng/hủy. | active |
| 10 | created_at | timestamp | Có | - | - | Thời điểm tạo bản ghi. | 2026-06-22 09:00:00 |
| 11 | updated_at | timestamp | Có | - | - | Thời điểm cập nhật bản ghi gần nhất. | 2026-06-22 09:00:00 |

### 3. Khóa chính, khóa ngoại, index
- PK: id
- FK: author_id -> users.id (on delete: restrict)
- FK: booking_id -> bookings.id (on delete: cascade)
- INDEX: player_posts_author_id_created_at_index (author_id, created_at)
- INDEX: player_posts_booking_id_status_index (booking_id, status)
- INDEX: player_posts_status_index (status)
- INDEX: player_posts_title_index (title)

### 4. Quan hệ với bảng khác
- player_posts n-1 users qua author_id.
- player_posts n-1 bookings qua booking_id.

### 5. Ví dụ bản ghi
```json
{
    "id": "10000000-0000-0000-0000-000000000001",
    "booking_id": "10000000-0000-0000-0000-000000000001",
    "author_id": "10000000-0000-0000-0000-000000000001",
    "title": "Ví dụ SportGo",
    "description": "Nội dung mẫu",
    "needed_players": 1,
    "cost_per_player": 1,
    "status": "open"
}
```

---

## Tên bảng: hashtags

### 1. Mục đích bảng
Lưu các hashtag

### 2. Danh sách trường

| STT | Tên trường | Kiểu dữ liệu | Null | Default | Khóa/Ràng buộc | Mô tả tác dụng trường | Ví dụ |
|---|---|---|---|---|---|---|---|
| 1 | id | unsignedBigInteger | Không | - | PK | Khóa chính định danh duy nhất của bản ghi. | 1 |
| 2 | name | string(100) | Không | - | UNIQUE | Tên/tiêu đề hiển thị hoặc dùng trong quản trị. | Ví dụ SportGo |
| 3 | slug | string(100) | Không | - | UNIQUE | Chuỗi định danh thân thiện URL và SEO. | example |
| 4 | created_at | timestamp | Có | - | - | Thời điểm tạo bản ghi. | 2026-06-22 09:00:00 |
| 5 | updated_at | timestamp | Có | - | - | Thời điểm cập nhật bản ghi gần nhất. | 2026-06-22 09:00:00 |

### 3. Khóa chính, khóa ngoại, index
- PK: id
- UNIQUE: hashtags_name_unique (name)
- UNIQUE: hashtags_slug_unique (slug)

### 4. Quan hệ với bảng khác
- Không có khóa ngoại trực tiếp trong migration.

### 5. Ví dụ bản ghi
```json
{
    "id": 1,
    "name": "Ví dụ SportGo",
    "slug": "example",
    "created_at": "2026-06-22 09:00:00",
    "updated_at": "2026-06-22 09:00:00"
}
```

---

## Tên bảng: player_post_participants

### 1. Mục đích bảng
Lưu những người dùng gửi yêu cầu tham gia vào "Bài tìm kèo" và trạng thái duyệt của chủ kèo.

### 2. Danh sách trường

| STT | Tên trường | Kiểu dữ liệu | Null | Default | Khóa/Ràng buộc | Mô tả tác dụng trường | Ví dụ |
|---|---|---|---|---|---|---|---|
| 1 | id | unsignedBigInteger | Không | - | PK | Khóa chính định danh duy nhất của bản ghi. | 1 |
| 2 | post_id | char(36) | Không | - | FK | Bài tuyển mà user muốn tham gia. | 10000000-0000-0000-0000-000000000001 |
| 3 | user_id | char(36) | Không | - | FK | User quan tâm hoặc tham gia. | 10000000-0000-0000-0000-000000000001 |
| 4 | status | enum(pending, approved, rejected, cancelled) | Không | pending | INDEX | Trạng thái tham gia. | pending |
| 5 | message | text | Có | - | - | Tin nhắn/ghi chú. | example |
| 6 | responded_at | timestamp | Có | - | - | Thời điểm người tạo bài phản hồi. | 2026-06-22 09:00:00 |
| 7 | created_at | timestamp | Có | - | - | Thời điểm tạo bản ghi. | 2026-06-22 09:00:00 |
| 8 | updated_at | timestamp | Có | - | - | Thời điểm cập nhật bản ghi gần nhất. | 2026-06-22 09:00:00 |

### 3. Khóa chính, khóa ngoại, index
- PK: id
- FK: post_id -> player_posts.id (on delete: cascade)
- FK: user_id -> users.id (on delete: cascade)
- UNIQUE: player_post_participants_post_id_user_id_unique (post_id, user_id)
- INDEX: player_post_participants_status_index (status)
- INDEX: player_post_participants_user_id_status_index (user_id, status)

### 4. Quan hệ với bảng khác
- player_post_participants n-1 player_posts qua post_id.
- player_post_participants n-1 users qua user_id.

### 5. Ví dụ bản ghi
```json
{
    "id": 1,
    "post_id": "10000000-0000-0000-0000-000000000001",
    "user_id": "10000000-0000-0000-0000-000000000001",
    "status": "pending",
    "message": "example",
    "responded_at": "2026-06-22 09:00:00",
    "created_at": "2026-06-22 09:00:00",
    "updated_at": "2026-06-22 09:00:00"
}
```

---

### MODULE: PLAYER

## Tên bảng: player_preferences

### 1. Mục đích bảng
Lưu thông tin đánh giá trung bình của người chơi

### 2. Danh sách trường

| STT | Tên trường | Kiểu dữ liệu | Null | Default | Khóa/Ràng buộc | Mô tả tác dụng trường | Ví dụ |
|---|---|---|---|---|---|---|---|
| 1 | id | char(36) | Không | - | PK | Khóa chính định danh duy nhất của bản ghi. | 10000000-0000-0000-0000-000000000001 |
| 2 | user_id | char(36) | Không | - | FK, UNIQUE | User sở hữu hồ sơ người chơi. | 10000000-0000-0000-0000-000000000001 |
| 3 | player_rating_avg | decimal(3,2) | Không | 0 | - | Điểm trung bình. | 1 |
| 4 | player_rating_count | unsignedInteger | Không | 0 | - | Số lượt đánh giá. | 1 |
| 5 | created_at | timestamp | Có | - | - | Thời điểm tạo bản ghi. | 2026-06-22 09:00:00 |
| 6 | updated_at | timestamp | Có | - | - | Thời điểm cập nhật bản ghi gần nhất. | 2026-06-22 09:00:00 |

### 3. Khóa chính, khóa ngoại, index
- PK: id
- FK: user_id -> users.id (on delete: cascade)
- UNIQUE: player_preferences_user_id_unique (user_id)

### 4. Quan hệ với bảng khác
- player_preferences n-1 users qua user_id.

### 5. Ví dụ bản ghi
```json
{
    "id": "10000000-0000-0000-0000-000000000001",
    "user_id": "10000000-0000-0000-0000-000000000001",
    "player_rating_avg": 1,
    "player_rating_count": 1,
    "created_at": "2026-06-22 09:00:00",
    "updated_at": "2026-06-22 09:00:00"
}
```

---

### MODULE: PAYMENT

## Tên bảng: venue_platform_fee_ledgers

### 1. Mục đích bảng
Quản lý lịch sử và trạng thái đóng phí nền tảng của cụm sân

### 2. Danh sách trường

| STT | Tên trường | Kiểu dữ liệu | Null | Default | Khóa/Ràng buộc | Mô tả tác dụng trường | Ví dụ |
|---|---|---|---|---|---|---|---|
| 1 | id | char(36) | Không | - | PK | Khóa chính định danh duy nhất của bản ghi. | 10000000-0000-0000-0000-000000000001 |
| 2 | venue_cluster_id | char(36) | Không | - | FK | Khóa ngoại tham chiếu bảng `venue_clusters`, dùng để liên kết bản ghi với dữ liệu liên quan. | 10000000-0000-0000-0000-000000000001 |
| 3 | tier_id | unsignedBigInteger | Có | - | FK | Khóa ngoại tham chiếu bảng `platform_fee_tiers`, dùng để liên kết bản ghi với dữ liệu liên quan. | 1 |
| 4 | court_count | unsignedInteger | Không | - | - | Số lượng/tổng số dùng cho thống kê hoặc giới hạn nghiệp vụ. | 1 |
| 5 | billing_cycle | enum(monthly, yearly) | Không | monthly | INDEX | Trường dữ liệu phục vụ nghiệp vụ của bảng `venue_platform_fee_ledgers`. | example |
| 6 | period_months | unsignedSmallInteger | Không | 1 | INDEX | Số tháng của kỳ phí: 1, 3, 6, 9 hoặc 12. | 1 |
| 7 | period_start | date | Không | - | INDEX | Trường dữ liệu phục vụ nghiệp vụ của bảng `venue_platform_fee_ledgers`. | 2026-06-22 |
| 8 | period_end | date | Không | - | INDEX | Trường dữ liệu phục vụ nghiệp vụ của bảng `venue_platform_fee_ledgers`. | 2026-06-22 |
| 9 | due_date | date | Có | - | INDEX | Hạn cuối owner cần đóng phí. | 2026-06-22 |
| 10 | price_per_court_month | decimal(12,2) | Không | 0 | - | Giá trị tiền tệ dùng trong tính toán và đối soát. | 100000 |
| 11 | discount_percent | decimal(5,2) | Không | 0 | - | Số lượng/tổng số dùng cho thống kê hoặc giới hạn nghiệp vụ. | 1 |
| 12 | amount_due | decimal(12,2) | Không | 0 | - | Giá trị tiền tệ dùng trong tính toán và đối soát. | 100000 |
| 13 | amount_paid | decimal(12,2) | Không | 0 | - | Giá trị tiền tệ dùng trong tính toán và đối soát. | 100000 |
| 14 | payment_proof_media_id | char(36) | Có | - | FK | File bằng chứng thanh toán gần nhất trong media. | 10000000-0000-0000-0000-000000000001 |
| 15 | payment_proof_status | enum(none, submitted, approved, rejected) | Không | none | INDEX | Trạng thái duyệt bằng chứng thanh toán. | none |
| 16 | payment_proof_note | text | Có | - | - | Ghi chú từ owner/admin về bằng chứng thanh toán. | Nội dung mẫu |
| 17 | status | enum(pending, paid, overdue, cancelled) | Không | pending | INDEX | Trạng thái xử lý hoặc vòng đời của bản ghi. | pending |
| 18 | paid_at | timestamp | Có | - | - | Thời điểm xảy ra sự kiện `paid_at`. | 2026-06-22 09:00:00 |
| 19 | payment_confirmed_by | char(36) | Có | - | FK | Admin xác nhận thanh toán. | 10000000-0000-0000-0000-000000000001 |
| 20 | payment_confirmed_at | timestamp | Có | - | - | Thời điểm xác nhận thanh toán. | 2026-06-22 09:00:00 |
| 21 | payment_rejected_by | char(36) | Có | - | FK | Admin từ chối bằng chứng thanh toán. | 10000000-0000-0000-0000-000000000001 |
| 22 | payment_rejected_at | timestamp | Có | - | - | Thời điểm từ chối bằng chứng. | 2026-06-22 09:00:00 |
| 23 | payment_reject_reason | text | Có | - | - | Lý do từ chối bằng chứng thanh toán. | Nội dung mẫu |
| 24 | locked_venue_at | timestamp | Có | - | - | Thời điểm hệ thống/admin khóa cụm sân vì quá hạn phí. | 2026-06-22 09:00:00 |
| 25 | internal_receipt_id | char(36) | Có | - | FK | Phiếu/hóa đơn nội bộ phát hành cho kỳ phí. | 10000000-0000-0000-0000-000000000001 |
| 26 | created_at | timestamp | Có | - | - | Thời điểm tạo bản ghi. | 2026-06-22 09:00:00 |
| 27 | updated_at | timestamp | Có | - | - | Thời điểm cập nhật bản ghi gần nhất. | 2026-06-22 09:00:00 |

### 3. Khóa chính, khóa ngoại, index
- PK: id
- FK: tier_id -> platform_fee_tiers.id (on delete: set null)
- FK: venue_cluster_id -> venue_clusters.id (on delete: restrict)
- FK: payment_proof_media_id -> media.id (on delete: set null)
- FK: payment_confirmed_by -> users.id (on delete: set null)
- FK: payment_rejected_by -> users.id (on delete: set null)
- FK: internal_receipt_id -> internal_receipts.id (on delete: set null)
- INDEX: venue_platform_fee_ledgers_billing_cycle_index (billing_cycle)
- INDEX: venue_platform_fee_ledgers_period_start_index (period_start)
- INDEX: venue_platform_fee_ledgers_period_end_index (period_end)
- INDEX: venue_platform_fee_ledgers_status_index (status)
- INDEX: venue_platform_fee_ledgers_venue_cluster_id_status_index (venue_cluster_id, status)
- INDEX: vpfl_period_months_index (period_months)
- INDEX: vpfl_due_date_index (due_date)
- INDEX: vpfl_payment_proof_status_index (payment_proof_status)

### 4. Quan hệ với bảng khác
- venue_platform_fee_ledgers n-1 platform_fee_tiers qua tier_id.
- venue_platform_fee_ledgers n-1 venue_clusters qua venue_cluster_id.
- venue_platform_fee_ledgers n-1 media qua payment_proof_media_id.
- venue_platform_fee_ledgers n-1 users qua payment_confirmed_by.
- venue_platform_fee_ledgers n-1 users qua payment_rejected_by.
- venue_platform_fee_ledgers n-1 internal_receipts qua internal_receipt_id.

### 5. Ví dụ bản ghi
```json
{
    "id": "10000000-0000-0000-0000-000000000001",
    "venue_cluster_id": "10000000-0000-0000-0000-000000000001",
    "tier_id": 1,
    "court_count": 1,
    "billing_cycle": "example",
    "period_months": 1,
    "period_start": "2026-06-22",
    "period_end": "2026-06-22"
}
```

---

### MODULE: PLAYER

## Tên bảng: player_preferred_court_types

### 1. Mục đích bảng
Người chơi chọn loại môn thể thao quan tâm

### 2. Danh sách trường

| STT | Tên trường | Kiểu dữ liệu | Null | Default | Khóa/Ràng buộc | Mô tả tác dụng trường | Ví dụ |
|---|---|---|---|---|---|---|---|
| 1 | id | unsignedBigInteger | Không | - | PK | Khóa chính định danh duy nhất của bản ghi. | 1 |
| 2 | user_id | char(36) | Không | - | FK | Người chơi chọn loại sân yêu thích. | 10000000-0000-0000-0000-000000000001 |
| 3 | court_type_id | unsignedBigInteger | Không | - | FK | Loại sân được yêu thích. | 1 |
| 4 | sort_order | integer | Không | 0 | - | Thứ tự ưu tiên. | 1 |
| 5 | created_at | timestamp | Có | - | - | Thời điểm tạo bản ghi. | 2026-06-22 09:00:00 |
| 6 | updated_at | timestamp | Có | - | - | Thời điểm cập nhật bản ghi gần nhất. | 2026-06-22 09:00:00 |

### 3. Khóa chính, khóa ngoại, index
- PK: id
- FK: court_type_id -> court_types.id (on delete: cascade)
- FK: user_id -> users.id (on delete: cascade)
- UNIQUE: player_preferred_court_types_user_id_court_type_id_unique (user_id, court_type_id)

### 4. Quan hệ với bảng khác
- player_preferred_court_types n-1 court_types qua court_type_id.
- player_preferred_court_types n-1 users qua user_id.

### 5. Ví dụ bản ghi
```json
{
    "id": 1,
    "user_id": "10000000-0000-0000-0000-000000000001",
    "court_type_id": 1,
    "sort_order": 1,
    "created_at": "2026-06-22 09:00:00",
    "updated_at": "2026-06-22 09:00:00"
}
```

---

## Tên bảng: player_ratings

### 1. Mục đích bảng
Lưu đánh giá (Rating) giữa người chơi với nhau sau khi tham gia kèo thành công, giúp xây dựng uy tín cá nhân.

### 2. Danh sách trường

| STT | Tên trường | Kiểu dữ liệu | Null | Default | Khóa/Ràng buộc | Mô tả tác dụng trường | Ví dụ |
|---|---|---|---|---|---|---|---|
| 1 | id | char(36) | Không | - | PK | Khóa chính định danh duy nhất của bản ghi. | 10000000-0000-0000-0000-000000000001 |
| 2 | rater_id | char(36) | Không | - | FK | Người đánh giá. | 10000000-0000-0000-0000-000000000001 |
| 3 | rated_user_id | char(36) | Không | - | FK | Người được đánh giá. | 10000000-0000-0000-0000-000000000001 |
| 4 | post_id | char(36) | Có | - | FK | Bài tuyển làm ngữ cảnh đánh giá. | 10000000-0000-0000-0000-000000000001 |
| 5 | rating | unsignedTinyInteger | Không | - | - | Điểm đánh giá. | 1 |
| 6 | comment | text | Có | - | - | Nhận xét. | example |
| 7 | tags | json | Có | - | - | JSON nhãn đánh giá. | {"key":"value"} |
| 8 | created_at | timestamp | Có | - | - | Thời điểm tạo bản ghi. | 2026-06-22 09:00:00 |
| 9 | updated_at | timestamp | Có | - | - | Thời điểm cập nhật bản ghi gần nhất. | 2026-06-22 09:00:00 |

### 3. Khóa chính, khóa ngoại, index
- PK: id
- FK: post_id -> player_posts.id (on delete: set null)
- FK: rated_user_id -> users.id (on delete: restrict)
- FK: rater_id -> users.id (on delete: restrict)
- UNIQUE: player_ratings_context_unique (rater_id, rated_user_id, post_id)
- INDEX: player_ratings_rated_user_id_created_at_index (rated_user_id, created_at)

### 4. Quan hệ với bảng khác
- player_ratings n-1 player_posts qua post_id.
- player_ratings n-1 users qua rated_user_id.
- player_ratings n-1 users qua rater_id.

### 5. Ví dụ bản ghi
```json
{
    "id": "10000000-0000-0000-0000-000000000001",
    "rater_id": "10000000-0000-0000-0000-000000000001",
    "rated_user_id": "10000000-0000-0000-0000-000000000001",
    "post_id": "10000000-0000-0000-0000-000000000001",
    "rating": 1,
    "comment": "example",
    "tags": {
        "key": "value"
    },
    "created_at": "2026-06-22 09:00:00"
}
```

---

### MODULE: COMMUNITY

## Tên bảng: post_hashtags

### 1. Mục đích bảng
Liên kết hashtag với các loại bài viết

### 2. Danh sách trường

| STT | Tên trường | Kiểu dữ liệu | Null | Default | Khóa/Ràng buộc | Mô tả tác dụng trường | Ví dụ |
|---|---|---|---|---|---|---|---|
| 1 | id | unsignedBigInteger | Không | - | PK | Khóa chính định danh duy nhất của bản ghi. | 1 |
| 2 | hashtag_id | unsignedBigInteger | Không | - | FK | Hashtag được gắn.; VD: 10000000-0000-0000-0000-000000000001 | 1 |
| 3 | post_type | string(50) | Không | - | - | Loại bài viết được gắn hashtag: system_posts, community_posts, venue_posts.; VD: booking_reminder | default |
| 4 | post_id | string(100) | Không | - | - | ID bài viết được gắn hashtag; logical reference.; VD: 10000000-0000-0000-0000-000000000001 | 1 |
| 5 | created_at | timestamp | Có | - | - | Thời điểm gắn hashtag vào bài.; VD: 2026-06-15 18:00:00 | 2026-06-22 09:00:00 |

### 3. Khóa chính, khóa ngoại, index
- PK: id
- FK: hashtag_id -> hashtags.id (on delete: cascade)
- INDEX: post_hashtags_post_type_post_id_index (post_type, post_id)
- UNIQUE: post_hashtags_unique (hashtag_id, post_type, post_id)

### 4. Quan hệ với bảng khác
- post_hashtags n-1 hashtags qua hashtag_id.

### 5. Ví dụ bản ghi
```json
{
    "id": 1,
    "hashtag_id": 1,
    "post_type": "default",
    "post_id": 1,
    "created_at": "2026-06-22 09:00:00"
}
```

---

### MODULE: BOOKING

## Tên bảng: price_slots

### 1. Mục đích bảng
Lưu trữ bảng giá sân theo các khung giờ khác nhau của một cụm sân và loại môn.

### 2. Danh sách trường

| STT | Tên trường | Kiểu dữ liệu | Null | Default | Khóa/Ràng buộc | Mô tả tác dụng trường | Ví dụ |
|---|---|---|---|---|---|---|---|
| 1 | id | char(36) | Không | - | PK | Khóa chính định danh duy nhất của bản ghi. | 10000000-0000-0000-0000-000000000001 |
| 2 | venue_cluster_id | char(36) | Không | - | FK | Cụm sân áp dụng giá. | 10000000-0000-0000-0000-000000000001 |
| 3 | court_type_id | unsignedBigInteger | Không | - | FK | Loại sân áp dụng giá. | 1 |
| 4 | booking_type | enum(all, single, recurring) | Không | all | INDEX | Kiểu booking áp dụng giá. | all |
| 5 | start_time | time | Không | - | INDEX | Giờ bắt đầu khung giá. | 08:00:00 |
| 6 | end_time | time | Không | - | INDEX | Giờ kết thúc khung giá. | 08:00:00 |
| 7 | price | decimal(12,2) | Không | 0 | - | Giá tiền. | 100000 |
| 8 | apply_to_days | json | Có | - | - | Dữ liệu có cấu trúc hoặc nội dung dài phục vụ xử lý nghiệp vụ. | {"key":"value"} |
| 9 | is_active | boolean | Không | true | INDEX | Khung giá còn dùng. | true |
| 10 | created_at | timestamp | Có | - | - | Thời điểm tạo bản ghi. | 2026-06-22 09:00:00 |
| 11 | updated_at | timestamp | Có | - | - | Thời điểm cập nhật bản ghi gần nhất. | 2026-06-22 09:00:00 |

### 3. Khóa chính, khóa ngoại, index
- PK: id
- FK: court_type_id -> court_types.id (on delete: restrict)
- FK: venue_cluster_id -> venue_clusters.id (on delete: cascade)
- INDEX: price_slots_cluster_type_active_index (venue_cluster_id, court_type_id, booking_type, is_active)
- INDEX: price_slots_booking_type_index (booking_type)
- INDEX: price_slots_start_time_index (start_time)
- INDEX: price_slots_end_time_index (end_time)
- INDEX: price_slots_is_active_index (is_active)

### 4. Quan hệ với bảng khác
- price_slots n-1 court_types qua court_type_id.
- price_slots n-1 venue_clusters qua venue_cluster_id.

### 5. Ví dụ bản ghi
```json
{
    "id": "10000000-0000-0000-0000-000000000001",
    "venue_cluster_id": "10000000-0000-0000-0000-000000000001",
    "court_type_id": 1,
    "booking_type": "all",
    "start_time": "08:00:00",
    "end_time": "08:00:00",
    "price": 100000,
    "apply_to_days": {
        "key": "value"
    }
}
```

---

### MODULE: PAYMENT

## Tên bảng: refunds

### 1. Mục đích bảng
Quản lý yêu cầu hoàn tiền khi booking bị hủy sau khi đã thanh toán.

### 2. Danh sách trường

| STT | Tên trường | Kiểu dữ liệu | Null | Default | Khóa/Ràng buộc | Mô tả tác dụng trường | Ví dụ |
|---|---|---|---|---|---|---|---|
| 1 | id | char(36) | Không | - | PK | Khóa chính định danh duy nhất của bản ghi. | 10000000-0000-0000-0000-000000000001 |
| 2 | payment_id | char(36) | Không | - | FK | Payment gốc cần hoàn tiền. | 10000000-0000-0000-0000-000000000001 |
| 3 | booking_id | char(36) | Không | - | - | Booking liên quan, denormalized. | 10000000-0000-0000-0000-000000000001 |
| 4 | customer_id | char(36) | Có | - | FK, INDEX | User nhận hoàn tiền, denormalized từ booking. | 10000000-0000-0000-0000-000000000001 |
| 5 | amount | decimal(12,2) | Không | - | - | Số tiền yêu cầu hoàn. | 100000 |
| 6 | refund_destination | enum(original_payment, user_wallet, bank_account) | Không | original_payment | INDEX | Đích hoàn tiền: payment gốc, ví user hoặc tài khoản ngân hàng. | example |
| 7 | user_wallet_id | char(36) | Có | - | FK, INDEX | Ví user nhận tiền hoàn nếu refund_destination=user_wallet. | 10000000-0000-0000-0000-000000000001 |
| 8 | user_wallet_ledger_id | char(36) | Có | - | FK, INDEX | Ledger credit ví user khi hoàn tiền vào ví. | 10000000-0000-0000-0000-000000000001 |
| 9 | user_payout_account_id | char(36) | Có | - | FK, INDEX | Tài khoản ngân hàng user nhận tiền nếu hoàn về bank. | 10000000-0000-0000-0000-000000000001 |
| 10 | owner_wallet_ledger_id | char(36) | Có | - | FK, INDEX | Ledger debit ví owner nếu refund làm giảm doanh thu chủ sân. | 10000000-0000-0000-0000-000000000001 |
| 11 | policy_id | char(36) | Có | - | FK, INDEX | Khóa ngoại tham chiếu bảng `system_policies`, dùng để liên kết bản ghi với dữ liệu liên quan. | 10000000-0000-0000-0000-000000000001 |
| 12 | policy_rule_id | char(36) | Có | - | FK, INDEX | Khóa ngoại tham chiếu bảng `policy_rules`, dùng để liên kết bản ghi với dữ liệu liên quan. | 10000000-0000-0000-0000-000000000001 |
| 13 | policy_evaluation_log_id | char(36) | Có | - | FK, INDEX | Khóa ngoại tham chiếu bảng `policy_evaluation_logs`, dùng để liên kết bản ghi với dữ liệu liên quan. | 10000000-0000-0000-0000-000000000001 |
| 14 | reason | text | Có | - | - | Lý do yêu cầu hoàn. | Nội dung mẫu |
| 15 | status | enum(pending_confirmation, processing, completed, failed, rejected, pending_owner_confirmation, owner_confirmed, owner_rejected, admin_processing, cancelled) | Không | pending_owner_confirmation | - | Trạng thái refund. | pending_confirmation |
| 16 | status_reason | text | Có | - | - | Lý do từ chối/thất bại. | active |
| 17 | owner_confirmed_by | char(36) | Có | - | FK, INDEX | Owner/nhân viên sân xác nhận hoàn tiền. | 10000000-0000-0000-0000-000000000001 |
| 18 | owner_confirmed_at | timestamp | Có | - | - | Thời điểm owner xác nhận hoàn tiền. | 2026-06-22 09:00:00 |
| 19 | owner_confirm_note | text | Có | - | - | Ghi chú xác nhận hoàn tiền của owner. | Nội dung mẫu |
| 20 | processed_by | char(36) | Có | - | FK | Admin xử lý hoàn tiền. | 10000000-0000-0000-0000-000000000001 |
| 21 | processed_at | timestamp | Có | - | - | Thời điểm xử lý. | 2026-06-22 09:00:00 |
| 22 | admin_confirmed_by | char(36) | Có | - | FK, INDEX | Admin xác nhận refund hoàn tất sau khi API/giao dịch thành công. | 10000000-0000-0000-0000-000000000001 |
| 23 | admin_confirmed_at | timestamp | Có | - | - | Thời điểm admin xác nhận refund hoàn tất. | 2026-06-22 09:00:00 |
| 24 | completed_at | timestamp | Có | - | - | Thời điểm xảy ra sự kiện `completed_at`. | 2026-06-22 09:00:00 |
| 25 | gateway_refund_txn_id | string(100) | Có | - | INDEX | Mã giao dịch hoàn tiền từ gateway nếu có. | 1 |
| 26 | payout_transfer_code | string(40) | Có | - | UNIQUE | Mã nội dung chuyển khoản admin dùng khi hoàn tiền bằng QR. | CODE-001 |
| 27 | payout_qr_created_at | timestamp | Có | - | - | Thời điểm tạo QR chuyển tiền hoàn tiền gần nhất. | 2026-06-22 09:00:00 |
| 28 | created_at | timestamp | Có | - | - | Thời điểm tạo bản ghi. | 2026-06-22 09:00:00 |
| 29 | updated_at | timestamp | Có | - | - | Thời điểm cập nhật bản ghi gần nhất. | 2026-06-22 09:00:00 |

### 3. Khóa chính, khóa ngoại, index
- PK: id
- FK: payment_id -> payments.id (on delete: restrict)
- FK: processed_by -> users.id (on delete: set null)
- FK: customer_id -> users.id (on delete: set null)
- FK: user_wallet_id -> user_wallets.id (on delete: set null)
- FK: user_wallet_ledger_id -> user_wallet_ledgers.id (on delete: set null)
- FK: user_payout_account_id -> user_payout_accounts.id (on delete: set null)
- FK: owner_wallet_ledger_id -> owner_wallet_ledgers.id (on delete: set null)
- FK: owner_confirmed_by -> users.id (on delete: set null)
- FK: admin_confirmed_by -> users.id (on delete: set null)
- FK: policy_id -> system_policies.id (on delete: set null)
- FK: policy_rule_id -> policy_rules.id (on delete: set null)
- FK: policy_evaluation_log_id -> policy_evaluation_logs.id (on delete: set null)
- INDEX: refunds_booking_id_status_index (booking_id, status)
- INDEX: refunds_status_created_at_index (status, created_at)
- INDEX: refunds_customer_id_index (customer_id)
- INDEX: refunds_refund_destination_index (refund_destination)
- INDEX: refunds_user_wallet_id_index (user_wallet_id)
- INDEX: refunds_user_wallet_ledger_id_index (user_wallet_ledger_id)
- INDEX: refunds_user_payout_account_id_index (user_payout_account_id)
- INDEX: refunds_owner_wallet_ledger_id_index (owner_wallet_ledger_id)
- INDEX: refunds_owner_confirmed_by_index (owner_confirmed_by)
- INDEX: refunds_admin_confirmed_by_index (admin_confirmed_by)
- INDEX: refunds_gateway_refund_txn_id_index (gateway_refund_txn_id)
- UNIQUE: refunds_payout_transfer_code_unique (payout_transfer_code)
- INDEX: refunds_policy_id_index (policy_id)
- INDEX: refunds_policy_rule_id_index (policy_rule_id)
- INDEX: refunds_policy_evaluation_log_id_index (policy_evaluation_log_id)

### 4. Quan hệ với bảng khác
- refunds n-1 payments qua payment_id.
- refunds n-1 users qua processed_by.
- refunds n-1 users qua customer_id.
- refunds n-1 user_wallets qua user_wallet_id.
- refunds n-1 user_wallet_ledgers qua user_wallet_ledger_id.
- refunds n-1 user_payout_accounts qua user_payout_account_id.
- refunds n-1 owner_wallet_ledgers qua owner_wallet_ledger_id.
- refunds n-1 users qua owner_confirmed_by.
- refunds n-1 users qua admin_confirmed_by.
- refunds n-1 system_policies qua policy_id.
- refunds n-1 policy_rules qua policy_rule_id.
- refunds n-1 policy_evaluation_logs qua policy_evaluation_log_id.

### 5. Ví dụ bản ghi
```json
{
    "id": "10000000-0000-0000-0000-000000000001",
    "payment_id": "10000000-0000-0000-0000-000000000001",
    "booking_id": "10000000-0000-0000-0000-000000000001",
    "customer_id": "10000000-0000-0000-0000-000000000001",
    "amount": 100000,
    "refund_destination": "example",
    "user_wallet_id": "10000000-0000-0000-0000-000000000001",
    "user_wallet_ledger_id": "10000000-0000-0000-0000-000000000001"
}
```

---

### MODULE: SYSTEM/REPORT

## Tên bảng: reports

### 1. Mục đích bảng
Quản lý báo cáo xấu, spam

### 2. Danh sách trường

| STT | Tên trường | Kiểu dữ liệu | Null | Default | Khóa/Ràng buộc | Mô tả tác dụng trường | Ví dụ |
|---|---|---|---|---|---|---|---|
| 1 | id | char(36) | Không | - | PK | Khóa chính định danh duy nhất của bản ghi. | 10000000-0000-0000-0000-000000000001 |
| 2 | reporter_id | char(36) | Không | - | FK | User gửi report. | 10000000-0000-0000-0000-000000000001 |
| 3 | reportable_type | string(100) | Không | - | - | Loại đối tượng bị report; logical reference. | default |
| 4 | reportable_id | string(100) | Không | - | - | ID đối tượng bị report. | 1 |
| 5 | violation_type_id | unsignedBigInteger | Có | - | FK | Khóa ngoại tham chiếu bảng `violation_types`, dùng để liên kết bản ghi với dữ liệu liên quan. | 1 |
| 6 | severity_level | string(20) | Không | mild | INDEX | Trường dữ liệu phục vụ nghiệp vụ của bảng `reports`. | example |
| 7 | score_contribution | unsignedSmallInteger | Không | 0 | - | Trường dữ liệu phục vụ nghiệp vụ của bảng `reports`. | 1 |
| 8 | auto_action_taken | string(50) | Có | - | - | Trường dữ liệu phục vụ nghiệp vụ của bảng `reports`. | example |
| 9 | auto_actioned_at | timestamp | Có | - | - | Thời điểm xảy ra sự kiện `auto_actioned_at`. | 2026-06-22 09:00:00 |
| 10 | reason | enum(spam, offensive, fake, harassment, other) | Không | - | - | Lý do report. | Nội dung mẫu |
| 11 | description | text | Có | - | - | Mô tả chi tiết. | Nội dung mẫu |
| 12 | status | enum(pending, reviewing, resolved, dismissed) | Không | pending | INDEX | Trạng thái xử lý. | pending |
| 13 | action_taken | enum(warning, content_hidden, content_deleted, account_locked, venue_locked) | Có | - | - | Hành động đã áp dụng. | example |
| 14 | action_note | text | Có | - | - | Ghi chú xử lý. | Nội dung mẫu |
| 15 | reviewed_by | char(36) | Có | - | FK | Người xử lý. | 10000000-0000-0000-0000-000000000001 |
| 16 | reviewed_at | timestamp | Có | - | - | Thời điểm xử lý. | 2026-06-22 09:00:00 |
| 17 | created_at | timestamp | Có | - | INDEX | Thời điểm gửi report. | 2026-06-22 09:00:00 |

### 3. Khóa chính, khóa ngoại, index
- PK: id
- FK: reporter_id -> users.id (on delete: restrict)
- FK: reviewed_by -> users.id (on delete: set null)
- FK: violation_type_id -> violation_types.id (on delete: set null)
- INDEX: reports_reportable_type_reportable_id_index (reportable_type, reportable_id)
- UNIQUE: reports_reporter_target_unique (reporter_id, reportable_type, reportable_id)
- INDEX: reports_status_index (status)
- INDEX: reports_status_created_at_index (status, created_at)
- INDEX: reports_created_at_index (created_at)
- INDEX: reports_target_created_index (reportable_type, reportable_id, created_at)
- INDEX: reports_severity_level_index (severity_level)

### 4. Quan hệ với bảng khác
- reports n-1 users qua reporter_id.
- reports n-1 users qua reviewed_by.
- reports n-1 violation_types qua violation_type_id.

### 5. Ví dụ bản ghi
```json
{
    "id": "10000000-0000-0000-0000-000000000001",
    "reporter_id": "10000000-0000-0000-0000-000000000001",
    "reportable_type": "default",
    "reportable_id": 1,
    "violation_type_id": 1,
    "severity_level": "example",
    "score_contribution": 1,
    "auto_action_taken": "example"
}
```

---

## Tên bảng: reviews

### 1. Mục đích bảng
Người dùng đánh giá chất lượng của cụm sân sau khi hoàn thành một Booking.

### 2. Danh sách trường

| STT | Tên trường | Kiểu dữ liệu | Null | Default | Khóa/Ràng buộc | Mô tả tác dụng trường | Ví dụ |
|---|---|---|---|---|---|---|---|
| 1 | id | char(36) | Không | - | PK | Khóa chính định danh duy nhất của bản ghi. | 10000000-0000-0000-0000-000000000001 |
| 2 | booking_id | char(36) | Không | - | FK, UNIQUE | Booking được review; unique. | 10000000-0000-0000-0000-000000000001 |
| 3 | customer_id | char(36) | Không | - | INDEX | User đã đặt sân, denormalized. | 10000000-0000-0000-0000-000000000001 |
| 4 | venue_cluster_id | char(36) | Không | - | INDEX | Cụm sân được review, denormalized. | 10000000-0000-0000-0000-000000000001 |
| 5 | rating | unsignedTinyInteger | Không | - | - | Điểm đánh giá. | 1 |
| 6 | comment | text | Có | - | - | Nội dung review. | example |
| 7 | reply_content | text | Có | - | - | Phản hồi của chủ sân. | Nội dung mẫu |
| 8 | replied_at | timestamp | Có | - | - | Thời điểm chủ sân phản hồi. | 2026-06-22 09:00:00 |
| 9 | is_visible | boolean | Không | true | INDEX | Review có hiển thị công khai. | true |
| 10 | created_at | timestamp | Có | - | - | Thời điểm tạo bản ghi. | 2026-06-22 09:00:00 |
| 11 | updated_at | timestamp | Có | - | - | Thời điểm cập nhật bản ghi gần nhất. | 2026-06-22 09:00:00 |

### 3. Khóa chính, khóa ngoại, index
- PK: id
- FK: booking_id -> bookings.id (on delete: restrict)
- UNIQUE: reviews_booking_id_unique (booking_id)
- INDEX: reviews_customer_id_index (customer_id)
- INDEX: reviews_venue_cluster_id_index (venue_cluster_id)
- INDEX: reviews_is_visible_index (is_visible)
- INDEX: reviews_venue_cluster_id_is_visible_created_at_index (venue_cluster_id, is_visible, created_at)

### 4. Quan hệ với bảng khác
- reviews n-1 bookings qua booking_id.

### 5. Ví dụ bản ghi
```json
{
    "id": "10000000-0000-0000-0000-000000000001",
    "booking_id": "10000000-0000-0000-0000-000000000001",
    "customer_id": "10000000-0000-0000-0000-000000000001",
    "venue_cluster_id": "10000000-0000-0000-0000-000000000001",
    "rating": 1,
    "comment": "example",
    "reply_content": "Nội dung mẫu",
    "replied_at": "2026-06-22 09:00:00"
}
```

---

### MODULE: AUTH/RBAC

## Tên bảng: role_permissions

### 1. Mục đích bảng
Bảng trung gian n-n kết nối roles và permissions, định nghĩa 1 role có những quyền chi tiết nào.

### 2. Danh sách trường

| STT | Tên trường | Kiểu dữ liệu | Null | Default | Khóa/Ràng buộc | Mô tả tác dụng trường | Ví dụ |
|---|---|---|---|---|---|---|---|
| 1 | role_id | unsignedBigInteger | Không | - | PK, FK | Khóa ngoại tham chiếu bảng `roles`, dùng để liên kết bản ghi với dữ liệu liên quan. | 1 |
| 2 | permission_id | unsignedBigInteger | Không | - | PK, FK | Khóa ngoại tham chiếu bảng `permissions`, dùng để liên kết bản ghi với dữ liệu liên quan. | 1 |

### 3. Khóa chính, khóa ngoại, index
- PK: role_id, permission_id
- FK: permission_id -> permissions.id (on delete: cascade)
- FK: role_id -> roles.id (on delete: cascade)

### 4. Quan hệ với bảng khác
- role_permissions n-1 permissions qua permission_id.
- role_permissions n-1 roles qua role_id.

### 5. Ví dụ bản ghi
```json
{
    "role_id": 1,
    "permission_id": 1
}
```

---

### MODULE: BOOKING

## Tên bảng: slot_locks

### 1. Mục đích bảng
Quản lý việc khóa khung giờ (lock slot) do chủ sân tự block lịch hoặc block tạm thời khi user đang ở màn hình thanh toán.

### 2. Danh sách trường

| STT | Tên trường | Kiểu dữ liệu | Null | Default | Khóa/Ràng buộc | Mô tả tác dụng trường | Ví dụ |
|---|---|---|---|---|---|---|---|
| 1 | id | char(36) | Không | - | PK | Khóa chính định danh duy nhất của bản ghi. | 10000000-0000-0000-0000-000000000001 |
| 2 | venue_cluster_id | char(36) | Không | - | INDEX | Cụm sân bị giữ/khóa; denormalized. | 10000000-0000-0000-0000-000000000001 |
| 3 | venue_court_id | char(36) | Có | - | FK | Sân con bị giữ/khóa; nullable khi khóa cả cụm. | 10000000-0000-0000-0000-000000000001 |
| 4 | lock_scope | enum(court, cluster) | Không | court | INDEX | Phạm vi khóa. | example |
| 5 | booking_date | date | Không | - | INDEX | Ngày bị giữ/khóa. | 2026-06-22 |
| 6 | start_time | time | Không | - | INDEX | Giờ bắt đầu. | 08:00:00 |
| 7 | end_time | time | Không | - | INDEX | Giờ kết thúc. | 08:00:00 |
| 8 | locked_by | string(100) | Không | - | INDEX | Định danh người/session tạo lock. | example |
| 9 | booking_id | char(36) | Có | - | FK, INDEX | Booking liên quan. | 10000000-0000-0000-0000-000000000001 |
| 10 | booking_item_id | char(36) | Có | - | FK, INDEX | Item cụ thể được lock. | 10000000-0000-0000-0000-000000000001 |
| 11 | lock_type | enum(auto, manual) | Không | auto | - | Loại lock. | auto |
| 12 | reason | string(500) | Có | - | - | Lý do khóa lịch thủ công như bảo trì, nghỉ hoặc sự kiện riêng. | Nội dung mẫu |
| 13 | expires_at | timestamp | Không | - | INDEX | Thời điểm lock hết hạn. | 2026-06-22 09:00:00 |
| 14 | created_at | timestamp | Có | - | - | Thời điểm tạo lock. | 2026-06-22 09:00:00 |

### 3. Khóa chính, khóa ngoại, index
- PK: id
- FK: booking_id -> bookings.id (on delete: set null)
- FK: venue_court_id -> venue_courts.id (on delete: cascade)
- FK: booking_item_id -> booking_items.id (on delete: set null)
- INDEX: slot_locks_court_time_index (venue_court_id, booking_date, start_time, end_time)
- INDEX: slot_locks_venue_cluster_id_index (venue_cluster_id)
- INDEX: slot_locks_booking_date_index (booking_date)
- INDEX: slot_locks_start_time_index (start_time)
- INDEX: slot_locks_end_time_index (end_time)
- INDEX: slot_locks_lock_scope_index (lock_scope)
- INDEX: slot_locks_locked_by_index (locked_by)
- INDEX: slot_locks_expires_at_index (expires_at)
- INDEX: slot_locks_booking_id_foreign (booking_id)
- INDEX: slot_locks_booking_item_id_index (booking_item_id)

### 4. Quan hệ với bảng khác
- slot_locks n-1 bookings qua booking_id.
- slot_locks n-1 venue_courts qua venue_court_id.
- slot_locks n-1 booking_items qua booking_item_id.

### 5. Ví dụ bản ghi
```json
{
    "id": "10000000-0000-0000-0000-000000000001",
    "venue_cluster_id": "10000000-0000-0000-0000-000000000001",
    "venue_court_id": "10000000-0000-0000-0000-000000000001",
    "lock_scope": "example",
    "booking_date": "2026-06-22",
    "start_time": "08:00:00",
    "end_time": "08:00:00",
    "locked_by": "example"
}
```

---

### MODULE: SYSTEM

## Tên bảng: system_policies

### 1. Mục đích bảng
Điều khoản, chính sách (bảo mật, hoàn tiền, v.v.)

### 2. Danh sách trường

| STT | Tên trường | Kiểu dữ liệu | Null | Default | Khóa/Ràng buộc | Mô tả tác dụng trường | Ví dụ |
|---|---|---|---|---|---|---|---|
| 1 | id | char(36) | Không | - | PK | Khóa chính định danh duy nhất của bản ghi. | 10000000-0000-0000-0000-000000000001 |
| 2 | key | string(100) | Không | - | - | Mã chính sách. | example |
| 3 | policy_category | enum(document, numeric_threshold, penalty_matrix, percentage_table) | Không | document | INDEX | Loại hoặc nhóm phân loại của bản ghi. | document |
| 4 | version | unsignedInteger | Không | 1 | - | Phiên bản chính sách. | 1 |
| 5 | title | string(255) | Không | - | - | Tiêu đề chính sách. | Ví dụ SportGo |
| 6 | content | longText | Không | - | - | Nội dung đầy đủ. | Nội dung mẫu |
| 7 | type | enum(general, refund, booking, moderation) | Không | general | INDEX | Loại chính sách. | general |
| 8 | policy_type | string(50) | Có | - | - | Nhóm nghiệp vụ của chính sách dùng cho rule engine. | default |
| 9 | is_overridable | boolean | Không | false | - | Cho phép sân cấu hình rule override trong phạm vi hệ thống cho phép. | true |
| 10 | priority | integer | Không | 0 | - | Độ ưu tiên khi nhiều chính sách cùng áp dụng. | 1 |
| 11 | is_active | boolean | Không | true | INDEX | Chính sách đang có hiệu lực. | true |
| 12 | status | enum(draft, active, inactive, archived) | Không | active | - | Trạng thái vòng đời của chính sách. | draft |
| 13 | effective_from | timestamp | Có | - | INDEX | Thời điểm bắt đầu hiệu lực. | 2026-06-22 09:00:00 |
| 14 | effective_to | timestamp | Có | - | - | Trường dữ liệu phục vụ nghiệp vụ của bảng `system_policies`. | 2026-06-22 09:00:00 |
| 15 | published_at | timestamp | Có | - | - | Thời điểm xảy ra sự kiện `published_at`. | 2026-06-22 09:00:00 |
| 16 | published_by | char(36) | Có | - | FK, INDEX | Khóa ngoại tham chiếu bảng `users`, dùng để liên kết bản ghi với dữ liệu liên quan. | 10000000-0000-0000-0000-000000000001 |
| 17 | replaced_policy_id | char(36) | Có | - | FK, INDEX | Khóa ngoại tham chiếu bảng `system_policies`, dùng để liên kết bản ghi với dữ liệu liên quan. | 10000000-0000-0000-0000-000000000001 |
| 18 | require_reaccept | boolean | Không | false | - | Cờ bật/tắt cho hành vi hoặc trạng thái tương ứng. | true |
| 19 | change_summary | text | Có | - | - | Trường dữ liệu phục vụ nghiệp vụ của bảng `system_policies`. | example |
| 20 | created_by | char(36) | Có | - | FK | Admin tạo. | 10000000-0000-0000-0000-000000000001 |
| 21 | updated_by | char(36) | Có | - | FK | Admin cập nhật. | 10000000-0000-0000-0000-000000000001 |
| 22 | created_at | timestamp | Có | - | - | Thời điểm tạo bản ghi. | 2026-06-22 09:00:00 |
| 23 | updated_at | timestamp | Có | - | - | Thời điểm cập nhật bản ghi gần nhất. | 2026-06-22 09:00:00 |

### 3. Khóa chính, khóa ngoại, index
- PK: id
- FK: created_by -> users.id (on delete: set null)
- FK: updated_by -> users.id (on delete: set null)
- FK: published_by -> users.id (on delete: set null)
- FK: replaced_policy_id -> system_policies.id (on delete: set null)
- UNIQUE: system_policies_key_version_unique (key, version)
- INDEX: system_policies_type_index (type)
- INDEX: system_policies_is_active_index (is_active)
- INDEX: system_policies_effective_from_index (effective_from)
- INDEX: system_policies_published_by_index (published_by)
- INDEX: system_policies_replaced_policy_id_index (replaced_policy_id)
- INDEX: system_policies_status_active_index (status, is_active)
- INDEX: system_policies_policy_category_index (policy_category)

### 4. Quan hệ với bảng khác
- system_policies n-1 users qua created_by.
- system_policies n-1 users qua updated_by.
- system_policies n-1 users qua published_by.
- system_policies n-1 system_policies qua replaced_policy_id.

### 5. Ví dụ bản ghi
```json
{
    "id": "10000000-0000-0000-0000-000000000001",
    "key": "example",
    "policy_category": "document",
    "version": 1,
    "title": "Ví dụ SportGo",
    "content": "Nội dung mẫu",
    "type": "general",
    "policy_type": "default"
}
```

---

### MODULE: COMMUNITY

## Tên bảng: system_posts

### 1. Mục đích bảng
Admin đăng thông báo, tin tức hệ thống

### 2. Danh sách trường

| STT | Tên trường | Kiểu dữ liệu | Null | Default | Khóa/Ràng buộc | Mô tả tác dụng trường | Ví dụ |
|---|---|---|---|---|---|---|---|
| 1 | id | char(36) | Không | - | PK | Khóa chính định danh duy nhất của bản ghi. | 10000000-0000-0000-0000-000000000001 |
| 2 | author_id | char(36) | Có | - | FK | Admin/nhân viên tạo bài viết. | 10000000-0000-0000-0000-000000000001 |
| 3 | title | string(255) | Không | - | - | Tiêu đề bài viết. | Ví dụ SportGo |
| 4 | slug | string(255) | Không | - | UNIQUE | Slug duy nhất. | example |
| 5 | content | longText | Không | - | - | Nội dung bài viết. | Nội dung mẫu |
| 6 | thumbnail_path | string(1000) | Có | - | - | Đường dẫn ảnh đại diện. | /storage/example.pdf |
| 7 | status | enum(draft, published, hidden) | Không | draft | INDEX | Trạng thái bài viết. | draft |
| 8 | published_at | timestamp | Có | - | INDEX | Thời điểm công khai. | 2026-06-22 09:00:00 |
| 9 | view_count | unsignedBigInteger | Không | 0 | - | Số lượt xem. | 1 |
| 10 | created_at | timestamp | Có | - | - | Thời điểm tạo bản ghi. | 2026-06-22 09:00:00 |
| 11 | updated_at | timestamp | Có | - | - | Thời điểm cập nhật bản ghi gần nhất. | 2026-06-22 09:00:00 |

### 3. Khóa chính, khóa ngoại, index
- PK: id
- FK: author_id -> users.id (on delete: set null)
- UNIQUE: system_posts_slug_unique (slug)
- INDEX: system_posts_status_index (status)
- INDEX: system_posts_published_at_index (published_at)

### 4. Quan hệ với bảng khác
- system_posts n-1 users qua author_id.

### 5. Ví dụ bản ghi
```json
{
    "id": "10000000-0000-0000-0000-000000000001",
    "author_id": "10000000-0000-0000-0000-000000000001",
    "title": "Ví dụ SportGo",
    "slug": "example",
    "content": "Nội dung mẫu",
    "thumbnail_path": "/storage/example.pdf",
    "status": "draft",
    "published_at": "2026-06-22 09:00:00"
}
```

---

### MODULE: AUTH/RBAC

## Tên bảng: user_permission_revokes

### 1. Mục đích bảng
Bảng quản lý việc "rút" một quyền cụ thể của 1 user nhất định, kể cả khi role của họ có cấp quyền đó. Hỗ trợ scope (phạm vi).

### 2. Danh sách trường

| STT | Tên trường | Kiểu dữ liệu | Null | Default | Khóa/Ràng buộc | Mô tả tác dụng trường | Ví dụ |
|---|---|---|---|---|---|---|---|
| 1 | id | unsignedBigInteger | Không | - | PK | Khóa chính định danh duy nhất của bản ghi. | 1 |
| 2 | user_id | char(36) | Không | - | FK | User bị thu hồi quyền, trỏ users.id.; VD: 10000000-0000-0000-0000-000000000001 | 10000000-0000-0000-0000-000000000001 |
| 3 | permission_id | unsignedBigInteger | Không | - | FK | Quyền bị thu hồi, trỏ permissions.id.; VD: booking.manage | 1 |
| 4 | scope_type | enum(255) | Không | system | INDEX | Phạm vi thu hồi quyền: system hoặc venue. Giá trị enum: system=hệ thống; venue=theo cụm sân.; VD: booking_reminder | system |
| 5 | scope_id | char(36) | Không | 00000000-0000-0000-0000-000000000000 | INDEX | ID phạm vi thu hồi. Với system dùng zero UUID; với venue là venue_clusters.id.; VD: 10000000-0000-0000-0000-000000000001 | 10000000-0000-0000-0000-000000000001 |
| 6 | revoked_by | char(36) | Có | - | FK | Người thực hiện thu hồi quyền, trỏ users.id.; VD: 10000000-0000-0000-0000-000000000001 | 10000000-0000-0000-0000-000000000001 |
| 7 | reason | string(255) | Có | - | - | Lý do thu hồi quyền để admin xem lại.; VD: Nội dung mẫu dùng để demo. | Nội dung mẫu |
| 8 | created_at | timestamp | Có | - | - | Thời điểm thu hồi quyền.; VD: 2026-06-15 18:00:00 | 2026-06-22 09:00:00 |

### 3. Khóa chính, khóa ngoại, index
- PK: id
- FK: permission_id -> permissions.id (on delete: cascade)
- FK: revoked_by -> users.id (on delete: set null)
- FK: user_id -> users.id (on delete: cascade)
- UNIQUE: user_permission_revokes_scope_unique (user_id, permission_id, scope_type, scope_id)
- INDEX: user_permission_revokes_scope_type_index (scope_type)
- INDEX: user_permission_revokes_scope_id_index (scope_id)

### 4. Quan hệ với bảng khác
- user_permission_revokes n-1 permissions qua permission_id.
- user_permission_revokes n-1 users qua revoked_by.
- user_permission_revokes n-1 users qua user_id.

### 5. Ví dụ bản ghi
```json
{
    "id": 1,
    "user_id": "10000000-0000-0000-0000-000000000001",
    "permission_id": 1,
    "scope_type": "system",
    "scope_id": "10000000-0000-0000-0000-000000000001",
    "revoked_by": "10000000-0000-0000-0000-000000000001",
    "reason": "Nội dung mẫu",
    "created_at": "2026-06-22 09:00:00"
}
```

---

### MODULE: SYSTEM

## Tên bảng: user_policy_acceptances

### 1. Mục đích bảng
Ghi nhận user đã đồng ý phiên bản chính sách

### 2. Danh sách trường

| STT | Tên trường | Kiểu dữ liệu | Null | Default | Khóa/Ràng buộc | Mô tả tác dụng trường | Ví dụ |
|---|---|---|---|---|---|---|---|
| 1 | id | unsignedBigInteger | Không | - | PK | Khóa chính định danh duy nhất của bản ghi. | 1 |
| 2 | user_id | char(36) | Không | - | FK | Khóa ngoại tham chiếu bảng `users`, dùng để liên kết bản ghi với dữ liệu liên quan. | 10000000-0000-0000-0000-000000000001 |
| 3 | system_policy_id | char(36) | Không | - | FK | Khóa ngoại tham chiếu bảng `system_policies`, dùng để liên kết bản ghi với dữ liệu liên quan. | 10000000-0000-0000-0000-000000000001 |
| 4 | policy_version | string(50) | Không | - | - | Trường dữ liệu phục vụ nghiệp vụ của bảng `user_policy_acceptances`. | example |
| 5 | accepted_at | timestamp | Không | CURRENT_TIMESTAMP | - | Thời điểm xảy ra sự kiện `accepted_at`. | 2026-06-22 09:00:00 |
| 6 | ip_address | string(45) | Có | - | - | Địa chỉ IP tại thời điểm thực hiện thao tác. | example |
| 7 | user_agent | string(500) | Có | - | - | Thông tin trình duyệt/thiết bị tại thời điểm thao tác. | example |

### 3. Khóa chính, khóa ngoại, index
- PK: id
- FK: system_policy_id -> system_policies.id (on delete: cascade)
- FK: user_id -> users.id (on delete: cascade)
- UNIQUE: user_policy_acceptances_unique (user_id, system_policy_id, policy_version)

### 4. Quan hệ với bảng khác
- user_policy_acceptances n-1 system_policies qua system_policy_id.
- user_policy_acceptances n-1 users qua user_id.

### 5. Ví dụ bản ghi
```json
{
    "id": 1,
    "user_id": "10000000-0000-0000-0000-000000000001",
    "system_policy_id": "10000000-0000-0000-0000-000000000001",
    "policy_version": "example",
    "accepted_at": "2026-06-22 09:00:00",
    "ip_address": "example",
    "user_agent": "example"
}
```

---

### MODULE: AUTH/RBAC

## Tên bảng: user_roles

### 1. Mục đích bảng
Bảng trung gian n-n kết nối users và roles. Đặc biệt hỗ trợ phân quyền theo phạm vi (scope) để 1 user có thể làm chủ sân A nhưng không có quyền ở sân B.

### 2. Danh sách trường

| STT | Tên trường | Kiểu dữ liệu | Null | Default | Khóa/Ràng buộc | Mô tả tác dụng trường | Ví dụ |
|---|---|---|---|---|---|---|---|
| 1 | id | unsignedBigInteger | Không | - | PK | Khóa chính định danh duy nhất của bản ghi. | 1 |
| 2 | user_id | char(36) | Không | - | FK | User nhận role, trỏ users.id.; VD: 10000000-0000-0000-0000-000000000001 | 10000000-0000-0000-0000-000000000001 |
| 3 | role_id | unsignedBigInteger | Không | - | FK | Role được gán, trỏ roles.id.; VD: venue_owner | 1 |
| 4 | scope_type | enum(system, venue) | Không | system | INDEX | Phạm vi role: system là toàn hệ thống, venue là trong một cụm sân. | system |
| 5 | scope_id | char(36) | Không | 00000000-0000-0000-0000-000000000000 | INDEX | ID phạm vi. Với system dùng zero UUID; với venue là venue_clusters.id. | 10000000-0000-0000-0000-000000000001 |
| 6 | granted_by | char(36) | Có | - | FK | Admin/chủ sân đã gán role, trỏ users.id.; VD: 10000000-0000-0000-0000-000000000001 | 10000000-0000-0000-0000-000000000001 |
| 7 | created_at | timestamp | Có | - | - | Thời điểm gán role.; VD: 2026-06-15 18:00:00 | 2026-06-22 09:00:00 |

### 3. Khóa chính, khóa ngoại, index
- PK: id
- FK: granted_by -> users.id (on delete: set null)
- FK: role_id -> roles.id (on delete: cascade)
- FK: user_id -> users.id (on delete: cascade)
- UNIQUE: user_roles_scope_unique (user_id, role_id, scope_type, scope_id)
- INDEX: user_roles_scope_type_index (scope_type)
- INDEX: user_roles_scope_id_index (scope_id)

### 4. Quan hệ với bảng khác
- user_roles n-1 users qua granted_by.
- user_roles n-1 roles qua role_id.
- user_roles n-1 users qua user_id.

### 5. Ví dụ bản ghi
```json
{
    "id": 1,
    "user_id": "10000000-0000-0000-0000-000000000001",
    "role_id": 1,
    "scope_type": "system",
    "scope_id": "10000000-0000-0000-0000-000000000001",
    "granted_by": "10000000-0000-0000-0000-000000000001",
    "created_at": "2026-06-22 09:00:00"
}
```

---

### MODULE: SYSTEM

## Tên bảng: verification_codes

### 1. Mục đích bảng
Lưu mã xác thực OTP dùng cho việc đăng ký, xác nhận số điện thoại, và quên mật khẩu.

### 2. Danh sách trường

| STT | Tên trường | Kiểu dữ liệu | Null | Default | Khóa/Ràng buộc | Mô tả tác dụng trường | Ví dụ |
|---|---|---|---|---|---|---|---|
| 1 | id | unsignedBigInteger | Không | - | PK | Khóa chính định danh duy nhất của bản ghi. | 1 |
| 2 | user_id | char(36) | Có | - | FK | User liên quan nếu đã có tài khoản; nullable cho reset hoặc pre-register.; VD: 10000000-0000-0000-0000-000000000001 | 10000000-0000-0000-0000-000000000001 |
| 3 | identifier | string(255) | Không | - | INDEX | Email hoặc phone nhận mã.; VD: giá trị mẫu | example |
| 4 | type | enum(255) | Không | - | INDEX | Mục đích mã: register, reset_password, phone_verify, email_verify. Giá trị enum: register=đăng ký; reset_password=đặt lại mật khẩu; phone_verify=xác thực phone; email_verify=xác thực email.; VD: booking_reminder | register |
| 5 | channel | enum(255) | Không | email | - | Kênh gửi mã: email hoặc sms. Giá trị enum: email=email; sms=tin nhắn SMS.; VD: giá trị mẫu | example |
| 6 | code | string(255) | Không | - | - | Mã xác thực đã sinh.; VD: SPORTGO_CODE_001 | CODE-001 |
| 7 | attempt_count | unsignedSmallInteger | Không | 0 | - | Số lần user đã thử nhập mã.; VD: 60 | 1 |
| 8 | max_attempts | unsignedSmallInteger | Không | 5 | - | Số lần thử tối đa trước khi khóa mã.; VD: 60 | 1 |
| 9 | is_used | boolean | Không | false | INDEX | Đánh dấu mã đã được dùng để tránh dùng lại.; VD: true | true |
| 10 | expires_at | timestamp | Không | - | INDEX | Thời điểm mã hết hạn.; VD: 2026-06-15 18:00:00 | 2026-06-22 09:00:00 |
| 11 | created_at | timestamp | Có | - | - | Thời điểm sinh mã.; VD: 2026-06-15 18:00:00 | 2026-06-22 09:00:00 |

### 3. Khóa chính, khóa ngoại, index
- PK: id
- FK: user_id -> users.id (on delete: cascade)
- INDEX: verification_codes_lookup_index (identifier, type, is_used)
- INDEX: verification_codes_identifier_index (identifier)
- INDEX: verification_codes_type_index (type)
- INDEX: verification_codes_is_used_index (is_used)
- INDEX: verification_codes_expires_at_index (expires_at)

### 4. Quan hệ với bảng khác
- verification_codes n-1 users qua user_id.

### 5. Ví dụ bản ghi
```json
{
    "id": 1,
    "user_id": "10000000-0000-0000-0000-000000000001",
    "identifier": "example",
    "type": "register",
    "channel": "example",
    "code": "CODE-001",
    "attempt_count": 1,
    "max_attempts": 1
}
```

---

### MODULE: AUTH/RBAC

## Tên bảng: personal_access_tokens

### 1. Mục đích bảng
Bảng chuẩn của gói Laravel Sanctum dùng để lưu trữ và xác thực token API của users.

### 2. Danh sách trường

| STT | Tên trường | Kiểu dữ liệu | Null | Default | Khóa/Ràng buộc | Mô tả tác dụng trường | Ví dụ |
|---|---|---|---|---|---|---|---|
| 1 | id | unsignedBigInteger | Không | - | PK | Khóa chính định danh duy nhất của bản ghi. | 1 |
| 2 | tokenable_type | string(255) | Không | - | - | Loại hoặc nhóm phân loại của bản ghi. | default |
| 3 | tokenable_id | char(36) | Không | - | - | ID tham chiếu đối tượng liên quan trong nghiệp vụ. | 10000000-0000-0000-0000-000000000001 |
| 4 | name | text | Không | - | - | Tên/tiêu đề hiển thị hoặc dùng trong quản trị. | Ví dụ SportGo |
| 5 | token | string(64) | Không | - | UNIQUE | Token đã hash hoặc chuỗi xác thực dùng một lần. | example |
| 6 | abilities | text | Có | - | - | Trường dữ liệu phục vụ nghiệp vụ của bảng `personal_access_tokens`. | example |
| 7 | last_used_at | timestamp | Có | - | - | Thời điểm xảy ra sự kiện `last_used_at`. | 2026-06-22 09:00:00 |
| 8 | expires_at | timestamp | Có | - | INDEX | Thời điểm hết hạn hiệu lực. | 2026-06-22 09:00:00 |
| 9 | created_at | timestamp | Có | - | - | Thời điểm tạo bản ghi. | 2026-06-22 09:00:00 |
| 10 | updated_at | timestamp | Có | - | - | Thời điểm cập nhật bản ghi gần nhất. | 2026-06-22 09:00:00 |

### 3. Khóa chính, khóa ngoại, index
- PK: id
- INDEX: tokenable_morphs_index (tokenable_type, tokenable_id)
- UNIQUE: personal_access_tokens_token_unique (token)
- INDEX: personal_access_tokens_expires_at_index (expires_at)

### 4. Quan hệ với bảng khác
- Không có khóa ngoại trực tiếp trong migration.

### 5. Ví dụ bản ghi
```json
{
    "id": 1,
    "tokenable_type": "default",
    "tokenable_id": "10000000-0000-0000-0000-000000000001",
    "name": "Ví dụ SportGo",
    "token": "example",
    "abilities": "example",
    "last_used_at": "2026-06-22 09:00:00",
    "expires_at": "2026-06-22 09:00:00"
}
```

---

### MODULE: PAYMENT

## Tên bảng: system_bank_accounts

### 1. Mục đích bảng
Quản lý danh sách các tài khoản ngân hàng của hệ thống (dùng để tích hợp tạo mã QR thanh toán qua SePay).

### 2. Danh sách trường

| STT | Tên trường | Kiểu dữ liệu | Null | Default | Khóa/Ràng buộc | Mô tả tác dụng trường | Ví dụ |
|---|---|---|---|---|---|---|---|
| 1 | id | char(36) | Không | - | PK | Khóa chính định danh duy nhất của bản ghi. | 10000000-0000-0000-0000-000000000001 |
| 2 | name | string(100) | Không | Tài khoản nhận tiền hệ thống | - | Tên gợi nhớ nội bộ. | Ví dụ SportGo |
| 3 | bank_name | string(100) | Có | - | - | Tên ngân hàng hiển thị cho người dùng. | Ví dụ SportGo |
| 4 | bank_code | string(50) | Không | - | - | Mã ngân hàng dùng để tạo QR SePay. | CODE-001 |
| 5 | account_number | string(50) | Không | - | - | Số tài khoản hệ thống nhận tiền. | 1 |
| 6 | account_holder_name | string(150) | Không | - | - | Tên chủ tài khoản hệ thống. | 1 |
| 7 | status | enum(active, inactive) | Không | active | - | Trạng thái sử dụng. | active |
| 8 | is_default | boolean | Không | false | - | Tài khoản nhận tiền mặc định. | true |
| 9 | created_at | timestamp | Có | - | - | Thời điểm tạo bản ghi. | 2026-06-22 09:00:00 |
| 10 | updated_at | timestamp | Có | - | - | Thời điểm cập nhật bản ghi gần nhất. | 2026-06-22 09:00:00 |

### 3. Khóa chính, khóa ngoại, index
- PK: id
- UNIQUE: system_bank_accounts_bank_account_unique (bank_code, account_number)
- INDEX: system_bank_accounts_status_default_index (status, is_default)

### 4. Quan hệ với bảng khác
- Không có khóa ngoại trực tiếp trong migration.

### 5. Ví dụ bản ghi
```json
{
    "id": "10000000-0000-0000-0000-000000000001",
    "name": "Ví dụ SportGo",
    "bank_name": "Ví dụ SportGo",
    "bank_code": "CODE-001",
    "account_number": 1,
    "account_holder_name": 1,
    "status": "active",
    "is_default": true
}
```

---

## Tên bảng: owner_wallets

### 1. Mục đích bảng
Quản lý ví tiền của mỗi chủ sân. Tiền khách thanh toán vào TK hệ thống sẽ được cộng vào ví này (đóng vai trò như số dư thu hộ).

### 2. Danh sách trường

| STT | Tên trường | Kiểu dữ liệu | Null | Default | Khóa/Ràng buộc | Mô tả tác dụng trường | Ví dụ |
|---|---|---|---|---|---|---|---|
| 1 | id | char(36) | Không | - | PK | Khóa chính định danh duy nhất của bản ghi. | 10000000-0000-0000-0000-000000000001 |
| 2 | owner_id | char(36) | Không | - | FK, UNIQUE | Chủ sân sở hữu ví. | 10000000-0000-0000-0000-000000000001 |
| 3 | venue_cluster_id | char(36) | Có | - | FK | Cụm sân sở hữu ví này. | 10000000-0000-0000-0000-000000000001 |
| 4 | available_balance | decimal(14,2) | Không | 0 | - | Số dư có thể rút. | 100000 |
| 5 | pending_withdrawal_balance | decimal(14,2) | Không | 0 | - | Số tiền đang giữ cho lệnh rút. | 100000 |
| 6 | total_earned | decimal(14,2) | Không | 0 | - | Tổng tiền hệ thống đã thu hộ. | 1 |
| 7 | total_withdrawn | decimal(14,2) | Không | 0 | - | Tổng tiền đã chi trả cho chủ sân. | 1 |
| 8 | created_at | timestamp | Có | - | - | Thời điểm tạo bản ghi. | 2026-06-22 09:00:00 |
| 9 | updated_at | timestamp | Có | - | - | Thời điểm cập nhật bản ghi gần nhất. | 2026-06-22 09:00:00 |

### 3. Khóa chính, khóa ngoại, index
- PK: id
- FK: venue_cluster_id -> venue_clusters.id (on delete: cascade)
- FK: owner_id -> users.id (on delete: restrict)
- UNIQUE: owner_wallets_owner_id_unique (owner_id)
- UNIQUE: owner_wallets_owner_venue_cluster_unique (owner_id, venue_cluster_id)

### 4. Quan hệ với bảng khác
- owner_wallets n-1 venue_clusters qua venue_cluster_id.
- owner_wallets n-1 users qua owner_id.

### 5. Ví dụ bản ghi
```json
{
    "id": "10000000-0000-0000-0000-000000000001",
    "owner_id": "10000000-0000-0000-0000-000000000001",
    "venue_cluster_id": "10000000-0000-0000-0000-000000000001",
    "available_balance": 100000,
    "pending_withdrawal_balance": 100000,
    "total_earned": 1,
    "total_withdrawn": 1,
    "created_at": "2026-06-22 09:00:00"
}
```

---

## Tên bảng: owner_wallet_ledgers

### 1. Mục đích bảng
Sổ phụ ghi chú từng biến động của ví chủ sân (cộng tiền do booking, trừ tiền do rút).

### 2. Danh sách trường

| STT | Tên trường | Kiểu dữ liệu | Null | Default | Khóa/Ràng buộc | Mô tả tác dụng trường | Ví dụ |
|---|---|---|---|---|---|---|---|
| 1 | id | char(36) | Không | - | PK | Khóa chính định danh duy nhất của bản ghi. | 10000000-0000-0000-0000-000000000001 |
| 2 | owner_wallet_id | char(36) | Không | - | FK | Ví chủ sân được ghi nhận. | 10000000-0000-0000-0000-000000000001 |
| 3 | owner_id | char(36) | Không | - | FK | Chủ sân được hưởng tiền. | 10000000-0000-0000-0000-000000000001 |
| 4 | venue_cluster_id | char(36) | Có | - | FK | Cụm sân phát sinh doanh thu. | 10000000-0000-0000-0000-000000000001 |
| 5 | booking_id | char(36) | Có | - | FK, INDEX | Booking phát sinh doanh thu. | 10000000-0000-0000-0000-000000000001 |
| 6 | payment_id | char(36) | Có | - | FK, INDEX | Payment phát sinh dòng tiền. | 10000000-0000-0000-0000-000000000001 |
| 7 | type | enum(credit, debit, hold, release) | Không | - | - | Loại biến động số dư. | credit |
| 8 | direction | enum(credit, debit) | Có | - | INDEX | Chiều biến động số dư, bổ sung để đối soát rõ hơn. | example |
| 9 | amount | decimal(14,2) | Không | - | - | Số tiền biến động. | 100000 |
| 10 | balance_before | decimal(14,2) | Không | - | - | Số dư trước biến động. | 100000 |
| 11 | balance_after | decimal(14,2) | Không | - | - | Số dư sau biến động. | 100000 |
| 12 | status | enum(pending, completed, failed, cancelled) | Không | completed | INDEX | Trạng thái giao dịch ví chủ sân. | pending |
| 13 | reference_code | string(100) | Có | - | - | Mã tham chiếu nội bộ/gateway. | CODE-001 |
| 14 | reference_type | string(100) | Có | - | - | Loại đối tượng tham chiếu như booking, payment, refund, withdrawal. | default |
| 15 | reference_id | string(100) | Có | - | - | ID đối tượng tham chiếu. | 1 |
| 16 | transaction_code | string(50) | Có | - | UNIQUE | Mã giao dịch ví nội bộ. | CODE-001 |
| 17 | description | text | Có | - | - | Ghi chú nghiệp vụ. | Nội dung mẫu |
| 18 | note | text | Có | - | - | Ghi chú nghiệp vụ ngắn. | Nội dung mẫu |
| 19 | metadata | json | Có | - | - | Thông tin bổ sung. | {"key":"value"} |
| 20 | created_at | timestamp | Có | - | - | Thời điểm tạo bản ghi. | 2026-06-22 09:00:00 |
| 21 | updated_at | timestamp | Có | - | - | Thời điểm cập nhật bản ghi gần nhất. | 2026-06-22 09:00:00 |

### 3. Khóa chính, khóa ngoại, index
- PK: id
- FK: owner_wallet_id -> owner_wallets.id (on delete: restrict)
- FK: owner_id -> users.id (on delete: restrict)
- FK: venue_cluster_id -> venue_clusters.id (on delete: set null)
- FK: booking_id -> bookings.id (on delete: set null)
- FK: payment_id -> payments.id (on delete: set null)
- INDEX: owner_wallet_ledgers_owner_created_at_index (owner_id, created_at)
- INDEX: owner_wallet_ledgers_cluster_created_at_index (venue_cluster_id, created_at)
- INDEX: owner_wallet_ledgers_booking_id_index (booking_id)
- INDEX: owner_wallet_ledgers_payment_id_index (payment_id)
- UNIQUE: owner_wallet_ledgers_payment_type_unique (payment_id, type)
- UNIQUE: owner_wallet_ledgers_transaction_code_unique (transaction_code)
- INDEX: owner_wallet_ledgers_direction_index (direction)
- INDEX: owner_wallet_ledgers_status_index (status)
- INDEX: owner_wallet_ledgers_reference_index (reference_type, reference_id)

### 4. Quan hệ với bảng khác
- owner_wallet_ledgers n-1 owner_wallets qua owner_wallet_id.
- owner_wallet_ledgers n-1 users qua owner_id.
- owner_wallet_ledgers n-1 venue_clusters qua venue_cluster_id.
- owner_wallet_ledgers n-1 bookings qua booking_id.
- owner_wallet_ledgers n-1 payments qua payment_id.

### 5. Ví dụ bản ghi
```json
{
    "id": "10000000-0000-0000-0000-000000000001",
    "owner_wallet_id": "10000000-0000-0000-0000-000000000001",
    "owner_id": "10000000-0000-0000-0000-000000000001",
    "venue_cluster_id": "10000000-0000-0000-0000-000000000001",
    "booking_id": "10000000-0000-0000-0000-000000000001",
    "payment_id": "10000000-0000-0000-0000-000000000001",
    "type": "credit",
    "direction": "example"
}
```

---

### MODULE: BOOKING

## Tên bảng: booking_items

### 1. Mục đích bảng
Lưu trữ từng sân con và khung giờ cụ thể trong một booking, phục vụ luồng đặt nhiều sân/nhiều slot trong cùng một đơn.

### 2. Danh sách trường

| STT | Tên trường | Kiểu dữ liệu | Null | Default | Khóa/Ràng buộc | Mô tả tác dụng trường | Ví dụ |
|---|---|---|---|---|---|---|---|
| 1 | id | char(36) | Không | - | PK | Khóa chính định danh duy nhất của bản ghi. | 10000000-0000-0000-0000-000000000001 |
| 2 | booking_id | char(36) | Không | - | FK | Đơn đặt sân cha. | 10000000-0000-0000-0000-000000000001 |
| 3 | venue_court_id | char(36) | Không | - | FK | Sân con thực tế được gán. | 10000000-0000-0000-0000-000000000001 |
| 4 | requested_venue_court_id | char(36) | Có | - | FK | Sân con khách yêu cầu ban đầu. | 10000000-0000-0000-0000-000000000001 |
| 5 | start_time | time | Không | - | - | Giờ bắt đầu của item. | 08:00:00 |
| 6 | end_time | time | Không | - | - | Giờ kết thúc của item. | 08:00:00 |
| 7 | duration_minutes | unsignedInteger | Không | - | - | Thời lượng tính bằng phút. | 1 |
| 8 | unit_price | decimal(12,2) | Không | 0 | - | Đơn giá trung bình/giờ tại thời điểm đặt. | 100000 |
| 9 | subtotal | decimal(12,2) | Không | 0 | - | Thành tiền = (duration/60) * unit_price. | 1 |
| 10 | court_changed_by | char(36) | Có | - | FK | Người đổi sân. | 10000000-0000-0000-0000-000000000001 |
| 11 | court_changed_at | timestamp | Có | - | - | Thời điểm đổi sân. | 2026-06-22 09:00:00 |
| 12 | court_changed_reason | text | Có | - | - | Lý do đổi sân. | Nội dung mẫu |
| 13 | sort_order | unsignedInteger | Không | 0 | - | Thứ tự hiển thị. | 1 |
| 14 | created_at | timestamp | Có | - | - | Thời điểm tạo bản ghi. | 2026-06-22 09:00:00 |
| 15 | updated_at | timestamp | Có | - | - | Thời điểm cập nhật bản ghi gần nhất. | 2026-06-22 09:00:00 |

### 3. Khóa chính, khóa ngoại, index
- PK: id
- FK: booking_id -> bookings.id (on delete: cascade)
- FK: venue_court_id -> venue_courts.id (on delete: restrict)
- FK: requested_venue_court_id -> venue_courts.id (on delete: set null)
- FK: court_changed_by -> users.id (on delete: set null)
- INDEX: booking_items_booking_sort_index (booking_id, sort_order)
- INDEX: booking_items_court_time_index (venue_court_id, start_time, end_time)

### 4. Quan hệ với bảng khác
- booking_items n-1 bookings qua booking_id.
- booking_items n-1 venue_courts qua venue_court_id.
- booking_items n-1 venue_courts qua requested_venue_court_id.
- booking_items n-1 users qua court_changed_by.

### 5. Ví dụ bản ghi
```json
{
    "id": "10000000-0000-0000-0000-000000000001",
    "booking_id": "10000000-0000-0000-0000-000000000001",
    "venue_court_id": "10000000-0000-0000-0000-000000000001",
    "requested_venue_court_id": "10000000-0000-0000-0000-000000000001",
    "start_time": "08:00:00",
    "end_time": "08:00:00",
    "duration_minutes": 1,
    "unit_price": 100000
}
```

---

### MODULE: PAYMENT

## Tên bảng: owner_bank_accounts

### 1. Mục đích bảng
Lưu thông tin tài khoản ngân hàng của chủ sân dùng để nhận tiền rút và đối soát.

### 2. Danh sách trường

| STT | Tên trường | Kiểu dữ liệu | Null | Default | Khóa/Ràng buộc | Mô tả tác dụng trường | Ví dụ |
|---|---|---|---|---|---|---|---|
| 1 | id | char(36) | Không | - | PK | Khóa chính định danh duy nhất của bản ghi. | 10000000-0000-0000-0000-000000000001 |
| 2 | owner_id | char(36) | Không | - | FK | User chủ sân sở hữu tài khoản nhận tiền. | 10000000-0000-0000-0000-000000000001 |
| 3 | partner_application_id | char(36) | Có | - | FK, INDEX | Hồ sơ đăng ký chủ sân đã cung cấp tài khoản này. | 10000000-0000-0000-0000-000000000001 |
| 4 | bank_name | string(100) | Không | - | - | Tên ngân hàng. | Ví dụ SportGo |
| 5 | bank_code | string(50) | Không | - | - | Mã ngân hàng dùng cho đối soát/chuyển khoản. | CODE-001 |
| 6 | account_number | string(50) | Không | - | - | Số tài khoản nhận tiền. | 1 |
| 7 | account_holder_name | string(150) | Không | - | - | Tên chủ tài khoản. | 1 |
| 8 | branch_name | string(150) | Có | - | - | Chi nhánh ngân hàng nếu có. | Ví dụ SportGo |
| 9 | status | enum(pending, active, rejected, inactive) | Không | pending | - | Trạng thái xác minh tài khoản. | pending |
| 10 | is_default | boolean | Không | false | - | Tài khoản nhận tiền mặc định của owner. | true |
| 11 | verified_by | char(36) | Có | - | FK | Admin xác minh tài khoản. | 10000000-0000-0000-0000-000000000001 |
| 12 | verified_at | timestamp | Có | - | - | Thời điểm xác minh. | 2026-06-22 09:00:00 |
| 13 | rejected_reason | text | Có | - | - | Lý do từ chối tài khoản nhận tiền. | Nội dung mẫu |
| 14 | created_at | timestamp | Có | - | - | Thời điểm tạo bản ghi. | 2026-06-22 09:00:00 |
| 15 | updated_at | timestamp | Có | - | - | Thời điểm cập nhật bản ghi gần nhất. | 2026-06-22 09:00:00 |

### 3. Khóa chính, khóa ngoại, index
- PK: id
- FK: owner_id -> users.id (on delete: restrict)
- FK: partner_application_id -> partner_applications.id (on delete: set null)
- FK: verified_by -> users.id (on delete: set null)
- UNIQUE: owner_bank_accounts_owner_bank_unique (owner_id, bank_code, account_number)
- INDEX: owner_bank_accounts_owner_status_index (owner_id, status)
- INDEX: owner_bank_accounts_status_default_index (status, is_default)
- INDEX: owner_bank_accounts_partner_application_index (partner_application_id)

### 4. Quan hệ với bảng khác
- owner_bank_accounts n-1 users qua owner_id.
- owner_bank_accounts n-1 partner_applications qua partner_application_id.
- owner_bank_accounts n-1 users qua verified_by.

### 5. Ví dụ bản ghi
```json
{
    "id": "10000000-0000-0000-0000-000000000001",
    "owner_id": "10000000-0000-0000-0000-000000000001",
    "partner_application_id": "10000000-0000-0000-0000-000000000001",
    "bank_name": "Ví dụ SportGo",
    "bank_code": "CODE-001",
    "account_number": 1,
    "account_holder_name": 1,
    "branch_name": "Ví dụ SportGo"
}
```

---

## Tên bảng: owner_withdrawal_requests

### 1. Mục đích bảng
Quản lý yêu cầu rút tiền từ ví chủ sân.

### 2. Danh sách trường

| STT | Tên trường | Kiểu dữ liệu | Null | Default | Khóa/Ràng buộc | Mô tả tác dụng trường | Ví dụ |
|---|---|---|---|---|---|---|---|
| 1 | id | char(36) | Không | - | PK | Khóa chính định danh duy nhất của bản ghi. | 10000000-0000-0000-0000-000000000001 |
| 2 | request_code | string(30) | Không | - | UNIQUE | Mã yêu cầu rút tiền để admin/owner tra cứu. | CODE-001 |
| 3 | source | enum(manual, partner_termination_settlement) | Có | - | INDEX | Trường dữ liệu phục vụ nghiệp vụ của bảng `owner_withdrawal_requests`. | example |
| 4 | partner_settlement_id | char(36) | Có | - | FK, INDEX | Khóa ngoại tham chiếu bảng `partner_settlements`, dùng để liên kết bản ghi với dữ liệu liên quan. | 10000000-0000-0000-0000-000000000001 |
| 5 | partner_termination_request_id | char(36) | Có | - | FK, INDEX | Khóa ngoại tham chiếu bảng `partner_termination_requests`, dùng để liên kết bản ghi với dữ liệu liên quan. | 10000000-0000-0000-0000-000000000001 |
| 6 | auto_created | boolean | Không | false | - | Cờ bật/tắt cho hành vi hoặc trạng thái tương ứng. | true |
| 7 | owner_id | char(36) | Không | - | FK | Chủ sân yêu cầu rút tiền. | 10000000-0000-0000-0000-000000000001 |
| 8 | owner_wallet_id | char(36) | Không | - | FK, INDEX | Ví owner bị trừ/giữ tiền. | 10000000-0000-0000-0000-000000000001 |
| 9 | owner_bank_account_id | char(36) | Không | - | FK, INDEX | Tài khoản nhận tiền owner chọn. | 10000000-0000-0000-0000-000000000001 |
| 10 | amount | decimal(14,2) | Không | - | - | Số tiền owner yêu cầu rút. | 100000 |
| 11 | status | enum(pending, reviewing, approved, rejected, completed, cancelled) | Không | pending | - | Trạng thái xử lý rút tiền. | pending |
| 12 | owner_note | text | Có | - | - | Ghi chú của owner khi gửi yêu cầu. | Nội dung mẫu |
| 13 | reviewed_by | char(36) | Có | - | FK | Admin duyệt/từ chối yêu cầu. | 10000000-0000-0000-0000-000000000001 |
| 14 | reviewed_at | timestamp | Có | - | - | Thời điểm duyệt/từ chối. | 2026-06-22 09:00:00 |
| 15 | review_note | text | Có | - | - | Ghi chú nội bộ khi duyệt. | Nội dung mẫu |
| 16 | status_reason | text | Có | - | - | Lý do từ chối/hủy/thất bại. | active |
| 17 | completed_by | char(36) | Có | - | FK | Admin xác nhận đã chuyển tiền. | 10000000-0000-0000-0000-000000000001 |
| 18 | completed_at | timestamp | Có | - | - | Thời điểm hoàn tất chuyển tiền. | 2026-06-22 09:00:00 |
| 19 | transfer_reference | string(100) | Có | - | - | Mã giao dịch chuyển khoản thực tế. | example |
| 20 | payout_transfer_code | string(40) | Có | - | UNIQUE | Mã nội dung chuyển khoản admin dùng khi chi trả rút tiền bằng QR. | CODE-001 |
| 21 | payout_qr_created_at | timestamp | Có | - | - | Thời điểm tạo QR chuyển tiền rút tiền gần nhất. | 2026-06-22 09:00:00 |
| 22 | metadata | json | Có | - | - | Dữ liệu phụ cho đối soát. | {"key":"value"} |
| 23 | requested_at | timestamp | Không | CURRENT_TIMESTAMP | - | Thời điểm owner gửi yêu cầu. | 2026-06-22 09:00:00 |
| 24 | created_at | timestamp | Có | - | - | Thời điểm tạo bản ghi. | 2026-06-22 09:00:00 |
| 25 | updated_at | timestamp | Có | - | - | Thời điểm cập nhật bản ghi gần nhất. | 2026-06-22 09:00:00 |

### 3. Khóa chính, khóa ngoại, index
- PK: id
- FK: owner_id -> users.id (on delete: restrict)
- FK: owner_wallet_id -> owner_wallets.id (on delete: restrict)
- FK: owner_bank_account_id -> owner_bank_accounts.id (on delete: restrict)
- FK: reviewed_by -> users.id (on delete: set null)
- FK: completed_by -> users.id (on delete: set null)
- FK: partner_settlement_id -> partner_settlements.id (on delete: restrict)
- FK: partner_termination_request_id -> partner_termination_requests.id (on delete: restrict)
- UNIQUE: owner_withdrawal_requests_request_code_unique (request_code)
- INDEX: owner_withdrawal_requests_owner_status_index (owner_id, status)
- INDEX: owner_withdrawal_requests_status_requested_index (status, requested_at)
- INDEX: owner_withdrawal_requests_wallet_index (owner_wallet_id)
- INDEX: owner_withdrawal_requests_bank_index (owner_bank_account_id)
- UNIQUE: owner_withdrawals_payout_transfer_code_unique (payout_transfer_code)
- INDEX: owner_withdrawal_requests_source_index (source)
- INDEX: owner_withdrawal_requests_settlement_index (partner_settlement_id)
- INDEX: owner_withdrawal_requests_termination_index (partner_termination_request_id)

### 4. Quan hệ với bảng khác
- owner_withdrawal_requests n-1 users qua owner_id.
- owner_withdrawal_requests n-1 owner_wallets qua owner_wallet_id.
- owner_withdrawal_requests n-1 owner_bank_accounts qua owner_bank_account_id.
- owner_withdrawal_requests n-1 users qua reviewed_by.
- owner_withdrawal_requests n-1 users qua completed_by.
- owner_withdrawal_requests n-1 partner_settlements qua partner_settlement_id.
- owner_withdrawal_requests n-1 partner_termination_requests qua partner_termination_request_id.

### 5. Ví dụ bản ghi
```json
{
    "id": "10000000-0000-0000-0000-000000000001",
    "request_code": "CODE-001",
    "source": "example",
    "partner_settlement_id": "10000000-0000-0000-0000-000000000001",
    "partner_termination_request_id": "10000000-0000-0000-0000-000000000001",
    "auto_created": true,
    "owner_id": "10000000-0000-0000-0000-000000000001",
    "owner_wallet_id": "10000000-0000-0000-0000-000000000001"
}
```

---

## Tên bảng: internal_receipts

### 1. Mục đích bảng
Lưu phiếu thu/chi nội bộ cho các nghiệp vụ tài chính (phí nền tảng, rút tiền, hoàn tiền, thanh toán).

### 2. Danh sách trường

| STT | Tên trường | Kiểu dữ liệu | Null | Default | Khóa/Ràng buộc | Mô tả tác dụng trường | Ví dụ |
|---|---|---|---|---|---|---|---|
| 1 | id | char(36) | Không | - | PK | Khóa chính định danh duy nhất của bản ghi. | 10000000-0000-0000-0000-000000000001 |
| 2 | receipt_code | string(40) | Không | - | UNIQUE | Mã phiếu/hóa đơn nội bộ. | CODE-001 |
| 3 | receipt_type | enum(platform_fee, withdrawal, refund, payment) | Không | - | - | Nghiệp vụ phát sinh phiếu. | platform_fee |
| 4 | receiptable_type | string(100) | Không | - | - | Loại đối tượng phát sinh phiếu. | default |
| 5 | receiptable_id | string(100) | Không | - | - | ID đối tượng phát sinh phiếu. | 1 |
| 6 | issued_to_user_id | char(36) | Có | - | FK | User nhận phiếu, nếu có. | 10000000-0000-0000-0000-000000000001 |
| 7 | issued_by | char(36) | Có | - | FK | Admin/người tạo phiếu. | 10000000-0000-0000-0000-000000000001 |
| 8 | title | string(255) | Không | - | - | Tiêu đề phiếu. | Ví dụ SportGo |
| 9 | amount | decimal(14,2) | Không | 0 | - | Số tiền ghi nhận trên phiếu. | 100000 |
| 10 | currency | string(10) | Không | VND | - | Đơn vị tiền tệ. | example |
| 11 | status | enum(draft, issued, cancelled) | Không | issued | - | Trạng thái phiếu. | draft |
| 12 | issued_at | timestamp | Có | - | INDEX | Thời điểm phát hành. | 2026-06-22 09:00:00 |
| 13 | cancelled_at | timestamp | Có | - | - | Thời điểm hủy phiếu. | 2026-06-22 09:00:00 |
| 14 | cancel_reason | text | Có | - | - | Lý do hủy phiếu. | Nội dung mẫu |
| 15 | file_path | string(500) | Có | - | - | Đường dẫn file PDF/HTML nếu có xuất file. | /storage/example.pdf |
| 16 | metadata | json | Có | - | - | Dữ liệu phụ để render phiếu. | {"key":"value"} |
| 17 | created_at | timestamp | Có | - | - | Thời điểm tạo bản ghi. | 2026-06-22 09:00:00 |
| 18 | updated_at | timestamp | Có | - | - | Thời điểm cập nhật bản ghi gần nhất. | 2026-06-22 09:00:00 |

### 3. Khóa chính, khóa ngoại, index
- PK: id
- FK: issued_to_user_id -> users.id (on delete: set null)
- FK: issued_by -> users.id (on delete: set null)
- UNIQUE: internal_receipts_receipt_code_unique (receipt_code)
- INDEX: internal_receipts_receiptable_index (receiptable_type, receiptable_id)
- INDEX: internal_receipts_type_status_index (receipt_type, status)
- INDEX: internal_receipts_issued_at_index (issued_at)

### 4. Quan hệ với bảng khác
- internal_receipts n-1 users qua issued_to_user_id.
- internal_receipts n-1 users qua issued_by.

### 5. Ví dụ bản ghi
```json
{
    "id": "10000000-0000-0000-0000-000000000001",
    "receipt_code": "CODE-001",
    "receipt_type": "platform_fee",
    "receiptable_type": "default",
    "receiptable_id": 1,
    "issued_to_user_id": "10000000-0000-0000-0000-000000000001",
    "issued_by": "10000000-0000-0000-0000-000000000001",
    "title": "Ví dụ SportGo"
}
```

---

### MODULE: POLICY

## Tên bảng: policy_action_bindings

### 1. Mục đích bảng
Map chính sách hệ thống với module/action nghiệp vụ (VD: `booking.cancel`, `refund.request`).

### 2. Danh sách trường

| STT | Tên trường | Kiểu dữ liệu | Null | Default | Khóa/Ràng buộc | Mô tả tác dụng trường | Ví dụ |
|---|---|---|---|---|---|---|---|
| 1 | id | char(36) | Không | - | PK | Khóa chính định danh duy nhất của bản ghi. | 10000000-0000-0000-0000-000000000001 |
| 2 | system_policy_id | char(36) | Không | - | FK | Chính sách hệ thống được bind với action. | 10000000-0000-0000-0000-000000000001 |
| 3 | module | string(50) | Không | - | - | Module nghiệp vụ như booking, refund, report. | example |
| 4 | action_code | string(100) | Không | - | - | Mã action như booking.cancel, refund.request. | CODE-001 |
| 5 | description | text | Có | - | - | Mô tả ngắn action/policy binding. | Nội dung mẫu |
| 6 | is_active | boolean | Không | true | INDEX | Binding đang có hiệu lực. | true |
| 7 | created_at | timestamp | Có | - | - | Thời điểm tạo bản ghi. | 2026-06-22 09:00:00 |
| 8 | updated_at | timestamp | Có | - | - | Thời điểm cập nhật bản ghi gần nhất. | 2026-06-22 09:00:00 |

### 3. Khóa chính, khóa ngoại, index
- PK: id
- FK: system_policy_id -> system_policies.id (on delete: cascade)
- UNIQUE: policy_action_bindings_policy_action_unique (system_policy_id, action_code)
- INDEX: policy_action_bindings_module_action_index (module, action_code)
- INDEX: policy_action_bindings_is_active_index (is_active)

### 4. Quan hệ với bảng khác
- policy_action_bindings n-1 system_policies qua system_policy_id.

### 5. Ví dụ bản ghi
```json
{
    "id": "10000000-0000-0000-0000-000000000001",
    "system_policy_id": "10000000-0000-0000-0000-000000000001",
    "module": "example",
    "action_code": "CODE-001",
    "description": "Nội dung mẫu",
    "is_active": true,
    "created_at": "2026-06-22 09:00:00",
    "updated_at": "2026-06-22 09:00:00"
}
```

---

## Tên bảng: policy_rules

### 1. Mục đích bảng
Lưu rule hệ thống có cấu trúc JSON để backend evaluate theo từng action.

### 2. Danh sách trường

| STT | Tên trường | Kiểu dữ liệu | Null | Default | Khóa/Ràng buộc | Mô tả tác dụng trường | Ví dụ |
|---|---|---|---|---|---|---|---|
| 1 | id | char(36) | Không | - | PK | Khóa chính định danh duy nhất của bản ghi. | 10000000-0000-0000-0000-000000000001 |
| 2 | system_policy_id | char(36) | Không | - | FK | Chính sách hệ thống sở hữu rule. | 10000000-0000-0000-0000-000000000001 |
| 3 | action_code | string(100) | Không | - | - | Action mà rule áp dụng. | CODE-001 |
| 4 | rule_code | string(100) | Không | - | - | Mã rule duy nhất trong cùng policy. | CODE-001 |
| 5 | rule_name | string(255) | Không | - | - | Tên rule dễ đọc. | Ví dụ SportGo |
| 6 | rule_type | string(50) | Không | - | - | Loại evaluator xử lý rule. | default |
| 7 | decision_key | string(100) | Có | - | - | Trường dữ liệu phục vụ nghiệp vụ của bảng `policy_rules`. | example |
| 8 | conflict_group | string(100) | Có | - | - | Trường dữ liệu phục vụ nghiệp vụ của bảng `policy_rules`. | example |
| 9 | condition_json | json | Có | - | - | Điều kiện có cấu trúc để backend evaluate. | {"key":"value"} |
| 10 | result_json | json | Có | - | - | Kết quả/gợi ý xử lý khi rule match. | {"key":"value"} |
| 11 | constraint_json | json | Có | - | - | Dữ liệu có cấu trúc hoặc nội dung dài phục vụ xử lý nghiệp vụ. | {"key":"value"} |
| 12 | allowed_override_json | json | Có | - | - | Dữ liệu có cấu trúc hoặc nội dung dài phục vụ xử lý nghiệp vụ. | {"key":"value"} |
| 13 | priority | integer | Không | 0 | - | Độ ưu tiên rule. | 1 |
| 14 | is_active | boolean | Không | true | - | Rule đang có hiệu lực. | true |
| 15 | created_by | char(36) | Có | - | FK | Khóa ngoại tham chiếu bảng `users`, dùng để liên kết bản ghi với dữ liệu liên quan. | 10000000-0000-0000-0000-000000000001 |
| 16 | updated_by | char(36) | Có | - | FK | Khóa ngoại tham chiếu bảng `users`, dùng để liên kết bản ghi với dữ liệu liên quan. | 10000000-0000-0000-0000-000000000001 |
| 17 | created_at | timestamp | Có | - | - | Thời điểm tạo bản ghi. | 2026-06-22 09:00:00 |
| 18 | updated_at | timestamp | Có | - | - | Thời điểm cập nhật bản ghi gần nhất. | 2026-06-22 09:00:00 |

### 3. Khóa chính, khóa ngoại, index
- PK: id
- FK: system_policy_id -> system_policies.id (on delete: cascade)
- FK: created_by -> users.id (on delete: set null)
- FK: updated_by -> users.id (on delete: set null)
- UNIQUE: policy_rules_policy_rule_code_unique (system_policy_id, rule_code)
- INDEX: policy_rules_action_active_index (action_code, is_active)
- INDEX: policy_rules_type_priority_index (rule_type, priority)
- INDEX: policy_rules_action_type_active_priority_index (action_code, rule_type, is_active, priority)
- INDEX: policy_rules_conflict_lookup_index (action_code, decision_key, conflict_group)

### 4. Quan hệ với bảng khác
- policy_rules n-1 system_policies qua system_policy_id.
- policy_rules n-1 users qua created_by.
- policy_rules n-1 users qua updated_by.

### 5. Ví dụ bản ghi
```json
{
    "id": "10000000-0000-0000-0000-000000000001",
    "system_policy_id": "10000000-0000-0000-0000-000000000001",
    "action_code": "CODE-001",
    "rule_code": "CODE-001",
    "rule_name": "Ví dụ SportGo",
    "rule_type": "default",
    "decision_key": "example",
    "conflict_group": "example"
}
```

---

## Tên bảng: venue_policy_rules

### 1. Mục đích bảng
Lưu rule riêng của sân, chỉ dùng khi chính sách hệ thống cho phép override (`is_overridable = true`).

### 2. Danh sách trường

| STT | Tên trường | Kiểu dữ liệu | Null | Default | Khóa/Ràng buộc | Mô tả tác dụng trường | Ví dụ |
|---|---|---|---|---|---|---|---|
| 1 | id | char(36) | Không | - | PK | Khóa chính định danh duy nhất của bản ghi. | 10000000-0000-0000-0000-000000000001 |
| 2 | venue_cluster_id | char(36) | Không | - | FK | Cụm sân cấu hình rule riêng. | 10000000-0000-0000-0000-000000000001 |
| 3 | base_policy_rule_id | char(36) | Có | - | FK, INDEX | Rule hệ thống được override nếu được phép. | 10000000-0000-0000-0000-000000000001 |
| 4 | action_code | string(100) | Không | - | - | Action mà rule sân áp dụng. | CODE-001 |
| 5 | rule_code | string(100) | Không | - | - | Mã rule sân. | CODE-001 |
| 6 | rule_name | string(255) | Không | - | - | Tên rule sân. | Ví dụ SportGo |
| 7 | rule_type | string(50) | Không | - | - | Loại evaluator xử lý rule. | default |
| 8 | condition_json | json | Có | - | - | Điều kiện do owner cấu hình qua form. | {"key":"value"} |
| 9 | result_json | json | Có | - | - | Kết quả/gợi ý xử lý khi rule sân match. | {"key":"value"} |
| 10 | status | enum(draft, pending_review, active, inactive, rejected, archived) | Không | draft | - | Trạng thái duyệt rule sân. | draft |
| 11 | approved_by | char(36) | Có | - | FK | Admin duyệt rule sân. | 10000000-0000-0000-0000-000000000001 |
| 12 | approved_at | timestamp | Có | - | - | Thời điểm duyệt rule sân. | 2026-06-22 09:00:00 |
| 13 | rejected_reason | text | Có | - | - | Lý do từ chối rule sân. | Nội dung mẫu |
| 14 | created_by | char(36) | Có | - | FK | Owner/nhân viên tạo rule sân. | 10000000-0000-0000-0000-000000000001 |
| 15 | submitted_by | char(36) | Có | - | - | Người dùng/tác nhân thực hiện thao tác tương ứng. | 10000000-0000-0000-0000-000000000001 |
| 16 | submitted_at | timestamp | Có | - | - | Thời điểm xảy ra sự kiện `submitted_at`. | 2026-06-22 09:00:00 |
| 17 | reviewed_by | char(36) | Có | - | - | Người dùng/tác nhân thực hiện thao tác tương ứng. | 10000000-0000-0000-0000-000000000001 |
| 18 | reviewed_at | timestamp | Có | - | - | Thời điểm xảy ra sự kiện `reviewed_at`. | 2026-06-22 09:00:00 |
| 19 | reject_reason | text | Có | - | - | Lý do/ghi chú giải thích cho trạng thái hoặc quyết định xử lý. | Nội dung mẫu |
| 20 | effective_from | timestamp | Có | - | - | Trường dữ liệu phục vụ nghiệp vụ của bảng `venue_policy_rules`. | 2026-06-22 09:00:00 |
| 21 | effective_to | timestamp | Có | - | - | Trường dữ liệu phục vụ nghiệp vụ của bảng `venue_policy_rules`. | 2026-06-22 09:00:00 |
| 22 | constraint_check_result | json | Có | - | - | Dữ liệu có cấu trúc hoặc nội dung dài phục vụ xử lý nghiệp vụ. | {"key":"value"} |
| 23 | updated_by | char(36) | Có | - | FK | Khóa ngoại tham chiếu bảng `users`, dùng để liên kết bản ghi với dữ liệu liên quan. | 10000000-0000-0000-0000-000000000001 |
| 24 | created_at | timestamp | Có | - | - | Thời điểm tạo bản ghi. | 2026-06-22 09:00:00 |
| 25 | updated_at | timestamp | Có | - | - | Thời điểm cập nhật bản ghi gần nhất. | 2026-06-22 09:00:00 |

### 3. Khóa chính, khóa ngoại, index
- PK: id
- FK: venue_cluster_id -> venue_clusters.id (on delete: cascade)
- FK: base_policy_rule_id -> policy_rules.id (on delete: set null)
- FK: approved_by -> users.id (on delete: set null)
- FK: created_by -> users.id (on delete: set null)
- FK: updated_by -> users.id (on delete: set null)
- INDEX: venue_policy_rules_cluster_status_index (venue_cluster_id, status)
- INDEX: venue_policy_rules_action_status_index (action_code, status)
- INDEX: venue_policy_rules_base_rule_index (base_policy_rule_id)

### 4. Quan hệ với bảng khác
- venue_policy_rules n-1 venue_clusters qua venue_cluster_id.
- venue_policy_rules n-1 policy_rules qua base_policy_rule_id.
- venue_policy_rules n-1 users qua approved_by.
- venue_policy_rules n-1 users qua created_by.
- venue_policy_rules n-1 users qua updated_by.

### 5. Ví dụ bản ghi
```json
{
    "id": "10000000-0000-0000-0000-000000000001",
    "venue_cluster_id": "10000000-0000-0000-0000-000000000001",
    "base_policy_rule_id": "10000000-0000-0000-0000-000000000001",
    "action_code": "CODE-001",
    "rule_code": "CODE-001",
    "rule_name": "Ví dụ SportGo",
    "rule_type": "default",
    "condition_json": {
        "key": "value"
    }
}
```

---

## Tên bảng: policy_evaluation_logs

### 1. Mục đích bảng
Ghi nhận mỗi lần hệ thống evaluate rule, lưu input, output, actor, entity liên quan.

### 2. Danh sách trường

| STT | Tên trường | Kiểu dữ liệu | Null | Default | Khóa/Ràng buộc | Mô tả tác dụng trường | Ví dụ |
|---|---|---|---|---|---|---|---|
| 1 | id | char(36) | Không | - | PK | Khóa chính định danh duy nhất của bản ghi. | 10000000-0000-0000-0000-000000000001 |
| 2 | system_policy_id | char(36) | Có | - | FK, INDEX | Chính sách hệ thống đã evaluate. | 10000000-0000-0000-0000-000000000001 |
| 3 | policy_rule_id | char(36) | Có | - | FK, INDEX | Rule hệ thống đã evaluate. | 10000000-0000-0000-0000-000000000001 |
| 4 | venue_policy_rule_id | char(36) | Có | - | FK, INDEX | Rule sân đã evaluate nếu có. | 10000000-0000-0000-0000-000000000001 |
| 5 | action_code | string(100) | Không | - | - | Action được evaluate. | CODE-001 |
| 6 | entity_type | string(100) | Không | - | - | Loại đối tượng nghiệp vụ được evaluate. | default |
| 7 | entity_id | string(100) | Không | - | - | ID đối tượng nghiệp vụ được evaluate. | 1 |
| 8 | input_data | json | Có | - | - | Dữ liệu đầu vào của lần evaluate. | {"key":"value"} |
| 9 | result_data | json | Có | - | - | Kết quả evaluate. | {"key":"value"} |
| 10 | policy_version_snapshot | json | Có | - | - | Dữ liệu có cấu trúc hoặc nội dung dài phục vụ xử lý nghiệp vụ. | {"key":"value"} |
| 11 | rule_snapshot | json | Có | - | - | Dữ liệu có cấu trúc hoặc nội dung dài phục vụ xử lý nghiệp vụ. | {"key":"value"} |
| 12 | evaluated_by_type | enum(user, owner, venue_staff, admin, super_admin, system) | Không | system | - | Loại actor kích hoạt evaluate. | user |
| 13 | evaluated_by_id | char(36) | Có | - | FK | User kích hoạt evaluate, nullable nếu system. | 10000000-0000-0000-0000-000000000001 |
| 14 | created_at | timestamp | Có | - | - | Thời điểm tạo bản ghi. | 2026-06-22 09:00:00 |

### 3. Khóa chính, khóa ngoại, index
- PK: id
- FK: system_policy_id -> system_policies.id (on delete: set null)
- FK: policy_rule_id -> policy_rules.id (on delete: set null)
- FK: venue_policy_rule_id -> venue_policy_rules.id (on delete: set null)
- FK: evaluated_by_id -> users.id (on delete: set null)
- INDEX: policy_eval_logs_action_created_index (action_code, created_at)
- INDEX: policy_eval_logs_entity_index (entity_type, entity_id)
- INDEX: policy_eval_logs_policy_index (system_policy_id)
- INDEX: policy_eval_logs_rule_index (policy_rule_id)
- INDEX: policy_eval_logs_venue_rule_index (venue_policy_rule_id)
- INDEX: policy_eval_logs_actor_type_created_index (evaluated_by_type, created_at)
- INDEX: policy_eval_action_entity_created_index (action_code, entity_type, entity_id, created_at)

### 4. Quan hệ với bảng khác
- policy_evaluation_logs n-1 system_policies qua system_policy_id.
- policy_evaluation_logs n-1 policy_rules qua policy_rule_id.
- policy_evaluation_logs n-1 venue_policy_rules qua venue_policy_rule_id.
- policy_evaluation_logs n-1 users qua evaluated_by_id.

### 5. Ví dụ bản ghi
```json
{
    "id": "10000000-0000-0000-0000-000000000001",
    "system_policy_id": "10000000-0000-0000-0000-000000000001",
    "policy_rule_id": "10000000-0000-0000-0000-000000000001",
    "venue_policy_rule_id": "10000000-0000-0000-0000-000000000001",
    "action_code": "CODE-001",
    "entity_type": "default",
    "entity_id": 1,
    "input_data": {
        "key": "value"
    }
}
```

---

### MODULE: AI

## Tên bảng: ai_conversations

### 1. Mục đích bảng
Lưu cuộc trò chuyện AI của user.

### 2. Danh sách trường

| STT | Tên trường | Kiểu dữ liệu | Null | Default | Khóa/Ràng buộc | Mô tả tác dụng trường | Ví dụ |
|---|---|---|---|---|---|---|---|
| 1 | id | char(36) | Không | - | PK | Khóa chính định danh duy nhất của bản ghi. | 10000000-0000-0000-0000-000000000001 |
| 2 | user_id | char(36) | Không | - | FK | User sở hữu lịch sử trò chuyện AI. | 10000000-0000-0000-0000-000000000001 |
| 3 | title | string(255) | Có | - | - | Tiêu đề cuộc trò chuyện AI. | Ví dụ SportGo |
| 4 | status | enum(active, archived, deleted) | Không | active | - | Trạng thái hiển thị lịch sử AI. | active |
| 5 | deleted_at | timestamp | Có | - | INDEX | Thời điểm xóa mềm; null nghĩa là bản ghi còn hiệu lực. | 2026-06-22 09:00:00 |
| 6 | created_at | timestamp | Có | - | - | Thời điểm tạo bản ghi. | 2026-06-22 09:00:00 |
| 7 | updated_at | timestamp | Có | - | - | Thời điểm cập nhật bản ghi gần nhất. | 2026-06-22 09:00:00 |

### 3. Khóa chính, khóa ngoại, index
- PK: id
- FK: user_id -> users.id (on delete: cascade)
- INDEX: ai_conversations_user_status_index (user_id, status)
- INDEX: ai_conversations_deleted_at_index (deleted_at)

### 4. Quan hệ với bảng khác
- ai_conversations n-1 users qua user_id.

### 5. Ví dụ bản ghi
```json
{
    "id": "10000000-0000-0000-0000-000000000001",
    "user_id": "10000000-0000-0000-0000-000000000001",
    "title": "Ví dụ SportGo",
    "status": "active",
    "deleted_at": "2026-06-22 09:00:00",
    "created_at": "2026-06-22 09:00:00",
    "updated_at": "2026-06-22 09:00:00"
}
```

---

## Tên bảng: ai_messages

### 1. Mục đích bảng
Lưu message user/assistant/system trong cuộc trò chuyện AI.

### 2. Danh sách trường

| STT | Tên trường | Kiểu dữ liệu | Null | Default | Khóa/Ràng buộc | Mô tả tác dụng trường | Ví dụ |
|---|---|---|---|---|---|---|---|
| 1 | id | char(36) | Không | - | PK | Khóa chính định danh duy nhất của bản ghi. | 10000000-0000-0000-0000-000000000001 |
| 2 | ai_conversation_id | char(36) | Không | - | FK | Cuộc trò chuyện AI chứa message. | 10000000-0000-0000-0000-000000000001 |
| 3 | role | enum(user, assistant, system) | Không | - | INDEX | Vai trò message trong cuộc trò chuyện AI. | example |
| 4 | content | longText | Không | - | - | Nội dung message. | Nội dung mẫu |
| 5 | metadata | json | Có | - | - | Dữ liệu phụ như token, model, context rút gọn. | {"key":"value"} |
| 6 | deleted_at | timestamp | Có | - | INDEX | Thời điểm xóa mềm; null nghĩa là bản ghi còn hiệu lực. | 2026-06-22 09:00:00 |
| 7 | created_at | timestamp | Có | - | - | Thời điểm tạo bản ghi. | 2026-06-22 09:00:00 |
| 8 | updated_at | timestamp | Có | - | - | Thời điểm cập nhật bản ghi gần nhất. | 2026-06-22 09:00:00 |

### 3. Khóa chính, khóa ngoại, index
- PK: id
- FK: ai_conversation_id -> ai_conversations.id (on delete: cascade)
- INDEX: ai_messages_conversation_created_index (ai_conversation_id, created_at)
- INDEX: ai_messages_role_index (role)
- INDEX: ai_messages_deleted_at_index (deleted_at)

### 4. Quan hệ với bảng khác
- ai_messages n-1 ai_conversations qua ai_conversation_id.

### 5. Ví dụ bản ghi
```json
{
    "id": "10000000-0000-0000-0000-000000000001",
    "ai_conversation_id": "10000000-0000-0000-0000-000000000001",
    "role": "example",
    "content": "Nội dung mẫu",
    "metadata": {
        "key": "value"
    },
    "deleted_at": "2026-06-22 09:00:00",
    "created_at": "2026-06-22 09:00:00",
    "updated_at": "2026-06-22 09:00:00"
}
```

---

## Tên bảng: ai_feedbacks

### 1. Mục đích bảng
Lưu feedback của user cho message AI (đánh giá chất lượng câu trả lời).

### 2. Danh sách trường

| STT | Tên trường | Kiểu dữ liệu | Null | Default | Khóa/Ràng buộc | Mô tả tác dụng trường | Ví dụ |
|---|---|---|---|---|---|---|---|
| 1 | id | char(36) | Không | - | PK | Khóa chính định danh duy nhất của bản ghi. | 10000000-0000-0000-0000-000000000001 |
| 2 | ai_message_id | char(36) | Không | - | FK | Message assistant được đánh giá. | 10000000-0000-0000-0000-000000000001 |
| 3 | user_id | char(36) | Không | - | FK | User gửi feedback. | 10000000-0000-0000-0000-000000000001 |
| 4 | rating | tinyInteger | Có | - | INDEX | Điểm đánh giá, ví dụ 1-5. | 1 |
| 5 | comment | text | Có | - | - | Góp ý của user. | example |
| 6 | created_at | timestamp | Có | - | - | Thời điểm tạo bản ghi. | 2026-06-22 09:00:00 |
| 7 | updated_at | timestamp | Có | - | - | Thời điểm cập nhật bản ghi gần nhất. | 2026-06-22 09:00:00 |

### 3. Khóa chính, khóa ngoại, index
- PK: id
- FK: ai_message_id -> ai_messages.id (on delete: cascade)
- FK: user_id -> users.id (on delete: cascade)
- UNIQUE: ai_feedbacks_message_user_unique (ai_message_id, user_id)
- INDEX: ai_feedbacks_rating_index (rating)

### 4. Quan hệ với bảng khác
- ai_feedbacks n-1 ai_messages qua ai_message_id.
- ai_feedbacks n-1 users qua user_id.

### 5. Ví dụ bản ghi
```json
{
    "id": "10000000-0000-0000-0000-000000000001",
    "ai_message_id": "10000000-0000-0000-0000-000000000001",
    "user_id": "10000000-0000-0000-0000-000000000001",
    "rating": 1,
    "comment": "example",
    "created_at": "2026-06-22 09:00:00",
    "updated_at": "2026-06-22 09:00:00"
}
```

---

### MODULE: PAYMENT

## Tên bảng: user_wallets

### 1. Mục đích bảng
Quản lý ví nội bộ của user, dùng để thanh toán booking hoặc nhận tiền hoàn.

### 2. Danh sách trường

| STT | Tên trường | Kiểu dữ liệu | Null | Default | Khóa/Ràng buộc | Mô tả tác dụng trường | Ví dụ |
|---|---|---|---|---|---|---|---|
| 1 | id | char(36) | Không | - | PK | Khóa chính định danh duy nhất của bản ghi. | 10000000-0000-0000-0000-000000000001 |
| 2 | user_id | char(36) | Không | - | FK, UNIQUE | User sở hữu ví. | 10000000-0000-0000-0000-000000000001 |
| 3 | balance | decimal(14,2) | Không | 0 | - | Số dư có thể sử dụng. | 100000 |
| 4 | locked_balance | decimal(14,2) | Không | 0 | - | Số dư đang bị giữ/chờ xử lý. | 100000 |
| 5 | status | enum(active, locked, suspended) | Không | active | INDEX | Trạng thái ví user. | active |
| 6 | created_at | timestamp | Có | - | - | Thời điểm tạo bản ghi. | 2026-06-22 09:00:00 |
| 7 | updated_at | timestamp | Có | - | - | Thời điểm cập nhật bản ghi gần nhất. | 2026-06-22 09:00:00 |

### 3. Khóa chính, khóa ngoại, index
- PK: id
- FK: user_id -> users.id (on delete: restrict)
- UNIQUE: user_wallets_user_id_unique (user_id)
- INDEX: user_wallets_status_index (status)

### 4. Quan hệ với bảng khác
- user_wallets n-1 users qua user_id.

### 5. Ví dụ bản ghi
```json
{
    "id": "10000000-0000-0000-0000-000000000001",
    "user_id": "10000000-0000-0000-0000-000000000001",
    "balance": 100000,
    "locked_balance": 100000,
    "status": "active",
    "created_at": "2026-06-22 09:00:00",
    "updated_at": "2026-06-22 09:00:00"
}
```

---

## Tên bảng: user_wallet_ledgers

### 1. Mục đích bảng
Ghi nhận biến động số dư ví user (nguyên tắc kế toán kép: balance_before, balance_after).

### 2. Danh sách trường

| STT | Tên trường | Kiểu dữ liệu | Null | Default | Khóa/Ràng buộc | Mô tả tác dụng trường | Ví dụ |
|---|---|---|---|---|---|---|---|
| 1 | id | char(36) | Không | - | PK | Khóa chính định danh duy nhất của bản ghi. | 10000000-0000-0000-0000-000000000001 |
| 2 | user_wallet_id | char(36) | Không | - | FK | Ví user được ghi nhận biến động. | 10000000-0000-0000-0000-000000000001 |
| 3 | transaction_code | string(50) | Không | - | UNIQUE | Mã giao dịch ví nội bộ. | CODE-001 |
| 4 | type | enum(deposit, payment, refund, withdrawal, adjustment) | Không | - | - | Loại biến động ví user. | deposit |
| 5 | direction | enum(credit, debit) | Không | - | - | Chiều biến động số dư. | example |
| 6 | amount | decimal(14,2) | Không | - | - | Số tiền biến động. | 100000 |
| 7 | balance_before | decimal(14,2) | Không | - | - | Số dư trước biến động. | 100000 |
| 8 | balance_after | decimal(14,2) | Không | - | - | Số dư sau biến động. | 100000 |
| 9 | reference_type | string(100) | Có | - | - | Loại đối tượng tham chiếu như booking, payment, refund. | default |
| 10 | reference_id | string(100) | Có | - | - | ID đối tượng tham chiếu. | 1 |
| 11 | status | enum(pending, completed, failed, cancelled) | Không | completed | - | Trạng thái giao dịch ví. | pending |
| 12 | note | text | Có | - | - | Ghi chú nghiệp vụ. | Nội dung mẫu |
| 13 | created_by | char(36) | Có | - | FK | User/admin tạo biến động; nullable nếu system. | 10000000-0000-0000-0000-000000000001 |
| 14 | created_at | timestamp | Có | - | - | Thời điểm tạo bản ghi. | 2026-06-22 09:00:00 |
| 15 | updated_at | timestamp | Có | - | - | Thời điểm cập nhật bản ghi gần nhất. | 2026-06-22 09:00:00 |

### 3. Khóa chính, khóa ngoại, index
- PK: id
- FK: user_wallet_id -> user_wallets.id (on delete: restrict)
- FK: created_by -> users.id (on delete: set null)
- UNIQUE: user_wallet_ledgers_transaction_code_unique (transaction_code)
- INDEX: user_wallet_ledgers_wallet_created_index (user_wallet_id, created_at)
- INDEX: user_wallet_ledgers_reference_index (reference_type, reference_id)
- INDEX: user_wallet_ledgers_type_status_index (type, status)

### 4. Quan hệ với bảng khác
- user_wallet_ledgers n-1 user_wallets qua user_wallet_id.
- user_wallet_ledgers n-1 users qua created_by.

### 5. Ví dụ bản ghi
```json
{
    "id": "10000000-0000-0000-0000-000000000001",
    "user_wallet_id": "10000000-0000-0000-0000-000000000001",
    "transaction_code": "CODE-001",
    "type": "deposit",
    "direction": "example",
    "amount": 100000,
    "balance_before": 100000,
    "balance_after": 100000
}
```

---

## Tên bảng: user_payout_accounts

### 1. Mục đích bảng
TKNH user dùng nhận tiền khi rút ví hoặc refund.

### 2. Danh sách trường

| STT | Tên trường | Kiểu dữ liệu | Null | Default | Khóa/Ràng buộc | Mô tả tác dụng trường | Ví dụ |
|---|---|---|---|---|---|---|---|
| 1 | id | char(36) | Không | - | PK | Khóa chính định danh duy nhất của bản ghi. | 10000000-0000-0000-0000-000000000001 |
| 2 | user_id | char(36) | Không | - | FK | User sở hữu tài khoản nhận tiền. | 10000000-0000-0000-0000-000000000001 |
| 3 | bank_name | string(100) | Không | - | - | Tên ngân hàng. | Ví dụ SportGo |
| 4 | bank_account_number | string(50) | Không | - | - | Số tài khoản nhận tiền. | 1 |
| 5 | bank_account_holder | string(150) | Không | - | - | Tên chủ tài khoản. | 1 |
| 6 | bank_branch | string(150) | Có | - | - | Chi nhánh ngân hàng nếu có. | example |
| 7 | is_default | boolean | Không | false | - | Tài khoản mặc định. | true |
| 8 | status | enum(active, inactive) | Không | active | - | Trạng thái tài khoản nhận tiền. | active |
| 9 | created_at | timestamp | Có | - | - | Thời điểm tạo bản ghi. | 2026-06-22 09:00:00 |
| 10 | updated_at | timestamp | Có | - | - | Thời điểm cập nhật bản ghi gần nhất. | 2026-06-22 09:00:00 |

### 3. Khóa chính, khóa ngoại, index
- PK: id
- FK: user_id -> users.id (on delete: restrict)
- UNIQUE: user_payout_accounts_user_account_unique (user_id, bank_account_number)
- INDEX: user_payout_accounts_user_status_index (user_id, status)
- INDEX: user_payout_accounts_status_default_index (status, is_default)

### 4. Quan hệ với bảng khác
- user_payout_accounts n-1 users qua user_id.

### 5. Ví dụ bản ghi
```json
{
    "id": "10000000-0000-0000-0000-000000000001",
    "user_id": "10000000-0000-0000-0000-000000000001",
    "bank_name": "Ví dụ SportGo",
    "bank_account_number": 1,
    "bank_account_holder": 1,
    "bank_branch": "example",
    "is_default": true,
    "status": "active"
}
```

---

## Tên bảng: user_withdrawal_requests

### 1. Mục đích bảng
Quản lý yêu cầu rút tiền từ ví user.

### 2. Danh sách trường

| STT | Tên trường | Kiểu dữ liệu | Null | Default | Khóa/Ràng buộc | Mô tả tác dụng trường | Ví dụ |
|---|---|---|---|---|---|---|---|
| 1 | id | char(36) | Không | - | PK | Khóa chính định danh duy nhất của bản ghi. | 10000000-0000-0000-0000-000000000001 |
| 2 | user_wallet_id | char(36) | Không | - | FK | Ví user bị giữ/trừ tiền. | 10000000-0000-0000-0000-000000000001 |
| 3 | user_id | char(36) | Không | - | FK | User yêu cầu rút tiền. | 10000000-0000-0000-0000-000000000001 |
| 4 | payout_account_id | char(36) | Không | - | FK | Tài khoản nhận tiền user chọn. | 10000000-0000-0000-0000-000000000001 |
| 5 | amount | decimal(14,2) | Không | - | - | Số tiền user yêu cầu rút. | 100000 |
| 6 | status | enum(pending, approved, rejected, paid, cancelled) | Không | pending | - | Trạng thái yêu cầu rút tiền user. | pending |
| 7 | rejected_reason | text | Có | - | - | Lý do từ chối. | Nội dung mẫu |
| 8 | approved_by | char(36) | Có | - | FK | Admin duyệt yêu cầu. | 10000000-0000-0000-0000-000000000001 |
| 9 | paid_by | char(36) | Có | - | FK | Admin xác nhận đã chi trả. | 10000000-0000-0000-0000-000000000001 |
| 10 | requested_at | timestamp | Không | CURRENT_TIMESTAMP | - | Thời điểm user gửi yêu cầu. | 2026-06-22 09:00:00 |
| 11 | approved_at | timestamp | Có | - | - | Thời điểm duyệt. | 2026-06-22 09:00:00 |
| 12 | paid_at | timestamp | Có | - | - | Thời điểm chi trả. | 2026-06-22 09:00:00 |
| 13 | created_at | timestamp | Có | - | - | Thời điểm tạo bản ghi. | 2026-06-22 09:00:00 |
| 14 | updated_at | timestamp | Có | - | - | Thời điểm cập nhật bản ghi gần nhất. | 2026-06-22 09:00:00 |

### 3. Khóa chính, khóa ngoại, index
- PK: id
- FK: user_wallet_id -> user_wallets.id (on delete: restrict)
- FK: user_id -> users.id (on delete: restrict)
- FK: payout_account_id -> user_payout_accounts.id (on delete: restrict)
- FK: approved_by -> users.id (on delete: set null)
- FK: paid_by -> users.id (on delete: set null)
- INDEX: user_withdrawal_requests_user_status_index (user_id, status)
- INDEX: user_withdrawal_requests_status_requested_index (status, requested_at)

### 4. Quan hệ với bảng khác
- user_withdrawal_requests n-1 user_wallets qua user_wallet_id.
- user_withdrawal_requests n-1 users qua user_id.
- user_withdrawal_requests n-1 user_payout_accounts qua payout_account_id.
- user_withdrawal_requests n-1 users qua approved_by.
- user_withdrawal_requests n-1 users qua paid_by.

### 5. Ví dụ bản ghi
```json
{
    "id": "10000000-0000-0000-0000-000000000001",
    "user_wallet_id": "10000000-0000-0000-0000-000000000001",
    "user_id": "10000000-0000-0000-0000-000000000001",
    "payout_account_id": "10000000-0000-0000-0000-000000000001",
    "amount": 100000,
    "status": "pending",
    "rejected_reason": "Nội dung mẫu",
    "approved_by": "10000000-0000-0000-0000-000000000001"
}
```

---

### MODULE: VOUCHER

## Tên bảng: vouchers

### 1. Mục đích bảng
Lưu voucher hệ thống và voucher sân.

### 2. Danh sách trường

| STT | Tên trường | Kiểu dữ liệu | Null | Default | Khóa/Ràng buộc | Mô tả tác dụng trường | Ví dụ |
|---|---|---|---|---|---|---|---|
| 1 | id | char(36) | Không | - | PK | Khóa chính định danh duy nhất của bản ghi. | 10000000-0000-0000-0000-000000000001 |
| 2 | code | string(50) | Không | - | UNIQUE | Mã voucher user nhập. | CODE-001 |
| 3 | name | string(255) | Không | - | - | Tên voucher hiển thị. | Ví dụ SportGo |
| 4 | description | text | Có | - | - | Mô tả voucher. | Nội dung mẫu |
| 5 | owner_type | enum(system, venue) | Không | - | - | Voucher hệ thống hay voucher sân. | system |
| 6 | owner_id | char(36) | Có | - | - | ID owner/cụm sân sở hữu voucher nếu owner_type=venue. | 10000000-0000-0000-0000-000000000001 |
| 7 | funded_by | enum(system, venue) | Không | - | INDEX | Bên chịu tiền giảm. | example |
| 8 | stacking_rule | enum(exclusive, allow_with_system, allow_with_venue) | Không | exclusive | - | Quy tắc dùng chung với voucher khác. | example |
| 9 | discount_type | enum(percent, fixed) | Không | - | - | Kiểu giảm giá. | percent |
| 10 | discount_value | decimal(12,2) | Không | - | - | Giá trị giảm theo percent hoặc fixed. | 1 |
| 11 | max_discount_amount | decimal(12,2) | Có | - | - | Mức giảm tối đa. | 100000 |
| 12 | min_order_amount | decimal(12,2) | Không | 0 | - | Giá trị đơn tối thiểu. | 100000 |
| 13 | total_quantity | unsignedInteger | Có | - | - | Tổng số lượt phát hành/sử dụng. | 1 |
| 14 | used_quantity | unsignedInteger | Không | 0 | - | Số lượt đã dùng. | 1 |
| 15 | per_user_limit | unsignedInteger | Có | - | - | Số lượt tối đa mỗi user. | 1 |
| 16 | valid_from | dateTime | Có | - | - | Thời điểm bắt đầu hiệu lực. | 2026-06-22 09:00:00 |
| 17 | valid_to | dateTime | Có | - | - | Thời điểm hết hiệu lực. | 2026-06-22 09:00:00 |
| 18 | status | enum(draft, active, inactive, expired) | Không | draft | - | Trạng thái voucher. | draft |
| 19 | created_by | char(36) | Có | - | FK | Admin/owner tạo voucher. | 10000000-0000-0000-0000-000000000001 |
| 20 | created_at | timestamp | Có | - | - | Thời điểm tạo bản ghi. | 2026-06-22 09:00:00 |
| 21 | updated_at | timestamp | Có | - | - | Thời điểm cập nhật bản ghi gần nhất. | 2026-06-22 09:00:00 |

### 3. Khóa chính, khóa ngoại, index
- PK: id
- FK: created_by -> users.id (on delete: set null)
- UNIQUE: vouchers_code_unique (code)
- INDEX: vouchers_owner_index (owner_type, owner_id)
- INDEX: vouchers_status_valid_index (status, valid_from, valid_to)
- INDEX: vouchers_funded_by_index (funded_by)

### 4. Quan hệ với bảng khác
- vouchers n-1 users qua created_by.

### 5. Ví dụ bản ghi
```json
{
    "id": "10000000-0000-0000-0000-000000000001",
    "code": "CODE-001",
    "name": "Ví dụ SportGo",
    "description": "Nội dung mẫu",
    "owner_type": "system",
    "owner_id": "10000000-0000-0000-0000-000000000001",
    "funded_by": "example",
    "stacking_rule": "example"
}
```

---

## Tên bảng: voucher_scopes

### 1. Mục đích bảng
Giới hạn phạm vi áp dụng voucher (cụm sân, loại sân, loại booking).

### 2. Danh sách trường

| STT | Tên trường | Kiểu dữ liệu | Null | Default | Khóa/Ràng buộc | Mô tả tác dụng trường | Ví dụ |
|---|---|---|---|---|---|---|---|
| 1 | id | char(36) | Không | - | PK | Khóa chính định danh duy nhất của bản ghi. | 10000000-0000-0000-0000-000000000001 |
| 2 | voucher_id | char(36) | Không | - | FK | Voucher được giới hạn phạm vi. | 10000000-0000-0000-0000-000000000001 |
| 3 | scope_type | enum(all, venue_cluster, court_type, booking_type) | Không | - | - | Loại phạm vi áp dụng. | all |
| 4 | scope_id | string(100) | Có | - | - | ID/mã phạm vi; nullable khi scope_type=all. | 1 |
| 5 | scope_key | string(120) | Không | __all__ | - | Khóa ổn định để unique scope kể cả khi scope_id null. | example |
| 6 | created_at | timestamp | Có | - | - | Thời điểm tạo bản ghi. | 2026-06-22 09:00:00 |
| 7 | updated_at | timestamp | Có | - | - | Thời điểm cập nhật bản ghi gần nhất. | 2026-06-22 09:00:00 |

### 3. Khóa chính, khóa ngoại, index
- PK: id
- FK: voucher_id -> vouchers.id (on delete: cascade)
- INDEX: voucher_scopes_scope_index (scope_type, scope_id)
- UNIQUE: voucher_scopes_voucher_scope_unique (voucher_id, scope_type, scope_key)

### 4. Quan hệ với bảng khác
- voucher_scopes n-1 vouchers qua voucher_id.

### 5. Ví dụ bản ghi
```json
{
    "id": "10000000-0000-0000-0000-000000000001",
    "voucher_id": "10000000-0000-0000-0000-000000000001",
    "scope_type": "all",
    "scope_id": 1,
    "scope_key": "example",
    "created_at": "2026-06-22 09:00:00",
    "updated_at": "2026-06-22 09:00:00"
}
```

---

## Tên bảng: voucher_usages

### 1. Mục đích bảng
Ghi nhận voucher đã áp dụng cho booking/payment nào.

### 2. Danh sách trường

| STT | Tên trường | Kiểu dữ liệu | Null | Default | Khóa/Ràng buộc | Mô tả tác dụng trường | Ví dụ |
|---|---|---|---|---|---|---|---|
| 1 | id | char(36) | Không | - | PK | Khóa chính định danh duy nhất của bản ghi. | 10000000-0000-0000-0000-000000000001 |
| 2 | voucher_id | char(36) | Không | - | FK | Voucher đã dùng. | 10000000-0000-0000-0000-000000000001 |
| 3 | user_id | char(36) | Không | - | FK | User dùng voucher. | 10000000-0000-0000-0000-000000000001 |
| 4 | booking_id | char(36) | Không | - | FK, INDEX | Booking áp dụng voucher. | 10000000-0000-0000-0000-000000000001 |
| 5 | payment_id | char(36) | Có | - | FK | Payment liên quan nếu đã thanh toán. | 10000000-0000-0000-0000-000000000001 |
| 6 | discount_amount | decimal(12,2) | Không | 0 | - | Số tiền giảm thực tế. | 100000 |
| 7 | used_at | timestamp | Có | - | - | Thời điểm áp dụng voucher. | 2026-06-22 09:00:00 |
| 8 | status | enum(applied, cancelled, refunded) | Không | applied | - | Trạng thái usage khi booking hủy/refund. | applied |
| 9 | created_at | timestamp | Có | - | - | Thời điểm tạo bản ghi. | 2026-06-22 09:00:00 |
| 10 | updated_at | timestamp | Có | - | - | Thời điểm cập nhật bản ghi gần nhất. | 2026-06-22 09:00:00 |

### 3. Khóa chính, khóa ngoại, index
- PK: id
- FK: voucher_id -> vouchers.id (on delete: restrict)
- FK: user_id -> users.id (on delete: restrict)
- FK: booking_id -> bookings.id (on delete: restrict)
- FK: payment_id -> payments.id (on delete: set null)
- INDEX: voucher_usages_voucher_status_index (voucher_id, status)
- INDEX: voucher_usages_user_voucher_index (user_id, voucher_id)
- INDEX: voucher_usages_booking_index (booking_id)
- UNIQUE: voucher_usages_voucher_user_booking_unique (voucher_id, user_id, booking_id)

### 4. Quan hệ với bảng khác
- voucher_usages n-1 vouchers qua voucher_id.
- voucher_usages n-1 users qua user_id.
- voucher_usages n-1 bookings qua booking_id.
- voucher_usages n-1 payments qua payment_id.

### 5. Ví dụ bản ghi
```json
{
    "id": "10000000-0000-0000-0000-000000000001",
    "voucher_id": "10000000-0000-0000-0000-000000000001",
    "user_id": "10000000-0000-0000-0000-000000000001",
    "booking_id": "10000000-0000-0000-0000-000000000001",
    "payment_id": "10000000-0000-0000-0000-000000000001",
    "discount_amount": 100000,
    "used_at": "2026-06-22 09:00:00",
    "status": "applied"
}
```

---

### MODULE: SYSTEM

## Tên bảng: backup_jobs

### 1. Mục đích bảng
Lưu metadata và trạng thái các lần backup database.

### 2. Danh sách trường

| STT | Tên trường | Kiểu dữ liệu | Null | Default | Khóa/Ràng buộc | Mô tả tác dụng trường | Ví dụ |
|---|---|---|---|---|---|---|---|
| 1 | id | char(36) | Không | - | PK | Khóa chính định danh duy nhất của bản ghi. | 10000000-0000-0000-0000-000000000001 |
| 2 | backup_code | string(50) | Không | - | UNIQUE | Mã backup để admin tra cứu. | CODE-001 |
| 3 | file_name | string(255) | Có | - | - | Tên file backup đã tạo. | Ví dụ SportGo |
| 4 | file_path | string(1000) | Có | - | - | Đường dẫn file backup ngoài DB. | /storage/example.pdf |
| 5 | disk | string(100) | Có | - | - | Storage disk lưu backup. | example |
| 6 | size_bytes | unsignedBigInteger | Có | - | - | Dung lượng file backup. | 1 |
| 7 | checksum | string(128) | Có | - | - | Checksum kiểm tra file backup. | example |
| 8 | type | enum(manual, auto) | Không | manual | - | Backup thủ công hay tự động. | manual |
| 9 | status | enum(pending, running, completed, failed) | Không | pending | - | Trạng thái job backup. | pending |
| 10 | created_by | char(36) | Có | - | FK | Admin tạo backup thủ công. | 10000000-0000-0000-0000-000000000001 |
| 11 | started_at | timestamp | Có | - | - | Thời điểm bắt đầu backup. | 2026-06-22 09:00:00 |
| 12 | completed_at | timestamp | Có | - | INDEX | Thời điểm hoàn tất backup. | 2026-06-22 09:00:00 |
| 13 | error_message | text | Có | - | - | Lỗi backup nếu thất bại. | example |
| 14 | retention_days | unsignedInteger | Có | - | - | Số ngày giữ file backup. | 1 |
| 15 | created_at | timestamp | Có | - | INDEX | Thời điểm tạo bản ghi. | 2026-06-22 09:00:00 |
| 16 | updated_at | timestamp | Có | - | - | Thời điểm cập nhật bản ghi gần nhất. | 2026-06-22 09:00:00 |

### 3. Khóa chính, khóa ngoại, index
- PK: id
- FK: created_by -> users.id (on delete: set null)
- UNIQUE: backup_jobs_backup_code_unique (backup_code)
- INDEX: backup_jobs_type_status_index (type, status)
- INDEX: backup_jobs_created_at_index (created_at)
- INDEX: backup_jobs_completed_at_index (completed_at)

### 4. Quan hệ với bảng khác
- backup_jobs n-1 users qua created_by.

### 5. Ví dụ bản ghi
```json
{
    "id": "10000000-0000-0000-0000-000000000001",
    "backup_code": "CODE-001",
    "file_name": "Ví dụ SportGo",
    "file_path": "/storage/example.pdf",
    "disk": "example",
    "size_bytes": 1,
    "checksum": "example",
    "type": "manual"
}
```

---

### MODULE: VENUE

## Tên bảng: amenities

### 1. Mục đích bảng
Lưu danh mục tiện ích như bãi xe, wifi, phòng tắm để gắn với cụm sân.

### 2. Danh sách trường

| STT | Tên trường | Kiểu dữ liệu | Null | Default | Khóa/Ràng buộc | Mô tả tác dụng trường | Ví dụ |
|---|---|---|---|---|---|---|---|
| 1 | id | unsignedBigInteger | Không | - | PK | Khóa chính định danh duy nhất của bản ghi. | 1 |
| 2 | name | string(255) | Không | - | - | Tên/tiêu đề hiển thị hoặc dùng trong quản trị. | Ví dụ SportGo |
| 3 | description | text | Có | - | - | Mô tả hoặc ghi chú bổ sung cho bản ghi. | Nội dung mẫu |
| 4 | status | enum(pending_review, active, rejected, inactive, cancelled) | Không | - | - | Trạng thái xử lý hoặc vòng đời của bản ghi. | pending_review |
| 5 | created_by | char(36) | Có | - | FK | Khóa ngoại tham chiếu bảng `users`, dùng để liên kết bản ghi với dữ liệu liên quan. | 10000000-0000-0000-0000-000000000001 |
| 6 | reviewed_by | char(36) | Có | - | FK | Khóa ngoại tham chiếu bảng `users`, dùng để liên kết bản ghi với dữ liệu liên quan. | 10000000-0000-0000-0000-000000000001 |
| 7 | reviewed_at | timestamp | Có | - | - | Thời điểm xảy ra sự kiện `reviewed_at`. | 2026-06-22 09:00:00 |
| 8 | status_reason | text | Có | - | - | Lý do/ghi chú giải thích cho trạng thái hoặc quyết định xử lý. | active |
| 9 | created_at | timestamp | Có | - | - | Thời điểm tạo bản ghi. | 2026-06-22 09:00:00 |
| 10 | updated_at | timestamp | Có | - | - | Thời điểm cập nhật bản ghi gần nhất. | 2026-06-22 09:00:00 |
| 11 | deleted_at | timestamp | Có | - | - | Thời điểm xóa mềm; null nghĩa là bản ghi còn hiệu lực. | 2026-06-22 09:00:00 |
| 12 | active_name | string(255) | Có | - | UNIQUE | Tên/tiêu đề hiển thị hoặc dùng trong quản trị. | Ví dụ SportGo |

### 3. Khóa chính, khóa ngoại, index
- PK: id
- FK: created_by -> users.id (on delete: set null)
- FK: reviewed_by -> users.id (on delete: set null)
- UNIQUE: amenities_active_name_unique (active_name)

### 4. Quan hệ với bảng khác
- amenities n-1 users qua created_by.
- amenities n-1 users qua reviewed_by.

### 5. Ví dụ bản ghi
```json
{
    "id": 1,
    "name": "Ví dụ SportGo",
    "description": "Nội dung mẫu",
    "status": "pending_review",
    "created_by": "10000000-0000-0000-0000-000000000001",
    "reviewed_by": "10000000-0000-0000-0000-000000000001",
    "reviewed_at": "2026-06-22 09:00:00",
    "status_reason": "active"
}
```

---

### MODULE: POLICY

## Tên bảng: policy_rule_templates

### 1. Mục đích bảng
Lưu danh mục mẫu rule để admin cấu hình nhanh khi tạo policy rule mới. Template chứa sẵn action_code, schema JSON cho condition/result, mức rủi ro và khả năng override bởi sân.

### 2. Danh sách trường

| STT | Tên trường | Kiểu dữ liệu | Null | Default | Khóa/Ràng buộc | Mô tả tác dụng trường | Ví dụ |
|---|---|---|---|---|---|---|---|
| 1 | id | unsignedBigInteger | Không | - | PK | Khóa chính định danh duy nhất của bản ghi. | 1 |
| 2 | policy_type | string(50) | Không | - | - | Loại hoặc nhóm phân loại của bản ghi. | default |
| 3 | rule_code | string(100) | Không | - | - | Trường dữ liệu phục vụ nghiệp vụ của bảng `policy_rule_templates`. | CODE-001 |
| 4 | rule_name | string(255) | Không | - | - | Tên/tiêu đề hiển thị hoặc dùng trong quản trị. | Ví dụ SportGo |
| 5 | description | text | Có | - | - | Mô tả hoặc ghi chú bổ sung cho bản ghi. | Nội dung mẫu |
| 6 | action_code | string(100) | Không | - | INDEX | Trường dữ liệu phục vụ nghiệp vụ của bảng `policy_rule_templates`. | CODE-001 |
| 7 | condition_schema | json | Có | - | - | Dữ liệu có cấu trúc hoặc nội dung dài phục vụ xử lý nghiệp vụ. | {"key":"value"} |
| 8 | result_schema | json | Có | - | - | Dữ liệu có cấu trúc hoặc nội dung dài phục vụ xử lý nghiệp vụ. | {"key":"value"} |
| 9 | is_venue_overridable | boolean | Không | false | - | Cờ bật/tắt cho hành vi hoặc trạng thái tương ứng. | true |
| 10 | risk_level | enum(low, medium, high, critical) | Không | medium | - | Trường dữ liệu phục vụ nghiệp vụ của bảng `policy_rule_templates`. | example |
| 11 | is_active | boolean | Không | true | - | Cờ xác định bản ghi đang hoạt động hay bị tắt. | true |
| 12 | created_at | timestamp | Có | - | - | Thời điểm tạo bản ghi. | 2026-06-22 09:00:00 |
| 13 | updated_at | timestamp | Có | - | - | Thời điểm cập nhật bản ghi gần nhất. | 2026-06-22 09:00:00 |

### 3. Khóa chính, khóa ngoại, index
- PK: id
- UNIQUE: policy_rule_templates_type_code_unique (policy_type, rule_code)
- INDEX: policy_rule_templates_type_active_index (policy_type, is_active)
- INDEX: policy_rule_templates_action_index (action_code)

### 4. Quan hệ với bảng khác
- Không có khóa ngoại trực tiếp trong migration.

### 5. Ví dụ bản ghi
```json
{
    "id": 1,
    "policy_type": "default",
    "rule_code": "CODE-001",
    "rule_name": "Ví dụ SportGo",
    "description": "Nội dung mẫu",
    "action_code": "CODE-001",
    "condition_schema": {
        "key": "value"
    },
    "result_schema": {
        "key": "value"
    }
}
```

---

## Tên bảng: policy_override_constraints

### 1. Mục đích bảng
Định nghĩa ràng buộc mà chủ sân phải tuân theo khi override chính sách hệ thống. Ví dụ: mức hoàn tiền tối thiểu 80%, chủ sân không được giảm dưới mức này.

### 2. Danh sách trường

| STT | Tên trường | Kiểu dữ liệu | Null | Default | Khóa/Ràng buộc | Mô tả tác dụng trường | Ví dụ |
|---|---|---|---|---|---|---|---|
| 1 | id | unsignedBigInteger | Không | - | PK | Khóa chính định danh duy nhất của bản ghi. | 1 |
| 2 | system_policy_id | char(36) | Không | - | FK | Khóa ngoại tham chiếu bảng `system_policies`, dùng để liên kết bản ghi với dữ liệu liên quan. | 10000000-0000-0000-0000-000000000001 |
| 3 | policy_rule_id | char(36) | Có | - | FK | Khóa ngoại tham chiếu bảng `policy_rules`, dùng để liên kết bản ghi với dữ liệu liên quan. | 10000000-0000-0000-0000-000000000001 |
| 4 | rule_code | string(100) | Không | - | - | Trường dữ liệu phục vụ nghiệp vụ của bảng `policy_override_constraints`. | CODE-001 |
| 5 | constraint_key | string(100) | Không | - | - | Trường dữ liệu phục vụ nghiệp vụ của bảng `policy_override_constraints`. | example |
| 6 | constraint_name | string(255) | Không | - | - | Tên/tiêu đề hiển thị hoặc dùng trong quản trị. | Ví dụ SportGo |
| 7 | comparison_direction | enum(exact_only, venue_can_be_more_favorable_to_customer, venue_can_be_stricter_for_safety, venue_can_only_choose_within_range) | Không | - | - | Trường dữ liệu phục vụ nghiệp vụ của bảng `policy_override_constraints`. | example |
| 8 | min_value | decimal(12,2) | Có | - | - | Trường dữ liệu phục vụ nghiệp vụ của bảng `policy_override_constraints`. | 1 |
| 9 | max_value | decimal(12,2) | Có | - | - | Trường dữ liệu phục vụ nghiệp vụ của bảng `policy_override_constraints`. | 1 |
| 10 | allowed_values | json | Có | - | - | Dữ liệu có cấu trúc hoặc nội dung dài phục vụ xử lý nghiệp vụ. | {"key":"value"} |
| 11 | message_vi | text | Không | - | - | Trường dữ liệu phục vụ nghiệp vụ của bảng `policy_override_constraints`. | example |
| 12 | is_active | boolean | Không | true | - | Cờ xác định bản ghi đang hoạt động hay bị tắt. | true |
| 13 | created_at | timestamp | Có | - | - | Thời điểm tạo bản ghi. | 2026-06-22 09:00:00 |
| 14 | updated_at | timestamp | Có | - | - | Thời điểm cập nhật bản ghi gần nhất. | 2026-06-22 09:00:00 |

### 3. Khóa chính, khóa ngoại, index
- PK: id
- FK: system_policy_id -> system_policies.id (on delete: restrict)
- FK: policy_rule_id -> policy_rules.id (on delete: set null)
- UNIQUE: policy_override_constraints_policy_key_unique (system_policy_id, constraint_key)
- INDEX: policy_override_constraints_rule_active_index (rule_code, is_active)

### 4. Quan hệ với bảng khác
- policy_override_constraints n-1 system_policies qua system_policy_id.
- policy_override_constraints n-1 policy_rules qua policy_rule_id.

### 5. Ví dụ bản ghi
```json
{
    "id": 1,
    "system_policy_id": "10000000-0000-0000-0000-000000000001",
    "policy_rule_id": "10000000-0000-0000-0000-000000000001",
    "rule_code": "CODE-001",
    "constraint_key": "example",
    "constraint_name": "Ví dụ SportGo",
    "comparison_direction": "example",
    "min_value": 1
}
```

---

## Tên bảng: policy_status_histories

### 1. Mục đích bảng
Ghi nhận lịch sử mỗi lần chính sách hệ thống đổi trạng thái (draft → active, active → inactive, thay đổi version...).

### 2. Danh sách trường

| STT | Tên trường | Kiểu dữ liệu | Null | Default | Khóa/Ràng buộc | Mô tả tác dụng trường | Ví dụ |
|---|---|---|---|---|---|---|---|
| 1 | id | unsignedBigInteger | Không | - | PK | Khóa chính định danh duy nhất của bản ghi. | 1 |
| 2 | system_policy_id | char(36) | Không | - | FK | Khóa ngoại tham chiếu bảng `system_policies`, dùng để liên kết bản ghi với dữ liệu liên quan. | 10000000-0000-0000-0000-000000000001 |
| 3 | old_status | string(50) | Có | - | - | Trạng thái xử lý hoặc vòng đời của bản ghi. | active |
| 4 | new_status | string(50) | Không | - | - | Trạng thái xử lý hoặc vòng đời của bản ghi. | active |
| 5 | changed_by | char(36) | Có | - | FK | Khóa ngoại tham chiếu bảng `users`, dùng để liên kết bản ghi với dữ liệu liên quan. | 10000000-0000-0000-0000-000000000001 |
| 6 | actor_type | string(50) | Không | admin | - | Loại hoặc nhóm phân loại của bản ghi. | default |
| 7 | reason | text | Có | - | - | Lý do/ghi chú giải thích cho trạng thái hoặc quyết định xử lý. | Nội dung mẫu |
| 8 | created_at | timestamp | Không | CURRENT_TIMESTAMP | - | Thời điểm tạo bản ghi. | 2026-06-22 09:00:00 |

### 3. Khóa chính, khóa ngoại, index
- PK: id
- FK: system_policy_id -> system_policies.id (on delete: restrict)
- FK: changed_by -> users.id (on delete: set null)
- INDEX: policy_status_histories_policy_created_index (system_policy_id, created_at)

### 4. Quan hệ với bảng khác
- policy_status_histories n-1 system_policies qua system_policy_id.
- policy_status_histories n-1 users qua changed_by.

### 5. Ví dụ bản ghi
```json
{
    "id": 1,
    "system_policy_id": "10000000-0000-0000-0000-000000000001",
    "old_status": "active",
    "new_status": "active",
    "changed_by": "10000000-0000-0000-0000-000000000001",
    "actor_type": "default",
    "reason": "Nội dung mẫu",
    "created_at": "2026-06-22 09:00:00"
}
```

---

### MODULE: PAYMENT

## Tên bảng: refund_status_histories

### 1. Mục đích bảng
Ghi nhận từng bước xử lý hoàn tiền: khách yêu cầu → owner xác nhận → admin xử lý → gateway hoàn → hoàn tất.

### 2. Danh sách trường

| STT | Tên trường | Kiểu dữ liệu | Null | Default | Khóa/Ràng buộc | Mô tả tác dụng trường | Ví dụ |
|---|---|---|---|---|---|---|---|
| 1 | id | unsignedBigInteger | Không | - | PK | Khóa chính định danh duy nhất của bản ghi. | 1 |
| 2 | refund_id | char(36) | Không | - | FK | Khóa ngoại tham chiếu bảng `refunds`, dùng để liên kết bản ghi với dữ liệu liên quan. | 10000000-0000-0000-0000-000000000001 |
| 3 | old_status | string(50) | Có | - | - | Trạng thái xử lý hoặc vòng đời của bản ghi. | active |
| 4 | new_status | string(50) | Không | - | INDEX | Trạng thái xử lý hoặc vòng đời của bản ghi. | active |
| 5 | changed_by | char(36) | Có | - | FK | Khóa ngoại tham chiếu bảng `users`, dùng để liên kết bản ghi với dữ liệu liên quan. | 10000000-0000-0000-0000-000000000001 |
| 6 | actor_type | string(50) | Không | system | - | Loại hoặc nhóm phân loại của bản ghi. | default |
| 7 | reason | text | Có | - | - | Lý do/ghi chú giải thích cho trạng thái hoặc quyết định xử lý. | Nội dung mẫu |
| 8 | metadata | json | Có | - | - | JSON metadata bổ sung cho nghiệp vụ. | {"key":"value"} |
| 9 | created_at | timestamp | Không | CURRENT_TIMESTAMP | - | Thời điểm tạo bản ghi. | 2026-06-22 09:00:00 |

### 3. Khóa chính, khóa ngoại, index
- PK: id
- FK: refund_id -> refunds.id (on delete: restrict)
- FK: changed_by -> users.id (on delete: set null)
- INDEX: refund_status_histories_refund_created_index (refund_id, created_at)
- INDEX: refund_status_histories_new_status_index (new_status)

### 4. Quan hệ với bảng khác
- refund_status_histories n-1 refunds qua refund_id.
- refund_status_histories n-1 users qua changed_by.

### 5. Ví dụ bản ghi
```json
{
    "id": 1,
    "refund_id": "10000000-0000-0000-0000-000000000001",
    "old_status": "active",
    "new_status": "active",
    "changed_by": "10000000-0000-0000-0000-000000000001",
    "actor_type": "default",
    "reason": "Nội dung mẫu",
    "metadata": {
        "key": "value"
    }
}
```

---

### MODULE: PARTNER

## Tên bảng: partner_application_documents

### 1. Mục đích bảng
Lưu file/tài liệu đính kèm hồ sơ đối tác: ảnh mặt tiền sân, CCCD, giấy đăng ký kinh doanh, hợp đồng thuê mặt bằng, chứng từ ngân hàng. Mỗi file được admin review và đánh dấu verified/rejected.

### 2. Danh sách trường

| STT | Tên trường | Kiểu dữ liệu | Null | Default | Khóa/Ràng buộc | Mô tả tác dụng trường | Ví dụ |
|---|---|---|---|---|---|---|---|
| 1 | id | unsignedBigInteger | Không | - | PK | Khóa chính định danh duy nhất của bản ghi. | 1 |
| 2 | partner_application_id | char(36) | Không | - | FK | Khóa ngoại tham chiếu bảng `partner_applications`, dùng để liên kết bản ghi với dữ liệu liên quan. | 10000000-0000-0000-0000-000000000001 |
| 3 | media_id | char(36) | Có | - | FK | Khóa ngoại tham chiếu bảng `media`, dùng để liên kết bản ghi với dữ liệu liên quan. | 10000000-0000-0000-0000-000000000001 |
| 4 | document_type | string(100) | Không | - | - | Loại hoặc nhóm phân loại của bản ghi. | default |
| 5 | document_group | string(100) | Không | - | - | Trường dữ liệu phục vụ nghiệp vụ của bảng `partner_application_documents`. | example |
| 6 | title | string(255) | Không | - | - | Tên/tiêu đề hiển thị hoặc dùng trong quản trị. | Ví dụ SportGo |
| 7 | description | text | Có | - | - | Mô tả hoặc ghi chú bổ sung cho bản ghi. | Nội dung mẫu |
| 8 | file_path | string(1000) | Có | - | - | Đường dẫn hoặc URL tới tài nguyên liên quan. | /storage/example.pdf |
| 9 | status | enum(uploaded, verified, rejected) | Không | uploaded | - | Trạng thái xử lý hoặc vòng đời của bản ghi. | uploaded |
| 10 | reviewed_by | char(36) | Có | - | FK | Khóa ngoại tham chiếu bảng `users`, dùng để liên kết bản ghi với dữ liệu liên quan. | 10000000-0000-0000-0000-000000000001 |
| 11 | reviewed_at | timestamp | Có | - | - | Thời điểm xảy ra sự kiện `reviewed_at`. | 2026-06-22 09:00:00 |
| 12 | reject_reason | text | Có | - | - | Lý do/ghi chú giải thích cho trạng thái hoặc quyết định xử lý. | Nội dung mẫu |
| 13 | sort_order | unsignedInteger | Không | 0 | - | Thứ tự sắp xếp khi hiển thị. | 1 |
| 14 | created_at | timestamp | Có | - | - | Thời điểm tạo bản ghi. | 2026-06-22 09:00:00 |
| 15 | updated_at | timestamp | Có | - | - | Thời điểm cập nhật bản ghi gần nhất. | 2026-06-22 09:00:00 |

### 3. Khóa chính, khóa ngoại, index
- PK: id
- FK: partner_application_id -> partner_applications.id (on delete: restrict)
- FK: media_id -> media.id (on delete: set null)
- FK: reviewed_by -> users.id (on delete: set null)
- INDEX: partner_app_docs_app_group_index (partner_application_id, document_group)
- INDEX: partner_app_docs_type_status_index (document_type, status)

### 4. Quan hệ với bảng khác
- partner_application_documents n-1 partner_applications qua partner_application_id.
- partner_application_documents n-1 media qua media_id.
- partner_application_documents n-1 users qua reviewed_by.

### 5. Ví dụ bản ghi
```json
{
    "id": 1,
    "partner_application_id": "10000000-0000-0000-0000-000000000001",
    "media_id": "10000000-0000-0000-0000-000000000001",
    "document_type": "default",
    "document_group": "example",
    "title": "Ví dụ SportGo",
    "description": "Nội dung mẫu",
    "file_path": "/storage/example.pdf"
}
```

---

## Tên bảng: partner_application_status_histories

### 1. Mục đích bảng
Ghi nhận lịch sử đổi trạng thái hồ sơ đối tác: submitted → reviewing → approved_pending_contract → contract_pending_owner_signature...

### 2. Danh sách trường

| STT | Tên trường | Kiểu dữ liệu | Null | Default | Khóa/Ràng buộc | Mô tả tác dụng trường | Ví dụ |
|---|---|---|---|---|---|---|---|
| 1 | id | unsignedBigInteger | Không | - | PK | Khóa chính định danh duy nhất của bản ghi. | 1 |
| 2 | partner_application_id | char(36) | Không | - | FK | Khóa ngoại tham chiếu bảng `partner_applications`, dùng để liên kết bản ghi với dữ liệu liên quan. | 10000000-0000-0000-0000-000000000001 |
| 3 | old_status | string(50) | Có | - | - | Trạng thái xử lý hoặc vòng đời của bản ghi. | active |
| 4 | new_status | string(50) | Không | - | - | Trạng thái xử lý hoặc vòng đời của bản ghi. | active |
| 5 | changed_by | char(36) | Có | - | FK | Khóa ngoại tham chiếu bảng `users`, dùng để liên kết bản ghi với dữ liệu liên quan. | 10000000-0000-0000-0000-000000000001 |
| 6 | actor_type | string(50) | Không | admin | - | Loại hoặc nhóm phân loại của bản ghi. | default |
| 7 | reason | text | Có | - | - | Lý do/ghi chú giải thích cho trạng thái hoặc quyết định xử lý. | Nội dung mẫu |
| 8 | metadata | json | Có | - | - | JSON metadata bổ sung cho nghiệp vụ. | {"key":"value"} |
| 9 | created_at | timestamp | Không | CURRENT_TIMESTAMP | - | Thời điểm tạo bản ghi. | 2026-06-22 09:00:00 |

### 3. Khóa chính, khóa ngoại, index
- PK: id
- FK: partner_application_id -> partner_applications.id (on delete: restrict)
- FK: changed_by -> users.id (on delete: set null)
- INDEX: partner_app_status_app_created_index (partner_application_id, created_at)

### 4. Quan hệ với bảng khác
- partner_application_status_histories n-1 partner_applications qua partner_application_id.
- partner_application_status_histories n-1 users qua changed_by.

### 5. Ví dụ bản ghi
```json
{
    "id": 1,
    "partner_application_id": "10000000-0000-0000-0000-000000000001",
    "old_status": "active",
    "new_status": "active",
    "changed_by": "10000000-0000-0000-0000-000000000001",
    "actor_type": "default",
    "reason": "Nội dung mẫu",
    "metadata": {
        "key": "value"
    }
}
```

---

### MODULE: DOCUMENT

## Tên bảng: document_templates

### 1. Mục đích bảng
Lưu template DOCX cho các loại văn bản: đơn đăng ký đối tác, hợp đồng, đơn chấm dứt, biên bản thanh lý, công văn đơn phương, biên bản quyết toán. Mỗi loại có nhiều version, chỉ 1 version active.

### 2. Danh sách trường

| STT | Tên trường | Kiểu dữ liệu | Null | Default | Khóa/Ràng buộc | Mô tả tác dụng trường | Ví dụ |
|---|---|---|---|---|---|---|---|
| 1 | id | char(36) | Không | - | PK | Khóa chính định danh duy nhất của bản ghi. | 10000000-0000-0000-0000-000000000001 |
| 2 | template_code | string(100) | Không | - | UNIQUE | Trường dữ liệu phục vụ nghiệp vụ của bảng `document_templates`. | CODE-001 |
| 3 | document_type | string(100) | Không | - | - | Loại hoặc nhóm phân loại của bản ghi. | default |
| 4 | template_name | string(255) | Không | - | - | Tên/tiêu đề hiển thị hoặc dùng trong quản trị. | Ví dụ SportGo |
| 5 | version | unsignedInteger | Không | 1 | - | Trường dữ liệu phục vụ nghiệp vụ của bảng `document_templates`. | 1 |
| 6 | file_name | string(255) | Không | - | - | Đường dẫn hoặc URL tới tài nguyên liên quan. | Ví dụ SportGo |
| 7 | file_path | string(1000) | Không | - | - | Đường dẫn hoặc URL tới tài nguyên liên quan. | /storage/example.pdf |
| 8 | output_format | enum(docx, pdf) | Không | docx | - | Trường dữ liệu phục vụ nghiệp vụ của bảng `document_templates`. | example |
| 9 | mime_type | string(150) | Không | application/vnd.openxmlformats-officedocument.wordprocessingml.document | - | Loại hoặc nhóm phân loại của bản ghi. | default |
| 10 | storage_disk | string(50) | Không | local | - | Trường dữ liệu phục vụ nghiệp vụ của bảng `document_templates`. | example |
| 11 | template_variables | json | Có | - | - | Dữ liệu có cấu trúc hoặc nội dung dài phục vụ xử lý nghiệp vụ. | {"key":"value"} |
| 12 | required_fields | json | Có | - | - | Dữ liệu có cấu trúc hoặc nội dung dài phục vụ xử lý nghiệp vụ. | {"key":"value"} |
| 13 | render_engine | enum(docx_placeholder, manual_upload, pdf_static) | Không | docx_placeholder | - | Trường dữ liệu phục vụ nghiệp vụ của bảng `document_templates`. | example |
| 14 | status | enum(draft, active, inactive, archived) | Không | draft | - | Trạng thái xử lý hoặc vòng đời của bản ghi. | draft |
| 15 | is_active | boolean | Không | false | - | Cờ xác định bản ghi đang hoạt động hay bị tắt. | true |
| 16 | created_by | char(36) | Có | - | FK | Khóa ngoại tham chiếu bảng `users`, dùng để liên kết bản ghi với dữ liệu liên quan. | 10000000-0000-0000-0000-000000000001 |
| 17 | uploaded_by | char(36) | Có | - | FK | Khóa ngoại tham chiếu bảng `users`, dùng để liên kết bản ghi với dữ liệu liên quan. | 10000000-0000-0000-0000-000000000001 |
| 18 | activated_at | timestamp | Có | - | - | Thời điểm xảy ra sự kiện `activated_at`. | 2026-06-22 09:00:00 |
| 19 | replaced_template_id | char(36) | Có | - | FK | Khóa ngoại tham chiếu bảng `document_templates`, dùng để liên kết bản ghi với dữ liệu liên quan. | 10000000-0000-0000-0000-000000000001 |
| 20 | note | text | Có | - | - | Mô tả hoặc ghi chú bổ sung cho bản ghi. | Nội dung mẫu |
| 21 | created_at | timestamp | Có | - | - | Thời điểm tạo bản ghi. | 2026-06-22 09:00:00 |
| 22 | updated_at | timestamp | Có | - | - | Thời điểm cập nhật bản ghi gần nhất. | 2026-06-22 09:00:00 |

### 3. Khóa chính, khóa ngoại, index
- PK: id
- FK: created_by -> users.id (on delete: set null)
- FK: uploaded_by -> users.id (on delete: set null)
- FK: replaced_template_id -> document_templates.id (on delete: set null)
- UNIQUE: document_templates_type_version_unique (document_type, version)
- UNIQUE: document_templates_code_unique (template_code)
- INDEX: document_templates_type_status_active_index (document_type, status, is_active)

### 4. Quan hệ với bảng khác
- document_templates n-1 users qua created_by.
- document_templates n-1 users qua uploaded_by.
- document_templates n-1 document_templates qua replaced_template_id.

### 5. Ví dụ bản ghi
```json
{
    "id": "10000000-0000-0000-0000-000000000001",
    "template_code": "CODE-001",
    "document_type": "default",
    "template_name": "Ví dụ SportGo",
    "version": 1,
    "file_name": "Ví dụ SportGo",
    "file_path": "/storage/example.pdf",
    "output_format": "example"
}
```

---

## Tên bảng: generated_documents

### 1. Mục đích bảng
Lưu văn bản đã sinh từ template, bao gồm snapshot dữ liệu render, file path và trạng thái ký. Mỗi văn bản liên kết polymorphic hoặc FK trực tiếp tới hồ sơ/hợp đồng/chấm dứt/quyết toán.

### 2. Danh sách trường

| STT | Tên trường | Kiểu dữ liệu | Null | Default | Khóa/Ràng buộc | Mô tả tác dụng trường | Ví dụ |
|---|---|---|---|---|---|---|---|
| 1 | id | char(36) | Không | - | PK | Khóa chính định danh duy nhất của bản ghi. | 10000000-0000-0000-0000-000000000001 |
| 2 | document_code | string(50) | Không | - | UNIQUE | Trường dữ liệu phục vụ nghiệp vụ của bảng `generated_documents`. | CODE-001 |
| 3 | document_type | string(100) | Không | - | - | Loại hoặc nhóm phân loại của bản ghi. | default |
| 4 | template_id | char(36) | Không | - | FK | Khóa ngoại tham chiếu bảng `document_templates`, dùng để liên kết bản ghi với dữ liệu liên quan. | 10000000-0000-0000-0000-000000000001 |
| 5 | template_version | unsignedInteger | Không | - | - | Trường dữ liệu phục vụ nghiệp vụ của bảng `generated_documents`. | 1 |
| 6 | reference_type | string(100) | Có | - | - | Loại hoặc nhóm phân loại của bản ghi. | default |
| 7 | reference_id | string(100) | Có | - | - | ID tham chiếu đối tượng liên quan trong nghiệp vụ. | 1 |
| 8 | entity_type | string(100) | Có | - | - | Loại hoặc nhóm phân loại của bản ghi. | default |
| 9 | entity_id | string(100) | Có | - | - | ID tham chiếu đối tượng liên quan trong nghiệp vụ. | 1 |
| 10 | partner_application_id | char(36) | Có | - | FK, INDEX | Khóa ngoại tham chiếu bảng `partner_applications`, dùng để liên kết bản ghi với dữ liệu liên quan. | 10000000-0000-0000-0000-000000000001 |
| 11 | partner_contract_id | char(36) | Có | - | FK, INDEX | Khóa ngoại tham chiếu bảng `partner_contracts`, dùng để liên kết bản ghi với dữ liệu liên quan. | 10000000-0000-0000-0000-000000000001 |
| 12 | partner_termination_request_id | char(36) | Có | - | FK, INDEX | Khóa ngoại tham chiếu bảng `partner_termination_requests`, dùng để liên kết bản ghi với dữ liệu liên quan. | 10000000-0000-0000-0000-000000000001 |
| 13 | partner_settlement_id | char(36) | Có | - | FK, INDEX | Khóa ngoại tham chiếu bảng `partner_settlements`, dùng để liên kết bản ghi với dữ liệu liên quan. | 10000000-0000-0000-0000-000000000001 |
| 14 | owner_id | char(36) | Có | - | FK | Khóa ngoại tham chiếu bảng `users`, dùng để liên kết bản ghi với dữ liệu liên quan. | 10000000-0000-0000-0000-000000000001 |
| 15 | venue_cluster_id | char(36) | Có | - | FK | Khóa ngoại tham chiếu bảng `venue_clusters`, dùng để liên kết bản ghi với dữ liệu liên quan. | 10000000-0000-0000-0000-000000000001 |
| 16 | title | string(255) | Có | - | - | Tên/tiêu đề hiển thị hoặc dùng trong quản trị. | Ví dụ SportGo |
| 17 | status | enum(draft, generated, pending_owner_signature, pending_sportgo_signature, signed, completed, cancelled) | Không | generated | - | Trạng thái xử lý hoặc vòng đời của bản ghi. | draft |
| 18 | render_data | json | Không | - | - | Dữ liệu có cấu trúc hoặc nội dung dài phục vụ xử lý nghiệp vụ. | {"key":"value"} |
| 19 | generated_file_media_id | char(36) | Có | - | FK | Khóa ngoại tham chiếu bảng `media`, dùng để liên kết bản ghi với dữ liệu liên quan. | 10000000-0000-0000-0000-000000000001 |
| 20 | signed_file_media_id | char(36) | Có | - | FK | Khóa ngoại tham chiếu bảng `media`, dùng để liên kết bản ghi với dữ liệu liên quan. | 10000000-0000-0000-0000-000000000001 |
| 21 | final_file_media_id | char(36) | Có | - | FK | Khóa ngoại tham chiếu bảng `media`, dùng để liên kết bản ghi với dữ liệu liên quan. | 10000000-0000-0000-0000-000000000001 |
| 22 | generated_file_path | string(1000) | Có | - | - | Đường dẫn hoặc URL tới tài nguyên liên quan. | /storage/example.pdf |
| 23 | final_file_path | string(1000) | Có | - | - | Đường dẫn hoặc URL tới tài nguyên liên quan. | /storage/example.pdf |
| 24 | file_hash | string(128) | Có | - | - | Đường dẫn hoặc URL tới tài nguyên liên quan. | example |
| 25 | generated_by | char(36) | Có | - | FK | Khóa ngoại tham chiếu bảng `users`, dùng để liên kết bản ghi với dữ liệu liên quan. | 10000000-0000-0000-0000-000000000001 |
| 26 | generated_at | timestamp | Có | - | - | Thời điểm xảy ra sự kiện `generated_at`. | 2026-06-22 09:00:00 |
| 27 | locked_at | timestamp | Có | - | - | Thời điểm xảy ra sự kiện `locked_at`. | 2026-06-22 09:00:00 |
| 28 | completed_at | timestamp | Có | - | - | Thời điểm xảy ra sự kiện `completed_at`. | 2026-06-22 09:00:00 |
| 29 | created_at | timestamp | Có | - | - | Thời điểm tạo bản ghi. | 2026-06-22 09:00:00 |
| 30 | updated_at | timestamp | Có | - | - | Thời điểm cập nhật bản ghi gần nhất. | 2026-06-22 09:00:00 |

### 3. Khóa chính, khóa ngoại, index
- PK: id
- FK: template_id -> document_templates.id (on delete: restrict)
- FK: partner_application_id -> partner_applications.id (on delete: set null)
- FK: owner_id -> users.id (on delete: set null)
- FK: venue_cluster_id -> venue_clusters.id (on delete: set null)
- FK: generated_file_media_id -> media.id (on delete: set null)
- FK: signed_file_media_id -> media.id (on delete: set null)
- FK: final_file_media_id -> media.id (on delete: set null)
- FK: generated_by -> users.id (on delete: set null)
- FK: partner_contract_id -> partner_contracts.id (on delete: set null)
- FK: partner_termination_request_id -> partner_termination_requests.id (on delete: set null)
- FK: partner_settlement_id -> partner_settlements.id (on delete: set null)
- UNIQUE: generated_documents_document_code_unique (document_code)
- INDEX: generated_documents_reference_index (reference_type, reference_id)
- INDEX: generated_documents_entity_index (entity_type, entity_id)
- INDEX: generated_documents_application_index (partner_application_id)
- INDEX: generated_documents_contract_index (partner_contract_id)
- INDEX: generated_documents_termination_index (partner_termination_request_id)
- INDEX: generated_documents_settlement_index (partner_settlement_id)
- INDEX: generated_documents_owner_cluster_index (owner_id, venue_cluster_id)
- INDEX: generated_documents_type_status_index (document_type, status)

### 4. Quan hệ với bảng khác
- generated_documents n-1 document_templates qua template_id.
- generated_documents n-1 partner_applications qua partner_application_id.
- generated_documents n-1 users qua owner_id.
- generated_documents n-1 venue_clusters qua venue_cluster_id.
- generated_documents n-1 media qua generated_file_media_id.
- generated_documents n-1 media qua signed_file_media_id.
- generated_documents n-1 media qua final_file_media_id.
- generated_documents n-1 users qua generated_by.
- generated_documents n-1 partner_contracts qua partner_contract_id.
- generated_documents n-1 partner_termination_requests qua partner_termination_request_id.
- generated_documents n-1 partner_settlements qua partner_settlement_id.

### 5. Ví dụ bản ghi
```json
{
    "id": "10000000-0000-0000-0000-000000000001",
    "document_code": "CODE-001",
    "document_type": "default",
    "template_id": "10000000-0000-0000-0000-000000000001",
    "template_version": 1,
    "reference_type": "default",
    "reference_id": 1,
    "entity_type": "default"
}
```

---

## Tên bảng: generated_document_signatures

### 1. Mục đích bảng
Lưu chữ ký/xác nhận của mỗi bên (owner, SportGo) trên văn bản đã sinh. Hỗ trợ nhiều phương thức ký: upload ảnh, vẽ tay, gõ xác nhận, OTP, ký số.

### 2. Danh sách trường

| STT | Tên trường | Kiểu dữ liệu | Null | Default | Khóa/Ràng buộc | Mô tả tác dụng trường | Ví dụ |
|---|---|---|---|---|---|---|---|
| 1 | id | char(36) | Không | - | PK | Khóa chính định danh duy nhất của bản ghi. | 10000000-0000-0000-0000-000000000001 |
| 2 | generated_document_id | char(36) | Không | - | FK | Khóa ngoại tham chiếu bảng `generated_documents`, dùng để liên kết bản ghi với dữ liệu liên quan. | 10000000-0000-0000-0000-000000000001 |
| 3 | signer_side | enum(owner, sportgo, witness, system) | Không | - | - | Trường dữ liệu phục vụ nghiệp vụ của bảng `generated_document_signatures`. | example |
| 4 | signer_user_id | char(36) | Có | - | FK | Khóa ngoại tham chiếu bảng `users`, dùng để liên kết bản ghi với dữ liệu liên quan. | 10000000-0000-0000-0000-000000000001 |
| 5 | signer_full_name | string(255) | Không | - | - | Tên/tiêu đề hiển thị hoặc dùng trong quản trị. | Ví dụ SportGo |
| 6 | signer_title | string(255) | Có | - | - | Tên/tiêu đề hiển thị hoặc dùng trong quản trị. | Ví dụ SportGo |
| 7 | signer_organization | string(255) | Có | - | - | Trường dữ liệu phục vụ nghiệp vụ của bảng `generated_document_signatures`. | example |
| 8 | signature_method | enum(uploaded_image, drawn, typed_confirm, otp_confirm, digital) | Không | typed_confirm | - | Trường dữ liệu phục vụ nghiệp vụ của bảng `generated_document_signatures`. | example |
| 9 | signature_media_id | char(36) | Có | - | FK | Khóa ngoại tham chiếu bảng `media`, dùng để liên kết bản ghi với dữ liệu liên quan. | 10000000-0000-0000-0000-000000000001 |
| 10 | signed_at | timestamp | Có | - | - | Thời điểm xảy ra sự kiện `signed_at`. | 2026-06-22 09:00:00 |
| 11 | ip_address | string(45) | Có | - | - | Địa chỉ IP tại thời điểm thực hiện thao tác. | example |
| 12 | user_agent | string(500) | Có | - | - | Thông tin trình duyệt/thiết bị tại thời điểm thao tác. | example |
| 13 | status | enum(pending, signed, rejected, cancelled) | Không | pending | - | Trạng thái xử lý hoặc vòng đời của bản ghi. | pending |
| 14 | reject_reason | text | Có | - | - | Lý do/ghi chú giải thích cho trạng thái hoặc quyết định xử lý. | Nội dung mẫu |
| 15 | created_at | timestamp | Có | - | - | Thời điểm tạo bản ghi. | 2026-06-22 09:00:00 |
| 16 | updated_at | timestamp | Có | - | - | Thời điểm cập nhật bản ghi gần nhất. | 2026-06-22 09:00:00 |

### 3. Khóa chính, khóa ngoại, index
- PK: id
- FK: generated_document_id -> generated_documents.id (on delete: restrict)
- FK: signer_user_id -> users.id (on delete: set null)
- FK: signature_media_id -> media.id (on delete: set null)
- INDEX: generated_doc_signatures_doc_side_status_index (generated_document_id, signer_side, status)

### 4. Quan hệ với bảng khác
- generated_document_signatures n-1 generated_documents qua generated_document_id.
- generated_document_signatures n-1 users qua signer_user_id.
- generated_document_signatures n-1 media qua signature_media_id.

### 5. Ví dụ bản ghi
```json
{
    "id": "10000000-0000-0000-0000-000000000001",
    "generated_document_id": "10000000-0000-0000-0000-000000000001",
    "signer_side": "example",
    "signer_user_id": "10000000-0000-0000-0000-000000000001",
    "signer_full_name": "Ví dụ SportGo",
    "signer_title": "Ví dụ SportGo",
    "signer_organization": "example",
    "signature_method": "example"
}
```

---

### MODULE: CONTRACT

## Tên bảng: partner_contracts

### 1. Mục đích bảng
Lưu hợp đồng giữa SportGo và chủ sân. Mỗi hợp đồng được sinh từ hồ sơ đối tác đã duyệt, link tới văn bản đã ký. Status: draft → generated → pending_owner_signature → pending_sportgo_signature → signed_active.

### 2. Danh sách trường

| STT | Tên trường | Kiểu dữ liệu | Null | Default | Khóa/Ràng buộc | Mô tả tác dụng trường | Ví dụ |
|---|---|---|---|---|---|---|---|
| 1 | id | char(36) | Không | - | PK | Khóa chính định danh duy nhất của bản ghi. | 10000000-0000-0000-0000-000000000001 |
| 2 | contract_code | string(50) | Không | - | UNIQUE | Trường dữ liệu phục vụ nghiệp vụ của bảng `partner_contracts`. | CODE-001 |
| 3 | partner_application_id | char(36) | Không | - | FK | Khóa ngoại tham chiếu bảng `partner_applications`, dùng để liên kết bản ghi với dữ liệu liên quan. | 10000000-0000-0000-0000-000000000001 |
| 4 | owner_id | char(36) | Không | - | FK | Khóa ngoại tham chiếu bảng `users`, dùng để liên kết bản ghi với dữ liệu liên quan. | 10000000-0000-0000-0000-000000000001 |
| 5 | venue_cluster_id | char(36) | Có | - | FK | Khóa ngoại tham chiếu bảng `venue_clusters`, dùng để liên kết bản ghi với dữ liệu liên quan. | 10000000-0000-0000-0000-000000000001 |
| 6 | contract_title | string(255) | Không | - | - | Tên/tiêu đề hiển thị hoặc dùng trong quản trị. | Ví dụ SportGo |
| 7 | status | enum(draft, generated, pending_owner_signature, pending_sportgo_signature, signed_active, cancelled, terminated) | Không | draft | - | Trạng thái xử lý hoặc vòng đời của bản ghi. | draft |
| 8 | generated_document_id | char(36) | Có | - | FK | Khóa ngoại tham chiếu bảng `generated_documents`, dùng để liên kết bản ghi với dữ liệu liên quan. | 10000000-0000-0000-0000-000000000001 |
| 9 | generated_file_media_id | char(36) | Có | - | - | ID tham chiếu đối tượng liên quan trong nghiệp vụ. | 10000000-0000-0000-0000-000000000001 |
| 10 | signed_file_media_id | char(36) | Có | - | - | ID tham chiếu đối tượng liên quan trong nghiệp vụ. | 10000000-0000-0000-0000-000000000001 |
| 11 | final_file_media_id | char(36) | Có | - | - | ID tham chiếu đối tượng liên quan trong nghiệp vụ. | 10000000-0000-0000-0000-000000000001 |
| 12 | generated_by | char(36) | Có | - | FK | Khóa ngoại tham chiếu bảng `users`, dùng để liên kết bản ghi với dữ liệu liên quan. | 10000000-0000-0000-0000-000000000001 |
| 13 | approved_by | char(36) | Có | - | FK | Khóa ngoại tham chiếu bảng `users`, dùng để liên kết bản ghi với dữ liệu liên quan. | 10000000-0000-0000-0000-000000000001 |
| 14 | owner_signed_at | timestamp | Có | - | - | Thời điểm xảy ra sự kiện `owner_signed_at`. | 2026-06-22 09:00:00 |
| 15 | sportgo_signed_at | timestamp | Có | - | - | Thời điểm xảy ra sự kiện `sportgo_signed_at`. | 2026-06-22 09:00:00 |
| 16 | effective_from | timestamp | Có | - | - | Trường dữ liệu phục vụ nghiệp vụ của bảng `partner_contracts`. | 2026-06-22 09:00:00 |
| 17 | effective_to | timestamp | Có | - | - | Trường dữ liệu phục vụ nghiệp vụ của bảng `partner_contracts`. | 2026-06-22 09:00:00 |
| 18 | terminated_at | timestamp | Có | - | - | Thời điểm xảy ra sự kiện `terminated_at`. | 2026-06-22 09:00:00 |
| 19 | note | text | Có | - | - | Mô tả hoặc ghi chú bổ sung cho bản ghi. | Nội dung mẫu |
| 20 | created_at | timestamp | Có | - | - | Thời điểm tạo bản ghi. | 2026-06-22 09:00:00 |
| 21 | updated_at | timestamp | Có | - | - | Thời điểm cập nhật bản ghi gần nhất. | 2026-06-22 09:00:00 |
| 22 | deleted_at | timestamp | Có | - | - | Thời điểm xóa mềm; null nghĩa là bản ghi còn hiệu lực. | 2026-06-22 09:00:00 |

### 3. Khóa chính, khóa ngoại, index
- PK: id
- FK: partner_application_id -> partner_applications.id (on delete: restrict)
- FK: owner_id -> users.id (on delete: restrict)
- FK: venue_cluster_id -> venue_clusters.id (on delete: set null)
- FK: generated_document_id -> generated_documents.id (on delete: restrict)
- FK: generated_by -> users.id (on delete: set null)
- FK: approved_by -> users.id (on delete: set null)
- UNIQUE: partner_contracts_contract_code_unique (contract_code)
- INDEX: partner_contracts_app_status_index (partner_application_id, status)
- INDEX: partner_contracts_owner_status_index (owner_id, status)

### 4. Quan hệ với bảng khác
- partner_contracts n-1 partner_applications qua partner_application_id.
- partner_contracts n-1 users qua owner_id.
- partner_contracts n-1 venue_clusters qua venue_cluster_id.
- partner_contracts n-1 generated_documents qua generated_document_id.
- partner_contracts n-1 users qua generated_by.
- partner_contracts n-1 users qua approved_by.

### 5. Ví dụ bản ghi
```json
{
    "id": "10000000-0000-0000-0000-000000000001",
    "contract_code": "CODE-001",
    "partner_application_id": "10000000-0000-0000-0000-000000000001",
    "owner_id": "10000000-0000-0000-0000-000000000001",
    "venue_cluster_id": "10000000-0000-0000-0000-000000000001",
    "contract_title": "Ví dụ SportGo",
    "status": "draft",
    "generated_document_id": "10000000-0000-0000-0000-000000000001"
}
```

---

### MODULE: TERMINATION

## Tên bảng: partner_termination_requests

### 1. Mục đích bảng
Quản lý yêu cầu chấm dứt hợp tác: hai bên đồng ý (mutual_agreement), đơn phương bởi owner (unilateral_by_owner) hoặc đơn phương bởi SportGo (unilateral_by_sportgo). Có thời gian chuyển tiếp trước khi thu quyền.

### 2. Danh sách trường

| STT | Tên trường | Kiểu dữ liệu | Null | Default | Khóa/Ràng buộc | Mô tả tác dụng trường | Ví dụ |
|---|---|---|---|---|---|---|---|
| 1 | id | char(36) | Không | - | PK | Khóa chính định danh duy nhất của bản ghi. | 10000000-0000-0000-0000-000000000001 |
| 2 | termination_code | string(50) | Không | - | UNIQUE | Trường dữ liệu phục vụ nghiệp vụ của bảng `partner_termination_requests`. | CODE-001 |
| 3 | partner_contract_id | char(36) | Không | - | FK | Khóa ngoại tham chiếu bảng `partner_contracts`, dùng để liên kết bản ghi với dữ liệu liên quan. | 10000000-0000-0000-0000-000000000001 |
| 4 | partner_application_id | char(36) | Có | - | FK | Khóa ngoại tham chiếu bảng `partner_applications`, dùng để liên kết bản ghi với dữ liệu liên quan. | 10000000-0000-0000-0000-000000000001 |
| 5 | owner_id | char(36) | Không | - | FK | Khóa ngoại tham chiếu bảng `users`, dùng để liên kết bản ghi với dữ liệu liên quan. | 10000000-0000-0000-0000-000000000001 |
| 6 | venue_cluster_id | char(36) | Có | - | FK | Khóa ngoại tham chiếu bảng `venue_clusters`, dùng để liên kết bản ghi với dữ liệu liên quan. | 10000000-0000-0000-0000-000000000001 |
| 7 | termination_type | enum(mutual_agreement, unilateral_by_owner, unilateral_by_sportgo) | Không | - | - | Loại hoặc nhóm phân loại của bản ghi. | mutual_agreement |
| 8 | requested_by | char(36) | Không | - | FK | Khóa ngoại tham chiếu bảng `users`, dùng để liên kết bản ghi với dữ liệu liên quan. | 10000000-0000-0000-0000-000000000001 |
| 9 | requested_at | timestamp | Không | CURRENT_TIMESTAMP | - | Thời điểm xảy ra sự kiện `requested_at`. | 2026-06-22 09:00:00 |
| 10 | reason | text | Không | - | - | Lý do/ghi chú giải thích cho trạng thái hoặc quyết định xử lý. | Nội dung mẫu |
| 11 | requested_effective_date | date | Có | - | - | Trường dữ liệu phục vụ nghiệp vụ của bảng `partner_termination_requests`. | 2026-06-22 |
| 12 | status | enum(draft, submitted, reviewing, approved, pending_signature, settlement_processing, settlement_completed, transition_period, completed, rejected, cancelled) | Không | draft | - | Trạng thái xử lý hoặc vòng đời của bản ghi. | draft |
| 13 | approved_by | char(36) | Có | - | FK | Khóa ngoại tham chiếu bảng `users`, dùng để liên kết bản ghi với dữ liệu liên quan. | 10000000-0000-0000-0000-000000000001 |
| 14 | approved_at | timestamp | Có | - | - | Thời điểm xảy ra sự kiện `approved_at`. | 2026-06-22 09:00:00 |
| 15 | reject_reason | text | Có | - | - | Lý do/ghi chú giải thích cho trạng thái hoặc quyết định xử lý. | Nội dung mẫu |
| 16 | effective_termination_date | timestamp | Có | - | - | Trường dữ liệu phục vụ nghiệp vụ của bảng `partner_termination_requests`. | 2026-06-22 09:00:00 |
| 17 | transition_end_at | timestamp | Có | - | - | Thời điểm xảy ra sự kiện `transition_end_at`. | 2026-06-22 09:00:00 |
| 18 | owner_access_revoked_at | timestamp | Có | - | - | Thời điểm xảy ra sự kiện `owner_access_revoked_at`. | 2026-06-22 09:00:00 |
| 19 | created_at | timestamp | Có | - | - | Thời điểm tạo bản ghi. | 2026-06-22 09:00:00 |
| 20 | updated_at | timestamp | Có | - | - | Thời điểm cập nhật bản ghi gần nhất. | 2026-06-22 09:00:00 |

### 3. Khóa chính, khóa ngoại, index
- PK: id
- FK: partner_contract_id -> partner_contracts.id (on delete: restrict)
- FK: partner_application_id -> partner_applications.id (on delete: set null)
- FK: owner_id -> users.id (on delete: restrict)
- FK: venue_cluster_id -> venue_clusters.id (on delete: set null)
- FK: requested_by -> users.id (on delete: restrict)
- FK: approved_by -> users.id (on delete: set null)
- UNIQUE: partner_termination_requests_termination_code_unique (termination_code)
- INDEX: partner_term_requests_contract_status_index (partner_contract_id, status)
- INDEX: partner_term_requests_owner_status_index (owner_id, status)
- INDEX: partner_term_requests_type_status_index (termination_type, status)

### 4. Quan hệ với bảng khác
- partner_termination_requests n-1 partner_contracts qua partner_contract_id.
- partner_termination_requests n-1 partner_applications qua partner_application_id.
- partner_termination_requests n-1 users qua owner_id.
- partner_termination_requests n-1 venue_clusters qua venue_cluster_id.
- partner_termination_requests n-1 users qua requested_by.
- partner_termination_requests n-1 users qua approved_by.

### 5. Ví dụ bản ghi
```json
{
    "id": "10000000-0000-0000-0000-000000000001",
    "termination_code": "CODE-001",
    "partner_contract_id": "10000000-0000-0000-0000-000000000001",
    "partner_application_id": "10000000-0000-0000-0000-000000000001",
    "owner_id": "10000000-0000-0000-0000-000000000001",
    "venue_cluster_id": "10000000-0000-0000-0000-000000000001",
    "termination_type": "mutual_agreement",
    "requested_by": "10000000-0000-0000-0000-000000000001"
}
```

---

## Tên bảng: partner_termination_documents

### 1. Mục đích bảng
Lưu các văn bản liên quan đến yêu cầu chấm dứt: đơn chấm dứt, biên bản thanh lý, công văn đơn phương, biên bản quyết toán, file cuối cùng.

### 2. Danh sách trường

| STT | Tên trường | Kiểu dữ liệu | Null | Default | Khóa/Ràng buộc | Mô tả tác dụng trường | Ví dụ |
|---|---|---|---|---|---|---|---|
| 1 | id | unsignedBigInteger | Không | - | PK | Khóa chính định danh duy nhất của bản ghi. | 1 |
| 2 | partner_termination_request_id | char(36) | Không | - | FK | Khóa ngoại tham chiếu bảng `partner_termination_requests`, dùng để liên kết bản ghi với dữ liệu liên quan. | 10000000-0000-0000-0000-000000000001 |
| 3 | generated_document_id | char(36) | Có | - | FK | Khóa ngoại tham chiếu bảng `generated_documents`, dùng để liên kết bản ghi với dữ liệu liên quan. | 10000000-0000-0000-0000-000000000001 |
| 4 | document_type | enum(owner_termination_request, mutual_liquidation_minutes, unilateral_notice, settlement_minutes, final_termination_file) | Không | - | - | Loại hoặc nhóm phân loại của bản ghi. | owner_termination_request |
| 5 | media_id | char(36) | Có | - | FK | Khóa ngoại tham chiếu bảng `media`, dùng để liên kết bản ghi với dữ liệu liên quan. | 10000000-0000-0000-0000-000000000001 |
| 6 | file_path | string(1000) | Có | - | - | Đường dẫn hoặc URL tới tài nguyên liên quan. | /storage/example.pdf |
| 7 | status | enum(generated, pending_signature, signed, completed, cancelled) | Không | generated | - | Trạng thái xử lý hoặc vòng đời của bản ghi. | generated |
| 8 | generated_by | char(36) | Có | - | FK | Khóa ngoại tham chiếu bảng `users`, dùng để liên kết bản ghi với dữ liệu liên quan. | 10000000-0000-0000-0000-000000000001 |
| 9 | generated_at | timestamp | Có | - | - | Thời điểm xảy ra sự kiện `generated_at`. | 2026-06-22 09:00:00 |
| 10 | created_at | timestamp | Có | - | - | Thời điểm tạo bản ghi. | 2026-06-22 09:00:00 |
| 11 | updated_at | timestamp | Có | - | - | Thời điểm cập nhật bản ghi gần nhất. | 2026-06-22 09:00:00 |

### 3. Khóa chính, khóa ngoại, index
- PK: id
- FK: partner_termination_request_id -> partner_termination_requests.id (on delete: restrict)
- FK: generated_document_id -> generated_documents.id (on delete: restrict)
- FK: media_id -> media.id (on delete: set null)
- FK: generated_by -> users.id (on delete: set null)
- INDEX: partner_term_docs_request_type_index (partner_termination_request_id, document_type)

### 4. Quan hệ với bảng khác
- partner_termination_documents n-1 partner_termination_requests qua partner_termination_request_id.
- partner_termination_documents n-1 generated_documents qua generated_document_id.
- partner_termination_documents n-1 media qua media_id.
- partner_termination_documents n-1 users qua generated_by.

### 5. Ví dụ bản ghi
```json
{
    "id": 1,
    "partner_termination_request_id": "10000000-0000-0000-0000-000000000001",
    "generated_document_id": "10000000-0000-0000-0000-000000000001",
    "document_type": "owner_termination_request",
    "media_id": "10000000-0000-0000-0000-000000000001",
    "file_path": "/storage/example.pdf",
    "status": "generated",
    "generated_by": "10000000-0000-0000-0000-000000000001"
}
```

---

## Tên bảng: partner_termination_status_histories

### 1. Mục đích bảng
Ghi nhận từng bước xử lý yêu cầu chấm dứt hợp tác: submitted → reviewing → approved → settlement_processing → completed.

### 2. Danh sách trường

| STT | Tên trường | Kiểu dữ liệu | Null | Default | Khóa/Ràng buộc | Mô tả tác dụng trường | Ví dụ |
|---|---|---|---|---|---|---|---|
| 1 | id | unsignedBigInteger | Không | - | PK | Khóa chính định danh duy nhất của bản ghi. | 1 |
| 2 | partner_termination_request_id | char(36) | Không | - | FK | Khóa ngoại tham chiếu bảng `partner_termination_requests`, dùng để liên kết bản ghi với dữ liệu liên quan. | 10000000-0000-0000-0000-000000000001 |
| 3 | old_status | string(50) | Có | - | - | Trạng thái xử lý hoặc vòng đời của bản ghi. | active |
| 4 | new_status | string(50) | Không | - | - | Trạng thái xử lý hoặc vòng đời của bản ghi. | active |
| 5 | changed_by | char(36) | Có | - | FK | Khóa ngoại tham chiếu bảng `users`, dùng để liên kết bản ghi với dữ liệu liên quan. | 10000000-0000-0000-0000-000000000001 |
| 6 | actor_type | string(50) | Không | admin | - | Loại hoặc nhóm phân loại của bản ghi. | default |
| 7 | reason | text | Có | - | - | Lý do/ghi chú giải thích cho trạng thái hoặc quyết định xử lý. | Nội dung mẫu |
| 8 | metadata | json | Có | - | - | JSON metadata bổ sung cho nghiệp vụ. | {"key":"value"} |
| 9 | created_at | timestamp | Không | CURRENT_TIMESTAMP | - | Thời điểm tạo bản ghi. | 2026-06-22 09:00:00 |

### 3. Khóa chính, khóa ngoại, index
- PK: id
- FK: partner_termination_request_id -> partner_termination_requests.id (on delete: restrict)
- FK: changed_by -> users.id (on delete: set null)
- INDEX: partner_term_status_request_created_index (partner_termination_request_id, created_at)

### 4. Quan hệ với bảng khác
- partner_termination_status_histories n-1 partner_termination_requests qua partner_termination_request_id.
- partner_termination_status_histories n-1 users qua changed_by.

### 5. Ví dụ bản ghi
```json
{
    "id": 1,
    "partner_termination_request_id": "10000000-0000-0000-0000-000000000001",
    "old_status": "active",
    "new_status": "active",
    "changed_by": "10000000-0000-0000-0000-000000000001",
    "actor_type": "default",
    "reason": "Nội dung mẫu",
    "metadata": {
        "key": "value"
    }
}
```

---

### MODULE: SETTLEMENT

## Tên bảng: partner_settlements

### 1. Mục đích bảng
Lưu kết quả quyết toán công nợ khi chấm dứt hợp tác. Tính toán: ví owner, phí nền tảng còn hoàn, phí nền tảng chưa đóng, phạt, điều chỉnh → ra final_payable_to_owner hoặc final_receivable_from_owner.

### 2. Danh sách trường

| STT | Tên trường | Kiểu dữ liệu | Null | Default | Khóa/Ràng buộc | Mô tả tác dụng trường | Ví dụ |
|---|---|---|---|---|---|---|---|
| 1 | id | char(36) | Không | - | PK | Khóa chính định danh duy nhất của bản ghi. | 10000000-0000-0000-0000-000000000001 |
| 2 | settlement_code | string(50) | Không | - | UNIQUE | Trường dữ liệu phục vụ nghiệp vụ của bảng `partner_settlements`. | CODE-001 |
| 3 | partner_termination_request_id | char(36) | Không | - | FK | Khóa ngoại tham chiếu bảng `partner_termination_requests`, dùng để liên kết bản ghi với dữ liệu liên quan. | 10000000-0000-0000-0000-000000000001 |
| 4 | partner_contract_id | char(36) | Không | - | FK | Khóa ngoại tham chiếu bảng `partner_contracts`, dùng để liên kết bản ghi với dữ liệu liên quan. | 10000000-0000-0000-0000-000000000001 |
| 5 | owner_id | char(36) | Không | - | FK | Khóa ngoại tham chiếu bảng `users`, dùng để liên kết bản ghi với dữ liệu liên quan. | 10000000-0000-0000-0000-000000000001 |
| 6 | venue_cluster_id | char(36) | Có | - | FK | Khóa ngoại tham chiếu bảng `venue_clusters`, dùng để liên kết bản ghi với dữ liệu liên quan. | 10000000-0000-0000-0000-000000000001 |
| 7 | owner_wallet_available_amount | decimal(14,2) | Không | 0 | - | Giá trị tiền tệ dùng trong tính toán và đối soát. | 100000 |
| 8 | owner_wallet_pending_amount | decimal(14,2) | Không | 0 | - | Giá trị tiền tệ dùng trong tính toán và đối soát. | 100000 |
| 9 | platform_fee_remaining_refund_amount | decimal(14,2) | Không | 0 | - | Giá trị tiền tệ dùng trong tính toán và đối soát. | 100000 |
| 10 | unpaid_platform_fee_amount | decimal(14,2) | Không | 0 | - | Giá trị tiền tệ dùng trong tính toán và đối soát. | 100000 |
| 11 | penalty_amount | decimal(14,2) | Không | 0 | - | Giá trị tiền tệ dùng trong tính toán và đối soát. | 100000 |
| 12 | adjustment_amount | decimal(14,2) | Không | 0 | - | Giá trị tiền tệ dùng trong tính toán và đối soát. | 100000 |
| 13 | final_payable_to_owner | decimal(14,2) | Không | 0 | - | Trường dữ liệu phục vụ nghiệp vụ của bảng `partner_settlements`. | 1 |
| 14 | final_receivable_from_owner | decimal(14,2) | Không | 0 | - | Trường dữ liệu phục vụ nghiệp vụ của bảng `partner_settlements`. | 1 |
| 15 | status | enum(draft, calculated, pending_approval, approved, payout_created, completed, cancelled) | Không | draft | - | Trạng thái xử lý hoặc vòng đời của bản ghi. | draft |
| 16 | calculated_by | char(36) | Có | - | FK | Khóa ngoại tham chiếu bảng `users`, dùng để liên kết bản ghi với dữ liệu liên quan. | 10000000-0000-0000-0000-000000000001 |
| 17 | approved_by | char(36) | Có | - | FK | Khóa ngoại tham chiếu bảng `users`, dùng để liên kết bản ghi với dữ liệu liên quan. | 10000000-0000-0000-0000-000000000001 |
| 18 | approved_at | timestamp | Có | - | - | Thời điểm xảy ra sự kiện `approved_at`. | 2026-06-22 09:00:00 |
| 19 | note | text | Có | - | - | Mô tả hoặc ghi chú bổ sung cho bản ghi. | Nội dung mẫu |
| 20 | created_at | timestamp | Có | - | - | Thời điểm tạo bản ghi. | 2026-06-22 09:00:00 |
| 21 | updated_at | timestamp | Có | - | - | Thời điểm cập nhật bản ghi gần nhất. | 2026-06-22 09:00:00 |

### 3. Khóa chính, khóa ngoại, index
- PK: id
- FK: partner_termination_request_id -> partner_termination_requests.id (on delete: restrict)
- FK: partner_contract_id -> partner_contracts.id (on delete: restrict)
- FK: owner_id -> users.id (on delete: restrict)
- FK: venue_cluster_id -> venue_clusters.id (on delete: set null)
- FK: calculated_by -> users.id (on delete: set null)
- FK: approved_by -> users.id (on delete: set null)
- UNIQUE: partner_settlements_settlement_code_unique (settlement_code)
- INDEX: partner_settlements_request_status_index (partner_termination_request_id, status)
- INDEX: partner_settlements_owner_status_index (owner_id, status)

### 4. Quan hệ với bảng khác
- partner_settlements n-1 partner_termination_requests qua partner_termination_request_id.
- partner_settlements n-1 partner_contracts qua partner_contract_id.
- partner_settlements n-1 users qua owner_id.
- partner_settlements n-1 venue_clusters qua venue_cluster_id.
- partner_settlements n-1 users qua calculated_by.
- partner_settlements n-1 users qua approved_by.

### 5. Ví dụ bản ghi
```json
{
    "id": "10000000-0000-0000-0000-000000000001",
    "settlement_code": "CODE-001",
    "partner_termination_request_id": "10000000-0000-0000-0000-000000000001",
    "partner_contract_id": "10000000-0000-0000-0000-000000000001",
    "owner_id": "10000000-0000-0000-0000-000000000001",
    "venue_cluster_id": "10000000-0000-0000-0000-000000000001",
    "owner_wallet_available_amount": 100000,
    "owner_wallet_pending_amount": 100000
}
```

---

## Tên bảng: partner_settlement_items

### 1. Mục đích bảng
Lưu từng dòng chi tiết trong biên bản quyết toán: ví owner, rút tiền đang chờ, phí nền tảng hoàn, phí chưa đóng, phạt, điều chỉnh.

### 2. Danh sách trường

| STT | Tên trường | Kiểu dữ liệu | Null | Default | Khóa/Ràng buộc | Mô tả tác dụng trường | Ví dụ |
|---|---|---|---|---|---|---|---|
| 1 | id | unsignedBigInteger | Không | - | PK | Khóa chính định danh duy nhất của bản ghi. | 1 |
| 2 | partner_settlement_id | char(36) | Không | - | FK | Khóa ngoại tham chiếu bảng `partner_settlements`, dùng để liên kết bản ghi với dữ liệu liên quan. | 10000000-0000-0000-0000-000000000001 |
| 3 | item_type | enum(owner_wallet_balance, pending_withdrawal, platform_fee_remaining_refund, unpaid_platform_fee, penalty, adjustment) | Không | - | - | Loại hoặc nhóm phân loại của bản ghi. | owner_wallet_balance |
| 4 | description | text | Không | - | - | Mô tả hoặc ghi chú bổ sung cho bản ghi. | Nội dung mẫu |
| 5 | amount | decimal(14,2) | Không | - | - | Giá trị tiền tệ dùng trong tính toán và đối soát. | 100000 |
| 6 | direction | enum(payable_to_owner, receivable_from_owner) | Không | - | - | Trường dữ liệu phục vụ nghiệp vụ của bảng `partner_settlement_items`. | example |
| 7 | reference_type | string(100) | Có | - | - | Loại hoặc nhóm phân loại của bản ghi. | default |
| 8 | reference_id | string(100) | Có | - | - | ID tham chiếu đối tượng liên quan trong nghiệp vụ. | 1 |
| 9 | created_at | timestamp | Không | CURRENT_TIMESTAMP | - | Thời điểm tạo bản ghi. | 2026-06-22 09:00:00 |

### 3. Khóa chính, khóa ngoại, index
- PK: id
- FK: partner_settlement_id -> partner_settlements.id (on delete: restrict)
- INDEX: partner_settlement_items_settlement_type_index (partner_settlement_id, item_type)

### 4. Quan hệ với bảng khác
- partner_settlement_items n-1 partner_settlements qua partner_settlement_id.

### 5. Ví dụ bản ghi
```json
{
    "id": 1,
    "partner_settlement_id": "10000000-0000-0000-0000-000000000001",
    "item_type": "owner_wallet_balance",
    "description": "Nội dung mẫu",
    "amount": 100000,
    "direction": "example",
    "reference_type": "default",
    "reference_id": 1
}
```

---

### MODULE: OWNER RESTRICTION

## Tên bảng: venue_access_restrictions

### 1. Mục đích bảng
Giới hạn hoặc chặn quyền owner trên cụm sân. Ví dụ: quá hạn phí nền tảng → limited (hạn chế một số chức năng), chấm dứt hợp đồng → blocked (chặn toàn bộ).

### 2. Danh sách trường

| STT | Tên trường | Kiểu dữ liệu | Null | Default | Khóa/Ràng buộc | Mô tả tác dụng trường | Ví dụ |
|---|---|---|---|---|---|---|---|
| 1 | id | char(36) | Không | - | PK | Khóa chính định danh duy nhất của bản ghi. | 10000000-0000-0000-0000-000000000001 |
| 2 | venue_cluster_id | char(36) | Không | - | FK | Khóa ngoại tham chiếu bảng `venue_clusters`, dùng để liên kết bản ghi với dữ liệu liên quan. | 10000000-0000-0000-0000-000000000001 |
| 3 | restriction_type | enum(platform_fee_overdue, contract_termination, admin_manual) | Không | - | - | Loại hoặc nhóm phân loại của bản ghi. | platform_fee_overdue |
| 4 | access_mode | enum(full, limited, transition, blocked) | Không | limited | - | Trường dữ liệu phục vụ nghiệp vụ của bảng `venue_access_restrictions`. | example |
| 5 | reason | text | Không | - | - | Lý do/ghi chú giải thích cho trạng thái hoặc quyết định xử lý. | Nội dung mẫu |
| 6 | starts_at | timestamp | Không | - | - | Thời điểm xảy ra sự kiện `starts_at`. | 2026-06-22 09:00:00 |
| 7 | ends_at | timestamp | Có | - | - | Thời điểm xảy ra sự kiện `ends_at`. | 2026-06-22 09:00:00 |
| 8 | created_by | char(36) | Có | - | FK | Khóa ngoại tham chiếu bảng `users`, dùng để liên kết bản ghi với dữ liệu liên quan. | 10000000-0000-0000-0000-000000000001 |
| 9 | status | enum(active, expired, cancelled) | Không | active | - | Trạng thái xử lý hoặc vòng đời của bản ghi. | active |
| 10 | created_at | timestamp | Có | - | - | Thời điểm tạo bản ghi. | 2026-06-22 09:00:00 |
| 11 | updated_at | timestamp | Có | - | - | Thời điểm cập nhật bản ghi gần nhất. | 2026-06-22 09:00:00 |

### 3. Khóa chính, khóa ngoại, index
- PK: id
- FK: venue_cluster_id -> venue_clusters.id (on delete: restrict)
- FK: created_by -> users.id (on delete: set null)
- INDEX: venue_access_restrictions_cluster_status_index (venue_cluster_id, status)
- INDEX: venue_access_restrictions_type_mode_index (restriction_type, access_mode)

### 4. Quan hệ với bảng khác
- venue_access_restrictions n-1 venue_clusters qua venue_cluster_id.
- venue_access_restrictions n-1 users qua created_by.

### 5. Ví dụ bản ghi
```json
{
    "id": "10000000-0000-0000-0000-000000000001",
    "venue_cluster_id": "10000000-0000-0000-0000-000000000001",
    "restriction_type": "platform_fee_overdue",
    "access_mode": "example",
    "reason": "Nội dung mẫu",
    "starts_at": "2026-06-22 09:00:00",
    "ends_at": "2026-06-22 09:00:00",
    "created_by": "10000000-0000-0000-0000-000000000001"
}
```

---

### MODULE: VENUE

## Tên bảng: venue_cluster_amenities

### 1. Mục đích bảng
Bảng trung gian liên kết cụm sân với tiện ích đang hiển thị cho khách.

### 2. Danh sách trường

| STT | Tên trường | Kiểu dữ liệu | Null | Default | Khóa/Ràng buộc | Mô tả tác dụng trường | Ví dụ |
|---|---|---|---|---|---|---|---|
| 1 | id | unsignedBigInteger | Không | - | PK | Khóa chính định danh duy nhất của bản ghi. | 1 |
| 2 | venue_cluster_id | char(36) | Không | - | FK, INDEX | Khóa ngoại tham chiếu bảng `venue_clusters`, dùng để liên kết bản ghi với dữ liệu liên quan. | 10000000-0000-0000-0000-000000000001 |
| 3 | amenity_id | unsignedBigInteger | Không | - | FK, INDEX | Khóa ngoại tham chiếu bảng `amenities`, dùng để liên kết bản ghi với dữ liệu liên quan. | 1 |
| 4 | description | text | Có | - | - | Mô tả hoặc ghi chú bổ sung cho bản ghi. | Nội dung mẫu |
| 5 | is_visible | boolean | Không | true | - | Cờ bật/tắt cho hành vi hoặc trạng thái tương ứng. | true |
| 6 | created_at | timestamp | Có | - | - | Thời điểm tạo bản ghi. | 2026-06-22 09:00:00 |
| 7 | updated_at | timestamp | Có | - | - | Thời điểm cập nhật bản ghi gần nhất. | 2026-06-22 09:00:00 |

### 3. Khóa chính, khóa ngoại, index
- PK: id
- FK: venue_cluster_id -> venue_clusters.id (on delete: cascade)
- FK: amenity_id -> amenities.id (on delete: restrict)
- UNIQUE: venue_cluster_amenities_cluster_amenity_unique (venue_cluster_id, amenity_id)
- INDEX: venue_cluster_amenities_venue_cluster_id_index (venue_cluster_id)
- INDEX: venue_cluster_amenities_amenity_id_index (amenity_id)

### 4. Quan hệ với bảng khác
- venue_cluster_amenities n-1 venue_clusters qua venue_cluster_id.
- venue_cluster_amenities n-1 amenities qua amenity_id.

### 5. Ví dụ bản ghi
```json
{
    "id": 1,
    "venue_cluster_id": "10000000-0000-0000-0000-000000000001",
    "amenity_id": 1,
    "description": "Nội dung mẫu",
    "is_visible": true,
    "created_at": "2026-06-22 09:00:00",
    "updated_at": "2026-06-22 09:00:00"
}
```

---

### MODULE: CONTRACT

## Tên bảng: contract_templates

### 1. Mục đích bảng
Lưu file template hợp đồng đối tác, trạng thái hoạt động và mô tả dùng khi sinh hợp đồng.

### 2. Danh sách trường

| STT | Tên trường | Kiểu dữ liệu | Null | Default | Khóa/Ràng buộc | Mô tả tác dụng trường | Ví dụ |
|---|---|---|---|---|---|---|---|
| 1 | id | unsignedBigInteger | Không | - | PK | Khóa chính định danh duy nhất của bản ghi. | 1 |
| 2 | name | string(255) | Không | - | - | Tên/tiêu đề hiển thị hoặc dùng trong quản trị. | Ví dụ SportGo |
| 3 | type | string(255) | Không | partner_contract | - | Loại hoặc nhóm phân loại của bản ghi. | default |
| 4 | file_path | string(255) | Không | - | - | Đường dẫn hoặc URL tới tài nguyên liên quan. | /storage/example.pdf |
| 5 | is_active | boolean | Không | true | - | Cờ xác định bản ghi đang hoạt động hay bị tắt. | true |
| 6 | description | text | Có | - | - | Mô tả hoặc ghi chú bổ sung cho bản ghi. | Nội dung mẫu |
| 7 | created_at | timestamp | Có | - | - | Thời điểm tạo bản ghi. | 2026-06-22 09:00:00 |
| 8 | updated_at | timestamp | Có | - | - | Thời điểm cập nhật bản ghi gần nhất. | 2026-06-22 09:00:00 |
| 9 | deleted_at | timestamp | Có | - | - | Thời điểm xóa mềm; null nghĩa là bản ghi còn hiệu lực. | 2026-06-22 09:00:00 |

### 3. Khóa chính, khóa ngoại, index
- PK: id

### 4. Quan hệ với bảng khác
- Không có khóa ngoại trực tiếp trong migration.

### 5. Ví dụ bản ghi
```json
{
    "id": 1,
    "name": "Ví dụ SportGo",
    "type": "default",
    "file_path": "/storage/example.pdf",
    "is_active": true,
    "description": "Nội dung mẫu",
    "created_at": "2026-06-22 09:00:00",
    "updated_at": "2026-06-22 09:00:00"
}
```

---

### MODULE: PARTNER

## Tên bảng: partner_documents

### 1. Mục đích bảng
Lưu tài liệu/file đối tác tải lên trong quá trình đăng ký và quản lý hồ sơ.

### 2. Danh sách trường

| STT | Tên trường | Kiểu dữ liệu | Null | Default | Khóa/Ràng buộc | Mô tả tác dụng trường | Ví dụ |
|---|---|---|---|---|---|---|---|
| 1 | id | unsignedBigInteger | Không | - | PK | Khóa chính định danh duy nhất của bản ghi. | 1 |
| 2 | partner_application_id | char(36) | Không | - | FK | Khóa ngoại tham chiếu bảng `partner_applications`, dùng để liên kết bản ghi với dữ liệu liên quan. | 10000000-0000-0000-0000-000000000001 |
| 3 | type | string(255) | Không | - | - | Loại hoặc nhóm phân loại của bản ghi. | default |
| 4 | file_path | string(255) | Không | - | - | Đường dẫn hoặc URL tới tài nguyên liên quan. | /storage/example.pdf |
| 5 | file_name | string(255) | Có | - | - | Đường dẫn hoặc URL tới tài nguyên liên quan. | Ví dụ SportGo |
| 6 | created_at | timestamp | Có | - | - | Thời điểm tạo bản ghi. | 2026-06-22 09:00:00 |
| 7 | updated_at | timestamp | Có | - | - | Thời điểm cập nhật bản ghi gần nhất. | 2026-06-22 09:00:00 |
| 8 | deleted_at | timestamp | Có | - | - | Thời điểm xóa mềm; null nghĩa là bản ghi còn hiệu lực. | 2026-06-22 09:00:00 |

### 3. Khóa chính, khóa ngoại, index
- PK: id
- FK: partner_application_id -> partner_applications.id (on delete: cascade)

### 4. Quan hệ với bảng khác
- partner_documents n-1 partner_applications qua partner_application_id.

### 5. Ví dụ bản ghi
```json
{
    "id": 1,
    "partner_application_id": "10000000-0000-0000-0000-000000000001",
    "type": "default",
    "file_path": "/storage/example.pdf",
    "file_name": "Ví dụ SportGo",
    "created_at": "2026-06-22 09:00:00",
    "updated_at": "2026-06-22 09:00:00",
    "deleted_at": "2026-06-22 09:00:00"
}
```

---

### MODULE: CONTRACT

## Tên bảng: contract_signatures

### 1. Mục đích bảng
Ghi nhận người ký, vai trò ký và thông tin phiên ký của hợp đồng đối tác.

### 2. Danh sách trường

| STT | Tên trường | Kiểu dữ liệu | Null | Default | Khóa/Ràng buộc | Mô tả tác dụng trường | Ví dụ |
|---|---|---|---|---|---|---|---|
| 1 | id | unsignedBigInteger | Không | - | PK | Khóa chính định danh duy nhất của bản ghi. | 1 |
| 2 | partner_contract_id | char(36) | Không | - | FK | Khóa ngoại tham chiếu bảng `partner_contracts`, dùng để liên kết bản ghi với dữ liệu liên quan. | 10000000-0000-0000-0000-000000000001 |
| 3 | user_id | char(36) | Không | - | FK | Khóa ngoại tham chiếu bảng `users`, dùng để liên kết bản ghi với dữ liệu liên quan. | 10000000-0000-0000-0000-000000000001 |
| 4 | sign_role | string(255) | Không | - | - | Trường dữ liệu phục vụ nghiệp vụ của bảng `contract_signatures`. | example |
| 5 | ip_address | string(255) | Có | - | - | Địa chỉ IP tại thời điểm thực hiện thao tác. | example |
| 6 | user_agent | string(255) | Có | - | - | Thông tin trình duyệt/thiết bị tại thời điểm thao tác. | example |
| 7 | signed_at | timestamp | Không | - | - | Thời điểm xảy ra sự kiện `signed_at`. | 2026-06-22 09:00:00 |
| 8 | created_at | timestamp | Có | - | - | Thời điểm tạo bản ghi. | 2026-06-22 09:00:00 |
| 9 | updated_at | timestamp | Có | - | - | Thời điểm cập nhật bản ghi gần nhất. | 2026-06-22 09:00:00 |

### 3. Khóa chính, khóa ngoại, index
- PK: id
- FK: user_id -> users.id (on delete: cascade)
- FK: partner_contract_id -> partner_contracts.id (on delete: cascade)

### 4. Quan hệ với bảng khác
- contract_signatures n-1 users qua user_id.
- contract_signatures n-1 partner_contracts qua partner_contract_id.

### 5. Ví dụ bản ghi
```json
{
    "id": 1,
    "partner_contract_id": "10000000-0000-0000-0000-000000000001",
    "user_id": "10000000-0000-0000-0000-000000000001",
    "sign_role": "example",
    "ip_address": "example",
    "user_agent": "example",
    "signed_at": "2026-06-22 09:00:00",
    "created_at": "2026-06-22 09:00:00"
}
```

---

### MODULE: TERMINATION

## Tên bảng: partner_liquidations

### 1. Mục đích bảng
Lưu hồ sơ thanh lý hợp đồng khi chấm dứt hợp tác với đối tác.

### 2. Danh sách trường

| STT | Tên trường | Kiểu dữ liệu | Null | Default | Khóa/Ràng buộc | Mô tả tác dụng trường | Ví dụ |
|---|---|---|---|---|---|---|---|
| 1 | id | unsignedBigInteger | Không | - | PK | Khóa chính định danh duy nhất của bản ghi. | 1 |
| 2 | partner_contract_id | char(36) | Không | - | FK | Khóa ngoại tham chiếu bảng `partner_contracts`, dùng để liên kết bản ghi với dữ liệu liên quan. | 10000000-0000-0000-0000-000000000001 |
| 3 | termination_request_id | char(36) | Không | - | FK | Khóa ngoại tham chiếu bảng `partner_termination_requests`, dùng để liên kết bản ghi với dữ liệu liên quan. | 10000000-0000-0000-0000-000000000001 |
| 4 | file_path | string(255) | Không | - | - | Đường dẫn hoặc URL tới tài nguyên liên quan. | /storage/example.pdf |
| 5 | status | string(255) | Không | completed | - | Trạng thái xử lý hoặc vòng đời của bản ghi. | active |
| 6 | created_at | timestamp | Có | - | - | Thời điểm tạo bản ghi. | 2026-06-22 09:00:00 |
| 7 | updated_at | timestamp | Có | - | - | Thời điểm cập nhật bản ghi gần nhất. | 2026-06-22 09:00:00 |

### 3. Khóa chính, khóa ngoại, index
- PK: id
- FK: partner_contract_id -> partner_contracts.id (on delete: cascade)
- FK: termination_request_id -> partner_termination_requests.id (on delete: cascade)

### 4. Quan hệ với bảng khác
- partner_liquidations n-1 partner_contracts qua partner_contract_id.
- partner_liquidations n-1 partner_termination_requests qua termination_request_id.

### 5. Ví dụ bản ghi
```json
{
    "id": 1,
    "partner_contract_id": "10000000-0000-0000-0000-000000000001",
    "termination_request_id": "10000000-0000-0000-0000-000000000001",
    "file_path": "/storage/example.pdf",
    "status": "active",
    "created_at": "2026-06-22 09:00:00",
    "updated_at": "2026-06-22 09:00:00"
}
```

---

### MODULE: PARTNER

## Tên bảng: partner_histories

### 1. Mục đích bảng
Ghi lại lịch sử thay đổi hồ sơ đối tác để phục vụ audit và theo dõi workflow.

### 2. Danh sách trường

| STT | Tên trường | Kiểu dữ liệu | Null | Default | Khóa/Ràng buộc | Mô tả tác dụng trường | Ví dụ |
|---|---|---|---|---|---|---|---|
| 1 | id | unsignedBigInteger | Không | - | PK | Khóa chính định danh duy nhất của bản ghi. | 1 |
| 2 | partner_application_id | char(36) | Không | - | FK | Khóa ngoại tham chiếu bảng `partner_applications`, dùng để liên kết bản ghi với dữ liệu liên quan. | 10000000-0000-0000-0000-000000000001 |
| 3 | action | string(255) | Không | - | - | Trường dữ liệu phục vụ nghiệp vụ của bảng `partner_histories`. | example |
| 4 | actor_id | char(36) | Có | - | FK | Khóa ngoại tham chiếu bảng `users`, dùng để liên kết bản ghi với dữ liệu liên quan. | 10000000-0000-0000-0000-000000000001 |
| 5 | old_values | json | Có | - | - | Dữ liệu có cấu trúc hoặc nội dung dài phục vụ xử lý nghiệp vụ. | {"key":"value"} |
| 6 | new_values | json | Có | - | - | Dữ liệu có cấu trúc hoặc nội dung dài phục vụ xử lý nghiệp vụ. | {"key":"value"} |
| 7 | ip_address | string(255) | Có | - | - | Địa chỉ IP tại thời điểm thực hiện thao tác. | example |
| 8 | user_agent | string(255) | Có | - | - | Thông tin trình duyệt/thiết bị tại thời điểm thao tác. | example |
| 9 | created_at | timestamp | Có | - | - | Thời điểm tạo bản ghi. | 2026-06-22 09:00:00 |
| 10 | updated_at | timestamp | Có | - | - | Thời điểm cập nhật bản ghi gần nhất. | 2026-06-22 09:00:00 |

### 3. Khóa chính, khóa ngoại, index
- PK: id
- FK: partner_application_id -> partner_applications.id (on delete: cascade)
- FK: actor_id -> users.id (on delete: set null)

### 4. Quan hệ với bảng khác
- partner_histories n-1 partner_applications qua partner_application_id.
- partner_histories n-1 users qua actor_id.

### 5. Ví dụ bản ghi
```json
{
    "id": 1,
    "partner_application_id": "10000000-0000-0000-0000-000000000001",
    "action": "example",
    "actor_id": "10000000-0000-0000-0000-000000000001",
    "old_values": {
        "key": "value"
    },
    "new_values": {
        "key": "value"
    },
    "ip_address": "example",
    "user_agent": "example"
}
```

---

### MODULE: VENUE

## Tên bảng: venue_location_change_requests

### 1. Mục đích bảng
Lưu yêu cầu chủ sân gửi để thay đổi địa chỉ, khu vực và map URL của cụm sân.

### 2. Danh sách trường

| STT | Tên trường | Kiểu dữ liệu | Null | Default | Khóa/Ràng buộc | Mô tả tác dụng trường | Ví dụ |
|---|---|---|---|---|---|---|---|
| 1 | id | char(36) | Không | - | PK | Khóa chính định danh duy nhất của bản ghi. | 10000000-0000-0000-0000-000000000001 |
| 2 | venue_cluster_id | char(36) | Không | - | FK, INDEX | Khóa ngoại tham chiếu bảng `venue_clusters`, dùng để liên kết bản ghi với dữ liệu liên quan. | 10000000-0000-0000-0000-000000000001 |
| 3 | requested_by | char(36) | Không | - | FK | Khóa ngoại tham chiếu bảng `users`, dùng để liên kết bản ghi với dữ liệu liên quan. | 10000000-0000-0000-0000-000000000001 |
| 4 | reviewed_by | char(36) | Có | - | FK | Khóa ngoại tham chiếu bảng `users`, dùng để liên kết bản ghi với dữ liệu liên quan. | 10000000-0000-0000-0000-000000000001 |
| 5 | status | enum(pending, approved, rejected, cancelled) | Không | pending | INDEX | Trạng thái xử lý hoặc vòng đời của bản ghi. | pending |
| 6 | note | text | Có | - | - | Mô tả hoặc ghi chú bổ sung cho bản ghi. | Nội dung mẫu |
| 7 | status_reason | text | Có | - | - | Lý do/ghi chú giải thích cho trạng thái hoặc quyết định xử lý. | active |
| 8 | new_address | string(255) | Có | - | - | Trường dữ liệu phục vụ nghiệp vụ của bảng `venue_location_change_requests`. | example |
| 9 | new_province | string(255) | Có | - | - | Trường dữ liệu phục vụ nghiệp vụ của bảng `venue_location_change_requests`. | example |
| 10 | new_ward | string(255) | Có | - | - | Trường dữ liệu phục vụ nghiệp vụ của bảng `venue_location_change_requests`. | example |
| 11 | new_latitude | decimal(10,7) | Có | - | - | Trường dữ liệu phục vụ nghiệp vụ của bảng `venue_location_change_requests`. | 1 |
| 12 | new_longitude | decimal(10,7) | Có | - | - | Trường dữ liệu phục vụ nghiệp vụ của bảng `venue_location_change_requests`. | 1 |
| 13 | new_map_url | string(2000) | Có | - | - | Đường dẫn hoặc URL tới tài nguyên liên quan. | https://sportgo.vn/example |
| 14 | reviewed_at | timestamp | Có | - | - | Thời điểm xảy ra sự kiện `reviewed_at`. | 2026-06-22 09:00:00 |
| 15 | created_at | timestamp | Có | - | - | Thời điểm tạo bản ghi. | 2026-06-22 09:00:00 |
| 16 | updated_at | timestamp | Có | - | - | Thời điểm cập nhật bản ghi gần nhất. | 2026-06-22 09:00:00 |

### 3. Khóa chính, khóa ngoại, index
- PK: id
- FK: venue_cluster_id -> venue_clusters.id (on delete: cascade)
- FK: requested_by -> users.id (on delete: cascade)
- FK: reviewed_by -> users.id (on delete: set null)
- INDEX: venue_location_change_requests_status_index (status)
- INDEX: venue_location_change_requests_cluster_index (venue_cluster_id)

### 4. Quan hệ với bảng khác
- venue_location_change_requests n-1 venue_clusters qua venue_cluster_id.
- venue_location_change_requests n-1 users qua requested_by.
- venue_location_change_requests n-1 users qua reviewed_by.

### 5. Ví dụ bản ghi
```json
{
    "id": "10000000-0000-0000-0000-000000000001",
    "venue_cluster_id": "10000000-0000-0000-0000-000000000001",
    "requested_by": "10000000-0000-0000-0000-000000000001",
    "reviewed_by": "10000000-0000-0000-0000-000000000001",
    "status": "pending",
    "note": "Nội dung mẫu",
    "status_reason": "active",
    "new_address": "example"
}
```

---

### MODULE: MODERATION

## Tên bảng: violation_types

### 1. Mục đích bảng
Danh mục nhóm vi phạm nội dung/hành vi và điểm mặc định dùng trong kiểm duyệt.

### 2. Danh sách trường

| STT | Tên trường | Kiểu dữ liệu | Null | Default | Khóa/Ràng buộc | Mô tả tác dụng trường | Ví dụ |
|---|---|---|---|---|---|---|---|
| 1 | id | unsignedBigInteger | Không | - | PK | Khóa chính định danh duy nhất của bản ghi. | 1 |
| 2 | code | string(50) | Không | - | UNIQUE | Mã loại vi phạm dùng trong báo cáo và xử lý tự động. | CODE-001 |
| 3 | name | string(100) | Không | - | - | Tên loại vi phạm hiển thị cho admin/user. | Ví dụ SportGo |
| 4 | base_score | unsignedTinyInteger | Không | 1 | - | Trường dữ liệu phục vụ nghiệp vụ của bảng `violation_types`. | 1 |
| 5 | is_immediate | boolean | Không | false | - | Cờ bật/tắt cho hành vi hoặc trạng thái tương ứng. | true |
| 6 | is_active | boolean | Không | true | INDEX | Cờ xác định bản ghi đang hoạt động hay bị tắt. | true |
| 7 | created_at | timestamp | Có | - | - | Thời điểm tạo bản ghi. | 2026-06-22 09:00:00 |
| 8 | updated_at | timestamp | Có | - | - | Thời điểm cập nhật bản ghi gần nhất. | 2026-06-22 09:00:00 |

### 3. Khóa chính, khóa ngoại, index
- PK: id
- UNIQUE: violation_types_code_unique (code)
- INDEX: violation_types_is_active_index (is_active)

### 4. Quan hệ với bảng khác
- Không có khóa ngoại trực tiếp trong migration.

### 5. Ví dụ bản ghi
```json
{
    "id": 1,
    "code": "CODE-001",
    "name": "Ví dụ SportGo",
    "base_score": 1,
    "is_immediate": true,
    "is_active": true,
    "created_at": "2026-06-22 09:00:00",
    "updated_at": "2026-06-22 09:00:00"
}
```

---

## Tên bảng: severity_levels

### 1. Mục đích bảng
Danh mục cấp độ nghiêm trọng và khoảng điểm dùng để phân loại vi phạm.

### 2. Danh sách trường

| STT | Tên trường | Kiểu dữ liệu | Null | Default | Khóa/Ràng buộc | Mô tả tác dụng trường | Ví dụ |
|---|---|---|---|---|---|---|---|
| 1 | id | unsignedTinyInteger | Không | - | PK | Khóa chính định danh duy nhất của bản ghi. | 1 |
| 2 | code | string(20) | Không | - | UNIQUE | Mã cấp độ nghiêm trọng. | CODE-001 |
| 3 | name | string(100) | Không | - | - | Tên cấp độ hiển thị trong kiểm duyệt. | Ví dụ SportGo |
| 4 | multiplier | decimal(3,1) | Không | 1 | - | Trường dữ liệu phục vụ nghiệp vụ của bảng `severity_levels`. | 1 |
| 5 | sort_order | unsignedTinyInteger | Không | 0 | INDEX | Thứ tự sắp xếp khi hiển thị. | 1 |

### 3. Khóa chính, khóa ngoại, index
- PK: id
- UNIQUE: severity_levels_code_unique (code)
- INDEX: severity_levels_sort_order_index (sort_order)

### 4. Quan hệ với bảng khác
- Không có khóa ngoại trực tiếp trong migration.

### 5. Ví dụ bản ghi
```json
{
    "id": 1,
    "code": "CODE-001",
    "name": "Ví dụ SportGo",
    "multiplier": 1,
    "sort_order": 1
}
```

---

## Tên bảng: moderation_thresholds

### 1. Mục đích bảng
Cấu hình ngưỡng cảnh báo và hành động tự động theo chính sách kiểm duyệt.

### 2. Danh sách trường

| STT | Tên trường | Kiểu dữ liệu | Null | Default | Khóa/Ràng buộc | Mô tả tác dụng trường | Ví dụ |
|---|---|---|---|---|---|---|---|
| 1 | id | unsignedBigInteger | Không | - | PK | Khóa chính định danh duy nhất của bản ghi. | 1 |
| 2 | system_policy_id | char(36) | Không | - | FK | Chính sách hệ thống áp dụng bộ ngưỡng kiểm duyệt. | 10000000-0000-0000-0000-000000000001 |
| 3 | target_type | string(50) | Không | - | INDEX | Loại đối tượng áp dụng ngưỡng như user, post, comment. | default |
| 4 | created_at | timestamp | Có | - | - | Thời điểm tạo bản ghi. | 2026-06-22 09:00:00 |
| 5 | updated_at | timestamp | Có | - | - | Thời điểm cập nhật bản ghi gần nhất. | 2026-06-22 09:00:00 |
| 6 | warning_threshold | unsignedSmallInteger | Không | 3 | - | Ngưỡng cảnh báo | 1 |
| 7 | action_threshold | unsignedSmallInteger | Không | 5 | - | Ngưỡng thực hiện thao tác Ẩn/Khóa | 1 |
| 8 | unique_reporters_threshold | unsignedSmallInteger | Không | 2 | - | Ngưỡng số người báo cáo khác nhau | 1 |
| 9 | timeframe_days | unsignedSmallInteger | Không | 7 | - | Ngưỡng trong khoảng thời gian (ngày) | 1 |

### 3. Khóa chính, khóa ngoại, index
- PK: id
- FK: system_policy_id -> system_policies.id (on delete: cascade)
- UNIQUE: moderation_thresholds_policy_target_unique (system_policy_id, target_type)
- INDEX: moderation_thresholds_target_type_index (target_type)

### 4. Quan hệ với bảng khác
- moderation_thresholds n-1 system_policies qua system_policy_id.

### 5. Ví dụ bản ghi
```json
{
    "id": 1,
    "system_policy_id": "10000000-0000-0000-0000-000000000001",
    "target_type": "default",
    "created_at": "2026-06-22 09:00:00",
    "updated_at": "2026-06-22 09:00:00",
    "warning_threshold": 1,
    "action_threshold": 1,
    "unique_reporters_threshold": 1
}
```

---

## Tên bảng: violation_records

### 1. Mục đích bảng
Lưu từng lần ghi nhận vi phạm của đối tượng bị báo cáo hoặc bị hệ thống xử lý.

### 2. Danh sách trường

| STT | Tên trường | Kiểu dữ liệu | Null | Default | Khóa/Ràng buộc | Mô tả tác dụng trường | Ví dụ |
|---|---|---|---|---|---|---|---|
| 1 | id | char(36) | Không | - | PK | Khóa chính định danh duy nhất của bản ghi. | 10000000-0000-0000-0000-000000000001 |
| 2 | target_type | string(50) | Không | - | - | Loại đối tượng bị ghi nhận vi phạm. | default |
| 3 | target_id | char(36) | Không | - | - | ID đối tượng bị ghi nhận vi phạm. | 10000000-0000-0000-0000-000000000001 |
| 4 | violation_count | unsignedTinyInteger | Không | 1 | - | Số lượng/tổng số dùng cho thống kê hoặc giới hạn nghiệp vụ. | 1 |
| 5 | last_violation_at | timestamp | Có | - | - | Thời điểm xảy ra sự kiện `last_violation_at`. | 2026-06-22 09:00:00 |
| 6 | last_action_type | string(50) | Có | - | - | Loại hoặc nhóm phân loại của bản ghi. | default |
| 7 | last_action_expires_at | timestamp | Có | - | - | Thời điểm xảy ra sự kiện `last_action_expires_at`. | 2026-06-22 09:00:00 |
| 8 | created_at | timestamp | Có | - | - | Thời điểm tạo bản ghi. | 2026-06-22 09:00:00 |
| 9 | updated_at | timestamp | Có | - | - | Thời điểm cập nhật bản ghi gần nhất. | 2026-06-22 09:00:00 |

### 3. Khóa chính, khóa ngoại, index
- PK: id
- INDEX: violation_records_target_index (target_type, target_id)

### 4. Quan hệ với bảng khác
- Không có khóa ngoại trực tiếp trong migration.

### 5. Ví dụ bản ghi
```json
{
    "id": "10000000-0000-0000-0000-000000000001",
    "target_type": "default",
    "target_id": "10000000-0000-0000-0000-000000000001",
    "violation_count": 1,
    "last_violation_at": "2026-06-22 09:00:00",
    "last_action_type": "default",
    "last_action_expires_at": "2026-06-22 09:00:00",
    "created_at": "2026-06-22 09:00:00"
}
```

---

### MODULE: AUTH/RBAC

## Tên bảng: user_lock_logs

### 1. Mục đích bảng
Ghi lại lịch sử khóa/mở khóa tài khoản, người thực hiện và lý do.

### 2. Danh sách trường

| STT | Tên trường | Kiểu dữ liệu | Null | Default | Khóa/Ràng buộc | Mô tả tác dụng trường | Ví dụ |
|---|---|---|---|---|---|---|---|
| 1 | id | unsignedBigInteger | Không | - | PK | Khóa chính định danh duy nhất của bản ghi. | 1 |
| 2 | user_id | char(36) | Không | - | FK, INDEX | User bị khóa/mở khóa | 10000000-0000-0000-0000-000000000001 |
| 3 | action | enum(locked, unlocked) | Không | - | - | Hành động khóa hoặc mở khóa | example |
| 4 | reason | text | Có | - | - | Lý do khóa/mở khóa | Nội dung mẫu |
| 5 | locked_by | char(36) | Có | - | FK | Admin thực hiện, NULL nếu tự động | 10000000-0000-0000-0000-000000000001 |
| 6 | auto_triggered | boolean | Không | false | - | Khóa tự động hay thủ công | true |
| 7 | lock_until | timestamp | Có | - | - | Thời điểm hết khóa, NULL = vĩnh viễn | 2026-06-22 09:00:00 |
| 8 | policy_snapshot | json | Có | - | - | Snapshot policy tại thời điểm khóa tự động | {"key":"value"} |
| 9 | created_at | timestamp | Có | - | INDEX | Thời điểm tạo bản ghi. | 2026-06-22 09:00:00 |

### 3. Khóa chính, khóa ngoại, index
- PK: id
- FK: user_id -> users.id (on delete: cascade)
- FK: locked_by -> users.id (on delete: set null)
- INDEX: user_lock_logs_user_id_index (user_id)
- INDEX: user_lock_logs_created_at_index (created_at)

### 4. Quan hệ với bảng khác
- user_lock_logs n-1 users qua user_id.
- user_lock_logs n-1 users qua locked_by.

### 5. Ví dụ bản ghi
```json
{
    "id": 1,
    "user_id": "10000000-0000-0000-0000-000000000001",
    "action": "example",
    "reason": "Nội dung mẫu",
    "locked_by": "10000000-0000-0000-0000-000000000001",
    "auto_triggered": true,
    "lock_until": "2026-06-22 09:00:00",
    "policy_snapshot": {
        "key": "value"
    }
}
```

---
