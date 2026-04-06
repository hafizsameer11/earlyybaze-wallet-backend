<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class TatumV4SubscriptionService
{
    public function subscribeNative(string $v4Chain, string $address): ?string
    {
        $url = rtrim(config('tatum.v4_base_url', 'https://api.tatum.io/v4'), '/')
            .'/subscription?type='.urlencode(config('tatum_v2.v4_network_type', 'mainnet'));

        $payload = [
            'type' => 'INCOMING_NATIVE_TX',
            'attr' => [
                'chain' => $v4Chain,
                'address' => $address,
                'url' => config('tatum.webhook_v2_url'),
            ],
            'templateId' => 'enriched',
        ];

        return $this->postAndReturnId($url, $payload);
    }

    public function subscribeFungible(string $v4Chain, string $address, string $contractAddress): ?string
    {
        $url = rtrim(config('tatum.v4_base_url', 'https://api.tatum.io/v4'), '/')
            .'/subscription?type='.urlencode(config('tatum_v2.v4_network_type', 'mainnet'));

        $payload = [
            'type' => 'INCOMING_FUNGIBLE_TX',
            'attr' => [
                'chain' => $v4Chain,
                'address' => $address,
                'url' => config('tatum.webhook_v2_url'),
                'contractAddress' => $contractAddress,
            ],
            'templateId' => 'enriched',
        ];

        return $this->postAndReturnId($url, $payload);
    }

    private function postAndReturnId(string $url, array $payload): ?string
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

            return null;
        }

        $json = $response->json();

        return $json['id'] ?? $json['data']['id'] ?? null;
    }
}
