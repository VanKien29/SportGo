<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

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

        // Handle paths that already start with public/
        if (str_starts_with($path, 'public/')) {
            $path = substr($path, 7);
            if (Storage::disk('public')->exists($path)) {
                $disk = 'public';
            }
        } else {
            // Check if file exists in either private (local) or public disk
            if (Storage::disk('local')->exists($path)) {
                $disk = 'local';
            } elseif (Storage::disk('public')->exists($path)) {
                $disk = 'public';
            }
        }

        if (!$disk) {
            return response()->json(['message' => 'File not found: ' . $path], 404);
        }

        // Stream the file
        return Storage::disk($disk)->response($path);
    }
}
