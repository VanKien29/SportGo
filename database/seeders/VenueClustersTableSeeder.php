<?php

namespace Database\Seeders;

use App\Models\User;
use App\Models\VenueCluster;
use Illuminate\Database\Seeder;

class VenueClustersTableSeeder extends Seeder
{
    public function run(): void
    {
        // The seeded partner account must own its demo venues.  Using the first
        // user here accidentally assigns them to the super admin, leaving the
        // `owner` account unable to manage its own venue posts.
        $defaultOwner = User::query()->where('username', 'owner')->first()
            ?? User::query()->first();
        if (! $defaultOwner) {
            return;
        }

        $sunOwner = User::query()->where('username', 'owner_sun')->first() ?? $defaultOwner;

        // Xóa cụm sân test cũ
        VenueCluster::query()->where('slug', 'sportgo-test-cluster')->delete();

        $clusters = [
            [
                'name' => 'Green Sport Ba Đình',
                'slug' => 'green-sport-ba-dinh',
                'description' => 'Cụm sân đang hoạt động, đã hoàn tất hồ sơ đối tác và hợp đồng đang hiệu lực.',
                'phone_contact' => '0902000001',
                'province' => 'Thành phố Hà Nội',
                'province_code' => '01',
                'ward' => 'Phường Kim Mã',
                'ward_code' => '001',
                'address' => 'Số 12 Kim Mã, Ba Đình',
                'latitude' => 21.0362360,
                'longitude' => 105.7905830,
                'status' => 'active',
            ],
            [
                'name' => 'Sun Sport Cầu Giấy',
                'slug' => 'sun-sport-cau-giay',
                'owner_username' => 'owner_sun',
                'description' => 'Cụm sân hiện đại bậc nhất khu vực Cầu Giấy.',
                'phone_contact' => '0902000002',
                'province' => 'Thành phố Hà Nội',
                'province_code' => '01',
                'ward' => 'Phường Dịch Vọng',
                'ward_code' => '002',
                'address' => 'Số 8 phố Trần Thái Tông, Cầu Giấy',
                'latitude' => 21.0365200,
                'longitude' => 105.7897200,
                'status' => 'active',
            ],
            [
                'name' => 'Victory Sport Hà Đông',
                'slug' => 'victory-sport-ha-dong',
                'description' => 'Cụm sân đa môn tại Hà Đông, phục vụ các lịch chơi trong ngày và đặt trước.',
                'phone_contact' => '0902000003',
                'province' => 'Thành phố Hà Nội',
                'province_code' => '01',
                'ward' => 'Phường Văn Quán',
                'ward_code' => '004',
                'address' => 'Đường Trần Phú, Hà Đông',
                'latitude' => 20.9685190,
                'longitude' => 105.7853120,
                'status' => 'active',
            ],
            [
                'name' => 'Green Sport Cầu Giấy',
                'slug' => 'green-sport-cau-giay-2',
                'description' => 'Cụm sân thứ hai của chủ sân Green Sport, thuận tiện cho khu vực phía Tây Hà Nội.',
                'phone_contact' => '0902000004',
                'province' => 'Thành phố Hà Nội',
                'province_code' => '01',
                'ward' => 'Phường Dịch Vọng Hậu',
                'ward_code' => '003',
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
                'province' => 'Thành phố Hà Nội',
                'province_code' => '01',
                'ward' => 'Phường Xuân La',
                'ward_code' => '005',
                'address' => 'Số 18 đường Võ Chí Công, Tây Hồ',
                'latitude' => 21.0742800,
                'longitude' => 105.8079100,
                'status' => 'active',
            ],
            [
                'name' => 'Diamond Sport Thanh Xuân',
                'slug' => 'diamond-sport-thanh-xuan',
                'description' => 'Cụm sân thể thao cao cấp tại Lê Văn Lương, trang thiết bị tiêu chuẩn quốc tế.',
                'phone_contact' => '0902000006',
                'province' => 'Thành phố Hà Nội',
                'province_code' => '01',
                'ward' => 'Phường Nhân Chính',
                'ward_code' => '006',
                'address' => 'Số 68 Lê Văn Lương, Thanh Xuân',
                'latitude' => 21.0062800,
                'longitude' => 105.8039100,
                'status' => 'active',
            ],
            [
                'name' => 'Star Arena Nam Từ Liêm',
                'slug' => 'star-arena-nam-tu-liem',
                'description' => 'Tổ hợp thể thao đa năng gần sân vận động Mỹ Đình.',
                'phone_contact' => '0902000007',
                'province' => 'Thành phố Hà Nội',
                'province_code' => '01',
                'ward' => 'Phường Mỹ Đình 1',
                'ward_code' => '007',
                'address' => 'Số 15 Lê Đức Thọ, Nam Từ Liêm',
                'latitude' => 21.0282800,
                'longitude' => 105.7689100,
                'status' => 'active',
            ],
            [
                'name' => 'Elite Sport Hoàn Kiếm',
                'slug' => 'elite-sport-hoan-kiem',
                'description' => 'Cụm sân thể thao cao cấp trung tâm phố cổ Hà Nội.',
                'phone_contact' => '0902000008',
                'province' => 'Thành phố Hà Nội',
                'province_code' => '01',
                'ward' => 'Phường Tràng Tiền',
                'ward_code' => '008',
                'address' => 'Số 5 Phố Tràng Tiền, Hoàn Kiếm',
                'latitude' => 21.0252800,
                'longitude' => 105.8569100,
                'status' => 'active',
            ],
            [
                'name' => 'Riverside Sport Cần Thơ',
                'slug' => 'riverside-sport-can-tho',
                'description' => 'Cụm sân thể thao hiện đại tại trung tâm Thành phố Cần Thơ.',
                'phone_contact' => '0902000009',
                'province' => 'Thành phố Cần Thơ',
                'province_code' => '92',
                'ward' => 'Phường An Bình',
                'ward_code' => '31165',
                'address' => 'Đường Nguyễn Văn Cừ, Phường An Bình, Ninh Kiều',
                'latitude' => 10.0275000,
                'longitude' => 105.7538000,
                'status' => 'active',
            ],
            [
                'name' => 'Saigon Arena Quận 1',
                'slug' => 'saigon-arena-quan-1',
                'description' => 'Tổ hợp thể thao cao cấp trung tâm TP. Hồ Chí Minh.',
                'phone_contact' => '0902000010',
                'province' => 'Thành phố Hồ Chí Minh',
                'province_code' => '79',
                'ward' => 'Phường Bến Nghé',
                'ward_code' => '101',
                'address' => 'Số 10 Nguyễn Huệ, Bến Nghé, Quận 1',
                'latitude' => 10.7760000,
                'longitude' => 106.7000000,
                'status' => 'active',
            ],
        ];

        foreach ($clusters as $item) {
            $owner = (isset($item['owner_username']) && $item['owner_username'] === 'owner_sun')
                ? $sunOwner
                : $defaultOwner;

            unset($item['owner_username']);

            VenueCluster::query()->updateOrCreate(
                ['slug' => $item['slug']],
                array_merge($item, ['owner_id' => $owner->id])
            );
        }

        $this->command?->info('Đã seed ' . count($clusters) . ' cụm sân hoạt động đầy đủ tỉnh thành!');
    }
}
