<?php

namespace Database\Seeders;

use App\Models\CourtType;
use App\Models\Role;
use App\Models\User;
use App\Models\UserRole;
use App\Models\VenueCluster;
use App\Models\VenueStaffAssignment;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Schema;

class VenueStaffAssignmentsTableSeeder extends Seeder
{
    public function run(): void
    {
        if (
            ! Schema::hasTable('users')
            || ! Schema::hasTable('court_types')
            || ! Schema::hasTable('venue_clusters')
            || ! Schema::hasTable('venue_staff_assignments')
        ) {
            return;
        }

        $owner = User::query()->where('username', 'owner')->first();
        $staff = User::query()->where('username', 'venuestaff')->first();
        $cluster = VenueCluster::query()->where('slug', 'green-sport-ba-dinh')->first();
        $badminton = CourtType::query()->where('name', 'Cầu lông (Sân tiêu chuẩn)')->first();

        if (! $owner || ! $staff || ! $cluster) {
            return;
        }

        VenueStaffAssignment::query()->updateOrCreate(
            [
                'user_id' => $staff->id,
                'venue_cluster_id' => $cluster->id,
                'scope_key' => 'all',
            ],
            [
                'scope_type' => 'all_cluster',
                'court_type_id' => null,
                'assigned_by' => $owner->id,
                'status' => 'active',
            ],
        );

        $venueOwnerRole = Role::query()->where('name', 'venue_owner')->first();
        $venueStaffRole = Role::query()->where('name', 'venue_staff')->first();

        if ($venueOwnerRole) {
            UserRole::query()->updateOrCreate(
                [
                    'user_id' => $owner->id,
                    'role_id' => $venueOwnerRole->id,
                    'scope_type' => 'venue',
                    'scope_id' => $cluster->id,
                ],
                ['granted_by' => null],
            );
        }

        if ($venueStaffRole) {
            UserRole::query()->updateOrCreate(
                [
                    'user_id' => $staff->id,
                    'role_id' => $venueStaffRole->id,
                    'scope_type' => 'venue',
                    'scope_id' => $cluster->id,
                ],
                ['granted_by' => $owner->id],
            );
        }

        if (! $badminton) {
            return;
        }

        VenueStaffAssignment::query()->updateOrCreate(
            [
                'user_id' => $staff->id,
                'venue_cluster_id' => $cluster->id,
                'scope_key' => 'court_type:' . $badminton->id,
            ],
            [
                'scope_type' => 'court_type',
                'court_type_id' => $badminton->id,
                'assigned_by' => $owner->id,
                'status' => 'active',
            ],
        );
    }
}
