<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Complaint;
use App\Models\Notification;
use App\Models\Refund;
use App\Models\User;
use App\Services\Admin\AdminAuditService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class AdminComplaintController extends Controller
{
    public function __construct(private readonly AdminAuditService $audit)
    {
    }

    public function index(Request $request): JsonResponse
    {
        $this->authorizePermission($request, 'complaint.view');
        $request->validate([
            'complaint_type' => ['nullable', Rule::in(['venue', 'system'])],
            'status' => ['nullable', Rule::in(['open', 'processing', 'resolved', 'rejected', 'closed'])],
            'assigned_to' => ['nullable', Rule::when(
                $request->query('assigned_to') !== 'unassigned',
                ['uuid', 'exists:users,id']
            )],
            'date_from' => ['nullable', 'date_format:Y-m-d'],
            'date_to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:date_from'],
            'keyword' => ['nullable', 'string', 'max:100'],
        ]);

        $request->validate([
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date', 'after_or_equal:date_from'],
        ]);

        $complaints = Complaint::query()
            ->with([
                'customer:id,username,full_name,email,phone',
                'assignedTo:id,username,full_name',
                'resolvedBy:id,username,full_name',
                'venueCluster:id,name',
                'booking:id,booking_code,status,total_price,booking_date',
            ])
            ->when($request->filled('complaint_type'), fn ($query) => $query->where('complaint_type', $request->query('complaint_type')))
            ->when($request->filled('status'), fn ($query) => $query->where('status', $request->query('status')))
            ->when($request->filled('assigned_to'), function ($query) use ($request): void {
                $request->query('assigned_to') === 'unassigned'
                    ? $query->whereNull('assigned_to')
                    : $query->where('assigned_to', $request->query('assigned_to'));
            })
            ->when($request->filled('date_from'), fn ($query) => $query->whereDate('created_at', '>=', $request->query('date_from')))
            ->when($request->filled('date_to'), fn ($query) => $query->whereDate('created_at', '<=', $request->query('date_to')))
            ->when($request->filled('keyword'), function ($query) use ($request): void {
                $keyword = '%'.$request->query('keyword').'%';
                $query->where(function ($inner) use ($keyword): void {
                    $inner->where('content', 'like', $keyword)
                        ->orWhereHas('customer', fn ($user) => $user
                            ->where('full_name', 'like', $keyword)
                            ->orWhere('email', 'like', $keyword)
                            ->orWhere('phone', 'like', $keyword))
                        ->orWhereHas('booking', fn ($booking) => $booking->where('booking_code', 'like', $keyword))
                        ->orWhereHas('venueCluster', fn ($venue) => $venue->where('name', 'like', $keyword));
                });
            })
            ->orderByDesc('is_vip_priority')
            ->latest()
            ->get();

        $staff = User::query()
            ->where('status', 'active')
            ->whereHas('roles', fn ($query) => $query->whereIn('roles.name', ['super_admin', 'admin', 'complaint_handler', 'system_staff']))
            ->orderBy('full_name')
            ->get(['id', 'username', 'full_name']);

        return response()->json([
            'data' => $complaints->map(fn (Complaint $complaint): array => $this->listPayload($complaint))->values(),
            'summary' => [
                'total' => Complaint::query()->count(),
                'open' => Complaint::query()->where('status', 'open')->count(),
                'processing' => Complaint::query()->where('status', 'processing')->count(),
                'resolved' => Complaint::query()->where('status', 'resolved')->count(),
            ],
            'staff' => $staff,
        ]);
    }

    public function show(Request $request, string $id): JsonResponse
    {
        $this->authorizePermission($request, 'complaint.view');

        $complaint = Complaint::query()
            ->with([
                'customer:id,username,full_name,email,phone',
                'assignedTo:id,username,full_name,email',
                'resolvedBy:id,username,full_name',
                'venueCluster:id,name,address,owner_id',
                'venueCluster.owner:id,full_name,email',
                'booking.customer:id,full_name,email,phone',
                'booking.venueCluster:id,name',
                'booking.venueCourt:id,name',
                'booking.payments',
                'evidence',
            ])
            ->findOrFail($id);

        return response()->json([
            'data' => [
                'complaint' => $this->detailPayload($complaint),
                'audit_logs' => $this->auditLogs($complaint),
            ],
        ]);
    }

    public function assign(Request $request, string $id): JsonResponse
    {
        $this->authorizePermission($request, 'complaint.handle');

        $data = $request->validate([
            'assigned_to' => ['required', 'uuid', 'exists:users,id'],
        ]);

        $assignee = User::query()
            ->whereKey($data['assigned_to'])
            ->where('status', 'active')
            ->whereHas('roles', fn ($query) => $query->whereIn('roles.name', ['super_admin', 'admin', 'complaint_handler', 'system_staff']))
            ->first();

        if (! $assignee) {
            throw ValidationException::withMessages(['assigned_to' => 'Người được chọn không có quyền xử lý khiếu nại.']);
        }

        $complaint = Complaint::query()->findOrFail($id);
        if (in_array($complaint->status, ['resolved', 'rejected', 'closed'], true)) {
            throw ValidationException::withMessages(['status' => 'Khiếu nại này đã kết thúc, không thể phân công lại.']);
        }

        $oldValues = $complaint->only(['status', 'assigned_to']);
        $complaint->forceFill([
            'assigned_to' => $assignee->id,
            'status' => in_array($complaint->status, ['open', 'processing'], true) ? 'processing' : $complaint->status,
        ])->save();

        $this->audit->log($request, 'complaint', 'complaint.assigned', 'complaints', $complaint->id, $oldValues, $complaint->fresh()->toArray());
        $this->notify($assignee, $complaint, 'Bạn được phân công xử lý khiếu nại', 'Vui lòng kiểm tra nội dung và bằng chứng liên quan.');

        return response()->json([
            'message' => 'Đã phân công người xử lý.',
            'data' => $this->detailPayload($complaint->fresh($this->detailRelations())),
        ]);
    }

    public function resolve(Request $request, string $id): JsonResponse
    {
        $this->authorizePermission($request, 'complaint.handle');

        $data = $request->validate([
            'status' => ['required', Rule::in(['processing', 'resolved', 'rejected', 'closed'])],
            'resolve_note' => ['required', 'string', 'max:4000'],
        ]);

        $complaint = Complaint::query()->with(['customer', 'venueCluster.owner'])->findOrFail($id);
        if (in_array($complaint->status, ['resolved', 'rejected', 'closed'], true)) {
            throw ValidationException::withMessages([
                'status' => 'Khiếu nại đã kết thúc và không thể cập nhật lại.',
            ]);
        }
        $isSuperAdmin = $request->user()->roles()->where('roles.name', 'super_admin')->exists();
        if ($complaint->assigned_to && $complaint->assigned_to !== $request->user()->id && ! $isSuperAdmin) {
            throw ValidationException::withMessages([
                'assigned_to' => 'Khiếu nại đang được phân công cho nhân sự khác.',
            ]);
        }
        $oldValues = $complaint->toArray();
        $isFinished = in_array($data['status'], ['resolved', 'rejected', 'closed'], true);

        $complaint->forceFill([
            'status' => $data['status'],
            'assigned_to' => $request->user()->id,
            'resolved_by' => $isFinished ? $request->user()->id : null,
            'resolve_note' => $data['resolve_note'],
            'status_reason' => in_array($data['status'], ['rejected', 'closed'], true) ? $data['resolve_note'] : null,
            'resolved_at' => $isFinished ? now() : null,
        ])->save();

        $this->audit->log($request, 'complaint', 'complaint.'.$data['status'], 'complaints', $complaint->id, $oldValues, $complaint->fresh()->toArray(), [
            'reason' => $data['resolve_note'],
            'severity' => $data['status'] === 'rejected' ? 'warning' : 'info',
        ]);

        $this->notify($complaint->customer, $complaint, 'Khiếu nại đã được cập nhật', $data['resolve_note']);
        if ($complaint->complaint_type === 'venue') {
            $this->notify($complaint->venueCluster?->owner, $complaint, 'Cập nhật khiếu nại liên quan cụm sân', $data['resolve_note']);
        }

        return response()->json([
            'message' => 'Đã cập nhật kết quả xử lý khiếu nại.',
            'data' => $this->detailPayload($complaint->fresh($this->detailRelations())),
        ]);
    }

    private function listPayload(Complaint $complaint): array
    {
        return [
            'id' => $complaint->id,
            'complaint_type' => $complaint->complaint_type,
            'content' => $complaint->content,
            'status' => $complaint->status,
            'is_vip_priority' => (bool) $complaint->is_vip_priority,
            'customer' => $this->userPayload($complaint->customer),
            'assigned_to' => $this->userPayload($complaint->assignedTo),
            'resolved_by' => $this->userPayload($complaint->resolvedBy),
            'venue_cluster' => $complaint->venueCluster ? [
                'id' => $complaint->venueCluster->id,
                'name' => $complaint->venueCluster->name,
            ] : null,
            'booking' => $complaint->booking ? [
                'id' => $complaint->booking->id,
                'booking_code' => $complaint->booking->booking_code,
                'status' => $complaint->booking->status,
                'total_price' => $complaint->booking->total_price,
                'booking_date' => $complaint->booking->booking_date,
            ] : null,
            'resolved_at' => $complaint->resolved_at,
            'created_at' => $complaint->created_at,
        ];
    }

    private function detailPayload(Complaint $complaint): array
    {
        $booking = $complaint->booking;
        $refunds = $booking
            ? Refund::query()->where('booking_id', $booking->id)->with('processedBy:id,full_name')->latest()->get()
            : collect();

        return [
            ...$this->listPayload($complaint),
            'resolve_note' => $complaint->resolve_note,
            'status_reason' => $complaint->status_reason,
            'booking_detail' => $booking ? [
                'id' => $booking->id,
                'booking_code' => $booking->booking_code,
                'booking_date' => $booking->booking_date,
                'start_time' => $booking->start_time,
                'end_time' => $booking->end_time,
                'status' => $booking->status,
                'total_price' => $booking->total_price,
                'required_payment_amount' => $booking->required_payment_amount,
                'venue_cluster' => $booking->venueCluster?->only(['id', 'name']),
                'venue_court' => $booking->venueCourt?->only(['id', 'name']),
                'payments' => $booking->payments->map(fn ($payment): array => [
                    'id' => $payment->id,
                    'payment_code' => $payment->payment_code,
                    'amount' => $payment->amount,
                    'status' => $payment->status,
                    'method' => $payment->method,
                    'paid_at' => $payment->paid_at,
                ])->values(),
                'refunds' => $refunds,
            ] : null,
            'evidence' => $complaint->evidence->map(fn ($media): array => [
                'id' => $media->id,
                'file_name' => $media->file_name,
                'file_path' => $media->file_path,
                'mime_type' => $media->mime_type,
                'file_size' => $media->file_size,
            ])->values(),
        ];
    }

    private function detailRelations(): array
    {
        return [
            'customer', 'assignedTo', 'resolvedBy', 'venueCluster.owner',
            'booking.venueCluster', 'booking.venueCourt', 'booking.payments', 'evidence',
        ];
    }

    private function userPayload(?User $user): ?array
    {
        return $user ? [
            'id' => $user->id,
            'full_name' => $user->full_name,
            'username' => $user->username,
            'email' => $user->email,
            'phone' => $user->phone,
        ] : null;
    }

    private function auditLogs(Complaint $complaint)
    {
        if (! Schema::hasTable('audit_logs')) {
            return [];
        }

        return AuditLog::query()
            ->where('entity_type', 'complaints')
            ->where('entity_id', $complaint->id)
            ->with('actor:id,full_name,username')
            ->latest()
            ->limit(50)
            ->get();
    }

    private function notify(?User $user, Complaint $complaint, string $title, string $body): void
    {
        if (! $user || ! Schema::hasTable('notifications')) {
            return;
        }

        Notification::query()->create([
            'user_id' => $user->id,
            'type' => 'complaint_updated',
            'title' => $title,
            'body' => $body,
            'reference_type' => Complaint::class,
            'reference_id' => $complaint->id,
            'data' => ['status' => $complaint->status],
            'is_read' => false,
        ]);
    }

    public function autoResolveConfig(Request $request): JsonResponse
    {
        $this->authorizePermission($request, 'policy.rule.manage');

        $activePolicy = \App\Models\SystemPolicy::where('policy_type', 'moderation')->where('status', 'active')->first();
        if (!$activePolicy) {
            $activePolicy = \App\Models\SystemPolicy::where('key', 'moderation')->orderByDesc('version')->first();
        }

        $rules = $activePolicy ? $activePolicy->rules()->get() : collect();

        $types = ['venue', 'system'];
        $configs = [];

        foreach ($types as $type) {
            $rule = $rules->firstWhere('rule_code', 'complaint_auto_resolve_' . $type);
            $isAutoResolveEnabled = false;
            $reason = $type === 'venue' 
                ? 'Hệ thống tự động giải quyết khiếu nại cụm sân và thông báo tới chủ sân.' 
                : 'Hệ thống tự động giải quyết khiếu nại hệ thống.';

            if ($rule) {
                $r = $rule->result_json ?? [];
                $isAutoResolveEnabled = $r['is_auto_resolve_enabled'] ?? false;
                $reason = $r['reason'] ?? $reason;
            }

            $configs[$type] = [
                'target_type' => $type,
                'target_type_label' => $type === 'venue' ? 'Khiếu nại cụm sân' : 'Khiếu nại hệ thống',
                'reason' => $reason,
                'is_auto_resolve_enabled' => $isAutoResolveEnabled,
            ];
        }

        return response()->json([
            'data' => [
                'policy_id' => $activePolicy?->id,
                'configs' => $configs,
            ]
        ]);
    }

    public function saveAutoResolveConfig(Request $request): JsonResponse
    {
        $this->authorizePermission($request, 'policy.rule.manage');

        $data = $request->validate([
            'configs' => ['required', 'array'],
            'configs.*.target_type' => ['required', 'string', 'in:venue,system'],
            'configs.*.is_auto_resolve_enabled' => ['required', 'boolean'],
            'configs.*.reason' => ['required', 'string', 'max:255'],
        ]);

        $activePolicy = \App\Models\SystemPolicy::where('policy_type', 'moderation')->where('status', 'active')->first();
        if (!$activePolicy) {
            $activePolicy = \App\Models\SystemPolicy::where('key', 'moderation')->orderByDesc('version')->first();
        }

        if (!$activePolicy) {
            throw ValidationException::withMessages(['policy' => 'Không có chính sách kiểm duyệt nào đang hoạt động.']);
        }

        foreach ($data['configs'] as $config) {
            $targetType = $config['target_type'];
            
            $rule = \App\Models\PolicyRule::query()->updateOrCreate(
                [
                    'system_policy_id' => $activePolicy->id,
                    'rule_code' => 'complaint_auto_resolve_' . $targetType,
                ],
                [
                    'rule_name' => 'Tự động xử lý khiếu nại: ' . ($targetType === 'venue' ? 'Cụm sân' : 'Hệ thống'),
                    'rule_type' => 'complaint_auto_resolve',
                    'action_code' => 'complaint.created',
                    'decision_key' => 'complaint_auto_resolve',
                    'conflict_group' => 'complaint_auto_resolve_' . $targetType,
                    'condition_json' => ['complaint_type' => $targetType],
                    'priority' => $activePolicy->priority ?? 100,
                    'is_active' => true,
                ]
            );

            $r = $rule->result_json ?? [];
            $r['is_auto_resolve_enabled'] = $config['is_auto_resolve_enabled'];
            $r['reason'] = $config['reason'];
            
            $rule->update([
                'result_json' => $r,
            ]);
        }

        return response()->json(['message' => 'Lưu cấu hình tự động xử lý khiếu nại thành công.']);
    }

    private function authorizePermission(Request $request, string $permission): void
    {
        $user = $request->user();
        if (! $user) {
            throw new AuthorizationException('Bạn cần đăng nhập để thực hiện thao tác này.');
        }

        $roles = $user->roles()->pluck('roles.name')->all();
        if (array_intersect($roles, ['super_admin', 'admin'])) {
            return;
        }

        $hasPermission = DB::table('user_roles')
            ->join('role_permissions', 'role_permissions.role_id', '=', 'user_roles.role_id')
            ->join('permissions', 'permissions.id', '=', 'role_permissions.permission_id')
            ->where('user_roles.user_id', $user->id)
            ->where('permissions.code', $permission)
            ->exists();

        if (! $hasPermission) {
            throw new AuthorizationException('Bạn không có quyền thực hiện thao tác này.');
        }
    }
}
