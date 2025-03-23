<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AmlRule;
use Illuminate\Http\Request;

class AmlRuleController extends Controller
{
    public function index()
    {
        return response()->json(AmlRule::all());
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'transaction_type' => 'required|string',
            'condition_operator' => 'required|string',
            'amount' => 'required|numeric',
            'time_frame' => 'required|string',
            'trigger_count' => 'nullable|integer',
            'action' => 'required|string',
            'action_message' => 'nullable|string',
            'status' => 'in:active,inactive'
        ]);

        $rule = AmlRule::create($validated);

        return response()->json([
            'message' => 'AML Rule created successfully.',
            'data' => $rule
        ], 201);
    }

    public function show($id)
    {
        $rule = AmlRule::find($id);

        if (!$rule) {
            return response()->json(['message' => 'Rule not found'], 404);
        }

        return response()->json($rule);
    }

    public function update(Request $request, $id)
    {
        $rule = AmlRule::find($id);

        if (!$rule) {
            return response()->json(['message' => 'Rule not found'], 404);
        }

        $validated = $request->validate([
            'transaction_type' => 'required|string',
            'condition_operator' => 'required|string',
            'amount' => 'required|numeric',
            'time_frame' => 'required|string',
            'trigger_count' => 'nullable|integer',
            'action' => 'required|string',
            'action_message' => 'nullable|string',
            'status' => 'in:active,inactive'
        ]);

        $rule->update($validated);

        return response()->json([
            'message' => 'AML Rule updated successfully.',
            'data' => $rule
        ]);
    }

    public function destroy($id)
    {
        $rule = AmlRule::find($id);

        if (!$rule) {
            return response()->json(['message' => 'Rule not found'], 404);
        }

        $rule->delete();

        return response()->json(['message' => 'Rule deleted successfully.']);
    }

    public function getByTransactionType($type)
    {
        $rules = AmlRule::where('transaction_type', $type)->get();

        if ($rules->isEmpty()) {
            return response()->json(['message' => 'No rules found for this type.'], 404);
        }

        return response()->json($rules);
    }
}
