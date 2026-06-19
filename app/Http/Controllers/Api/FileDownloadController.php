<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class FileDownloadController extends Controller
{
    /**
     * Download or view a file from storage securely
     */
    public function download(Request $request)
    {
        $path = $request->query('path');

        if (!$path) {
            return response()->json(['message' => 'Path is required'], 400);
        }

        // Normalize path by stripping common prefixes (including duplicated prefixes like /storage//storage/...)
        $path = str_replace('\\', '/', (string) $path);
        $path = preg_replace('#/+#', '/', $path);
        $path = ltrim($path, '/');
        while (
            str_starts_with($path, 'storage/') ||
            str_starts_with($path, 'public/')
        ) {
            if (str_starts_with($path, 'storage/')) {
                $path = substr($path, 8);
                continue;
            }
            if (str_starts_with($path, 'public/')) {
                $path = substr($path, 7);
            }
            $path = ltrim($path, '/');
        }

        // Find file in either private (local) or public disk
        [$disk, $resolvedPath] = $this->resolveFileLocation($path);

        // If current stored "pdf" is corrupted placeholder, fallback to seeded DOCX contract template
        if ($disk && $this->isBrokenPdf($disk, $resolvedPath)) {
            [$fallbackDisk, $fallbackPath] = $this->resolveFileLocation('templates/contract_template.docx');
            if (! $fallbackDisk) {
                [$fallbackDisk, $fallbackPath] = $this->resolveFileLocation('document-templates/partner_contract_v1.docx');
            }
            if ($fallbackDisk) {
                $disk = $fallbackDisk;
                $resolvedPath = $fallbackPath;
            }
        }

        if (!$disk) {
            return response()->json(['message' => 'File not found: ' . $path], 404);
        }

        // Stream/download the file with proper exposed headers
        return Storage::disk($disk)->response($resolvedPath, null, [
            'Access-Control-Expose-Headers' => 'Content-Disposition'
        ]);
    }

    private function resolveFileLocation(string $path): array
    {
        if (Storage::disk('local')->exists($path)) {
            return ['local', $path];
        }
        if (Storage::disk('public')->exists($path)) {
            return ['public', $path];
        }
        return [null, null];
    }

    private function isBrokenPdf(string $disk, string $path): bool
    {
        if (! str_ends_with(strtolower($path), '.pdf')) {
            return false;
        }

        $stream = Storage::disk($disk)->readStream($path);
        if (! is_resource($stream)) {
            return true;
        }

        $prefix = fread($stream, 5);
        fclose($stream);

        return $prefix !== '%PDF-';
    }
}
