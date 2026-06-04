<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\PartnerApplication;
use App\Models\CourtType;
use App\Models\VenueCluster;
use App\Models\Role;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class PartnerApplicationController extends Controller
{
    /**
     * Danh sách đơn đăng kí
     */
    public function index(Request $request)
    {
        $query = PartnerApplication::with(['user', 'reviewedBy']);

        // Filter theo trạng thái
        if ($request->has('status') && $request->status) {
            $query->where('status', $request->status);
        }

        // Tìm kiếm theo tên sân
        if ($request->has('search') && $request->search) {
            $query->where('venue_name', 'like', '%' . $request->search . '%');
        }

        $applications = $query->orderByDesc('submitted_at')->paginate(15);
        $statuses = [
            'pending' => 'Chờ duyệt',
            'approved' => 'Đã duyệt',
            'rejected' => 'Từ chối',
        ];

        return view('admin.partner-applications.index', compact('applications', 'statuses'));
    }

    /**
     * Chi tiết đơn đăng kí
     */
    public function show(PartnerApplication $application)
    {
        $application->load(['user', 'reviewedBy']);
        $courtTypes = CourtType::all();

        return view('admin.partner-applications.show', compact('application', 'courtTypes'));
    }

    /**
     * Form duyệt đơn
     */
    public function approve_form(PartnerApplication $application)
    {
        $courtTypes = CourtType::all();
        return view('admin.partner-applications.approve', compact('application', 'courtTypes'));
    }

    /**
     * Xử lý duyệt đơn
     */
    public function approve(Request $request, PartnerApplication $application)
    {
        if ($application->status !== 'pending') {
            return back()->with('error', 'Đơn này đã được xử lý, không thể duyệt lại.');
        }

        $validated = $request->validate([
            'initial_court_name' => 'required|string|max:255',
            'court_type_id' => 'required|exists:court_types,id',
            'bank_account_name' => 'required|string|max:255',
            'bank_account_number' => 'required|string|max:30',
            'bank_name' => 'required|string|max:255',
        ]);

        try {
            DB::beginTransaction();

            // 1. Tạo cụm sân
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
                'court_type_id' => $validated['court_type_id'],
                'name' => $validated['initial_court_name'],
                'status' => 'active',
                'sort_order' => 1,
            ]);

            // 3. Gán role chủ sân cho user
            $venueOwnerRole = Role::where('name', 'venue_owner')->first();
            if ($venueOwnerRole) {
                $application->user->roles()->syncWithoutDetaching([$venueOwnerRole->id]);
            }

            // 4. Lưu tài khoản ngân hàng (nếu bảng tồn tại)
            if (class_exists(\App\Models\OwnerBankAccount::class)) {
                \App\Models\OwnerBankAccount::create([
                    'owner_id' => $application->user_id,
                    'partner_application_id' => $application->id,
                    'bank_name' => $validated['bank_name'],
                    'bank_code' => 'N/A',
                    'account_number' => $validated['bank_account_number'],
                    'account_holder_name' => $validated['bank_account_name'],
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

            return redirect()->route('admin.partner-applications.index')
                ->with('success', 'Duyệt đơn đăng kí thành công!');
        } catch (\Exception $e) {
            DB::rollBack();
            return back()->with('error', 'Lỗi khi duyệt: ' . $e->getMessage());
        }
    }

    /**
     * Form từ chối đơn
     */
    public function reject_form(PartnerApplication $application)
    {
        return view('admin.partner-applications.reject', compact('application'));
    }

    /**
     * Xử lý từ chối đơn
     */
    public function reject(Request $request, PartnerApplication $application)
    {
        if ($application->status !== 'pending') {
            return back()->with('error', 'Đơn này đã được xử lý, không thể từ chối lại.');
        }

        $validated = $request->validate([
            'reason' => 'required|string|max:500',
        ]);

        $application->update([
            'status' => 'rejected',
            'reviewed_by' => Auth::id(),
            'status_reason' => $validated['reason'],
            'reviewed_at' => now(),
        ]);

        return redirect()->route('admin.partner-applications.index')
            ->with('success', 'Từ chối đơn đăng kí thành công!');
    }
}
