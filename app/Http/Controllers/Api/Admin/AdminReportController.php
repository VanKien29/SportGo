<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\CommunityPost;
use App\Models\CommunityPostComment;
use App\Models\Notification;
use App\Models\PlayerPost;
use App\Models\Report;
use App\Models\User;
use App\Models\VenueCluster;
use App\Models\VenuePost;
use App\Models\ViolationRecord;
use App\Services\Admin\AdminAuditService;
use App\Services\Moderation\PenaltyEscalationService;
use App\Services\Moderation\ViolationScoreService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class AdminReportController extends Controller
{
    private const TARGET_TYPES = [
        'post' => CommunityPost::class,
        'comment' => CommunityPostComment::class,
        'venue_post' => VenuePost::class,
        'player_post' => PlayerPost::class,
        'user' => User::class,
        'venue' => VenueCluster::class,
    ];

    public function __construct(
        private readonly AdminAuditService $audit,
        private readonly PenaltyEscalationService $penalties,
        private readonly ViolationScoreService $violationScores
    )
    {
    }

    public function index(Request $request): JsonResponse
    {
        $this->authorizePermission($request, 'report.view');
        $request->validate([
            'status' => ['nullable', Rule::in(['pending', 'reviewing', 'resolved', 'dismissed'])],
            'reason' => ['nullable', Rule::in(['spam', 'offensive', 'fake', 'harassment', 'other'])],
            'target_type' => ['nullable', Rule::in(array_keys(self::TARGET_TYPES))],
            'date_from' => ['nullable', 'date_format:Y-m-d'],
            'date_to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:date_from'],
            'keyword' => ['nullable', 'string', 'max:100'],
        ]);

        $query = Report::query()
            ->with(['reporter:id,username,full_name,email', 'reviewedBy:id,username,full_name'])
            ->when($request->filled('status'), fn ($builder) => $builder->where('status', $request->query('status')))
            ->when($request->filled('reason'), fn ($builder) => $builder->where('reason', $request->query('reason')))
            ->when($request->filled('target_type'), function ($builder) use ($request): void {
                $type = self::TARGET_TYPES[$request->query('target_type')] ?? null;
                if ($type) {
                    $builder->where('reportable_type', $type);
                }
            })
            ->when($request->filled('date_from'), fn ($builder) => $builder->whereDate('created_at', '>=', $request->query('date_from')))
            ->when($request->filled('date_to'), fn ($builder) => $builder->whereDate('created_at', '<=', $request->query('date_to')))
            ->when($request->filled('keyword'), function ($builder) use ($request): void {
                $keyword = '%'.$request->query('keyword').'%';
                $builder->where(function ($inner) use ($keyword): void {
                    $inner->where('description', 'like', $keyword)
                        ->orWhere('reportable_id', 'like', $keyword)
                        ->orWhereHas('reporter', fn ($user) => $user
                            ->where('full_name', 'like', $keyword)
                            ->orWhere('username', 'like', $keyword)
                            ->orWhere('email', 'like', $keyword));
                });
            })
            ->latest();

        $reports = $query->get();
        $summary = [
            'total' => Report::query()->count(),
            'pending' => Report::query()->where('status', 'pending')->count(),
            'reviewing' => Report::query()->where('status', 'reviewing')->count(),
            'resolved' => Report::query()->where('status', 'resolved')->count(),
        ];

        return response()->json([
            'data' => $reports->map(fn (Report $report): array => $this->listPayload($report))->values(),
            'summary' => $summary,
        ]);
    }

    public function show(Request $request, string $id): JsonResponse
    {
        $this->authorizePermission($request, 'report.view');

        $report = Report::query()
            ->with(['reporter:id,username,full_name,email,phone', 'reviewedBy:id,username,full_name', 'reportable', 'evidence', 'violationType'])
            ->findOrFail($id);

        return response()->json([
            'data' => [
                'report' => $this->detailPayload($report),
                'audit_logs' => $this->auditLogs($report),
            ],
        ]);
    }

    public function violationRecord(Request $request, string $targetType, string $targetId): JsonResponse
    {
        $this->authorizePermission($request, 'report.view');

        if (! in_array($targetType, ['user', 'venue_cluster'], true)) {
            throw ValidationException::withMessages([
                'target_type' => 'Đối tượng hồ sơ vi phạm không hợp lệ.',
            ]);
        }

        $record = ViolationRecord::query()
            ->where('target_type', $targetType)
            ->where('target_id', $targetId)
            ->first();
        $suggestedPenalty = $this->penalties->getSuggestedPenalty($targetType);

        return response()->json([
            'data' => [
                'target_type' => $targetType,
                'target_id' => $targetId,
                'violation_count' => (int) ($record?->violation_count ?? 0),
                'last_violation_at' => $record?->last_violation_at,
                'last_action_type' => $record?->last_action_type,
                'last_action_expires_at' => $record?->last_action_expires_at,
                'suggested_penalty' => $suggestedPenalty ? [
                    'action_type' => $suggestedPenalty->action_type,
                    'duration_days' => $suggestedPenalty->duration_days,
                ] : null,
            ],
        ]);
    }

    public function review(Request $request, string $id): JsonResponse
    {
        $this->authorizePermission($request, 'report.resolve');

        $report = Report::query()->findOrFail($id);
        if (in_array($report->status, ['resolved', 'dismissed'], true)) {
            throw ValidationException::withMessages(['status' => 'Báo cáo này đã được xử lý.']);
        }

        if ($report->status === 'reviewing') {
            if ($report->reviewed_by !== $request->user()->id) {
                throw ValidationException::withMessages([
                    'status' => 'Báo cáo đang được quản trị viên khác kiểm duyệt.',
                ]);
            }

            return response()->json([
                'message' => 'Bạn đang kiểm duyệt báo cáo này.',
                'data' => $this->detailPayload($report->load(['reporter', 'reviewedBy', 'reportable', 'evidence'])),
            ]);
        }

        $oldValues = $report->only(['status', 'reviewed_by', 'reviewed_at']);
        $report->forceFill([
            'status' => 'reviewing',
            'reviewed_by' => $request->user()->id,
            'reviewed_at' => now(),
        ])->save();

        $this->audit->log($request, 'report', 'report.reviewing', 'reports', $report->id, $oldValues, $report->fresh()->toArray());

        return response()->json([
            'message' => 'Đã nhận kiểm duyệt báo cáo.',
            'data' => $this->detailPayload($report->fresh(['reporter', 'reviewedBy', 'reportable', 'evidence'])),
        ]);
    }

    public function resolve(Request $request, string $id): JsonResponse
    {
        $this->authorizePermission($request, 'report.resolve');

        $data = $request->validate([
            'decision' => ['required', Rule::in(['resolved', 'dismissed'])],
            'action_taken' => ['nullable', Rule::in(['warning', 'content_hidden', 'content_deleted', 'account_locked', 'venue_locked'])],
            'action_note' => ['required', 'string', 'max:3000'],
            'lock_days' => ['nullable', 'integer', 'min:1', 'max:365'],
            'use_suggested' => ['nullable', 'boolean'],
            'action_type' => ['nullable', 'string', 'max:50'],
            'duration_days' => ['nullable', 'integer', 'min:1', 'max:3650'],
        ]);

        if ($data['decision'] === 'resolved' && empty($data['action_taken']) && empty($data['use_suggested']) && empty($data['action_type'])) {
            throw ValidationException::withMessages(['action_taken' => 'Vui lòng chọn hành động xử lý.']);
        }

        $report = Report::query()->with('reportable')->findOrFail($id);
        if (in_array($report->status, ['resolved', 'dismissed'], true)) {
            throw ValidationException::withMessages(['status' => 'Báo cáo này đã được xử lý.']);
        }

        $targetOwner = $this->targetOwner($report->reportable);
        $oldValues = $report->toArray();
        $appliedReportAction = $data['action_taken'] ?? null;

        DB::transaction(function () use ($request, $report, $targetOwner, $data, &$appliedReportAction): void {
            if ($data['decision'] === 'resolved') {
                if ($data['use_suggested'] ?? false) {
                    [$penaltyTargetType, $penaltyTargetId] = $this->penaltyTarget($report);
                    $suggested = $this->penalties->getSuggestedPenalty($penaltyTargetType);
                    if (! $suggested) {
                        throw ValidationException::withMessages(['use_suggested' => 'Chưa có cấu hình hình phạt đề xuất cho đối tượng này.']);
                    }
                    $this->penalties->applyPenalty($penaltyTargetType, $penaltyTargetId, $suggested->action_type, $suggested->duration_days, $request->user(), $data['action_note']);
                    $appliedReportAction = $this->legacyActionFromPenalty($suggested->action_type);
                } elseif (! empty($data['action_type'])) {
                    [$penaltyTargetType, $penaltyTargetId] = $this->penaltyTarget($report);
                    $this->penalties->applyPenalty($penaltyTargetType, $penaltyTargetId, $data['action_type'], $data['duration_days'] ?? null, $request->user(), $data['action_note']);
                    $appliedReportAction = $this->legacyActionFromPenalty($data['action_type']);
                } else {
                    $this->applyAction($request, $report, $data['action_taken'], $data['action_note'], $data['lock_days'] ?? null);
                }
            }

            $report->forceFill([
                'status' => $data['decision'],
                'action_taken' => $data['decision'] === 'resolved' ? $appliedReportAction : null,
                'action_note' => $data['action_note'],
                'reviewed_by' => $request->user()->id,
                'reviewed_at' => now(),
            ])->save();

            $this->notifyReportUsers($report, $targetOwner, $data['decision'], $data['action_note']);
        });

        $this->audit->log($request, 'report', 'report.'.$data['decision'], 'reports', $report->id, $oldValues, $report->fresh()->toArray(), [
            'reason' => $data['action_note'],
            'severity' => $data['decision'] === 'resolved' ? 'warning' : 'info',
        ]);

        return response()->json([
            'message' => $data['decision'] === 'resolved' ? 'Đã xử lý báo cáo.' : 'Đã bỏ qua báo cáo.',
            'data' => $this->detailPayload($report->fresh(['reporter', 'reviewedBy', 'reportable', 'evidence'])),
        ]);
    }

    private function applyAction(Request $request, Report $report, string $action, string $reason, ?int $lockDays): void
    {
        $target = $report->reportable;

        if (! $target) {
            throw ValidationException::withMessages(['action_taken' => 'Đối tượng bị báo cáo không còn tồn tại.']);
        }

        if ($action === 'warning') {
            return;
        }

        if (in_array($action, ['content_hidden', 'content_deleted'], true)) {
            if (! $this->isContent($target)) {
                throw ValidationException::withMessages(['action_taken' => 'Hành động này chỉ áp dụng cho bài viết hoặc bình luận.']);
            }

            if ($action === 'content_deleted') {
                $snapshot = $target->toArray();
                $target->delete();
                $this->audit->log($request, 'report', 'content.deleted', $target->getTable(), $target->getKey(), $snapshot, [], ['reason' => $reason, 'severity' => 'warning']);
                return;
            }

            $old = $target->toArray();
            $updates = ['status' => $target instanceof PlayerPost ? 'closed' : 'hidden', 'status_reason' => $reason];
            if ($target instanceof CommunityPost || $target instanceof CommunityPostComment || $target instanceof VenuePost) {
                $updates['reviewed_by'] = $request->user()->id;
                $updates['reviewed_at'] = now();
            }
            $target->forceFill($updates)->save();
            $this->audit->log($request, 'report', 'content.hidden', $target->getTable(), $target->getKey(), $old, $target->fresh()->toArray(), ['reason' => $reason, 'severity' => 'warning']);
            return;
        }

        if ($action === 'account_locked') {
            $user = $target instanceof User ? $target : $this->targetOwner($target);
            if (! $user) {
                throw ValidationException::withMessages(['action_taken' => 'Không xác định được tài khoản cần khóa.']);
            }

            $old = $user->only(['status', 'lock_type', 'status_reason', 'locked_at', 'locked_until', 'locked_by']);
            $user->forceFill([
                'status' => 'locked',
                'lock_type' => $lockDays ? 'temporary' : 'permanent',
                'status_reason' => $reason,
                'locked_at' => now(),
                'locked_until' => $lockDays ? now()->addDays($lockDays) : null,
                'locked_by' => $request->user()->id,
            ])->save();
            $user->tokens()->delete();
            $this->audit->log($request, 'report', 'user.locked_by_report', 'users', $user->id, $old, $user->fresh()->toArray(), ['reason' => $reason, 'severity' => 'warning']);
            return;
        }

        $venue = $target instanceof VenueCluster
            ? $target
            : ($target instanceof VenuePost ? $target->venueCluster : null);

        if (! $venue) {
            throw ValidationException::withMessages(['action_taken' => 'Không xác định được cụm sân cần khóa.']);
        }

        $old = $venue->only(['status', 'status_reason', 'locked_at', 'locked_until', 'locked_by']);
        $venue->forceFill([
            'status' => 'locked',
            'status_reason' => $reason,
            'locked_at' => now(),
            'locked_until' => $lockDays ? now()->addDays($lockDays) : null,
            'locked_by' => $request->user()->id,
        ])->save();
        $this->audit->log($request, 'report', 'venue.locked_by_report', 'venue_clusters', $venue->id, $old, $venue->fresh()->toArray(), ['reason' => $reason, 'severity' => 'warning']);
    }

    private function penaltyTarget(Report $report): array
    {
        $target = $report->reportable;

        if ($target instanceof VenueCluster || $target instanceof VenuePost) {
            $venue = $target instanceof VenueCluster ? $target : $target->venueCluster;
            if ($venue) {
                return ['venue_cluster', (string) $venue->id];
            }
        }

        $user = $target instanceof User ? $target : $this->targetOwner($target);
        if ($user) {
            return ['user', (string) $user->id];
        }

        throw ValidationException::withMessages(['report' => 'Không xác định được đối tượng để áp dụng hình phạt.']);
    }

    private function legacyActionFromPenalty(string $actionType): string
    {
        return match ($actionType) {
            'hide_content' => 'content_hidden',
            'delete_content' => 'content_deleted',
            'lock_temp', 'lock_permanent' => 'account_locked',
            'limit_venue', 'block_venue', 'terminate_contract' => 'venue_locked',
            default => 'warning',
        };
    }

    private function listPayload(Report $report): array
    {
        $target = $this->resolveTarget($report);

        return [
            'id' => $report->id,
            'reason' => $report->reason,
            'description' => $report->description,
            'status' => $report->status,
            'action_taken' => $report->action_taken,
            'target_type' => $this->targetAlias($report->reportable_type),
            'target_label' => $target ? $this->targetLabel($target) : 'Đối tượng không còn tồn tại',
            'reporter' => $this->userPayload($report->reporter),
            'reviewed_by' => $this->userPayload($report->reviewedBy),
            'reviewed_at' => $report->reviewed_at,
            'created_at' => $report->created_at,
        ];
    }

    private function detailPayload(Report $report): array
    {
        $target = $report->reportable ?: $this->resolveTarget($report);
        $owner = $this->targetOwner($target);
        $scoreTargetType = $this->violationScores->normalizeTargetType($report->reportable_type);
        $currentScore = $this->violationScores->getAccumulatedScore($scoreTargetType, (string) $report->reportable_id);
        $suggestedPenalty = null;
        try {
            [$penaltyTargetType, $penaltyTargetId] = $this->penaltyTarget($report);
            $suggestedPenalty = $this->penalties->getSuggestedPenalty($penaltyTargetType);
        } catch (ValidationException) {
            $suggestedPenalty = null;
        }

        return [
            ...$this->listPayload($report),
            'action_note' => $report->action_note,
            'violation_type' => $report->violationType ? [
                'id' => $report->violationType->id,
                'code' => $report->violationType->code,
                'name' => $report->violationType->name,
                'base_score' => $report->violationType->base_score,
                'is_immediate' => $report->violationType->is_immediate,
            ] : null,
            'severity_level' => $report->severity_level,
            'score_contribution' => $report->score_contribution,
            'accumulated_score' => $currentScore,
            'auto_action_taken' => $report->auto_action_taken,
            'auto_actioned_at' => $report->auto_actioned_at,
            'suggested_penalty' => $suggestedPenalty ? [
                'action_type' => $suggestedPenalty->action_type,
                'duration_days' => $suggestedPenalty->duration_days,
                'violation_count' => $suggestedPenalty->violation_count,
                'is_catch_all' => $suggestedPenalty->is_catch_all,
            ] : null,
            'target' => $this->targetPayload($target),
            'reported_user' => $this->userPayload($owner),
            'available_actions' => $this->availableActions($target),
            'evidence' => $report->evidence->map(fn ($media): array => [
                'id' => $media->id,
                'file_name' => $media->file_name,
                'file_path' => $media->file_path,
                'mime_type' => $media->mime_type,
                'file_size' => $media->file_size,
            ])->values(),
        ];
    }

    private function targetPayload(?Model $target): ?array
    {
        if (! $target) {
            return null;
        }

        $payload = [
            'id' => $target->getKey(),
            'type' => $this->targetAlias($target::class),
            'label' => $this->targetLabel($target),
            'status' => $target->status ?? null,
        ];

        if ($this->isContent($target)) {
            $payload['content'] = $target->content ?? $target->description ?? null;
            $payload['title'] = $target->title ?? null;
        }

        if ($target instanceof User) {
            $payload['email'] = $target->email;
            $payload['username'] = $target->username;
        }

        if ($target instanceof VenueCluster) {
            $payload['address'] = $target->address;
        }

        return $payload;
    }

    private function resolveTarget(Report $report): ?Model
    {
        $class = $report->reportable_type;
        return class_exists($class) && is_subclass_of($class, Model::class)
            ? $class::query()->find($report->reportable_id)
            : null;
    }

    private function targetOwner(?Model $target): ?User
    {
        return match (true) {
            $target instanceof User => $target,
            $target instanceof CommunityPost, $target instanceof VenuePost, $target instanceof PlayerPost => $target->author,
            $target instanceof CommunityPostComment => $target->user,
            $target instanceof VenueCluster => $target->owner,
            default => null,
        };
    }

    private function targetLabel(Model $target): string
    {
        return match (true) {
            $target instanceof User => $target->full_name ?: $target->username,
            $target instanceof VenueCluster => $target->name,
            $target instanceof PlayerPost => $target->title,
            $target instanceof CommunityPostComment => 'Bình luận: '.mb_strimwidth($target->content, 0, 70, '...'),
            default => mb_strimwidth((string) ($target->content ?? $target->getKey()), 0, 80, '...'),
        };
    }

    private function isContent(Model $target): bool
    {
        return $target instanceof CommunityPost
            || $target instanceof CommunityPostComment
            || $target instanceof VenuePost
            || $target instanceof PlayerPost;
    }

    private function availableActions(?Model $target): array
    {
        $actions = ['warning'];
        if ($target && $this->isContent($target)) {
            array_push($actions, 'content_hidden', 'content_deleted');
        }
        if ($target && $this->targetOwner($target)) {
            $actions[] = 'account_locked';
        }
        if ($target instanceof VenueCluster || $target instanceof VenuePost) {
            $actions[] = 'venue_locked';
        }
        return $actions;
    }

    private function targetAlias(string $class): string
    {
        return array_search($class, self::TARGET_TYPES, true) ?: class_basename($class);
    }

    private function userPayload(?User $user): ?array
    {
        return $user ? [
            'id' => $user->id,
            'full_name' => $user->full_name,
            'username' => $user->username,
            'email' => $user->email,
        ] : null;
    }

    private function auditLogs(Report $report)
    {
        if (! Schema::hasTable('audit_logs')) {
            return [];
        }

        return AuditLog::query()
            ->where(function ($query) use ($report): void {
                $query->where(fn ($inner) => $inner->where('entity_type', 'reports')->where('entity_id', $report->id))
                    ->orWhere(fn ($inner) => $inner->where('entity_type', $this->targetTable($report->reportable_type))->where('entity_id', $report->reportable_id));
            })
            ->with('actor:id,full_name,username')
            ->latest()
            ->limit(50)
            ->get();
    }

    private function targetTable(string $class): string
    {
        return class_exists($class) ? (new $class())->getTable() : $class;
    }

    private function notifyReportUsers(Report $report, ?User $targetOwner, string $decision, string $note): void
    {
        if (! Schema::hasTable('notifications')) {
            return;
        }

        $recipients = collect([$report->reporter, $targetOwner])->filter()->unique('id');
        foreach ($recipients as $user) {
            Notification::query()->create([
                'user_id' => $user->id,
                'type' => 'report_processed',
                'title' => $decision === 'resolved' ? 'Báo cáo đã được xử lý' : 'Báo cáo đã được xem xét',
                'body' => $note,
                'reference_type' => Report::class,
                'reference_id' => $report->id,
                'data' => ['status' => $decision, 'action_taken' => $report->action_taken],
                'is_read' => false,
            ]);
        }
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
