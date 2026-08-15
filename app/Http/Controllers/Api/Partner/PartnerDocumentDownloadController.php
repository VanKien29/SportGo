<?php

namespace App\Http\Controllers\Api\Partner;

use App\Http\Controllers\Controller;
use App\Models\DocumentAccessLog;
use App\Models\GeneratedDocument;
use App\Services\Partner\PartnerDocumentService;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class PartnerDocumentDownloadController extends Controller
{
    public function __construct(private readonly PartnerDocumentService $documents)
    {
    }

    public function __invoke(Request $request, string $id): BinaryFileResponse
    {
        $document = GeneratedDocument::findOrFail($id);
        $roles = $request->user()?->roles()->pluck('roles.name')->all() ?? [];
        $isAdmin = (bool) array_intersect($roles, [
            'super_admin',
            'admin',
            'system_staff',
            'partner_manager',
            'finance_operator',
        ]);

        $this->documents->assertCanDownload($document, $request->user(), $isAdmin);
        $path = $this->documents->pdfDownloadPath($document);
        $fileName = $this->documents->pdfDownloadName($document);
        $absolutePath = \Illuminate\Support\Facades\Storage::disk('local')->path($path);
        $mode = (string) $request->query('mode', 'download');
        $isView = $mode === 'view';

        DocumentAccessLog::query()->create([
            'generated_document_id' => $document->id,
            'user_id' => $request->user()?->id,
            'action' => $isView ? 'view' : ($mode === 'export' ? 'export' : 'download'),
            'delivery' => 'pdf',
            'file_hash' => $document->final_pdf_hash ?: $document->pdf_hash,
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'metadata' => [
                'document_status' => $document->status,
                'document_version' => $document->document_version,
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
}
