<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\MaintenanceService;
use Illuminate\Http\Request;

class MaintenanceServiceController extends Controller
{
    public function index()
    {
        return response()->json(MaintenanceService::all());
    }

    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|unique:maintenance_services,name',
            'status' => 'in:active,maintenance',
        ]);

        $service = MaintenanceService::create([
            'name' => $request->name,
            'status' => $request->status ?? 'active'
        ]);

        return response()->json(['message' => 'Service created', 'data' => $service]);
    }

    public function show($id)
    {
        return MaintenanceService::findOrFail($id);
    }

    public function update(Request $request, $id)
    {
        $service = MaintenanceService::findOrFail($id);

        $request->validate([
            'name' => 'required|string|unique:maintenance_services,name,' . $id,
            'status' => 'in:active,maintenance',
        ]);

        $service->update($request->only('name', 'status'));

        return response()->json(['message' => 'Service updated', 'data' => $service]);
    }

    public function destroy($id)
    {
        $service = MaintenanceService::findOrFail($id);
        $service->delete();

        return response()->json(['message' => 'Service deleted']);
    }
}
