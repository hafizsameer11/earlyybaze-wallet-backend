<?php

namespace App\Http\Controllers\api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreOnChainTransferLogRequest;
use App\Models\OnChainTransferLog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class OnChainTransferLogController extends Controller
{
      public function store(StoreOnChainTransferLogRequest $request): JsonResponse
    {
        try {
            $data = $request->validated();

            $log = OnChainTransferLog::create([
                'tx' => $data['tx'],
                'received_asset_id' => $data['received_asset_id'],
                'gas_fee' => $data['gas_fee'],
                'address_to_send' => $data['address_to_send'],
            ]);

            // Eager-load the relation if you want it in the response
            $log->load('asset');

            return response()->json([
                'status' => true,
                'message' => 'On-chain transfer logged successfully.',
                'data' => $log,
            ], 201);

        } catch (\Throwable $e) {
            Log::error('Failed to store on-chain transfer log', [
                'error' => $e->getMessage(),
                'payload' => $request->all(),
            ]);

            return response()->json([
                'status' => false,
                'message' => 'Unexpected error while saving the transfer log.',
            ], 500);
        }
    }
}
