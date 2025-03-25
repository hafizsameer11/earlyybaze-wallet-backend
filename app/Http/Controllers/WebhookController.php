<?php

namespace App\Http\Controllers;

use App\Models\VirtualAccount;
use App\Models\WebhookResponse;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class WebhookController extends Controller
{
 


    public function webhook(Request $request)
    {
        Log::info($request->all());

        if (!$request->has('accountId')) {
            return response()->json(['message' => 'Account ID is required'], 400);
        }

        $account = VirtualAccount::where('account_id', $request->accountId)->first();

        if (!$account) {
            return response()->json(['message' => 'Virtual account not found'], 404);
        }

        // Update account balance
        $account->available_balance += $request->amount;
        $account->save();

        // Store the webhook response
        $webhook = WebhookResponse::create([
            'account_id'         => $request->accountId,
            'subscription_type'  => $request->subscriptionType,
            'amount'             => $request->amount,
            'reference'          => $request->reference,
            'currency'           => $request->currency,
            'tx_id'              => $request->txId,
            'block_height'       => $request->blockHeight,
            'block_hash'         => $request->blockHash,
            'from_address'       => $request->from,
            'to_address'         => $request->to,
            'transaction_date'   => Carbon::createFromTimestampMs($request->date),
            'index'              => $request->index,
        ]);

        return response()->json(['message' => 'Webhook received'], 200);
    }

}
