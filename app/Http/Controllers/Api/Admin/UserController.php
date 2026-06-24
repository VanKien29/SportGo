<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\User;
use App\Models\UserPermissionRevoke;
use App\Services\Admin\AdminAuditService;
use App\Services\Auth\RoleRedirectService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class UserController extends Controller
{
    private const STAFF_ROLES = [
        'super_admin',
        'admin',
        'system_staff',
        'content_moderator',
        'complaint_handler',
        'venue_manager',
        'partner_manager',
        'booking_support',
        'finance_operator',
        'policy_manager',
        'staff_manager',
    ];

    public function __construct(
        private readonly RoleRedirectService $roleRedirectService,
        private readonly AdminAuditService $audit
    ) {
    }

    public function index(Request $request): JsonResponse
    {
        $request->validate([
            'keyword' => ['nullable', 'string', 'max:255'],
            'status' => ['nullable', Rule::in(['active', 'locked', 'pending_verify', 'deactivated', 'warning'])],
            'role' => ['nullable', Rule::in(['super_admin', 'admin', 'system_staff', 'venue_owner', 'venue_staff', 'user'])],
            'warning_level' => ['nullable', Rule::in(['watch', 'near_lock', 'lock_suggested'])],
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:5', 'max:50'],
            'role_group' => ['nullable', 'string', Rule::in(['staff', 'customer', 'owner'])],
        ]);

        $warningUserIds = $request->query('status') === 'warning' || $request->filled('warning_level')
            ? $this->warningUserIds($request->query('warning_level'))
            : null;

        $paginatedUsers = User::query()
            ->with('roles:id,name,display_name')
            ->when($request->filled('keyword'), function ($query) use ($request): void {
                $keyword = '%' . $request->query('keyword') . '%';
                $query->where(function ($inner) use ($keyword): void {
                    $inner->where('username', 'like', $keyword)
                        ->orWhere('full_name', 'like', $keyword)
                        ->orWhere('email', 'like', $keyword)
                        ->orWhere('phone', 'like', $keyword);
                });
            })
            ->when($request->filled('status') && $request->query('status') !== 'warning', fn ($query) => $query->where('status', $request->query('status')))
            ->when($request->filled('role'), fn ($query) => $query->whereHas('roles', fn ($roleQuery) => $roleQuery->where('name', $request->query('role'))))
            ->when($request->filled('role_group'), function ($query) use ($request): void {
                if ($request->query('role_group') === 'staff') {
                    $query->whereHas('roles', fn ($roleQuery) => $roleQuery->whereIn('name', self::STAFF_ROLES));
                } elseif ($request->query('role_group') === 'owner') {
                    $query->whereHas('roles', fn ($roleQuery) => $roleQuery->whereIn('name', ['venue_owner', 'venue_staff']));
                } elseif ($request->query('role_group') === 'customer') {
                    $query->whereHas('roles', fn ($roleQuery) => $roleQuery->where('name', 'user'));
                }
            })
            ->when(is_array($warningUserIds), fn ($query) => $query->whereIn('id', $warningUserIds))
            ->latest()
            ->paginate((int) $request->integer('per_page', 15));

        $pageUsers = $paginatedUsers->getCollection();
        $userIds = $pageUsers->pluck('id')->all();
        $reportCounts = $this->reportCountsForUsers($userIds);
        $complaintCounts = $this->complaintCountsForUsers($userIds);
        $walletBalances = $this->walletBalancesForUsers($userIds);

        $paginatedUsers->setCollection(
            $pageUsers->map(function (User $user) use ($reportCounts, $complaintCounts, $walletBalances): array {
                $reports = $reportCounts[$user->id] ?? 0;
                $complaints = $complaintCounts[$user->id] ?? 0;

                return $this->payload($user, [
                    'reports_count_recent' => $reports,
                    'complaints_count_recent' => $complaints,
                    'warning_count' => $reports + $complaints,
                    'wallet_balance' => $walletBalances[$user->id] ?? 0,
                ]);
            })
        );

        return response()->json([
            'data' => $paginatedUsers->items(),
            'summary' => $this->accountSummary(),
            'meta' => [
                'current_page' => $paginatedUsers->currentPage(),
                'last_page' => $paginatedUsers->lastPage(),
                'per_page' => $paginatedUsers->perPage(),
                'total' => $paginatedUsers->total(),
            ],
        ]);
    }

    private function accountSummary(): array
    {
        return [
            'total' => User::query()->count(),
            'active' => User::query()->where('status', 'active')->count(),
            'warning' => count($this->warningUserIds()),
            'locked' => User::query()->where('status', 'locked')->count(),
            'pending_verify' => User::query()->where('status', 'pending_verify')->count(),
        ];
    }

    public function autoLockConfig(Request $request): JsonResponse
    {
        $this->authorizePermission($request, 'user.view');
        
        $activePolicy = \App\Models\SystemPolicy::where('policy_type', 'moderation')->where('status', 'active')->first();
        $rules = $activePolicy ? $activePolicy->rules()->get() : collect();

        $warnThreshold = 3;
        $lockThreshold = 10;
        $windowDays = 7;
        $uniqueReportersThreshold = 3;

        $warnRule = $rules->firstWhere('rule_code', 'user_report_warning_threshold');
        $lockRule = $rules->firstWhere('rule_code', 'user_report_lock_threshold');

        if ($activePolicy) {
            $userThreshold = \App\Models\ModerationThreshold::where('system_policy_id', $activePolicy->id)
                ->where('target_type', 'user')
                ->first();
                
            if ($userThreshold) {
                $warnThreshold = $userThreshold->warning_threshold;
                $lockThreshold = $userThreshold->action_threshold;
                $windowDays = $userThreshold->timeframe_days;
                $uniqueReportersThreshold = $userThreshold->unique_reporters_threshold;
            } else {
                if ($warnRule) {
                    $c = $warnRule->condition_json ?? [];
                    $warnThreshold = $c['threshold'] ?? $warnThreshold;
                    $windowDays = $c['window_days'] ?? $windowDays;
                }

                if ($lockRule) {
                    $c = $lockRule->condition_json ?? [];
                    $lockThreshold = $c['threshold'] ?? $lockThreshold;
                    $windowDays = $c['window_days'] ?? $windowDays;
                    $uniqueReportersThreshold = $c['threshold'] ?? $lockThreshold;
                }
            }
        }

        $actionType = 'lock_temp';
        $durationDays = 7;
        $autoLockReason = 'Vi phạm tiêu chuẩn cộng đồng';
        $isAutoLockEnabled = false;

        if ($lockRule) {
            $r = $lockRule->result_json ?? [];
            $actionType = 'lock_temp';
            $durationDays = $r['lock_duration_days'] ?? 7;
            $autoLockReason = $r['reason'] ?? 'Vi phạm tiêu chuẩn cộng đồng';
            $isAutoLockEnabled = $r['is_auto_lock_enabled'] ?? false;
        } else {
            // Fallback to legacy rule search
            foreach ($rules as $rule) {
                $c = $rule->condition_json ?? [];
                if (in_array($c['reportable_type'] ?? '', ['user', 'users']) || ($c['target_type'] ?? '') === 'user') {
                    $r = $rule->result_json ?? [];
                    if (in_array($r['action'] ?? '', ['auto_lock', 'lock_temp', 'lock_permanent'])) {
                        $actionType = 'lock_temp';
                        $durationDays = $r['lock_duration_days'] ?? 7;
                        $autoLockReason = $r['reason'] ?? 'Vi phạm tiêu chuẩn cộng đồng';
                        $isAutoLockEnabled = $r['is_auto_lock_enabled'] ?? false;
                    }
                }
            }
        }

        return response()->json([
            'data' => [
                'policy_id' => $activePolicy?->id,
                'warning_threshold' => $warnThreshold,
                'lock_threshold' => $lockThreshold,
                'unique_reporters_threshold' => $uniqueReportersThreshold ?? $lockThreshold,
                'window_days' => $windowDays,
                'action_type' => $actionType,
                'duration_days' => $durationDays,
                'reason' => $autoLockReason,
                'is_auto_lock_enabled' => $isAutoLockEnabled,
            ]
        ]);
    }

    public function saveAutoLockConfig(Request $request): JsonResponse
    {
        $this->authorizePermission($request, 'policy.rule.manage');

        $data = $request->validate([
            'is_auto_lock_enabled' => ['required', 'boolean'],
            'duration_days' => ['required', 'integer', 'min:1'],
            'reason' => ['required', 'string', 'max:255'],
        ]);

        $activePolicy = \App\Models\SystemPolicy::where('policy_type', 'moderation')->where('status', 'active')->first();
        if (!$activePolicy) {
            throw ValidationException::withMessages(['policy' => 'Không có chính sách kiểm duyệt nào đang Active.']);
        }

        $rules = $activePolicy->rules()->get();
        
        // Target user_report_lock_threshold first
        $actionRule = $rules->firstWhere('rule_code', 'user_report_lock_threshold');

        if (!$actionRule) {
            $actionRule = $rules->firstWhere('rule_code', 'moderation_score_user');
        }

        if (!$actionRule) {
            // Try to find legacy rule
            foreach ($rules as $rule) {
                $c = $rule->condition_json ?? [];
                if (in_array($c['reportable_type'] ?? '', ['user', 'users'])) {
                    $r = $rule->result_json ?? [];
                    if (in_array($r['action'] ?? '', ['auto_lock', 'lock_temp', 'lock_permanent'])) {
                        $actionRule = $rule;
                        break;
                    }
                }
            }
        }

        if ($actionRule) {
            $r = $actionRule->result_json ?? [];
            $r['lock_duration_days'] = $data['duration_days'];
            $r['reason'] = $data['reason'];
            $r['is_auto_lock_enabled'] = $data['is_auto_lock_enabled'];
            $actionRule->update([
                'result_json' => $r,
            ]);

            if ($data['is_auto_lock_enabled']) {
                $lockThreshold = $actionRule->condition_json['threshold'] ?? 10;
                $windowDays = $actionRule->condition_json['window_days'] ?? 7;

                $adminRoleIds = DB::table('roles')
                    ->whereIn('name', [
                        'super_admin', 'admin', 'system_staff', 'content_moderator',
                        'complaint_handler', 'venue_manager', 'partner_manager',
                        'booking_support', 'finance_operator', 'policy_manager', 'staff_manager'
                    ])
                    ->pluck('id');

                $adminUserIds = DB::table('user_roles')
                    ->whereIn('role_id', $adminRoleIds)
                    ->pluck('user_id')
                    ->all();

                $exceededUserIds = DB::table('reports')
                    ->whereIn('reportable_type', ['users', 'user', User::class])
                    ->whereNotIn('reportable_id', $adminUserIds)
                    ->whereNotIn('status', ['dismissed', 'resolved'])
                    ->where('created_at', '>=', now()->subDays($windowDays))
                    ->select('reportable_id', DB::raw('COUNT(DISTINCT reporter_id) as total'))
                    ->groupBy('reportable_id')
                    ->having('total', '>=', $lockThreshold)
                    ->pluck('reportable_id')
                    ->all();

                foreach ($exceededUserIds as $userId) {
                    $user = User::query()->find($userId);
                    if ($user && $user->status === 'active') {
                        $user->forceFill([
                            'status' => 'locked',
                            'lock_type' => 'auto',
                            'status_reason' => $data['reason'],
                            'locked_at' => now(),
                            'locked_until' => now()->addDays($data['duration_days']),
                            'locked_by' => $request->user()?->id,
                        ])->save();

                        if (class_exists(\App\Models\UserLockLog::class)) {
                            \App\Models\UserLockLog::create([
                                'user_id' => $user->id,
                                'action' => 'locked',
                                'reason' => $data['reason'],
                                'locked_by' => $request->user()?->id,
                                'auto_triggered' => true,
                                'lock_until' => now()->addDays($data['duration_days']),
                                'created_at' => now(),
                            ]);
                        }
                    }
                }
            }
        }

        return response()->json(['message' => 'Lưu cấu hình thành công.']);
    }

    public function show(string $id): JsonResponse
    {
        $user = User::query()
            ->with(['roles:id,name,display_name', 'lockedBy:id,username,full_name'])
            ->findOrFail($id);

        return response()->json([
            'data' => [
                'profile' => $this->payload($user),
                'status_summary' => $this->statusSummary($user),
                'role_summary' => [
                    'roles' => $this->roleDetails($user),
                    'primary_role_label' => $this->primaryRoleLabel($user->roles->pluck('name')->all()),
                ],
                'roles' => $this->roleDetails($user),
                'permission_revokes' => $this->permissionRevokes($user->id),
                'permission_summary' => [
                    'revoked_count' => count($this->permissionRevokes($user->id)),
                    'revokes' => $this->permissionRevokes($user->id),
                ],
                'warning_summary' => $this->warningSummary($user->id),
                'reports_summary' => $this->reportSummary($user->id),
                'complaints_summary' => $this->complaintSummary($user->id),
                'wallet_summary' => $this->walletSummary($user->id),
                'booking_summary' => $this->bookingSummary($user->id),
                'recent_bookings' => $this->bookingHistory($user->id),
                'audit_logs' => $this->auditLogs($user->id),
                'posts' => $this->userPosts($user->id),
                'comments' => $this->userComments($user->id),
                'content_reports_summary' => $this->contentReportSummary($user->id),
            ],
        ]);
    }

    private function userPosts(string $userId)
    {
        if (! Schema::hasTable('community_posts')) {
            return [];
        }
        return \App\Models\CommunityPost::query()
            ->with('media')
            ->where('author_id', $userId)
            ->latest('created_at')
            ->limit(100)
            ->get()
            ->map(fn ($post) => [
                'id' => $post->id,
                'content' => $post->content,
                'status' => $post->status,
                'comment_count' => $post->comment_count ?? 0,
                'like_count' => $post->like_count ?? 0,
                'created_at' => $post->created_at,
                'media' => $post->media->map(fn ($m) => [
                    'id' => $m->id,
                    'url' => str_starts_with($m->file_path, 'http') ? $m->file_path : \Illuminate\Support\Facades\Storage::url($m->file_path),
                    'mime_type' => $m->mime_type,
                ]),
            ])
            ->all();
    }

    private function userComments(string $userId)
    {
        if (! Schema::hasTable('community_post_comments')) {
            return [];
        }
        return \App\Models\CommunityPostComment::query()
            ->with(['post:id,content', 'media'])
            ->where('user_id', $userId)
            ->latest('created_at')
            ->limit(100)
            ->get()
            ->map(fn ($comment) => [
                'id' => $comment->id,
                'content' => $comment->content,
                'post_content' => $comment->post?->content,
                'replies_count' => $comment->replies()->count(),
                'created_at' => $comment->created_at,
                'media' => $comment->media->map(fn ($m) => [
                    'id' => $m->id,
                    'url' => str_starts_with($m->file_path, 'http') ? $m->file_path : \Illuminate\Support\Facades\Storage::url($m->file_path),
                    'mime_type' => $m->mime_type,
                ]),
            ])
            ->all();
    }

    private function contentReportSummary(string $userId)
    {
        if (! Schema::hasTable('reports')) {
            return ['total_post_reports' => 0, 'total_comment_reports' => 0, 'recent' => []];
        }
        $postIds = Schema::hasTable('community_posts') ? \App\Models\CommunityPost::query()->where('author_id', $userId)->pluck('id') : collect();
        $commentIds = Schema::hasTable('community_post_comments') ? \App\Models\CommunityPostComment::query()->where('user_id', $userId)->pluck('id') : collect();
        
        $postReports = \App\Models\Report::query()
            ->where('reportable_type', \App\Models\CommunityPost::class)
            ->whereIn('reportable_id', $postIds)
            ->latest('created_at')
            ->limit(20)
            ->get()
            ->toBase()
            ->map(fn ($r) => [
                'id' => $r->id,
                'type' => 'post',
                'target_id' => $r->reportable_id,
                'reason' => $this->reportReasonLabel($r->reason ?? null),
                'description' => $r->description,
                'status' => $r->status,
                'status_label' => $this->reportStatusLabel($r->status ?? null),
                'created_at' => $r->created_at,
            ]);
            
        $commentReports = \App\Models\Report::query()
            ->where('reportable_type', \App\Models\CommunityPostComment::class)
            ->whereIn('reportable_id', $commentIds)
            ->latest('created_at')
            ->limit(20)
            ->get()
            ->toBase()
            ->map(fn ($r) => [
                'id' => $r->id,
                'type' => 'comment',
                'target_id' => $r->reportable_id,
                'reason' => $this->reportReasonLabel($r->reason ?? null),
                'description' => $r->description,
                'status' => $r->status,
                'status_label' => $this->reportStatusLabel($r->status ?? null),
                'created_at' => $r->created_at,
            ]);
            
        return [
            'total_post_reports' => \App\Models\Report::query()->where('reportable_type', \App\Models\CommunityPost::class)->whereIn('reportable_id', $postIds)->whereNotIn('status', ['dismissed', 'resolved'])->count(),
            'total_comment_reports' => \App\Models\Report::query()->where('reportable_type', \App\Models\CommunityPostComment::class)->whereIn('reportable_id', $commentIds)->whereNotIn('status', ['dismissed', 'resolved'])->count(),
            'recent' => $postReports->merge($commentReports)->sortByDesc('created_at')->take(20)->values()->all(),
        ];
    }

    public function lock(Request $request, string $id): JsonResponse
    {
        $data = $request->validate([
            'lock_type' => ['required', Rule::in(['temporary', 'permanent', 'auto'])],
            'status_reason' => ['required', 'string', 'max:2000'],
            'locked_until' => ['nullable', 'date', 'after:now', 'required_if:lock_type,temporary'],
        ], [
            'lock_type.required' => 'Vui lòng chọn loại khóa.',
            'lock_type.in' => 'Loại khóa không hợp lệ.',
            'status_reason.required' => 'Vui lòng nhập lý do khóa.',
            'locked_until.required_if' => 'Vui lòng nhập thời hạn khóa tạm thời.',
            'locked_until.after' => 'Thời hạn khóa phải lớn hơn thời điểm hiện tại.',
        ]);

        /** @var User $actor */
        $actor = $request->user();
        $user = User::query()->with('roles:id,name')->findOrFail($id);

        if ($actor->id === $user->id) {
            throw ValidationException::withMessages([
                'user' => 'Không thể tự khóa tài khoản đang đăng nhập.',
            ]);
        }

        if ($this->hasRole($user, 'super_admin') && ! $this->hasRole($actor, 'super_admin')) {
            throw ValidationException::withMessages([
                'user' => 'Chỉ super admin mới được khóa tài khoản super admin.',
            ]);
        }

        $oldValues = $this->lockSnapshot($user);

        $user->forceFill([
            'status' => 'locked',
            'lock_type' => $data['lock_type'],
            'status_reason' => $data['status_reason'],
            'locked_at' => now(),
            'locked_until' => $data['locked_until'] ?? null,
            'locked_by' => $actor->id,
        ])->save();

        if (class_exists(\App\Models\UserLockLog::class)) {
            \App\Models\UserLockLog::create([
                'user_id' => $user->id,
                'action' => 'locked',
                'reason' => $data['status_reason'],
                'locked_by' => $actor->id,
                'auto_triggered' => false,
                'lock_until' => $data['locked_until'] ?? null,
                'created_at' => now(),
            ]);
        }

        $user->tokens()->delete();
        $this->audit($request, $actor, 'user.locked', $user, $oldValues, $this->lockSnapshot($user), $data['status_reason']);

        return response()->json([
            'message' => 'Khóa tài khoản thành công.',
            'user' => $this->payload($user->fresh(['roles', 'lockedBy'])),
        ]);
    }

    public function unlock(Request $request, string $id): JsonResponse
    {
        $data = $request->validate([
            'reason' => ['required', 'string', 'max:2000'],
        ], [
            'reason.required' => 'Vui lòng nhập lý do mở khóa.',
        ]);

        /** @var User $actor */
        $actor = $request->user();
        $user = User::query()->with('roles:id,name')->findOrFail($id);

        if ($this->hasRole($user, 'super_admin') && ! $this->hasRole($actor, 'super_admin')) {
            throw ValidationException::withMessages([
                'user' => 'Chỉ super admin mới được mở khóa tài khoản super admin.',
            ]);
        }

        $oldValues = $this->lockSnapshot($user);

        $user->forceFill([
            'status' => 'active',
            'lock_type' => null,
            'status_reason' => null,
            'locked_at' => null,
            'locked_until' => null,
            'locked_by' => null,
        ])->save();

        if (class_exists(\App\Models\UserLockLog::class)) {
            \App\Models\UserLockLog::create([
                'user_id' => $user->id,
                'action' => 'unlocked',
                'reason' => $data['reason'],
                'locked_by' => $actor->id,
                'auto_triggered' => false,
                'lock_until' => null,
                'created_at' => now(),
            ]);
        }

        $this->audit($request, $actor, 'user.unlocked', $user, $oldValues, $this->lockSnapshot($user), $data['reason']);

        return response()->json([
            'message' => 'Mở khóa tài khoản thành công.',
            'user' => $this->payload($user->fresh(['roles', 'lockedBy'])),
        ]);
    }

    private function payload(User $user, array $extras = []): array
    {
        if (! $user->relationLoaded('roles')) {
            $user->load('roles:id,name,display_name');
        }

        $roles = $user->roles->pluck('name')->values()->all();
        $isAdmin = $user->role_group === 'admin';

        $reports = $isAdmin ? 0 : ($extras['reports_count_recent'] ?? $this->reportSummary($user->id)['reports_14_days']);
        $complaints = $isAdmin ? 0 : ($extras['complaints_count_recent'] ?? $this->complaintSummary($user->id)['open_count']);
        $warningCount = $isAdmin ? 0 : ($extras['warning_count'] ?? ($reports + $complaints));

        return [
            'id' => $user->id,
            'username' => $user->username,
            'full_name' => $user->full_name,
            'email' => $user->email,
            'phone' => $user->phone,
            'avatar_url' => $user->avatar_url,
            'status' => $user->status,
            'status_label' => $this->statusLabel($user->status),
            'status_reason' => $user->status_reason,
            'lock_type' => $user->lock_type,
            'locked_at' => $user->locked_at,
            'locked_until' => $user->locked_until,
            'locked_by' => $user->locked_by,
            'locked_by_name' => $user->relationLoaded('lockedBy') ? ($user->lockedBy?->full_name ?: $user->lockedBy?->username) : null,
            'roles' => $roles,
            'role_labels' => array_map(fn (string $role): string => $this->roleLabel($role), $roles),
            'primary_role_label' => $this->primaryRoleLabel($roles),
            'role_group' => $this->roleRedirectService->roleGroup($roles),
            'reports_count_recent' => $reports,
            'complaints_count_recent' => $complaints,
            'warning_count' => $warningCount,
            'warning_summary' => $this->warningLevelText($reports, $complaints),
            'warning_level' => $this->warningLevelText($reports, $complaints)['level'],
            'warning_label' => $this->warningLevelText($reports, $complaints)['label'],
            'wallet_balance' => $extras['wallet_balance'] ?? ($this->walletSummary($user->id)['balance'] ?? 0),
            'wallet_balance_formatted' => $this->money($extras['wallet_balance'] ?? ($this->walletSummary($user->id)['balance'] ?? 0)),
            'actions_allowed' => [
                'view_detail' => true,
                'lock' => $user->status !== 'locked',
                'unlock' => $user->status === 'locked',
                'revoke_token' => true,
            ],
            'created_at' => $user->created_at,
            'updated_at' => $user->updated_at,
        ];
    }

    private function activeUserModerationConfig(): array
    {
        $activePolicy = \App\Models\SystemPolicy::where('policy_type', 'moderation')->where('status', 'active')->first();
        $warnThreshold = 3;
        $lockThreshold = 10;
        $windowDays = 7;

        if ($activePolicy) {
            $userThreshold = \App\Models\ModerationThreshold::where('system_policy_id', $activePolicy->id)
                ->where('target_type', 'user')
                ->first();
                
            if ($userThreshold) {
                $warnThreshold = $userThreshold->warning_threshold;
                $lockThreshold = $userThreshold->action_threshold;
                $windowDays = $userThreshold->timeframe_days;
            } else {
                $warnRule = $activePolicy->rules()->where('rule_code', 'user_report_warning_threshold')->first();
                if ($warnRule) {
                    $warnThreshold = $warnRule->condition_json['threshold'] ?? $warnThreshold;
                    $windowDays = $warnRule->condition_json['window_days'] ?? $windowDays;
                }

                $lockRule = $activePolicy->rules()->where('rule_code', 'user_report_lock_threshold')->first();
                if ($lockRule) {
                    $lockThreshold = $lockRule->condition_json['threshold'] ?? $lockThreshold;
                    $windowDays = $lockRule->condition_json['window_days'] ?? $windowDays;
                }
            }
        }
        return [$warnThreshold, $lockThreshold, $windowDays];
    }

    private function reportCountsForUsers(array $userIds): array
    {
        if (! Schema::hasTable('reports') || $userIds === []) {
            return [];
        }

        [$warn, $lock, $windowDays] = $this->activeUserModerationConfig();

        return DB::table('reports')
            ->whereIn('reportable_type', ['users', 'user', User::class])
            ->whereIn('reportable_id', $userIds)
            ->whereNotIn('status', ['dismissed', 'resolved'])
            ->where('created_at', '>=', now()->subDays($windowDays))
            ->select('reportable_id', DB::raw('COUNT(DISTINCT reporter_id) as total'))
            ->groupBy('reportable_id')
            ->pluck('total', 'reportable_id')
            ->map(fn ($value) => (int) $value)
            ->all();
    }

    private function complaintCountsForUsers(array $userIds): array
    {
        if (! Schema::hasTable('complaints') || $userIds === []) {
            return [];
        }

        return DB::table('complaints')
            ->whereIn('customer_id', $userIds)
            ->whereIn('status', ['open', 'processing'])
            ->select('customer_id', DB::raw('COUNT(*) as total'))
            ->groupBy('customer_id')
            ->pluck('total', 'customer_id')
            ->map(fn ($value) => (int) $value)
            ->all();
    }

    private function walletBalancesForUsers(array $userIds): array
    {
        if (! Schema::hasTable('user_wallets') || $userIds === []) {
            return [];
        }

        return DB::table('user_wallets')
            ->whereIn('user_id', $userIds)
            ->pluck('balance', 'user_id')
            ->map(fn ($value) => (float) $value)
            ->all();
    }

    private function warningUserIds(?string $level = null): array
    {
        $ids = collect();

        // Get admin user IDs to exclude them from warnings
        $adminRoleIds = DB::table('roles')
            ->whereIn('name', [
                'super_admin',
                'admin',
                'system_staff',
                'content_moderator',
                'complaint_handler',
                'venue_manager',
                'partner_manager',
                'booking_support',
                'finance_operator',
                'policy_manager',
                'staff_manager',
            ])
            ->pluck('id');

        $adminUserIds = DB::table('user_roles')
            ->whereIn('role_id', $adminRoleIds)
            ->pluck('user_id')
            ->all();

        if (Schema::hasTable('reports')) {
            [$warnThreshold, $lockThreshold, $windowDays] = $this->activeUserModerationConfig();

            $query = DB::table('reports')
                ->whereIn('reportable_type', ['users', 'user', User::class])
                ->whereNotIn('reportable_id', $adminUserIds)
                ->whereNotIn('status', ['dismissed', 'resolved'])
                ->where('created_at', '>=', now()->subDays($windowDays))
                ->select('reportable_id', DB::raw('COUNT(DISTINCT reporter_id) as total'))
                ->groupBy('reportable_id');

            if ($level === 'near_lock') {
                $query->having('total', '>=', $warnThreshold)->having('total', '<', $lockThreshold);
            } elseif ($level === 'lock_suggested') {
                $query->having('total', '>=', $lockThreshold);
            } else {
                $query->having('total', '>=', 1);
            }

            $ids = $ids->merge($query->pluck('reportable_id'));
        }

        if (Schema::hasTable('complaints')) {
            $ids = $ids->merge(
                DB::table('complaints')
                    ->whereIn('status', ['open', 'processing'])
                    ->whereNotIn('customer_id', $adminUserIds)
                    ->pluck('customer_id')
            );
        }

        return $ids->filter()->unique()->values()->all();
    }

    private function roleDetails(User $user): array
    {
        return $user->roles->map(fn ($role): array => [
            'id' => $role->id,
            'name' => $role->name,
            'label' => $role->display_name ?: $this->roleLabel($role->name),
            'scope_label' => $this->scopeLabel($role->pivot?->scope_type, $role->pivot?->scope_id),
        ])->values()->all();
    }

    private function statusSummary(User $user): array
    {
        return [
            'status' => $user->status,
            'status_label' => $this->statusLabel($user->status),
            'reason' => $user->status_reason,
            'lock_type' => $user->lock_type,
            'lock_type_label' => $this->lockTypeLabel($user->lock_type),
            'locked_at' => $user->locked_at,
            'locked_until' => $user->locked_until,
            'locked_by_name' => $user->relationLoaded('lockedBy') ? ($user->lockedBy?->full_name ?: $user->lockedBy?->username) : null,
        ];
    }

    private function permissionRevokes(string $userId): array
    {
        if (! Schema::hasTable('user_permission_revokes')) {
            return [];
        }

        return UserPermissionRevoke::query()
            ->with(['permission:id,name,code', 'revokedBy:id,username,full_name'])
            ->where('user_id', $userId)
            ->latest('created_at')
            ->limit(20)
            ->get()
            ->map(fn (UserPermissionRevoke $revoke): array => [
                'id' => $revoke->id,
                'permission' => $revoke->permission?->name ?: $this->permissionLabel($revoke->permission?->code),
                'scope_label' => $this->scopeLabel($revoke->scope_type, $revoke->scope_id),
                'reason' => $revoke->reason,
                'revoked_by_name' => $revoke->revokedBy?->full_name ?: $revoke->revokedBy?->username,
                'created_at' => $revoke->created_at,
            ])
            ->all();
    }

    private function warningSummary(string $userId): array
    {
        $reports = $this->reportSummary($userId);
        $complaints = $this->complaintSummary($userId);

        return $this->warningLevelText((int) $reports['reports_14_days'], (int) $complaints['open_count']) + [
            'reports_7_days' => $reports['reports_7_days'],
            'reports_14_days' => $reports['reports_14_days'],
            'reports_30_days' => $reports['reports_30_days'],
            'complaints_open' => $complaints['open_count'],
        ];
    }

    private function reportSummary(string $userId): array
    {
        if (! Schema::hasTable('reports')) {
            return [
                'total' => 0,
                'reports_7_days' => 0,
                'reports_14_days' => 0,
                'reports_30_days' => 0,
                'near_lock_message' => 'Chưa có dữ liệu báo cáo.',
                'recent' => [],
            ];
        }

        $base = DB::table('reports')
            ->whereIn('reportable_type', ['users', 'user', User::class])
            ->where('reportable_id', $userId);
            
        $unresolvedBase = (clone $base)->whereNotIn('status', ['dismissed', 'resolved']);
        [$warn, $lock, $windowDays] = $this->activeUserModerationConfig();
        $reportsWindowDays = (clone $unresolvedBase)->where('created_at', '>=', now()->subDays($windowDays))->distinct('reporter_id')->count('reporter_id');

        return [
            'total' => (clone $unresolvedBase)->distinct('reporter_id')->count('reporter_id'),
            'reports_7_days' => (clone $unresolvedBase)->where('created_at', '>=', now()->subDays(7))->distinct('reporter_id')->count('reporter_id'),
            'reports_14_days' => $reportsWindowDays, // using this for UI legacy
            'reports_30_days' => (clone $unresolvedBase)->where('created_at', '>=', now()->subDays(30))->distinct('reporter_id')->count('reporter_id'),
            'near_lock_message' => $reportsWindowDays > 0
                ? "Tài khoản này có {$reportsWindowDays} người báo cáo trong {$windowDays} ngày gần đây."
                : "Tài khoản chưa có người báo cáo trong {$windowDays} ngày gần đây.",
            'recent' => (clone $base)->latest('created_at')->limit(10)->get()->map(fn ($report) => [
                'id' => $report->id,
                'reason' => $this->reportReasonLabel($report->reason ?? null),
                'status' => $report->status ?? null,
                'status_label' => $this->reportStatusLabel($report->status ?? null),
                'description' => $report->description ?? null,
                'created_at' => $report->created_at ?? null,
            ]),
        ];
    }

    private function complaintSummary(string $userId): array
    {
        if (! Schema::hasTable('complaints')) {
            return ['total' => 0, 'open_count' => 0, 'recent' => []];
        }

        $base = DB::table('complaints')->where('customer_id', $userId);

        return [
            'total' => (clone $base)->count(),
            'open_count' => (clone $base)->whereIn('status', ['open', 'processing'])->count(),
            'recent' => (clone $base)->latest('created_at')->limit(5)->get()->map(fn ($complaint) => [
                'id' => $complaint->id,
                'content' => $complaint->content ?? null,
                'status' => $complaint->status ?? null,
                'status_label' => $this->complaintStatusLabel($complaint->status ?? null),
                'created_at' => $complaint->created_at ?? null,
            ]),
        ];
    }

    private function walletSummary(string $userId): array
    {
        if (! Schema::hasTable('user_wallets')) {
            return ['balance' => 0, 'locked_balance' => 0, 'balance_formatted' => $this->money(0), 'locked_balance_formatted' => $this->money(0), 'status' => 'none', 'status_label' => 'Chưa có ví', 'ledgers' => []];
        }

        $wallet = DB::table('user_wallets')->where('user_id', $userId)->first();
        if (! $wallet) {
            return ['balance' => 0, 'locked_balance' => 0, 'balance_formatted' => $this->money(0), 'locked_balance_formatted' => $this->money(0), 'status' => 'none', 'status_label' => 'Chưa có ví', 'ledgers' => []];
        }

        $ledgers = Schema::hasTable('user_wallet_ledgers')
            ? DB::table('user_wallet_ledgers')->where('user_wallet_id', $wallet->id)->latest('created_at')->limit(10)->get()
            : collect();

        return [
            'balance' => (float) $wallet->balance,
            'locked_balance' => (float) $wallet->locked_balance,
            'balance_formatted' => $this->money($wallet->balance),
            'locked_balance_formatted' => $this->money($wallet->locked_balance),
            'status' => $wallet->status,
            'status_label' => $this->walletStatusLabel($wallet->status ?? null),
            'ledgers' => $ledgers->map(fn ($ledger) => [
                'id' => $ledger->id,
                'transaction_code' => $ledger->transaction_code ?? null,
                'type' => $ledger->type ?? null,
                'type_label' => $this->walletLedgerTypeLabel($ledger->type ?? null),
                'direction' => $ledger->direction ?? null,
                'amount' => (float) ($ledger->amount ?? 0),
                'amount_formatted' => $this->money($ledger->amount ?? 0),
                'balance_after' => (float) ($ledger->balance_after ?? 0),
                'balance_after_formatted' => $this->money($ledger->balance_after ?? 0),
                'status' => $ledger->status ?? null,
                'status_label' => $this->walletStatusLabel($ledger->status ?? null),
                'created_at' => $ledger->created_at ?? null,
            ]),
        ];
    }

    private function bookingSummary(string $userId): array
    {
        if (! Schema::hasTable('bookings')) {
            return ['total' => 0, 'completed' => 0, 'cancelled' => 0, 'paid_total' => 0];
        }

        $base = DB::table('bookings')->where('customer_id', $userId);

        return [
            'total' => (clone $base)->count(),
            'completed' => (clone $base)->where('status', 'completed')->count(),
            'cancelled' => (clone $base)->where('status', 'cancelled')->count(),
            'paid_total' => (float) (clone $base)->whereIn('status', ['confirmed', 'checked_in', 'completed'])->sum('total_price'),
            'paid_total_formatted' => $this->money((clone $base)->whereIn('status', ['confirmed', 'checked_in', 'completed'])->sum('total_price')),
        ];
    }

    private function bookingHistory(string $userId)
    {
        if (! Schema::hasTable('bookings')) {
            return collect();
        }

        return DB::table('bookings')
            ->leftJoin('venue_clusters', 'venue_clusters.id', '=', 'bookings.venue_cluster_id')
            ->leftJoin('payments', 'payments.booking_id', '=', 'bookings.id')
            ->where('bookings.customer_id', $userId)
            ->select([
                'bookings.id',
                'bookings.booking_code',
                'bookings.booking_date',
                'bookings.total_price',
                'bookings.status',
                'bookings.payment_option',
                'venue_clusters.name as venue_cluster_name',
                DB::raw('MAX(payments.status) as payment_status'),
            ])
            ->groupBy('bookings.id', 'bookings.booking_code', 'bookings.booking_date', 'bookings.total_price', 'bookings.status', 'bookings.payment_option', 'venue_clusters.name')
            ->latest('bookings.created_at')
            ->limit(10)
            ->get()
            ->map(fn ($booking) => [
                'id' => $booking->id,
                'booking_code' => $booking->booking_code,
                'booking_date' => $booking->booking_date,
                'total_price' => (float) $booking->total_price,
                'total_price_formatted' => $this->money($booking->total_price),
                'status' => $booking->status,
                'status_label' => $this->bookingStatusLabel($booking->status),
                'payment_option' => $booking->payment_option,
                'payment_status' => $booking->payment_status,
                'payment_status_label' => $this->paymentStatusLabel($booking->payment_status),
                'venue_cluster_name' => $booking->venue_cluster_name,
            ]);
    }

    private function auditLogs(string $userId)
    {
        if (! Schema::hasTable('audit_logs')) {
            return collect();
        }

        return AuditLog::query()
            ->with('actor:id,username,full_name')
            ->where('entity_type', 'users')
            ->where('entity_id', $userId)
            ->latest('created_at')
            ->limit(20)
            ->get()
            ->map(fn (AuditLog $log): array => [
                'id' => $log->id,
                'actor_name' => $log->actor?->full_name ?: $log->actor?->username,
                'action' => $log->action,
                'action_label' => $this->auditActionLabel($log->action),
                'reason' => $log->reason,
                'old_values_summary' => $this->auditValuesSummary($log->old_values),
                'new_values_summary' => $this->auditValuesSummary($log->new_values),
                'technical_old_values' => $log->old_values,
                'technical_new_values' => $log->new_values,
                'created_at' => $log->created_at,
            ]);
    }

    private function auditValuesSummary(?array $values): array
    {
        if (! $values) {
            return [];
        }

        $labels = [
            'status' => 'Trạng thái',
            'lock_type' => 'Loại khóa',
            'status_reason' => 'Lý do',
            'locked_at' => 'Thời điểm khóa',
            'locked_until' => 'Khóa đến',
            'locked_by' => 'Người khóa',
        ];

        return collect($values)
            ->reject(fn ($value, $key) => in_array($key, ['id', 'created_at', 'updated_at'], true))
            ->map(fn ($value, $key) => [
                'field' => $labels[$key] ?? (string) $key,
                'value' => $this->displayValue($value),
            ])
            ->values()
            ->all();
    }

    private function displayValue(mixed $value): string
    {
        if ($value === null || $value === '') {
            return '(trống)';
        }

        if (is_bool($value)) {
            return $value ? 'Có' : 'Không';
        }

        if (is_array($value) || is_object($value)) {
            return 'Dữ liệu kỹ thuật đã thay đổi';
        }

        return (string) $value;
    }

    private function scopeLabel(?string $scopeType, ?string $scopeId): string
    {
        $zeroUuid = '00000000-0000-0000-0000-000000000000';
        if (! $scopeType || $scopeType === 'system' || ! $scopeId || $scopeId === $zeroUuid) {
            return 'Toàn hệ thống';
        }

        return match ($scopeType) {
            'venue', 'venue_cluster' => 'Cụm sân: ' . ($this->lookupName('venue_clusters', $scopeId) ?: 'không xác định'),
            'court_type' => 'Loại sân: ' . ($this->lookupName('court_types', $scopeId) ?: 'không xác định'),
            'court', 'venue_court' => 'Sân con: ' . ($this->lookupName('venue_courts', $scopeId) ?: 'không xác định'),
            default => 'Phạm vi nghiệp vụ',
        };
    }

    private function lookupName(string $table, string $id): ?string
    {
        if (! Schema::hasTable($table)) {
            return null;
        }

        return DB::table($table)->where('id', $id)->value('name');
    }

    private function permissionLabel(?string $code): string
    {
        if (! $code) {
            return 'Quyền không xác định';
        }

        return [
            'policy.view' => 'Xem chính sách',
            'policy.create' => 'Tạo chính sách',
            'policy.update' => 'Cập nhật chính sách',
            'policy.publish' => 'Áp dụng chính sách',
            'policy.rule.manage' => 'Cấu hình xử lý chính sách',
            'user.view' => 'Xem người dùng',
            'user.lock' => 'Khóa/mở tài khoản',
        ][$code] ?? str_replace(['.', '_'], ' ', $code);
    }

    private function statusLabel(?string $status): string
    {
        return [
            'active' => 'Đang hoạt động',
            'locked' => 'Đã khóa',
            'pending_verify' => 'Chờ xác thực',
            'deactivated' => 'Đã vô hiệu hóa',
            'temporary_locked' => 'Khóa tạm thời',
            'permanent_locked' => 'Khóa vĩnh viễn',
        ][$status] ?? 'Không xác định';
    }

    private function roleLabel(string $role): string
    {
        return [
            'super_admin' => 'Super admin',
            'admin' => 'Quản trị viên',
            'system_staff' => 'Nhân viên hệ thống',
            'venue_owner' => 'Chủ sân',
            'venue_staff' => 'Nhân viên sân',
            'user' => 'Người dùng',
        ][$role] ?? $role;
    }

    private function primaryRoleLabel(array $roles): string
    {
        foreach (['super_admin', 'admin', 'system_staff', 'venue_owner', 'venue_staff', 'user'] as $role) {
            if (in_array($role, $roles, true)) {
                return $this->roleLabel($role);
            }
        }

        return 'Chưa gán vai trò';
    }

    private function warningLevelText(int $reports, int $complaints): array
    {
        [$warnThreshold, $lockThreshold, $windowDays] = $this->activeUserModerationConfig();

        $level = match (true) {
            $reports >= $lockThreshold => 'lock_suggested',
            $reports >= $warnThreshold => 'near_lock',
            $reports > 0 || $complaints > 0 => 'watch',
            default => 'normal',
        };

        return [
            'level' => $level,
            'label' => [
                'normal' => 'Bình thường',
                'watch' => 'Cần theo dõi',
                'near_lock' => 'Cảnh báo',
                'lock_suggested' => 'Cảnh báo',
            ][$level],
            'message' => $reports > 0 || $complaints > 0
                ? "Tài khoản có {$reports} người báo cáo và {$complaints} khiếu nại đang mở trong {$windowDays} ngày gần đây."
                : 'Tài khoản chưa có dấu hiệu rủi ro gần đây.',
        ];
    }

    private function reportReasonLabel(?string $reason): string
    {
        return [
            'spam' => 'Spam',
            'offensive' => 'Nội dung xúc phạm',
            'fake' => 'Giả mạo',
            'harassment' => 'Quấy rối',
            'other' => 'Khác',
        ][$reason] ?? 'Khác';
    }

    private function reportStatusLabel(?string $status): string
    {
        return [
            'pending' => 'Chờ xử lý',
            'reviewing' => 'Đang xem xét',
            'resolved' => 'Đã xử lý',
            'dismissed' => 'Đã bỏ qua',
        ][$status] ?? 'Không xác định';
    }

    private function complaintStatusLabel(?string $status): string
    {
        return [
            'open' => 'Mới mở',
            'processing' => 'Đang xử lý',
            'resolved' => 'Đã giải quyết',
            'rejected' => 'Bị từ chối',
            'closed' => 'Đã đóng',
        ][$status] ?? 'Không xác định';
    }

    private function walletStatusLabel(?string $status): string
    {
        return [
            'active' => 'Đang hoạt động',
            'locked' => 'Đang khóa',
            'pending' => 'Chờ xử lý',
            'completed' => 'Hoàn tất',
            'failed' => 'Thất bại',
            'none' => 'Chưa có ví',
        ][$status] ?? ($status ?: 'Không xác định');
    }

    private function walletLedgerTypeLabel(?string $type): string
    {
        return [
            'refund' => 'Hoàn tiền',
            'payment' => 'Thanh toán',
            'withdrawal' => 'Rút tiền',
            'adjustment' => 'Điều chỉnh',
        ][$type] ?? ($type ?: 'Biến động');
    }

    private function lockTypeLabel(?string $type): string
    {
        return [
            'temporary' => 'Khóa tạm thời',
            'permanent' => 'Khóa vĩnh viễn',
            'auto' => 'Khóa tự động',
        ][$type] ?? ($type ?: 'Không áp dụng');
    }

    private function money(mixed $value): string
    {
        return number_format((float) $value, 0, ',', '.') . ' đ';
    }

    private function bookingStatusLabel(?string $status): string
    {
        return [
            'pending_approval' => 'Chờ duyệt',
            'pending_payment' => 'Chờ thanh toán',
            'confirmed' => 'Đã xác nhận',
            'checked_in' => 'Đã check-in',
            'completed' => 'Hoàn tất',
            'cancelled' => 'Đã hủy',
            'expired' => 'Hết hạn',
            'rejected' => 'Bị từ chối',
        ][$status] ?? 'Không xác định';
    }

    private function paymentStatusLabel(?string $status): string
    {
        return [
            'pending' => 'Chờ thanh toán',
            'paid' => 'Đã thanh toán',
            'failed' => 'Thất bại',
            'refunded' => 'Đã hoàn tiền',
        ][$status] ?? 'Chưa có thanh toán';
    }

    private function auditActionLabel(?string $action): string
    {
        return [
            'user.locked' => 'Khóa tài khoản',
            'user.unlocked' => 'Mở khóa tài khoản',
            'admin.user.updated' => 'Cập nhật tài khoản',
        ][$action] ?? ($action ?: 'Thao tác');
    }

    private function hasRole(User $user, string $role): bool
    {
        if (! $user->relationLoaded('roles')) {
            $user->load('roles:id,name');
        }

        return $user->roles->contains('name', $role);
    }

    private function lockSnapshot(User $user): array
    {
        return [
            'status' => $user->status,
            'lock_type' => $user->lock_type,
            'status_reason' => $user->status_reason,
            'locked_at' => $user->locked_at,
            'locked_until' => $user->locked_until,
            'locked_by' => $user->locked_by,
        ];
    }

    private function audit(Request $request, User $actor, string $action, User $target, array $oldValues, array $newValues, ?string $reason = null): void
    {
        if (! class_exists(AuditLog::class) || ! Schema::hasTable('audit_logs')) {
            return;
        }

        AuditLog::query()->create([
            'actor_id' => $actor->id,
            'actor_type' => $this->adminActorType($actor),
            'module' => 'admin_users',
            'action' => $action,
            'entity_type' => 'users',
            'entity_id' => $target->id,
            'old_values' => $oldValues,
            'new_values' => $newValues,
            'context' => 'admin',
            'reason' => $reason,
            'ip_address' => $request->ip(),
            'user_agent' => substr((string) $request->userAgent(), 0, 500),
        ]);
    }

    private function adminActorType(User $actor): string
    {
        return $this->hasRole($actor, 'super_admin') ? 'super_admin' : 'admin';
    }

    public function store(Request $request): JsonResponse
    {
        $this->authorizePermission($request, 'staff.create');

        $data = $request->validate([
            'username' => ['required', 'string', 'min:3', 'max:50', 'regex:/^[a-zA-Z0-9_]+$/', 'unique:users,username'],
            'full_name' => ['required', 'string', 'max:100'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users,email'],
            'phone' => ['nullable', 'string', 'max:20', 'unique:users,phone'],
            'password' => ['required', 'string', 'min:6'],
            'roles' => ['required', 'array'],
            'roles.*' => ['integer', 'exists:roles,id'],
        ], [
            'username.required' => 'Vui lòng nhập tên đăng nhập.',
            'username.unique' => 'Tên đăng nhập đã tồn tại.',
            'username.regex' => 'Tên đăng nhập chỉ bao gồm chữ, số và gạch dưới.',
            'email.required' => 'Vui lòng nhập email.',
            'email.unique' => 'Email đã tồn tại.',
            'phone.unique' => 'Số điện thoại đã tồn tại.',
            'password.required' => 'Vui lòng nhập mật khẩu.',
            'password.min' => 'Mật khẩu phải có ít nhất 6 ký tự.',
            'roles.required' => 'Vui lòng chọn vai trò.',
        ]);

        /** @var User $actor */
        $actor = $request->user();
        $actorRoles = $actor->roles()->pluck('roles.name')->all();

        $targetRoleNames = DB::table('roles')
            ->whereIn('id', $data['roles'])
            ->pluck('name')
            ->all();

        $hasAdminRole = array_intersect($targetRoleNames, ['super_admin', 'admin']);
        if ($hasAdminRole && !in_array('super_admin', $actorRoles, true)) {
            throw ValidationException::withMessages([
                'roles' => 'Chỉ Super Admin mới được phép tạo hoặc gán vai trò Admin.',
            ]);
        }

        $user = DB::transaction(function () use ($data, $actor): User {
            $created = User::query()->create([
                'username' => $data['username'],
                'full_name' => $data['full_name'],
                'email' => $data['email'],
                'phone' => $data['phone'] ?? null,
                'password' => Hash::make($data['password']),
                'status' => 'active',
            ]);

            foreach ($data['roles'] as $roleId) {
                DB::table('user_roles')->insert([
                    'user_id' => $created->id,
                    'role_id' => $roleId,
                    'granted_by' => $actor->id,
                    'created_at' => now(),
                ]);
            }

            return $created;
        });

        $freshUser = $user->fresh('roles');
        $payload = $this->payload($freshUser);

        $this->audit->log(
            $request,
            'staff',
            'user.created',
            'users',
            $freshUser->id,
            [],
            $payload,
            ['severity' => 'warning']
        );

        return response()->json([
            'message' => 'Tạo tài khoản nhân sự thành công.',
            'data' => $payload,
        ], 201);
    }

    public function update(Request $request, string $id): JsonResponse
    {
        $this->authorizePermission($request, ['staff.assign_role', 'staff.create']);

        $user = User::query()->findOrFail($id);

        $data = $request->validate([
            'full_name' => ['required', 'string', 'max:100'],
            'email' => ['required', 'string', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user->id)],
            'phone' => ['nullable', 'string', 'max:20', Rule::unique('users', 'phone')->ignore($user->id)],
            'password' => ['nullable', 'string', 'min:6'],
            'roles' => ['required', 'array'],
            'roles.*' => ['integer', 'exists:roles,id'],
        ], [
            'full_name.required' => 'Vui lòng nhập họ tên.',
            'email.required' => 'Vui lòng nhập email.',
            'email.unique' => 'Email đã tồn tại.',
            'phone.unique' => 'Số điện thoại đã tồn tại.',
            'password.min' => 'Mật khẩu phải có ít nhất 6 ký tự.',
            'roles.required' => 'Vui lòng chọn vai trò.',
        ]);

        /** @var User $actor */
        $actor = $request->user();
        $actorRoles = $actor->roles()->pluck('roles.name')->all();

        $targetCurrentRoles = $user->roles()->pluck('roles.name')->all();
        $targetNewRoleNames = DB::table('roles')
            ->whereIn('id', $data['roles'])
            ->pluck('name')
            ->all();

        $hasCurrentAdmin = array_intersect($targetCurrentRoles, ['super_admin', 'admin']);
        $hasNewAdmin = array_intersect($targetNewRoleNames, ['super_admin', 'admin']);

        if (($hasCurrentAdmin || $hasNewAdmin) && !in_array('super_admin', $actorRoles, true)) {
            throw ValidationException::withMessages([
                'roles' => 'Chỉ Super Admin mới được phép chỉnh sửa hoặc gán vai trò Admin.',
            ]);
        }

        $oldValues = $this->payload($user);

        DB::transaction(function () use ($user, $data, $actor): void {
            $updateData = [
                'full_name' => $data['full_name'],
                'email' => $data['email'],
                'phone' => $data['phone'] ?? null,
            ];

            if (!empty($data['password'])) {
                $updateData['password'] = Hash::make($data['password']);
            }

            $user->update($updateData);

            DB::table('user_roles')->where('user_id', $user->id)->delete();
            foreach ($data['roles'] as $roleId) {
                DB::table('user_roles')->insert([
                    'user_id' => $user->id,
                    'role_id' => $roleId,
                    'granted_by' => $actor->id,
                    'created_at' => now(),
                ]);
            }
        });

        $freshUser = $user->fresh('roles');
        $newValues = $this->payload($freshUser);

        $this->audit->log(
            $request,
            'staff',
            'user.updated',
            'users',
            $freshUser->id,
            $oldValues,
            $newValues,
            ['severity' => 'warning']
        );

        return response()->json([
            'message' => 'Cập nhật tài khoản nhân sự thành công.',
            'data' => $newValues,
        ]);
    }

    /**
     * @throws AuthorizationException
     */
    private function authorizePermission(Request $request, string|array $permissions): void
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
            ->whereIn('permissions.code', (array) $permissions)
            ->exists();

        if (! $hasPermission) {
            throw new AuthorizationException('Bạn không có quyền thực hiện thao tác này.');
        }
    }
}
