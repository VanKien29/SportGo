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
        $path = $this->downloadPath($document);
        abort_unless($path, 404);

        $fileName = $this->downloadName($document, $path);

        return response()->streamDownload(function () use ($path): void {
            echo Storage::disk('public')->get($path);
        }, $fileName, [
            'Content-Type' => Storage::disk('public')->mimeType($path) ?: 'application/octet-stream',
        ]);
    }

    private function downloadPath(PartnerApplicationDocument $document): ?string
    {
        $candidates = [
            $document->getRawOriginal('file_path'),
            $document->media?->getRawOriginal('file_path'),
        ];

        foreach ($candidates as $candidate) {
            if (is_string($candidate) && $candidate !== '' && Storage::disk('public')->exists($candidate)) {
                return $candidate;
            }
        }

        return null;
    }

    private function downloadName(PartnerApplicationDocument $document, string $path): string
    {
        $extension = pathinfo($path, PATHINFO_EXTENSION);
        $base = str($document->document_type ?: 'tai-lieu')
            ->slug()
            ->append('-', (string) $document->id)
            ->toString();

        return $extension ? $base . '.' . $extension : $base;
    }
}
