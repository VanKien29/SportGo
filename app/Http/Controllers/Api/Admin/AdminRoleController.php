<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Permission;
use App\Models\Role;
use App\Services\Admin\AdminAuditService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class AdminRoleController extends Controller
{
    private const FIXED_CLIENT_ROLES = ['user', 'venue_owner', 'venue_staff'];

    private const LOCKED_PERMISSION_ROLES = ['super_admin', 'admin'];

    public function __construct(private readonly AdminAuditService $audit)
    {
    }

    public function index(Request $request): JsonResponse
    {
        $this->authorizePermission($request, 'role.view');

        $roles = Role::query()
            ->withCount(['permissions', 'users'])
            ->whereNotIn('name', self::FIXED_CLIENT_ROLES)
            ->when($request->filled('keyword'), function ($query) use ($request): void {
                $keyword = '%' . $request->query('keyword') . '%';
                $query->where(function ($inner) use ($keyword): void {
                    $inner->where('name', 'like', $keyword)
                        ->orWhere('display_name', 'like', $keyword)
                        ->orWhere('description', 'like', $keyword);
                });
            })
            ->when($request->filled('is_system'), fn ($query) => $query->where('is_system', $request->boolean('is_system')))
            ->orderByDesc('is_system')
            ->orderBy('display_name')
            ->get();

        $staffCount = DB::table('user_roles')
            ->join('roles', 'roles.id', '=', 'user_roles.role_id')
            ->whereNotIn('roles.name', self::FIXED_CLIENT_ROLES)
            ->distinct('user_roles.user_id')
            ->count('user_roles.user_id');

        $sensitivePermissions = Permission::query()
            ->get()
            ->filter(fn (Permission $permission): bool => $this->permissionRiskLevel($permission->code) !== 'normal')
            ->count();

        return response()->json([
            'data' => $roles->map(fn (Role $role): array => $this->rolePayload($role))->values(),
            'summary' => [
                'total' => $roles->count(),
                'system' => $roles->where('is_system', true)->count(),
                'custom' => $roles->where('is_system', false)->count(),
                'staff_count' => $staffCount,
                'sensitive_permissions' => $sensitivePermissions,
                'permissions' => Permission::query()->count(),
            ],
        ]);
    }

    public function show(Request $request, string $id): JsonResponse
    {
        $this->authorizePermission($request, 'role.view');

        $role = Role::query()
            ->with(['permissions' => fn ($query) => $query->orderBy('group_name')->orderBy('code')])
            ->withCount('users')
            ->findOrFail($id);

        $users = $role->users()
            ->select('users.id', 'users.username', 'users.full_name', 'users.email', 'users.phone', 'users.status')
            ->orderBy('users.username')
            ->limit(100)
            ->get();

        $auditLogs = Schema::hasTable('audit_logs')
            ? AuditLog::query()
                ->with('actor:id,username,full_name,email')
                ->where('entity_type', 'roles')
                ->where('entity_id', (string) $role->id)
                ->latest()
                ->limit(50)
                ->get()
            : collect();

        return response()->json([
            'data' => [
                'role' => $this->rolePayload($role),
                'permissions' => $role->permissions
                    ->map(fn (Permission $permission): array => $this->permissionPayload($permission))
                    ->values(),
                'permission_groups' => $this->permissionGroups(),
                'users' => $users,
                'audit_logs' => $auditLogs->map(fn (AuditLog $log): array => $this->auditPayload($log))->values(),
            ],
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $this->authorizePermission($request, 'role.create');

        $data = $request->validate([
            'name' => ['required', 'string', 'max:50', 'regex:/^[a-z][a-z0-9_\\.]*$/', 'unique:roles,name'],
            'display_name' => ['required', 'string', 'max:100'],
            'description' => ['nullable', 'string', 'max:2000'],
        ], [
            'name.regex' => 'Mã nhóm chỉ dùng chữ thường, số, dấu chấm và gạch dưới.',
            'name.unique' => 'Mã nhóm quyền đã tồn tại.',
            'display_name.required' => 'Vui lòng nhập tên nhóm quyền.',
        ]);

        $role = Role::query()->create([
            ...$data,
            'is_system' => false,
        ]);

        $this->audit->log($request, 'role', 'role.created', 'roles', (string) $role->id, [], $role->toArray(), [
            'severity' => 'warning',
        ]);

        return response()->json([
            'message' => 'Đã tạo nhóm quyền hệ thống.',
            'data' => $this->rolePayload($role),
        ], 201);
    }

    public function update(Request $request, string $id): JsonResponse
    {
        $this->authorizePermission($request, 'role.update');

        $role = Role::query()->findOrFail($id);
        $data = $request->validate([
            'name' => [
                'required',
                'string',
                'max:50',
                'regex:/^[a-z][a-z0-9_\\.]*$/',
                Rule::unique('roles', 'name')->ignore($role->id),
            ],
            'display_name' => ['required', 'string', 'max:100'],
            'description' => ['nullable', 'string', 'max:2000'],
        ]);

        if (in_array($role->name, self::LOCKED_PERMISSION_ROLES, true)) {
            throw ValidationException::withMessages([
                'role' => 'Nhóm quyền quản trị lõi không được chỉnh sửa trực tiếp.',
            ]);
        }

        if ($role->is_system && $role->name !== $data['name']) {
            throw ValidationException::withMessages([
                'name' => 'Nhóm quyền hệ thống không được sửa mã nhóm.',
            ]);
        }

        $oldValues = $role->toArray();
        $role->update([
            'name' => $role->is_system ? $role->name : $data['name'],
            'display_name' => $data['display_name'],
            'description' => $data['description'] ?? null,
        ]);

        $this->audit->log($request, 'role', 'role.updated', 'roles', (string) $role->id, $oldValues, $role->fresh()->toArray(), [
            'severity' => 'warning',
        ]);

        return response()->json([
            'message' => 'Đã cập nhật nhóm quyền.',
            'data' => $this->rolePayload($role->fresh()->loadCount(['permissions', 'users'])),
        ]);
    }

    public function destroy(Request $request, string $id): JsonResponse
    {
        $this->authorizePermission($request, 'role.delete');

        $role = Role::query()->withCount('users')->findOrFail($id);

        if ($role->is_system) {
            throw ValidationException::withMessages([
                'role' => 'Không được xóa nhóm quyền hệ thống.',
            ]);
        }

        if ($role->users_count > 0) {
            throw ValidationException::withMessages([
                'role' => 'Không được xóa nhóm quyền đang có nhân sự.',
            ]);
        }

        $oldValues = $role->toArray();

        DB::transaction(function () use ($role): void {
            $role->permissions()->detach();
            $role->delete();
        });

        $this->audit->log($request, 'role', 'role.deleted', 'roles', (string) $role->id, $oldValues, [], [
            'severity' => 'critical',
        ]);

        return response()->json(['message' => 'Đã xóa nhóm quyền.']);
    }

    public function permissions(Request $request): JsonResponse
    {
        $this->authorizePermission($request, 'role.view');

        return response()->json(['data' => $this->permissionGroups()]);
    }

    public function matrix(Request $request): JsonResponse
    {
        $this->authorizePermission($request, 'role.view');

        $roles = Role::query()
            ->with('permissions:id')
            ->whereNotIn('name', self::FIXED_CLIENT_ROLES)
            ->orderByDesc('is_system')
            ->orderBy('display_name')
            ->get()
            ->map(fn (Role $role): array => [
                ...$this->rolePayload($role),
                'permission_ids' => $role->permissions->pluck('id')->map(fn ($id) => (int) $id)->values()->all(),
            ])
            ->values();

        return response()->json([
            'data' => [
                'roles' => $roles,
                'permission_groups' => $this->permissionGroups(),
            ],
        ]);
    }

    public function updatePermissions(Request $request, string $id): JsonResponse
    {
        $this->authorizePermission($request, 'role.permission.manage');

        $role = Role::query()->with('permissions')->findOrFail($id);

        if (! $this->canEditPermissions($role)) {
            throw ValidationException::withMessages([
                'permission_ids' => 'Nhóm quyền này bị khóa chỉnh sửa quyền.',
            ]);
        }

        $data = $request->validate([
            'permission_ids' => ['array'],
            'permission_ids.*' => ['integer', 'exists:permissions,id'],
        ]);

        $permissionIds = collect($data['permission_ids'] ?? [])
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values()
            ->all();

        $this->ensureActorCanGrantPermissions($request, $permissionIds);

        $oldValues = [
            'permissions' => $role->permissions->pluck('code')->values()->all(),
        ];

        $role->permissions()->sync($permissionIds);
        $freshRole = $role->fresh(['permissions'])->loadCount(['permissions', 'users']);
        $newPermissions = $freshRole->permissions->pluck('code')->values()->all();

        $this->audit->log($request, 'role', 'role.permissions_updated', 'roles', (string) $role->id, $oldValues, [
            'permissions' => $newPermissions,
        ], [
            'severity' => 'critical',
        ]);

        return response()->json([
            'message' => 'Đã cập nhật quyền cho nhóm.',
            'data' => [
                'role' => $this->rolePayload($freshRole),
                'permissions' => $newPermissions,
            ],
        ]);
    }

    public function togglePermission(Request $request, string $id): JsonResponse
    {
        $this->authorizePermission($request, 'role.permission.manage');

        $role = Role::query()->with('permissions')->findOrFail($id);

        if (! $this->canEditPermissions($role)) {
            throw ValidationException::withMessages([
                'permission_id' => 'Nhóm quyền này bị khóa chỉnh sửa quyền.',
            ]);
        }

        $data = $request->validate([
            'permission_id' => ['required', 'integer', 'exists:permissions,id'],
            'action' => ['required', Rule::in(['grant', 'revoke'])],
        ]);

        $permissionId = (int) $data['permission_id'];
        $this->ensureActorCanGrantPermissions($request, [$permissionId]);

        $oldValues = [
            'permissions' => $role->permissions->pluck('code')->values()->all(),
        ];

        if ($data['action'] === 'grant') {
            $role->permissions()->syncWithoutDetaching([$permissionId]);
        } else {
            $role->permissions()->detach($permissionId);
        }

        $freshRole = $role->fresh(['permissions'])->loadCount(['permissions', 'users']);
        $newPermissions = $freshRole->permissions->pluck('code')->values()->all();

        $this->audit->log($request, 'role', 'role.permissions_updated', 'roles', (string) $role->id, $oldValues, [
            'permissions' => $newPermissions,
        ], [
            'severity' => 'warning',
            'permission_id' => $permissionId,
            'toggle_action' => $data['action'],
        ]);

        return response()->json([
            'message' => $data['action'] === 'grant' ? 'Đã cấp quyền cho nhóm.' : 'Đã thu hồi quyền khỏi nhóm.',
            'data' => [
                'role' => $this->rolePayload($freshRole),
                'permission_ids' => $freshRole->permissions->pluck('id')->map(fn ($id) => (int) $id)->values()->all(),
            ],
        ]);
    }

    public function users(Request $request, string $id): JsonResponse
    {
        $this->authorizePermission($request, 'role.view');

        $role = Role::query()->findOrFail($id);
        $users = $role->users()
            ->select('users.id', 'users.username', 'users.full_name', 'users.email', 'users.phone', 'users.status')
            ->orderBy('users.username')
            ->get();

        return response()->json(['data' => $users]);
    }

    private function rolePayload(Role $role): array
    {
        return [
            'id' => $role->id,
            'name' => $role->name,
            'display_name' => $role->display_name,
            'description' => $role->description,
            'is_system' => (bool) $role->is_system,
            'display_scope' => in_array($role->name, self::FIXED_CLIENT_ROLES, true)
                ? 'Vai trò nghiệp vụ cố định'
                : 'Nhóm quyền nhân sự hệ thống',
            'is_configurable' => ! in_array($role->name, self::LOCKED_PERMISSION_ROLES, true)
                && ! in_array($role->name, self::FIXED_CLIENT_ROLES, true),
            'can_delete' => ! $role->is_system && (int) ($role->users_count ?? 0) === 0,
            'can_edit_permissions' => $this->canEditPermissions($role),
            'permissions_count' => (int) ($role->permissions_count ?? $role->permissions?->count() ?? 0),
            'users_count' => (int) ($role->users_count ?? 0),
            'created_at' => $role->created_at,
            'updated_at' => $role->updated_at,
        ];
    }

    private function permissionGroups(): array
    {
        return Permission::query()
            ->orderBy('group_name')
            ->orderBy('code')
            ->get()
            ->map(fn (Permission $permission): array => $this->permissionPayload($permission))
            ->groupBy('group_name')
            ->map(fn ($permissions, $moduleKey): array => [
                'group_name' => (string) $moduleKey,
                'module_label' => (string) $moduleKey,
                'module_description' => $this->moduleDescription((string) $moduleKey),
                'permissions' => $permissions->values(),
            ])
            ->values()
            ->all();
    }

    private function permissionPayload(Permission $permission): array
    {
        $groupName = (string) ($permission->group_name ?: 'Khác');
        $riskLevel = $this->permissionRiskLevel($permission->code);

        return [
            'id' => $permission->id,
            'code' => $permission->code,
            'name' => $permission->name,
            'group_name' => $groupName,
            'label' => $permission->name,
            'description' => 'Quyền thao tác trong nhóm ' . $groupName . '.',
            'risk_level' => $riskLevel,
            'risk_label' => $this->riskLabel($riskLevel),
            'module_key' => Str::slug($groupName, '_'),
            'module_label' => $groupName,
        ];
    }

    private function permissionRiskLevel(string $code): string
    {
        if (str_contains($code, 'payment') || str_contains($code, 'refund') || str_contains($code, 'wallet') || str_contains($code, 'withdrawal') || str_contains($code, 'reconciliation')) {
            return 'finance';
        }

        if (str_contains($code, 'permission') || str_contains($code, 'role') || str_contains($code, 'policy.publish')) {
            return 'permission';
        }

        if (str_contains($code, 'lock') || str_contains($code, 'unlock')) {
            return 'account_lock';
        }

        if (str_contains($code, 'delete') || str_contains($code, 'manage') || str_contains($code, 'approve') || str_contains($code, 'resolve') || str_contains($code, 'publish')) {
            return 'sensitive';
        }

        return 'normal';
    }

    private function canEditPermissions(Role $role): bool
    {
        return ! in_array($role->name, self::LOCKED_PERMISSION_ROLES, true)
            && ! in_array($role->name, self::FIXED_CLIENT_ROLES, true);
    }

    private function moduleDescription(string $moduleKey): string
    {
        return [
            'role' => 'Các quyền tạo, sửa và phân quyền nhóm nhân sự hệ thống.',
            'policy' => 'Các quyền tạo, sửa, publish chính sách và quy tắc xử lý tự động.',
            'moderation' => 'Các quyền duyệt, từ chối, ẩn nội dung và xử lý bình luận.',
            'report' => 'Các quyền xem và xử lý báo cáo vi phạm.',
            'finance' => 'Các quyền liên quan thanh toán, hoàn tiền và đối soát.',
            'user' => 'Các quyền xem, khóa và mở khóa tài khoản.',
            'staff' => 'Các quyền tạo, khóa và gán nhóm cho nhân sự hệ thống.',
            'venue' => 'Các quyền liên quan đối tác, cụm sân và sân con.',
            'partner' => 'Các quyền xem và duyệt hồ sơ đăng ký đối tác.',
            'complaint' => 'Các quyền xem và xử lý khiếu nại.',
        ][$moduleKey] ?? '';
    }

    private function riskLabel(string $riskLevel): string
    {
        return [
            'finance' => 'Tài chính',
            'system' => 'Hệ thống',
            'permission' => 'Phân quyền',
            'account_lock' => 'Khóa tài khoản',
            'sensitive' => 'Nhạy cảm',
            'normal' => '',
        ][$riskLevel] ?? '';
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

    private function ensureActorCanGrantPermissions(Request $request, array $permissionIds): void
    {
        $user = $request->user();
        $roles = $user?->roles()->pluck('roles.name')->all() ?? [];

        if (array_intersect($roles, ['super_admin', 'admin'])) {
            return;
        }

        $actorPermissionIds = DB::table('user_roles')
            ->join('role_permissions', 'role_permissions.role_id', '=', 'user_roles.role_id')
            ->where('user_roles.user_id', $user?->id)
            ->pluck('role_permissions.permission_id')
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->all();

        if (array_diff($permissionIds, $actorPermissionIds) !== []) {
            throw ValidationException::withMessages([
                'permission_ids' => 'Bạn chỉ được cấp các quyền mà tài khoản của bạn đang có.',
            ]);
        }
    }

    private function changesSummary(?array $oldValues, ?array $newValues): array
    {
        $oldValues ??= [];
        $newValues ??= [];
        $labels = [
            'name' => 'Mã nhóm',
            'display_name' => 'Tên nhóm',
            'description' => 'Mô tả',
            'permissions' => 'Quyền được cấp',
            'is_system' => 'Nhóm hệ thống',
        ];
        $changes = [];

        foreach (array_unique([...array_keys($oldValues), ...array_keys($newValues)]) as $field) {
            if (! isset($labels[$field])) {
                continue;
            }

            $old = $oldValues[$field] ?? null;
            $new = $newValues[$field] ?? null;

            if (json_encode($old) === json_encode($new)) {
                continue;
            }

            $changes[] = [
                'field' => $field,
                'field_label' => $labels[$field],
                'old' => $this->formatAuditValue($old),
                'new' => $this->formatAuditValue($new),
                'summary' => $field === 'permissions'
                    ? 'Danh sách quyền được cấp đã thay đổi'
                    : $labels[$field] . ' đã thay đổi',
            ];
        }

        return $changes;
    }

    private function formatAuditValue(mixed $value): string
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

        $string = (string) $value;

        return mb_strlen($string) > 120 ? mb_substr($string, 0, 120) . '...' : $string;
    }

    private function auditPayload(AuditLog $log): array
    {
        $actor = $log->relationLoaded('actor') ? $log->actor : null;

        return [
            ...$log->toArray(),
            'actor_name' => $actor?->full_name ?: $actor?->username ?: $actor?->email,
            'human_message' => match ($log->action) {
                'role.created' => 'Admin đã tạo nhóm quyền mới.',
                'role.updated' => 'Admin đã cập nhật thông tin nhóm quyền.',
                'role.deleted' => 'Admin đã xóa nhóm quyền.',
                'role.permissions_updated' => 'Admin đã cập nhật quyền được cấp cho nhóm.',
                default => 'Admin đã thực hiện thao tác trên nhóm quyền.',
            },
            'changes_summary' => $this->changesSummary($log->old_values ?? [], $log->new_values ?? []),
        ];
    }
}
