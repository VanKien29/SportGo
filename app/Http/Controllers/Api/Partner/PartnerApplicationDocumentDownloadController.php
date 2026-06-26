<?php

namespace App\Http\Controllers\Api\Partner;

use App\Http\Controllers\Controller;
use App\Models\PartnerApplicationDocument;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class PartnerApplicationDocumentDownloadController extends Controller
{
    public function __invoke(Request $request, string $documentId): StreamedResponse
    {
        $document = PartnerApplicationDocument::query()
            ->with('partnerApplication:id,user_id')
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
        abort_unless($document->file_path && Storage::disk('public')->exists($document->file_path), 404);

        $fileName = $this->downloadName($document);

        return response()->streamDownload(function () use ($document): void {
            echo Storage::disk('public')->get($document->file_path);
        }, $fileName, [
            'Content-Type' => Storage::disk('public')->mimeType($document->file_path) ?: 'application/octet-stream',
        ]);
    }

    private function downloadName(PartnerApplicationDocument $document): string
    {
        $extension = pathinfo((string) $document->file_path, PATHINFO_EXTENSION);
        $base = str($document->document_type ?: 'tai-lieu')
            ->slug()
            ->append('-', (string) $document->id)
            ->toString();

        return $extension ? $base . '.' . $extension : $base;
    }
}
