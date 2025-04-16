<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\TransactionIcon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class TransactionIconController extends Controller
{
    public function index()
    {
        return response()->json(TransactionIcon::all());
    }

    public function store(Request $request)
    {
        $request->validate([
            'type' => 'required|string',
            'icon' => 'required|image|mimes:jpeg,png,jpg,svg|max:2048'
        ]);

        $iconPath = $request->file('icon')->store('icons', 'public');

        $icon = TransactionIcon::create([
            'type' => $request->type,
            'icon' => $iconPath
        ]);

        return response()->json(['message' => 'Icon saved', 'data' => $icon], 201);
    }

    public function show($id)
    {
        $icon = TransactionIcon::findOrFail($id);
        return response()->json($icon);
    }

    public function destroy($id)
    {
        $icon = TransactionIcon::findOrFail($id);
        Storage::disk('public')->delete($icon->icon);
        $icon->delete();

        return response()->json(['message' => 'Icon deleted']);
    }
}
