<?php

namespace App\Http\Controllers\Api\Player;

use App\Http\Controllers\Controller;
use App\Models\PartnerApplication;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class PartnerApplicationController extends Controller
{
    public function store(Request $request)
    {
        $validated = $request->validate([
            'applicant_full_name' => 'required|string|max:255',
            'applicant_phone' => 'required|string|max:30',
            'applicant_email' => 'required|email|max:255',
            'applicant_address' => 'required|string',
            'applicant_type' => 'required|in:individual,business',
            
            'representative_name' => 'nullable|string|max:255',
            'representative_identity_type' => 'nullable|string|max:50',
            'representative_identity_number' => 'nullable|string|max:50',
            'representative_identity_issued_date' => 'nullable|date',
            'representative_identity_issued_place' => 'nullable|string|max:255',
            'representative_position' => 'nullable|string|max:100',
            
            'business_name' => 'required|string|max:255',
            'tax_code' => 'nullable|string|max:50',
            'business_code' => 'nullable|string|max:50',
            'business_license_number' => 'nullable|string|max:50',
            'business_address' => 'nullable|string',
            'business_representative_name' => 'nullable|string|max:255',
            'business_representative_position' => 'nullable|string|max:100',
            
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
            
            'bank_accounts' => 'nullable|array',
            'bank_accounts.*.bank_name' => 'required_with:bank_accounts|string|max:100',
            'bank_accounts.*.bank_code' => 'required_with:bank_accounts|string|max:50',
            'bank_accounts.*.account_number' => 'required_with:bank_accounts|string|max:50',
            'bank_accounts.*.account_holder_name' => 'required_with:bank_accounts|string|max:150',
            'bank_accounts.*.branch_name' => 'nullable|string|max:150',

            'documents' => 'nullable|array',
            'documents.*' => 'file|mimes:pdf,jpg,jpeg,png|max:5120',
        ]);

        $user = $request->user();

        // Check if user already has a pending application
        $existingPending = PartnerApplication::where('user_id', $user->id)
            ->whereIn('status', ['pending', 'reviewing'])
            ->first();

        if ($existingPending) {
            return response()->json([
                'message' => 'Bạn đang có một hồ sơ chờ duyệt. Vui lòng chờ kết quả trước khi nộp hồ sơ mới.'
            ], 400);
        }

        $applicationData = array_merge($validated, [
            'id' => Str::uuid()->toString(),
            'user_id' => $user->id,
            'type' => 'new_partner',
            'status' => 'pending',
            'submitted_at' => now(),
        ]);
        
        unset($applicationData['bank_accounts']);

        $application = PartnerApplication::create($applicationData);

        if (!empty($validated['bank_accounts'])) {
            foreach ($validated['bank_accounts'] as $index => $bankAccount) {
                \App\Models\OwnerBankAccount::updateOrCreate(
                    [
                        'owner_id' => $user->id,
                        'bank_code' => $bankAccount['bank_code'],
                        'account_number' => $bankAccount['account_number'],
                    ],
                    [
                        'partner_application_id' => $application->id,
                        'bank_name' => $bankAccount['bank_name'],
                        'account_holder_name' => $bankAccount['account_holder_name'],
                        'branch_name' => $bankAccount['branch_name'] ?? null,
                        'is_default' => $index === 0,
                        'status' => 'pending',
                    ]
                );
            }
        }

        if ($request->hasFile('documents')) {
            foreach ($request->file('documents') as $index => $file) {
                $path = $file->store('partner-documents', 'public');
                $originalName = $file->getClientOriginalName();
                \App\Models\PartnerApplicationDocument::create([
                    'partner_application_id' => $application->id,
                    'document_type' => 'other',
                    'document_group' => 'other',
                    'title' => $originalName,
                    'file_path' => $path,
                    'status' => 'uploaded',
                    'sort_order' => $index + 1,
                ]);
            }
        }

        return response()->json([
            'message' => 'Hồ sơ đăng ký đối tác đã được gửi thành công. Vui lòng chờ ban quản trị xét duyệt.',
            'data' => $application
        ], 201);
    }
}
