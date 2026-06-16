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

        // Check if file exists in either private or public disk
        $disk = null;
        if (Storage::disk('private')->exists($path)) {
            $disk = 'private';
        } elseif (Storage::disk('public')->exists($path)) {
            $disk = 'public';
        } else {
            return response()->json(['message' => 'File not found: ' . $path], 404);
        }

        // Stream the file
        return Storage::disk($disk)->response($path);
    }
}
