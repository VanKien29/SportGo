<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\PartnerApplication;
use App\Models\User;
use App\Models\VenueCluster;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class PartnerApplicationController extends Controller
{
    /**
     * Hiển thị danh sách các đơn đăng kí làm chủ sân.
     * Hỗ trợ filter theo trạng thái, người gửi, tên cơ sở, ngày gửi
     */
    public function index(Request $request)
    {
        $query = PartnerApplication::with(['user', 'reviewedBy']);

        // Filter theo trạng thái
        if ($request->has('status') && $request->status) {
            $query->where('status', $request->status);
        }

        // Filter theo người gửi
        if ($request->has('user_id') && $request->user_id) {
            $query->where('user_id', $request->user_id);
        }

        // Filter theo tên cơ sở
        if ($request->has('venue_name') && $request->venue_name) {
            $query->where('venue_name', 'like', '%' . $request->venue_name . '%');
        }

        // Filter theo ngày gửi
        if ($request->has('submitted_from') && $request->submitted_from) {
            $query->whereDate('submitted_at', '>=', $request->submitted_from);
        }

        if ($request->has('submitted_to') && $request->submitted_to) {
            $query->whereDate('submitted_at', '<=', $request->submitted_to);
        }

        $applications = $query->orderByDesc('submitted_at')->paginate(15);

        return response()->json([
            'status' => 'success',
            'data' => $applications
        ]);
    }

    /**
     * Hiển thị chi tiết đơn đăng kí
     * Bao gồm: thông tin kinh doanh, địa chỉ/map, sân con ban đầu, giấy tờ, tài khoản nhân tiền
     */
    public function show($id)
    {
        $application = PartnerApplication::with(['user', 'reviewedBy'])->findOrFail($id);

        // Thêm thông tin liên quan
        $relatedData = [
            'user_info' => $application->user,
            'business_info' => [
                'business_name' => $application->business_name,
                'tax_code' => $application->tax_code,
                'venue_name' => $application->venue_name,
            ],
            'venue_info' => [
                'address' => $application->venue_address,
                'map_url' => $application->venue_map_url,
                'latitude' => $application->venue_latitude,
                'longitude' => $application->venue_longitude,
            ],
            'review_info' => [
                'status' => $application->status,
                'reviewed_by' => $application->reviewedBy,
                'status_reason' => $application->status_reason,
                'reviewed_at' => $application->reviewed_at,
            ]
        ];

        return response()->json([
            'status' => 'success',
            'data' => array_merge($application->toArray(), $relatedData)
        ]);
    }

    /**
     * Duyệt đơn đăng kí
     * - Tạo cụm sân mới
     * - Tạo sân con ban đầu
     * - Gán role chủ sân cho user
     * - Lưu tài khoản nhân tiền
     */
    public function approve(Request $request, $id)
    {
        $application = PartnerApplication::findOrFail($id);

        if ($application->status !== 'pending') {
            return response()->json([
                'status' => 'error',
                'message' => 'Đơn này đã được xử lý, không thể duyệt lại.',
            ], 422);
        }

        // Validate dữ liệu
        $validator = Validator::make($request->all(), [
            'initial_court_name' => 'required|string|max:255',
            'court_type_id' => 'required|exists:court_types,id',
            'bank_account_name' => 'required|string|max:255',
            'bank_account_number' => 'required|string|max:30',
            'bank_name' => 'required|string|max:255',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => $validator->errors()->first(),
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            DB::beginTransaction();

            // 1. Tạo cụm sân (Venue Cluster)
            $venueCluster = VenueCluster::create([
                'name' => $application->venue_name,
                'slug' => Str::slug($application->venue_name . '-' . time()),
                'address' => $application->venue_address,
                'map_url' => $application->venue_map_url,
                'latitude' => $application->venue_latitude,
                'longitude' => $application->venue_longitude,
                'owner_id' => $application->user_id,
            ]);

            // 2. Tạo sân con ban đầu
            \App\Models\VenueCourt::create([
                'venue_cluster_id' => $venueCluster->id,
                'court_type_id' => $request->court_type_id,
                'name' => $request->initial_court_name,
                'status' => 'active',
                'sort_order' => 1,
            ]);

            // 3. Gán role chủ sân cho user
            $user = User::findOrFail($application->user_id);
            $venueOwnerRole = \App\Models\Role::where('name', 'venue_owner')->first();
            if ($venueOwnerRole) {
                $user->roles()->syncWithoutDetaching([$venueOwnerRole->id]);
            }

            // 4. Lưu tài khoản nhân tiền (nếu bảng tồn tại)
            if (class_exists(\App\Models\OwnerBankAccount::class)) {
                \App\Models\OwnerBankAccount::create([
                    'owner_id' => $application->user_id,
                    'partner_application_id' => $application->id,
                    'bank_name' => $request->bank_name,
                    'bank_code' => 'N/A', // or blank
                    'account_number' => $request->bank_account_number,
                    'account_holder_name' => $request->bank_account_name,
                    'status' => 'verified',
                    'is_default' => true,
                ]);
            }

            // 5. Cập nhật trạng thái đơn
            $application->update([
                'status' => 'approved',
                'reviewed_by' => Auth::id(),
                'reviewed_at' => now(),
                'approved_venue_cluster_id' => $venueCluster->id,
            ]);

            DB::commit();

            return response()->json([
                'status' => 'success',
                'message' => 'Duyệt đơn đăng kí thành công.',
                'data' => $application
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'status' => 'error',
                'message' => 'Lỗi khi duyệt đơn: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Từ chối đơn đăng kí
     * Bắt buộc phải nhập lý do từ chối
     */
    public function reject(Request $request, $id)
    {
        $application = PartnerApplication::findOrFail($id);

        if ($application->status !== 'pending') {
            return response()->json([
                'status' => 'error',
                'message' => 'Đơn này đã được xử lý, không thể từ chối lại.',
            ], 422);
        }

        $validator = Validator::make($request->all(), [
            'reason' => 'required|string|max:500',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => $validator->errors()->first(),
                'errors' => $validator->errors(),
            ], 422);
        }

        $application->update([
            'status' => 'rejected',
            'reviewed_by' => Auth::id(),
            'status_reason' => $request->reason,
            'reviewed_at' => now(),
        ]);

        return response()->json([
            'status' => 'success',
            'message' => 'Từ chối đơn đăng kí thành công.',
            'data' => $application
        ]);
    }
}
