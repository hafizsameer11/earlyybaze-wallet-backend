<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Module;
use Illuminate\Http\Request;

class ModuleController extends Controller
{
    public function index()
    {
        return response()->json(Module::all());
    }

    public function store(Request $request)
    {
        $request->validate(['name' => 'required']);

        $module = Module::create(['name' => $request->name]);
        return response()->json(['message' => 'Module created', 'module' => $module]);
    }

    public function destroy($id)
    {
        Module::findOrFail($id)->delete();
        return response()->json(['message' => 'Module deleted']);
    }
}
