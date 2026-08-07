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
        $sunOwner = User::query()->where('username', 'owner_sun')->first();

        if (! $owner) {
            return;
        }

        VenueCluster::query()->where('slug', 'sportgo-test-cluster')->delete();

        $clusters = [
            [
                'name' => 'Green Sport Ba Đình',
                'slug' => 'green-sport-ba-dinh',
                'description' => 'Cụm sân đang hoạt động, đã hoàn tất hồ sơ đối tác và hợp đồng đang hiệu lực.',
                'phone_contact' => '0902000001',
                'province' => 'Hà Nội',
                'ward' => 'Kim Mã',
                'address' => 'Số 12 Kim Mã, Ba Đình',
                'latitude' => 21.0362360,
                'longitude' => 105.7905830,
                'status' => 'active',
            ],
            [
                'name' => 'Sun Sport Cầu Giấy',
                'slug' => 'sun-sport-cau-giay',
                'owner_username' => 'owner_sun',
                'description' => 'Cụm sân đang chờ duyệt hồ sơ đối tác, chưa mở booking active.',
                'phone_contact' => '0902000002',
                'province' => 'Hà Nội',
                'ward' => 'Dịch Vọng',
                'address' => 'Số 8 phố Trần Thái Tông, Cầu Giấy',
                'latitude' => 21.0365200,
                'longitude' => 105.7897200,
                'status' => 'pending',
            ],
            [
                'name' => 'Victory Sport Hà Đông',
                'slug' => 'victory-sport-ha-dong',
                'description' => 'Cụm sân đa môn tại Hà Đông, phục vụ các lịch chơi trong ngày và đặt trước.',
                'phone_contact' => '0902000003',
                'province' => 'Hà Nội',
                'ward' => 'Văn Quán',
                'address' => 'Đường Trần Phú',
                'latitude' => 20.9685190,
                'longitude' => 105.7853120,
                'status' => 'pending',
            ],
            [
                'name' => 'Green Sport Cầu Giấy',
                'slug' => 'green-sport-cau-giay',
                'description' => 'Cụm sân thứ hai của chủ sân Green Sport, thuận tiện cho khu vực phía Tây Hà Nội.',
                'phone_contact' => '0902000004',
                'province' => 'Hà Nội',
                'ward' => 'Dịch Vọng Hậu',
                'address' => 'Số 25 phố Trần Quốc Vượng, Cầu Giấy',
                'latitude' => 21.0354100,
                'longitude' => 105.7812600,
                'status' => 'active',
            ],
            [
                'name' => 'Green Sport Tây Hồ',
                'slug' => 'green-sport-tay-ho',
                'description' => 'Cụm sân thể thao ngoài trời với sân pickleball, tennis và bóng đá mini.',
                'phone_contact' => '0902000005',
                'province' => 'Hà Nội',
                'ward' => 'Xuân La',
                'address' => 'Số 18 đường Võ Chí Công, Tây Hồ',
                'latitude' => 21.0742800,
                'longitude' => 105.8079100,
                'status' => 'active',
            ],
        ];

        foreach ($clusters as $cluster) {
            VenueCluster::query()->updateOrCreate(
                ['slug' => $cluster['slug']],
                [
                    'owner_id' => ($cluster['owner_username'] ?? null) === 'owner_sun' && $sunOwner ? $sunOwner->id : $owner->id,
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
