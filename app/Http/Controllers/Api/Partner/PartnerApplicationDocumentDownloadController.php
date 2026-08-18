<?php

namespace App\Http\Controllers\Api\Partner;

use App\Http\Controllers\Controller;
use App\Models\DocumentAccessLog;
use App\Models\PartnerApplicationDocument;
use App\Services\Partner\DocumentPdfService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class PartnerApplicationDocumentDownloadController extends Controller
{
    public function __construct(private readonly DocumentPdfService $pdfs)
    {
    }

    public function __invoke(Request $request, string $documentId): BinaryFileResponse
    {
        $document = PartnerApplicationDocument::query()
            ->with(['partnerApplication:id,user_id', 'media'])
            ->findOrFail($documentId);

        $user = $request->user();
        abort_unless($user, 401);

        $roles = $user->roles()->pluck('roles.name')->all();
        $isAdmin = (bool) array_intersect($roles, [
            'super_admin',
            'admin',
            'system_staff',
            'partner_manager',
        ]);

        abort_unless($isAdmin || $document->partnerApplication?->user_id === $user->id, 403);
        [$disk, $path] = $this->sourcePath($document);
        abort_unless($disk && $path, 404);

        $pdfPath = $document->getRawOriginal('pdf_file_path');
        if (! $pdfPath || ! Storage::disk('local')->exists($pdfPath)) {
            $pdfPath = 'partner-application-pdfs/' . $document->id . '.pdf';
            $this->pdfs->convertSource(
                Storage::disk($disk)->path($path),
                Storage::disk('local')->path($pdfPath),
                Storage::disk($disk)->mimeType($path) ?: $document->media?->mime_type ?: 'application/octet-stream',
                'TÀI LIỆU HỒ SƠ KIỂM SOÁT | ' . ($document->title ?: 'Tài liệu') . ' | Mã ' . $document->id
            );

            $document->forceFill([
                'pdf_file_path' => $pdfPath,
                'pdf_hash' => hash_file('sha256', Storage::disk('local')->path($pdfPath)),
                'pdf_generated_at' => now(),
            ])->save();
        }

        $fileName = $this->downloadName($document);
        $absolutePath = Storage::disk('local')->path($pdfPath);
        $mode = (string) $request->query('mode', 'download');
        $isView = $mode === 'view';

        DocumentAccessLog::query()->create([
            'partner_application_document_id' => $document->id,
            'user_id' => $user->id,
            'action' => $isView ? 'view' : ($mode === 'export' ? 'export' : 'download'),
            'delivery' => 'pdf',
            'file_hash' => $document->getRawOriginal('pdf_hash'),
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'metadata' => [
                'document_type' => $document->document_type,
                'application_id' => $document->partner_application_id,
            ],
        ]);

        $headers = [
            'Content-Type' => 'application/pdf',
            'Cache-Control' => 'private, no-store, max-age=0',
            'Pragma' => 'no-cache',
            'X-Content-Type-Options' => 'nosniff',
            'Content-Disposition' => ($isView ? 'inline' : 'attachment') . '; filename="' . $fileName . '"',
        ];

        return $isView
            ? response()->file($absolutePath, $headers)
            : response()->download($absolutePath, $fileName, $headers);
    }

    /** @return array{0: string|null, 1: string|null} */
    private function sourcePath(PartnerApplicationDocument $document): array
    {
        $candidates = [
            $document->getRawOriginal('file_path'),
            $document->media?->getRawOriginal('file_path'),
        ];

        foreach ($candidates as $candidate) {
            if (! is_string($candidate) || $candidate === '') {
                continue;
            }
            foreach (['local', 'public'] as $disk) {
                if (Storage::disk($disk)->exists($candidate)) {
                    return [$disk, $candidate];
                }
            }
        }

        return [null, null];
    }

    private function downloadName(PartnerApplicationDocument $document): string
    {
        $base = str($document->document_type ?: 'tai-lieu')
            ->slug()
            ->append('-', (string) $document->id)
            ->toString();

        return $base . '.pdf';
    }
}
