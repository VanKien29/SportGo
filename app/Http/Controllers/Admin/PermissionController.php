<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Permission;
use App\Models\Role;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PermissionController extends Controller
{
    /**
     * Danh sách vai trò và phân quyền
     */
    public function index(Request $request)
    {
        $roles = Role::with('permissions')
            ->orderBy('is_system', 'desc')
            ->orderBy('name')
            ->paginate(10);

        $permissions = Permission::orderBy('group_name')->orderBy('name')->get();
        $permissionsByGroup = $permissions->groupBy('group_name');

        return view('admin.permissions.index', compact('roles', 'permissions', 'permissionsByGroup'));
    }

    /**
     * Form chỉnh sửa vai trò
     */
    public function edit_role(Role $role)
    {
        if ($role->is_system) {
            return redirect()->route('admin.permissions.index')
                ->with('error', 'Không thể chỉnh sửa vai trò hệ thống.');
        }

        $permissions = Permission::orderBy('group_name')->orderBy('name')->get();
        $permissionsByGroup = $permissions->groupBy('group_name');
        $rolePermissionIds = $role->permissions()->pluck('permissions.id')->toArray();

        return view('admin.permissions.edit_role', compact('role', 'permissions', 'permissionsByGroup', 'rolePermissionIds'));
    }

    /**
     * Cập nhật phân quyền cho vai trò
     */
    public function update_role(Request $request, Role $role)
    {
        if ($role->is_system) {
            return redirect()->route('admin.permissions.index')
                ->with('error', 'Không thể chỉnh sửa vai trò hệ thống.');
        }

        $validated = $request->validate([
            'display_name' => 'required|string|max:255',
            'description' => 'nullable|string|max:500',
            'permissions' => 'nullable|array',
            'permissions.*' => 'exists:permissions,id',
        ]);

        // Cập nhật thông tin vai trò
        $role->update([
            'display_name' => $validated['display_name'],
            'description' => $validated['description'],
        ]);

        // Cập nhật phân quyền
        $permissionIds = $validated['permissions'] ?? [];
        $role->permissions()->sync($permissionIds);

        return redirect()->route('admin.permissions.index')
            ->with('success', 'Cập nhật vai trò và phân quyền thành công!');
    }

    /**
     * Form tạo vai trò mới
     */
    public function create_role()
    {
        $permissions = Permission::orderBy('group_name')->orderBy('name')->get();
        $permissionsByGroup = $permissions->groupBy('group_name');

        return view('admin.permissions.create_role', compact('permissions', 'permissionsByGroup'));
    }

    /**
     * Lưu vai trò mới
     */
    public function store_role(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255|unique:roles,name',
            'display_name' => 'required|string|max:255',
            'description' => 'nullable|string|max:500',
            'permissions' => 'nullable|array',
            'permissions.*' => 'exists:permissions,id',
        ]);

        $role = Role::create([
            'name' => $validated['name'],
            'display_name' => $validated['display_name'],
            'description' => $validated['description'],
            'is_system' => false,
        ]);

        // Gán phân quyền
        if (!empty($validated['permissions'])) {
            $role->permissions()->attach($validated['permissions']);
        }

        return redirect()->route('admin.permissions.index')
            ->with('success', 'Tạo vai trò mới thành công!');
    }

    /**
     * Xóa vai trò
     */
    public function destroy_role(Role $role)
    {
        if ($role->is_system) {
            return redirect()->route('admin.permissions.index')
                ->with('error', 'Không thể xóa vai trò hệ thống.');
        }

        // Kiểm tra xem có user nào dùng role này không
        if ($role->users()->exists()) {
            return redirect()->route('admin.permissions.index')
                ->with('error', 'Không thể xóa vai trò vì còn user sử dụng.');
        }

        $role->permissions()->detach();
        $role->delete();

        return redirect()->route('admin.permissions.index')
            ->with('success', 'Xóa vai trò thành công!');
    }

    /**
     * Danh sách quyền
     */
    public function permissions_list()
    {
        $permissions = Permission::orderBy('group_name')->orderBy('name')->paginate(20);
        $permissionsByGroup = Permission::orderBy('group_name')->orderBy('name')->get()->groupBy('group_name');

        return view('admin.permissions.list', compact('permissions', 'permissionsByGroup'));
    }

    /**
     * Tạo quyền mới
     */
    public function create_permission()
    {
        $groups = Permission::distinct('group_name')->pluck('group_name')->toArray();
        return view('admin.permissions.create_permission', compact('groups'));
    }

    /**
     * Lưu quyền mới
     */
    public function store_permission(Request $request)
    {
        $validated = $request->validate([
            'code' => 'required|string|max:255|unique:permissions,code',
            'name' => 'required|string|max:255',
            'group_name' => 'required|string|max:255',
        ]);

        Permission::create($validated);

        return redirect()->route('admin.permissions.list')
            ->with('success', 'Tạo quyền mới thành công!');
    }

    /**
     * Xóa quyền
     */
    public function destroy_permission(Permission $permission)
    {
        if (DB::table('role_permissions')->where('permission_id', $permission->id)->exists()) {
            return redirect()->route('admin.permissions.list')
                ->with('error', 'Không thể xóa quyền vì nó đang được sử dụng bởi các vai trò.');
        }

        if (DB::table('user_permission_revokes')->where('permission_id', $permission->id)->exists()) {
            return redirect()->route('admin.permissions.list')
                ->with('error', 'Không thể xóa quyền vì nó đang được thu hồi trên một số người dùng.');
        }

        $permission->delete();

        return redirect()->route('admin.permissions.list')
            ->with('success', 'Xóa quyền thành công!');
    }
}
