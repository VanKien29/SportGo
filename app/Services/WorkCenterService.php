<?php

namespace App\Services;

use App\Models\Complaint;
use App\Models\GeneratedDocument;
use App\Models\OwnerWithdrawalRequest;
use App\Models\PartnerApplication;
use App\Models\PartnerTerminationRequest;
use App\Models\Refund;
use App\Models\Report;
use App\Models\User;
use App\Models\UserWithdrawalRequest;
use App\Models\VenueCourtApprovalRequest;
use App\Models\VenueInformationChangeRequest;
use App\Models\VenueLocationChangeRequest;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class WorkCenterService
{
    public function forAdmin(User $admin): array
    {
        $tasks = collect()
            ->concat($this->adminPartnerApplications())
            ->concat($this->adminPartnerDocuments())
            ->concat($this->adminTerminations())
            ->concat($this->adminVenueChanges())
            ->concat($this->adminFinance())
            ->concat($this->adminSupport())
            ->sortBy([
                ['priority_order', 'asc'],
                ['created_at', 'desc'],
            ])
            ->unique('id')
            ->take(30)
            ->values();

        return $this->payload($tasks, $this->notifications($admin, 'admin'));
    }

    public function forOwner(User $owner): array
    {
        $clusterIds = DB::table('venue_clusters')
            ->where('owner_id', $owner->id)
            ->pluck('id');

        $tasks = collect()
            ->concat($this->ownerPartnerApplications($owner))
            ->concat($this->ownerPartnerDocuments($owner))
            ->concat($this->ownerTerminations($owner))
            ->concat($this->ownerRefunds($clusterIds))
            ->concat($this->ownerComplaints($clusterIds))
            ->sortBy([
                ['priority_order', 'asc'],
                ['created_at', 'desc'],
            ])
            ->unique('id')
            ->take(30)
            ->values();

        return $this->payload($tasks, $this->notifications($owner, 'owner'));
    }

    private function adminPartnerApplications(): Collection
    {
        if (! Schema::hasTable('partner_applications')) {
            return collect();
        }

        return PartnerApplication::query()
            ->with('user:id,full_name,username')
            ->whereIn('status', ['pending', 'submitted', 'reviewing', 'under_review'])
            ->oldest('submitted_at')
            ->limit(12)
            ->get()
            ->map(fn (PartnerApplication $application): array => $this->task(
                id: 'partner-application-' . $application->id,
                category: 'partner',
                priority: $application->status === 'submitted' ? 'high' : 'normal',
                title: 'Duyệt hồ sơ đối tác: ' . ($application->venue_name ?: $application->applicant_full_name),
                description: ($application->user?->full_name ?: $application->applicant_full_name) . ' đã gửi hồ sơ và đang chờ admin kiểm tra.',
                actionLabel: 'Mở hồ sơ duyệt',
                    target: '/admin/partner-applications/' . $application->id . '?tab=overview',
                createdAt: $application->submitted_at ?: $application->updated_at,
            ));
    }

    private function adminPartnerDocuments(): Collection
    {
        if (! Schema::hasTable('generated_documents')) {
            return collect();
        }

        return GeneratedDocument::query()
            ->where('status', 'pending_sportgo_signature')
            ->whereNotNull('partner_application_id')
            ->whereNull('partner_termination_request_id')
            ->latest()
            ->limit(10)
            ->get()
            ->map(fn (GeneratedDocument $document): array => $this->task(
                id: 'admin-sign-document-' . $document->id,
                category: 'signature',
                priority: 'high',
                title: 'SportGo cần ký: ' . ($document->title ?: $document->document_code),
                description: 'Văn bản ' . $document->document_code . ' đã sẵn sàng để admin ký OTP.',
                actionLabel: 'Đi tới màn ký',
                target: '/admin/partner-applications/' . $document->partner_application_id . '?tab=documents&document=' . $document->id,
                createdAt: $document->created_at,
            ));
    }

    private function adminTerminations(): Collection
    {
        if (! Schema::hasTable('partner_termination_requests')) {
            return collect();
        }

        return PartnerTerminationRequest::query()
            ->with(['venueCluster:id,name', 'documents.generatedDocument.signatures'])
            ->whereIn('status', ['draft', 'submitted', 'reviewing', 'settlement_processing', 'pending_signature'])
            ->latest()
            ->limit(12)
            ->get()
            ->map(function (PartnerTerminationRequest $request): ?array {
                $isUnilateral = $request->termination_type === 'unilateral_by_sportgo';
                if ($isUnilateral && $request->status === 'draft') {
                    return $this->task(
                        id: 'admin-unilateral-notice-sign-' . $request->id,
                        category: 'signature',
                        priority: 'critical',
                        title: 'Ký công văn chấm dứt: ' . ($request->venueCluster?->name ?: $request->termination_code),
                        description: 'Bản xem trước đã tạo nhưng công văn chưa được admin ký và chưa gửi cho chủ sân.',
                        actionLabel: 'Xem và ký công văn',
                        target: '/admin/partner-applications/' . $request->partner_application_id . '?tab=settlement&focus=termination-' . $request->id,
                        createdAt: $request->updated_at,
                    );
                }

                if ($isUnilateral && ! empty($request->workflow_state['reconsideration_pending'])) {
                    return $this->task(
                        id: 'admin-unilateral-reconsideration-' . $request->id,
                        category: 'termination',
                        priority: 'critical',
                        title: 'Xem xét phản hồi công văn: ' . ($request->venueCluster?->name ?: $request->termination_code),
                        description: $request->workflow_state['latest_reconsideration_reason'] ?: 'Chủ sân đang chờ SportGo phản hồi.',
                        actionLabel: 'Xử lý phản hồi',
                        target: '/admin/partner-applications/' . $request->partner_application_id . '?tab=settlement&focus=termination-' . $request->id,
                        createdAt: $request->updated_at,
                    );
                }

                // Owner drafts are intentionally private until they are signed and submitted.
                if ($request->status === 'draft') {
                    return null;
                }

                [$title, $description, $actionLabel, $priority] = match ($request->status) {
                    'submitted' => [
                        $isUnilateral ? 'Chờ chủ sân nhận công văn' : 'Xác nhận yêu cầu chấm dứt',
                        $isUnilateral ? 'Công văn đã gửi; chủ sân chưa xác nhận đã nhận.' : 'Chủ sân đã ký đơn. Admin cần xác nhận trước khi đối soát.',
                        $isUnilateral ? 'Theo dõi công văn' : 'Mở yêu cầu xác nhận',
                        $isUnilateral ? 'high' : 'critical',
                    ],
                    'reviewing' => [
                        'Xử lý booking của hồ sơ chấm dứt',
                        'Còn booking tương lai cần được xử lý hoặc xác nhận thủ công.',
                        'Mở danh sách xử lý',
                        'high',
                    ],
                    'settlement_processing' => [
                        'Đối soát hồ sơ chấm dứt',
                        'Kiểm tra hoàn tiền, rút tiền và sinh biên bản cuối khi đủ điều kiện.',
                        'Mở phần đối soát',
                        'high',
                    ],
                    'pending_signature' => [
                        'SportGo cần ký biên bản chấm dứt',
                        'Biên bản cuối đã sinh và đang chờ chữ ký admin.',
                        'Đi tới màn ký',
                        'critical',
                    ],
                };

                if ($request->status === 'pending_signature' && $this->hasSignedSide($request, 'sportgo')) {
                    return null;
                }

                return $this->task(
                    id: 'admin-termination-' . $request->id,
                    category: 'termination',
                    priority: $priority,
                    title: $title . ': ' . ($request->venueCluster?->name ?: $request->termination_code),
                    description: $description,
                    actionLabel: $actionLabel,
                    target: '/admin/partner-applications/' . $request->partner_application_id . '?tab=settlement&focus=termination-' . $request->id,
                    createdAt: $request->updated_at,
                );
            })
            ->filter()
            ->values();
    }

    private function adminVenueChanges(): Collection
    {
        $items = collect();

        if (Schema::hasTable('venue_court_approval_requests')) {
            $items = $items->concat(VenueCourtApprovalRequest::query()
                ->with('venueCluster:id,name')
                ->where('status', 'pending')
                ->latest()
                ->limit(8)
                ->get()
                ->map(fn (VenueCourtApprovalRequest $request): array => $this->task(
                    id: 'venue-scale-' . $request->id,
                    category: 'venue',
                    priority: 'high',
                    title: 'Duyệt thay đổi quy mô: ' . ($request->venueCluster?->name ?: 'Cụm sân'),
                    description: 'Chủ sân đã ký và gửi yêu cầu thay đổi sân con/quy mô.',
                    actionLabel: 'Mở yêu cầu quy mô',
                    target: '/admin/venue-clusters/' . $request->venue_cluster_id . '?tab=approvals&request=' . $request->id,
                    createdAt: $request->created_at,
                )));
        }

        if (Schema::hasTable('venue_location_change_requests')) {
            $items = $items->concat(VenueLocationChangeRequest::query()
                ->with('venueCluster:id,name')
                ->where('status', 'pending')
                ->latest()
                ->limit(8)
                ->get()
                ->map(fn (VenueLocationChangeRequest $request): array => $this->task(
                    id: 'venue-location-' . $request->id,
                    category: 'venue',
                    priority: 'high',
                    title: 'Duyệt thay đổi vị trí: ' . ($request->venueCluster?->name ?: 'Cụm sân'),
                    description: 'Địa chỉ/vị trí mới đang chờ admin kiểm tra và ký phụ lục.',
                    actionLabel: 'Mở yêu cầu vị trí',
                    target: '/admin/venue-clusters/' . $request->venue_cluster_id . '?tab=location_changes&request=' . $request->id,
                    createdAt: $request->created_at,
                )));
        }

        if (Schema::hasTable('venue_information_change_requests')) {
            $items = $items->concat(VenueInformationChangeRequest::query()
                ->with('venueCluster:id,name')
                ->where('status', 'pending')
                ->latest()
                ->limit(8)
                ->get()
                ->map(fn (VenueInformationChangeRequest $request): array => $this->task(
                    id: 'venue-information-' . $request->id,
                    category: 'venue',
                    priority: 'normal',
                    title: 'Duyệt thông tin cụm sân: ' . ($request->venueCluster?->name ?: 'Cụm sân'),
                    description: 'Tên, liên hệ hoặc nội dung giới thiệu đang chờ duyệt.',
                    actionLabel: 'Mở yêu cầu thông tin',
                    target: '/admin/venue-clusters/' . $request->venue_cluster_id . '?tab=info_changes&request=' . $request->id,
                    createdAt: $request->created_at,
                )));
        }

        return $items;
    }

    private function adminFinance(): Collection
    {
        $items = collect();

        if (Schema::hasTable('refunds')) {
            $items = $items->concat(Refund::query()
                ->with(['booking:id,booking_code,venue_cluster_id', 'booking.venueCluster:id,name'])
                ->where('status', 'pending_owner_confirmation')
                ->latest()
                ->limit(8)
                ->get()
                ->map(fn (Refund $refund): array => $this->task(
                    id: 'admin-refund-' . $refund->id,
                    category: 'finance',
                    priority: 'high',
                    title: 'Theo dõi hoàn tiền ' . ($refund->booking?->booking_code ?: '#' . $refund->id),
                    description: ($refund->booking?->venueCluster?->name ?: 'Booking') . ' · chờ chủ sân xác nhận, admin chỉ theo dõi.',
                    actionLabel: 'Mở yêu cầu hoàn tiền',
                    target: '/admin/finance-operations?tab=refunds&focus=' . $refund->id,
                    createdAt: $refund->created_at,
                )));
        }

        if (Schema::hasTable('owner_withdrawal_requests')) {
            $items = $items->concat(OwnerWithdrawalRequest::query()
                ->whereIn('status', ['pending', 'approved'])
                ->latest()
                ->limit(8)
                ->get()
                ->map(fn (OwnerWithdrawalRequest $withdrawal): array => $this->task(
                    id: 'owner-withdrawal-' . $withdrawal->id,
                    category: 'finance',
                    priority: 'normal',
                    title: 'Xử lý rút tiền chủ sân ' . $withdrawal->request_code,
                    description: number_format((float) $withdrawal->amount, 0, ',', '.') . ' đ đang chờ đối soát/chuyển khoản.',
                    actionLabel: 'Mở yêu cầu rút tiền',
                    target: '/admin/finance-operations?tab=withdrawals&scope=owner&focus=' . $withdrawal->id,
                    createdAt: $withdrawal->created_at,
                )));
        }

        if (Schema::hasTable('user_withdrawal_requests')) {
            $items = $items->concat(UserWithdrawalRequest::query()
                ->whereIn('status', ['pending', 'approved'])
                ->latest()
                ->limit(8)
                ->get()
                ->map(fn (UserWithdrawalRequest $withdrawal): array => $this->task(
                    id: 'user-withdrawal-' . $withdrawal->id,
                    category: 'finance',
                    priority: 'normal',
                    title: 'Xử lý rút tiền người dùng #' . $withdrawal->id,
                    description: number_format((float) $withdrawal->amount, 0, ',', '.') . ' đ đang chờ xử lý.',
                    actionLabel: 'Mở yêu cầu rút tiền',
                    target: '/admin/finance-operations?tab=withdrawals&scope=user&focus=' . $withdrawal->id,
                    createdAt: $withdrawal->created_at,
                )));
        }

        return $items;
    }

    private function adminSupport(): Collection
    {
        $items = collect();

        if (Schema::hasTable('complaints')) {
            $items = $items->concat(Complaint::query()
                ->whereIn('status', ['open', 'pending', 'in_progress'])
                ->latest()
                ->limit(8)
                ->get()
                ->map(fn (Complaint $complaint): array => $this->task(
                    id: 'admin-complaint-' . $complaint->id,
                    category: 'support',
                    priority: $complaint->is_vip_priority ? 'critical' : 'high',
                    title: 'Xử lý khiếu nại #' . $complaint->id,
                    description: mb_strimwidth($complaint->content ?: 'Khiếu nại đang chờ phản hồi.', 0, 120, '...'),
                    actionLabel: 'Mở khiếu nại',
                    target: '/admin/reports-complaints?tab=complaints&focus=' . $complaint->id,
                    createdAt: $complaint->created_at,
                )));
        }

        if (Schema::hasTable('reports')) {
            $items = $items->concat(Report::query()
                ->where('status', 'pending')
                ->latest('created_at')
                ->limit(8)
                ->get()
                ->map(fn (Report $report): array => $this->task(
                    id: 'admin-report-' . $report->id,
                    category: 'support',
                    priority: 'normal',
                    title: 'Kiểm tra báo cáo nội dung #' . $report->id,
                    description: mb_strimwidth($report->reason ?: $report->description ?: 'Báo cáo đang chờ kiểm duyệt.', 0, 120, '...'),
                    actionLabel: 'Mở báo cáo',
                    target: '/admin/reports-complaints?tab=reports&focus=' . $report->id,
                    createdAt: $report->created_at,
                )));
        }

        return $items;
    }

    private function ownerPartnerApplications(User $owner): Collection
    {
        if (! Schema::hasTable('partner_applications')) {
            return collect();
        }

        return PartnerApplication::query()
            ->where('user_id', $owner->id)
            ->where('status', 'need_supplement')
            ->latest()
            ->limit(5)
            ->get()
            ->map(fn (PartnerApplication $application): array => $this->task(
                id: 'owner-supplement-application-' . $application->id,
                category: 'partner',
                priority: 'critical',
                title: 'Bổ sung hồ sơ đối tác: ' . ($application->venue_name ?: $application->applicant_full_name),
                description: $application->status_reason ?: 'Admin yêu cầu bổ sung thông tin hoặc tài liệu trước khi duyệt tiếp.',
                actionLabel: 'Mở hồ sơ bổ sung',
                target: '/owner/partner-profile?application=' . $application->id,
                createdAt: $application->updated_at,
            ));
    }

    private function ownerPartnerDocuments(User $owner): Collection
    {
        if (! Schema::hasTable('generated_documents')) {
            return collect();
        }

        return GeneratedDocument::query()
            ->where('owner_id', $owner->id)
            ->where('status', 'pending_owner_signature')
            ->whereNull('partner_termination_request_id')
            ->latest()
            ->limit(10)
            ->get()
            ->map(function (GeneratedDocument $document): array {
                $target = $document->partner_termination_request_id
                    ? '/owner/termination-requests/' . $document->partner_termination_request_id
                    : '/owner/partner-profile?document=' . $document->id;

                return $this->task(
                    id: 'owner-sign-document-' . $document->id,
                    category: 'signature',
                    priority: 'critical',
                    title: 'Bạn cần ký: ' . ($document->title ?: $document->document_code),
                    description: 'Văn bản ' . $document->document_code . ' đang chờ chữ ký OTP của chủ sân.',
                    actionLabel: 'Đi tới màn ký',
                    target: $target,
                    createdAt: $document->created_at,
                );
            });
    }

    private function ownerTerminations(User $owner): Collection
    {
        if (! Schema::hasTable('partner_termination_requests')) {
            return collect();
        }

        return PartnerTerminationRequest::query()
            ->with(['venueCluster:id,name', 'documents.generatedDocument.signatures'])
            ->where('owner_id', $owner->id)
            ->whereIn('status', ['draft', 'submitted', 'reviewing', 'settlement_processing', 'pending_signature'])
            ->latest()
            ->limit(10)
            ->get()
            ->map(function (PartnerTerminationRequest $request): ?array {
                $isUnilateral = $request->termination_type === 'unilateral_by_sportgo';
                if ($isUnilateral && $request->status === 'draft') {
                    return null;
                }

                if ($request->status === 'pending_signature' && ! $this->hasSignedSide($request, 'sportgo')) {
                    return null;
                }

                [$title, $description, $actionLabel, $priority] = match ($request->status) {
                    'draft' => [
                        'Ký và gửi yêu cầu chấm dứt',
                        'Bản xem trước đã tạo nhưng yêu cầu chưa được ký gửi.',
                        'Tiếp tục ký gửi',
                        'critical',
                    ],
                    'submitted' => [
                        $isUnilateral ? 'Xác nhận đã nhận công văn SportGo' : 'Theo dõi yêu cầu chấm dứt',
                        $isUnilateral ? 'Mở file công văn, đọc nội dung và xác nhận đã nhận để chuyển sang xử lý booking/công nợ.' : 'Yêu cầu đã gửi và đang chờ admin xác nhận.',
                        $isUnilateral ? 'Xem và xác nhận công văn' : 'Mở hồ sơ',
                        $isUnilateral ? 'critical' : 'normal',
                    ],
                    'reviewing' => [
                        'Xử lý booking trước khi chấm dứt',
                        'Còn booking tương lai cần thực hiện theo phương án đã chọn.',
                        'Mở danh sách booking',
                        'high',
                    ],
                    'settlement_processing' => [
                        'Hoàn tất nghĩa vụ tài chính',
                        'Kiểm tra hoàn tiền, khoản rút và số dư trước khi sinh biên bản cuối.',
                        'Mở hồ sơ đối soát',
                        'high',
                    ],
                    'pending_signature' => [
                        'Chủ sân cần ký biên bản chấm dứt',
                        'SportGo đã ký biên bản cuối. Vui lòng kiểm tra file rồi ký OTP.',
                        'Đi tới màn ký',
                        'critical',
                    ],
                };

                return $this->task(
                    id: 'owner-termination-' . $request->id,
                    category: 'termination',
                    priority: $priority,
                    title: $title . ': ' . ($request->venueCluster?->name ?: $request->termination_code),
                    description: $description,
                    actionLabel: $actionLabel,
                    target: '/owner/termination-requests/' . $request->id,
                    createdAt: $request->updated_at,
                );
            })
            ->filter()
            ->values();
    }

    private function ownerRefunds(Collection $clusterIds): Collection
    {
        if (! Schema::hasTable('refunds') || $clusterIds->isEmpty()) {
            return collect();
        }

        return Refund::query()
            ->with(['booking:id,booking_code,venue_cluster_id', 'booking.venueCluster:id,name'])
            ->where('status', 'pending_owner_confirmation')
            ->whereHas('booking', fn ($query) => $query->whereIn('venue_cluster_id', $clusterIds))
            ->latest()
            ->limit(10)
            ->get()
            ->map(fn (Refund $refund): array => $this->task(
                id: 'owner-refund-' . $refund->id,
                category: 'finance',
                priority: 'critical',
                title: 'Xác nhận hoàn tiền ' . ($refund->booking?->booking_code ?: '#' . $refund->id),
                description: ($refund->booking?->venueCluster?->name ?: 'Booking') . ' · kiểm tra mức hoàn theo chính sách trước khi xác nhận.',
                actionLabel: 'Xử lý hoàn tiền',
                target: '/owner/refunds?focus=' . $refund->id,
                createdAt: $refund->created_at,
            ));
    }

    private function ownerComplaints(Collection $clusterIds): Collection
    {
        if (! Schema::hasTable('complaints') || $clusterIds->isEmpty()) {
            return collect();
        }

        return Complaint::query()
            ->with('venueCluster:id,name')
            ->whereIn('venue_cluster_id', $clusterIds)
            ->whereIn('status', ['open', 'pending', 'in_progress', 'waiting_owner_response'])
            ->latest()
            ->limit(10)
            ->get()
            ->map(fn (Complaint $complaint): array => $this->task(
                id: 'owner-complaint-' . $complaint->id,
                category: 'support',
                priority: $complaint->is_vip_priority ? 'critical' : 'high',
                title: 'Phản hồi khiếu nại #' . $complaint->id,
                description: ($complaint->venueCluster?->name ?: 'Cụm sân') . ' · ' . mb_strimwidth($complaint->content ?: '', 0, 100, '...'),
                actionLabel: 'Mở khiếu nại',
                target: '/owner/complaints/' . $complaint->id,
                createdAt: $complaint->created_at,
            ));
    }

    private function notifications(User $user, string $audience): Collection
    {
        if (! Schema::hasTable('notifications')) {
            return collect();
        }

        return DB::table('notifications')
            ->where('user_id', $user->id)
            ->latest('created_at')
            ->limit(12)
            ->get()
            ->map(function (object $notification) use ($audience): array {
                $data = is_string($notification->data)
                    ? (json_decode($notification->data, true) ?: [])
                    : (array) ($notification->data ?: []);

                return [
                    'id' => 'notification-' . $notification->id,
                    'notification_id' => $notification->id,
                    'kind' => 'notification',
                    'category' => $this->notificationCategory((string) $notification->type),
                    'priority' => 'normal',
                    'title' => $notification->title,
                    'description' => $notification->body,
                    'action_label' => 'Xem chi tiết',
                    'target' => $this->notificationTarget($notification, $data, $audience),
                    'created_at' => $notification->created_at,
                    'is_read' => (bool) $notification->is_read,
                ];
            });
    }

    private function notificationTarget(object $notification, array $data, string $audience): string
    {
        if (! empty($data['action_url']) && str_starts_with((string) $data['action_url'], '/')) {
            return (string) $data['action_url'];
        }

        if (! empty($data['link']) && str_starts_with((string) $data['link'], '/')) {
            return (string) $data['link'];
        }

        $referenceType = str_replace('\\', '/', strtolower((string) $notification->reference_type));
        $referenceId = $notification->reference_id;

        if (str_contains($referenceType, 'partner_termination_request')) {
            if ($audience === 'owner') {
                return '/owner/termination-requests/' . $referenceId;
            }

            $applicationId = DB::table('partner_termination_requests')->where('id', $referenceId)->value('partner_application_id');
            return $applicationId
                ? '/admin/partner-applications/' . $applicationId . '?tab=settlement&focus=termination-' . $referenceId
                : '/admin/partner-applications?tab=terminating';
        }

        if (str_contains($referenceType, 'partner_application')) {
            return $audience === 'admin'
                ? '/admin/partner-applications/' . $referenceId
                : '/owner/partner-profile';
        }

        if (str_contains($referenceType, 'refund')) {
            return $audience === 'admin'
                ? '/admin/finance-operations?tab=refunds&focus=' . $referenceId
                : '/owner/refunds?focus=' . $referenceId;
        }

        if (str_contains($referenceType, 'complaint')) {
            return $audience === 'admin'
                ? '/admin/reports-complaints?tab=complaints&focus=' . $referenceId
                : '/owner/complaints/' . $referenceId;
        }

        if (str_contains($referenceType, 'withdrawal')) {
            return $audience === 'admin' ? '/admin/finance-operations?tab=withdrawals' : '/owner/finance';
        }

        return $audience === 'admin' ? '/admin/dashboard' : '/owner/dashboard';
    }

    private function hasSignedSide(PartnerTerminationRequest $request, string $side): bool
    {
        return $request->documents
            ->pluck('generatedDocument')
            ->filter()
            ->flatMap(fn (GeneratedDocument $document) => $document->signatures)
            ->contains(fn ($signature): bool => $signature->status === 'signed' && $signature->signer_side === $side);
    }

    private function payload(Collection $tasks, Collection $notifications): array
    {
        $unreadNotifications = $notifications->where('is_read', false)->count();

        return [
            'summary' => [
                'action_count' => $tasks->count(),
                'unread_notification_count' => $unreadNotifications,
                'categories' => $tasks->countBy('category')->all(),
            ],
            'tasks' => $tasks->map(fn (array $item): array => collect($item)->except('priority_order')->all())->values(),
            'notifications' => $notifications->values(),
        ];
    }

    private function task(
        string $id,
        string $category,
        string $priority,
        string $title,
        string $description,
        string $actionLabel,
        string $target,
        mixed $createdAt,
    ): array {
        return [
            'id' => $id,
            'kind' => 'task',
            'category' => $category,
            'priority' => $priority,
            'priority_order' => ['critical' => 0, 'high' => 1, 'normal' => 2][$priority] ?? 2,
            'title' => $title,
            'description' => $description,
            'action_label' => $actionLabel,
            'target' => $target,
            'created_at' => optional($createdAt)->toIso8601String() ?: (string) $createdAt,
            'is_read' => false,
        ];
    }

    private function notificationCategory(string $type): string
    {
        return match (true) {
            str_contains($type, 'partner') => 'partner',
            str_contains($type, 'refund'), str_contains($type, 'withdrawal') => 'finance',
            str_contains($type, 'complaint'), str_contains($type, 'report') => 'support',
            default => 'system',
        };
    }
}
