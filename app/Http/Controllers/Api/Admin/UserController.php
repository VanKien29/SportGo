<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\User;
use App\Services\Auth\RoleRedirectService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class UserController extends Controller
{
    public function __construct(private readonly RoleRedirectService $roleRedirectService)
    {
    }

    public function index(): JsonResponse
    {
        $users = User::query()
            ->with('roles:id,name,display_name')
            ->latest()
            ->get()
            ->map(function (User $user): array {
                $roles = $user->roles->pluck('name')->values()->all();

                return [
                    'id' => $user->id,
                    'username' => $user->username,
                    'full_name' => $user->full_name,
                    'email' => $user->email,
                    'phone' => $user->phone,
                    'status' => $user->status,
                    'roles' => $roles,
                    'role_group' => $this->roleRedirectService->roleGroup($roles),
                    'status_reason' => $user->status_reason,
                    'lock_type' => $user->lock_type,
                    'locked_at' => $user->locked_at,
                    'locked_until' => $user->locked_until,
                    'locked_by' => $user->locked_by,
                ];
            });

        return response()->json(['data' => $users]);
    }

    public function lock(Request $request, string $id): JsonResponse
    {
        $data = $request->validate([
            'lock_type' => ['required', Rule::in(['temporary', 'permanent', 'auto'])],
            'status_reason' => ['required', 'string', 'max:2000'],
            'locked_until' => ['nullable', 'date', 'after:now', 'required_if:lock_type,temporary'],
        ]);

        /** @var User $actor */
        $actor = $request->user();
        $user = User::query()->findOrFail($id);

        if ($actor->id === $user->id) {
            throw ValidationException::withMessages([
                'user' => 'Không thể tự khóa chính tài khoản đang đăng nhập.',
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

        $user->tokens()->delete();
        $this->audit($request, $actor, 'user.locked', $user, $oldValues, $this->lockSnapshot($user));

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
        $oldValues = $this->lockSnapshot($user);

        $user->forceFill([
            'status' => 'active',
            'lock_type' => null,
            'status_reason' => null,
            'locked_at' => null,
            'locked_until' => null,
            'locked_by' => null,
        ])->save();

        $this->audit($request, $actor, 'user.unlocked', $user, $oldValues, $this->lockSnapshot($user));

        return response()->json([
            'message' => 'Mở khóa tài khoản thành công.',
            'user' => $this->payload($user->fresh('roles')),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'username' => ['required', 'string', 'max:50', Rule::unique('users', 'username')],
            'full_name' => ['required', 'string', 'max:255'],
            'phone' => ['required', 'string', 'max:20', Rule::unique('users', 'phone')],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')],
            'password' => ['required', 'string', 'min:8'],
            'roles' => ['nullable', 'array'],
            'roles.*' => ['exists:roles,id'],
        ], [
            'username.unique' => 'Tên tài khoản này đã tồn tại.',
            'phone.unique' => 'Số điện thoại này đã tồn tại.',
            'email.unique' => 'Địa chỉ email này đã tồn tại.',
        ]);

        /** @var User $actor */
        $actor = $request->user();

        $user = User::query()->create([
            'username' => $data['username'],
            'full_name' => $data['full_name'],
            'phone' => $data['phone'],
            'email' => $data['email'],
            'password' => Hash::make($data['password']),
            'status' => 'active',
        ]);

        if (!empty($data['roles'])) {
            $syncData = [];
            foreach ($data['roles'] as $roleId) {
                $syncData[$roleId] = [
                    'scope_type' => 'system',
                    'scope_id' => '00000000-0000-0000-0000-000000000000',
                    'granted_by' => $actor?->id,
                ];
            }
            $user->roles()->sync($syncData);
        }

        $this->audit($request, $actor, 'user.created', $user, [], $this->lockSnapshot($user));

        return response()->json([
            'message' => 'Tạo tài khoản nhân viên thành công.',
            'user' => $this->payload($user->fresh('roles')),
        ], 201);
    }

    public function assignRoles(Request $request, string $id): JsonResponse
    {
        $data = $request->validate([
            'roles' => ['nullable', 'array'],
            'roles.*' => ['exists:roles,id'],
        ]);

        /** @var User $actor */
        $actor = $request->user();
        $user = User::query()->findOrFail($id);

        if ($actor->id === $user->id) {
            throw ValidationException::withMessages([
                'user' => 'Không thể tự chỉnh sửa vai trò của tài khoản đang đăng nhập.',
            ]);
        }

        $oldRoles = $user->roles->pluck('id')->all();

        $syncData = [];
        if (!empty($data['roles'])) {
            foreach ($data['roles'] as $roleId) {
                $syncData[$roleId] = [
                    'scope_type' => 'system',
                    'scope_id' => '00000000-0000-0000-0000-000000000000',
                    'granted_by' => $actor?->id,
                ];
            }
        }
        $user->roles()->sync($syncData);

        $this->audit($request, $actor, 'user.roles_updated', $user, ['roles' => $oldRoles], ['roles' => $data['roles'] ?? []]);

        return response()->json([
            'message' => 'Cập nhật vai trò thành công.',
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
            'role_group' => $this->roleRedirectService->roleGroup($roles),
            'status_reason' => $user->status_reason,
            'lock_type' => $user->lock_type,
            'locked_at' => $user->locked_at,
            'locked_until' => $user->locked_until,
            'locked_by' => $user->locked_by,
        ];
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

    private function audit(Request $request, User $actor, string $action, User $target, array $oldValues, array $newValues): void
    {
        if (! class_exists(AuditLog::class) || ! Schema::hasTable('audit_logs')) {
            return;
        }

        AuditLog::query()->create([
            'actor_id' => $actor->id,
            'action' => $action,
            'entity_type' => 'users',
            'entity_id' => $target->id,
            'old_values' => $oldValues,
            'new_values' => $newValues,
            'context' => 'admin',
            'ip_address' => $request->ip(),
            'user_agent' => substr((string) $request->userAgent(), 0, 500),
        ]);
    }
}
