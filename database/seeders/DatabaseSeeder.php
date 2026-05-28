<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Schema;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->callIfTablesExist(RolesTableSeeder::class, ['roles']);
        $this->callIfTablesExist(PermissionsTableSeeder::class, ['permissions']);
        $this->callIfTablesExist(RolePermissionsTableSeeder::class, ['roles', 'permissions', 'role_permissions']);
        $this->callIfTablesExist(UsersTableSeeder::class, ['users']);
        $this->callIfTablesExist(UserRolesTableSeeder::class, ['users', 'roles', 'user_roles']);

        $this->callIfTablesExist(CourtTypesTableSeeder::class, ['court_types']);
        $this->callIfTablesExist(VenueClustersTableSeeder::class, ['users', 'venue_clusters']);
        $this->callIfTablesExist(VenueCourtsTableSeeder::class, ['court_types', 'venue_clusters', 'venue_courts']);
        $this->callIfTablesExist(BookingConfigsTableSeeder::class, ['venue_clusters', 'booking_configs']);
        $this->callIfTablesExist(VenueStaffAssignmentsTableSeeder::class, [
            'users',
            'court_types',
            'venue_clusters',
            'venue_staff_assignments',
        ]);

        $this->callIfTablesExist(PriceSlotsTableSeeder::class, ['court_types', 'venue_clusters', 'price_slots']);
        $this->callIfTablesExist(HolidayPricesTableSeeder::class, ['court_types', 'venue_clusters', 'holiday_prices']);
        $this->callIfTablesExist(PlatformFeeTiersTableSeeder::class, ['platform_fee_tiers']);

        $this->callIfTablesExist(SystemPoliciesTableSeeder::class, ['users', 'system_policies']);
        $this->callIfTablesExist(HashtagsTableSeeder::class, ['hashtags']);
        $this->callIfTablesExist(SystemPostsTableSeeder::class, ['users', 'system_posts']);
        $this->callIfTablesExist(BannersTableSeeder::class, ['users', 'banners']);
        $this->callIfTablesExist(ModerationConfigsTableSeeder::class, ['users', 'moderation_configs']);
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
