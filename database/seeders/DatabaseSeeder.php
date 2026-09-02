<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Schema;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // Demo data is opt-in in production. This prevents an accidental
        // `php artisan db:seed` from recreating demo users, venues and
        // transactions in the live database.
        if (app()->environment('production') && ! filter_var(env('SEED_DEMO_DATA', false), FILTER_VALIDATE_BOOLEAN)) {
            $this->command?->warn('Demo seeding skipped in production. Set SEED_DEMO_DATA=true only for an intentional demo reset.');

            return;
        }

        // Demo data is intentionally orchestrated by one deterministic seeder.
        // The legacy chain below is retained for reference but must not run
        // together with it because several legacy seeders create stale rows.
        $this->call(DemoDatabaseSeeder::class);
        return;

        $this->callIfTablesExist(RolesTableSeeder::class, ['roles']);
        $this->callIfTablesExist(PermissionsTableSeeder::class, ['permissions']);
        $this->callIfTablesExist(RolePermissionsTableSeeder::class, ['roles', 'permissions', 'role_permissions']);
        $this->callIfTablesExist(UsersTableSeeder::class, ['users']);
        $this->callIfTablesExist(UserRolesTableSeeder::class, ['users', 'roles', 'user_roles']);
        $this->callIfTablesExist(VietnamLocationsSeeder::class, ['vn_provinces', 'vn_wards']);

        $this->callIfTablesExist(CourtTypesTableSeeder::class, ['court_types']);
        $this->callIfTablesExist(VenueClustersTableSeeder::class, ['users', 'venue_clusters']);
        $this->callIfTablesExist(AmenitiesTableSeeder::class, ['amenities']);
        $this->callIfTablesExist(VenueClusterAmenitiesTableSeeder::class, [
            'amenities',
            'venue_clusters',
            'venue_cluster_amenities',
        ]);
        $this->callIfTablesExist(VenueCourtsTableSeeder::class, ['court_types', 'venue_clusters', 'venue_courts']);
        $this->callIfTablesExist(BookingConfigsTableSeeder::class, ['venue_clusters', 'booking_configs']);
        $this->callIfTablesExist(VenueStaffAssignmentsTableSeeder::class, [
            'users',
            'court_types',
            'venue_clusters',
            'venue_staff_assignments',
        ]);
        $this->callIfTablesExist(VenueStaffShiftsTableSeeder::class, [
            'users',
            'venue_clusters',
            'venue_staff_shifts',
            'venue_staff_shift_schedules',
        ]);

        $this->callIfTablesExist(PriceSlotsTableSeeder::class, ['court_types', 'venue_clusters', 'price_slots']);
        $this->callIfTablesExist(VenueBasePricesTableSeeder::class, [
            'venue_clusters',
            'venue_courts',
            'venue_base_prices',
        ]);
        $this->callIfTablesExist(HolidayPricesTableSeeder::class, ['court_types', 'venue_clusters', 'holiday_prices']);
        $this->callIfTablesExist(PlatformFeeTiersTableSeeder::class, ['platform_fee_tiers']);
        $this->callIfTablesExist(SystemBankAccountSeeder::class, ['system_bank_accounts']);
        $this->callIfTablesExist(MembershipPackagesSeeder::class, ['membership_packages']);

        $this->callIfTablesExist(SystemPoliciesTableSeeder::class, ['users', 'system_policies']);
        $this->callIfTablesExist(ViolationTypesSeeder::class, ['violation_types']);
        $this->callIfTablesExist(SeverityLevelsSeeder::class, ['severity_levels']);
        $this->callIfTablesExist(ModerationThresholdsSeeder::class, ['moderation_thresholds', 'system_policies']);
        $this->callIfTablesExist(PenaltyEscalationRulesSeeder::class, ['penalty_escalation_rules', 'system_policies']);
        $this->callIfTablesExist(PolicyActionBindingsTableSeeder::class, ['policy_action_bindings']);
        $this->callIfTablesExist(PolicyRulesTableSeeder::class, [
            'users',
            'system_policies',
            'policy_action_bindings',
            'policy_rules',
        ]);
        $this->callIfTablesExist(PolicyRuleTemplatesTableSeeder::class, [
            'system_policies',
            'policy_rules',
            'policy_rule_templates',
        ]);
        $this->callIfTablesExist(PolicyOverrideConstraintsTableSeeder::class, [
            'system_policies',
            'policy_rules',
            'policy_override_constraints',
        ]);
        $this->callIfTablesExist(PolicyStatusHistoriesTableSeeder::class, [
            'users',
            'system_policies',
            'policy_status_histories',
        ]);
        $this->callIfTablesExist(VenuePolicyRulesSeeder::class, [
            'users',
            'venue_clusters',
            'policy_rules',
            'venue_policy_rules',
        ]);

        $this->callIfTablesExist(PartnerApplicationsTableSeeder::class, ['users', 'partner_applications']);
        $this->callIfTablesExist(PartnerApplicationCourtsTableSeeder::class, [
            'partner_applications',
            'partner_application_courts',
            'court_types',
        ]);
        $this->callIfTablesExist(OwnerBankAccountsTableSeeder::class, [
            'users',
            'partner_applications',
            'owner_bank_accounts',
        ]);
        $this->callIfTablesExist(PartnerApplicationDocumentsTableSeeder::class, [
            'partner_applications',
            'partner_application_documents',
        ]);
        $this->callIfTablesExist(PartnerApplicationStatusHistoriesTableSeeder::class, [
            'partner_applications',
            'partner_application_status_histories',
        ]);

        $this->callIfTablesExist(DocumentTemplatesTableSeeder::class, ['document_templates']);
        $this->callIfTablesExist(PartnerContractsTableSeeder::class, [
            'users',
            'partner_applications',
            'partner_contracts',
        ]);
        $this->callIfTablesExist(PartnerTerminationRequestsTableSeeder::class, [
            'users',
            'partner_contracts',
            'partner_termination_requests',
        ]);
        $this->callIfTablesExist(PartnerSettlementsTableSeeder::class, [
            'partner_settlements',
            'partner_termination_requests',
        ]);
        $this->callIfTablesExist(PartnerSettlementItemsTableSeeder::class, [
            'partner_settlements',
            'partner_settlement_items',
        ]);
        $this->callIfTablesExist(GeneratedDocumentsTableSeeder::class, [
            'document_templates',
            'generated_documents',
        ]);
        $this->callIfTablesExist(PartnerDocumentLinksSeeder::class, [
            'partner_applications',
            'partner_contracts',
            'generated_documents',
        ]);
        $this->callIfTablesExist(GeneratedDocumentSignaturesTableSeeder::class, [
            'users',
            'generated_documents',
            'generated_document_signatures',
        ]);
        $this->callIfTablesExist(PartnerTerminationDocumentsTableSeeder::class, [
            'partner_termination_requests',
            'partner_termination_documents',
            'generated_documents',
        ]);
        $this->callIfTablesExist(PartnerTerminationStatusHistoriesTableSeeder::class, [
            'partner_termination_requests',
            'partner_termination_status_histories',
        ]);

        $this->callIfTablesExist(VouchersTableSeeder::class, [
            'users',
            'vouchers',
            'voucher_scopes',
        ]);

        $this->callIfTablesExist(BookingFinanceTestDataSeeder::class, [
            'users',
            'venue_clusters',
            'venue_courts',
            'bookings',
            'booking_items',
            'payments',
            'refunds',
            'slot_locks',
        ]);

        $this->callIfTablesExist(PlayerPostsTableSeeder::class, ['users', 'bookings', 'player_posts']);
        $this->callIfTablesExist(PlayerPostParticipantsTableSeeder::class, [
            'users',
            'player_posts',
            'player_post_participants',
        ]);
        $this->callIfTablesExist(VenueAccessRestrictionsTableSeeder::class, [
            'venue_clusters',
            'venue_access_restrictions',
        ]);
        $this->callIfTablesExist(VenuePlatformFeeLedgersTableSeeder::class, [
            'venue_clusters',
            'venue_courts',
            'platform_fee_tiers',
            'venue_platform_fee_ledgers',
        ]);

        $this->callIfTablesExist(HashtagsTableSeeder::class, ['hashtags']);
        $this->callIfTablesExist(SystemPostsTableSeeder::class, ['users', 'system_posts']);
        $this->callIfTablesExist(BannersTableSeeder::class, ['users', 'banners']);
        $this->callIfTablesExist(ModerationConfigsTableSeeder::class, ['users', 'moderation_configs']);
        $this->callIfTablesExist(VipPackageVouchersSeeder::class, [
            'users',
            'vouchers',
            'voucher_scopes',
        ]);
        $this->callIfTablesExist(CommunityPostsTableSeeder::class, ['users', 'community_posts']);
        $this->callIfTablesExist(CommunityPostCommentsTableSeeder::class, [
            'users',
            'community_posts',
            'community_post_comments',
        ]);
        $this->callIfTablesExist(VenuePostsTableSeeder::class, ['users', 'venue_clusters', 'venue_posts']);
        $this->callIfTablesExist(ReportsTableSeeder::class, ['users', 'reports']);
        $this->callIfTablesExist(ComplaintsTableSeeder::class, ['users', 'bookings', 'complaints']);
        $this->callIfTablesExist(ComplaintSeeder::class, ['users', 'complaints']);
        $this->callIfTablesExist(InternalReceiptsTableSeeder::class, ['internal_receipts']);
        $this->callIfTablesExist(MediaTableSeeder::class, ['media']);
        $this->callIfTablesExist(AuditLogsTableSeeder::class, ['audit_logs']);
        $this->callIfTablesExist(NotificationsTableSeeder::class, ['users', 'notifications']);

        $this->callIfTablesExist(ChatConversationsTableSeeder::class, [
            'users',
            'conversations',
            'conversation_participants',
            'messages',
        ]);

        $this->callIfTablesExist(ServiceCategoriesTableSeeder::class, ['service_categories']);
        $this->callIfTablesExist(VenueClusterServicesTableSeeder::class, ['venue_cluster_services', 'venue_clusters', 'service_categories']);
        $this->callIfTablesExist(StaffDemoDataSeeder::class, ['users', 'venue_clusters', 'venue_staff_shifts', 'venue_staff_shift_schedules', 'bookings']);
    }

    private function callIfTablesExist(string $seeder, array $tables): void
    {
        foreach ($tables as $table) {
            if (! Schema::hasTable($table)) {
                return;
            }
        }

        $this->call($seeder);
    }
}
