<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class TatumV4SubscriptionService
{
    /**
     * v4 templateId / finality / conditions are only valid on TRON and EVM chains (Tatum API).
     */
    private function v4ChainSupportsSubscriptionExtras(string $v4Chain): bool
    {
        $noExtras = ['bitcoin-', 'litecoin-core-', 'doge-', 'ripple-', 'solana-', 'tezos-'];
        foreach ($noExtras as $prefix) {
            if (str_starts_with($v4Chain, $prefix)) {
                return false;
            }
        }

        return true;
    }

    private function subscriptionBaseUrl(): string
    {
        return rtrim(config('tatum.v4_base_url', 'https://api.tatum.io/v4'), '/')
            .'/subscription?type='.urlencode(config('tatum_v2.v4_network_type', 'mainnet'));
    }

    /**
     * @return array{type: string, attr: array<string, mixed>, templateId?: string}
     */
    private function incomingNativePayload(string $v4Chain, string $address): array
    {
        $payload = [
            'type' => 'INCOMING_NATIVE_TX',
            'attr' => [
                'chain' => $v4Chain,
                'address' => $address,
                'url' => config('tatum.webhook_v2_url'),
            ],
        ];
        if ($this->v4ChainSupportsSubscriptionExtras($v4Chain)) {
            $payload['templateId'] = 'enriched';
        }

        return $payload;
    }

    /**
     * @return array{type: string, attr: array<string, mixed>, templateId?: string}
     */
    private function incomingFungiblePayload(string $v4Chain, string $address): array
    {
        $payload = [
            'type' => 'INCOMING_FUNGIBLE_TX',
            'attr' => [
                'chain' => $v4Chain,
                'address' => $address,
                'url' => config('tatum.webhook_v2_url'),
            ],
        ];
        if ($this->v4ChainSupportsSubscriptionExtras($v4Chain)) {
            $payload['templateId'] = 'enriched';
        }

        return $payload;
    }

    /**
     * @return array{ok: true, id: string}|array{ok: false, status: int, body: mixed}
     */
    private function postSubscription(string $url, array $payload): array
    {
        $response = Http::withHeaders([
            'x-api-key' => config('tatum.api_key'),
            'Content-Type' => 'application/json',
        ])->post($url, $payload);

        if ($response->failed()) {
            Log::error('Tatum v4 subscription failed', [
                'url' => $url,
                'body' => $response->body(),
                'status' => $response->status(),
            ]);

            return [
                'ok' => false,
                'status' => $response->status(),
                'body' => $response->json() ?? $response->body(),
            ];
        }

        $json = $response->json();
        $id = $json['id'] ?? $json['data']['id'] ?? null;
        if ($id === null || $id === '') {
            return [
                'ok' => false,
                'status' => $response->status(),
                'body' => $json,
            ];
        }

        return ['ok' => true, 'id' => (string) $id];
    }

    /**
     * @return array{ok: true, id: string}|array{ok: false, status: int, body: mixed}
     */
    public function createIncomingNativeSubscription(string $v4Chain, string $address): array
    {
        return $this->postSubscription($this->subscriptionBaseUrl(), $this->incomingNativePayload($v4Chain, $address));
    }

    /**
     * @return array{ok: true, id: string}|array{ok: false, status: int, body: mixed}
     */
    public function createIncomingFungibleSubscription(string $v4Chain, string $address): array
    {
        return $this->postSubscription($this->subscriptionBaseUrl(), $this->incomingFungiblePayload($v4Chain, $address));
    }

    public function subscribeNative(string $v4Chain, string $address): ?string
    {
        $result = $this->createIncomingNativeSubscription($v4Chain, $address);

        return $result['ok'] ? $result['id'] : null;
    }

    public function subscribeFungible(string $v4Chain, string $address): ?string
    {
        $result = $this->createIncomingFungibleSubscription($v4Chain, $address);

        return $result['ok'] ? $result['id'] : null;
    }
}
