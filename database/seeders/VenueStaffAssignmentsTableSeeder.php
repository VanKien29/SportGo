<?php

namespace Database\Seeders;

use App\Models\CourtType;
use App\Models\User;
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
        $cluster = VenueCluster::query()->where('slug', 'sportgo-cau-giay')->first();
        $badminton = CourtType::query()->where('name', 'Cầu lông')->first();

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
            ]
        );

        if (! $badminton) {
            return;
        }

        VenueStaffAssignment::query()->updateOrCreate(
            [
                'user_id' => $staff->id,
                'venue_cluster_id' => $cluster->id,
                'scope_key' => 'court_type:'.$badminton->id,
            ],
            [
                'scope_type' => 'court_type',
                'court_type_id' => $badminton->id,
                'assigned_by' => $owner->id,
                'status' => 'active',
            ]
        );
    }
}
