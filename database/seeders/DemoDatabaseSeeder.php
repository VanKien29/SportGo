<?php

namespace Database\Seeders;

use Carbon\Carbon;
use Carbon\CarbonImmutable;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * A deterministic, business-shaped dataset for local/staging demonstrations.
 *
 * This seeder intentionally does not call DatabaseSeeder. The legacy seeder
 * chain mixes demo transactions, fixed dates and hard-coded IDs.
 */
class DemoDatabaseSeeder extends Seeder
{
    private string $timezone = 'Asia/Ho_Chi_Minh';
    private CarbonImmutable $asOf;
    private CarbonImmutable $from;
    private array $users = [];
    private array $owners = [];
    private array $players = [];
    private array $venues = [];
    private array $courts = [];
    private array $courtTypes = [];
    private array $priceMap = [];
    private array $vouchers = [];
    private array $completedBookings = [];
    private array $cancelledBookings = [];
    private array $futureBookings = [];
    private array $paidPayments = [];
    private array $packages = [];
    private int $bookingSequence = 1;
    private int $paymentSequence = 1;
    private int $walletLedgerSequence = 1;
    private int $ownerLedgerSequence = 1;

    public function run(): void
    {
        $asOf = env('DEMO_AS_OF');
        $this->asOf = $asOf
            ? CarbonImmutable::parse($asOf, $this->timezone)
            : CarbonImmutable::now($this->timezone);
        $this->from = $this->asOf->startOfDay()->subDays(30);

        Carbon::setTestNow($this->asOf);

        try {
            // Some foundation seeders refresh location tables with TRUNCATE,
            // which implicitly commits in MySQL; keep this orchestration out
            // of one long-lived transaction and validate the final snapshot.
            $this->seedFoundations();
            $this->seedIdentities();
            $this->seedSystemSettings();
            $this->seedPoliciesAndTemplates();
            $this->seedPartnerVenues();
            $this->seedVenueConfiguration();
            $this->seedVouchers();
            $this->seedUserWallets();
            $this->seedMemberships();
            $this->seedStaff();
            $this->seedBookingsAndPayments();
            $this->seedRefundsAndWallets();
            $this->seedPlatformFees();
            $this->seedFinanceOperations();
            $this->seedContentAndSupport();
            $this->seedNotificationsAndAudit();

            $this->integrityCheck();
        } finally {
            Carbon::setTestNow();
        }

        $this->command?->info('Demo data seeded successfully.');
        $this->command?->line('As-of: '.$this->asOf->toDateTimeString().' '.$this->timezone);
        $this->command?->line('History: '.$this->from->toDateString().' → '.$this->asOf->toDateString());
    }

    private function seedFoundations(): void
    {
        $this->call([
            RolesTableSeeder::class,
            PermissionsTableSeeder::class,
            RolePermissionsTableSeeder::class,
            VietnamLocationsSeeder::class,
            CourtTypesTableSeeder::class,
            AmenitiesTableSeeder::class,
            PlatformFeeTiersTableSeeder::class,
            SystemBankAccountSeeder::class,
            MembershipPackagesSeeder::class,
            ServiceCategoriesTableSeeder::class,
        ]);

        $this->courtTypes = DB::table('court_types as type')
            ->leftJoin('court_types as child', 'child.parent_id', '=', 'type.id')
            ->where('type.is_active', true)
            ->whereNull('type.deleted_at')
            ->whereNull('child.id')
            ->orderBy('type.id')
            ->pluck('type.id')
            ->map(fn ($id): int => (int) $id)
            ->values()
            ->all();

        if (count($this->courtTypes) < 4) {
            throw new \RuntimeException('Court type seed must provide at least four active leaf types.');
        }
    }

    private function seedIdentities(): void
    {
        $internal = [
            ['superadmin', 'Demo Super Admin', 'superadmin@demo.sportgo.test', '0908000001', 'super_admin'],
            ['admin', 'Demo Admin Vận Hành', 'admin@demo.sportgo.test', '0908000002', 'admin'],
            ['partner_manager', 'Demo Quản Lý Đối Tác', 'partner.manager@demo.sportgo.test', '0908000003', 'partner_manager'],
            ['finance', 'Demo Tài Chính', 'finance@demo.sportgo.test', '0908000004', 'finance_operator'],
            ['moderator', 'Demo Kiểm Duyệt', 'moderator@demo.sportgo.test', '0908000005', 'content_moderator'],
            ['complaints', 'Demo Xử Lý Khiếu Nại', 'complaints@demo.sportgo.test', '0908000006', 'complaint_handler'],
            ['policy', 'Demo Quản Lý Chính Sách', 'policy@demo.sportgo.test', '0908000007', 'policy_manager'],
        ];

        foreach ($internal as [$username, $name, $email, $phone, $role]) {
            $user = $this->createUser($username, $name, $email, $phone, ['badminton', 'pickleball']);
            $this->users[$username] = $user;
            $this->grantRole($user, $role, 'system', 0, $this->users['superadmin']['id'] ?? null);
        }

        for ($i = 1; $i <= 12; $i++) {
            $owner = $this->createUser(
                'demo_owner_'.$i,
                'Chủ sân Demo '.$i,
                'owner'.$i.'@demo.sportgo.test',
                '091800'.str_pad((string) $i, 4, '0', STR_PAD_LEFT),
                $i % 2 ? ['badminton', 'pickleball'] : ['football', 'tennis'],
            );
            $this->owners[] = $owner;
            $this->users[$owner['username']] = $owner;
            if ($i <= 10) {
                $this->grantRole($owner, 'venue_owner', 'system', 0, $this->users['admin']['id']);
            }
        }

        for ($i = 1; $i <= 60; $i++) {
            $status = $i === 60 ? 'locked' : 'active';
            $player = $this->createUser(
                'demo_player_'.$i,
                'Người chơi Demo '.$i,
                'player'.$i.'@demo.sportgo.test',
                '093800'.str_pad((string) $i, 4, '0', STR_PAD_LEFT),
                $i % 3 === 0 ? ['pickleball', 'tennis'] : ['badminton', 'football'],
                $status,
            );
            $this->players[] = $player;
            $this->users[$player['username']] = $player;
            $this->grantRole($player, 'user', 'system', 0, null);
        }
    }

    private function createUser(string $username, string $name, string $email, string $phone, array $sports, string $status = 'active'): array
    {
        $createdAt = $this->from->subDays(5)->addMinutes(count($this->users) * 7);
        $id = DB::table('users')->insertGetId([
            'username' => $username,
            'full_name' => $name,
            'phone' => $phone,
            'email' => $email,
            'email_verified_at' => $createdAt,
            'phone_verified_at' => $createdAt,
            'password' => Hash::make('Demo@123456'),
            'preferred_sports' => json_encode($sports, JSON_UNESCAPED_UNICODE),
            'status' => $status,
            'is_locked' => $status === 'locked',
            'verification_channel' => 'email',
            'lock_type' => $status === 'locked' ? 'permanent' : null,
            'status_reason' => $status === 'locked' ? 'Tài khoản demo bị khóa để minh họa màn hình xử lý người dùng.' : null,
            'locked_at' => $status === 'locked' ? $this->asOf->subDays(2) : null,
            'created_at' => $createdAt,
            'updated_at' => $this->asOf,
        ]);

        return ['id' => $id, 'username' => $username, 'full_name' => $name, 'email' => $email, 'phone' => $phone];
    }

    private function grantRole(array $user, string $roleName, string $scopeType, int $scopeId, ?int $grantedBy): void
    {
        $roleId = DB::table('roles')->where('name', $roleName)->value('id');
        if (! $roleId) {
            throw new \RuntimeException('Missing role: '.$roleName);
        }

        DB::table('user_roles')->insert([
            'user_id' => $user['id'],
            'role_id' => $roleId,
            'scope_type' => $scopeType,
            'scope_id' => $scopeId,
            'granted_by' => $grantedBy,
            'created_at' => $this->asOf,
        ]);
    }

    private function seedSystemSettings(): void
    {
        $settings = [
            'system_name' => 'SportGo Demo',
            'company_name' => 'Công ty TNHH SportGo Demo',
            'company_short_name' => 'SportGo',
            'representative_name' => 'Nguyễn Văn SportGo',
            'representative_title' => 'Tổng Giám đốc',
            'company_address' => 'Tầng 8, Tòa nhà Demo, Cầu Giấy, Hà Nội',
            'tax_code' => '0109999999',
            'business_code' => '0109999999',
            'business_license_number' => 'GP-SPORTGO-DEMO-2026',
            'support_email' => 'support@demo.sportgo.test',
            'support_phone' => '19009999',
            'website_url' => 'https://demo.sportgo.test',
            'logo_url' => '',
            'favicon_url' => '',
        ];

        foreach ($settings as $key => $value) {
            DB::table('system_settings')->updateOrInsert(
                ['key' => $key],
                [
                    'value' => $value,
                    'type' => 'string',
                    'value_type' => 'string',
                    'group' => str_contains($key, 'company') || str_contains($key, 'tax') || str_contains($key, 'business') || str_contains($key, 'representative') ? 'legal' : 'identity',
                    'label' => $key,
                    'description' => 'Giá trị demo dùng cho môi trường trình diễn.',
                    'updated_at' => $this->asOf,
                    'created_at' => $this->asOf,
                ],
            );
        }
    }

    private function seedPoliciesAndTemplates(): void
    {
        $this->call([
            SystemPoliciesTableSeeder::class,
            ViolationTypesSeeder::class,
            SeverityLevelsSeeder::class,
            ModerationThresholdsSeeder::class,
            PenaltyEscalationRulesSeeder::class,
            PolicyActionBindingsTableSeeder::class,
            PolicyRulesTableSeeder::class,
            PolicyRuleTemplatesTableSeeder::class,
            PolicyOverrideConstraintsTableSeeder::class,
            PolicyStatusHistoriesTableSeeder::class,
            ModerationConfigsTableSeeder::class,
            DocumentTemplatesTableSeeder::class,
        ]);

        $policies = DB::table('system_policies')->where('is_active', true)->get();
        foreach (array_merge($this->players, $this->owners) as $user) {
            foreach ($policies->where('key', 'terms') as $policy) {
                DB::table('user_policy_acceptances')->insertOrIgnore([
                    'user_id' => $user['id'],
                    'system_policy_id' => $policy->id,
                    'policy_version' => (string) $policy->version,
                    'accepted_at' => $this->asOf->subDays(10)->addMinutes($user['id']),
                ]);
            }
        }
    }

    private function seedPartnerVenues(): void
    {
        $venueNames = [
            'Green Sport Ba Đình', 'Sun Sport Cầu Giấy', 'Victory Sport Hà Đông',
            'Riverside Sport Tây Hồ', 'Diamond Sport Thanh Xuân', 'Star Arena Nam Từ Liêm',
            'Elite Sport Hoàn Kiếm', 'Saigon Arena Quận 1', 'Riverside Sport Cần Thơ',
            'Central Sport Đà Nẵng',
        ];
        $provinceCodes = ['1', '1', '1', '1', '1', '1', '1', '79', '92', '48'];
        $coordinates = [
            [21.034, 105.823], [21.036, 105.793], [20.971, 105.778], [21.067, 105.821],
            [20.996, 105.810], [21.017, 105.760], [21.028, 105.852], [10.771, 106.698],
            [10.046, 105.746], [16.061, 108.211],
        ];

        foreach ($venueNames as $index => $name) {
            $owner = $this->owners[$index];
            $location = $this->location($provinceCodes[$index]);
            $createdAt = $this->from->addDays($index);
            $applicationId = DB::table('partner_applications')->insertGetId([
                'user_id' => $owner['id'],
                'applicant_full_name' => $owner['full_name'],
                'applicant_phone' => $owner['phone'],
                'applicant_email' => $owner['email'],
                'applicant_birth_date' => '1992-04-12',
                'applicant_address' => $location['province_name'],
                'applicant_type' => 'business',
                'representative_name' => $owner['full_name'],
                'representative_identity_type' => 'cccd',
                'representative_identity_number' => '079'.str_pad((string) ($index + 1), 9, '0', STR_PAD_LEFT),
                'representative_identity_issued_date' => '2021-01-01',
                'representative_identity_issued_place' => 'Cục Cảnh sát QLHC về TTXH',
                'representative_position' => 'Chủ doanh nghiệp',
                'business_name' => 'Hộ kinh doanh '.$name,
                'tax_code' => '010'.str_pad((string) ($index + 1000000), 7, '0', STR_PAD_LEFT),
                'business_code' => 'HKD-DEMO-'.str_pad((string) ($index + 1), 3, '0', STR_PAD_LEFT),
                'business_license_number' => 'GPKD-DEMO-'.str_pad((string) ($index + 1), 3, '0', STR_PAD_LEFT),
                'business_address' => $location['province_name'],
                'business_representative_name' => $owner['full_name'],
                'business_representative_position' => 'Chủ hộ kinh doanh',
                'venue_name' => $name,
                'venue_address' => 'Số '.(10 + $index).', Đường Thể Thao, '.$location['ward_name'].', '.$location['province_name'],
                'venue_province' => $location['province_name'],
                'venue_province_code' => $location['province_code'],
                'venue_district' => null,
                'venue_district_code' => null,
                'venue_ward' => $location['ward_name'],
                'venue_ward_code' => $location['ward_code'],
                'venue_map_url' => 'https://www.google.com/maps/search/?api=1&query='.$coordinates[$index][0].','.$coordinates[$index][1],
                'venue_latitude' => $coordinates[$index][0],
                'venue_longitude' => $coordinates[$index][1],
                'venue_phone' => $owner['phone'],
                'venue_email' => 'venue'.$index.'@demo.sportgo.test',
                'venue_description' => 'Cụm sân demo vận hành ổn định, có lịch online và đặt tại quầy.',
                'expected_opening_hours' => '06:00-22:00',
                'parking_info' => 'Có bãi xe máy và ô tô trong khuôn viên.',
                'amenities' => json_encode(['Bãi đỗ xe', 'Wifi', 'Phòng thay đồ'], JSON_UNESCAPED_UNICODE),
                'court_count_total' => $index < 5 ? 5 : 4,
                'base_price_per_hour' => 120000 + ($index % 5) * 20000,
                'bank_name' => 'TPBank',
                'bank_code' => 'TPBank',
                'account_number' => '72906'.str_pad((string) ($index + 100001), 6, '0', STR_PAD_LEFT),
                'account_holder_name' => Str::upper(Str::ascii($owner['full_name'])),
                'bank_branch' => 'Chi nhánh Demo',
                'bank_verification_status' => 'verified',
                'bank_verified_at' => $createdAt->addDays(1),
                'status' => 'completed',
                'reviewed_by' => $this->users['partner_manager']['id'],
                'status_reason' => 'Hồ sơ và hợp đồng đã hoàn tất.',
                'submitted_at' => $createdAt,
                'reviewed_at' => $createdAt->addHours(8),
                'created_at' => $createdAt,
                'updated_at' => $this->asOf,
            ]);

            $this->applicationHistory($applicationId, null, 'submitted', $owner['id'], $createdAt);
            $this->applicationHistory($applicationId, 'submitted', 'reviewing', $this->users['partner_manager']['id'], $createdAt->addHours(2));
            $this->applicationHistory($applicationId, 'reviewing', 'approved_pending_contract', $this->users['partner_manager']['id'], $createdAt->addHours(8));

            [$province, $ward] = [$location['province_name'], $location['ward_name']];
            $clusterId = DB::table('venue_clusters')->insertGetId([
                'owner_id' => $owner['id'],
                'name' => $name,
                'slug' => Str::slug($name).'-demo-'.$index,
                'description' => 'Cụm sân demo của '.$name.'.',
                'phone_contact' => $owner['phone'],
                'province' => $province,
                'province_code' => $location['province_code'],
                'ward' => $ward,
                'ward_code' => $location['ward_code'],
                'address' => 'Số '.(10 + $index).', Đường Thể Thao, '.$ward.', '.$province,
                'map_url' => 'https://www.google.com/maps/search/?api=1&query='.$coordinates[$index][0].','.$coordinates[$index][1],
                'latitude' => $coordinates[$index][0],
                'longitude' => $coordinates[$index][1],
                'layout_decorations' => json_encode([], JSON_UNESCAPED_UNICODE),
                'amenities' => json_encode(['Bãi đỗ xe', 'Wifi', 'Phòng thay đồ'], JSON_UNESCAPED_UNICODE),
                'status' => 'active',
                'rating_avg' => 4.2 + (($index % 7) / 10),
                'rating_count' => 10 + $index * 3,
                'created_at' => $createdAt->addHours(10),
                'updated_at' => $this->asOf,
            ]);

            DB::table('partner_applications')->where('id', $applicationId)->update(['approved_venue_cluster_id' => $clusterId]);
            $this->venues[] = ['id' => $clusterId, 'owner' => $owner, 'application_id' => $applicationId, 'name' => $name, 'created_at' => $createdAt];

            $this->seedApplicationCourts($applicationId, $index < 5 ? 5 : 4);
            $this->seedVenueCourts($clusterId, $index < 5 ? 5 : 4, $index);
            $this->seedOwnerBankAccount($owner, $applicationId, $index, $createdAt);
            $this->seedContract($applicationId, $clusterId, $owner, $index, $createdAt);
            $this->seedVenueAmenities($clusterId, $index);
        }

        $this->seedPendingApplications();
    }

    private function location(string $provinceCode): array
    {
        $province = DB::table('vn_provinces')->where('code', $provinceCode)->first()
            ?: DB::table('vn_provinces')->orderBy('code')->first();
        $ward = DB::table('vn_wards')->where('province_code', $province->code)->orderBy('code')->first();

        return [
            'province_code' => (string) $province->code,
            'province_name' => $province->name,
            'ward_code' => (string) $ward->code,
            'ward_name' => $ward->name,
        ];
    }

    private function seedApplicationCourts(int $applicationId, int $count): void
    {
        for ($i = 0; $i < $count; $i++) {
            $typeId = $this->courtTypes[$i % count($this->courtTypes)];
            $typeName = DB::table('court_types')->where('id', $typeId)->value('name');
            DB::table('partner_application_courts')->insert([
                'partner_application_id' => $applicationId,
                'court_type_id' => $typeId,
                'court_type_name_snapshot' => $typeName,
                'expected_court_count' => 1,
                'note' => 'Sân demo đã nghiệm thu.',
                'name' => 'Sân '.($i + 1),
                'sort_order' => $i + 1,
                'created_at' => $this->asOf->subDays(25),
                'updated_at' => $this->asOf,
            ]);
        }
    }

    private function seedVenueCourts(int $clusterId, int $count, int $venueIndex): void
    {
        for ($i = 0; $i < $count; $i++) {
            $typeId = $this->courtTypes[$i % count($this->courtTypes)];
            $status = $venueIndex === 2 && $i === $count - 1 ? 'maintenance' : 'active';
            $id = DB::table('venue_courts')->insertGetId([
                'venue_cluster_id' => $clusterId,
                'court_type_id' => $typeId,
                'name' => 'Sân '.chr(65 + $i),
                'status' => $status,
                'sort_order' => $i + 1,
                'layout_x' => 60 + ($i % 3) * 180,
                'layout_y' => 60 + intdiv($i, 3) * 140,
                'layout_w' => 140,
                'layout_h' => 100,
                'layout_rotation' => 0,
                'created_at' => $this->asOf->subDays(24),
                'updated_at' => $this->asOf,
            ]);
            $this->courts[$clusterId][] = ['id' => $id, 'court_type_id' => $typeId, 'status' => $status];
        }
    }

    private function seedOwnerBankAccount(array $owner, int $applicationId, int $index, CarbonImmutable $createdAt): void
    {
        DB::table('owner_bank_accounts')->insert([
            'owner_id' => $owner['id'],
            'partner_application_id' => $applicationId,
            'bank_name' => 'TPBank',
            'bank_code' => 'TPBank',
            'account_number' => '72906'.str_pad((string) ($index + 100001), 6, '0', STR_PAD_LEFT),
            'account_holder_name' => Str::upper(Str::ascii($owner['full_name'])),
            'branch_name' => 'Chi nhánh Demo',
            'status' => 'active',
            'is_default' => true,
            'verified_by' => $this->users['finance']['id'],
            'verified_at' => $createdAt->addDays(1),
            'created_at' => $createdAt,
            'updated_at' => $this->asOf,
        ]);
    }

    private function seedContract(int $applicationId, int $clusterId, array $owner, int $index, CarbonImmutable $createdAt): void
    {
        $templateId = DB::table('document_templates')->where('document_type', 'partner_contract')->where('is_active', true)->value('id');
        $applicationTemplateId = DB::table('document_templates')->where('document_type', 'partner_application_form')->where('is_active', true)->value('id');
        if (! $templateId || ! $applicationTemplateId) {
            throw new \RuntimeException('Partner document templates were not seeded.');
        }

        $applicationDocumentId = DB::table('generated_documents')->insertGetId([
            'document_code' => 'DEMO-APP-DOC-'.str_pad((string) ($index + 1), 3, '0', STR_PAD_LEFT),
            'document_type' => 'partner_application_form',
            'template_id' => $applicationTemplateId,
            'template_version' => 1,
            'document_version' => 1,
            'reference_type' => 'partner_application',
            'reference_id' => (string) $applicationId,
            'entity_type' => 'partner_application',
            'entity_id' => (string) $applicationId,
            'partner_application_id' => $applicationId,
            'owner_id' => $owner['id'],
            'venue_cluster_id' => $clusterId,
            'title' => 'Đơn đăng ký đối tác demo',
            'status' => 'completed',
            'render_data' => json_encode(['demo' => true, 'owner' => $owner['full_name']], JSON_UNESCAPED_UNICODE),
            'generated_file_path' => 'document-templates/partner_application_form_v1.docx',
            'generated_pdf_path' => null,
            'generated_by' => $this->users['partner_manager']['id'],
            'generated_at' => $createdAt->addHours(3),
            'completed_at' => $createdAt->addHours(4),
            'created_at' => $createdAt->addHours(3),
            'updated_at' => $this->asOf,
        ]);

        DB::table('generated_document_signatures')->insert([
            'generated_document_id' => $applicationDocumentId,
            'signer_side' => 'owner',
            'signer_user_id' => $owner['id'],
            'signer_full_name' => $owner['full_name'],
            'signer_title' => 'Chủ sân',
            'signer_organization' => 'Đối tác SportGo Demo',
            'signature_method' => 'otp_confirm',
            'signed_at' => $createdAt->addHours(4),
            'status' => 'signed',
            'created_at' => $createdAt->addHours(4),
            'updated_at' => $this->asOf,
        ]);

        foreach ([
            ['identity_card', 'identity', 'Giấy tờ định danh chủ sân', 'partner-applications/demo-'.$applicationId.'/identity-card.pdf'],
            ['business_license', 'business', 'Giấy đăng ký kinh doanh', 'partner-applications/demo-'.$applicationId.'/business-license.pdf'],
            ['venue_photo', 'venue', 'Ảnh mặt bằng cụm sân', 'partner-applications/demo-'.$applicationId.'/venue-photo.jpg'],
        ] as $documentIndex => [$type, $group, $title, $path]) {
            DB::table('partner_application_documents')->insert([
                'partner_application_id' => $applicationId,
                'document_type' => $type,
                'document_group' => $group,
                'title' => $title,
                'description' => 'Tệp demo phục vụ hiển thị hồ sơ đã xác minh.',
                'file_path' => $path,
                'pdf_file_path' => str_ends_with($path, '.pdf') ? $path : null,
                'status' => 'verified',
                'reviewed_by' => $this->users['partner_manager']['id'],
                'reviewed_at' => $createdAt->addHours(8),
                'sort_order' => $documentIndex + 1,
                'created_at' => $createdAt->addHours(2 + $documentIndex),
                'updated_at' => $this->asOf,
            ]);
        }

        $contractId = DB::table('partner_contracts')->insertGetId([
            'contract_code' => 'DEMO-CONTRACT-'.str_pad((string) ($index + 1), 3, '0', STR_PAD_LEFT),
            'partner_application_id' => $applicationId,
            'owner_id' => $owner['id'],
            'venue_cluster_id' => $clusterId,
            'contract_title' => 'Hợp đồng hợp tác đối tác SportGo Demo',
            'status' => 'signed_active',
            'generated_by' => $this->users['partner_manager']['id'],
            'approved_by' => $this->users['partner_manager']['id'],
            'owner_signed_at' => $createdAt->addDays(1),
            'sportgo_signed_at' => $createdAt->addDays(1)->addHours(2),
            'effective_from' => $createdAt->addDays(1)->toDateString(),
            'effective_to' => $createdAt->addYear()->toDateString(),
            'note' => 'Hợp đồng demo đã đủ chữ ký.',
            'created_at' => $createdAt->addHours(10),
            'updated_at' => $this->asOf,
        ]);

        $contractDocumentId = DB::table('generated_documents')->insertGetId([
            'document_code' => 'DEMO-CONTRACT-DOC-'.str_pad((string) ($index + 1), 3, '0', STR_PAD_LEFT),
            'document_type' => 'partner_contract',
            'template_id' => $templateId,
            'template_version' => 1,
            'document_version' => 1,
            'reference_type' => 'partner_contract',
            'reference_id' => (string) $contractId,
            'entity_type' => 'partner_contract',
            'entity_id' => (string) $contractId,
            'partner_application_id' => $applicationId,
            'partner_contract_id' => $contractId,
            'owner_id' => $owner['id'],
            'venue_cluster_id' => $clusterId,
            'title' => 'Hợp đồng hợp tác đối tác demo',
            'status' => 'completed',
            'render_data' => json_encode(['demo' => true, 'contract_id' => $contractId], JSON_UNESCAPED_UNICODE),
            'generated_file_path' => 'document-templates/partner_contract_v1.docx',
            'generated_by' => $this->users['partner_manager']['id'],
            'generated_at' => $createdAt->addDays(1),
            'completed_at' => $createdAt->addDays(1)->addHours(2),
            'created_at' => $createdAt->addDays(1),
            'updated_at' => $this->asOf,
        ]);

        DB::table('partner_contracts')->where('id', $contractId)->update(['generated_document_id' => $contractDocumentId]);
        DB::table('partner_applications')->where('id', $applicationId)->update(['current_contract_id' => $contractId]);

        foreach ([['owner', $owner['full_name'], 'Chủ sân', $owner['id'], $createdAt->addDays(1)], ['sportgo', 'Nguyễn Văn SportGo', 'Tổng Giám đốc', $this->users['partner_manager']['id'], $createdAt->addDays(1)->addHours(2)]] as [$side, $name, $title, $userId, $signedAt]) {
            DB::table('generated_document_signatures')->insert([
                'generated_document_id' => $contractDocumentId,
                'signer_side' => $side,
                'signer_user_id' => $userId,
                'signer_full_name' => $name,
                'signer_title' => $title,
                'signer_organization' => $side === 'owner' ? 'Đối tác SportGo Demo' : 'SportGo',
                'signature_method' => 'otp_confirm',
                'signed_at' => $signedAt,
                'status' => 'signed',
                'created_at' => $signedAt,
                'updated_at' => $this->asOf,
            ]);
        }

        $this->applicationHistory($applicationId, 'approved_pending_contract', 'contract_pending_sportgo_signature', $this->users['partner_manager']['id'], $createdAt->addDays(1));
        $this->applicationHistory($applicationId, 'contract_pending_sportgo_signature', 'contract_pending_owner_signature', $this->users['partner_manager']['id'], $createdAt->addDays(1)->addHours(1));
        $this->applicationHistory($applicationId, 'contract_pending_owner_signature', 'completed', $owner['id'], $createdAt->addDays(1)->addHours(2));
    }

    private function applicationHistory(int $applicationId, ?string $old, string $new, ?int $actor, CarbonImmutable $at): void
    {
        DB::table('partner_application_status_histories')->insert([
            'partner_application_id' => $applicationId,
            'old_status' => $old,
            'new_status' => $new,
            'changed_by' => $actor,
            'actor_type' => 'user',
            'reason' => 'Demo workflow event.',
            'metadata' => json_encode(['demo' => true], JSON_UNESCAPED_UNICODE),
            'created_at' => $at,
        ]);
    }

    private function seedPendingApplications(): void
    {
        $statuses = ['reviewing', 'need_supplement'];
        foreach ($statuses as $offset => $status) {
            $owner = $this->owners[10 + $offset];
            $location = $this->location('1');
            $createdAt = $this->asOf->subDays(2 + $offset)->setTime(10, 0);
            $applicationId = DB::table('partner_applications')->insertGetId([
                'user_id' => $owner['id'],
                'applicant_full_name' => $owner['full_name'],
                'applicant_phone' => $owner['phone'],
                'applicant_email' => $owner['email'],
                'applicant_birth_date' => '1990-01-01',
                'applicant_address' => $location['province_name'],
                'applicant_type' => 'individual',
                'representative_name' => $owner['full_name'],
                'representative_identity_type' => 'cccd',
                'representative_identity_number' => '07999999'.str_pad((string) ($offset + 1), 4, '0', STR_PAD_LEFT),
                'business_name' => 'Hộ kinh doanh đang xét duyệt '.$offset,
                'business_license_number' => 'PENDING-DEMO-'.$offset,
                'business_address' => $location['province_name'],
                'venue_name' => 'Cụm sân chờ duyệt '.$offset,
                'venue_address' => 'Địa chỉ chờ bổ sung',
                'venue_province' => $location['province_name'],
                'venue_province_code' => $location['province_code'],
                'venue_ward' => $location['ward_name'],
                'venue_ward_code' => $location['ward_code'],
                'venue_map_url' => 'https://www.google.com/maps/search/?api=1&query=21.03,105.80',
                'venue_latitude' => 21.03,
                'venue_longitude' => 105.80,
                'venue_phone' => $owner['phone'],
                'venue_email' => 'pending'.$offset.'@demo.sportgo.test',
                'court_count_total' => 3,
                'base_price_per_hour' => 150000,
                'bank_name' => 'TPBank',
                'bank_code' => 'TPBank',
                'account_number' => '7290600000'.($offset + 1),
                'account_holder_name' => Str::upper(Str::ascii($owner['full_name'])),
                'bank_verification_status' => 'pending',
                'status' => $status,
                'status_reason' => $status === 'need_supplement' ? 'Thiếu hợp đồng thuê địa điểm.' : 'Đang chờ nhân viên đối tác kiểm tra.',
                'submitted_at' => $createdAt,
                'created_at' => $createdAt,
                'updated_at' => $this->asOf,
            ]);
            $this->applicationHistory($applicationId, null, 'submitted', $owner['id'], $createdAt);
            $this->applicationHistory($applicationId, 'submitted', $status, $this->users['partner_manager']['id'], $createdAt->addHours(2));
        }
    }

    private function seedVenueAmenities(int $clusterId, int $index): void
    {
        $amenities = DB::table('amenities')->where('status', 'active')->orderBy('id')->limit(5)->get();
        foreach ($amenities as $amenity) {
            DB::table('venue_cluster_amenities')->insert([
                'venue_cluster_id' => $clusterId,
                'amenity_id' => $amenity->id,
                'description' => $index % 2 === 0 ? 'Có phục vụ trong giờ mở cửa.' : null,
                'is_visible' => true,
                'created_at' => $this->asOf->subDays(20),
                'updated_at' => $this->asOf,
            ]);
        }
    }

    private function seedVenueConfiguration(): void
    {
        $categories = DB::table('service_categories')->where('status', 'active')->pluck('id')->values()->all();
        foreach ($this->venues as $index => $venue) {
            $clusterId = $venue['id'];
            $open = '06:00';
            $close = $index % 4 === 0 ? '23:00' : '22:00';
            DB::table('booking_configs')->insert([
                'venue_cluster_id' => $clusterId,
                'min_duration_minutes' => 30,
                'max_duration_minutes' => $index % 2 === 0 ? 180 : null,
                'min_advance_booking_minutes' => $index % 3 === 0 ? 30 : 60,
                'fixed_open_time' => $open,
                'fixed_close_time' => $close,
                'weekly_operating_hours' => json_encode([['is_open' => true, 'open_time' => $open, 'close_time' => $close]], JSON_UNESCAPED_UNICODE),
                'special_operating_hours' => json_encode([], JSON_UNESCAPED_UNICODE),
                'custom_time_periods' => json_encode([], JSON_UNESCAPED_UNICODE),
                'slot_hold_minutes' => 20,
                'reminder_before_minutes' => $index % 2 === 0 ? 30 : 60,
                'allow_full_payment' => true,
                'allow_deposit' => true,
                'allow_no_prepay' => true,
                'auto_approve_full_payment' => $index % 4 === 0,
                'deposit_percent' => 30,
                'reset_membership_progress_on_upgrade' => false,
                'cancel_before_hours' => 2,
                'refund_percent' => 80,
                'created_at' => $venue['created_at']->addHours(12),
                'updated_at' => $this->asOf,
            ]);

            $courtTypeIds = collect($this->courts[$clusterId])->pluck('court_type_id')->unique()->values()->all();
            foreach ($courtTypeIds as $typeIndex => $typeId) {
                $base = 100000 + (($index + $typeIndex) % 6) * 25000;
                $this->priceMap[$clusterId][$typeId] = $base;
                DB::table('venue_base_prices')->insert([
                    'venue_cluster_id' => $clusterId,
                    'court_type_id' => $typeId,
                    'price' => $base,
                    'created_at' => $venue['created_at']->addHours(13),
                    'updated_at' => $this->asOf,
                ]);

                foreach ([['06:00', '17:00', 0.9], ['17:00', $close, 1.2]] as [$start, $end, $factor]) {
                    DB::table('price_slots')->insert([
                        'venue_cluster_id' => $clusterId,
                        'court_type_id' => $typeId,
                        'booking_type' => 'all',
                        'start_time' => $start,
                        'end_time' => $end,
                        'price' => round($base * $factor, -3),
                        'apply_to_days' => json_encode([1, 2, 3, 4, 5, 6, 7]),
                        'is_active' => true,
                        'created_at' => $venue['created_at']->addHours(14),
                        'updated_at' => $this->asOf,
                    ]);
                }

                DB::table('holiday_prices')->insert([
                    'venue_cluster_id' => $clusterId,
                    'court_type_id' => $typeId,
                    'date_type' => 'special_date',
                    'booking_type' => 'all',
                    'holiday_date' => $this->from->addDays(15)->toDateString(),
                    'start_time' => '06:00',
                    'end_time' => $close,
                    'price' => round($base * 1.35, -3),
                    'note' => 'Ngày cao điểm demo.',
                    'is_active' => true,
                    'created_at' => $venue['created_at']->addHours(15),
                    'updated_at' => $this->asOf,
                ]);
            }

            foreach (range(1, 7) as $serviceIndex) {
                $categoryId = $categories[($index + $serviceIndex) % max(1, count($categories))] ?? null;
                if (! $categoryId) {
                    continue;
                }
                DB::table('venue_cluster_services')->insert([
                    'id' => (string) Str::uuid(),
                    'venue_cluster_id' => $clusterId,
                    'category_id' => $categoryId,
                    'name' => ['Nước suối', 'Thuê vợt', 'Thuê bóng', 'Khăn lạnh', 'Nước điện giải', 'HLV cá nhân', 'Phụ kiện'][($serviceIndex - 1) % 7],
                    'price' => 10000 + $serviceIndex * 15000,
                    'unit' => $serviceIndex === 6 ? 'buổi' : 'món',
                    'status' => 'active',
                    'description' => 'Dịch vụ demo tại cụm sân.',
                    'created_at' => $venue['created_at']->addHours(16),
                    'updated_at' => $this->asOf,
                ]);
            }
        }
    }

    private function seedVouchers(): void
    {
        for ($i = 1; $i <= 6; $i++) {
            $this->vouchers['system_'.$i] = $this->createVoucher([
                'code' => 'DEMO-SYS-'.$i,
                'name' => 'Voucher hệ thống Demo '.$i,
                'owner_type' => 'system',
                'owner_id' => null,
                'funded_by' => 'system',
                'discount_type' => $i % 2 ? 'percent' : 'fixed',
                'discount_value' => $i % 2 ? 10 : 30000,
                'max_discount_amount' => $i % 2 ? 50000 : null,
                'min_order_amount' => 100000,
                'total_quantity' => 1000,
                'per_user_limit' => 2,
                'status' => 'active',
                'source' => 'manual',
            ]);
        }

        foreach ($this->venues as $index => $venue) {
            $this->vouchers['venue_'.$venue['id']] = $this->createVoucher([
                'code' => 'DEMO-VENUE-'.$index,
                'name' => 'Ưu đãi '.$venue['name'],
                'owner_type' => 'venue',
                'owner_id' => $venue['id'],
                'funded_by' => 'venue',
                'discount_type' => 'percent',
                'discount_value' => 10,
                'max_discount_amount' => 60000,
                'min_order_amount' => 150000,
                'total_quantity' => 200,
                'per_user_limit' => 1,
                'status' => 'active',
                'source' => 'manual',
            ]);
        }
    }

    private function createVoucher(array $data): int
    {
        $id = DB::table('vouchers')->insertGetId([
            ...$data,
            'stacking_rule' => $data['stacking_rule'] ?? 'exclusive',
            'used_quantity' => 0,
            'valid_from' => $this->from,
            'valid_to' => $this->asOf->addDays(30),
            'created_by' => $this->users['admin']['id'],
            'created_at' => $this->from->addDays(2),
            'updated_at' => $this->asOf,
        ]);
        DB::table('voucher_scopes')->insert([
            'voucher_id' => $id,
            'scope_type' => $data['owner_type'] === 'venue' ? 'venue_cluster' : 'all',
            'scope_id' => $data['owner_type'] === 'venue' ? $data['owner_id'] : null,
            'scope_key' => $data['owner_type'] === 'venue' ? (string) $data['owner_id'] : '__all__',
            'created_at' => $this->from->addDays(2),
            'updated_at' => $this->asOf,
        ]);

        return $id;
    }

    private function seedUserWallets(): void
    {
        foreach ($this->players as $index => $player) {
            $createdAt = $this->from->addDays(5)->addMinutes($index);
            DB::table('user_wallets')->insert([
                'user_id' => $player['id'],
                'balance' => 0,
                'locked_balance' => 0,
                'status' => $player['username'] === 'demo_player_60' ? 'locked' : 'active',
                'created_at' => $createdAt,
                'updated_at' => $this->asOf,
            ]);
            $this->walletLedger(
                $player['id'],
                'deposit',
                'credit',
                1500000 + ($index % 8) * 100000,
                'demo_seed',
                'DEMO-WALLET-TOPUP-'.str_pad((string) ($index + 1), 3, '0', STR_PAD_LEFT),
                $createdAt->addMinutes(5),
            );
        }
    }

    private function seedMemberships(): void
    {
        $this->packages = DB::table('membership_packages')->where('is_active', true)->orderBy('sort_order')->get()->all();
        foreach ($this->venues as $index => $venue) {
            foreach (['standard', 'silver', 'gold', 'diamond'] as $tierIndex => $tier) {
                DB::table('court_membership_tiers')->insert([
                    'venue_cluster_id' => $venue['id'],
                    'tier' => $tier,
                    'tier_label' => ucfirst($tier),
                    'is_active' => true,
                    'voucher_id' => null,
                    'discount_percent' => $tierIndex * 3,
                    'min_bookings' => $tierIndex * 5,
                    'min_spent_amount' => $tierIndex * 1000000,
                    'maintain_min_bookings' => max(0, $tierIndex * 3),
                    'maintain_min_spent' => max(0, $tierIndex * 500000),
                    'maintain_period_months' => 3,
                    'created_at' => $venue['created_at']->addHours(17),
                    'updated_at' => $this->asOf,
                ]);
            }
        }

        foreach (array_slice($this->players, 0, 24) as $index => $player) {
            $package = $this->packages[$index % max(1, count($this->packages))] ?? null;
            if (! $package || $package->type === 'free') {
                continue;
            }
            $startedAt = $this->from->addDays(8 + ($index % 14));
            $expiresAt = $startedAt->addMonth();
            $status = $expiresAt->isFuture() ? 'active' : 'expired';
            $subscriptionId = DB::table('user_subscriptions')->insertGetId([
                'user_id' => $player['id'],
                'package_id' => $package->id,
                'billing_cycle' => 'monthly',
                'started_at' => $startedAt,
                'expires_at' => $expiresAt,
                'status' => $status,
                'paid_amount' => $package->monthly_price,
                'payment_ref' => 'DEMO-VIP-PAY-'.$index,
                'month_post_count' => $index % 4,
                'month_post_reset_at' => $this->asOf->startOfMonth(),
                'created_at' => $startedAt,
                'updated_at' => $this->asOf,
            ]);

            $paymentId = DB::table('payments')->insertGetId([
                'payment_code' => 'DEMO-VIP-PAY-'.$index,
                'payment_context' => 'vip_subscription',
                'subscription_id' => $subscriptionId,
                'system_bank_account_id' => $this->systemBankId(),
                'amount' => $package->monthly_price,
                'wallet_amount' => 0,
                'gateway_amount' => $package->monthly_price,
                'payment_kind' => 'full',
                'method' => 'sepay',
                'gateway_txn_id' => 'DEMO-SEPAY-VIP-'.$index,
                'gateway_response' => json_encode(['demo' => true], JSON_UNESCAPED_UNICODE),
                'status' => 'paid',
                'paid_at' => $startedAt->addMinutes(10),
                'created_at' => $startedAt,
                'updated_at' => $this->asOf,
            ]);
            $this->paymentLog($paymentId, 'vip_subscription_payment_completed', 'paid', $startedAt->addMinutes(10));
        }
    }

    private function seedStaff(): void
    {
        $menuKeys = ['dashboard', 'schedules', 'bookings', 'counter_booking', 'chat', 'settings'];
        foreach ($this->venues as $venueIndex => $venue) {
            $shiftId = DB::table('venue_staff_shifts')->insertGetId([
                'venue_cluster_id' => $venue['id'],
                'name' => 'Ca hành chính',
                'start_time' => '06:00',
                'end_time' => '14:00',
                'description' => 'Ca trực buổi sáng demo.',
                'is_active' => true,
                'created_at' => $venue['created_at']->addHours(18),
                'updated_at' => $this->asOf,
            ]);

            for ($staffIndex = 0; $staffIndex < 2; $staffIndex++) {
                $staff = $this->createUser(
                    'demo_staff_'.$venueIndex.'_'.$staffIndex,
                    'Nhân viên '.($staffIndex + 1).' - '.$venue['name'],
                    'staff'.$venueIndex.'_'.$staffIndex.'@demo.sportgo.test',
                    '094800'.str_pad((string) ($venueIndex * 2 + $staffIndex + 1), 4, '0', STR_PAD_LEFT),
                    ['badminton'],
                );
                $this->users[$staff['username']] = $staff;
                $this->grantRole($staff, 'venue_staff', 'venue', $venue['id'], $venue['owner']['id']);
                $assignmentId = DB::table('venue_staff_assignments')->insertGetId([
                    'user_id' => $staff['id'],
                    'venue_cluster_id' => $venue['id'],
                    'scope_type' => 'all_cluster',
                    'court_type_id' => null,
                    'scope_key' => 'all',
                    'assigned_by' => $venue['owner']['id'],
                    'status' => 'active',
                    'created_at' => $venue['created_at']->addHours(18),
                    'updated_at' => $this->asOf,
                ]);
                unset($assignmentId);

                foreach ($menuKeys as $menuKey) {
                    DB::table('venue_staff_menu_permissions')->insert([
                        'user_id' => $staff['id'],
                        'venue_cluster_id' => $venue['id'],
                        'menu_key' => $menuKey,
                        'granted_by' => $venue['owner']['id'],
                        'created_at' => $venue['created_at']->addHours(18),
                        'updated_at' => $this->asOf,
                    ]);
                }

                for ($day = 0; $day < 30; $day++) {
                    $date = $this->from->addDays($day);
                    $isPast = $date->isBefore($this->asOf->startOfDay());
                    DB::table('venue_staff_shift_schedules')->insert([
                        'venue_cluster_id' => $venue['id'],
                        'user_id' => $staff['id'],
                        'venue_staff_shift_id' => $shiftId,
                        'date' => $date->toDateString(),
                        'start_time' => '06:00',
                        'end_time' => '14:00',
                        'status' => $isPast ? ($day % 9 === 0 ? 'absent' : 'checked_out') : 'scheduled',
                        'check_in_at' => $isPast && $day % 9 !== 0 ? $date->setTime(5, 55) : null,
                        'check_out_at' => $isPast && $day % 9 !== 0 ? $date->setTime(14, 5) : null,
                        'notes' => $day % 9 === 0 ? 'Nghỉ có báo trước.' : null,
                        'created_by' => $venue['owner']['id'],
                        'created_at' => $date->setTime(4, 0),
                        'updated_at' => $this->asOf,
                    ]);
                }
            }
        }
    }

    private function seedBookingsAndPayments(): void
    {
        $activeCourts = collect($this->courts)->flatten(1)->filter(fn (array $court): bool => $court['status'] === 'active')->values()->all();
        if (count($activeCourts) < 10) {
            throw new \RuntimeException('Not enough active courts for demo bookings.');
        }

        $slotUsage = [];
        for ($day = 0; $day < 30; $day++) {
            $date = $this->from->addDays($day);
            $count = $date->isWeekend() ? 20 : 14;
            if ($day === 29) {
                $count = 10;
            }

            for ($index = 0; $index < $count; $index++) {
                $court = $activeCourts[($day * 7 + $index) % count($activeCourts)];
                $clusterId = $this->courtCluster($court['id']);
                $startMinutes = 6 * 60 + (($index * 90 + ($day % 4) * 30) % (14 * 60));
                $duration = $index % 6 === 0 ? 90 : 60;
                $start = $this->minutesToTime($startMinutes);
                $end = $this->minutesToTime($startMinutes + $duration);
                $key = $court['id'].'|'.$date->toDateString();
                if (($slotUsage[$key] ?? []) && $this->overlaps($slotUsage[$key], $startMinutes, $startMinutes + $duration)) {
                    continue;
                }
                $slotUsage[$key][] = [$startMinutes, $startMinutes + $duration];

                $player = $this->players[($day * 3 + $index) % 59];
                $source = $index % 4 === 0 ? 'counter' : 'online';
                $paymentOption = match ($index % 8) {
                    0 => 'no_prepay',
                    1 => 'deposit',
                    2 => 'wallet',
                    default => 'full_payment',
                };
                $createdAt = $date->subDays(1 + ($index % 5))->setTime(8, 0)->addMinutes($index * 4);
                $past = $date->isBefore($this->asOf->startOfDay());
                $status = $past
                    ? match ($index % 20) {
                        0, 1 => 'no_show',
                        2, 3, 4 => 'cancelled',
                        5 => 'expired',
                        6 => 'rejected',
                        default => 'completed',
                    }
                    : ($date->isSameDay($this->asOf) && $index === 1 ? 'checked_in' : 'confirmed');

                $booking = $this->createBooking($player, $court, $clusterId, $date, $start, $end, $duration, $source, $paymentOption, $status, $createdAt);
                $this->addBookingItem($booking, $court, $start, $end, $duration, $createdAt);

                if ($status === 'completed') {
                    $this->completedBookings[] = $booking;
                } elseif ($date->isAfter($this->asOf->startOfDay())) {
                    $this->futureBookings[] = $booking;
                }
                if ($status === 'cancelled') {
                    $this->cancelledBookings[] = $booking;
                }

                if ($status !== 'expired' && $status !== 'rejected' && ($paymentOption !== 'no_prepay' || $source === 'counter')) {
                    $paid = $status !== 'pending_payment';
                    $this->createBookingPayment($booking, $paymentOption, $source, $paid, $createdAt);
                }
            }
        }

        $this->seedFutureBookings($activeCourts);
        $this->seedRecurringBookings($activeCourts);
    }

    private function createBooking(array $player, array $court, int $clusterId, CarbonImmutable $date, string $start, string $end, int $duration, string $source, string $paymentOption, string $status, CarbonImmutable $createdAt): array
    {
        $base = $this->priceMap[$clusterId][$court['court_type_id']] ?? 120000;
        $hourly = $start >= '17:00:00' ? round($base * 1.2, -3) : round($base * 0.9, -3);
        $total = round($hourly * $duration / 60, 2);
        $discount = ($this->bookingSequence % 7 === 0) ? round($total * 0.1, 2) : 0;
        $final = max(0, $total - $discount);
        $required = match ($paymentOption) {
            'deposit' => round($final * 0.3, 2),
            'no_prepay' => 0,
            default => $final,
        };
        $isPast = $date->isBefore($this->asOf->startOfDay());
        $approvalDeadline = $status === 'pending_approval' ? $createdAt->addMinutes(30) : null;
        $paymentDeadline = $status === 'pending_payment' ? $createdAt->addMinutes(20) : null;
        $bookingId = DB::table('bookings')->insertGetId([
            'booking_code' => 'DEMO-BK-'.str_pad((string) $this->bookingSequence, 6, '0', STR_PAD_LEFT),
            'customer_id' => $player['id'],
            'venue_court_id' => $court['id'],
            'requested_venue_court_id' => $court['id'],
            'venue_cluster_id' => $clusterId,
            'booking_date' => $date->toDateString(),
            'start_time' => $start,
            'end_time' => $end,
            'duration_minutes' => $duration,
            'total_price' => $final,
            'original_amount' => $total,
            'discount_amount' => $discount,
            'membership_tier_discount_amount' => 0,
            'membership_tier' => 'standard',
            'cashback_amount' => 0,
            'system_discount_amount' => 0,
            'venue_discount_amount' => 0,
            'final_amount' => $final,
            'payment_option' => $paymentOption,
            'effective_payment_option' => $paymentOption,
            'required_payment_amount' => $required,
            'source' => $source,
            'booking_type' => 'single',
            'status' => $status,
            'approval_deadline_at' => $approvalDeadline,
            'payment_deadline_at' => $paymentDeadline,
            'owner_approved_at' => in_array($status, ['confirmed', 'checked_in', 'completed'], true) && $paymentOption !== 'full_payment' ? $createdAt->addMinutes(15) : null,
            'owner_approved_by' => in_array($status, ['confirmed', 'checked_in', 'completed'], true) && $paymentOption !== 'full_payment' ? $this->venueOwnerId($clusterId) : null,
            'walk_in_name' => $source === 'counter' ? $player['full_name'] : null,
            'walk_in_phone' => $source === 'counter' ? $player['phone'] : null,
            'status_reason' => $status === 'cancelled' ? 'Khách hủy theo chính sách demo.' : ($status === 'no_show' ? 'Khách không check-in.' : null),
            'cancelled_by' => $status === 'cancelled' ? $player['id'] : null,
            'cancellation_initiator' => $status === 'cancelled' ? 'customer' : null,
            'cancellation_reason_type' => $status === 'cancelled' ? 'customer_request' : null,
            'cancelled_at' => $status === 'cancelled' ? $date->setTime(10, 0) : null,
            'created_by' => $source === 'counter' ? $this->venueOwnerId($clusterId) : $player['id'],
            'created_at' => $createdAt,
            'updated_at' => $this->asOf,
        ]);
        $this->bookingSequence++;

        $initialStatus = in_array($status, ['completed', 'checked_in', 'no_show'], true) ? 'pending_payment' : $status;
        $this->bookingHistory($bookingId, null, $initialStatus, $player['id'], $createdAt);
        $scheduledStart = $date->setTimeFromTimeString($start);
        $confirmedAt = $createdAt->addMinutes(15);
        if ($confirmedAt->greaterThanOrEqualTo($scheduledStart)) {
            $confirmedAt = $scheduledStart->subMinutes(30);
        }
        if (in_array($status, ['completed', 'checked_in'], true)) {
            $this->bookingHistory($bookingId, 'pending_payment', 'confirmed', $this->venueOwnerId($clusterId), $confirmedAt);
            $this->bookingHistory($bookingId, 'confirmed', 'checked_in', $this->venueOwnerId($clusterId), $scheduledStart->subMinutes(10));
            if ($status === 'completed') {
                $this->bookingHistory($bookingId, 'checked_in', 'completed', $this->venueOwnerId($clusterId), $date->setTimeFromTimeString($end)->addMinutes(5));
            }
        } elseif ($status === 'no_show') {
            $this->bookingHistory($bookingId, 'pending_payment', 'confirmed', $this->venueOwnerId($clusterId), $confirmedAt);
            $this->bookingHistory($bookingId, 'confirmed', 'no_show', $this->venueOwnerId($clusterId), $date->setTimeFromTimeString($end)->addMinutes(30));
        }

        return [
            'id' => $bookingId,
            'customer_id' => $player['id'],
            'venue_cluster_id' => $clusterId,
            'venue_court_id' => $court['id'],
            'date' => $date,
            'start' => $start,
            'end' => $end,
            'total' => $final,
            'required' => $required,
            'status' => $status,
            'payment_option' => $paymentOption,
            'source' => $source,
            'created_at' => $createdAt,
        ];
    }

    private function addBookingItem(array $booking, array $court, string $start, string $end, int $duration, CarbonImmutable $createdAt): int
    {
        $itemId = DB::table('booking_items')->insertGetId([
            'booking_id' => $booking['id'],
            'venue_court_id' => $court['id'],
            'requested_venue_court_id' => $court['id'],
            'start_time' => $start,
            'end_time' => $end,
            'duration_minutes' => $duration,
            'unit_price' => round($booking['total'] / max($duration / 60, 0.5), 2),
            'subtotal' => $booking['total'],
            'status' => in_array($booking['status'], ['cancelled', 'expired', 'rejected'], true) ? 'cancelled' : 'active',
            'status_reason' => in_array($booking['status'], ['cancelled', 'expired', 'rejected'], true) ? 'Booking không còn hiệu lực.' : null,
            'cancelled_by' => $booking['status'] === 'cancelled' ? $booking['customer_id'] : null,
            'cancelled_at' => $booking['status'] === 'cancelled' ? $booking['date']->setTime(10, 0) : null,
            'sort_order' => 1,
            'created_at' => $createdAt,
            'updated_at' => $this->asOf,
        ]);

        if (in_array($booking['status'], ['pending_payment', 'pending_approval'], true)) {
            DB::table('slot_locks')->insert([
                'venue_cluster_id' => $booking['venue_cluster_id'],
                'venue_court_id' => $booking['venue_court_id'],
                'lock_scope' => 'court',
                'booking_date' => $booking['date']->toDateString(),
                'start_time' => $start,
                'end_time' => $end,
                'locked_by' => 'booking:'.$booking['id'],
                'booking_id' => $booking['id'],
                'booking_item_id' => $itemId,
                'lock_type' => 'auto',
                'reason' => $booking['status'] === 'pending_approval' ? 'Chờ chủ sân duyệt booking demo.' : 'Chờ hoàn tất thanh toán booking demo.',
                'notified_booking_ids' => json_encode([], JSON_UNESCAPED_UNICODE),
                'notification_sent_at' => null,
                'expires_at' => $createdAt->addMinutes($booking['status'] === 'pending_approval' ? 30 : 20),
                'created_at' => $createdAt,
            ]);
        }

        return $itemId;
    }

    private function createBookingPayment(array $booking, string $paymentOption, string $source, bool $paid, CarbonImmutable $createdAt): void
    {
        $method = $paymentOption === 'wallet' ? 'wallet' : ($source === 'counter' ? 'cash' : 'sepay');
        $amount = $booking['required'];
        if ($amount <= 0) {
            return;
        }
        $walletId = $method === 'wallet'
            ? DB::table('user_wallets')->where('user_id', $booking['customer_id'])->value('id')
            : null;
        $paymentId = DB::table('payments')->insertGetId([
            'payment_code' => 'DEMO-PAY-'.str_pad((string) $this->paymentSequence, 6, '0', STR_PAD_LEFT),
            'payment_context' => 'booking',
            'booking_id' => $booking['id'],
            'system_bank_account_id' => $method === 'sepay' ? $this->systemBankId() : null,
            'user_wallet_id' => $walletId,
            'amount' => $amount,
            'wallet_amount' => $method === 'wallet' ? $amount : 0,
            'gateway_amount' => $method === 'wallet' ? 0 : $amount,
            'payment_kind' => $paymentOption === 'deposit' ? 'deposit' : 'full',
            'method' => $method,
            'gateway_txn_id' => $paid ? 'DEMO-TXN-'.$this->paymentSequence : null,
            'gateway_response' => json_encode(['demo' => true, 'source' => $source], JSON_UNESCAPED_UNICODE),
            'status' => $paid ? 'paid' : 'pending',
            'paid_at' => $paid ? $createdAt->addMinutes(12) : null,
            'created_at' => $createdAt->addMinutes(2),
            'updated_at' => $this->asOf,
        ]);
        $this->paymentSequence++;
        $this->paymentLog($paymentId, $paid ? 'payment_completed' : 'payment_created', $paid ? 'paid' : 'pending', $createdAt->addMinutes(12));
        if ($paid) {
            $this->paidPayments[$booking['id']] = ['id' => $paymentId, 'amount' => $amount, 'method' => $method];
        }

        if ($method === 'wallet' && $paid) {
            $ledgerId = $this->walletLedger($booking['customer_id'], 'payment', 'debit', $amount, 'payment', (string) $paymentId, $createdAt->addMinutes(12));
            DB::table('payments')->where('id', $paymentId)->update(['user_wallet_ledger_id' => $ledgerId]);
        }
    }

    private function seedFutureBookings(array $activeCourts): void
    {
        for ($i = 0; $i < 35; $i++) {
            $date = $this->asOf->startOfDay()->addDays(1 + ($i % 7));
            $court = $activeCourts[($i + 5) % count($activeCourts)];
            $clusterId = $this->courtCluster($court['id']);
            $start = $this->minutesToTime(17 * 60 + ($i % 5) * 60);
            $end = $this->minutesToTime($this->timeToMinutes($start) + 60);
            $player = $this->players[($i + 7) % 59];
            $status = match ($i % 5) {
                0 => 'pending_payment',
                1, 2 => 'pending_approval',
                default => 'confirmed',
            };
            $option = $status === 'pending_approval' ? ($i % 2 ? 'no_prepay' : 'deposit') : 'full_payment';
            $createdAt = in_array($status, ['pending_payment', 'pending_approval'], true)
                ? $this->asOf->subMinutes(10)->addMinutes($i)
                : $this->asOf->subHours(2)->addMinutes($i);
            $booking = $this->createBooking($player, $court, $clusterId, $date, $start, $end, 60, $i % 6 === 0 ? 'counter' : 'online', $option, $status, $createdAt);
            $this->addBookingItem($booking, $court, $start, $end, 60, $booking['created_at']);
            $this->futureBookings[] = $booking;
            if ($status === 'pending_payment' || ($status === 'pending_approval' && $option === 'deposit')) {
                $this->createBookingPayment(
                    $booking,
                    $option,
                    $booking['source'],
                    $status === 'pending_approval' && $i % 4 === 1,
                    $booking['created_at'],
                );
            }
        }
    }

    private function seedRecurringBookings(array $activeCourts): void
    {
        for ($group = 0; $group < 8; $group++) {
            $court = $activeCourts[$group % count($activeCourts)];
            $clusterId = $this->courtCluster($court['id']);
            $player = $this->players[($group + 20) % 59];
            $groupCode = 'DEMO-RECURRING-'.str_pad((string) ($group + 1), 3, '0', STR_PAD_LEFT);
            for ($occurrence = 0; $occurrence < 4; $occurrence++) {
                $date = $this->from->addDays(3 + $group + $occurrence * 7);
                $start = '19:00:00';
                $end = '20:00:00';
                $status = $date->isBefore($this->asOf->startOfDay()) ? 'completed' : 'confirmed';
                $booking = $this->createBooking($player, $court, $clusterId, $date, $start, $end, 60, 'online', 'full_payment', $status, $date->subDays(7)->setTime(9, 0));
                DB::table('bookings')->where('id', $booking['id'])->update([
                    'booking_type' => 'recurring',
                    'recurring_group_code' => $groupCode,
                    'recurring_start_date' => $this->from->addDays(3 + $group)->toDateString(),
                    'recurring_end_date' => $this->from->addDays(3 + $group + 21)->toDateString(),
                    'recurrence_type' => 'weekly',
                    'recurrence_interval' => 1,
                    'recurrence_days_of_week' => json_encode([(int) $date->dayOfWeek]),
                ]);
                $this->addBookingItem($booking, $court, $start, $end, 60, $booking['created_at']);
                if ($status === 'completed') {
                    $this->completedBookings[] = $booking;
                    $this->createBookingPayment($booking, 'full_payment', 'online', true, $booking['created_at']);
                } else {
                    $this->futureBookings[] = $booking;
                }
            }
        }
    }

    private function seedRefundsAndWallets(): void
    {
        foreach (array_slice($this->cancelledBookings, 0, 28) as $index => $booking) {
            $payment = $this->paidPayments[$booking['id']] ?? null;
            if (! $payment || $index % 6 === 0) {
                continue;
            }
            $refundStatus = match ($index % 5) {
                0 => 'pending_owner_confirmation',
                1, 3 => 'completed',
                2 => 'completed_cash',
                default => 'owner_rejected',
            };
            $refundAmount = round($payment['amount'] * 0.8, 2);
            $createdAt = $booking['date']->subDays(1)->setTime(11, 0);
            $ownerId = $this->venueOwnerId($booking['venue_cluster_id']);
            $isPending = $refundStatus === 'pending_owner_confirmation';
            $isCompleted = in_array($refundStatus, ['completed', 'completed_cash'], true);
            $isCash = $refundStatus === 'completed_cash';
            $refundId = DB::table('refunds')->insertGetId([
                'payment_id' => $payment['id'],
                'booking_id' => $booking['id'],
                'customer_id' => $booking['customer_id'],
                'amount' => $refundAmount,
                'refund_destination' => $isCash ? 'cash' : 'user_wallet',
                'user_wallet_id' => $isCash ? null : DB::table('user_wallets')->where('user_id', $booking['customer_id'])->value('id'),
                'reason' => 'Hoàn tiền demo theo chính sách.',
                'status' => $refundStatus,
                'status_reason' => $refundStatus === 'owner_rejected' ? 'Không đủ điều kiện hoàn theo chính sách đã công bố.' : null,
                'owner_confirmed_by' => $isPending ? null : $ownerId,
                'owner_confirmed_at' => $isPending ? null : $createdAt->copy()->addHours(2),
                'owner_confirm_note' => $isPending ? null : ($refundStatus === 'owner_rejected' ? 'Chủ sân từ chối theo chính sách.' : 'Chủ sân đã chọn hình thức hoàn tiền.'),
                'processed_by' => null,
                'processed_at' => null,
                'admin_confirmed_by' => null,
                'admin_confirmed_at' => null,
                'completed_at' => $isCompleted ? $createdAt->copy()->addHours(5) : null,
                'cash_refunded_by' => $isCash ? $ownerId : null,
                'cash_refunded_at' => $isCash ? $createdAt->copy()->addHours(5) : null,
                'cash_refund_note' => $isCash ? 'Chủ sân đã hoàn tiền mặt trực tiếp tại sân.' : null,
                'gateway_refund_txn_id' => null,
                'created_at' => $createdAt,
                'updated_at' => $this->asOf,
            ]);
            DB::table('refund_status_histories')->insert([
                'refund_id' => $refundId,
                'old_status' => null,
                'new_status' => $refundStatus,
                'changed_by' => $isPending ? $booking['customer_id'] : $ownerId,
                'actor_type' => $isPending ? 'user' : 'owner',
                'reason' => $isPending ? 'Khách gửi yêu cầu hoàn tiền.' : ($isCash ? 'Chủ sân đã hoàn tiền mặt tại sân.' : ($refundStatus === 'owner_rejected' ? 'Chủ sân từ chối hoàn tiền.' : 'Chủ sân đã xác nhận hoàn vào ví SportGo.')),
                'metadata' => json_encode(['demo' => true], JSON_UNESCAPED_UNICODE),
                'created_at' => $createdAt,
            ]);
            if ($refundStatus === 'completed') {
                $ledgerId = $this->walletLedger($booking['customer_id'], 'refund', 'credit', $refundAmount, 'refund', (string) $refundId, $createdAt->addHours(5));
                DB::table('refunds')->where('id', $refundId)->update([
                    'user_wallet_ledger_id' => $ledgerId,
                    'gateway_refund_txn_id' => 'USER-WALLET-'.$refundId,
                ]);
                DB::table('payments')->where('id', $payment['id'])->update(['status' => 'refunded']);
                $this->paymentLog($payment['id'], 'refund_completed', 'refunded', $createdAt->addHours(5));
            } elseif ($refundStatus === 'completed_cash') {
                DB::table('refunds')->where('id', $refundId)->update(['gateway_refund_txn_id' => 'CASH-'.$refundId]);
                DB::table('payments')->where('id', $payment['id'])->update(['status' => 'refunded']);
                $this->paymentLog($payment['id'], 'cash_refund_completed', 'refunded', $createdAt->addHours(5));
            }
        }

        foreach ($this->venues as $venue) {
            $totalEarned = 7000000 + $venue['id'] * 500000;
            $totalWithdrawn = $venue['id'] % 3 === 0 ? 2500000 : 1500000;
            $pendingWithdrawal = $venue['id'] % 3 === 0 ? 500000 : 0;
            DB::table('owner_wallets')->insert([
                'owner_id' => $venue['owner']['id'],
                'venue_cluster_id' => $venue['id'],
                'available_balance' => $totalEarned - $totalWithdrawn - $pendingWithdrawal,
                'pending_withdrawal_balance' => $pendingWithdrawal,
                'total_earned' => $totalEarned,
                'total_withdrawn' => $totalWithdrawn,
                'created_at' => $venue['created_at']->addDays(2),
                'updated_at' => $this->asOf,
            ]);

            $walletId = DB::table('owner_wallets')->where('owner_id', $venue['owner']['id'])->where('venue_cluster_id', $venue['id'])->value('id');
            $this->ownerLedger($walletId, $venue, 'credit', $totalEarned, 'booking_revenue', 'DEMO-WALLET-CREDIT-'.$venue['id'], $venue['created_at']->addDays(3));
            if ($totalWithdrawn > 0) {
                $this->ownerLedger($walletId, $venue, 'debit', $totalWithdrawn, 'withdrawal', 'DEMO-WALLET-WITHDRAW-'.$venue['id'], $this->asOf->subDays(3));
            }
            if ($pendingWithdrawal > 0) {
                $this->ownerLedger($walletId, $venue, 'hold', $pendingWithdrawal, 'withdrawal_hold', 'DEMO-WALLET-HOLD-'.$venue['id'], $this->asOf->subDay());
            }
        }
    }

    private function seedPlatformFees(): void
    {
        $tiers = DB::table('platform_fee_tiers')->where('is_active', true)->orderBy('min_courts')->get();
        foreach ($this->venues as $index => $venue) {
            $courtCount = count($this->courts[$venue['id']]);
            $tier = $tiers->first(fn ($item): bool => $courtCount >= $item->min_courts && ($item->max_courts === null || $courtCount <= $item->max_courts)) ?: $tiers->last();
            $amount = $courtCount * (float) $tier->price_per_court_month;
            $status = match ($index % 4) {
                0 => 'paid',
                1 => 'pending',
                2 => 'overdue',
                default => 'paid',
            };
            DB::table('venue_platform_fee_ledgers')->insert([
                'venue_cluster_id' => $venue['id'],
                'creation_source' => 'automation',
                'automation_key' => 'demo-platform-fee-'.$this->asOf->format('Y-m').'-'.$venue['id'],
                'tier_id' => $tier->id,
                'tier_name_snapshot' => $tier->name,
                'tier_min_courts_snapshot' => $tier->min_courts,
                'tier_max_courts_snapshot' => $tier->max_courts,
                'court_count' => $courtCount,
                'billing_cycle' => 'monthly',
                'period_months' => 1,
                'period_start' => $this->asOf->startOfMonth()->toDateString(),
                'period_end' => $this->asOf->endOfMonth()->toDateString(),
                'due_date' => $this->asOf->startOfMonth()->addDays(7)->toDateString(),
                'price_per_court_month' => $tier->price_per_court_month,
                'discount_percent' => 0,
                'pricing_snapshotted_at' => $this->asOf,
                'amount_due' => $amount,
                'amount_paid' => $status === 'paid' ? $amount : 0,
                'system_bank_account_id' => $this->systemBankId(),
                'payment_code' => $status === 'paid' ? 'DEMO-FEE-PAY-'.$index : null,
                'gateway_txn_id' => $status === 'paid' ? 'DEMO-FEE-TXN-'.$index : null,
                'gateway_response' => json_encode(['demo' => true], JSON_UNESCAPED_UNICODE),
                'status' => $status,
                'paid_at' => $status === 'paid' ? $this->asOf->subDays(3) : null,
                'payment_confirmed_by' => $status === 'paid' ? $this->users['finance']['id'] : null,
                'payment_confirmed_at' => $status === 'paid' ? $this->asOf->subDays(3) : null,
                'created_at' => $this->asOf->startOfMonth(),
                'updated_at' => $this->asOf,
            ]);
        }
    }

    private function seedFinanceOperations(): void
    {
        $withdrawals = [];
        foreach ($this->venues as $index => $venue) {
            $wallet = DB::table('owner_wallets')
                ->where('owner_id', $venue['owner']['id'])
                ->where('venue_cluster_id', $venue['id'])
                ->first();
            $bankAccountId = DB::table('owner_bank_accounts')
                ->where('owner_id', $venue['owner']['id'])
                ->where('status', 'active')
                ->value('id');
            $status = match ($index) {
                0, 1, 6, 9 => 'completed',
                2, 5, 8 => 'pending',
                3 => 'pending',
                4 => 'approved',
                default => 'rejected',
            };
            $amount = in_array($index, [2, 5, 8], true) ? 500000 : 650000 + ($index % 4) * 100000;
            $requestedAt = $this->asOf->subDays(8 - min($index, 5))->setTime(10, 0);
            $requestId = DB::table('owner_withdrawal_requests')->insertGetId([
                'request_code' => 'DEMO-WD-'.str_pad((string) ($index + 1), 4, '0', STR_PAD_LEFT),
                'source' => 'manual',
                'owner_id' => $venue['owner']['id'],
                'owner_wallet_id' => $wallet->id,
                'owner_bank_account_id' => $bankAccountId,
                'amount' => $amount,
                'status' => $status,
                'owner_note' => 'Rút doanh thu booking tháng này - dữ liệu demo.',
                'reviewed_by' => $status === 'pending' ? null : $this->users['finance']['id'],
                'reviewed_at' => $status === 'pending' ? null : $requestedAt->addHours(3),
                'review_note' => $status === 'rejected' ? 'Thông tin đối soát cần bổ sung.' : ($status === 'pending' ? null : 'Đã kiểm tra đối soát demo.'),
                'status_reason' => $status === 'rejected' ? 'Thiếu thông tin đối soát.' : null,
                'completed_by' => $status === 'completed' ? $this->users['finance']['id'] : null,
                'completed_at' => $status === 'completed' ? $requestedAt->addDays(1) : null,
                'transfer_reference' => $status === 'completed' ? 'DEMO-TRANSFER-'.str_pad((string) ($index + 1), 3, '0', STR_PAD_LEFT) : null,
                'payout_transfer_code' => $status === 'completed' ? 'DEMO-PAYOUT-'.str_pad((string) ($index + 1), 3, '0', STR_PAD_LEFT) : null,
                'payout_qr_created_at' => $status === 'approved' ? $requestedAt->addHours(4) : null,
                'metadata' => json_encode(['demo' => true, 'venue_name' => $venue['name']], JSON_UNESCAPED_UNICODE),
                'requested_at' => $requestedAt,
                'created_at' => $requestedAt,
                'updated_at' => $this->asOf,
            ]);
            $withdrawals[] = [
                'id' => $requestId,
                'owner_id' => $venue['owner']['id'],
                'amount' => $amount,
                'status' => $status,
                'at' => $requestedAt,
            ];

            if ($status === 'completed') {
                DB::table('internal_receipts')->insert([
                    'receipt_code' => 'DEMO-REC-WD-'.str_pad((string) ($index + 1), 4, '0', STR_PAD_LEFT),
                    'receipt_type' => 'withdrawal',
                    'receiptable_type' => 'owner_withdrawal_request',
                    'receiptable_id' => (string) $requestId,
                    'issued_to_user_id' => $venue['owner']['id'],
                    'issued_by' => $this->users['finance']['id'],
                    'title' => 'Biên nhận chi trả doanh thu đối tác demo',
                    'amount' => $amount,
                    'currency' => 'VND',
                    'status' => 'issued',
                    'issued_at' => $requestedAt->addDays(1),
                    'metadata' => json_encode(['demo' => true], JSON_UNESCAPED_UNICODE),
                    'created_at' => $requestedAt->addDays(1),
                    'updated_at' => $this->asOf,
                ]);
            }
        }

        foreach (DB::table('venue_platform_fee_ledgers')->where('status', 'paid')->get() as $index => $fee) {
            DB::table('internal_receipts')->insert([
                'receipt_code' => 'DEMO-REC-FEE-'.str_pad((string) ($index + 1), 4, '0', STR_PAD_LEFT),
                'receipt_type' => 'platform_fee',
                'receiptable_type' => 'venue_platform_fee_ledger',
                'receiptable_id' => (string) $fee->id,
                'issued_to_user_id' => $this->venueOwnerId((int) $fee->venue_cluster_id),
                'issued_by' => $this->users['finance']['id'],
                'title' => 'Biên nhận phí nền tảng tháng demo',
                'amount' => $fee->amount_paid,
                'currency' => 'VND',
                'status' => 'issued',
                'issued_at' => $fee->paid_at ?: $this->asOf->subDays(3),
                'metadata' => json_encode(['demo' => true], JSON_UNESCAPED_UNICODE),
                'created_at' => $fee->created_at ?: $this->asOf->startOfMonth(),
                'updated_at' => $this->asOf,
            ]);
        }

        $events = [];
        foreach (DB::table('payments')->where('payment_context', 'booking')->whereIn('status', ['paid', 'refunded'])->orderBy('paid_at')->limit(80)->get() as $payment) {
            $events[] = ['direction' => 'in', 'amount' => (float) $payment->amount, 'type' => 'booking_payment', 'reference_type' => 'payment', 'reference_id' => (string) $payment->id, 'description' => 'Thu tiền booking demo.', 'at' => CarbonImmutable::parse($payment->paid_at, $this->timezone)];
        }
        foreach (DB::table('venue_platform_fee_ledgers')->where('status', 'paid')->orderBy('paid_at')->get() as $fee) {
            $events[] = ['direction' => 'in', 'amount' => (float) $fee->amount_paid, 'type' => 'platform_fee_received', 'reference_type' => 'venue_platform_fee_ledger', 'reference_id' => (string) $fee->id, 'description' => 'Thu phí nền tảng demo.', 'at' => CarbonImmutable::parse($fee->paid_at ?: $this->asOf->subDays(3), $this->timezone)];
        }
        foreach (DB::table('refunds')->where('status', 'completed')->orderBy('completed_at')->get() as $refund) {
            $events[] = ['direction' => 'out', 'amount' => (float) $refund->amount, 'type' => 'refund_to_customer', 'reference_type' => 'refund', 'reference_id' => (string) $refund->id, 'description' => 'Hoàn tiền booking demo vào ví người chơi.', 'at' => CarbonImmutable::parse($refund->completed_at, $this->timezone)];
        }
        foreach (array_filter($withdrawals, fn (array $row): bool => $row['status'] === 'completed') as $withdrawal) {
            $events[] = ['direction' => 'out', 'amount' => (float) $withdrawal['amount'], 'type' => 'withdrawal_to_owner', 'reference_type' => 'owner_withdrawal_request', 'reference_id' => (string) $withdrawal['id'], 'description' => 'Chi trả doanh thu cho đối tác demo.', 'at' => $withdrawal['at']->addDay()];
        }
        usort($events, fn (array $a, array $b): int => $a['at']->getTimestamp() <=> $b['at']->getTimestamp());

        $balanceRow = DB::table('system_wallet_balances')->where('system_bank_account_id', $this->systemBankId())->first();
        $balance = (float) ($balanceRow->current_balance ?? 0);
        foreach ($events as $index => $event) {
            $before = $balance;
            $balance = round($event['direction'] === 'in' ? $balance + $event['amount'] : $balance - $event['amount'], 2);
            DB::table('system_wallet_ledgers')->insert([
                'system_bank_account_id' => $this->systemBankId(),
                'transaction_ref' => 'DEMO-SWL-'.str_pad((string) ($index + 1), 5, '0', STR_PAD_LEFT),
                'direction' => $event['direction'],
                'entry_kind' => 'actual',
                'amount' => $event['amount'],
                'balance_before' => $before,
                'balance_after' => $balance,
                'refund_reserved_before' => 0,
                'refund_reserved_after' => 0,
                'voucher_reserved_before' => 0,
                'voucher_reserved_after' => 0,
                'transaction_type' => $event['type'],
                'reference_type' => $event['reference_type'],
                'reference_id' => $event['reference_id'],
                'description' => $event['description'],
                'metadata' => json_encode(['demo' => true], JSON_UNESCAPED_UNICODE),
                'transacted_at' => $event['at'],
                'synced_at' => $event['at']->addMinutes(1),
                'created_at' => $event['at'],
            ]);
        }
        if ($balanceRow) {
            DB::table('system_wallet_balances')->where('id', $balanceRow->id)->update([
                'current_balance' => $balance,
                'bank_balance' => $balance,
                'last_synced_at' => $this->asOf,
                'bank_synced_at' => $this->asOf,
                'updated_at' => $this->asOf,
            ]);
        }

        DB::table('venue_access_restrictions')->insert([
            'venue_cluster_id' => $this->venues[2]['id'],
            'restriction_type' => 'admin_manual',
            'access_mode' => 'limited',
            'reason' => 'Lịch sử hạn chế truy cập demo đã được gỡ sau khi đối tác bổ sung hồ sơ.',
            'starts_at' => $this->asOf->subDays(18),
            'ends_at' => $this->asOf->subDays(16),
            'created_by' => $this->users['admin']['id'],
            'status' => 'expired',
            'created_at' => $this->asOf->subDays(18),
            'updated_at' => $this->asOf,
        ]);
    }

    private function seedContentAndSupport(): void
    {
        $publishedPosts = [];
        for ($i = 1; $i <= 45; $i++) {
            $status = $i % 11 === 0 ? 'pending_review' : 'published';
            $author = $this->players[$i % 59];
            $postId = DB::table('community_posts')->insertGetId([
                'author_id' => $author['id'],
                'content' => 'Bài viết cộng đồng demo số '.$i.' chia sẻ kinh nghiệm chơi thể thao tại SportGo.',
                'status' => $status,
                'reviewed_by' => $status === 'published' ? $this->users['moderator']['id'] : null,
                'reviewed_at' => $status === 'published' ? $this->asOf->subDays($i % 20) : null,
                'ai_verdict' => $status === 'published' ? 'safe' : 'review',
                'ai_score' => $status === 'published' ? 0.98 : 0.62,
                'view_count' => 30 + $i * 5,
                'like_count' => 3 + ($i % 12),
                'comment_count' => 2 + ($i % 8),
                'created_at' => $this->from->addDays($i % 25),
                'updated_at' => $this->asOf,
            ]);
            if ($status === 'published') {
                $publishedPosts[] = $postId;
            }
            for ($comment = 0; $comment < 3; $comment++) {
                DB::table('community_post_comments')->insert([
                    'post_id' => $postId,
                    'user_id' => $this->players[($i + $comment + 1) % 59]['id'],
                    'content' => 'Bình luận demo hữu ích, cảm ơn bạn đã chia sẻ.',
                    'status' => 'visible',
                    'created_at' => $this->from->addDays($i % 25)->addHours($comment + 1),
                    'updated_at' => $this->asOf,
                ]);
            }
            foreach (array_slice($this->players, $i % 10, 5) as $player) {
                DB::table('community_post_likes')->insertOrIgnore([
                    'post_id' => $postId,
                    'user_id' => $player['id'],
                    'created_at' => $this->from->addDays(($i + $player['id']) % 25),
                ]);
            }
        }

        foreach ($this->venues as $index => $venue) {
            for ($post = 1; $post <= 3; $post++) {
                DB::table('venue_posts')->insert([
                    'venue_cluster_id' => $venue['id'],
                    'author_id' => $venue['owner']['id'],
                    'title' => $venue['name'].' - Tin hoạt động '.$post,
                    'content' => 'Thông tin hoạt động và ưu đãi mới tại '.$venue['name'].'.',
                    'short_description' => 'Tin cập nhật từ cụm sân demo.',
                    'status' => $post === 3 && $index % 3 === 0 ? 'pending_review' : 'published',
                    'reviewed_by' => $post === 3 && $index % 3 === 0 ? null : $this->users['moderator']['id'],
                    'reviewed_at' => $post === 3 && $index % 3 === 0 ? null : $this->asOf->subDays($post),
                    'view_count' => 20 + $post * 10,
                    'like_count' => 3 + $post,
                    'comment_count' => 1 + $post,
                    'created_at' => $this->from->addDays($post + $index),
                    'updated_at' => $this->asOf,
                    'post_type' => $post === 1 ? 'promotion' : 'news',
                    'slug' => Str::slug($venue['name'].'-'.$post).'-demo-'.$index,
                ]);
            }
        }

        foreach (array_slice($this->futureBookings, 0, 18) as $index => $booking) {
            if ($booking['status'] !== 'confirmed') {
                continue;
            }
            $postId = DB::table('player_posts')->insertGetId([
                'booking_id' => $booking['id'],
                'author_id' => $booking['customer_id'],
                'title' => 'Tìm người chơi cùng - Demo',
                'description' => 'Mình còn thiếu người cho buổi chơi sắp tới.',
                'needed_players' => 1 + ($index % 3),
                'cost_per_player' => 0,
                'status' => $index % 6 === 0 ? 'full' : 'open',
                'created_at' => $booking['created_at'],
                'updated_at' => $this->asOf,
            ]);
            foreach (array_slice($this->players, 30 + ($index % 10), 2) as $participant) {
                DB::table('player_post_participants')->insert([
                    'post_id' => $postId,
                    'user_id' => $participant['id'],
                    'status' => 'approved',
                    'message' => 'Mình muốn tham gia buổi này.',
                    'responded_at' => $this->asOf->subHours(3),
                    'created_at' => $this->asOf->subHours(4),
                    'updated_at' => $this->asOf,
                ]);
            }
            $conversationId = DB::table('conversations')->insertGetId([
                'type' => 'player_post',
                'reference_type' => 'player_post',
                'reference_id' => (string) $postId,
                'title' => 'Nhóm giao lưu demo',
                'created_by' => $booking['customer_id'],
                'last_message_at' => $this->asOf->subHours($index % 12),
                'created_at' => $booking['created_at'],
                'updated_at' => $this->asOf,
            ]);
            foreach ([$booking['customer_id'], $this->players[30 + ($index % 10)]['id']] as $userId) {
                DB::table('conversation_participants')->insert([
                    'conversation_id' => $conversationId,
                    'user_id' => $userId,
                    'joined_at' => $booking['created_at'],
                ]);
            }
            for ($message = 0; $message < 3; $message++) {
                DB::table('messages')->insert([
                    'conversation_id' => $conversationId,
                    'sender_id' => $message % 2 ? $this->players[30 + ($index % 10)]['id'] : $booking['customer_id'],
                    'content' => $message % 2 ? 'Đã xác nhận tham gia nhé.' : 'Mình đã tạo nhóm cho buổi chơi.',
                    'is_system' => false,
                    'is_pinned' => false,
                    'is_recalled' => false,
                    'created_at' => $this->asOf->subHours(5 - $message),
                ]);
            }
        }

        foreach (array_slice($this->completedBookings, 0, 180) as $index => $booking) {
            DB::table('reviews')->insert([
                'booking_id' => $booking['id'],
                'customer_id' => $booking['customer_id'],
                'venue_cluster_id' => $booking['venue_cluster_id'],
                'rating' => 4 + ($index % 2),
                'comment' => 'Sân sạch, nhân viên thân thiện, đặt lịch thuận tiện.',
                'is_visible' => true,
                'created_at' => $booking['date']->setTimeFromTimeString($booking['end'])->addHours(3),
                'updated_at' => $this->asOf,
            ]);
        }

        $violationType = DB::table('violation_types')->where('is_active', true)->value('id');
        foreach (array_slice($publishedPosts, 0, 16) as $index => $postId) {
            DB::table('reports')->insert([
                'reporter_id' => $this->players[($index + 4) % 59]['id'],
                'reportable_type' => 'community_post',
                'reportable_id' => (string) $postId,
                'violation_type_id' => $violationType,
                'severity_level' => $index % 4 === 0 ? 'moderate' : 'mild',
                'score_contribution' => $index % 4 === 0 ? 4 : 1,
                'auto_action_taken' => false,
                'reason' => $index % 3 === 0 ? 'spam' : 'other',
                'description' => 'Báo cáo demo để minh họa hàng chờ moderation.',
                'status' => match ($index % 4) { 0 => 'pending', 1 => 'reviewing', 2 => 'resolved', default => 'dismissed' },
                'reviewed_by' => $index % 4 > 1 ? $this->users['moderator']['id'] : null,
                'reviewed_at' => $index % 4 > 1 ? $this->asOf->subDays(1) : null,
                'created_at' => $this->asOf->subDays(10 - ($index % 8)),
            ]);
        }

        $confirmedFutureBookings = array_values(array_filter(
            $this->futureBookings,
            fn (array $booking): bool => $booking['status'] === 'confirmed',
        ));
        foreach (array_slice($confirmedFutureBookings, 0, 10) as $index => $booking) {
            $status = match ($index % 4) { 0 => 'open', 1 => 'processing', 2 => 'resolved', default => 'closed' };
            $complaintId = DB::table('complaints')->insertGetId([
                'complaint_type' => 'venue',
                'is_vip_priority' => $index % 4 === 0,
                'booking_id' => $booking['id'],
                'venue_cluster_id' => $booking['venue_cluster_id'],
                'customer_id' => $booking['customer_id'],
                'content' => 'Khiếu nại demo về trải nghiệm đặt sân và cần được hỗ trợ.',
                'status' => $status,
                'assigned_to' => $status === 'open' ? null : $this->users['complaints']['id'],
                'resolve_note' => $status === 'resolved' ? 'Đã liên hệ và xử lý xong.' : null,
                'resolved_by' => $status === 'resolved' ? $this->users['complaints']['id'] : null,
                'resolved_at' => $status === 'resolved' ? $this->asOf->subDays(1) : null,
                'created_at' => $this->asOf->subDays(5),
                'updated_at' => $this->asOf,
            ]);
            DB::table('complaint_replies')->insert([
                'complaint_id' => $complaintId,
                'user_id' => $this->users['complaints']['id'],
                'content' => 'SportGo đã tiếp nhận yêu cầu và đang xử lý.',
                'created_at' => $this->asOf->subDays(4),
                'updated_at' => $this->asOf->subDays(4),
            ]);
        }
    }

    private function seedNotificationsAndAudit(): void
    {
        $notificationUsers = array_merge([$this->users['admin'], $this->users['finance']], array_slice($this->players, 0, 20));
        foreach ($notificationUsers as $index => $user) {
            DB::table('notifications')->insert([
                'user_id' => $user['id'],
                'type' => $index % 2 ? 'booking_confirmed' : 'system_notice',
                'title' => $index % 2 ? 'Booking đã được xác nhận' : 'Thông báo hệ thống demo',
                'body' => 'Đây là thông báo demo được tạo từ hoạt động trong hệ thống.',
                'reference_type' => $index % 2 ? 'booking' : null,
                'reference_id' => $index % 2 && isset($this->futureBookings[$index]) ? (string) $this->futureBookings[$index]['id'] : null,
                'data' => json_encode(['demo' => true], JSON_UNESCAPED_UNICODE),
                'is_read' => $index % 3 === 0,
                'read_at' => $index % 3 === 0 ? $this->asOf->subDays(1) : null,
                'created_at' => $this->asOf->subDays($index % 10),
            ]);
        }

        for ($i = 0; $i < 100; $i++) {
            DB::table('audit_logs')->insert([
                'actor_id' => $i % 2 ? $this->users['admin']['id'] : $this->users['finance']['id'],
                'actor_type' => 'user',
                'action' => $i % 3 ? 'demo.data_created' : 'demo.workflow_transition',
                'module' => $i % 2 ? 'booking' : 'finance',
                'entity_type' => $i % 2 ? 'bookings' : 'payments',
                'entity_id' => (string) (($i % max(1, count($this->completedBookings))) + 1),
                'old_values' => json_encode([], JSON_UNESCAPED_UNICODE),
                'new_values' => json_encode(['demo' => true], JSON_UNESCAPED_UNICODE),
                'metadata' => json_encode(['seed_as_of' => $this->asOf->toDateTimeString()], JSON_UNESCAPED_UNICODE),
                'reason' => 'Demo seed audit trail.',
                'severity' => 'info',
                'context' => 'demo',
                'created_at' => $this->from->addDays($i % 30),
            ]);
        }
    }

    private function bookingHistory(int $bookingId, ?string $old, string $new, ?int $actor, CarbonImmutable $at): void
    {
        DB::table('booking_status_histories')->insert([
            'booking_id' => $bookingId,
            'from_status' => $old,
            'to_status' => $new,
            'reason_code' => 'demo_seed',
            'reason' => 'Demo workflow transition.',
            'actor_id' => $actor,
            'metadata' => json_encode(['demo' => true], JSON_UNESCAPED_UNICODE),
            'created_at' => $at,
        ]);
    }

    private function paymentLog(int $paymentId, string $event, string $status, CarbonImmutable $at): void
    {
        DB::table('payment_logs')->insert([
            'payment_id' => $paymentId,
            'event_type' => $event,
            'request_payload' => json_encode(['demo' => true], JSON_UNESCAPED_UNICODE),
            'response_payload' => json_encode(['status' => $status], JSON_UNESCAPED_UNICODE),
            'status_before' => null,
            'status_after' => $status,
            'gateway_txn_id' => $status === 'paid' ? 'DEMO-LOG-'.$paymentId : null,
            'created_at' => $at,
        ]);
    }

    private function walletLedger(int $userId, string $type, string $direction, float $amount, string $referenceType, string $referenceId, CarbonImmutable $at): int
    {
        $walletId = DB::table('user_wallets')->where('user_id', $userId)->value('id');
        if (! $walletId) {
            $walletId = DB::table('user_wallets')->insertGetId([
                'user_id' => $userId,
                'balance' => 0,
                'locked_balance' => 0,
                'status' => 'active',
                'created_at' => $at,
                'updated_at' => $this->asOf,
            ]);
        }
        $wallet = DB::table('user_wallets')->where('id', $walletId)->first();
        $before = (float) $wallet->balance;
        $after = round($direction === 'credit' ? $before + $amount : $before - $amount, 2);
        DB::table('user_wallets')->where('id', $walletId)->update(['balance' => $after, 'updated_at' => $this->asOf]);
        return DB::table('user_wallet_ledgers')->insertGetId([
            'user_wallet_id' => $walletId,
            'transaction_code' => 'DEMO-UWL-'.str_pad((string) $this->walletLedgerSequence++, 7, '0', STR_PAD_LEFT),
            'type' => $type,
            'direction' => $direction,
            'amount' => $amount,
            'balance_before' => $before,
            'balance_after' => $after,
            'reference_type' => $referenceType,
            'reference_id' => $referenceId,
            'status' => 'completed',
            'note' => 'Biến động ví demo.',
            'created_by' => $userId,
            'created_at' => $at,
            'updated_at' => $this->asOf,
        ]);
    }

    private function ownerLedger(int $walletId, array $venue, string $type, float $amount, string $referenceType, string $referenceId, CarbonImmutable $at): void
    {
        $last = DB::table('owner_wallet_ledgers')->where('owner_wallet_id', $walletId)->orderByDesc('id')->first();
        $before = (float) ($last?->balance_after ?? 0);
        $direction = in_array($type, ['debit', 'hold'], true) ? 'debit' : 'credit';
        $after = round($direction === 'credit' ? $before + $amount : $before - $amount, 2);
        DB::table('owner_wallet_ledgers')->insert([
            'owner_wallet_id' => $walletId,
            'owner_id' => $venue['owner']['id'],
            'venue_cluster_id' => $venue['id'],
            'type' => $type,
            'direction' => $direction,
            'amount' => $amount,
            'balance_before' => $before,
            'balance_after' => $after,
            'status' => 'completed',
            'reference_code' => $referenceId,
            'reference_type' => $referenceType,
            'reference_id' => $referenceId,
            'transaction_code' => 'DEMO-OWL-'.str_pad((string) $this->ownerLedgerSequence++, 7, '0', STR_PAD_LEFT),
            'description' => $type === 'credit' ? 'Doanh thu booking demo.' : 'Biến động số dư đối tác demo.',
            'note' => 'Dữ liệu demo.',
            'metadata' => json_encode(['demo' => true], JSON_UNESCAPED_UNICODE),
            'created_at' => $at,
            'updated_at' => $this->asOf,
        ]);
    }

    private function systemBankId(): int
    {
        return (int) DB::table('system_bank_accounts')->where('is_default', true)->where('status', 'active')->value('id');
    }

    private function courtCluster(int $courtId): int
    {
        foreach ($this->courts as $clusterId => $courts) {
            if (collect($courts)->contains(fn (array $court): bool => $court['id'] === $courtId)) {
                return (int) $clusterId;
            }
        }
        throw new \RuntimeException('Court cluster not found: '.$courtId);
    }

    private function venueOwnerId(int $clusterId): int
    {
        $venue = collect($this->venues)->firstWhere('id', $clusterId);
        return (int) $venue['owner']['id'];
    }

    private function minutesToTime(int $minutes): string
    {
        return sprintf('%02d:%02d:00', intdiv($minutes, 60), $minutes % 60);
    }

    private function timeToMinutes(string $time): int
    {
        [$hour, $minute] = array_map('intval', explode(':', substr($time, 0, 5)));
        return $hour * 60 + $minute;
    }

    private function overlaps(array $ranges, int $start, int $end): bool
    {
        foreach ($ranges as [$existingStart, $existingEnd]) {
            if ($start < $existingEnd && $end > $existingStart) {
                return true;
            }
        }
        return false;
    }

    private function integrityCheck(): void
    {
        $checks = [
            ['bookings', 'booking_items', 'id', 'booking_id'],
            ['bookings', 'payments', 'id', 'booking_id'],
            ['venue_clusters', 'venue_courts', 'id', 'venue_cluster_id'],
            ['venue_clusters', 'booking_configs', 'id', 'venue_cluster_id'],
            ['users', 'user_roles', 'id', 'user_id'],
        ];
        foreach ($checks as [$parent, $child, $parentKey, $childKey]) {
            $query = DB::table($child)
                ->leftJoin($parent, $child.'.'.$childKey, '=', $parent.'.'.$parentKey)
                ->whereNull($parent.'.'.$parentKey);
            if ($child === 'payments') {
                $query->where('payments.payment_context', 'booking')->whereNotNull('payments.booking_id');
            }
            $orphans = $query->count();
            if ($orphans > 0) {
                throw new \RuntimeException("Demo integrity check failed: {$child} has {$orphans} orphan rows.");
            }
        }

        $badPending = DB::table('bookings')
            ->where('status', 'pending_approval')
            ->whereNull('approval_deadline_at')
            ->count();
        if ($badPending > 0) {
            throw new \RuntimeException('Demo integrity check failed: pending approval booking without deadline.');
        }

        $badReviews = DB::table('reviews')->join('bookings', 'bookings.id', '=', 'reviews.booking_id')->where('bookings.status', '!=', 'completed')->count();
        if ($badReviews > 0) {
            throw new \RuntimeException('Demo integrity check failed: review attached to non-completed booking.');
        }

        $allowedRefundStatuses = ['pending_owner_confirmation', 'completed', 'completed_cash', 'owner_rejected'];
        $badRefunds = DB::table('refunds')->whereNotIn('status', $allowedRefundStatuses)->count();
        if ($badRefunds > 0) {
            throw new \RuntimeException('Demo integrity check failed: refunds contain a legacy status.');
        }

        if (Schema::hasTable('refund_status_histories')) {
            $badRefundHistories = DB::table('refund_status_histories')
                ->where(function ($query) use ($allowedRefundStatuses): void {
                    $query->whereNotNull('old_status')->whereNotIn('old_status', $allowedRefundStatuses);
                })
                ->orWhereNotIn('new_status', $allowedRefundStatuses)
                ->count();
            if ($badRefundHistories > 0) {
                throw new \RuntimeException('Demo integrity check failed: refund histories contain a legacy status.');
            }
        }

        $this->command?->line('Demo integrity checks passed.');
    }
}
