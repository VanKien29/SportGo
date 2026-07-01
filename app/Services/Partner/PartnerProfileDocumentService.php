<?php

namespace App\Services\Partner;

use App\Models\Media;
use App\Models\PartnerApplication;
use App\Models\PartnerApplicationDocument;
use App\Models\VenueCluster;
use Illuminate\Http\UploadedFile;

class PartnerProfileDocumentService
{
    /**
     * @param array<int, UploadedFile> $files
     * @return array<int, array<string, mixed>>
     */
    public function attachVenueRequestDocuments(
        VenueCluster $cluster,
        array $files,
        string $requestId,
        string $documentType,
        string $documentGroup,
        string $title,
        string $description
    ): array {
        if ($files === []) {
            return [];
        }

        $application = PartnerApplication::query()
            ->where('approved_venue_cluster_id', $cluster->id)
            ->latest('reviewed_at')
            ->latest('created_at')
            ->first();

        if (! $application) {
            return $this->storeRequestOnly($cluster, $files, $requestId, $documentGroup);
        }

        $stored = [];
        foreach ($files as $index => $file) {
            if (! $file instanceof UploadedFile) {
                continue;
            }

            $path = $file->store('partner-applications/' . $application->id . '/' . $documentGroup . '/' . $requestId, 'public');
            $media = Media::query()->create([
                'mediable_type' => PartnerApplication::class,
                'mediable_id' => $application->id,
                'collection' => 'partner_application_' . $documentGroup,
                'file_name' => $file->getClientOriginalName(),
                'file_path' => $path,
                'mime_type' => $file->getMimeType() ?: $file->getClientMimeType(),
                'file_size' => $file->getSize(),
                'sort_order' => $index + 1,
            ]);

            $document = PartnerApplicationDocument::query()->create([
                'partner_application_id' => $application->id,
                'media_id' => $media->id,
                'document_type' => $documentType,
                'document_group' => $documentGroup,
                'title' => $title,
                'description' => $description,
                'file_path' => $path,
                'status' => 'uploaded',
                'sort_order' => $index + 1,
            ]);

            $stored[] = $this->documentPayload($document, $file);
        }

        return $stored;
    }

    /**
     * @param array<int, UploadedFile> $files
     * @return array<int, array<string, mixed>>
     */
    private function storeRequestOnly(VenueCluster $cluster, array $files, string $requestId, string $documentGroup): array
    {
        $stored = [];
        foreach ($files as $index => $file) {
            if (! $file instanceof UploadedFile) {
                continue;
            }

            $path = $file->store('venue-change-requests/' . $cluster->id . '/' . $documentGroup . '/' . $requestId, 'public');
            $stored[] = [
                'id' => null,
                'file_name' => $file->getClientOriginalName(),
                'file_path' => $path,
                'download_url' => asset('storage/' . $path),
                'mime_type' => $file->getMimeType() ?: $file->getClientMimeType(),
                'file_size' => $file->getSize(),
                'sort_order' => $index + 1,
            ];
        }

        return $stored;
    }

    private function documentPayload(PartnerApplicationDocument $document, UploadedFile $file): array
    {
        return [
            'id' => $document->id,
            'file_name' => $file->getClientOriginalName(),
            'file_path' => $document->file_path,
            'download_url' => $document->download_url,
            'mime_type' => $file->getMimeType() ?: $file->getClientMimeType(),
            'file_size' => $file->getSize(),
            'sort_order' => $document->sort_order,
        ];
    }
}
