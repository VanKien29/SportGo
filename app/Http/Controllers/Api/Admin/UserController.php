<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\User;
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
        'venue_owner',
        'venue_staff',
    ];

    public function __construct(
        private readonly RoleRedirectService $roleRedirectService,
        private readonly AdminAuditService $audit
    ) {
    }

    public function index(Request $request): JsonResponse
    {
        $this->authorizePermission($request, ['user.view', 'staff.view']);

        $query = User::query()->with('roles:id,name,display_name')->latest();

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('role')) {
            $query->whereHas('roles', function ($q) use ($request) {
                $q->where('name', $request->role);
            });
        }

        if ($request->filled('role_group')) {
            if ($request->role_group === 'staff') {
                $query->whereHas('roles', function ($q) {
                    $q->whereIn('name', [
                        'super_admin', 'admin', 'system_staff', 'content_moderator',
                        'complaint_handler', 'venue_manager', 'partner_manager',
                        'booking_support', 'finance_operator', 'policy_manager', 'staff_manager'
                    ]);
                });
            } elseif ($request->role_group === 'user') {
                $query->where(function ($q) {
                    $q->whereHas('roles', function ($sq) {
                        $sq->whereIn('name', ['user', 'venue_owner', 'venue_staff']);
                    })->orWhereDoesntHave('roles');
                });
            }
        }

        if ($request->filled('keyword')) {
            $keyword = '%' . $request->keyword . '%';
            $query->where(function ($q) use ($keyword) {
                $q->where('full_name', 'like', $keyword)
                  ->orWhere('email', 'like', $keyword)
                  ->orWhere('username', 'like', $keyword)
                  ->orWhere('phone', 'like', $keyword);
            });
        }

        if ($request->filled('per_page')) {
            $paginator = $query->paginate($request->per_page);
            return response()->json([
                'data' => collect($paginator->items())->map(fn (User $user) => $this->payload($user)),
                'meta' => [
                    'current_page' => $paginator->currentPage(),
                    'last_page' => $paginator->lastPage(),
                    'per_page' => $paginator->perPage(),
                    'total' => $paginator->total(),
                ]
            ]);
        }

        $users = $query->get()->map(fn (User $user): array => $this->payload($user));

        return response()->json(['data' => $users]);
    }

    public function show(Request $request, string $id): JsonResponse
    {
        $this->authorizePermission($request, ['user.view', 'staff.view']);

        $user = User::query()
            ->with(['roles:id,name,display_name'])
            ->findOrFail($id);

        $auditLogs = Schema::hasTable('audit_logs')
            ? AuditLog::query()
                ->with('actor:id,username,full_name,email')
                ->where('entity_type', 'users')
                ->where('entity_id', (string) $user->id)
                ->latest()
                ->limit(50)
                ->get()
            : collect();

        return response()->json([
            'data' => [
                'user' => $this->payload($user),
                'audit_logs' => $auditLogs->map(fn (AuditLog $log): array => [
                    'id' => $log->id,
                    'actor_name' => $log->actor?->full_name ?: $log->actor?->username ?: $log->actor?->email,
                    'action' => $log->action,
                    'ip_address' => $log->ip_address,
                    'user_agent' => $log->user_agent,
                    'created_at' => $log->created_at ? $log->created_at->toDateTimeString() : null,
                    'old_values' => $log->old_values,
                    'new_values' => $log->new_values,
                ])->values()->all(),
            ],
        ]);
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

        // Lấy tên của các vai trò chuẩn bị gán
        $targetRoleNames = DB::table('roles')
            ->whereIn('id', $data['roles'])
            ->pluck('name')
            ->all();

        // Chỉ Super Admin mới được tạo hoặc gán Admin / Super Admin
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

            // Gán các vai trò
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

        // 1. Kiểm tra vai trò HIỆN TẠI của đối tượng bị tác động
        $targetCurrentRoles = $user->roles()->pluck('roles.name')->all();

        // 2. Kiểm tra vai trò MỚI chuẩn bị gán
        $targetNewRoleNames = DB::table('roles')
            ->whereIn('id', $data['roles'])
            ->pluck('name')
            ->all();

        $hasCurrentAdmin = array_intersect($targetCurrentRoles, ['super_admin', 'admin']);
        $hasNewAdmin = array_intersect($targetNewRoleNames, ['super_admin', 'admin']);

        // Chỉ Super Admin mới được sửa đổi thông tin của Admin hiện tại, hoặc nâng cấp tài khoản khác lên Admin
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

            // Đồng bộ vai trò
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

    public function lock(Request $request, string $id): JsonResponse
    {
        /** @var User $actor */
        $actor = $request->user();
        $user = User::query()->findOrFail($id);

        if ($actor->id === $user->id) {
            throw ValidationException::withMessages([
                'user' => 'Không thể tự khóa tài khoản đang đăng nhập.',
            ]);
        }

        // Kiểm tra quyền khóa của actor
        $targetRoles = $user->roles()->pluck('roles.name')->all();
        $isStaff = array_intersect($targetRoles, self::STAFF_ROLES);

        if ($isStaff) {
            $this->authorizePermission($request, 'staff.lock');
        } else {
            $this->authorizePermission($request, 'user.lock');
        }

        // Chỉ Super Admin mới được khóa tài khoản Admin
        $actorRoles = $actor->roles()->pluck('roles.name')->all();
        $isTargetAdmin = array_intersect($targetRoles, ['super_admin', 'admin']);
        if ($isTargetAdmin && !in_array('super_admin', $actorRoles, true)) {
            throw ValidationException::withMessages([
                'user' => 'Chỉ Super Admin mới được phép khóa tài khoản Admin.',
            ]);
        }

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

        $oldValues = $this->lockSnapshot($user);

        $user->forceFill([
            'status' => 'locked',
            'lock_type' => $data['lock_type'],
            'status_reason' => $data['status_reason'],
            'locked_at' => now(),
            'locked_until' => $data['locked_until'] ?? null,
            'locked_by' => $actor->id,
        ])->save();

        $user->tokens()->delete();
        
        $newSnapshot = $this->lockSnapshot($user);

        $this->audit->log(
            $request,
            $isStaff ? 'staff' : 'user',
            'user.locked',
            'users',
            $user->id,
            $oldValues,
            $newSnapshot,
            ['severity' => 'critical', 'reason' => $data['status_reason']]
        );

        return response()->json([
            'message' => 'Khóa tài khoản thành công.',
            'user' => $this->payload($user->fresh('roles')),
        ]);
    }

    public function unlock(Request $request, string $id): JsonResponse
    {
        /** @var User $actor */
        $actor = $request->user();
        $user = User::query()->findOrFail($id);

        // Kiểm tra quyền mở khóa của actor
        $targetRoles = $user->roles()->pluck('roles.name')->all();
        $isStaff = array_intersect($targetRoles, self::STAFF_ROLES);

        if ($isStaff) {
            $this->authorizePermission($request, 'staff.lock');
        } else {
            $this->authorizePermission($request, 'user.unlock');
        }

        // Chỉ Super Admin mới được mở khóa tài khoản Admin
        $actorRoles = $actor->roles()->pluck('roles.name')->all();
        $isTargetAdmin = array_intersect($targetRoles, ['super_admin', 'admin']);
        if ($isTargetAdmin && !in_array('super_admin', $actorRoles, true)) {
            throw ValidationException::withMessages([
                'user' => 'Chỉ Super Admin mới được phép mở khóa tài khoản Admin.',
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

        $newSnapshot = $this->lockSnapshot($user);

        $this->audit->log(
            $request,
            $isStaff ? 'staff' : 'user',
            'user.unlocked',
            'users',
            $user->id,
            $oldValues,
            $newSnapshot,
            ['severity' => 'critical']
        );

        return response()->json([
            'message' => 'Mở khóa tài khoản thành công.',
            'user' => $this->payload($user->fresh('roles')),
        ]);
    }

    private function payload(User $user): array
    {
        $roles = $user->roles->pluck('name')->values()->all();

        return [
            'id' => $user->id,
            'username' => $user->username,
            'full_name' => $user->full_name,
            'email' => $user->email,
            'phone' => $user->phone,
            'status' => $user->status,
            'roles' => $roles,
            'role_ids' => $user->roles->pluck('id')->values()->all(),
            'role_group' => $this->roleRedirectService->roleGroup($roles),
            'status_reason' => $user->status_reason,
            'lock_type' => $user->lock_type,
            'locked_at' => $user->locked_at ? $user->locked_at->toDateTimeString() : null,
            'locked_until' => $user->locked_until ? $user->locked_until->toDateTimeString() : null,
            'locked_by' => $user->locked_by,
        ];
    }

    private function lockSnapshot(User $user): array
    {
        return [
            'status' => $user->status,
            'lock_type' => $user->lock_type,
            'status_reason' => $user->status_reason,
            'locked_at' => $user->locked_at ? $user->locked_at->toDateTimeString() : null,
            'locked_until' => $user->locked_until ? $user->locked_until->toDateTimeString() : null,
            'locked_by' => $user->locked_by,
        ];
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
