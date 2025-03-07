<?php

namespace App\Http\Controllers;

use App\Models\ReferalPayment;
use Illuminate\Http\Request;

class ReferalPaymentController extends Controller
{
    public function index()
    {
        return response()->json(ReferalPayment::all(), 200);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $request->validate([
            'type' => 'nullable|string',
            'amount' => 'nullable|string',
            'percentage' => 'nullable|string',
        ]);

        $payment = ReferalPayment::create($request->all());

        return response()->json(['message' => 'Referral payment created successfully', 'data' => $payment], 201);
    }

    /**
     * Display the specified resource.
     */
    public function show($id)
    {
        $payment = ReferalPayment::find($id);

        if (!$payment) {
            return response()->json(['message' => 'Referral payment not found'], 404);
        }

        return response()->json($payment, 200);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, $id)
    {
        $payment = ReferalPayment::find($id);

        if (!$payment) {
            return response()->json(['message' => 'Referral payment not found'], 404);
        }

        $request->validate([
            'type' => 'nullable|string',
            'amount' => 'nullable|string',
            'percentage' => 'nullable|string',
        ]);

        $payment->update($request->all());

        return response()->json(['message' => 'Referral payment updated successfully', 'data' => $payment], 200);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy($id)
    {
        $payment = ReferalPayment::find($id);

        if (!$payment) {
            return response()->json(['message' => 'Referral payment not found'], 404);
        }

        $payment->delete();

        return response()->json(['message' => 'Referral payment deleted successfully'], 200);
    }
}
