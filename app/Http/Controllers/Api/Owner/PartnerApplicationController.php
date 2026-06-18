<?php

namespace App\Http\Controllers\Api\Owner;

use App\Http\Controllers\Controller;
use App\Models\PartnerApplication;
use App\Models\VenueCluster;
use Illuminate\Http\Request;

class PartnerApplicationController extends Controller
{
    public function myApplications(Request $request)
    {
        $ownerId = $request->user()->id;
        $ownedClusterIds = VenueCluster::query()
            ->where('owner_id', $ownerId)
            ->pluck('id');

        $applications = PartnerApplication::with([
            'bankAccounts',
            'documents',
            'contracts.template',
            'contracts.generatedDocument',
            'contracts.terminations',
            'courts',
            'terminationRequests'
        ])
        ->where(function ($query) use ($ownedClusterIds, $ownerId) {
            if ($ownedClusterIds->isNotEmpty()) {
                $query->whereIn('approved_venue_cluster_id', $ownedClusterIds);
            }

            // Keep pending cluster expansion requests visible before cluster is approved/created.
            $query->orWhere(function ($pendingQuery) use ($ownerId) {
                $pendingQuery->where('user_id', $ownerId)
                    ->whereNull('approved_venue_cluster_id')
                    ->whereIn('status', ['pending', 'reviewing']);
            });
        })
        ->latest()
        ->get();

        return response()->json(['data' => $applications]);
    }
    
    /**
     * Get the authenticated owner's partner application along with related data.
     */
    public function myApplication(Request $request)
    {
        $ownerId = $request->user()->id;
        $ownedClusterIds = VenueCluster::query()
            ->where('owner_id', $ownerId)
            ->pluck('id');

        $application = PartnerApplication::with([
            'bankAccounts',
            'documents',
            'contracts.template',
            'contracts.generatedDocument',
            'contracts.terminations',
            'courts',
            'terminationRequests'
        ])
        ->where(function ($query) use ($ownedClusterIds, $ownerId) {
            if ($ownedClusterIds->isNotEmpty()) {
                $query->whereIn('approved_venue_cluster_id', $ownedClusterIds);
            }

            $query->orWhere(function ($pendingQuery) use ($ownerId) {
                $pendingQuery->where('user_id', $ownerId)
                    ->whereNull('approved_venue_cluster_id')
                    ->whereIn('status', ['pending', 'reviewing']);
            });
        })
        ->latest()
        ->first();

        if (!$application) {
            return response()->json(['message' => 'Bạn chưa có hồ sơ đăng ký nào.', 'data' => null], 404);
        }

        return response()->json(['data' => $application]);
    }

    public function storeNewCluster(Request $request)
    {
        $validated = $request->validate([
            'venue_name' => 'required|string|max:255',
            'venue_address' => 'required|string',
            'venue_province' => 'nullable|string|max:100',
            'venue_district' => 'nullable|string|max:100',
            'venue_ward' => 'nullable|string|max:100',
            'venue_map_url' => 'nullable|url|max:1000',
            'venue_latitude' => 'required|numeric',
            'venue_longitude' => 'required|numeric',
            'venue_phone' => 'nullable|string|max:30',
            'venue_email' => 'nullable|email|max:255',
            'venue_description' => 'nullable|string',
            'expected_opening_hours' => 'nullable|string|max:255',
            'parking_info' => 'nullable|string',
            'amenities' => 'nullable|array',
            'court_count_total' => 'required|integer|min:0',
        ]);

        $user = $request->user();

        // Get the latest approved application to copy business and legal info
        $baseApplication = PartnerApplication::where('user_id', $user->id)
            ->whereIn('status', ['approved', 'completed'])
            ->latest()
            ->first();

        if (!$baseApplication) {
            return response()->json([
                'message' => 'Bạn cần có một hồ sơ đăng ký hợp lệ đã được duyệt trước khi thêm cụm sân mới.'
            ], 400);
        }

        $newApplicationData = array_merge($validated, [
            'id' => \Illuminate\Support\Str::uuid()->toString(),
            'user_id' => $user->id,
            'type' => 'new_cluster',
            'status' => 'pending',
            
            // Copy info from previous application
            'applicant_full_name' => $baseApplication->applicant_full_name,
            'applicant_phone' => $baseApplication->applicant_phone,
            'applicant_email' => $baseApplication->applicant_email,
            'applicant_address' => $baseApplication->applicant_address,
            'applicant_type' => $baseApplication->applicant_type,
            
            'representative_name' => $baseApplication->representative_name,
            'representative_identity_type' => $baseApplication->representative_identity_type,
            'representative_identity_number' => $baseApplication->representative_identity_number,
            'representative_identity_issued_date' => $baseApplication->representative_identity_issued_date,
            'representative_identity_issued_place' => $baseApplication->representative_identity_issued_place,
            'representative_position' => $baseApplication->representative_position,
            
            'business_name' => $baseApplication->business_name,
            'tax_code' => $baseApplication->tax_code,
            'business_code' => $baseApplication->business_code,
            'business_license_number' => $baseApplication->business_license_number,
            'business_address' => $baseApplication->business_address,
            'business_representative_name' => $baseApplication->business_representative_name,
            'business_representative_position' => $baseApplication->business_representative_position,
        ]);

        $newApplication = PartnerApplication::create($newApplicationData);

        // Do not copy bank accounts because owner_bank_accounts has a unique constraint on (owner_id, bank_code, account_number).
        // The owner already has their bank accounts registered globally.

        return response()->json([
            'message' => 'Yêu cầu đăng ký cụm sân mới đã được gửi thành công.',
            'data' => $newApplication
        ], 201);
    }
}
