<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\CommunityPost;
use App\Models\PartnerApplication;
use App\Models\Refund;
use App\Models\Report;
use App\Models\VenueCourtApprovalRequest;
use App\Models\VenueInformationChangeRequest;
use App\Models\VenueLocationChangeRequest;
use App\Models\OwnerWithdrawalRequest;
use App\Models\UserWithdrawalRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdminPendingCountsController extends Controller
{
    /**
     * Trả về số lượng công việc đang chờ xử lý theo nhóm nav.
     * Dùng cho badge notification trên sidebar admin.
     */
    public function index(Request $request): JsonResponse
    {
        // Hồ sơ đối tác chờ duyệt
        $partnerApplications = PartnerApplication::query()
            ->whereIn('status', ['submitted', 'under_review', 'pending_contract'])
            ->count();

        // Yêu cầu thay đổi quy mô sân chờ duyệt
        $scaleApprovals = VenueCourtApprovalRequest::query()
            ->where('status', 'pending')
            ->count();

        // Yêu cầu thay đổi vị trí sân chờ duyệt
        $locationChanges = VenueLocationChangeRequest::query()
            ->where('status', 'pending')
            ->count();

        // Yêu cầu thay đổi thông tin sân chờ duyệt
        $infoChanges = VenueInformationChangeRequest::query()
            ->where('status', 'pending')
            ->count();

        // Tổng venue cluster pending
        $venueClusters = $scaleApprovals + $locationChanges + $infoChanges;

        // Hoàn tiền cần xác nhận
        $refunds = Refund::query()
            ->whereIn('status', ['pending_confirmation', 'pending_owner_confirmation'])
            ->count();

        // Rút tiền cần xử lý
        $ownerWithdrawals = OwnerWithdrawalRequest::query()
            ->whereIn('status', ['pending', 'approved'])
            ->count();

        $userWithdrawals = UserWithdrawalRequest::query()
            ->whereIn('status', ['pending', 'approved'])
            ->count();
            
        $withdrawals = $ownerWithdrawals + $userWithdrawals;

        // Tổng tài chính
        $finance = $refunds + $withdrawals;

        // Báo cáo chờ xử lý
        $reports = Report::query()
            ->where('status', 'pending')
            ->count();

        // Bài viết cộng đồng chờ kiểm duyệt
        $moderation = CommunityPost::query()
            ->where('status', 'pending_review')
            ->count();

        // Tổng kiểm duyệt & hỗ trợ
        $moderationSupport = $reports + $moderation;

        return response()->json([
            'data' => [
                'partner_applications' => $partnerApplications,
                'venue_clusters'       => $venueClusters,
                'finance'              => $finance,
                'moderation_support'   => $moderationSupport,
                // Chi tiết nếu cần
                'detail' => [
                    'scale_approvals'   => $scaleApprovals,
                    'location_changes'  => $locationChanges,
                    'info_changes'      => $infoChanges,
                    'refunds'           => $refunds,
                    'withdrawals'       => $withdrawals,
                    'reports'           => $reports,
                    'moderation_posts'  => $moderation,
                ],
            ],
        ]);
    }
}
