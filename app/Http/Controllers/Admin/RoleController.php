<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Module;
use App\Models\Role;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class RoleController extends Controller
{
    public function index()
    {
        $roles=Role::with('modules')->get();
        $customRole=['id'=>7,'name'=>'user'];
        $roles->push($customRole);
        return response()->json($roles);
    }

    public function show($id)
    {
        $role = Role::with('modules')->findOrFail($id);
        return response()->json($role);
    }

    public function store(Request $request)
    {
        $request->validate(['name' => 'required|unique:roles']);
        $role = Role::create(['name' => $request->name]);
        return response()->json(['message' => 'Role created', 'role' => $role]);
    }

    public function update(Request $request, $id)
    {
        $role = Role::findOrFail($id);
        $request->validate(['name' => 'required|unique:roles,name,' . $id]);
        $role->update(['name' => $request->name]);
        return response()->json(['message' => 'Role updated', 'role' => $role]);
    }

    public function destroy($id)
    {
        Role::findOrFail($id)->delete();
        return response()->json(['message' => 'Role deleted']);
    }

    public function assignModules(Request $request, $id)
    {
        $role = Role::findOrFail($id);
        $request->validate(['access' => 'required|array']);

        $moduleIds = collect($request->access)->filter()->keys();
        $role->modules()->sync($moduleIds);

        return response()->json(['message' => 'Permissions updated successfully.']);
    }

    public function getRoleModulePermissions($id)
    {
        $role = Role::with('modules')->findOrFail($id);
        $allModules = Module::all();

        $permissions = [];
        foreach ($allModules as $module) {
            $permissions[$module->id] = $role->modules->contains($module->id);
        }

        return response()->json([
            'role_id' => $role->id,
            'role_name' => $role->name,
            'permissions' => $permissions,
            'modules' => $allModules->pluck('name', 'id')
        ]);
    }
    public function getRoleModuleByName($name)
    {
        $auth=Auth::user();
        // if($auth->role!='admin'){
        //     return response()->json(['message' => 'Unauthorized'], 403);
        // }
        $role = Role::with('modules')->where('name', $name)->firstOrFail();
        $allModules = Module::all();

        $permissions = [];
        foreach ($allModules as $module) {
            $permissions[$module->id] = $role->modules->contains($module->id);
        }

        return response()->json([
            'role_id' => $role->id,
            'role_name' => $role->name,
            'permissions' => $permissions,
            'modules' => $allModules->pluck('name', 'id')
        ]);
    }
}
