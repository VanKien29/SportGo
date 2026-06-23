<?php

namespace App\Http\Controllers\Api\Partner;

use App\Http\Controllers\Controller;
use App\Http\Middleware\EnsureAdminRole;
use App\Models\GeneratedDocument;
use App\Services\Partner\PartnerDocumentService;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class PartnerDocumentDownloadController extends Controller
{
    public function __construct(private readonly PartnerDocumentService $documents)
    {
    }

    public function __invoke(Request $request, string $id): StreamedResponse
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
        $path = $this->documents->downloadPath($document);
        $fileName = $this->downloadName($document);

        return response()->streamDownload(function () use ($path): void {
            echo \Illuminate\Support\Facades\Storage::disk('local')->get($path);
        }, $fileName, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        ]);
    }

    private function downloadName(GeneratedDocument $document): string
    {
        $base = match ($document->document_type) {
            'partner_contract' => 'HopDong',
            'mutual_liquidation_minutes', 'settlement_minutes' => 'BienBan',
            'unilateral_termination_notice' => 'CongVan',
            default => 'VanBan',
        };

        return $base . '_' . $document->document_code . '.docx';
    }
}
