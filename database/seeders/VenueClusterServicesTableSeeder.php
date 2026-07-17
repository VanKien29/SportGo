<?php

namespace Database\Seeders;

use App\Models\VenueCluster;
use App\Models\VenueClusterService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class VenueClusterServicesTableSeeder extends Seeder
{
    public function run(): void
    {
        if (! Schema::hasTable('venue_cluster_services') || ! Schema::hasTable('venue_clusters')) {
            return;
        }

        // Lấy tất cả cụm sân hiện có
        $clusters = VenueCluster::all();

        if ($clusters->isEmpty()) {
            return;
        }

        // Xóa sạch dữ liệu cũ để tránh trùng lặp khi seed lại
        VenueClusterService::query()->delete();

        foreach ($clusters as $cluster) {
            $services = [
                // Nước uống (11111111-1111-1111-1111-111111111111)
                [
                    'category_id' => '11111111-1111-1111-1111-111111111111',
                    'name' => 'Nước ngọt Sting dâu',
                    'price' => 15000.00,
                    'unit' => 'chai',
                    'status' => 'active',
                    'description' => 'Nước tăng lực Sting hương dâu 320ml ướp lạnh.',
                ],
                [
                    'category_id' => '11111111-1111-1111-1111-111111111111',
                    'name' => 'Nước bù khoáng Revive',
                    'price' => 15000.00,
                    'unit' => 'chai',
                    'status' => 'active',
                    'description' => 'Nước bù khoáng chanh muối Revive 500ml.',
                ],
                [
                    'category_id' => '11111111-1111-1111-1111-111111111111',
                    'name' => 'Nước suối Aquafina',
                    'price' => 10000.00,
                    'unit' => 'chai',
                    'status' => 'active',
                    'description' => 'Nước khoáng tinh khiết Aquafina 500ml.',
                ],

                // Cho thuê vợt (22222222-2222-2222-2222-222222222222)
                [
                    'category_id' => '22222222-2222-2222-2222-222222222222',
                    'name' => 'Thuê vợt Yonex Astrox 88D Play',
                    'price' => 30000.00,
                    'unit' => 'tiếng',
                    'status' => 'active',
                    'description' => 'Vợt cầu lông Yonex chính hãng, trợ lực tốt cho người chơi.',
                ],
                [
                    'category_id' => '22222222-2222-2222-2222-222222222222',
                    'name' => 'Thuê vợt Lining Tectonic 7',
                    'price' => 50000.00,
                    'unit' => 'buổi',
                    'status' => 'active',
                    'description' => 'Vợt Lining cao cấp, căng sẵn 10.5kg cho người chơi trình độ trung bình - khá.',
                ],

                // Bán cầu / Bóng (33333333-3333-3333-3333-333333333333)
                [
                    'category_id' => '33333333-3333-3333-3333-333333333333',
                    'name' => 'Hộp cầu lông Hải Yến (12 quả)',
                    'price' => 240000.00,
                    'unit' => 'hộp',
                    'status' => 'active',
                    'description' => 'Cầu Hải Yến đỏ chính hãng, độ bền cao, bay ổn định.',
                ],
                [
                    'category_id' => '33333333-3333-3333-3333-333333333333',
                    'name' => 'Cầu lông lẻ Hải Yến',
                    'price' => 22000.00,
                    'unit' => 'quả',
                    'status' => 'active',
                    'description' => 'Bán lẻ quả cầu lông Hải Yến đỏ.',
                ],

                // Đồ ăn nhẹ (44444444-4444-4444-4444-444444444444)
                [
                    'category_id' => '44444444-4444-4444-4444-444444444444',
                    'name' => 'Mì ly Omachi sườn hầm',
                    'price' => 20000.00,
                    'unit' => 'hộp',
                    'status' => 'active',
                    'description' => 'Mì ăn liền Omachi có sẵn nước sôi ăn tại sân.',
                ],
                [
                    'category_id' => '44444444-4444-4444-4444-444444444444',
                    'name' => 'Xúc xích ăn liền Ponnie',
                    'price' => 10000.00,
                    'unit' => 'cái',
                    'status' => 'active',
                    'description' => 'Xúc xích dinh dưỡng Ponnie thịt heo.',
                ],
            ];

            foreach ($services as $srv) {
                VenueClusterService::create([
                    'id' => (string) Str::uuid(),
                    'venue_cluster_id' => $cluster->id,
                    'category_id' => $srv['category_id'],
                    'name' => $srv['name'],
                    'price' => $srv['price'],
                    'unit' => $srv['unit'],
                    'status' => $srv['status'],
                    'description' => $srv['description'],
                ]);
            }
        }
    }
}
