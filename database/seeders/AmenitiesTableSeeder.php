<?php

namespace Database\Seeders;

use App\Models\Amenity;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Schema;

class AmenitiesTableSeeder extends Seeder
{
    public function run(): void
    {
        if (! Schema::hasTable('amenities')) {
            return;
        }

        $admin = User::query()->where('username', 'admin')->first();
        $owner = User::query()->where('username', 'owner')->first();

        $adminId = $admin?->id;
        $ownerId = $owner?->id;

        $activeAmenities = [
            ['Wifi', 'Wifi miễn phí tốc độ cao.'],
            ['Bãi gửi xe', 'Bãi gửi xe rộng rãi, an toàn.'],
            ['Điều hòa', 'Hệ thống điều hòa làm mát phòng chờ.'],
            ['Phòng thay đồ', 'Phòng thay đồ sạch sẽ, tiện nghi.'],
            ['Nhà vệ sinh', 'Nhà vệ sinh sạch sẽ, riêng biệt nam nữ.'],
            ['Căng tin', 'Căng tin phục vụ nước uống và đồ ăn nhẹ.'],
            ['Tủ gửi đồ', 'Tủ khóa gửi đồ cá nhân an toàn.'],
            ['Cho thuê vợt', 'Dịch vụ cho thuê vợt chất lượng tốt.'],
            ['Cho thuê bóng', 'Dịch vụ cho thuê bóng thi đấu.'],
            ['Đèn chiếu sáng', 'Hệ thống đèn LED chiếu sáng ban đêm.'],
            ['Mái che', 'Sân có mái che chống mưa nắng.'],
            ['Khu nghỉ chờ', 'Khu nghỉ chờ rộng rãi cho vận động viên.'],
            ['Nước uống', 'Nước uống tinh khiết miễn phí hoặc có bán.'],
            ['Camera an ninh', 'Hệ thống camera giám sát an ninh 24/7.'],
        ];

        foreach ($activeAmenities as [$name, $description]) {
            $this->saveAmenity($name, [
                'description' => $description,
                'status' => 'active',
                'created_by' => $adminId,
                'reviewed_by' => $adminId,
                'reviewed_at' => now(),
                'status_reason' => null,
            ]);
        }

        $this->saveAmenity('Máy bắn cầu tự động', [
            'description' => 'Chủ sân đề xuất thêm tiện ích máy bắn cầu tự động.',
            'status' => 'pending_review',
            'created_by' => $ownerId,
            'reviewed_by' => null,
            'reviewed_at' => null,
            'status_reason' => null,
        ]);

        $this->saveAmenity('Dịch vụ huấn luyện cá nhân', [
            'description' => 'Tiện ích đang bị từ chối vì chủ sân chưa gửi đủ hồ sơ huấn luyện viên.',
            'status' => 'rejected',
            'created_by' => $ownerId,
            'reviewed_by' => $adminId,
            'reviewed_at' => now(),
            'status_reason' => 'Chưa có hồ sơ xác minh huấn luyện viên.',
        ]);

        $this->saveAmenity('Khu xông hơi', [
            'description' => 'Tiện ích tạm ngưng sử dụng để rà soát lại vận hành.',
            'status' => 'inactive',
            'created_by' => $adminId,
            'reviewed_by' => $adminId,
            'reviewed_at' => now(),
            'status_reason' => 'Tạm ngưng sử dụng để kiểm tra an toàn vận hành.',
        ]);
    }

    private function saveAmenity(string $name, array $data): void
    {
        $amenity = Amenity::withTrashed()->where('name', $name)->first();

        if ($amenity) {
            if ($amenity->trashed()) {
                $amenity->restore();
            }

            $amenity->update(array_merge(['name' => $name], $data));
            return;
        }

        Amenity::create(array_merge(['name' => $name], $data));
    }
}
