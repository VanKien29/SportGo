<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\ViolationType;
use App\Services\Admin\AdminAuditService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class ViolationTypeController extends Controller
{
    public function __construct(private readonly AdminAuditService $audit)
    {
    }

    public function index(Request $request): JsonResponse
    {
        $this->authorizePermission($request, 'policy.view');

        $types = ViolationType::query()
            ->when($request->filled('keyword'), function ($query) use ($request): void {
                $keyword = '%' . $request->query('keyword') . '%';
                $query->where(fn ($inner) => $inner->where('code', 'like', $keyword)->orWhere('name', 'like', $keyword));
            })
            ->orderByDesc('is_active')
            ->orderBy('base_score')
            ->get();

        return response()->json(['data' => $types]);
    }

    public function store(Request $request): JsonResponse
    {
        $this->authorizePermission($request, 'policy.rule.manage');

        $data = $this->validated($request);
        $type = ViolationType::query()->create($data);
        $this->audit->log($request, 'moderation', 'violation_type.created', 'violation_types', (string) $type->id, [], $type->toArray());

        return response()->json(['message' => 'Đã tạo loại vi phạm.', 'data' => $type], 201);
    }

    public function update(Request $request, string $id): JsonResponse
    {
        $this->authorizePermission($request, 'policy.rule.manage');

        $type = ViolationType::query()->findOrFail($id);
        $oldValues = $type->toArray();
        $type->update($this->validated($request, $type->id));
        $this->audit->log($request, 'moderation', 'violation_type.updated', 'violation_types', (string) $type->id, $oldValues, $type->fresh()->toArray());

        return response()->json(['message' => 'Đã cập nhật loại vi phạm.', 'data' => $type->fresh()]);
    }

    public function destroy(Request $request, string $id): JsonResponse
    {
        $this->authorizePermission($request, 'policy.rule.manage');

        $type = ViolationType::query()->findOrFail($id);
        $oldValues = $type->toArray();
        $type->update(['is_active' => false]);
        $this->audit->log($request, 'moderation', 'violation_type.disabled', 'violation_types', (string) $type->id, $oldValues, $type->fresh()->toArray());

        return response()->json(['message' => 'Đã tắt loại vi phạm.']);
    }

    private function validated(Request $request, ?int $ignoreId = null): array
    {
        return $request->validate([
            'code' => ['required', 'string', 'max:50', Rule::unique('violation_types', 'code')->ignore($ignoreId)],
            'name' => ['required', 'string', 'max:100'],
            'base_score' => ['required', 'integer', 'min:1', 'max:255'],
            'is_immediate' => ['nullable', 'boolean'],
            'is_active' => ['nullable', 'boolean'],
        ]);
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
