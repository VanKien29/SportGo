<?php

namespace App\Http\Controllers\Api\Player;

use App\Http\Controllers\Controller;
use App\Models\Complaint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Intervention\Image\ImageManager;
use Intervention\Image\Drivers\Gd\Driver;

class ComplaintController extends Controller
{
    public function store(Request $request)
    {
        $request->validate([
            'complaint_type' => ['required', 'string', 'in:system,venue'],
            'venue_cluster_id' => ['required_if:complaint_type,venue', 'nullable', 'exists:venue_clusters,id'],
            'booking_id' => ['nullable', 'string', 'exists:bookings,id'],
            'content' => ['required', 'string', 'max:2000'],
            'evidence_image' => ['nullable', 'image', 'max:5120'], // max 5MB
        ]);

        $complaint = Complaint::create([
            'complaint_type' => $request->complaint_type,
            'customer_id' => $request->user()->id,
            'venue_cluster_id' => $request->complaint_type === 'venue' ? $request->venue_cluster_id : null,
            'booking_id' => $request->booking_id,
            'content' => $request->content,
            'status' => 'pending',
        ]);

        if ($request->hasFile('evidence_image')) {
            $thumbnail = $request->file('evidence_image');
            $manager = ImageManager::usingDriver(new Driver());
            $image = $manager->decodePath($thumbnail->getPathname());
            
            $filename = uniqid('complaint_', true) . '.webp';
            $path = 'complaints/' . $filename;
            
            if (!Storage::disk('public')->exists('complaints')) {
                Storage::disk('public')->makeDirectory('complaints');
            }
            
            $image->save(storage_path('app/public/' . $path), 80);

            $complaint->evidence()->create([
                'collection' => 'evidence_image',
                'file_name' => $thumbnail->getClientOriginalName() . '.webp',
                'file_path' => $path,
                'mime_type' => 'image/webp',
                'file_size' => filesize(storage_path('app/public/' . $path)),
            ]);
        }

        return response()->json([
            'message' => 'Khiếu nại của bạn đã được gửi thành công.',
            'data' => $complaint->load('evidence')
        ], 201);
    }
}
