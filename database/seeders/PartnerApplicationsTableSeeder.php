<?php

namespace Database\Seeders;

use App\Models\PartnerApplication;
use App\Models\User;
use App\Models\VenueCluster;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Schema;

class PartnerApplicationsTableSeeder extends Seeder
{
    public function run(): void
    {
        if (! Schema::hasTable('partner_applications') || ! Schema::hasTable('users')) {
            return;
        }

        $admin = User::query()->where('username', 'admin')->first();
        $staff = User::query()->where('username', 'systemstaff')->first();
        $owner = User::query()->where('username', 'owner')->first();
        $user = User::query()->where('username', 'user')->first();
        $approvedCluster = VenueCluster::query()->where('slug', 'sportgo-cau-giay')->first();

        if (! $owner || ! $user) {
            return;
        }

        PartnerApplication::query()->updateOrCreate(
            [
                'user_id' => $owner->id,
                'venue_name' => 'SportGo Cầu Giấy',
            ],
            [
                'business_name' => 'Hộ kinh doanh SportGo Cầu Giấy',
                'tax_code' => '0109999001',
                'venue_address' => 'Dịch Vọng, Cầu Giấy, Hà Nội',
                'venue_map_url' => 'https://maps.google.com/?q=SportGo+Cau+Giay',
                'venue_latitude' => 21.0362360,
                'venue_longitude' => 105.7905830,
                'status' => 'approved',
                'reviewed_by' => $admin?->id,
                'status_reason' => null,
                'approved_venue_cluster_id' => $approvedCluster?->id,
                'submitted_at' => now()->subDays(25),
                'reviewed_at' => now()->subDays(23),
            ]
        );

        PartnerApplication::query()->updateOrCreate(
            [
                'user_id' => $user->id,
                'venue_name' => 'SportGo Thanh Xuân',
            ],
            [
                'business_name' => 'CLB Thể thao Thanh Xuân',
                'tax_code' => '0109999002',
                'venue_address' => 'Nguyễn Trãi, Thanh Xuân, Hà Nội',
                'venue_map_url' => 'https://maps.google.com/?q=SportGo+Thanh+Xuan',
                'venue_latitude' => 20.9948120,
                'venue_longitude' => 105.8076540,
                'status' => 'reviewing',
                'reviewed_by' => $staff?->id,
                'status_reason' => null,
                'approved_venue_cluster_id' => null,
                'submitted_at' => now()->subDays(3),
                'reviewed_at' => now()->subDay(),
            ]
        );

        PartnerApplication::query()->updateOrCreate(
            [
                'user_id' => $user->id,
                'venue_name' => 'Sân Demo Hồ Tây',
            ],
            [
                'business_name' => 'Sân Demo Hồ Tây',
                'tax_code' => '0109999003',
                'venue_address' => 'Tây Hồ, Hà Nội',
                'venue_map_url' => 'https://maps.google.com/?q=Ho+Tay+Ha+Noi',
                'venue_latitude' => 21.0612310,
                'venue_longitude' => 105.8194540,
                'status' => 'rejected',
                'reviewed_by' => $admin?->id,
                'status_reason' => 'Hồ sơ thiếu giấy tờ xác minh quyền sử dụng địa điểm.',
                'approved_venue_cluster_id' => null,
                'submitted_at' => now()->subDays(12),
                'reviewed_at' => now()->subDays(10),
            ]
        );
    }
}
