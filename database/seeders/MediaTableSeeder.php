<?php

namespace Database\Seeders;

use App\Models\Complaint;
use App\Models\Media;
use App\Models\PartnerApplication;
use App\Models\Report;
use App\Models\VenueCluster;
use App\Models\VenuePlatformFeeLedger;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Schema;

class MediaTableSeeder extends Seeder
{
    public function run(): void
    {
        if (! Schema::hasTable('media')) {
            return;
        }

        $this->seedPartnerApplicationDocuments();
        $this->seedPlatformFeeProofs();
        $this->seedReportEvidence();
        $this->seedComplaintEvidence();
    }

    private function upsertMedia(string $type, string $id, string $collection, string $fileName, string $filePath, string $mimeType, int $sortOrder = 0): Media
    {
        return Media::query()->updateOrCreate(
            [
                'mediable_type' => $type,
                'mediable_id' => $id,
                'collection' => $collection,
                'file_name' => $fileName,
            ],
            [
                'file_path' => $filePath,
                'mime_type' => $mimeType,
                'file_size' => 256000,
                'sort_order' => $sortOrder,
            ]
        );
    }

    private function seedPartnerApplicationDocuments(): void
    {
        if (! Schema::hasTable('partner_applications')) {
            return;
        }

        foreach (['SportGo Cầu Giấy', 'SportGo Thanh Xuân'] as $venueName) {
            $application = PartnerApplication::query()->where('venue_name', $venueName)->first();

            if (! $application) {
                continue;
            }

            $this->upsertMedia(
                PartnerApplication::class,
                $application->id,
                'partner_application_documents',
                'giay-phep-kinh-doanh.pdf',
                'partner-applications/'.$application->id.'/giay-phep-kinh-doanh.pdf',
                'application/pdf'
            );
        }
    }

    private function seedPlatformFeeProofs(): void
    {
        if (! Schema::hasTable('venue_platform_fee_ledgers')) {
            return;
        }

        $clusters = VenueCluster::query()->whereIn('slug', ['sportgo-cau-giay', 'sportgo-my-dinh'])->get()->keyBy('slug');
        $targets = [
            ['sportgo-cau-giay', '2026-04-01', 'bien-lai-phi-thang-04.jpg'],
            ['sportgo-my-dinh', '2026-02-01', 'bang-chung-phi-bi-tu-choi.jpg'],
        ];

        foreach ($targets as [$slug, $periodStart, $fileName]) {
            $cluster = $clusters[$slug] ?? null;
            $ledger = $cluster
                ? VenuePlatformFeeLedger::query()
                    ->where('venue_cluster_id', $cluster->id)
                    ->where('period_start', $periodStart)
                    ->first()
                : null;

            if (! $ledger) {
                continue;
            }

            $media = $this->upsertMedia(
                VenuePlatformFeeLedger::class,
                $ledger->id,
                'platform_fee_payment_proof',
                $fileName,
                'platform-fees/'.$ledger->id.'/'.$fileName,
                'image/jpeg'
            );

            if (Schema::hasColumn('venue_platform_fee_ledgers', 'payment_proof_media_id')) {
                $ledger->update(['payment_proof_media_id' => $media->id]);
            }
        }
    }

    private function seedReportEvidence(): void
    {
        if (! Schema::hasTable('reports')) {
            return;
        }

        $report = Report::query()->where('reason', 'spam')->first();

        if (! $report) {
            return;
        }

        $this->upsertMedia(
            Report::class,
            $report->id,
            'report_evidence',
            'anh-chup-report-spam.jpg',
            'reports/'.$report->id.'/anh-chup-report-spam.jpg',
            'image/jpeg'
        );
    }

    private function seedComplaintEvidence(): void
    {
        if (! Schema::hasTable('complaints')) {
            return;
        }

        $complaint = Complaint::query()->where('content', 'Khách phản ánh sân mở cửa trễ 10 phút so với giờ đặt.')->first();

        if (! $complaint) {
            return;
        }

        $this->upsertMedia(
            Complaint::class,
            $complaint->id,
            'complaint_evidence',
            'anh-chup-tai-san.jpg',
            'complaints/'.$complaint->id.'/anh-chup-tai-san.jpg',
            'image/jpeg'
        );
    }
}
