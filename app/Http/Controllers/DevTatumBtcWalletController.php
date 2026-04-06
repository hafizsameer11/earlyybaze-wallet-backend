<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Dev-only: create a BTC wallet via Tatum v3 and register v4 INCOMING_NATIVE_TX (no persistence).
 */
class DevTatumBtcWalletController extends Controller
{
    public function createWithV4IncomingNativeSubscription(Request $request)
    {
        if (! config('tatum.dev_btc_wallet_v4_endpoint_enabled')) {
            abort(404);
        }

        $validated = $request->validate([
            'email' => 'required|email',
            'webhook_url' => 'sometimes|nullable|url|max:2048',
            /** mainnet | testnet — passed as ?type= to v4 subscription URL */
            'network' => 'sometimes|string|in:mainnet,testnet',
            /** Override chain id e.g. bitcoin-testnet when using testnet key */
            'chain' => 'sometimes|string|max:64',
            'template_id' => 'sometimes|string|max:128',
        ]);

        $apiKey = config('tatum.api_key');
        $v3Base = rtrim(config('tatum.base_url'), '/');
        $v4Base = rtrim(config('tatum.v4_base_url'), '/');

        $webhookUrl = $validated['webhook_url'] ?? config('tatum.webhook_url');
        if (empty($webhookUrl)) {
            return response()->json([
                'ok' => false,
                'error' => 'No webhook URL: pass webhook_url or set tatum.webhook_url / TATUM default.',
            ], 422);
        }

        $network = $validated['network'] ?? 'mainnet';
        $chain = $validated['chain'] ?? config('tatum.v4_btc_chain');

        try {
            $walletResponse = Http::withHeaders([
                'x-api-key' => $apiKey,
            ])->get("{$v3Base}/bitcoin/wallet");

            if ($walletResponse->failed()) {
                Log::warning('Tatum dev BTC wallet: v3 wallet failed', [
                    'body' => $walletResponse->body(),
                    'status' => $walletResponse->status(),
                ]);

                return response()->json([
                    'ok' => false,
                    'step' => 'create_wallet_v3',
                    'status' => $walletResponse->status(),
                    'body' => $walletResponse->json() ?? $walletResponse->body(),
                ], 502);
            }

            $wallet = $walletResponse->json();
            $address = $wallet['address'] ?? null;

            if (! $address && ! empty($wallet['xpub'])) {
                $addrResponse = Http::withHeaders([
                    'x-api-key' => $apiKey,
                ])->get($v3Base.'/bitcoin/address/'.rawurlencode($wallet['xpub']).'/0');
                if ($addrResponse->successful()) {
                    $address = $addrResponse->json('address');
                }
            }

            if (! $address) {
                return response()->json([
                    'ok' => false,
                    'step' => 'parse_wallet',
                    'hint' => 'No address in /bitcoin/wallet and deriving /bitcoin/address/{xpub}/0 failed.',
                    'has_xpub' => !empty($wallet['xpub']),
                ], 502);
            }

            $subscriptionPayload = [
                'type' => 'INCOMING_NATIVE_TX',
                'attr' => [
                    'chain' => $chain,
                    'address' => $address,
                    'url' => $webhookUrl,
                ],
            ];
            if (! empty($validated['template_id'])) {
                $subscriptionPayload['templateId'] = $validated['template_id'];
            }

            $subUrl = "{$v4Base}/subscription?type={$network}";
            $subscriptionResponse = Http::withHeaders([
                'x-api-key' => $apiKey,
                'Content-Type' => 'application/json',
            ])->post($subUrl, $subscriptionPayload);

            $subOk = $subscriptionResponse->successful();
            $subJson = $subscriptionResponse->json();

            return response()->json([
                'ok' => $subOk,
                'email' => $validated['email'],
                'network_query' => $network,
                'chain' => $chain,
                'address' => $address,
                'v4_subscription' => [
                    'request_url' => $subUrl,
                    'http_status' => $subscriptionResponse->status(),
                    'body' => $subJson ?? $subscriptionResponse->body(),
                ],
                'wallet_created' => true,
                'v4_subscription_created' => $subOk,
                'note' => $subOk
                    ? 'v4 subscription created; v4_subscription.body should include subscription id.'
                    : 'Wallet was created in memory only. v4 subscription failed — check credits/plan, chain vs API key network, and webhook URL. See v4_subscription.body.',
            ], $subOk ? 201 : 200);
        } catch (\Throwable $e) {
            Log::error('DevTatumBtcWalletController: ' . $e->getMessage(), ['e' => $e]);

            return response()->json([
                'ok' => false,
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}
