<?php

namespace App\Http\Controllers;

use App\Jobs\AssignDepositAddress;
use App\Models\User;
use App\Models\VirtualAccount;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class DepositAddressController extends Controller
{
     public function assignAll(User $user): JsonResponse
    {
        try {
            // If you only want accounts that don't have an address yet,
            // uncomment the whereNull() line and make sure the column name matches.
            $virtualAccounts = VirtualAccount::query()
                ->where('user_id', $user->id)
                // ->whereNull('deposit_address')
            ->get();

            if ($virtualAccounts->isEmpty()) {
                return response()->json([
                    'ok' => true,
                    'message' => 'No virtual accounts found for this user.',
                    'user_id' => $user->id,
                    'dispatched' => [],
                    'count' => 0,
                ], 200);
            }

            $dispatched = [];

            foreach ($virtualAccounts as $va) {
                // Your job already accepts a VirtualAccount instance
                dispatch(new AssignDepositAddress($va));

                $dispatched[] = [
                    'virtual_account_db_id' => $va->id,
                    'account_id' => $va->account_id,
                    'currency' => $va->currency,
                    'blockchain' => $va->blockchain,
                ];
            }

            return response()->json([
                'ok' => true,
                'message' => 'AssignDepositAddress jobs dispatched.',
                'user_id' => $user->id,
                'count' => count($dispatched),
                'dispatched' => $dispatched,
            ], 200);

        } catch (\Throwable $e) {
            Log::error('Failed to dispatch AssignDepositAddress jobs', [
                'user_id' => $user->id,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'ok' => false,
                'message' => 'Failed to dispatch jobs.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}
