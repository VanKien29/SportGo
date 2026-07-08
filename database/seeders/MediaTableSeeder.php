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
            ],
        );
    }

    private function seedPartnerApplicationDocuments(): void
    {
        if (! Schema::hasTable('partner_applications')) {
            return;
        }

        foreach (['Green Sport Ba Đình', 'Sun Sport Cầu Giấy'] as $venueName) {
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
                'application/pdf',
            );
        }
    }

    private function seedPlatformFeeProofs(): void
    {
        if (! Schema::hasTable('venue_platform_fee_ledgers')) {
            return;
        }

        $cluster = VenueCluster::query()->where('slug', 'green-sport-ba-dinh')->first();
        $ledger = $cluster
            ? VenuePlatformFeeLedger::query()
                ->where('venue_cluster_id', $cluster->id)
                ->where('period_start', '2026-04-01')
                ->first()
            : null;

        if (! $ledger) {
            return;
        }

        $media = $this->upsertMedia(
            VenuePlatformFeeLedger::class,
            $ledger->id,
            'platform_fee_payment_proof',
            'bien-lai-phi-green-202604.jpg',
            'platform-fees/'.$ledger->id.'/bien-lai-phi-green-202604.jpg',
            'image/jpeg',
        );

        if (Schema::hasColumn('venue_platform_fee_ledgers', 'payment_proof_media_id')) {
            $ledger->update(['payment_proof_media_id' => $media->id]);
        }
    }

    private function seedReportEvidence(): void
    {
        if (! Schema::hasTable('reports')) {
            return;
        }

        $report = Report::query()->where('reason', 'other')->first();

        if (! $report) {
            return;
        }

        $this->upsertMedia(
            Report::class,
            $report->id,
            'report_evidence',
            'anh-san-cho-xu-ly.jpg',
            'reports/'.$report->id.'/anh-san-cho-xu-ly.jpg',
            'image/jpeg',
        );
    }

    private function seedComplaintEvidence(): void
    {
        if (! Schema::hasTable('complaints')) {
            return;
        }

        $complaint = Complaint::query()
            ->where('content', ComplaintsTableSeeder::VENUE_COMPLAINT_CONTENT)
            ->first();

        if (! $complaint) {
            return;
        }

        $this->upsertMedia(
            Complaint::class,
            $complaint->id,
            'complaint_evidence',
            'anh-hoa-don-ho-tro.jpg',
            'complaints/'.$complaint->id.'/anh-hoa-don-ho-tro.jpg',
            'image/jpeg',
        );
    }
}
