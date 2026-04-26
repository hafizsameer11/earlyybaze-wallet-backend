<?php

namespace App\Http\Controllers;

use App\Services\TatumV4SubscriptionService;
use Illuminate\Http\Request;

/**
 * GET: create a Tatum v4 address subscription from the query string (browser-friendly).
 *
 * /api/dev/tatum/v4-subscribe?address=...&chain=bitcoin-mainnet&kind=native
 */
class DevTatumV4SubscribeController extends Controller
{
    public function __invoke(Request $request, TatumV4SubscriptionService $subscriptions)
    {
        $validated = $request->validate([
            'address' => 'required|string|max:256',
            'chain' => 'sometimes|string|max:64',
            'kind' => 'sometimes|string|in:native,fungible',
        ]);

        $webhookUrl = config('tatum.webhook_v2_url');
        if (empty($webhookUrl)) {
            return response()->json([
                'ok' => false,
                'error' => 'Set tatum.webhook_v2_url / TATUM_WEBHOOK_V2_URL before creating subscriptions.',
            ], 422);
        }

        $address = trim($validated['address']);
        $chain = $validated['chain'] ?? config('tatum.v4_btc_chain', 'bitcoin-mainnet');
        $kind = $validated['kind'] ?? 'native';

        $result = $kind === 'fungible'
            ? $subscriptions->createIncomingFungibleSubscription($chain, $address)
            : $subscriptions->createIncomingNativeSubscription($chain, $address);

        if (! $result['ok']) {
            $httpStatus = $result['status'] >= 400 && $result['status'] < 600
                ? $result['status']
                : 502;

            return response()->json([
                'ok' => false,
                'chain' => $chain,
                'address' => $address,
                'kind' => $kind,
                'status' => $result['status'],
                'tatum' => $result['body'],
            ], $httpStatus);
        }

        return response()->json([
            'ok' => true,
            'subscriptionId' => $result['id'],
            'chain' => $chain,
            'address' => $address,
            'kind' => $kind,
            'webhookUrl' => $webhookUrl,
        ]);
    }
}
