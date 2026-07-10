<?php

namespace Database\Seeders;

use App\Models\ServiceCategory;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Schema;

class ServiceCategoriesTableSeeder extends Seeder
{
    public function run(): void
    {
        if (! Schema::hasTable('service_categories')) {
            return;
        }

        $categories = [
            [
                'id' => '11111111-1111-1111-1111-111111111111',
                'name' => 'Nước uống',
                'status' => 'active',
                'description' => 'Các loại nước ngọt, nước suối và nước tăng lực giải khát tại sân.',
            ],
            [
                'id' => '22222222-2222-2222-2222-222222222222',
                'name' => 'Cho thuê vợt',
                'status' => 'active',
                'description' => 'Dịch vụ thuê các dòng vợt tập luyện hoặc thi đấu chuyên nghiệp.',
            ],
            [
                'id' => '33333333-3333-3333-3333-333333333333',
                'name' => 'Bán cầu / Bóng',
                'status' => 'active',
                'description' => 'Bán cầu lông, bóng tennis, bóng đá chính hãng.',
            ],
            [
                'id' => '44444444-4444-4444-4444-444444444444',
                'name' => 'Đồ ăn nhẹ',
                'status' => 'active',
                'description' => 'Các loại bánh mì, mì gói, xúc xích và thức ăn nhanh tại quầy.',
            ],
            [
                'id' => '55555555-5555-5555-5555-555555555555',
                'name' => 'Dịch vụ khác',
                'status' => 'active',
                'description' => 'Các dịch vụ cọc giày, gửi xe hoặc dịch vụ đặc thù khác.',
            ],
        ];

        foreach ($categories as $cat) {
            ServiceCategory::query()->updateOrCreate(
                ['id' => $cat['id']],
                [
                    'name' => $cat['name'],
                    'status' => $cat['status'],
                    'description' => $cat['description'],
                ]
            );
        }
    }
}
