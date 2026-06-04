<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Permission;
use App\Models\Role;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class PermissionController extends Controller
{
    /**
     * Danh sách tất cả vai trò kèm permissions
     */
    public function roles(): JsonResponse
    {
        $roles = Role::with('permissions')
            ->orderByDesc('is_system')
            ->orderBy('name')
            ->get();

        return response()->json(['data' => $roles]);
    }

    /**
     * Tạo vai trò mới
     */
    public function storeRole(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'name'         => 'required|string|max:100|unique:roles,name|regex:/^[a-z0-9_]+$/',
            'display_name' => 'required|string|max:255',
            'description'  => 'nullable|string|max:500',
            'permissions'  => 'nullable|array',
            'permissions.*'=> 'exists:permissions,id',
        ], [
            'name.regex' => 'Tên vai trò chỉ được dùng chữ thường, số và dấu gạch dưới.',
            'name.unique' => 'Tên vai trò này đã tồn tại.',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => $validator->errors()->first(),
                'errors'  => $validator->errors(),
            ], 422);
        }

        $role = Role::create([
            'name'         => $request->name,
            'display_name' => $request->display_name,
            'description'  => $request->description,
            'is_system'    => false,
        ]);

        if (!empty($request->permissions)) {
            $role->permissions()->attach($request->permissions);
        }

        return response()->json([
            'message' => 'Tạo vai trò mới thành công.',
            'data'    => $role->load('permissions'),
        ], 201);
    }

    /**
     * Cập nhật vai trò (display_name, description, permissions)
     */
    public function updateRole(Request $request, Role $role): JsonResponse
    {
        if ($role->is_system) {
            return response()->json(['message' => 'Không thể chỉnh sửa vai trò hệ thống.'], 403);
        }

        $validator = Validator::make($request->all(), [
            'display_name' => 'required|string|max:255',
            'description'  => 'nullable|string|max:500',
            'permissions'  => 'nullable|array',
            'permissions.*'=> 'exists:permissions,id',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => $validator->errors()->first(),
                'errors'  => $validator->errors(),
            ], 422);
        }

        $role->update([
            'display_name' => $request->display_name,
            'description'  => $request->description,
        ]);

        $role->permissions()->sync($request->permissions ?? []);

        return response()->json([
            'message' => 'Cập nhật vai trò thành công.',
            'data'    => $role->load('permissions'),
        ]);
    }

    /**
     * Xóa vai trò
     */
    public function destroyRole(Role $role): JsonResponse
    {
        if ($role->is_system) {
            return response()->json(['message' => 'Không thể xóa vai trò hệ thống.'], 403);
        }

        if ($role->users()->exists()) {
            return response()->json(['message' => 'Không thể xóa vai trò vì vẫn còn người dùng đang sử dụng.'], 422);
        }

        $role->permissions()->detach();
        $role->delete();

        return response()->json(['message' => 'Xóa vai trò thành công.']);
    }

    /**
     * Danh sách tất cả quyền
     */
    public function permissions(): JsonResponse
    {
        $permissions = Permission::orderBy('group_name')->orderBy('name')->get();

        return response()->json(['data' => $permissions]);
    }

    /**
     * Tạo quyền mới
     */
    public function storePermission(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'code'       => 'required|string|max:100|unique:permissions,code',
            'name'       => 'required|string|max:255',
            'group_name' => 'required|string|max:100',
        ], [
            'code.unique' => 'Mã quyền này đã tồn tại.',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => $validator->errors()->first(),
                'errors'  => $validator->errors(),
            ], 422);
        }

        $permission = Permission::create($request->only(['code', 'name', 'group_name']));

        return response()->json([
            'message' => 'Tạo quyền mới thành công.',
            'data'    => $permission,
        ], 201);
    }

    /**
     * Xóa quyền
     */
    public function destroyPermission(Permission $permission): JsonResponse
    {
        // Kiểm tra xem quyền đang được gán cho role nào không
        if ($permission->roles()->exists()) {
            return response()->json([
                'message' => 'Không thể xóa quyền này vì nó đang được sử dụng bởi một hoặc nhiều vai trò.',
            ], 422);
        }

        $permission->delete();

        return response()->json(['message' => 'Xóa quyền thành công.']);
    }
}
