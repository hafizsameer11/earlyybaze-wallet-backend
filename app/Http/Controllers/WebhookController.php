<?php

namespace App\Http\Controllers;

use App\Models\VirtualAccount;
use App\Models\WebhookResponse;
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
        $account->available_balance = $account->available_balance + $request->amount;
        $account->save();
        $webhook = WebhookResponse::create($request->all());
        return response()->json(['message' => 'Webhook received'], 200);
    }
}
