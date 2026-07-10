<?php

namespace Database\Seeders;

use App\Models\PartnerApplication;
use App\Models\PartnerContract;
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
        $ownerSun = User::query()->where('username', 'owner_sun')->first();
        $clusters = VenueCluster::query()->pluck('id', 'slug');

        if (! $owner || ! $ownerSun) {
            return;
        }

        $applications = [
            ['user' => $owner, 'venue_name' => 'Green Sport Ba Đình', 'business_name' => 'Hộ kinh doanh Green Sport Ba Đình', 'status' => 'completed', 'contract_code' => 'CONTRACT_GREEN_0001', 'reviewed_by' => $admin?->id, 'reviewed_at' => now()->subDays(12), 'approved_venue_cluster_id' => $clusters['green-sport-ba-dinh'] ?? null, 'status_reason' => 'Hồ sơ đã hoàn tất ký hợp đồng và được kích hoạt.'],
            ['user' => $ownerSun, 'venue_name' => 'Sun Sport Cầu Giấy', 'business_name' => 'Hộ kinh doanh Sun Sport Cầu Giấy', 'status' => 'submitted', 'contract_code' => null, 'reviewed_by' => null, 'reviewed_at' => null, 'approved_venue_cluster_id' => $clusters['sun-sport-cau-giay'] ?? null, 'status_reason' => 'Hồ sơ đang chờ SportGo tiếp nhận, cụm sân chưa được kích hoạt.'],
            ['user' => $owner, 'venue_name' => 'Victory Sport Hà Đông', 'business_name' => 'Hộ kinh doanh Victory Sport Hà Đông', 'status' => 'submitted', 'contract_code' => null, 'reviewed_by' => null, 'reviewed_at' => null, 'approved_venue_cluster_id' => $clusters['victory-sport-ha-dong'] ?? null, 'status_reason' => 'Hồ sơ tham chiếu cho cụm sân mẫu, chưa có hợp đồng active.'],
        ];

        foreach ($applications as $index => $item) {
            $applicant = $item['user'];
            $contract = $item['contract_code']
                ? PartnerContract::query()->where('contract_code', $item['contract_code'])->first()
                : null;

            $application = PartnerApplication::query()->updateOrCreate(
                [
                    'user_id' => $applicant->id,
                    'venue_name' => $item['venue_name'],
                ],
                [
                    'applicant_full_name' => $applicant->full_name,
                    'applicant_phone' => $applicant->phone,
                    'applicant_email' => $applicant->email,
                    'applicant_address' => 'Số ' . (10 + $index) . ' phố SportGo, Hà Nội',
                    'applicant_type' => $index % 2 === 0 ? 'household_business' : 'company',
                    'representative_name' => $applicant->full_name,
                    'representative_identity_type' => 'cccd',
                    'representative_identity_number' => '0010' . str_pad((string) ($index + 1), 8, '0', STR_PAD_LEFT),
                    'representative_identity_issued_date' => now()->subYears(3)->toDateString(),
                    'representative_identity_issued_place' => 'Cục Cảnh sát QLHC về TTXH',
                    'representative_position' => $index % 2 === 0 ? 'Chủ hộ kinh doanh' : 'Giám đốc',
                    'business_name' => $item['business_name'],
                    'business_code' => 'BUS' . str_pad((string) ($index + 1), 6, '0', STR_PAD_LEFT),
                    'tax_code' => '01099990' . str_pad((string) ($index + 1), 2, '0', STR_PAD_LEFT),
                    'business_license_number' => 'GPKD-SG-' . str_pad((string) ($index + 1), 3, '0', STR_PAD_LEFT),
                    'business_address' => 'Quận Cầu Giấy, Hà Nội',
                    'business_representative_name' => $applicant->full_name,
                    'business_representative_position' => $index % 2 === 0 ? 'Chủ hộ kinh doanh' : 'Người đại diện pháp luật',
                    'venue_address' => 'Khu thể thao ' . $item['venue_name'] . ', Hà Nội',
                    'venue_province' => 'Hà Nội',
                    'venue_district' => $index % 2 === 0 ? 'Cầu Giấy' : 'Thanh Xuân',
                    'venue_ward' => 'Dịch Vọng',
                    'venue_map_url' => 'https://maps.google.com/?q=' . urlencode($item['venue_name']),
                    'venue_latitude' => 21.0362360 + ($index / 1000),
                    'venue_longitude' => 105.7905830 + ($index / 1000),
                    'venue_phone' => '09020000' . str_pad((string) ($index + 1), 2, '0', STR_PAD_LEFT),
                    'venue_email' => 'venue' . ($index + 1) . '@sportgo.vn',
                    'venue_description' => 'Cụm sân thể thao đăng ký hợp tác với SportGo.',
                    'expected_opening_hours' => '06:00 - 22:00',
                    'parking_info' => 'Có khu vực gửi xe máy và ô tô.',
                    'amenities' => ['Bãi gửi xe', 'Đèn chiếu sáng', 'Khu chờ'],
                    'court_count_total' => 4 + $index,
                    'status' => $item['status'],
                    'reviewed_by' => $item['reviewed_by'],
                    'status_reason' => $item['status_reason'],
                    'approved_venue_cluster_id' => $item['approved_venue_cluster_id'],
                    'current_contract_id' => $contract?->id,
                    'submitted_at' => now()->subDays(15 - $index),
                    'reviewed_at' => $item['reviewed_at'],
                ],
            );

            $application->forceFill([
                'bank_name' => $index % 2 === 0 ? 'Vietcombank' : 'MB Bank',
                'bank_code' => $index % 2 === 0 ? 'VCB' : 'MB',
                'account_number' => '1903' . str_pad((string) ($index + 1), 8, '0', STR_PAD_LEFT),
                'account_holder_name' => mb_strtoupper($applicant->full_name, 'UTF-8'),
                'bank_branch' => 'Chi nhánh Hà Nội',
                'bank_verification_status' => $item['status'] === 'rejected' ? 'rejected' : ($item['status'] === 'submitted' ? 'pending' : 'verified'),
            ])->save();
        }
    }
}
