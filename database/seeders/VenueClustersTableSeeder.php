<?php

namespace Database\Seeders;

use App\Models\User;
use App\Models\VenueCluster;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Schema;

class VenueClustersTableSeeder extends Seeder
{
    public function run(): void
    {
        if (! Schema::hasTable('users') || ! Schema::hasTable('venue_clusters')) {
            return;
        }

        $owner = User::query()->where('username', 'owner')->first();

        if (! $owner) {
            return;
        }

        VenueCluster::query()->where('slug', 'sportgo-test-cluster')->delete();

        $clusters = [
            [
                'name' => 'SportGo Cầu Giấy',
                'slug' => 'sportgo-cau-giay',
                'description' => 'Cụm sân demo tại Cầu Giấy để test chức năng quản lý sân.',
                'phone_contact' => '0902000001',
                'province' => 'Hà Nội',
                'ward' => 'Dịch Vọng',
                'address' => 'Số 1 Dịch Vọng',
                'latitude' => 21.0362360,
                'longitude' => 105.7905830,
                'status' => 'active',
            ],
            [
                'name' => 'SportGo Mỹ Đình',
                'slug' => 'sportgo-my-dinh',
                'description' => 'Cụm sân demo tại Mỹ Đình để test lịch và bảng giá.',
                'phone_contact' => '0902000002',
                'province' => 'Hà Nội',
                'ward' => 'Mỹ Đình 1',
                'address' => 'Đường Lê Đức Thọ',
                'latitude' => 21.0285110,
                'longitude' => 105.7783390,
                'status' => 'active',
            ],
            [
                'name' => 'SportGo Hà Đông',
                'slug' => 'sportgo-ha-dong',
                'description' => 'Cụm sân demo dùng để test quyền owner trong thời gian chuyển tiếp.',
                'phone_contact' => '0902000003',
                'province' => 'Hà Nội',
                'ward' => 'Văn Quán',
                'address' => 'Đường Trần Phú',
                'latitude' => 20.9685190,
                'longitude' => 105.7853120,
                'status' => 'active',
            ],
            [
                'name' => 'SportGo Ba Đình',
                'slug' => 'sportgo-ba-dinh',
                'description' => 'Cụm sân demo dùng để test trạng thái owner bị chặn quyền.',
                'phone_contact' => '0902000004',
                'province' => 'Hà Nội',
                'ward' => 'Kim Mã',
                'address' => 'Phố Kim Mã',
                'latitude' => 21.0328640,
                'longitude' => 105.8131040,
                'status' => 'active',
            ],
        ];

        foreach ($clusters as $cluster) {
            VenueCluster::query()->updateOrCreate(
                ['slug' => $cluster['slug']],
                [
                    'owner_id' => $owner->id,
                    'name' => $cluster['name'],
                    'description' => $cluster['description'],
                    'phone_contact' => $cluster['phone_contact'],
                    'province' => $cluster['province'],
                    'ward' => $cluster['ward'],
                    'address' => $cluster['address'],
                    'map_url' => null,
                    'latitude' => $cluster['latitude'],
                    'longitude' => $cluster['longitude'],
                    'status' => $cluster['status'],
                    'status_reason' => null,
                    'locked_at' => null,
                    'locked_until' => null,
                    'locked_by' => null,
                    'rating_avg' => 0,
                    'rating_count' => 0,
                ],
            );
        }
    }
}
