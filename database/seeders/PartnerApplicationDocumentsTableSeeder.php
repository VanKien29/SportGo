<?php

namespace Database\Seeders;

use App\Models\PartnerApplication;
use App\Models\PartnerApplicationDocument;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Schema;

class PartnerApplicationDocumentsTableSeeder extends Seeder
{
    public function run(): void
    {
        if (! Schema::hasTable('partner_application_documents') || ! Schema::hasTable('partner_applications')) {
            return;
        }

        $admin = User::query()->where('username', 'admin')->first();

        $documents = [
            ['venue_images', 'venue_front_image', 'Ảnh mặt tiền sân', 'venue-front.jpg'],
            ['venue_images', 'court_area_image', 'Ảnh khu vực sân', 'court-area.jpg'],
            ['venue_images', 'parking_area_image', 'Ảnh khu vực gửi xe', 'parking.jpg'],
            ['identity_documents', 'identity_front', 'CCCD mặt trước', 'identity-front.jpg'],
            ['identity_documents', 'identity_back', 'CCCD mặt sau', 'identity-back.jpg'],
            ['business_documents', 'business_registration', 'Giấy đăng ký kinh doanh', 'business-registration.pdf'],
            ['land_documents', 'lease_contract', 'Hợp đồng thuê mặt bằng', 'lease-contract.pdf'],
            ['bank_documents', 'bank_account_proof', 'Chứng từ tài khoản ngân hàng', 'bank-proof.jpg'],
        ];

        PartnerApplication::query()->orderBy('created_at')->get()->each(function (PartnerApplication $application) use ($documents, $admin): void {
            foreach ($documents as $index => [$group, $type, $title, $fileName]) {
                $bankDocumentRejected = $application->status === 'rejected' && $group === 'bank_documents';

                PartnerApplicationDocument::query()->updateOrCreate(
                    [
                        'partner_application_id' => $application->id,
                        'document_type' => $type,
                        'file_path' => '/seed/partner-applications/' . $application->id . '/' . $fileName,
                    ],
                    [
                        'media_id' => null,
                        'document_group' => $group,
                        'title' => $title,
                        'description' => 'File seed dùng để kiểm tra hồ sơ đối tác cho ' . $application->venue_name,
                        'status' => $bankDocumentRejected ? 'rejected' : 'verified',
                        'reviewed_by' => $admin?->id,
                        'reviewed_at' => now()->subDays(3),
                        'reject_reason' => $bankDocumentRejected
                            ? 'Chứng từ tài khoản ngân hàng không khớp tên người đại diện.'
                            : null,
                        'sort_order' => $index + 1,
                    ],
                );
            }
        });
    }
}
