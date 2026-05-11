<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;

class RoleController extends Controller
{
    /**
     * List all roles with their permissions.
     */
    public function index()
    {
        $roles = Role::where('guard_name', 'web')
            ->with('permissions')
            ->withCount('users')
            ->get();

        $permissions = Permission::where('guard_name', 'web')
            ->orderBy('name')
            ->get()
            ->groupBy(function ($permission) {
                return explode('.', $permission->name)[0];
            });

        return Inertia::render('Admin/Roles/Index', [
            'roles'       => $roles,
            'permissions' => $permissions,
        ]);
    }

    /**
     * Store a new role.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name'          => ['required', 'string', 'max:255', 'unique:roles,name'],
            'permissions'   => ['nullable', 'array'],
            'permissions.*' => ['exists:permissions,id'],
        ]);

        $role = Role::create([
            'name'       => $validated['name'],
            'guard_name' => 'web',
        ]);

        if (!empty($validated['permissions'])) {
            $perms = Permission::whereIn('id', $validated['permissions'])->get();
            $role->syncPermissions($perms);
        }

        return back()->with('success', "Tạo vai trò '{$role->name}' thành công.");
    }

    /**
     * Update role and its permissions.
     */
    public function update(Request $request, Role $role)
    {
        // Cannot edit super-admin role
        if ($role->name === 'super-admin') {
            return back()->with('error', 'Không thể chỉnh sửa vai trò Super Admin.');
        }

        $validated = $request->validate([
            'name'          => ['required', 'string', 'max:255', "unique:roles,name,{$role->id}"],
            'permissions'   => ['nullable', 'array'],
            'permissions.*' => ['exists:permissions,id'],
        ]);

        $role->update(['name' => $validated['name']]);

        $perms = !empty($validated['permissions'])
            ? Permission::whereIn('id', $validated['permissions'])->get()
            : [];
        $role->syncPermissions($perms);

        return back()->with('success', "Cập nhật vai trò '{$role->name}' thành công.");
    }

    /**
     * Delete a role.
     */
    public function destroy(Role $role)
    {
        if (in_array($role->name, ['super-admin', 'admin', 'staff'])) {
            return back()->with('error', 'Không thể xóa vai trò mặc định.');
        }

        $role->delete();

        return back()->with('success', 'Xóa vai trò thành công.');
    }
}
