# BĂ¡o CĂ¡o Thiáº¿t Káº¿ Database Dá»± Ăn SportGo

BĂ¡o cĂ¡o nĂ y Ä‘Æ°á»£c tá»± Ä‘á»™ng trĂ­ch xuáº¥t vĂ  tá»•ng há»£p tá»« cĂ¡c file migration hiá»‡n táº¡i cá»§a dá»± Ă¡n. KhĂ´ng bao gá»“m cĂ¡c giáº£ Ä‘á»‹nh ngoĂ i code.

==================================================
## PHáº¦N 1. Tá»”NG Há»¢P CĂC Báº¢NG
==================================================

| STT | TĂªn báº£ng | Module | TĂ¡c dá»¥ng chĂ­nh | MĂ´ táº£ | CĂ¡c liĂªn káº¿t chĂ­nh |
|---|---|---|---|---|---|
| 1 | users | Auth/RBAC | LÆ°u thĂ´ng tin ngÆ°á»i dĂ¹ng | LÆ°u tĂ i khoáº£n Ä‘Äƒng nháº­p, tráº¡ng thĂ¡i, vĂ  profile cÆ¡ báº£n | roles (user_roles), bookings (customer_id), audit_logs (actor_id) |
| 2 | roles | Auth/RBAC | LÆ°u cĂ¡c nhĂ³m quyá»n | LÆ°u mĂ£ role Ä‘á»ƒ phĂ¢n quyá»n (admin, venue_owner, customer...) | users (user_roles), permissions (role_permissions) |
| 3 | user_roles | Auth/RBAC | GĂ¡n role cho user | Cáº§u ná»‘i n-n giá»¯a users vĂ  roles, cĂ³ há»— trá»£ scope theo system/venue | users.id, roles.id |
| 4 | permissions | Auth/RBAC | LÆ°u danh sĂ¡ch quyá»n | LÆ°u cĂ¡c quyá»n chi tiáº¿t (vd: booking.manage) Ä‘á»ƒ check logic | roles (role_permissions) |
| 5 | role_permissions | Auth/RBAC | GĂ¡n quyá»n cho role | Cáº§u ná»‘i n-n giá»¯a roles vĂ  permissions | roles.id, permissions.id |
| 6 | user_permission_revokes | Auth/RBAC | Thu há»“i quyá»n cá»§a user | LÆ°u cĂ¡c quyá»n bá»‹ thu há»“i cá»¥ thá»ƒ cá»§a 1 user dĂ¹ role cĂ³ cáº¥p | users.id, permissions.id |
| 7 | personal_access_tokens | Auth/RBAC | LÆ°u token Ä‘Äƒng nháº­p | Báº£ng chuáº©n cá»§a Laravel Sanctum lÆ°u access token | users (morphs) |
| 8 | venue_clusters | Venue | LÆ°u cá»¥m sĂ¢n | LÆ°u thĂ´ng tin 1 cÆ¡ sá»Ÿ sĂ¢n bĂ£i (Ä‘á»‹a chá»‰, tá»a Ä‘á»™, chá»§ sĂ¢n) | users.owner_id, venue_courts.venue_cluster_id, bookings.venue_cluster_id |
| 9 | court_types | Venue | LÆ°u loáº¡i sĂ¢n thá»ƒ thao | Quáº£n lĂ½ loáº¡i mĂ´n/sĂ¢n (vd: sĂ¢n cáº§u lĂ´ng, sĂ¢n bĂ³ng Ä‘Ă¡ 7 ngÆ°á»i) | court_types.parent_id, venue_courts.court_type_id |
| 10 | venue_courts | Venue | LÆ°u sĂ¢n con thá»±c táº¿ | CĂ¡c sĂ¢n nhá» bĂªn trong 1 cá»¥m sĂ¢n Ä‘á»ƒ khĂ¡ch Ä‘áº·t | venue_clusters.id, court_types.id, bookings.venue_court_id |
| 11 | venue_staff_assignments | Venue | PhĂ¢n cĂ´ng nhĂ¢n viĂªn | PhĂ¢n cĂ´ng nhĂ¢n viĂªn quáº£n lĂ½ cá»¥m sĂ¢n hoáº·c loáº¡i sĂ¢n cá»¥ thá»ƒ | users.id, venue_clusters.id, court_types.id |
| 12 | venue_court_approval_requests | Venue | Xin duyá»‡t táº¡o sĂ¢n con | LÆ°u yĂªu cáº§u duyá»‡t táº¡o sĂ¢n con má»›i cá»§a chá»§ sĂ¢n | users.id, venue_clusters.id, court_types.id |
| 13 | favorite_venues | Venue | SĂ¢n yĂªu thĂ­ch | LÆ°u danh sĂ¡ch cá»¥m sĂ¢n yĂªu thĂ­ch cá»§a khĂ¡ch | users.id, venue_clusters.id |
| 14 | booking_configs | Booking | Cáº¥u hĂ¬nh Ä‘áº·t sĂ¢n | Cáº¥u hĂ¬nh quy Ä‘á»‹nh Ä‘áº·t sĂ¢n (thá»i gian tá»‘i thiá»ƒu, tiá»n cá»c) cho cá»¥m sĂ¢n | venue_clusters.id |
| 15 | bookings | Booking | ÄÆ¡n Ä‘áº·t sĂ¢n | Quáº£n lĂ½ lá»‹ch Ä‘áº·t sĂ¢n, giá» chÆ¡i, thanh toĂ¡n, tráº¡ng thĂ¡i | users.customer_id, venue_courts.id, venue_clusters.id |
| 16 | price_slots | Booking | Báº£ng giĂ¡ theo khung giá» | LÆ°u giĂ¡ tiá»n theo khung giá» cá»§a loáº¡i sĂ¢n trong cá»¥m | venue_clusters.id, court_types.id |
| 17 | holiday_prices | Booking | Báº£ng giĂ¡ ngĂ y lá»… | LÆ°u giĂ¡ Ä‘áº·c biá»‡t Ă¡p dá»¥ng cho ngĂ y lá»…/sá»± kiá»‡n | venue_clusters.id, court_types.id |
| 18 | slot_locks | Booking | KhĂ³a khung giá» | Giá»¯ chá»— hoáº·c khĂ³a khung giá» khĂ´ng cho Ä‘áº·t sĂ¢n | venue_courts.id, venue_clusters.id, bookings.id |
| 19 | payments | Payment | Thanh toĂ¡n | Quáº£n lĂ½ giao dá»‹ch thanh toĂ¡n cá»§a booking | bookings.id, system_bank_accounts.id |
| 20 | payment_logs | Payment | Log giao dá»‹ch | Log chi tiáº¿t request/response tá»« cá»•ng thanh toĂ¡n | payments.id |
| 21 | refunds | Payment | HoĂ n tiá»n | Quáº£n lĂ½ yĂªu cáº§u hoĂ n tiá»n cho thanh toĂ¡n bá»‹ há»§y | payments.id, users.processed_by |
| 22 | system_bank_accounts | Payment | TĂ i khoáº£n ngĂ¢n hĂ ng | LÆ°u thĂ´ng tin TKNH há»‡ thá»‘ng dĂ¹ng Ä‘á»ƒ nháº­n thanh toĂ¡n | payments.system_bank_account_id |
| 23 | owner_wallets | Payment | VĂ­ chá»§ sĂ¢n | Quáº£n lĂ½ sá»‘ dÆ°, tiá»n thu há»™ cá»§a chá»§ sĂ¢n | users.owner_id |
| 24 | owner_wallet_ledgers | Payment | Sá»• quá»¹ vĂ­ chá»§ sĂ¢n | Ghi nháº­n biáº¿n Ä‘á»™ng sá»‘ dÆ° chi tiáº¿t cá»§a vĂ­ chá»§ sĂ¢n | owner_wallets.id, users.id, bookings.id |
| 25 | platform_fee_tiers | Payment | Báº­c phĂ­ ná»n táº£ng | Quáº£n lĂ½ cĂ¡c gĂ³i thu phĂ­ ná»n táº£ng Ă¡p dá»¥ng cho chá»§ sĂ¢n | venue_platform_fee_ledgers.tier_id |
| 26 | venue_platform_fee_ledgers | Payment | CĂ´ng ná»£ phĂ­ ná»n táº£ng | Quáº£n lĂ½ lá»‹ch sá»­ vĂ  tráº¡ng thĂ¡i Ä‘Ă³ng phĂ­ ná»n táº£ng cá»§a cá»¥m sĂ¢n | venue_clusters.id, platform_fee_tiers.id |
| 27 | community_posts | Community | BĂ i Ä‘Äƒng cá»™ng Ä‘á»“ng | NgÆ°á»i chÆ¡i Ä‘Äƒng bĂ i tháº£o luáº­n tá»± do | users.author_id |
| 28 | community_post_comments | Community | BĂ¬nh luáº­n cá»™ng Ä‘á»“ng | BĂ¬nh luáº­n trong cĂ¡c bĂ i Ä‘Äƒng cá»™ng Ä‘á»“ng | community_posts.id, users.user_id |
| 29 | community_post_likes | Community | ThĂ­ch bĂ i viáº¿t | LÆ°á»£t thĂ­ch bĂ i Ä‘Äƒng cá»™ng Ä‘á»“ng | community_posts.id, users.user_id |
| 30 | venue_posts | Community | BĂ i Ä‘Äƒng chá»§ sĂ¢n | Chá»§ sĂ¢n Ä‘Äƒng bĂ i quáº£ng bĂ¡, thĂ´ng bĂ¡o | venue_clusters.id, users.author_id |
| 31 | player_posts | Community | BĂ i tĂ¬m Ä‘á»‘i thá»§/Ä‘á»™i | KhĂ¡ch tĂ¬m kĂ¨o chÆ¡i chung, chia sáº» chi phĂ­ | bookings.id, users.author_id |
| 32 | player_post_participants | Community | Xin tham gia kĂ¨o | KhĂ¡ch xin tham gia vĂ o bĂ i tĂ¬m Ä‘á»‘i/Ä‘á»™i | player_posts.id, users.user_id |
| 33 | hashtags | Community | Hashtag chung | LÆ°u cĂ¡c hashtag | post_hashtags.hashtag_id |
| 34 | post_hashtags | Community | Gáº¯n hashtag vĂ o bĂ i | LiĂªn káº¿t hashtag vá»›i cĂ¡c loáº¡i bĂ i viáº¿t | hashtags.id (logical vá»›i bĂ i) |
| 35 | system_posts | Community | BĂ i viáº¿t há»‡ thá»‘ng | Admin Ä‘Äƒng thĂ´ng bĂ¡o, tin tá»©c há»‡ thá»‘ng | users.author_id |
| 36 | player_preferences | Player | Há»“ sÆ¡ ngÆ°á»i chÆ¡i | LÆ°u thĂ´ng tin Ä‘Ă¡nh giĂ¡ trung bĂ¬nh cá»§a ngÆ°á»i chÆ¡i | users.user_id |
| 37 | player_preferred_court_types | Player | MĂ´n thá»ƒ thao yĂªu thĂ­ch | NgÆ°á»i chÆ¡i chá»n loáº¡i mĂ´n thá»ƒ thao quan tĂ¢m | users.user_id, court_types.id |
| 38 | player_ratings | Player | ÄĂ¡nh giĂ¡ ngÆ°á»i chÆ¡i | ÄĂ¡nh giĂ¡ trĂ¬nh Ä‘á»™/thĂ¡i Ä‘á»™ giá»¯a nhá»¯ng ngÆ°á»i chÆ¡i vá»›i nhau | users.rater_id, users.rated_user_id, player_posts.id |
| 39 | conversations | Chat | Cuá»™c há»™i thoáº¡i | Quáº£n lĂ½ phĂ²ng chat (direct, post, venue) | users.created_by |
| 40 | conversation_participants | Chat | ThĂ nh viĂªn chat | ThĂ nh viĂªn tham gia vĂ o cuá»™c há»™i thoáº¡i | conversations.id, users.user_id |
| 41 | messages | Chat | Tin nháº¯n | Ná»™i dung tin nháº¯n trong há»™i thoáº¡i | conversations.id, users.sender_id |
| 42 | banners | System | Quáº£n lĂ½ banner | Banner quáº£ng cĂ¡o, hiá»ƒn thá»‹ trang chá»§ | users.created_by |
| 43 | media | System | Quáº£n lĂ½ file Ä‘Ă­nh kĂ¨m | Quáº£n lĂ½ táº­p tin, hĂ¬nh áº£nh Ä‘a phÆ°Æ¡ng tiá»‡n (polymorphic) | LiĂªn káº¿t logic Ä‘a hĂ¬nh |
| 44 | system_policies | System | ChĂ­nh sĂ¡ch há»‡ thá»‘ng | Äiá»u khoáº£n, chĂ­nh sĂ¡ch (báº£o máº­t, hoĂ n tiá»n, v.v.) | users.created_by |
| 45 | user_policy_acceptances | System | Cháº¥p nháº­n chĂ­nh sĂ¡ch | Ghi nháº­n user Ä‘Ă£ Ä‘á»“ng Ă½ phiĂªn báº£n chĂ­nh sĂ¡ch | users.id, system_policies.id |
| 46 | moderation_configs | System | Cáº¥u hĂ¬nh kiá»ƒm duyá»‡t | Cáº¥u hĂ¬nh há»‡ thá»‘ng (key-value) | users.updated_by |
| 47 | verification_codes | System | MĂ£ xĂ¡c thá»±c | OTP dĂ¹ng cho email/sms Ä‘Äƒng kĂ½, quĂªn máº­t kháº©u | users.user_id |
| 48 | partner_applications | System | ÄÆ¡n Ä‘Äƒng kĂ½ Ä‘á»‘i tĂ¡c | ÄÆ¡n xin lĂ m chá»§ sĂ¢n gá»­i cho admin duyá»‡t | users.user_id |
| 49 | partner_application_courts | System | MĂ´n thá»ƒ thao Ä‘Äƒng kĂ½ | Loáº¡i sĂ¢n kinh doanh dá»± kiáº¿n cá»§a Ä‘Æ¡n Ä‘Äƒng kĂ½ Ä‘á»‘i tĂ¡c | partner_applications.id, court_types.id |
| 50 | audit_logs | System/Log | Lá»‹ch sá»­ thao tĂ¡c | Ghi nháº­n hĂ nh Ä‘á»™ng nháº¡y cáº£m trong há»‡ thá»‘ng | users.actor_id |
| 51 | reports | System/Report| BĂ¡o cĂ¡o vi pháº¡m | Quáº£n lĂ½ bĂ¡o cĂ¡o xáº¥u, spam | users.reporter_id |
| 52 | complaints | System/Report| Khiáº¿u náº¡i | Khiáº¿u náº¡i vá» sĂ¢n bĂ£i, dá»‹ch vá»¥ hoáº·c booking | users.customer_id, bookings.id |
| 53 | reviews | System/Report| ÄĂ¡nh giĂ¡ cá»¥m sĂ¢n | KhĂ¡ch Ä‘Ă¡nh giĂ¡ sau khi hoĂ n thĂ nh booking | bookings.id, venue_clusters.id |
| 54 | notifications | System | ThĂ´ng bĂ¡o | LÆ°u thĂ´ng bĂ¡o gá»­i cho user | users.user_id |
| 55 | password_reset_tokens | Laravel | Äáº·t láº¡i máº­t kháº©u | Báº£ng chuáº©n cá»§a Laravel cho reset password (email token) | KhĂ´ng FK |
| 56 | sessions | Laravel | LÆ°u session | Báº£ng quáº£n lĂ½ session user Ä‘Äƒng nháº­p | users.id |
| 57 | cache | Laravel | LÆ°u cache | Báº£ng cache database driver cá»§a Laravel | KhĂ´ng FK |
| 58 | cache_locks | Laravel | KhĂ³a cache | Quáº£n lĂ½ lock cá»§a cache | KhĂ´ng FK |
| 59 | jobs | Laravel | HĂ ng Ä‘á»£i cĂ´ng viá»‡c | Quáº£n lĂ½ background jobs (Queue) | KhĂ´ng FK |
| 60 | job_batches | Laravel | LĂ´ cĂ´ng viá»‡c | Quáº£n lĂ½ batch jobs | KhĂ´ng FK |
| 61 | failed_jobs | Laravel | Job tháº¥t báº¡i | LÆ°u cĂ¡c queue job cháº¡y lá»—i | KhĂ´ng FK |

==================================================
## PHáº¦N 2. CHI TIáº¾T CĂC Báº¢NG
==================================================

### MODULE: AUTH/RBAC

## TĂªn báº£ng: users

### 1. Má»¥c Ä‘Ă­ch báº£ng
LÆ°u trá»¯ thĂ´ng tin tĂ i khoáº£n ngÆ°á»i dĂ¹ng, phá»¥c vá»¥ Ä‘Äƒng nháº­p, quáº£n lĂ½ há»“ sÆ¡ vĂ  lĂ  thá»±c thá»ƒ cá»‘t lĂµi cho má»i nghiá»‡p vá»¥ nhÆ° booking, chat, role.

### 2. Danh sĂ¡ch trÆ°á»ng

| STT | TĂªn trÆ°á»ng | Kiá»ƒu dá»¯ liá»‡u | Null | Default | KhĂ³a/RĂ ng buá»™c | MĂ´ táº£ tĂ¡c dá»¥ng trÆ°á»ng | VĂ­ dá»¥ |
|---|---|---|---|---|---|---|---|
| 1 | id | char(36) | KhĂ´ng | - | PK | UUID Ä‘á»‹nh danh user | 10000000-0000-0000-0000-000000000001 |
| 2 | username | varchar(50) | KhĂ´ng | - | Unique | TĂªn tĂ i khoáº£n dĂ¹ng Ä‘á»ƒ Ä‘Äƒng nháº­p | john_doe |
| 3 | full_name | varchar(255) | KhĂ´ng | - | - | Há» tĂªn hiá»ƒn thá»‹ | John Doe |
| 4 | phone | varchar(20) | CĂ³ | null | Unique | Sá»‘ Ä‘iá»‡n thoáº¡i chĂ­nh | 0901234567 |
| 5 | email | varchar(255) | CĂ³ | null | Unique | Email phá»¥ dĂ¹ng Ä‘Äƒng nháº­p/reset pass | john@example.com |
| 6 | google_id | varchar(255) | CĂ³ | null | Unique | ID Ä‘Äƒng nháº­p qua Google | 10101010101 |
| 7 | email_verified_at | timestamp | CĂ³ | null | - | Thá»i Ä‘iá»ƒm xĂ¡c thá»±c email | 2026-06-15 18:00:00 |
| 8 | phone_verified_at | timestamp | CĂ³ | null | - | Thá»i Ä‘iá»ƒm xĂ¡c thá»±c phone | null |
| 9 | password | varchar(255) | KhĂ´ng | - | - | Máº­t kháº©u Ä‘Ă£ hash | $2y$10$... |
| 10 | avatar_url | varchar(500) | CĂ³ | null | - | ÄÆ°á»ng dáº«n avatar hiá»‡n táº¡i | /storage/avatar.jpg |
| 11 | bio | text | CĂ³ | null | - | MĂ´ táº£ cĂ¡ nhĂ¢n do user tá»± nháº­p | YĂªu thá»ƒ thao, tĂ¬m kĂ¨o thá»© 7 |
| 12 | status | enum | KhĂ´ng | pending_verify | Index | Tráº¡ng thĂ¡i: pending_verify, active, locked, deactivated | active |
| 13 | verification_channel | enum | KhĂ´ng | email | - | KĂªnh nháº­n mĂ£ xĂ¡c thá»±c (email/sms) | email |
| 14 | lock_type | enum | CĂ³ | null | - | Kiá»ƒu khĂ³a (temporary, permanent, auto) | null |
| 15 | status_reason | text | CĂ³ | null | - | LĂ½ do khĂ³a/há»§y tĂ i khoáº£n | null |
| 16 | locked_at | timestamp | CĂ³ | null | - | Thá»i Ä‘iá»ƒm bá»‹ khĂ³a | null |
| 17 | locked_until | timestamp | CĂ³ | null | Index | Thá»i Ä‘iá»ƒm háº¿t khĂ³a táº¡m thá»i | null |
| 18 | locked_by | char(36) | CĂ³ | null | FK | Admin thá»±c hiá»‡n khĂ³a tĂ i khoáº£n | null |
| 19 | remember_token | varchar(100) | CĂ³ | null | - | Token remember me Laravel | abcxyz... |

### 3. KhĂ³a chĂ­nh, khĂ³a ngoáº¡i, index
- PK: id
- Unique: username, phone, email, google_id
- FK: locked_by -> users.id (on delete set null)
- Index: status, locked_until

### 4. Quan há»‡ vá»›i báº£ng khĂ¡c
- users 1-n users qua locked_by
- users n-n roles qua user_roles
- users 1-n bookings qua bookings.customer_id
- Tham gia vĂ o háº§u háº¿t cĂ¡c báº£ng khĂ¡c trong há»‡ thá»‘ng qua khĂ³a ngoáº¡i.

### 5. VĂ­ dá»¥ báº£n ghi
```json
{
  "id": "10000000-0000-0000-0000-000000000001",
  "username": "admin123",
  "full_name": "Nguyá»…n VÄƒn Admin",
  "email": "admin@sportgo.vn",
  "status": "active"
}
```

## TĂªn báº£ng: roles

### 1. Má»¥c Ä‘Ă­ch báº£ng
LÆ°u trá»¯ danh má»¥c cĂ¡c nhĂ³m quyá»n (vai trĂ²) dĂ¹ng Ä‘á»ƒ phĂ¢n quyá»n (RBAC) cho users.

### 2. Danh sĂ¡ch trÆ°á»ng

| STT | TĂªn trÆ°á»ng | Kiá»ƒu dá»¯ liá»‡u | Null | Default | KhĂ³a/RĂ ng buá»™c | MĂ´ táº£ tĂ¡c dá»¥ng trÆ°á»ng | VĂ­ dá»¥ |
|---|---|---|---|---|---|---|---|
| 1 | id | bigint | KhĂ´ng | auto | PK | ID tá»± tÄƒng | 1 |
| 2 | name | varchar(50) | KhĂ´ng | - | Unique | MĂ£ role duy nháº¥t dĂ¹ng trong code | venue_owner |
| 3 | display_name | varchar(100) | KhĂ´ng | - | - | TĂªn role dá»… Ä‘á»c hiá»ƒn thá»‹ UI | Chá»§ sĂ¢n |
| 4 | description | text | CĂ³ | null | - | MĂ´ táº£ quyá»n háº¡n cá»§a role | Quáº£n lĂ½ cá»¥m sĂ¢n cá»§a mĂ¬nh |
| 5 | is_system | boolean | KhĂ´ng | false | Index | LĂ  role há»‡ thá»‘ng máº·c Ä‘á»‹nh, khĂ´ng xĂ³a Ä‘Æ°á»£c | 1 |

### 3. KhĂ³a chĂ­nh, khĂ³a ngoáº¡i, index
- PK: id
- Unique: name
- Index: is_system

### 4. Quan há»‡ vá»›i báº£ng khĂ¡c
- roles n-n users qua user_roles
- roles n-n permissions qua role_permissions

### 5. VĂ­ dá»¥ báº£n ghi
```json
{
  "id": 1,
  "name": "venue_owner",
  "display_name": "Chá»§ sĂ¢n",
  "is_system": true
}
```

## TĂªn báº£ng: user_roles

### 1. Má»¥c Ä‘Ă­ch báº£ng
Báº£ng trung gian n-n káº¿t ná»‘i users vĂ  roles. Äáº·c biá»‡t há»— trá»£ phĂ¢n quyá»n theo pháº¡m vi (scope) Ä‘á»ƒ 1 user cĂ³ thá»ƒ lĂ m chá»§ sĂ¢n A nhÆ°ng khĂ´ng cĂ³ quyá»n á»Ÿ sĂ¢n B.

### 2. Danh sĂ¡ch trÆ°á»ng

| STT | TĂªn trÆ°á»ng | Kiá»ƒu dá»¯ liá»‡u | Null | Default | KhĂ³a/RĂ ng buá»™c | MĂ´ táº£ tĂ¡c dá»¥ng trÆ°á»ng | VĂ­ dá»¥ |
|---|---|---|---|---|---|---|---|
| 1 | id | bigint | KhĂ´ng | auto | PK | ID tá»± tÄƒng | 1 |
| 2 | user_id | char(36) | KhĂ´ng | - | FK | ID ngÆ°á»i dĂ¹ng | 10000000-... |
| 3 | role_id | bigint | KhĂ´ng | - | FK | ID role Ä‘Æ°á»£c gĂ¡n | 2 |
| 4 | scope_type | enum | KhĂ´ng | system | Index | Pháº¡m vi (system hoáº·c venue) | venue |
| 5 | scope_id | char(36) | KhĂ´ng | 0000... | Index | ID cá»§a cá»¥m sĂ¢n (náº¿u scope lĂ  venue) | aabbccdd-... |
| 6 | granted_by | char(36) | CĂ³ | null | FK | NgÆ°á»i gĂ¡n quyá»n | null |

### 3. KhĂ³a chĂ­nh, khĂ³a ngoáº¡i, index
- PK: id
- Unique: user_id, role_id, scope_type, scope_id (user_roles_scope_unique)
- FK: user_id -> users.id (cascade), role_id -> roles.id (cascade), granted_by -> users.id (set null)
- Index: scope_type, scope_id

### 4. Quan há»‡ vá»›i báº£ng khĂ¡c
- Cáº§u ná»‘i user vĂ  role. Logical reference tá»›i venue_clusters thĂ´ng qua scope_id.

### 5. VĂ­ dá»¥ báº£n ghi
```json
{
  "user_id": "10000000-0000-0000-0000-000000000001",
  "role_id": 2,
  "scope_type": "venue",
  "scope_id": "90000000-0000-0000-0000-000000000009"
}
```

## TĂªn báº£ng: permissions

### 1. Má»¥c Ä‘Ă­ch báº£ng
LÆ°u trá»¯ danh sĂ¡ch cĂ¡c quyá»n cá»¥ thá»ƒ, chi tiáº¿t dĂ¹ng Ä‘á»ƒ check logic trong code.

### 2. Danh sĂ¡ch trÆ°á»ng

| STT | TĂªn trÆ°á»ng | Kiá»ƒu dá»¯ liá»‡u | Null | Default | KhĂ³a/RĂ ng buá»™c | MĂ´ táº£ tĂ¡c dá»¥ng trÆ°á»ng | VĂ­ dá»¥ |
|---|---|---|---|---|---|---|---|
| 1 | id | bigint | KhĂ´ng | auto | PK | ID tá»± tÄƒng | 1 |
| 2 | code | varchar(100) | KhĂ´ng | - | Unique | MĂ£ quyá»n duy nháº¥t check trong code | booking.manage |
| 3 | name | varchar(255) | KhĂ´ng | - | - | TĂªn quyá»n hiá»ƒn thá»‹ | Quáº£n lĂ½ Ä‘áº·t sĂ¢n |
| 4 | group_name | varchar(50) | KhĂ´ng | - | Index | NhĂ³m quyá»n Ä‘á»ƒ UI gom nhĂ³m | booking |

### 3. KhĂ³a chĂ­nh, khĂ³a ngoáº¡i, index
- PK: id
- Unique: code
- Index: group_name

### 4. Quan há»‡ vá»›i báº£ng khĂ¡c
- permissions n-n roles qua role_permissions
- permissions 1-n user_permission_revokes qua user_permission_revokes.permission_id

### 5. VĂ­ dá»¥ báº£n ghi
```json
{
  "id": 1,
  "code": "booking.manage",
  "name": "Quáº£n lĂ½ Ä‘áº·t sĂ¢n",
  "group_name": "booking"
}
```

## TĂªn báº£ng: role_permissions

### 1. Má»¥c Ä‘Ă­ch báº£ng
Báº£ng trung gian n-n káº¿t ná»‘i roles vĂ  permissions, Ä‘á»‹nh nghÄ©a 1 role cĂ³ nhá»¯ng quyá»n chi tiáº¿t nĂ o.

### 2. Danh sĂ¡ch trÆ°á»ng

| STT | TĂªn trÆ°á»ng | Kiá»ƒu dá»¯ liá»‡u | Null | Default | KhĂ³a/RĂ ng buá»™c | MĂ´ táº£ tĂ¡c dá»¥ng trÆ°á»ng | VĂ­ dá»¥ |
|---|---|---|---|---|---|---|---|
| 1 | role_id | bigint | KhĂ´ng | - | PK, FK | ID cá»§a role | 1 |
| 2 | permission_id | bigint | KhĂ´ng | - | PK, FK | ID cá»§a quyá»n | 10 |

### 3. KhĂ³a chĂ­nh, khĂ³a ngoáº¡i, index
- PK: (role_id, permission_id)
- FK: role_id -> roles.id (cascade), permission_id -> permissions.id (cascade)

### 4. Quan há»‡ vá»›i báº£ng khĂ¡c
- Cáº§u ná»‘i role vĂ  permission.

### 5. VĂ­ dá»¥ báº£n ghi
```json
{
  "role_id": 1,
  "permission_id": 10
}
```

## TĂªn báº£ng: user_permission_revokes

### 1. Má»¥c Ä‘Ă­ch báº£ng
Báº£ng quáº£n lĂ½ viá»‡c "rĂºt" má»™t quyá»n cá»¥ thá»ƒ cá»§a 1 user nháº¥t Ä‘á»‹nh, ká»ƒ cáº£ khi role cá»§a há» cĂ³ cáº¥p quyá»n Ä‘Ă³. Há»— trá»£ scope (pháº¡m vi).

### 2. Danh sĂ¡ch trÆ°á»ng

| STT | TĂªn trÆ°á»ng | Kiá»ƒu dá»¯ liá»‡u | Null | Default | KhĂ³a/RĂ ng buá»™c | MĂ´ táº£ tĂ¡c dá»¥ng trÆ°á»ng | VĂ­ dá»¥ |
|---|---|---|---|---|---|---|---|
| 1 | id | bigint | KhĂ´ng | auto | PK | ID tá»± tÄƒng | 1 |
| 2 | user_id | char(36) | KhĂ´ng | - | FK | User bá»‹ thu há»“i quyá»n | 10000000-... |
| 3 | permission_id | bigint | KhĂ´ng | - | FK | Quyá»n bá»‹ thu há»“i | 5 |
| 4 | scope_type | enum | KhĂ´ng | system | Index | Pháº¡m vi (system hoáº·c venue) | venue |
| 5 | scope_id | char(36) | KhĂ´ng | 0000... | Index | ID pháº¡m vi thu há»“i | aabbccdd-... |
| 6 | revoked_by | char(36) | CĂ³ | null | FK | NgÆ°á»i thá»±c hiá»‡n thu há»“i | null |
| 7 | reason | varchar(255) | CĂ³ | null | - | LĂ½ do thu há»“i quyá»n | Vi pháº¡m ná»™i quy |

### 3. KhĂ³a chĂ­nh, khĂ³a ngoáº¡i, index
- PK: id
- Unique: user_id, permission_id, scope_type, scope_id (user_permission_revokes_scope_unique)
- FK: user_id -> users.id, permission_id -> permissions.id, revoked_by -> users.id
- Index: scope_type, scope_id

### 4. Quan há»‡ vá»›i báº£ng khĂ¡c
- LiĂªn káº¿t vá»›i users, permissions vĂ  logical reference tá»›i cá»¥m sĂ¢n qua scope_id.

### 5. VĂ­ dá»¥ báº£n ghi
```json
{
  "user_id": "10000000-0000-0000-0000-000000000001",
  "permission_id": 5,
  "scope_type": "system",
  "scope_id": "00000000-0000-0000-0000-000000000000",
  "reason": "Táº¡m khĂ³a quyá»n chat"
}
```

## TĂªn báº£ng: personal_access_tokens

### 1. Má»¥c Ä‘Ă­ch báº£ng
Báº£ng chuáº©n cá»§a gĂ³i Laravel Sanctum dĂ¹ng Ä‘á»ƒ lÆ°u trá»¯ vĂ  xĂ¡c thá»±c token API cá»§a users.

### 2. Danh sĂ¡ch trÆ°á»ng

| STT | TĂªn trÆ°á»ng | Kiá»ƒu dá»¯ liá»‡u | Null | Default | KhĂ³a/RĂ ng buá»™c | MĂ´ táº£ tĂ¡c dá»¥ng trÆ°á»ng | VĂ­ dá»¥ |
|---|---|---|---|---|---|---|---|
| 1 | id | bigint | KhĂ´ng | auto | PK | ID tá»± tÄƒng | 1 |
| 2 | tokenable_type | varchar(255) | KhĂ´ng | - | Index | Class model (User) | App\Models\User |
| 3 | tokenable_id | char(36) | KhĂ´ng | - | Index | ID cá»§a User | 10000000-... |
| 4 | name | text | KhĂ´ng | - | - | TĂªn token | android-app |
| 5 | token | varchar(64) | KhĂ´ng | - | Unique | Chuá»—i token Ä‘Ă£ hash | abc...xyz |
| 6 | abilities | text | CĂ³ | null | - | Pháº¡m vi token | ["*"] |
| 7 | last_used_at | timestamp | CĂ³ | null | - | Thá»i Ä‘iá»ƒm dĂ¹ng cuá»‘i | 2026-06-15 |
| 8 | expires_at | timestamp | CĂ³ | null | Index | Háº¡n chĂ³t token | null |

### 3. KhĂ³a chĂ­nh, khĂ³a ngoáº¡i, index
- PK: id
- Unique: token
- Index: tokenable_type, tokenable_id, expires_at

### 4. Quan há»‡ vá»›i báº£ng khĂ¡c
- Polymorphic (tokenable) liĂªn káº¿t tá»›i users.

### 5. VĂ­ dá»¥ báº£n ghi
```json
{
  "tokenable_type": "App\\Models\\User",
  "tokenable_id": "10000000-0000-0000-0000-000000000001",
  "name": "web-login",
  "token": "hashed_string"
}
```

---
*(Sáº½ tiáº¿p tá»¥c ná»‘i thĂªm pháº§n Venue vĂ  Booking á»Ÿ pháº§n tiáº¿p theo)*
### MODULE: VENUE

## TĂªn báº£ng: venue_clusters

### 1. Má»¥c Ä‘Ă­ch báº£ng
LÆ°u trá»¯ thĂ´ng tin cÆ¡ sá»Ÿ kinh doanh (cá»¥m sĂ¢n) bao gá»“m tĂªn, Ä‘á»‹a chá»‰, chá»§ sá»Ÿ há»¯u, Ä‘Ă¡nh giĂ¡ vĂ  tráº¡ng thĂ¡i duyá»‡t.

### 2. Danh sĂ¡ch trÆ°á»ng

| STT | TĂªn trÆ°á»ng | Kiá»ƒu dá»¯ liá»‡u | Null | Default | KhĂ³a/RĂ ng buá»™c | MĂ´ táº£ tĂ¡c dá»¥ng trÆ°á»ng | VĂ­ dá»¥ |
|---|---|---|---|---|---|---|---|
| 1 | id | char(36) | KhĂ´ng | - | PK | ID cá»¥m sĂ¢n | 20000000-... |
| 2 | owner_id | char(36) | KhĂ´ng | - | FK | Chá»§ sĂ¢n sá»Ÿ há»¯u cá»¥m nĂ y | 10000000-... |
| 3 | name | varchar(255) | KhĂ´ng | - | Index | TĂªn cá»¥m sĂ¢n hiá»ƒn thá»‹ cho user | SĂ¢n cáº§u lĂ´ng 247 |
| 4 | slug | varchar(255) | KhĂ´ng | - | Unique | Äá»‹nh danh URL/SEO | san-cau-long-247 |
| 5 | description | text | CĂ³ | null | - | MĂ´ táº£ cá»¥m sĂ¢n, tiá»‡n Ă­ch | SĂ¢n má»›i xĂ¢y... |
| 6 | phone_contact | varchar(20) | CĂ³ | null | - | Sá»‘ Ä‘iá»‡n thoáº¡i liĂªn há»‡ | 0988776655 |
| 7 | address | text | KhĂ´ng | - | - | Äá»‹a chá»‰ thá»±c táº¿ | Sá»‘ 1 Ä‘Æ°á»ng X |
| 8 | map_url | varchar(1000)| CĂ³ | null | - | Link Google Maps | https://goo.gl/... |
| 9 | latitude | decimal(10,7)| KhĂ´ng | - | Index | VÄ© Ä‘á»™ Ä‘á»ƒ tĂ¬m sĂ¢n gáº§n Ä‘Ă¢y | 21.028511 |
| 10 | longitude | decimal(10,7)| KhĂ´ng | - | Index | Kinh Ä‘á»™ Ä‘á»ƒ tĂ¬m sĂ¢n gáº§n Ä‘Ă¢y | 105.804817 |
| 11 | amenities | json | CĂ³ | null | - | Danh sĂ¡ch tiá»‡n Ă­ch (wifi, bĂ£i xe...) | ["wifi", "parking"] |
| 12 | status | enum | KhĂ´ng | pending | Index | Tráº¡ng thĂ¡i (pending, active, locked)| active |
| 13 | status_reason | text | CĂ³ | null | - | LĂ½ do khĂ³a cá»¥m sĂ¢n | null |
| 14 | locked_at | timestamp | CĂ³ | null | - | Thá»i Ä‘iá»ƒm bá»‹ khĂ³a | null |
| 15 | locked_until | timestamp | CĂ³ | null | Index | Thá»i Ä‘iá»ƒm háº¿t khĂ³a táº¡m thá»i | null |
| 16 | locked_by | char(36) | CĂ³ | null | FK | Admin khĂ³a cá»¥m sĂ¢n | null |
| 17 | rating_avg | decimal(3,2) | KhĂ´ng | 0.00 | Index | Äiá»ƒm trung bĂ¬nh sĂ¢n | 4.80 |
| 18 | rating_count | unsigned int | KhĂ´ng | 0 | - | Sá»‘ lÆ°á»£t review | 150 |

### 3. KhĂ³a chĂ­nh, khĂ³a ngoáº¡i, index
- PK: id
- Unique: slug
- FK: owner_id -> users.id (restrict), locked_by -> users.id (set null)
- Index: name, status, rating_avg, locked_until, [latitude, longitude], [status, rating_avg]

### 4. Quan há»‡ vá»›i báº£ng khĂ¡c
- venue_clusters 1-n venue_courts qua venue_cluster_id
- venue_clusters 1-n bookings qua venue_cluster_id (denormalized)
- users 1-n venue_clusters qua owner_id

### 5. VĂ­ dá»¥ báº£n ghi
```json
{
  "id": "20000000-0000-0000-0000-000000000001",
  "owner_id": "10000000-0000-0000-0000-000000000002",
  "name": "SĂ¢n Cáº§u LĂ´ng ÄÄƒng Khoa",
  "latitude": 21.033,
  "longitude": 105.8,
  "status": "active"
}
```

## TĂªn báº£ng: court_types

### 1. Má»¥c Ä‘Ă­ch báº£ng
LÆ°u trá»¯ danh má»¥c cĂ¡c mĂ´n thá»ƒ thao hoáº·c loáº¡i sĂ¢n. DĂ¹ng cho cáº£ há»‡ thá»‘ng quáº£n lĂ½ mĂ´n thá»ƒ thao.

### 2. Danh sĂ¡ch trÆ°á»ng

| STT | TĂªn trÆ°á»ng | Kiá»ƒu dá»¯ liá»‡u | Null | Default | KhĂ³a/RĂ ng buá»™c | MĂ´ táº£ tĂ¡c dá»¥ng trÆ°á»ng | VĂ­ dá»¥ |
|---|---|---|---|---|---|---|---|
| 1 | id | bigint | KhĂ´ng | auto | PK | ID tá»± tÄƒng | 1 |
| 2 | parent_id | bigint | CĂ³ | null | FK | Loáº¡i sĂ¢n cha (gom nhĂ³m bá»™ mĂ´n) | null |
| 3 | name | varchar(100) | KhĂ´ng | - | Unique | TĂªn mĂ´n / loáº¡i sĂ¢n | Cáº§u lĂ´ng |
| 4 | description | text | CĂ³ | null | - | MĂ´ táº£ loáº¡i sĂ¢n | SĂ¢n tiĂªu chuáº©n |
| 5 | player_count | unsigned int | KhĂ´ng | 0 | - | Sá»‘ ngÆ°á»i chÆ¡i tham kháº£o | 4 |
| 6 | is_active | boolean | KhĂ´ng | true | Index | CĂ²n Ă¡p dá»¥ng khĂ´ng | 1 |
| 7 | deleted_at | timestamp | CĂ³ | null | - | Soft delete | null |

### 3. KhĂ³a chĂ­nh, khĂ³a ngoáº¡i, index
- PK: id
- Unique: name
- FK: parent_id -> court_types.id (set null)
- Index: is_active

### 4. Quan há»‡ vá»›i báº£ng khĂ¡c
- Loáº¡i sĂ¢n cha-con (parent_id)
- court_types 1-n venue_courts qua court_type_id

### 5. VĂ­ dá»¥ báº£n ghi
```json
{
  "id": 1,
  "name": "SĂ¢n Cáº§u LĂ´ng",
  "player_count": 4,
  "is_active": true
}
```

## TĂªn báº£ng: venue_courts

### 1. Má»¥c Ä‘Ă­ch báº£ng
LÆ°u trá»¯ thĂ´ng tin cĂ¡c "sĂ¢n con" náº±m bĂªn trong má»™t cá»¥m sĂ¢n. KhĂ¡ch hĂ ng thá»±c táº¿ Ä‘áº·t lá»‹ch trĂªn cĂ¡c sĂ¢n con nĂ y.

### 2. Danh sĂ¡ch trÆ°á»ng

| STT | TĂªn trÆ°á»ng | Kiá»ƒu dá»¯ liá»‡u | Null | Default | KhĂ³a/RĂ ng buá»™c | MĂ´ táº£ tĂ¡c dá»¥ng trÆ°á»ng | VĂ­ dá»¥ |
|---|---|---|---|---|---|---|---|
| 1 | id | char(36) | KhĂ´ng | - | PK | ID sĂ¢n con | 3000... |
| 2 | venue_cluster_id | char(36) | KhĂ´ng | - | FK | ID cá»¥m sĂ¢n chá»©a sĂ¢n nĂ y | 2000... |
| 3 | court_type_id | bigint | KhĂ´ng | - | FK | ID mĂ´n thá»ƒ thao | 1 |
| 4 | name | varchar(100) | KhĂ´ng | - | Index | TĂªn gá»i cá»§a sĂ¢n con | SĂ¢n sá»‘ 1 |
| 5 | status | enum | KhĂ´ng | active | Index | Tráº¡ng thĂ¡i (active, maintenance...) | active |
| 6 | sort_order | int | KhĂ´ng | 0 | - | Thá»© tá»± hiá»ƒn thá»‹ UI | 1 |
| 7 | deleted_at | timestamp | CĂ³ | null | - | Soft delete | null |

### 3. KhĂ³a chĂ­nh, khĂ³a ngoáº¡i, index
- PK: id
- FK: venue_cluster_id -> venue_clusters.id (cascade), court_type_id -> court_types.id (restrict)
- Index: name, status, [venue_cluster_id, status]

### 4. Quan há»‡ vá»›i báº£ng khĂ¡c
- Thuá»™c vá» venue_clusters vĂ  court_types
- 1-n vá»›i bookings qua venue_court_id

### 5. VĂ­ dá»¥ báº£n ghi
```json
{
  "id": "30000000-0000-0000-0000-000000000001",
  "venue_cluster_id": "20000000-0000-0000-0000-000000000001",
  "court_type_id": 1,
  "name": "SĂ¢n Tháº£m Xanh 01",
  "status": "active"
}
```

## TĂªn báº£ng: venue_staff_assignments

### 1. Má»¥c Ä‘Ă­ch báº£ng
Quáº£n lĂ½ phĂ¢n cĂ´ng nhĂ¢n viĂªn phá»¥c vá»¥, quáº£n lĂ½ cho 1 cá»¥m sĂ¢n, há»— trá»£ phĂ¢n cĂ´ng theo tá»«ng loáº¡i sĂ¢n nhá» (scope).

### 2. Danh sĂ¡ch trÆ°á»ng

| STT | TĂªn trÆ°á»ng | Kiá»ƒu dá»¯ liá»‡u | Null | Default | KhĂ³a/RĂ ng buá»™c | MĂ´ táº£ tĂ¡c dá»¥ng trÆ°á»ng | VĂ­ dá»¥ |
|---|---|---|---|---|---|---|---|
| 1 | id | bigint | KhĂ´ng | auto | PK | ID tá»± tÄƒng | 1 |
| 2 | user_id | char(36) | KhĂ´ng | - | FK | ID nhĂ¢n viĂªn | 1000... |
| 3 | venue_cluster_id | char(36) | KhĂ´ng | - | FK | ID cá»¥m sĂ¢n lĂ m viá»‡c | 2000... |
| 4 | scope_type | enum | KhĂ´ng | all_cluster| Index | Quáº£n lĂ½ cáº£ cá»¥m hay 1 loáº¡i sĂ¢n | all_cluster |
| 5 | court_type_id | bigint | CĂ³ | null | FK | Náº¿u quáº£n lĂ½ loáº¡i sĂ¢n thĂ¬ Ä‘iá»n ID mĂ´n | null |
| 6 | scope_key | varchar(50) | KhĂ´ng | all | Index | Key Ä‘áº·c biá»‡t Ä‘á»ƒ phĂ¢n biá»‡t | all |
| 7 | assigned_by | char(36) | CĂ³ | null | FK | Admin/chá»§ sĂ¢n giao viá»‡c | null |
| 8 | status | enum | KhĂ´ng | active | Index | Tráº¡ng thĂ¡i (active/inactive) | active |

### 3. KhĂ³a chĂ­nh, khĂ³a ngoáº¡i, index
- PK: id
- Unique: user_id, venue_cluster_id, scope_key
- FK: user_id, venue_cluster_id, court_type_id, assigned_by
- Index: scope_type, scope_key, status

### 4. Quan há»‡ vá»›i báº£ng khĂ¡c
- Káº¿t ná»‘i nhĂ¢n viĂªn (users) vá»›i cá»¥m sĂ¢n (venue_clusters) vĂ  cĂ³ thá»ƒ lá»c theo mĂ´n (court_types).

### 5. VĂ­ dá»¥ báº£n ghi
```json
{
  "user_id": "10000000-0000-0000-0000-000000000011",
  "venue_cluster_id": "20000000-0000-0000-0000-000000000001",
  "scope_type": "all_cluster",
  "status": "active"
}
```

## TĂªn báº£ng: venue_court_approval_requests

### 1. Má»¥c Ä‘Ă­ch báº£ng
Khi chá»§ sĂ¢n muá»‘n táº¡o thĂªm sĂ¢n con, há» gá»­i yĂªu cáº§u vĂ  admin duyá»‡t trÆ°á»›c khi sĂ¢n hiá»‡n ra trĂªn há»‡ thá»‘ng.

### 2. Danh sĂ¡ch trÆ°á»ng

| STT | TĂªn trÆ°á»ng | Kiá»ƒu dá»¯ liá»‡u | Null | Default | KhĂ³a/RĂ ng buá»™c | MĂ´ táº£ tĂ¡c dá»¥ng trÆ°á»ng | VĂ­ dá»¥ |
|---|---|---|---|---|---|---|---|
| 1 | id | char(36) | KhĂ´ng | - | PK | ID yĂªu cáº§u duyá»‡t | 4000... |
| 2 | venue_cluster_id | char(36) | KhĂ´ng | - | FK | Cá»¥m sĂ¢n muá»‘n thĂªm | 2000... |
| 3 | court_type_id | bigint | KhĂ´ng | - | FK | Loáº¡i sĂ¢n muá»‘n thĂªm | 1 |
| 4 | name | varchar(100) | KhĂ´ng | - | - | TĂªn sĂ¢n con dá»± kiáº¿n | SĂ¢n sá»‘ 2 |
| 5 | status | enum | KhĂ´ng | pending | Index | Tráº¡ng thĂ¡i duyá»‡t (pending, approved) | pending |
| 6 | requested_by | char(36) | KhĂ´ng | - | FK | Chá»§ sĂ¢n gá»­i yĂªu cáº§u | 1000... |
| 7 | reviewed_by | char(36) | CĂ³ | null | FK | Admin duyá»‡t | null |
| 8 | status_reason | text | CĂ³ | null | - | LĂ½ do tá»« chá»‘i | null |
| 9 | approved_venue_court_id| char(36) | CĂ³ | null | Index | ID sĂ¢n con Ä‘Æ°á»£c sinh ra sau duyá»‡t | null |
| 10 | reviewed_at | timestamp | CĂ³ | null | - | Thá»i Ä‘iá»ƒm duyá»‡t | null |

### 3. KhĂ³a chĂ­nh, khĂ³a ngoáº¡i, index
- PK: id
- FK: venue_cluster_id, court_type_id, requested_by, reviewed_by
- Index: status, approved_venue_court_id

### 4. Quan há»‡ vá»›i báº£ng khĂ¡c
- Khi duyá»‡t xong sáº½ táº¡o ra 1 record á»Ÿ venue_courts vĂ  lÆ°u ID vĂ o approved_venue_court_id (logical reference).

### 5. VĂ­ dá»¥ báº£n ghi
```json
{
  "id": "40000000-0000-0000-0000-000000000001",
  "name": "SĂ¢n Cáº§u LĂ´ng VIP",
  "status": "pending",
  "requested_by": "10000000-0000-0000-0000-000000000002"
}
```

## TĂªn báº£ng: favorite_venues

### 1. Má»¥c Ä‘Ă­ch báº£ng
LÆ°u danh sĂ¡ch cá»¥m sĂ¢n yĂªu thĂ­ch cá»§a ngÆ°á»i dĂ¹ng.

### 2. Danh sĂ¡ch trÆ°á»ng

| STT | TĂªn trÆ°á»ng | Kiá»ƒu dá»¯ liá»‡u | Null | Default | KhĂ³a/RĂ ng buá»™c | MĂ´ táº£ tĂ¡c dá»¥ng trÆ°á»ng | VĂ­ dá»¥ |
|---|---|---|---|---|---|---|---|
| 1 | id | bigint | KhĂ´ng | auto | PK | ID tá»± tÄƒng | 1 |
| 2 | user_id | char(36) | KhĂ´ng | - | FK | User yĂªu thĂ­ch sĂ¢n | 1000... |
| 3 | venue_cluster_id | char(36) | KhĂ´ng | - | FK | Cá»¥m sĂ¢n Ä‘Æ°á»£c yĂªu thĂ­ch | 2000... |

### 3. KhĂ³a chĂ­nh, khĂ³a ngoáº¡i, index
- PK: id
- Unique: user_id, venue_cluster_id
- FK: user_id -> users.id (cascade), venue_cluster_id -> venue_clusters.id (cascade)

### 4. Quan há»‡ vá»›i báº£ng khĂ¡c
- Cáº§u ná»‘i 1-n giá»¯a users vĂ  venue_clusters.

### 5. VĂ­ dá»¥ báº£n ghi
```json
{
  "user_id": "10000000-0000-0000-0000-000000000001",
  "venue_cluster_id": "20000000-0000-0000-0000-000000000001"
}
```

### MODULE: BOOKING

## TĂªn báº£ng: booking_configs

### 1. Má»¥c Ä‘Ă­ch báº£ng
Cáº¥u hĂ¬nh linh hoáº¡t cho tá»«ng cá»¥m sĂ¢n (tiá»n cá»c, thá»i gian Ä‘áº·t tá»‘i thiá»ƒu, chĂ­nh sĂ¡ch hoĂ n tiá»n).

### 2. Danh sĂ¡ch trÆ°á»ng

| STT | TĂªn trÆ°á»ng | Kiá»ƒu dá»¯ liá»‡u | Null | Default | KhĂ³a/RĂ ng buá»™c | MĂ´ táº£ tĂ¡c dá»¥ng trÆ°á»ng | VĂ­ dá»¥ |
|---|---|---|---|---|---|---|---|
| 1 | venue_cluster_id | char(36) | KhĂ´ng | - | PK, FK | ID cá»¥m sĂ¢n | 2000... |
| 2 | min_duration_minutes | unsigned int | KhĂ´ng | 30 | - | Thá»i gian Ä‘áº·t tá»‘i thiá»ƒu (phĂºt) | 60 |
| 3 | max_duration_minutes | unsigned int | CĂ³ | null | - | Thá»i gian Ä‘áº·t tá»‘i Ä‘a (phĂºt) | null |
| 4 | slot_hold_minutes | unsigned int | KhĂ´ng | 20 | - | Giá»¯ chá»— trÆ°á»›c khi thanh toĂ¡n (phĂºt) | 15 |
| 5 | reminder_before_minutes| unsigned int | KhĂ´ng | 30 | - | Gá»­i nháº¯c nhá»Ÿ trÆ°á»›c giá» chÆ¡i (phĂºt) | 30 |
| 6 | allow_full_payment | boolean | KhĂ´ng | true | - | Cho phĂ©p thanh toĂ¡n 100% | 1 |
| 7 | allow_deposit | boolean | KhĂ´ng | true | - | Cho phĂ©p cá»c | 1 |
| 8 | allow_no_prepay | boolean | KhĂ´ng | true | - | Cho phĂ©p khĂ´ng tráº£ trÆ°á»›c | 0 |
| 9 | auto_approve_full_payment| boolean| KhĂ´ng | false | - | Tá»± duyá»‡t khi thanh toĂ¡n Ä‘á»§ | 1 |
| 10 | deposit_percent | decimal(5,2)| CĂ³ | null | - | Pháº§n trÄƒm cá»c | 30.00 |
| 11 | cancel_before_hours | unsigned int | KhĂ´ng | 0 | - | Sá»‘ giá» tá»‘i thiá»ƒu bĂ¡o há»§y Ä‘á»ƒ hoĂ n | 24 |
| 12 | refund_percent | unsigned int | KhĂ´ng | 0 | - | Pháº§n trÄƒm hoĂ n tiá»n náº¿u há»§y chuáº©n | 100 |

### 3. KhĂ³a chĂ­nh, khĂ³a ngoáº¡i, index
- PK: venue_cluster_id
- FK: venue_cluster_id -> venue_clusters.id (cascade)

### 4. Quan há»‡ vá»›i báº£ng khĂ¡c
- LiĂªn káº¿t 1-1 vá»›i venue_clusters.

### 5. VĂ­ dá»¥ báº£n ghi
```json
{
  "venue_cluster_id": "20000000-0000-0000-0000-000000000001",
  "min_duration_minutes": 60,
  "deposit_percent": 50.00,
  "cancel_before_hours": 24,
  "refund_percent": 100
}
```

## TĂªn báº£ng: bookings

### 1. Má»¥c Ä‘Ă­ch báº£ng
LÆ°u trá»¯ toĂ n bá»™ thĂ´ng tin Ä‘Æ¡n Ä‘áº·t sĂ¢n (booking láº» vĂ  cá»‘ Ä‘á»‹nh), ngĂ y giá» chÆ¡i, tiá»n thanh toĂ¡n.

### 2. Danh sĂ¡ch trÆ°á»ng

| STT | TĂªn trÆ°á»ng | Kiá»ƒu dá»¯ liá»‡u | Null | Default | KhĂ³a/RĂ ng buá»™c | MĂ´ táº£ tĂ¡c dá»¥ng trÆ°á»ng | VĂ­ dá»¥ |
|---|---|---|---|---|---|---|---|
| 1 | id | char(36) | KhĂ´ng | - | PK | ID booking | 5000... |
| 2 | booking_code | varchar(30) | KhĂ´ng | - | Unique | MĂ£ booking dá»… Ä‘á»c (VD: BKG123) | BKG-ABC123X |
| 3 | customer_id | char(36) | CĂ³ | null | FK | KhĂ¡ch Ä‘áº·t online (null = walk-in) | 1000... |
| 4 | venue_court_id | char(36) | KhĂ´ng | - | FK | SĂ¢n con thá»±c táº¿ chÆ¡i | 3000... |
| 5 | requested_venue_court_id| char(36) | CĂ³ | null | FK | SĂ¢n con lĂºc khĂ¡ch yĂªu cáº§u Ä‘áº·t | 3000... |
| 6 | venue_cluster_id | char(36) | KhĂ´ng | - | Index | Cá»¥m sĂ¢n (denormalized Ä‘á»ƒ filter) | 2000... |
| 7 | booking_date | date | KhĂ´ng | - | Index | NgĂ y chÆ¡i | 2026-10-15 |
| 8 | start_time | time | KhĂ´ng | - | Index | Giá» báº¯t Ä‘áº§u | 18:00:00 |
| 9 | end_time | time | KhĂ´ng | - | Index | Giá» káº¿t thĂºc | 20:00:00 |
| 10 | duration_minutes | unsigned int | KhĂ´ng | - | - | Thá»i lÆ°á»£ng (phĂºt) | 120 |
| 11 | total_price | decimal(12,2)| KhĂ´ng | 0.00 | - | Tá»•ng tiá»n sĂ¢n | 200000 |
| 12 | payment_option | enum | KhĂ´ng | no_prepay | - | Kiá»ƒu thanh toĂ¡n (full, deposit...) | deposit |
| 13 | required_payment_amount| decimal(12,2)| KhĂ´ng | 0.00 | - | Tiá»n cáº§n Ä‘Ă³ng ngay | 100000 |
| 14 | source | enum | KhĂ´ng | online | - | Nguá»“n Ä‘áº·t (online, counter) | online |
| 15 | booking_type | enum | KhĂ´ng | single | Index | Láº» (single) hay cá»‘ Ä‘á»‹nh (recurring) | single |
| 16 | recurring_group_code| varchar(30) | CĂ³ | null | Index | MĂ£ nhĂ³m Ä‘Æ¡n cá»‘ Ä‘á»‹nh | null |
| 17 | recurrence_interval | unsigned int | CĂ³ | null | - | Khoáº£ng láº·p | null |
| 18 | status | enum | KhĂ´ng | pending_approval| Index | Tráº¡ng thĂ¡i (confirmed, checked_in..) | confirmed |
| 19 | walk_in_name | varchar(255) | CĂ³ | null | - | TĂªn khĂ¡ch vĂ£ng lai | KhĂ¡ch vĂ£ng lai |
| 20 | walk_in_phone | varchar(20) | CĂ³ | null | - | SÄT khĂ¡ch vĂ£ng lai | 0911223344 |
| 21 | status_reason | text | CĂ³ | null | - | LĂ½ do há»§y/tá»« chá»‘i | KhĂ¡ch bĂ¡o há»§y |

*(CĂ³ thĂªm cĂ¡c trÆ°á»ng phá»¥: cancelled_by, court_changed_by, reminder_sent_at)*

### 3. KhĂ³a chĂ­nh, khĂ³a ngoáº¡i, index
- PK: id
- Unique: booking_code
- FK: customer_id (users.id), venue_court_id, requested_venue_court_id, cancelled_by, court_changed_by, created_by
- Index: Nhiá»u index gom nhĂ³m (vd: venue_court_id + booking_date + start_time + end_time) Ä‘á»ƒ query trá»‘ng lá»‹ch.

### 4. Quan há»‡ vá»›i báº£ng khĂ¡c
- 1-n vá»›i payments, refunds, complaints, reviews
- Gáº¯n cháº·t vá»›i venue_courts vĂ  venue_clusters.

### 5. VĂ­ dá»¥ báº£n ghi
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

## TĂªn báº£ng: price_slots

### 1. Má»¥c Ä‘Ă­ch báº£ng
LÆ°u trá»¯ báº£ng giĂ¡ sĂ¢n theo cĂ¡c khung giá» khĂ¡c nhau cá»§a má»™t cá»¥m sĂ¢n vĂ  loáº¡i mĂ´n.

### 2. Danh sĂ¡ch trÆ°á»ng

| STT | TĂªn trÆ°á»ng | Kiá»ƒu dá»¯ liá»‡u | Null | Default | KhĂ³a/RĂ ng buá»™c | MĂ´ táº£ tĂ¡c dá»¥ng trÆ°á»ng | VĂ­ dá»¥ |
|---|---|---|---|---|---|---|---|
| 1 | id | char(36) | KhĂ´ng | - | PK | ID báº£ng giĂ¡ | 6000... |
| 2 | venue_cluster_id | char(36) | KhĂ´ng | - | FK | ID cá»¥m sĂ¢n | 2000... |
| 3 | court_type_id | bigint | KhĂ´ng | - | FK | ID mĂ´n thá»ƒ thao | 1 |
| 4 | booking_type | enum | KhĂ´ng | all | Index | Ăp dá»¥ng cho Ä‘Æ¡n láº» hay cá»‘ Ä‘á»‹nh | all |
| 5 | start_time | time | KhĂ´ng | - | Index | Giá» báº¯t Ä‘áº§u khung giĂ¡ | 17:00:00 |
| 6 | end_time | time | KhĂ´ng | - | Index | Giá» káº¿t thĂºc khung giĂ¡ | 22:00:00 |
| 7 | price | decimal(12,2)| KhĂ´ng | 0.00 | - | GiĂ¡ má»—i giá» (hoáº·c slot) | 120000.00 |
| 8 | apply_to_days | json | CĂ³ | null | - | NgĂ y Ă¡p dá»¥ng (T2-CN) | [1, 2, 3, 4, 5] |
| 9 | is_active | boolean | KhĂ´ng | true | Index | CĂ²n Ă¡p dá»¥ng khĂ´ng | 1 |

### 3. KhĂ³a chĂ­nh, khĂ³a ngoáº¡i, index
- PK: id
- FK: venue_cluster_id, court_type_id
- Index: start_time, end_time, [venue_cluster_id, court_type_id, booking_type, is_active]

### 4. Quan há»‡ vá»›i báº£ng khĂ¡c
- Thuá»™c cá»¥m sĂ¢n vĂ  loáº¡i sĂ¢n, dĂ¹ng Ä‘á»ƒ tĂ­nh tiá»n khi khĂ¡ch Ä‘áº·t booking.

## TĂªn báº£ng: holiday_prices

### 1. Má»¥c Ä‘Ă­ch báº£ng
Ghi Ä‘Ă¨ giĂ¡ á»Ÿ báº£ng price_slots vĂ o cĂ¡c ngĂ y lá»… hoáº·c ngĂ y Ä‘áº·c biá»‡t.

### 2. Danh sĂ¡ch trÆ°á»ng (TĂ³m táº¯t)
- **id, venue_cluster_id, court_type_id**: Äá»‹nh danh vĂ  FK
- **date_type**: (holiday, special_date)
- **holiday_date**: (date) NgĂ y nghá»‰ lá»… cá»¥ thá»ƒ.
- **start_time, end_time, price**: GiĂ¡ trong khung giá» lá»….
- **is_active**: CĂ³ Ă¡p dá»¥ng khĂ´ng.

## TĂªn báº£ng: slot_locks

### 1. Má»¥c Ä‘Ă­ch báº£ng
Quáº£n lĂ½ viá»‡c khĂ³a khung giá» (lock slot) do chá»§ sĂ¢n tá»± block lá»‹ch hoáº·c block táº¡m thá»i khi user Ä‘ang á»Ÿ mĂ n hĂ¬nh thanh toĂ¡n.

### 2. Danh sĂ¡ch trÆ°á»ng (TĂ³m táº¯t)
- **id, venue_cluster_id, venue_court_id**: XĂ¡c Ä‘á»‹nh sĂ¢n bá»‹ khĂ³a.
- **lock_scope**: (court, cluster) KhĂ³a 1 sĂ¢n con hay khĂ³a nguyĂªn cá»¥m sĂ¢n.
- **booking_date, start_time, end_time**: Thá»i gian bá»‹ khĂ³a.
- **locked_by**: ID user/session giá»¯ chá»—.
- **lock_type**: (auto, manual) KhĂ³a há»‡ thá»‘ng tá»± táº¡o khi chá» thanh toĂ¡n, hoáº·c chá»§ sĂ¢n tá»± táº¡o.
- **expires_at**: Thá»i Ä‘iá»ƒm háº¿t háº¡n giá»¯ chá»— náº¿u lĂ  auto lock.

---
*(Sáº½ tiáº¿p tá»¥c ná»‘i thĂªm pháº§n Payment vĂ  Community á»Ÿ pháº§n tiáº¿p theo)*
### MODULE: PAYMENT & WALLET

## TĂªn báº£ng: payments

### 1. Má»¥c Ä‘Ă­ch báº£ng
LÆ°u trá»¯ thĂ´ng tin giao dá»‹ch thanh toĂ¡n cho cĂ¡c booking.

### 2. Danh sĂ¡ch trÆ°á»ng

| STT | TĂªn trÆ°á»ng | Kiá»ƒu dá»¯ liá»‡u | Null | Default | KhĂ³a/RĂ ng buá»™c | MĂ´ táº£ tĂ¡c dá»¥ng trÆ°á»ng | VĂ­ dá»¥ |
|---|---|---|---|---|---|---|---|
| 1 | id | char(36) | KhĂ´ng | - | PK | ID thanh toĂ¡n | 7000... |
| 2 | payment_code | varchar(50) | KhĂ´ng | - | Unique | MĂ£ thanh toĂ¡n ná»™i bá»™ há»‡ thá»‘ng | PAY-12345 |
| 3 | booking_id | char(36) | KhĂ´ng | - | FK | ID Booking thanh toĂ¡n cho | 5000... |
| 4 | system_bank_account_id| char(36) | CĂ³ | null | FK | TĂ i khoáº£n NH há»‡ thá»‘ng nháº­n tiá»n | 8000... |
| 5 | amount | decimal(12,2)| KhĂ´ng | - | - | Sá»‘ tiá»n thanh toĂ¡n Ä‘á»£t nĂ y | 100000.00 |
| 6 | payment_kind | enum | KhĂ´ng | partial | - | Loáº¡i thanh toĂ¡n (full, deposit, partial)| deposit |
| 7 | method | varchar(50) | KhĂ´ng | sepay | Index | PhÆ°Æ¡ng thá»©c (sepay...) | sepay |
| 8 | gateway_txn_id | varchar(100) | CĂ³ | null | Unique | MĂ£ GD tá»« cá»•ng thanh toĂ¡n tráº£ vá» | SEPAY-999 |
| 9 | gateway_response | json | CĂ³ | null | - | Dá»¯ liá»‡u gá»‘c tá»« gateway | {"status":"ok"} |
| 10| status | enum | KhĂ´ng | pending | Index | Tráº¡ng thĂ¡i (pending, paid, failed, refunded) | paid |
| 11| paid_at | timestamp | CĂ³ | null | Index | Thá»i Ä‘iá»ƒm thanh toĂ¡n thĂ nh cĂ´ng | 2026-06-15 |

### 3. KhĂ³a chĂ­nh, khĂ³a ngoáº¡i, index
- PK: id
- Unique: payment_code, gateway_txn_id
- FK: booking_id -> bookings.id, system_bank_account_id -> system_bank_accounts.id
- Index: method, status, paid_at, [booking_id, status]

### 4. Quan há»‡ vá»›i báº£ng khĂ¡c
- 1 booking cĂ³ thá»ƒ cĂ³ nhiá»u payments (cá»c rá»“i thanh toĂ¡n ná»‘t).
- 1 payment thuá»™c vá» 1 system_bank_accounts.

## TĂªn báº£ng: payment_logs

### 1. Má»¥c Ä‘Ă­ch báº£ng
Lá»‹ch sá»­ webhook, thay Ä‘á»•i tráº¡ng thĂ¡i cá»§a cá»•ng thanh toĂ¡n.

### 2. Danh sĂ¡ch trÆ°á»ng (TĂ³m táº¯t)
- **id, payment_id**: XĂ¡c Ä‘á»‹nh log cá»§a payment nĂ o.
- **event_type**: Loáº¡i sá»± kiá»‡n (webhook_received, status_changed).
- **request_payload, response_payload**: Data JSON thĂ´.
- **status_before, status_after**: Theo dĂµi Ä‘á»•i tráº¡ng thĂ¡i.

## TĂªn báº£ng: refunds

### 1. Má»¥c Ä‘Ă­ch báº£ng
Quáº£n lĂ½ yĂªu cáº§u hoĂ n tiá»n khi booking bá»‹ há»§y sau khi Ä‘Ă£ thanh toĂ¡n.

### 2. Danh sĂ¡ch trÆ°á»ng (TĂ³m táº¯t)
- **id, payment_id, booking_id**: LiĂªn káº¿t vá» thanh toĂ¡n gá»‘c vĂ  booking.
- **amount**: Sá»‘ tiá»n cáº§n hoĂ n (tĂ¹y % theo cáº¥u hĂ¬nh).
- **reason**: LĂ½ do hoĂ n.
- **status**: Tráº¡ng thĂ¡i (pending_confirmation, processing, completed, failed, rejected).
- **processed_by**: Admin xá»­ lĂ½ thá»§ cĂ´ng (do khĂ´ng hoĂ n tá»± Ä‘á»™ng).

## TĂªn báº£ng: system_bank_accounts

### 1. Má»¥c Ä‘Ă­ch báº£ng
Quáº£n lĂ½ danh sĂ¡ch cĂ¡c tĂ i khoáº£n ngĂ¢n hĂ ng cá»§a há»‡ thá»‘ng (dĂ¹ng Ä‘á»ƒ tĂ­ch há»£p táº¡o mĂ£ QR thanh toĂ¡n qua SePay).

### 2. Danh sĂ¡ch trÆ°á»ng (TĂ³m táº¯t)
- **id, name**: TĂªn gá»i gá»£i nhá»›.
- **bank_name, bank_code, account_number, account_holder_name**: ThĂ´ng tin TKNH thá»±c táº¿.
- **status, is_default**: Tráº¡ng thĂ¡i vĂ  TK máº·c Ä‘á»‹nh dĂ¹ng Ä‘á»ƒ táº¡o QR thanh toĂ¡n.

## TĂªn báº£ng: owner_wallets

### 1. Má»¥c Ä‘Ă­ch báº£ng
Quáº£n lĂ½ vĂ­ tiá»n cá»§a má»—i chá»§ sĂ¢n. Tiá»n khĂ¡ch thanh toĂ¡n vĂ o TK há»‡ thá»‘ng sáº½ Ä‘Æ°á»£c cá»™ng vĂ o vĂ­ nĂ y (Ä‘Ă³ng vai trĂ² nhÆ° sá»‘ dÆ° thu há»™).

### 2. Danh sĂ¡ch trÆ°á»ng (TĂ³m táº¯t)
- **id, owner_id**: VĂ­ thuá»™c vá» user chá»§ sĂ¢n.
- **available_balance**: Sá»‘ dÆ° cĂ³ thá»ƒ rĂºt.
- **pending_withdrawal_balance**: Sá»‘ dÆ° Ä‘ang treo do lá»‡nh rĂºt tiá»n.
- **total_earned**: Tá»•ng tiá»n há»‡ thá»‘ng Ä‘Ă£ thu há»™ tá»« trÆ°á»›c tá»›i nay.
- **total_withdrawn**: Tá»•ng tiá»n chá»§ sĂ¢n Ä‘Ă£ rĂºt ra thĂ nh cĂ´ng.

## TĂªn báº£ng: owner_wallet_ledgers

### 1. Má»¥c Ä‘Ă­ch báº£ng
Sá»• phá»¥ ghi chĂº tá»«ng biáº¿n Ä‘á»™ng cá»§a vĂ­ chá»§ sĂ¢n (cá»™ng tiá»n do booking, trá»« tiá»n do rĂºt).

### 2. Danh sĂ¡ch trÆ°á»ng (TĂ³m táº¯t)
- **id, owner_wallet_id, owner_id**: LiĂªn káº¿t vĂ­.
- **venue_cluster_id, booking_id, payment_id**: Giao dá»‹ch sinh ra biáº¿n Ä‘á»™ng.
- **type**: (credit, debit, hold, release).
- **amount, balance_before, balance_after**: LÆ°u láº¡i sá»‘ dÆ° táº¡i thá»i Ä‘iá»ƒm Ä‘Ă³ (nguyĂªn táº¯c káº¿ toĂ¡n kĂ©p).

## TĂªn báº£ng: platform_fee_tiers & venue_platform_fee_ledgers

### 1. Má»¥c Ä‘Ă­ch báº£ng
Quáº£n lĂ½ cĂ¡c gĂ³i thu phĂ­ (SaaS) mĂ  ná»n táº£ng thu cá»§a chá»§ sĂ¢n dá»±a trĂªn sá»‘ lÆ°á»£ng sĂ¢n con, vĂ  sá»• cĂ¡i theo dĂµi tĂ¬nh tráº¡ng thanh toĂ¡n gĂ³i phĂ­ cá»§a tá»«ng cá»¥m sĂ¢n theo thĂ¡ng/nÄƒm.

### MODULE: COMMUNITY & POSTS

## TĂªn báº£ng: community_posts

### 1. Má»¥c Ä‘Ă­ch báº£ng
LÆ°u trá»¯ bĂ i Ä‘Äƒng tháº£o luáº­n tá»± do cá»§a ngÆ°á»i dĂ¹ng trĂªn trang cá»™ng Ä‘á»“ng.

### 2. Danh sĂ¡ch trÆ°á»ng (TĂ³m táº¯t)
- **id, author_id**: NgÆ°á»i Ä‘Äƒng.
- **content**: Ná»™i dung bĂ i Ä‘Äƒng.
- **status**: (pending_review, published, rejected, hidden) Kiá»ƒm duyá»‡t.
- **view_count, like_count, comment_count**: Bá»™ Ä‘áº¿m tÆ°Æ¡ng tĂ¡c (denormalized).

## TĂªn báº£ng: community_post_comments & community_post_likes

### 1. Má»¥c Ä‘Ă­ch báº£ng
LÆ°u bĂ¬nh luáº­n vĂ  lÆ°á»£t thĂ­ch (Like) cá»§a bĂ i Ä‘Äƒng cá»™ng Ä‘á»“ng. Comment cĂ³ `parent_id` Ä‘á»ƒ táº¡o thread reply.

## TĂªn báº£ng: venue_posts

### 1. Má»¥c Ä‘Ă­ch báº£ng
LÆ°u trá»¯ cĂ¡c bĂ i viáº¿t, thĂ´ng bĂ¡o, quáº£ng bĂ¡ do chá»§ sĂ¢n Ä‘Äƒng cho cá»¥m sĂ¢n cá»§a mĂ¬nh.

### 2. Danh sĂ¡ch trÆ°á»ng (TĂ³m táº¯t)
- **id, venue_cluster_id, author_id**: LiĂªn káº¿t sĂ¢n vĂ  ngÆ°á»i viáº¿t.
- **content, status**: TÆ°Æ¡ng tá»± community_posts.

## TĂªn báº£ng: player_posts

### 1. Má»¥c Ä‘Ă­ch báº£ng
BĂ i Ä‘Äƒng "TĂ¬m kĂ¨o" hoáº·c "GhĂ©p Ä‘á»™i", báº¯t buá»™c pháº£i gáº¯n vá»›i má»™t `booking_id` Ä‘Ă£ Ä‘áº·t thĂ nh cĂ´ng Ä‘á»ƒ trĂ¡nh Ä‘Äƒng bĂ i áº£o.

### 2. Danh sĂ¡ch trÆ°á»ng (TĂ³m táº¯t)
- **id, booking_id, author_id**: BĂ i Ä‘Äƒng gáº¯n vá»›i booking nĂ o.
- **title, description**: TiĂªu Ä‘á» vĂ  mĂ´ táº£ trĂ¬nh Ä‘á»™/yĂªu cáº§u.
- **needed_players, cost_per_player**: Sá»‘ lÆ°á»£ng cáº§n thĂªm, giĂ¡ chia má»—i ngÆ°á»i.
- **status**: (open, full, closed, cancelled).

## TĂªn báº£ng: player_post_participants

### 1. Má»¥c Ä‘Ă­ch báº£ng
LÆ°u nhá»¯ng ngÆ°á»i dĂ¹ng gá»­i yĂªu cáº§u tham gia vĂ o "BĂ i tĂ¬m kĂ¨o" vĂ  tráº¡ng thĂ¡i duyá»‡t cá»§a chá»§ kĂ¨o.

### 2. Danh sĂ¡ch trÆ°á»ng (TĂ³m táº¯t)
- **post_id, user_id**: Ai xin vĂ o kĂ¨o nĂ o.
- **status**: (pending, approved, rejected, cancelled).
- **message**: Tin nháº¯n chĂ o há»i khi xin vĂ o.

## TĂªn báº£ng: player_ratings

### 1. Má»¥c Ä‘Ă­ch báº£ng
LÆ°u Ä‘Ă¡nh giĂ¡ (Rating) giá»¯a ngÆ°á»i chÆ¡i vá»›i nhau sau khi tham gia kĂ¨o thĂ nh cĂ´ng, giĂºp xĂ¢y dá»±ng uy tĂ­n cĂ¡ nhĂ¢n.

### 2. Danh sĂ¡ch trÆ°á»ng (TĂ³m táº¯t)
- **id, rater_id, rated_user_id**: Ai Ä‘Ă¡nh giĂ¡ ai.
- **post_id**: ÄĂ¡nh giĂ¡ dá»±a trĂªn kĂ¨o chÆ¡i chung nĂ o.
- **rating, comment, tags**: Äiá»ƒm vĂ  nháº­n xĂ©t (VD: "ChÆ¡i nhiá»‡t tĂ¬nh", "BĂ¹ng kĂ¨o").

## TĂªn báº£ng: hashtags & post_hashtags

### 1. Má»¥c Ä‘Ă­ch báº£ng
Quáº£n lĂ½ cĂ¡c hashtag gáº¯n vĂ o cĂ¡c bĂ i Ä‘Äƒng (cá»™ng Ä‘á»“ng, tĂ¬m kĂ¨o, v.v.). LiĂªn káº¿t `post_hashtags` lĂ  dáº¡ng hĂ¬nh thĂ¡i Ä‘a hĂ¬nh logic (`post_type` vĂ  `post_id`).

### MODULE: CHAT

## TĂªn báº£ng: conversations

### 1. Má»¥c Ä‘Ă­ch báº£ng
LÆ°u trá»¯ cĂ¡c phiĂªn há»™i thoáº¡i (phĂ²ng chat).

### 2. Danh sĂ¡ch trÆ°á»ng (TĂ³m táº¯t)
- **id, type**: Kiá»ƒu chat (direct cĂ¡ nhĂ¢n, player_post chat nhĂ³m tĂ¬m kĂ¨o, venue_contact chat vá»›i chá»§ sĂ¢n).
- **reference_type, reference_id**: LÆ°u ID Ä‘á»‘i tÆ°á»£ng liĂªn káº¿t tá»›i chat.
- **title**: TiĂªu Ä‘á» nhĂ³m chat.

## TĂªn báº£ng: conversation_participants

### 1. Má»¥c Ä‘Ă­ch báº£ng
LÆ°u trá»¯ danh sĂ¡ch nhá»¯ng ngÆ°á»i dĂ¹ng cĂ³ trong 1 conversation.
- **conversation_id, user_id**
- **last_read_at**: ÄĂ¡nh dáº¥u thá»i Ä‘iá»ƒm Ä‘á»c tin cuá»‘i Ä‘á»ƒ hiá»‡n Unread.

## TĂªn báº£ng: messages

### 1. Má»¥c Ä‘Ă­ch báº£ng
LÆ°u ná»™i dung tin nháº¯n trong phĂ²ng chat.
- **id, conversation_id, sender_id**
- **content**
- **is_system**: ÄĂ¡nh dáº¥u tin nháº¯n há»‡ thá»‘ng (VD: "A Ä‘Ă£ tham gia nhĂ³m").

---
*(Sáº½ tiáº¿p tá»¥c ná»‘i thĂªm pháº§n System & Config á»Ÿ pháº§n tiáº¿p theo)*
### MODULE: SYSTEM & REPORT

## TĂªn báº£ng: media

### 1. Má»¥c Ä‘Ă­ch báº£ng
Sá»­ dá»¥ng mĂ´ hĂ¬nh Ä‘a hĂ¬nh (Polymorphic) Ä‘á»ƒ lÆ°u trá»¯ má»i file Ä‘Ă­nh kĂ¨m (áº£nh sĂ¢n, avatar, file bĂ¡o cĂ¡o) cá»§a há»‡ thá»‘ng.

### 2. Danh sĂ¡ch trÆ°á»ng (TĂ³m táº¯t)
- **id**: PK
- **mediable_type, mediable_id**: Polymorphic liĂªn káº¿t vá»›i model (vĂ­ dá»¥: `App\Models\VenueCluster`, ID: `2000...`).
- **collection**: NhĂ³m file (vĂ­ dá»¥: `avatar`, `gallery`).
- **file_name, file_path**: TĂªn vĂ  Ä‘Æ°á»ng dáº«n váº­t lĂ½ (S3/local).
- **mime_type, file_size**: Metadata.

## TĂªn báº£ng: banners

### 1. Má»¥c Ä‘Ă­ch báº£ng
LÆ°u thĂ´ng tin banner quáº£ng cĂ¡o/sá»± kiá»‡n Ä‘á»ƒ hiá»ƒn thá»‹ linh Ä‘á»™ng trĂªn trang chá»§ hoáº·c á»©ng dá»¥ng.
- **image_path**: Link áº£nh banner.
- **link_url**: NÆ¡i chuyá»ƒn hÆ°á»›ng khi báº¥m.
- **position, sort_order**: Vá»‹ trĂ­ Ä‘áº·t vĂ  thá»© tá»±.
- **is_active, starts_at, ends_at**: Kiá»ƒm soĂ¡t thá»i gian cháº¡y banner.

## TĂªn báº£ng: partner_applications

### 1. Má»¥c Ä‘Ă­ch báº£ng
LÆ°u há»“ sÆ¡ ngÆ°á»i dĂ¹ng gá»­i lĂªn xin trá»Ÿ thĂ nh chá»§ sĂ¢n.

### 2. Danh sĂ¡ch trÆ°á»ng (TĂ³m táº¯t)
- **user_id**: KhĂ¡ch gá»­i Ä‘Æ¡n.
- **business_name, tax_code**: ThĂ´ng tin kinh doanh.
- **venue_name, venue_address, venue_latitude, venue_longitude**: ThĂ´ng tin cá»¥m sĂ¢n dá»± kiáº¿n táº¡o.
- **status**: (pending, reviewing, approved, rejected).
- **approved_venue_cluster_id**: Khi duyá»‡t xong sáº½ lÆ°u ID cá»¥m sĂ¢n tháº­t má»›i táº¡o.

## TĂªn báº£ng: partner_application_courts

### 1. Má»¥c Ä‘Ă­ch báº£ng
LÆ°u danh sĂ¡ch mĂ´n thá»ƒ thao mĂ  chá»§ sĂ¢n Ä‘Äƒng kĂ½ trong Ä‘Æ¡n xin lĂ m Ä‘á»‘i tĂ¡c.
- **partner_application_id, court_type_id**

## TĂªn báº£ng: system_policies & user_policy_acceptances

### 1. Má»¥c Ä‘Ă­ch báº£ng
- `system_policies`: LÆ°u trá»¯ cĂ¡c Ä‘iá»u khoáº£n, chĂ­nh sĂ¡ch hoáº¡t Ä‘á»™ng, cĂ³ Ä‘Ă¡nh version.
- `user_policy_acceptances`: Ghi nháº­n ngÆ°á»i dĂ¹ng nĂ o Ä‘Ă£ báº¥m "Äá»“ng Ă½" vá»›i phiĂªn báº£n chĂ­nh sĂ¡ch nĂ o (Ä‘á»ƒ phá»¥c vá»¥ tĂ­nh phĂ¡p lĂ½, giáº£i quyáº¿t khiáº¿u náº¡i).

## TĂªn báº£ng: verification_codes

### 1. Má»¥c Ä‘Ă­ch báº£ng
LÆ°u mĂ£ xĂ¡c thá»±c OTP dĂ¹ng cho viá»‡c Ä‘Äƒng kĂ½, xĂ¡c nháº­n sá»‘ Ä‘iá»‡n thoáº¡i, vĂ  quĂªn máº­t kháº©u.

### 2. Danh sĂ¡ch trÆ°á»ng (TĂ³m táº¯t)
- **user_id**: KhĂ³a ngoáº¡i (cĂ³ thá»ƒ null náº¿u chÆ°a táº¡o tĂ i khoáº£n lĂºc Ä‘Äƒng kĂ½).
- **identifier**: (VD: "nguyenvana@gmail.com").
- **type**: Má»¥c Ä‘Ă­ch mĂ£ (register, reset_password...).
- **channel**: (email, sms).
- **code**: Chuá»—i mĂ£ sinh ra.
- **expires_at**: Thá»i Ä‘iá»ƒm háº¿t háº¡n.

## TĂªn báº£ng: moderation_configs

### 1. Má»¥c Ä‘Ă­ch báº£ng
LÆ°u cĂ¡c cáº¥u hĂ¬nh há»‡ thá»‘ng dáº¡ng Key-Value (vd: tá»· lá»‡ hoa há»“ng tá»‘i Ä‘a, giá»›i háº¡n file Ä‘Ă­nh kĂ¨m).
- **key**: KhĂ³a chĂ­nh (string).
- **value, value_type**: GiĂ¡ trá»‹ lÆ°u dáº¡ng text vĂ  kiá»ƒu dá»¯ liá»‡u gá»‘c Ä‘á»ƒ parse.

## TĂªn báº£ng: audit_logs

### 1. Má»¥c Ä‘Ă­ch báº£ng
Lá»‹ch sá»­ kiá»ƒm toĂ¡n, ghi láº¡i má»i thao tĂ¡c quan trá»ng (thĂªm/sá»­a/xĂ³a báº£ng nháº¡y cáº£m) cá»§a báº¥t ká»³ ai.

### 2. Danh sĂ¡ch trÆ°á»ng (TĂ³m táº¯t)
- **actor_id**: NgÆ°á»i thá»±c hiá»‡n (admin/há»‡ thá»‘ng).
- **action**: MĂ£ thao tĂ¡c (vd: `venue.locked`).
- **entity_type, entity_id**: Äá»‘i tÆ°á»£ng bá»‹ thay Ä‘á»•i.
- **old_values, new_values**: JSON lÆ°u thay Ä‘á»•i trÆ°á»›c vĂ  sau.
- **ip_address, user_agent**: ThĂ´ng tin thiáº¿t bá»‹.

## TĂªn báº£ng: complaints

### 1. Má»¥c Ä‘Ă­ch báº£ng
LÆ°u khiáº¿u náº¡i cá»§a khĂ¡ch hĂ ng Ä‘á»‘i vá»›i sĂ¢n hoáº·c booking (VD: sĂ¢n Ä‘Ă³ng cá»­a, phá»¥c vá»¥ kĂ©m).
- **complaint_type**: (venue, system).
- **booking_id, venue_cluster_id**: LiĂªn quan tá»›i booking/sĂ¢n nĂ o.
- **customer_id, content**: NgÆ°á»i khiáº¿u náº¡i vĂ  ná»™i dung.
- **status**: QuĂ¡ trĂ¬nh xá»­ lĂ½ (open, processing, resolved).

## TĂªn báº£ng: reports

### 1. Má»¥c Ä‘Ă­ch báº£ng
Há»‡ thá»‘ng Report ná»™i dung xáº¥u (spam, vi pháº¡m) dĂ nh cho Community Posts, Player Posts hoáº·c bĂ¬nh luáº­n.
- **reporter_id**: NgÆ°á»i bĂ¡o cĂ¡o.
- **reportable_type, reportable_id**: Äá»‘i tÆ°á»£ng bá»‹ bĂ¡o cĂ¡o.
- **reason**: LĂ½ do vi pháº¡m chuáº©n má»±c.

## TĂªn báº£ng: reviews

### 1. Má»¥c Ä‘Ă­ch báº£ng
NgÆ°á»i dĂ¹ng Ä‘Ă¡nh giĂ¡ cháº¥t lÆ°á»£ng cá»§a cá»¥m sĂ¢n sau khi hoĂ n thĂ nh má»™t Booking.

### 2. Danh sĂ¡ch trÆ°á»ng (TĂ³m táº¯t)
- **booking_id**: (Unique) Má»™t booking chá»‰ Ä‘Æ°á»£c review 1 láº§n.
- **customer_id, venue_cluster_id**: Denormalized Ä‘á»ƒ query nhanh.
- **rating**: Äiá»ƒm (1-5).
- **comment**: Ná»™i dung khen/chĂª.
- **reply_content**: Chá»§ sĂ¢n pháº£n há»“i.

## TĂªn báº£ng: notifications

### 1. Má»¥c Ä‘Ă­ch báº£ng
Gá»­i thĂ´ng bĂ¡o Ä‘áº©y (Notification) vĂ o trung tĂ¢m thĂ´ng bĂ¡o cá»§a user trĂªn app/web.
- **user_id, type, title, body**.
- **reference_type, reference_id**: Trá» tá»›i mĂ n hĂ¬nh cáº§n chuyá»ƒn hÆ°á»›ng khi click.
- **is_read, read_at**: ÄĂ¡nh dáº¥u Ä‘Ă£ Ä‘á»c.

### MODULE: SYSTEM (LARAVEL DEFAULT)

## CĂ¡c báº£ng há»‡ thá»‘ng Laravel

CĂ¡c báº£ng nĂ y Ä‘Æ°á»£c Laravel tá»± Ä‘á»™ng sinh ra hoáº·c sá»­ dá»¥ng cho core framework:

1. **password_reset_tokens**: Báº£ng máº·c Ä‘á»‹nh há»— trá»£ cÆ¡ cháº¿ Reset Password cá»§a Laravel.
2. **sessions**: Báº£ng lÆ°u trá»¯ Session cá»§a user thay vĂ¬ lÆ°u trĂªn file, phá»¥c vá»¥ tĂ­nh nÄƒng quáº£n lĂ½ thiáº¿t bá»‹ Ä‘ang Ä‘Äƒng nháº­p.
3. **cache & cache_locks**: Báº£ng dĂ¹ng lĂ m Database Driver cho tĂ­nh nÄƒng Cache cá»§a Laravel, bao gá»“m tĂ­nh nÄƒng khĂ³a (Atomic Locks).
4. **jobs, job_batches, failed_jobs**: Báº£ng Queue lÆ°u trá»¯ hĂ ng Ä‘á»£i cĂ´ng viá»‡c ná»n (Background Jobs) nhÆ° gá»­i Email, thĂ´ng bĂ¡o cháº­m, dá»n dáº¹p data cÅ©.

---
**Ghi chĂº cuá»‘i bĂ¡o cĂ¡o:**
- BĂ¡o cĂ¡o nĂ y pháº£n Ă¡nh 100% nguyĂªn tráº¡ng tá»« file migration.
- Má»™t sá»‘ báº£ng nhÆ° `payments` cĂ³ field `system_bank_account_id` Ä‘Ă£ Ä‘Æ°á»£c update vĂ  loáº¡i bá» cá»•ng thanh toĂ¡n qua BankHub Ä‘á»ƒ thay tháº¿ hoĂ n toĂ n bá»Ÿi cáº¥u hĂ¬nh má»›i (`2026_05_29_000003...` vĂ  `2026_05_29_000004...`).
- CĂ¡c liĂªn káº¿t nhÆ° `mediable_type` / `reference_type` Ä‘á»u lĂ  *LiĂªn káº¿t logic Ä‘a hĂ¬nh (Polymorphic)* Ä‘Æ°á»£c validate á»Ÿ má»©c Service cá»§a Laravel chá»© khĂ´ng cĂ³ khĂ³a ngoáº¡i (Foreign Key) cá»©ng trĂªn MySQL.

==================================================
## PHẦN BỔ SUNG. THIẾT KẾ CHỨC NĂNG ADMIN THEO DB HIỆN TẠI
==================================================

Phần này đối chiếu trực tiếp các chức năng admin cần làm với schema hiện tại trong `database/migrations`. Nguyên tắc thiết kế:

- Chỉ dùng bảng/cột hiện có.
- Không tự giả định bảng nghiệp vụ chưa tồn tại.
- Chỗ DB đã đủ thì mô tả luồng hoàn chỉnh.
- Chỗ DB thiếu thì thiết kế bản tối thiểu hoặc ghi rõ chưa làm ở phase này.
- File đính kèm, ảnh, bằng chứng dùng chung bảng `media` qua `mediable_type`, `mediable_id`, `collection`.
- Lịch sử thao tác nhạy cảm dùng `audit_logs`.
- Thông báo trong hệ thống dùng `notifications`.

### 1. Admin duyệt hồ sơ đăng ký làm chủ sân

#### Mức đáp ứng DB

Đáp ứng một phần, đủ làm luồng duyệt hồ sơ cơ bản.

#### Bảng sử dụng

| Bảng | Vai trò |
|---|---|
| `partner_applications` | Hồ sơ đăng ký làm chủ sân |
| `partner_application_courts` | Danh sách sân con/loại sân ban đầu trong hồ sơ |
| `venue_clusters` | Cụm sân được tạo sau khi hồ sơ approved |
| `venue_courts` | Sân con ban đầu được tạo sau khi hồ sơ approved |
| `booking_configs` | Cấu hình booking mặc định cho cụm sân mới |
| `users` | Người gửi hồ sơ, người duyệt |
| `roles`, `user_roles` | Gán role `venue_owner` sau khi duyệt |
| `media` | Giấy tờ/hồ sơ đính kèm nếu có upload |
| `audit_logs` | Ghi lịch sử duyệt, từ chối, chuyển reviewing |
| `notifications` | Thông báo kết quả cho người gửi hồ sơ |

#### FE

Màn admin hiển thị danh sách hồ sơ đăng ký làm chủ sân:

- Người gửi.
- Tên đơn vị kinh doanh.
- Tên cơ sở/cụm sân dự kiến.
- Địa chỉ.
- Ngày gửi.
- Trạng thái: `pending`, `reviewing`, `approved`, `rejected`, `cancelled`.
- Nút xem chi tiết.

Màn chi tiết hồ sơ hiển thị:

- Thông tin người gửi từ `users`.
- Thông tin kinh doanh: `business_name`, `tax_code`.
- Thông tin cụm sân dự kiến: `venue_name`, `venue_address`, `venue_map_url`, `venue_latitude`, `venue_longitude`.
- Danh sách sân con ban đầu từ `partner_application_courts`.
- Giấy tờ đính kèm từ `media` với `collection = partner_application_documents`.
- Lịch sử xử lý từ `audit_logs`.

#### BE

API admin cần có:

- `GET /api/admin/partner-applications`
- `GET /api/admin/partner-applications/{id}`
- `POST /api/admin/partner-applications/{id}/reviewing`
- `POST /api/admin/partner-applications/{id}/approve`
- `POST /api/admin/partner-applications/{id}/reject`

Luồng duyệt:

1. Chỉ xử lý hồ sơ đang `pending` hoặc `reviewing`.
2. Khi chuyển `reviewing`:
   - Set `status = reviewing`.
   - Set `reviewed_by`, `reviewed_at`.
   - Ghi `audit_logs`.
3. Khi từ chối:
   - Bắt buộc nhập `status_reason`.
   - Set `status = rejected`.
   - Set `reviewed_by`, `reviewed_at`.
   - Ghi `audit_logs`.
   - Tạo `notifications` cho người gửi.
4. Khi duyệt:
   - Tạo `venue_clusters` từ dữ liệu hồ sơ.
   - `owner_id = partner_applications.user_id`.
   - `status = active` hoặc `pending` tùy chính sách vận hành. Nếu admin đã duyệt hồ sơ thì nên dùng `active`.
   - Tạo `venue_courts` từ `partner_application_courts`.
   - Tạo `booking_configs` mặc định cho cụm sân.
   - Gán role `venue_owner` cho user qua `user_roles` với `scope_type = venue`, `scope_id = venue_clusters.id`.
   - Set `partner_applications.status = approved`.
   - Set `approved_venue_cluster_id = venue_clusters.id`.
   - Ghi `audit_logs`.
   - Tạo `notifications`.

#### Không làm theo DB hiện tại

- Chưa có bảng riêng cho tài khoản nhận tiền của owner. Không lưu tài khoản nhận tiền trong hồ sơ ở phase này.
- Nếu cần lưu tài khoản nhận tiền để rút tiền thật, cần bổ sung bảng kiểu `owner_bank_accounts` hoặc `withdrawal_accounts`.
- Giấy tờ pháp lý không có cột riêng trong `partner_applications`; nếu cần lưu file thì dùng `media`.

### 2. Admin quản lý banner client

#### Mức đáp ứng DB

Đáp ứng tốt.

#### Bảng sử dụng

| Bảng | Vai trò |
|---|---|
| `banners` | Cấu hình banner |
| `users` | `created_by`, `updated_by` |
| `audit_logs` | Ghi thao tác thêm/sửa/tắt banner |

#### FE

Màn danh sách banner:

- Ảnh.
- Tiêu đề.
- Vị trí hiển thị: `position`.
- Link: `link_url`.
- Thời gian bắt đầu/kết thúc: `starts_at`, `ends_at`.
- Trạng thái bật/tắt: `is_active`.
- Thứ tự: `sort_order`.
- Action: thêm, sửa, bật/tắt, xem trước.

Form thêm/sửa:

- `title`.
- Upload ảnh hoặc nhập `image_path`.
- `link_url`.
- `position`.
- `starts_at`, `ends_at`.
- `sort_order`.
- `is_active`.

#### BE

API admin:

- `GET /api/admin/banners`
- `POST /api/admin/banners`
- `GET /api/admin/banners/{id}`
- `PUT /api/admin/banners/{id}`
- `PATCH /api/admin/banners/{id}/toggle`
- `DELETE /api/admin/banners/{id}`

API client:

- `GET /api/banners?position=home`

Rule trả banner cho client:

- `is_active = true`.
- `starts_at IS NULL OR starts_at <= now()`.
- `ends_at IS NULL OR ends_at >= now()`.
- Order theo `sort_order`, sau đó `created_at`.

### 3. Admin quản lý cụm sân toàn hệ thống

#### Mức đáp ứng DB

Đáp ứng tốt.

#### Bảng sử dụng

| Bảng | Vai trò |
|---|---|
| `venue_clusters` | Cụm sân |
| `venue_courts` | Sân con |
| `booking_configs` | Cấu hình booking |
| `price_slots` | Giá thường |
| `holiday_prices` | Giá ngày lễ/ngày đặc biệt |
| `bookings`, `booking_items` | Lịch booking liên quan |
| `venue_platform_fee_ledgers` | Trạng thái phí duy trì |
| `media` | Ảnh/gallery cụm sân |
| `audit_logs` | Lịch sử khóa/mở khóa/cập nhật |

#### FE

Màn danh sách cụm sân:

- Tên cụm sân.
- Owner.
- Địa chỉ.
- Số sân con.
- Loại sân.
- Rating.
- Trạng thái cụm: `pending`, `active`, `locked`.
- Trạng thái phí gần nhất: `pending`, `paid`, `overdue`, `cancelled`.
- Action: xem chi tiết, khóa, mở khóa.

Filter:

- Trạng thái cụm.
- Owner.
- Tên cụm sân.
- Loại sân.
- Trạng thái phí.

Màn chi tiết cụm sân gồm tab:

- Thông tin: tên, địa chỉ, map, tọa độ, mô tả, tiện ích, phone.
- Sân con.
- Booking.
- Giá.
- Phí duy trì.
- Lịch sử khóa/mở khóa.

#### BE

API admin:

- `GET /api/admin/venue-clusters`
- `GET /api/admin/venue-clusters/{id}`
- `POST /api/admin/venue-clusters/{id}/lock`
- `POST /api/admin/venue-clusters/{id}/unlock`

Rule khóa:

- Khi khóa, bắt buộc nhập lý do.
- Update `venue_clusters.status = locked`.
- Set `status_reason`, `locked_at`, `locked_by`.
- Ghi `audit_logs` action `venue.locked`.
- Cụm `locked` không hiển thị cho client đặt sân, owner không được sửa cấu hình/sân con/giá.

Rule mở khóa:

- Update `venue_clusters.status = active`.
- Clear hoặc giữ `status_reason` tùy nhu cầu hiển thị lịch sử. Lịch sử chuẩn lấy từ `audit_logs`.
- Ghi `audit_logs` action `venue.unlocked`.

#### Giới hạn theo DB hiện tại

- Không có bảng lưu riêng lịch sử khóa cụm sân. Dùng `audit_logs`.
- Không nên cho admin sửa tùy tiện địa chỉ/tọa độ/thông tin pháp lý đã duyệt; nếu cần sửa, ghi audit rõ lý do.

### 4. Admin quản lý tài khoản, nhân sự hệ thống và phân quyền

#### Mức đáp ứng DB

Đáp ứng tốt.

#### Bảng sử dụng

| Bảng | Vai trò |
|---|---|
| `users` | Tài khoản |
| `roles` | Nhóm quyền |
| `permissions` | Quyền |
| `role_permissions` | Quyền theo role |
| `user_roles` | Role của user, có scope `system`/`venue` |
| `user_permission_revokes` | Thu hồi quyền riêng của một user |
| `personal_access_tokens` | Token đăng nhập |
| `audit_logs` | Lịch sử khóa/mở/gán role/revoke |

#### FE

Màn danh sách user:

- Tên, email, phone.
- Role hiện tại.
- Role group: admin, owner, staff, user.
- Scope quyền nếu là role theo venue.
- Trạng thái: `pending_verify`, `active`, `locked`, `deactivated`.
- Ngày tạo.
- Action: xem, khóa, mở khóa, gán role, thu hồi quyền.

Màn chi tiết user:

- Hồ sơ cơ bản.
- Danh sách role/scope từ `user_roles`.
- Danh sách quyền bị thu hồi từ `user_permission_revokes`.
- Lịch sử audit.
- Nút revoke token khi khóa.

#### BE

API admin:

- `GET /api/admin/users`
- `GET /api/admin/users/{id}`
- `POST /api/admin/users/{id}/lock`
- `POST /api/admin/users/{id}/unlock`
- `POST /api/admin/users/{id}/roles`
- `DELETE /api/admin/users/{id}/roles/{userRoleId}`
- `POST /api/admin/users/{id}/permission-revokes`
- `DELETE /api/admin/users/{id}/permission-revokes/{id}`

Rule:

- Khóa tài khoản bắt buộc nhập `status_reason`.
- Khi khóa user, revoke token trong `personal_access_tokens`.
- Gán role phải kiểm tra scope:
  - Role hệ thống dùng `scope_type = system`, `scope_id = 00000000-0000-0000-0000-000000000000`.
  - Role sân dùng `scope_type = venue`, `scope_id = venue_clusters.id`.
- Không gán role ngoài 6 role chuẩn nếu hệ thống đang chốt 6 role.

### 5. Admin quản lý nhóm quyền

#### Mức đáp ứng DB

Đáp ứng tốt.

#### Bảng sử dụng

| Bảng | Vai trò |
|---|---|
| `roles` | Role |
| `permissions` | Permission |
| `role_permissions` | Ma trận role - permission |
| `user_roles` | Kiểm tra role đang được dùng |
| `audit_logs` | Ghi thay đổi role/permission |

#### FE

Màn danh sách role:

- Mã role.
- Tên hiển thị.
- Mô tả.
- `is_system`.
- Số quyền.
- Số user đang dùng.

Màn chi tiết role dạng ma trận quyền:

- Group theo `permissions.group_name`.
- Checkbox theo `permissions.code`.
- Chỉ role không nguy hiểm mới cho sửa toàn bộ.
- Role hệ thống (`is_system = true`) nên hạn chế đổi mã/xóa.

#### BE

API admin:

- `GET /api/admin/roles`
- `GET /api/admin/permissions`
- `POST /api/admin/roles`
- `PUT /api/admin/roles/{id}`
- `PUT /api/admin/roles/{id}/permissions`

Rule:

- `roles.name` unique.
- `permissions.code` unique.
- Không xóa role đang có user nếu không có chính sách chuyển role.
- Không sửa/xóa role hệ thống nguy hiểm như `super_admin` nếu không có xác nhận đặc biệt.

### 6. Admin theo dõi thanh toán booking

#### Mức đáp ứng DB

Đáp ứng tốt cho thanh toán booking online.

#### Bảng sử dụng

| Bảng | Vai trò |
|---|---|
| `bookings` | Đơn booking |
| `booking_items` | Các khung giờ/sân trong booking |
| `payments` | Giao dịch thanh toán |
| `payment_logs` | Log gateway |
| `system_bank_accounts` | Tài khoản hệ thống nhận tiền |
| `owner_wallets` | Ví owner được cộng tiền thu hộ |
| `owner_wallet_ledgers` | Lịch sử cộng/trừ ví owner |
| `refunds` | Hoàn tiền nếu phát sinh |

#### FE

Màn danh sách payment:

- Mã thanh toán: `payment_code`.
- Booking: `booking_code`.
- Khách.
- Cụm sân.
- Số tiền.
- Loại thanh toán: `full`, `deposit`, `partial`.
- Phương thức: `method`.
- Trạng thái: `pending`, `paid`, `failed`, `refunded`.
- Thời gian thanh toán: `paid_at`.
- Gateway transaction: `gateway_txn_id`.
- Action: xem chi tiết.

Màn chi tiết:

- Booking liên quan.
- Payment data.
- Gateway response.
- Payment logs.
- Tài khoản hệ thống nhận tiền.
- Dòng ví owner liên quan nếu đã paid.

#### BE

API admin:

- `GET /api/admin/payments`
- `GET /api/admin/payments/{id}`
- `GET /api/admin/payments/{id}/logs`

Rule:

- Mỗi retry/attempt từ gateway cần có log trong `payment_logs`.
- Khi payment `paid`, hệ thống cập nhật booking và cộng ví owner qua `owner_wallets`, `owner_wallet_ledgers`.
- Tiền booking online là hệ thống thu hộ chủ sân, không chuyển thẳng vào tài khoản owner.

### 7. Admin xử lý hoàn tiền và rút tiền owner

#### Mức đáp ứng DB

Hoàn tiền đáp ứng một phần tốt. Rút tiền owner chưa đủ DB để làm luồng approve/reject/complete đầy đủ.

#### Bảng sử dụng cho hoàn tiền

| Bảng | Vai trò |
|---|---|
| `refunds` | Yêu cầu/phiếu hoàn tiền |
| `payments` | Payment gốc |
| `bookings` | Booking liên quan |
| `users` | Khách và admin xử lý |
| `audit_logs` | Lịch sử xử lý |
| `notifications` | Thông báo kết quả |

#### FE hoàn tiền

Tab "Hoàn tiền":

- Booking.
- Khách.
- Payment gốc.
- Số tiền yêu cầu hoàn.
- Lý do.
- Trạng thái: `pending_confirmation`, `processing`, `completed`, `failed`, `rejected`.
- Người xử lý.
- Thời gian xử lý.
- Action: duyệt xử lý, hoàn tất, từ chối.

#### BE hoàn tiền

API admin:

- `GET /api/admin/refunds`
- `GET /api/admin/refunds/{id}`
- `POST /api/admin/refunds/{id}/process`
- `POST /api/admin/refunds/{id}/complete`
- `POST /api/admin/refunds/{id}/reject`

Rule:

- Từ chối bắt buộc nhập `status_reason`.
- Khi hoàn tất:
  - Set `status = completed`.
  - Set `processed_by`, `processed_at`.
  - Có thể update `payments.status = refunded` nếu hoàn toàn bộ.
  - Ghi `audit_logs`.

#### Thiết kế tối thiểu cho rút tiền owner theo DB hiện tại

DB có:

- `owner_wallets.available_balance`.
- `owner_wallets.pending_withdrawal_balance`.
- `owner_wallets.total_withdrawn`.
- `owner_wallet_ledgers` với type `credit`, `debit`, `hold`, `release`.

Có thể làm màn xem số dư và lịch sử ví:

- Owner.
- Số dư có thể rút.
- Số dư đang giữ.
- Tổng đã thu.
- Tổng đã rút.
- Ledger theo thời gian.

Không nên làm approve/reject/complete yêu cầu rút tiền đầy đủ vì thiếu bảng yêu cầu rút tiền.

#### Không làm theo DB hiện tại

- Chưa có bảng `withdrawal_requests`.
- Chưa có `withdrawal_status`, `requested_amount`, `approved_by`, `rejected_reason`, `completed_at`.
- Chưa có bảng tài khoản nhận tiền của owner.
- Nếu cần đúng nghiệp vụ rút tiền, cần bổ sung schema ở phase sau.

### 8. Admin xử lý báo xấu/report

#### Mức đáp ứng DB

Đáp ứng khá tốt.

#### Bảng sử dụng

| Bảng | Vai trò |
|---|---|
| `reports` | Báo cáo vi phạm |
| `media` | Bằng chứng đính kèm |
| `community_posts`, `venue_posts`, `player_posts`, `community_post_comments` | Đối tượng bị report tùy `reportable_type` |
| `users` | Người báo cáo, người bị báo cáo nếu target là user |
| `audit_logs` | Lịch sử xử lý |
| `notifications` | Thông báo cho người báo cáo/người bị xử lý |

#### FE

Màn danh sách report:

- Loại đối tượng bị báo cáo.
- Người gửi báo cáo.
- Lý do: `spam`, `offensive`, `fake`, `harassment`, `other`.
- Mô tả.
- Trạng thái: `pending`, `reviewing`, `resolved`, `dismissed`.
- Hành động đã áp dụng: `warning`, `content_hidden`, `content_deleted`, `account_locked`, `venue_locked`.
- Thời gian gửi.
- Action: xem chi tiết, nhận xử lý.

Màn chi tiết:

- Nội dung report.
- Link đến đối tượng bị báo cáo.
- Bằng chứng từ `media` với `collection = report_evidence`.
- Lịch sử xử lý.
- Form xử lý.

#### BE

API admin/moderator:

- `GET /api/admin/reports`
- `GET /api/admin/reports/{id}`
- `POST /api/admin/reports/{id}/reviewing`
- `POST /api/admin/reports/{id}/dismiss`
- `POST /api/admin/reports/{id}/resolve`

Rule:

- Bỏ qua report: set `status = dismissed`, bắt buộc `action_note`.
- Xử lý report: set `status = resolved`, bắt buộc `action_taken`, `action_note`.
- Nếu action là ẩn/xóa nội dung:
  - Với `community_posts`, `venue_posts`: update `status = hidden` hoặc trạng thái phù hợp.
  - Với comment nếu chưa có trạng thái moderation riêng thì chưa xóa cứng, chỉ ghi audit hoặc cần bổ sung schema.
- Nếu action là khóa tài khoản: update `users.status = locked`, set lý do.
- Nếu action là khóa cụm sân: update `venue_clusters.status = locked`, set lý do.
- Mọi action nhạy cảm ghi `audit_logs`.

#### Giới hạn theo DB hiện tại

- `reports.reportable_type/reportable_id` là logical reference, không có FK cứng.
- Không có bảng riêng cho lịch sử xử lý nhiều bước; dùng `audit_logs`.

### 9. Admin xử lý khiếu nại

#### Mức đáp ứng DB

Đáp ứng khá tốt cho luồng xử lý cơ bản.

#### Bảng sử dụng

| Bảng | Vai trò |
|---|---|
| `complaints` | Khiếu nại |
| `bookings` | Booking liên quan nếu có |
| `venue_clusters` | Cụm sân liên quan nếu có |
| `users` | Khách gửi, nhân sự xử lý |
| `media` | File bằng chứng |
| `audit_logs` | Lịch sử xử lý |
| `notifications` | Thông báo |

#### FE

Màn danh sách khiếu nại:

- Loại: `venue`, `system`.
- Khách gửi.
- Booking/cụm sân liên quan.
- Nội dung tóm tắt.
- Trạng thái: `open`, `processing`, `resolved`, `rejected`, `closed`.
- Người được phân công.
- Thời gian tạo.
- Action: xem chi tiết, nhận xử lý, phân công.

Màn chi tiết:

- Nội dung khiếu nại.
- Booking/cụm sân liên quan.
- Bằng chứng từ `media` với `collection = complaint_evidence`.
- Người đang xử lý.
- Ghi chú giải quyết.
- Lịch sử audit.

#### BE

API admin:

- `GET /api/admin/complaints`
- `GET /api/admin/complaints/{id}`
- `POST /api/admin/complaints/{id}/assign`
- `POST /api/admin/complaints/{id}/processing`
- `POST /api/admin/complaints/{id}/resolve`
- `POST /api/admin/complaints/{id}/reject`
- `POST /api/admin/complaints/{id}/close`

Rule:

- Phân công: set `assigned_to`.
- Nhận xử lý: set `status = processing`.
- Giải quyết: set `status = resolved`, set `resolve_note` hoặc `resolution_note` theo schema đang migrate, set `resolved_by`, `resolved_at`.
- Từ chối/đóng: bắt buộc nhập `status_reason`.
- Ghi `audit_logs` và gửi `notifications`.

#### Giới hạn theo DB hiện tại

- Lịch sử trao đổi nhiều tin nhắn giữa admin và khách chưa có bảng riêng cho complaint conversation. Nếu cần trao đổi nhiều vòng, dùng `audit_logs`/`notifications` ở phase này hoặc bổ sung bảng sau.

### 10. Admin cấu hình phí duy trì hệ thống

#### Mức đáp ứng DB

Đáp ứng một phần. Làm được bậc phí và sổ phí theo kỳ tháng/năm. Chưa đáp ứng đầy đủ kỳ 1/3/6/9/12 tháng và chứng từ thanh toán có trạng thái duyệt riêng.

#### Bảng sử dụng

| Bảng | Vai trò |
|---|---|
| `platform_fee_tiers` | Bậc phí theo số sân con |
| `venue_platform_fee_ledgers` | Sổ phí duy trì theo cụm sân/kỳ |
| `venue_clusters` | Cụm sân bị tính phí/khóa nếu quá hạn |
| `venue_courts` | Đếm số sân con |
| `system_bank_accounts` | Tài khoản hệ thống nhận phí |
| `media` | Bằng chứng thanh toán phí |
| `audit_logs` | Lịch sử tạo kỳ phí, xác nhận, từ chối, khóa cụm |
| `notifications` | Thông báo owner |

#### FE

Màn cấu hình bậc phí:

- Tên bậc.
- Số sân tối thiểu: `min_courts`.
- Số sân tối đa: `max_courts`.
- Giá/sân/tháng: `price_per_court_month`.
- Giảm giá năm: `annual_discount_percent`.
- Trạng thái: `is_active`.
- Ngày hiệu lực: `effective_from`.

Màn sao kê kỳ phí:

- Cụm sân.
- Owner.
- Số sân con: `court_count`.
- Chu kỳ: `billing_cycle` gồm `monthly`, `yearly`.
- Thời gian kỳ: `period_start` - `period_end`.
- Hạn đóng: dùng `period_end` vì DB chưa có `due_date`.
- Bậc phí.
- Số tiền phải thu: `amount_due`.
- Đã thu: `amount_paid`.
- Còn thiếu: `amount_due - amount_paid`.
- Trạng thái: `pending`, `paid`, `overdue`, `cancelled`.
- Ngày thanh toán: `paid_at`.
- Bằng chứng thanh toán từ `media` với `collection = platform_fee_payment_proof`.
- Action: xem chi tiết, xác nhận thanh toán, từ chối bằng chứng, đánh dấu quá hạn, khóa cụm.

#### BE

API admin:

- `GET /api/admin/platform-fee-tiers`
- `POST /api/admin/platform-fee-tiers`
- `PUT /api/admin/platform-fee-tiers/{id}`
- `GET /api/admin/platform-fee-ledgers`
- `POST /api/admin/platform-fee-ledgers`
- `GET /api/admin/platform-fee-ledgers/{id}`
- `POST /api/admin/platform-fee-ledgers/{id}/confirm-payment`
- `POST /api/admin/platform-fee-ledgers/{id}/reject-payment`
- `POST /api/admin/platform-fee-ledgers/{id}/mark-overdue`
- `POST /api/admin/platform-fee-ledgers/{id}/lock-venue`

Rule tạo kỳ phí:

1. Chọn cụm sân và kỳ.
2. Đếm sân con từ `venue_courts`.
3. Chọn bậc active trong `platform_fee_tiers` theo `min_courts`, `max_courts`.
4. Tính tiền:
   - Monthly: `court_count * price_per_court_month`.
   - Yearly: `court_count * price_per_court_month * 12`, trừ `annual_discount_percent`.
5. Tạo `venue_platform_fee_ledgers` với `status = pending`, `amount_paid = 0`.

Rule xác nhận thanh toán:

- Nhập `amount_paid`, `paid_at`.
- Upload bằng chứng vào `media`.
- Nếu `amount_paid >= amount_due`: set `status = paid`.
- Nếu chưa đủ: giữ `pending` hoặc `overdue` tùy hạn.
- Ghi `audit_logs`.

Rule từ chối bằng chứng:

- DB không có `rejected` status, `reject_reason`, `rejected_by`, `rejected_at`.
- Vì vậy không đổi sang trạng thái `rejected`.
- Giữ ledger ở `pending` hoặc `overdue`.
- Ghi lý do từ chối vào `audit_logs.new_values.reason`.
- Thông báo owner qua `notifications`.

Rule quá hạn và khóa cụm:

- Nếu `status = pending`, `period_end < today`, `amount_paid < amount_due` thì set `status = overdue`.
- Nếu quá hạn cần khóa cụm:
  - Set `venue_clusters.status = locked`.
  - Set `status_reason = "Cụm sân bị khóa do quá hạn thanh toán phí duy trì nền tảng."`.
  - Set `locked_at`, `locked_by`.
  - Ghi `audit_logs`.

#### Không làm theo DB hiện tại

- Không có `due_date`; dùng `period_end` làm hạn đóng.
- Không có kỳ 3/6/9 tháng trong enum `billing_cycle`; chỉ làm `monthly` và `yearly`.
- Không có bảng invoice/receipt; phiếu nội bộ chỉ xuất động từ ledger, không lưu lâu dài.
- Không có trạng thái `rejected` cho ledger; từ chối bằng chứng chỉ ghi audit.

### 11. Admin/Moderator kiểm duyệt nội dung

#### Mức đáp ứng DB

Đáp ứng tốt cho bài cộng đồng và bài cụm sân. Một số loại nội dung như bình luận chưa có trạng thái moderation riêng, cần xử lý hạn chế.

#### Bảng sử dụng

| Bảng | Vai trò |
|---|---|
| `community_posts` | Bài cộng đồng |
| `venue_posts` | Bài viết của chủ sân |
| `player_posts` | Bài tìm người chơi |
| `community_post_comments` | Bình luận cộng đồng |
| `reports` | Report nội dung |
| `hashtags`, `post_hashtags` | Hashtag dùng chung |
| `media` | Ảnh/file đính kèm |
| `audit_logs` | Lịch sử duyệt/ẩn/từ chối |
| `notifications` | Thông báo tác giả |

#### FE

Màn hàng chờ kiểm duyệt:

- Loại nội dung: bài cộng đồng, bài cụm sân, report nội dung.
- Tác giả.
- Nội dung tóm tắt.
- Ảnh/file.
- Hashtag.
- Trạng thái.
- Lý do report nếu vào từ `reports`.
- Action: duyệt, ẩn, từ chối, xóa theo quyền.

Màn chi tiết:

- Nội dung đầy đủ.
- Tác giả.
- File/ảnh.
- Hashtag.
- Lịch sử xử lý.
- Form nhập lý do khi từ chối/ẩn.

#### BE

API admin/moderator:

- `GET /api/admin/moderation/queue`
- `GET /api/admin/moderation/items/{type}/{id}`
- `POST /api/admin/moderation/items/{type}/{id}/approve`
- `POST /api/admin/moderation/items/{type}/{id}/hide`
- `POST /api/admin/moderation/items/{type}/{id}/reject`

Rule:

- Bài cộng đồng và bài cụm sân dùng status:
  - `pending_review`
  - `published`
  - `rejected`
  - `hidden`
- Duyệt: set `status = published`, set `reviewed_by`, `reviewed_at`.
- Từ chối/ẩn: bắt buộc `status_reason`.
- Hashtag dùng chung qua `post_hashtags`.
- Gửi `notifications` cho tác giả.
- Ghi `audit_logs`.

#### Giới hạn theo DB hiện tại

- `community_post_comments` hiện không có `status`, `reviewed_by`, `status_reason`; chưa nên làm duyệt/ẩn bình luận đầy đủ nếu không bổ sung schema.
- `reports` có thể xử lý report bình luận bằng action audit, nhưng không có cột status trên comment để ẩn mềm chuẩn.

### 12. Tổng hợp phần nên làm và chưa nên làm

#### Nên làm ngay theo DB hiện tại

| Chức năng | Lý do |
|---|---|
| Quản lý banner | DB đủ rõ |
| Quản lý cụm sân toàn hệ thống | DB đủ trạng thái, khóa, sân con, giá, booking config |
| Quản lý user/role/permission | DB đủ RBAC và revoke |
| Theo dõi thanh toán booking | DB đủ payments/payment_logs/wallet |
| Hoàn tiền booking | DB đủ refunds cơ bản |
| Report | DB đủ report/action/audit |
| Complaint | DB đủ complaint/assign/resolve cơ bản |
| Moderation bài cộng đồng/bài cụm sân | DB có status moderation |
| Duyệt hồ sơ chủ sân bản cơ bản | DB đủ application/court/create venue |

#### Chưa nên làm đầy đủ nếu không bổ sung DB

| Chức năng | Thiếu gì |
|---|---|
| Lưu tài khoản nhận tiền owner trong hồ sơ chủ sân | Thiếu bảng/cột tài khoản nhận tiền owner |
| Rút tiền owner approve/reject/complete | Thiếu bảng `withdrawal_requests` |
| Phí duy trì kỳ 3/6/9 tháng | `venue_platform_fee_ledgers.billing_cycle` chỉ có `monthly`, `yearly` |
| Từ chối bằng chứng phí duy trì bằng trạng thái riêng | Ledger thiếu `rejected`, `reject_reason`, `rejected_by`, `rejected_at` |
| Lưu hóa đơn/phiếu nội bộ lâu dài | Thiếu bảng invoice/receipt |
| Kiểm duyệt bình luận đầy đủ | Comment thiếu status moderation |

#### Đề xuất nếu phase sau được phép sửa DB

Nếu cần làm đủ 100% các chức năng trong mô tả ban đầu, nên bổ sung các bảng/cột sau:

- `owner_bank_accounts`: lưu tài khoản nhận tiền của chủ sân.
- `owner_withdrawal_requests`: lưu yêu cầu rút tiền, trạng thái, lý do từ chối, người duyệt, thời gian hoàn tất.
- Bổ sung `billing_cycle` hoặc `period_months` cho `venue_platform_fee_ledgers` để hỗ trợ kỳ 1/3/6/9/12 tháng.
- Bổ sung `due_date` cho `venue_platform_fee_ledgers`.
- Bổ sung trạng thái/lý do từ chối bằng chứng phí: `rejected`, `reject_reason`, `rejected_by`, `rejected_at`.
- `internal_receipts` hoặc `invoices` nếu cần lưu phiếu/hóa đơn nội bộ cố định.
- Bổ sung moderation fields cho comment: `status`, `reviewed_by`, `reviewed_at`, `status_reason`.

==================================================
## PHẦN CẬP NHẬT SAU MIGRATION 2026-05-30
==================================================

Các migration mới đã được bổ sung để DB đáp ứng tốt hơn các chức năng admin trong phần mô tả nghiệp vụ.

### 1. Bảng/cột mới

| Migration | Nội dung |
|---|---|
| `2026_05_30_000002_create_owner_bank_accounts_table` | Thêm bảng `owner_bank_accounts` để lưu tài khoản nhận tiền của chủ sân, liên kết được với hồ sơ đăng ký chủ sân. |
| `2026_05_30_000003_create_owner_withdrawal_requests_table` | Thêm bảng `owner_withdrawal_requests` để quản lý yêu cầu rút tiền: pending, reviewing, approved, rejected, completed, cancelled. |
| `2026_05_30_000004_create_internal_receipts_table` | Thêm bảng `internal_receipts` để lưu phiếu/hóa đơn nội bộ cho phí duy trì, rút tiền, refund, payment. |
| `2026_05_30_000005_extend_platform_fee_ledgers_for_admin_workflow` | Bổ sung `period_months`, `due_date`, thông tin bằng chứng thanh toán, xác nhận/từ chối, phiếu nội bộ cho `venue_platform_fee_ledgers`. |
| `2026_05_30_000006_add_moderation_fields_to_community_post_comments` | Bổ sung người xử lý, thời điểm xử lý, lý do xử lý cho `community_post_comments`. |
| `2026_05_30_000007_expand_payment_methods_for_system_collection` | Mở rộng `payments.method` để hỗ trợ `sepay`, `bank_transfer`, `cash` bên cạnh các cổng cũ. |
| `2026_05_30_000008_ensure_booking_runtime_columns_exist` | Đảm bảo fresh DB luôn có các cột runtime booking: sân con thực tế/yêu cầu, giờ bắt đầu/kết thúc, thời lượng và thông tin đổi sân. |

### 2. Các chức năng đã được DB đáp ứng sau cập nhật

#### Duyệt hồ sơ đăng ký làm chủ sân

DB hiện hỗ trợ:

- Hồ sơ đăng ký qua `partner_applications`.
- Sân con ban đầu qua `partner_application_courts`.
- Giấy tờ qua `media`.
- Tài khoản nhận tiền qua `owner_bank_accounts`.
- Khi duyệt có thể tạo `venue_clusters`, `venue_courts`, gán `user_roles`, lưu audit và notification.

#### Rút tiền chủ sân

DB hiện hỗ trợ đầy đủ luồng cơ bản:

- Ví chủ sân: `owner_wallets`.
- Lịch sử biến động ví: `owner_wallet_ledgers`.
- Tài khoản nhận tiền: `owner_bank_accounts`.
- Yêu cầu rút tiền: `owner_withdrawal_requests`.
- Phiếu chi nội bộ: `internal_receipts`.
- Audit và notification khi approve/reject/complete.

#### Sao kê phí duy trì

DB hiện hỗ trợ:

- Bậc phí theo số sân: `platform_fee_tiers`.
- Sổ phí theo cụm/kỳ: `venue_platform_fee_ledgers`.
- Kỳ 1/3/6/9/12 tháng qua `period_months`.
- Hạn đóng qua `due_date`.
- Bằng chứng thanh toán qua `media` và `payment_proof_media_id`.
- Trạng thái duyệt bằng chứng qua `payment_proof_status`.
- Lý do từ chối qua `payment_reject_reason`.
- Phiếu/hóa đơn nội bộ qua `internal_receipt_id`.
- Khóa cụm sân quá hạn bằng `venue_clusters.status = locked`, kèm `status_reason`, `locked_at`, `locked_by`.

#### Kiểm duyệt bình luận

DB hiện hỗ trợ:

- Ẩn/hiện bình luận bằng `community_post_comments.status`.
- Lưu người xử lý qua `reviewed_by`.
- Lưu thời điểm xử lý qua `reviewed_at`.
- Lưu lý do qua `status_reason`.

#### Thanh toán booking

DB hiện hỗ trợ:

- Payment booking qua `payments`.
- Log gateway qua `payment_logs`.
- Tài khoản hệ thống nhận tiền qua `system_bank_accounts`.
- Phương thức `sepay`, `bank_transfer`, `cash`, `vnpay`, `momo`, `zalopay`.
- Tiền online thu hộ chủ sân qua `owner_wallets` và `owner_wallet_ledgers`.

#### Booking runtime columns

DB fresh hiện có đủ các cột mà booking API/service đang dùng trực tiếp:

- `venue_court_id`: sân con thực tế.
- `requested_venue_court_id`: sân con khách chọn ban đầu.
- `start_time`, `end_time`, `duration_minutes`: khoảng giờ chơi chính của booking.
- `court_changed_by`, `court_changed_at`, `court_changed_reason`: thông tin đổi sân.

Các cột này giúp `bookings` tương thích với cả luồng cũ đang dùng trực tiếp trên booking và luồng mới có `booking_items`.

### 3. Seeder fake data mới

Các seeder mới tạo dữ liệu tối thiểu để admin test màn hình:

- `PartnerApplicationsTableSeeder`
- `PartnerApplicationCourtsTableSeeder`
- `OwnerBankAccountsTableSeeder`
- `OwnerWalletsTableSeeder`
- `OwnerWalletLedgersTableSeeder`
- `OwnerWithdrawalRequestsTableSeeder`
- `VenuePlatformFeeLedgersTableSeeder`
- `InternalReceiptsTableSeeder`
- `BookingsTableSeeder`
- `BookingItemsTableSeeder`
- `PaymentsTableSeeder`
- `PaymentLogsTableSeeder`
- `RefundsTableSeeder`
- `CommunityPostsTableSeeder`
- `CommunityPostCommentsTableSeeder`
- `VenuePostsTableSeeder`
- `ReportsTableSeeder`
- `ComplaintsTableSeeder`
- `MediaTableSeeder`
- `AuditLogsTableSeeder`
- `NotificationsTableSeeder`

Các seeder dùng `updateOrCreate`/`updateOrInsert` theo khóa tự nhiên như mã booking, mã payment, mã yêu cầu rút tiền, mã phiếu nội bộ, nên có thể chạy lại nhiều lần mà không nhân bản dữ liệu seed.
