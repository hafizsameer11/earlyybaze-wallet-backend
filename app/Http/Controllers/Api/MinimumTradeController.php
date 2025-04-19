<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\MinimumTrade;
use Illuminate\Http\Request;

class MinimumTradeController extends Controller
{
    //
    public function index()
    {
        return response()->json(MinimumTrade::all(), 200);
    }

    // ✅ Show single trade
    public function show($id)
    {
        $trade = MinimumTrade::find($id);
        if (!$trade) {
            return response()->json(['message' => 'Trade not found'], 404);
        }
        return response()->json($trade);
    }

    // ✅ Create trade
    public function store(Request $request)
    {
        $validated = $request->validate([
            'type' => 'required|string',
            'amount' => 'nullable|string',
            'amount_naira' => 'nullable|string',
            'percentage' => 'nullable|string',
            'status' => 'nullable|string',
        ]);

        $trade = MinimumTrade::create($validated);
        return response()->json([
            'message' => 'Minimum trade created successfully.',
            'data' => $trade
        ], 201);
    }

    // ✅ Update trade
    public function update(Request $request, $id)
    {
        $trade = MinimumTrade::find($id);
        if (!$trade) {
            return response()->json(['message' => 'Trade not found'], 404);
        }

        $validated = $request->validate([
            'type' => 'sometimes|string',
            'amount' => 'nullable|string',
            'amount_naira' => 'nullable|string',
            'percentage' => 'nullable|string',
            'status' => 'nullable|string',
        ]);

        $trade->update($validated);
        return response()->json([
            'message' => 'Minimum trade updated successfully.',
            'data' => $trade
        ]);
    }

    // ✅ Delete trade
    public function destroy($id)
    {
        $trade = MinimumTrade::find($id);
        if (!$trade) {
            return response()->json(['message' => 'Trade not found'], 404);
        }

        $trade->delete();
        return response()->json(['message' => 'Minimum trade deleted successfully.']);
    }
}
