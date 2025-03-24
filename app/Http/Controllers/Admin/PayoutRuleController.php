<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\PayoutRule;
use Illuminate\Http\Request;

class PayoutRuleController extends Controller
{
    public function index()
    {
        return response()->json(PayoutRule::all());
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'trigger_event' => 'required|string',
            'trade_amount' => 'nullable|numeric',
            'time_frame' => 'required|string',
            'action_type' => 'required|string',
            'wallet_type' => 'required|string',
            'payout_amount' => 'required|numeric',
            'description' => 'nullable|string',
            'status' => 'in:active,inactive'
        ]);

        $rule = PayoutRule::create($data);

        return response()->json([
            'message' => 'Payout Rule created successfully.',
            'rule' => $rule
        ]);
    }

    public function show($id)
    {
        $rule = PayoutRule::find($id);
        if (!$rule) return response()->json(['message' => 'Rule not found'], 404);
        return response()->json($rule);
    }

    public function update(Request $request, $id)
    {
        $rule = PayoutRule::find($id);
        if (!$rule) return response()->json(['message' => 'Rule not found'], 404);

        $data = $request->validate([
            'trigger_event' => 'required|string',
            'trade_amount' => 'nullable|numeric',
            'time_frame' => 'required|string',
            'action_type' => 'required|string',
            'wallet_type' => 'required|string',
            'payout_amount' => 'required|numeric',
            'description' => 'nullable|string',
            'status' => 'in:active,inactive'
        ]);

        $rule->update($data);

        return response()->json([
            'message' => 'Payout Rule updated successfully.',
            'rule' => $rule
        ]);
    }

    public function destroy($id)
    {
        $rule = PayoutRule::find($id);
        if (!$rule) return response()->json(['message' => 'Rule not found'], 404);

        $rule->delete();
        return response()->json(['message' => 'Payout Rule deleted']);
    }

    public function getByEvent($event)
    {
        $rules = PayoutRule::where('trigger_event', $event)->get();
        return response()->json($rules);
    }
}
