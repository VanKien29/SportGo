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
            ->filter(fn (Permission $permission): bool => $this->permissionMeta($permission->code)['risk_level'] !== 'normal')
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
            ->groupBy('module_key')
            ->map(fn ($permissions, $moduleKey): array => [
                'group_name' => $moduleKey,
                'module_label' => $this->moduleLabel((string) $moduleKey),
                'module_description' => $this->moduleDescription((string) $moduleKey),
                'permissions' => $permissions->values(),
            ])
            ->values()
            ->all();
    }

    private function permissionPayload(Permission $permission): array
    {
        $meta = $this->permissionMeta($permission->code);

        return [
            'id' => $permission->id,
            'code' => $permission->code,
            'name' => $permission->name,
            'group_name' => $permission->group_name,
            'label' => $meta['label'],
            'description' => $meta['description'],
            'risk_level' => $meta['risk_level'],
            'risk_label' => $this->riskLabel($meta['risk_level']),
            'module_key' => $meta['module_key'],
            'module_label' => $this->moduleLabel($meta['module_key']),
        ];
    }

    private function canEditPermissions(Role $role): bool
    {
        return ! in_array($role->name, self::LOCKED_PERMISSION_ROLES, true)
            && ! in_array($role->name, self::FIXED_CLIENT_ROLES, true);
    }

    private function permissionMeta(string $code): array
    {
        $map = [
            'dashboard.view' => ['Xem tổng quan hệ thống', 'Xem dashboard và các số liệu tổng quan.', 'normal', 'dashboard'],
            'profile.view' => ['Xem hồ sơ cá nhân', 'Xem thông tin hồ sơ của chính nhân sự đang đăng nhập.', 'normal', 'profile'],
            'profile.update' => ['Cập nhật hồ sơ cá nhân', 'Cập nhật thông tin cá nhân của chính nhân sự đang đăng nhập.', 'normal', 'profile'],
            'user.view' => ['Xem tài khoản', 'Xem danh sách và thông tin tài khoản trong hệ thống.', 'normal', 'user'],
            'user.lock' => ['Khóa tài khoản', 'Khóa tài khoản và thu hồi token đăng nhập hiện tại.', 'account_lock', 'user'],
            'user.unlock' => ['Mở khóa tài khoản', 'Mở khóa tài khoản để người dùng có thể đăng nhập lại.', 'account_lock', 'user'],
            'staff.view' => ['Xem nhân sự hệ thống', 'Xem danh sách tài khoản nhân sự nội bộ.', 'normal', 'staff'],
            'staff.create' => ['Tạo nhân sự hệ thống', 'Tạo tài khoản nhân sự nội bộ thấp hơn Admin.', 'permission', 'staff'],
            'staff.assign_role' => ['Gán nhóm quyền nhân sự', 'Gán nhóm quyền phù hợp cho nhân sự hệ thống.', 'permission', 'staff'],
            'staff.lock' => ['Khóa/mở khóa nhân sự', 'Khóa hoặc mở khóa tài khoản nhân sự nội bộ.', 'account_lock', 'staff'],
            'role.view' => ['Xem nhóm quyền', 'Xem danh sách và chi tiết nhóm quyền nhân sự hệ thống.', 'normal', 'role'],
            'role.create' => ['Tạo nhóm quyền', 'Tạo nhóm quyền mới cho nhân sự quản trị.', 'permission', 'role'],
            'role.update' => ['Sửa nhóm quyền', 'Cập nhật tên và mô tả nhóm quyền.', 'permission', 'role'],
            'role.delete' => ['Xóa nhóm quyền', 'Xóa nhóm quyền tùy chỉnh khi chưa có nhân sự sử dụng.', 'permission', 'role'],
            'role.permission.manage' => ['Phân quyền nhóm', 'Cấp hoặc thu hồi quyền của một nhóm nhân sự hệ thống.', 'permission', 'role'],
            'role.manage' => ['Quản lý vai trò', 'Quản lý thông tin và phân quyền của nhóm quyền.', 'permission', 'role'],
            'policy.view' => ['Xem chính sách', 'Xem danh sách, nội dung và lịch sử chính sách.', 'normal', 'policy'],
            'policy.create' => ['Tạo chính sách', 'Tạo bản nháp chính sách mới.', 'system', 'policy'],
            'policy.update' => ['Sửa chính sách', 'Cập nhật thông tin bản nháp chính sách.', 'system', 'policy'],
            'policy.publish' => ['Publish chính sách', 'Đưa chính sách vào áp dụng trên hệ thống.', 'system', 'policy'],
            'policy.rule.manage' => ['Quản lý quy tắc chính sách', 'Cấu hình quy tắc xử lý tự động theo chính sách.', 'system', 'policy'],
            'moderation.view' => ['Xem nội dung chờ duyệt', 'Xem bài viết, bình luận hoặc nội dung cần kiểm duyệt.', 'normal', 'moderation'],
            'moderation.manage' => ['Quản lý kiểm duyệt', 'Duyệt, từ chối hoặc ẩn nội dung vi phạm.', 'sensitive', 'moderation'],
            'moderation.approve' => ['Duyệt nội dung', 'Cho phép nội dung chờ duyệt được hiển thị công khai.', 'sensitive', 'moderation'],
            'moderation.reject' => ['Từ chối nội dung', 'Từ chối nội dung không đạt yêu cầu hiển thị.', 'sensitive', 'moderation'],
            'report.view' => ['Xem báo cáo vi phạm', 'Xem danh sách báo cáo vi phạm từ người dùng.', 'normal', 'report'],
            'report.resolve' => ['Xử lý báo cáo vi phạm', 'Nhận xử lý, bỏ qua hoặc xử lý nội dung vi phạm.', 'sensitive', 'report'],
            'complaint.view' => ['Xem khiếu nại', 'Xem danh sách khiếu nại và dữ liệu liên quan.', 'normal', 'complaint'],
            'complaint.handle' => ['Xử lý khiếu nại', 'Nhận xử lý, phản hồi, giải quyết hoặc từ chối khiếu nại.', 'sensitive', 'complaint'],
            'content.view' => ['Xem nội dung', 'Xem nội dung công khai và nội dung cần kiểm duyệt.', 'normal', 'moderation'],
            'content.manage' => ['Quản lý nội dung', 'Ẩn, duyệt hoặc xử lý nội dung trong hệ thống.', 'sensitive', 'moderation'],
            'venue.view' => ['Xem đối tác và cụm sân', 'Xem hồ sơ đối tác, cụm sân và sân con.', 'normal', 'venue'],
            'venue.manage' => ['Quản lý đối tác và cụm sân', 'Duyệt, khóa hoặc cập nhật thông tin cụm sân.', 'sensitive', 'venue'],
            'venue.lock' => ['Khóa/mở khóa cụm sân', 'Khóa hoặc mở khóa cụm sân khi có lý do hợp lệ.', 'sensitive', 'venue'],
            'partner.view' => ['Xem hồ sơ đối tác', 'Xem hồ sơ đăng ký chủ sân và giấy tờ liên quan.', 'normal', 'partner'],
            'partner.review' => ['Duyệt hồ sơ đối tác', 'Chuyển trạng thái, duyệt hoặc từ chối hồ sơ đối tác.', 'sensitive', 'partner'],
            'court.view' => ['Xem sân con', 'Xem danh sách sân con và loại sân.', 'normal', 'venue'],
            'court.manage' => ['Quản lý sân con', 'Cập nhật trạng thái, thông tin và loại sân.', 'sensitive', 'venue'],
            'booking.view' => ['Xem lịch đặt sân', 'Xem danh sách booking trong hệ thống.', 'normal', 'booking'],
            'booking.manage' => ['Quản lý lịch đặt sân', 'Xử lý booking, xác nhận, hủy hoặc cập nhật trạng thái.', 'sensitive', 'booking'],
            'booking.support' => ['Hỗ trợ xử lý booking', 'Hỗ trợ cập nhật trạng thái booking trong phạm vi được cấp.', 'sensitive', 'booking'],
            'price.view' => ['Xem bảng giá', 'Xem giá theo cụm sân, loại sân và khung giờ.', 'normal', 'pricing'],
            'price.manage' => ['Quản lý bảng giá', 'Cập nhật giá sân và giá ngày lễ.', 'sensitive', 'pricing'],
            'audit.view' => ['Xem nhật ký hệ thống', 'Xem audit log của các thao tác nhạy cảm.', 'system', 'audit'],
            'refund.view' => ['Xem yêu cầu hoàn tiền', 'Xem danh sách yêu cầu hoàn tiền.', 'finance', 'finance'],
            'refund.approve' => ['Duyệt hoàn tiền', 'Xác nhận hoàn tiền cho khách hàng.', 'finance', 'finance'],
            'payment.view' => ['Xem thanh toán', 'Xem giao dịch thanh toán và log cổng thanh toán.', 'finance', 'finance'],
            'payment.manage' => ['Quản lý thanh toán', 'Xử lý giao dịch thanh toán, đối soát hoặc lỗi thanh toán.', 'finance', 'finance'],
            'wallet.view' => ['Xem ví người dùng/chủ sân', 'Xem số dư và lịch sử ví phục vụ đối soát.', 'finance', 'finance'],
            'withdrawal.manage' => ['Xử lý yêu cầu rút tiền', 'Tiếp nhận và xử lý yêu cầu rút tiền của chủ sân.', 'finance', 'finance'],
            'reconciliation.manage' => ['Xử lý đối soát', 'Thực hiện đối soát tài chính và ghi nhận kết quả.', 'finance', 'finance'],
        ];

        if (isset($map[$code])) {
            [$label, $description, $riskLevel, $moduleKey] = $map[$code];

            return [
                'label' => $label,
                'description' => $description,
                'risk_level' => $riskLevel,
                'module_key' => $moduleKey,
            ];
        }

        $parts = explode('.', $code);
        $action = array_pop($parts);
        $moduleKey = $parts[0] ?? 'other';

        return [
            'label' => ucfirst(str_replace('_', ' ', $action . ' ' . implode(' ', $parts))),
            'description' => 'Quyền thao tác trong module ' . $this->moduleLabel($moduleKey) . '.',
            'risk_level' => str_contains($code, 'delete') || str_contains($code, 'lock') ? 'sensitive' : 'normal',
            'module_key' => $moduleKey,
        ];
    }

    private function moduleLabel(string $moduleKey): string
    {
        return [
            'dashboard' => 'Tổng quan hệ thống',
            'profile' => 'Hồ sơ cá nhân',
            'user' => 'Tài khoản',
            'staff' => 'Tài khoản nhân sự',
            'role' => 'Nhóm quyền',
            'policy' => 'Chính sách',
            'moderation' => 'Bài viết và kiểm duyệt',
            'report' => 'Báo cáo vi phạm',
            'complaint' => 'Khiếu nại',
            'partner' => 'Đối tác',
            'venue' => 'Đối tác và sân',
            'booking' => 'Đặt sân',
            'pricing' => 'Bảng giá',
            'finance' => 'Tài chính và đối soát',
            'audit' => 'Nhật ký hệ thống',
        ][$moduleKey] ?? ucfirst(str_replace('_', ' ', $moduleKey));
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
