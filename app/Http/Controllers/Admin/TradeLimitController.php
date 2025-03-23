<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\TradeLimit;
use Illuminate\Http\Request;

class TradeLimitController extends Controller
{
    public function index()
    {
        return response()->json(TradeLimit::all());
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'type' => 'nullable|string',
            'amount' => 'nullable|string',
            'status' => 'in:active,inactive'
        ]);

        $tradeLimit = TradeLimit::create($data);

        return response()->json([
            'message' => 'Trade Limit created successfully.',
            'data' => $tradeLimit
        ], 201);
    }

    public function show($id)
    {
        $tradeLimit = TradeLimit::find($id);

        if (!$tradeLimit) {
            return response()->json(['message' => 'Not found'], 404);
        }

        return response()->json($tradeLimit);
    }

    public function update(Request $request, $id)
    {
        $tradeLimit = TradeLimit::find($id);

        if (!$tradeLimit) {
            return response()->json(['message' => 'Not found'], 404);
        }

        $data = $request->validate([
            'type' => 'nullable|string',
            'amount' => 'nullable|string',
            'status' => 'in:active,inactive'
        ]);

        $tradeLimit->update($data);

        return response()->json([
            'message' => 'Trade Limit updated successfully.',
            'data' => $tradeLimit
        ]);
    }

    public function destroy($id)
    {
        $tradeLimit = TradeLimit::find($id);

        if (!$tradeLimit) {
            return response()->json(['message' => 'Not found'], 404);
        }

        $tradeLimit->delete();

        return response()->json(['message' => 'Trade Limit deleted successfully.']);
    }

    public function getByType($type)
    {
        $tradeLimits = TradeLimit::where('type', $type)->get();

        if ($tradeLimits->isEmpty()) {
            return response()->json(['message' => 'No records found for this type.'], 404);
        }

        return response()->json($tradeLimits);
    }
}
