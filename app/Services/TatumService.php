<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;

class TatumService
{
    protected $apiKey;
    protected $baseUrl;

    public function __construct()
    {
        $this->apiKey = config('tatum.api_key');
        $this->baseUrl = config('tatum.base_url');
    }

    /**
     * Create a virtual account in Tatum.
     *
     * @param string $currency
     * @param string $customerId
     * @return array
     */

    public function createWallet(string $blockchain): array
    {
        $endpoint = match ($blockchain) {
            'bitcoin' => '/bitcoin/wallet',                     // Bitcoin
            'ethereum' => '/ethereum/wallet',                   // Ethereum
            'xrp' => '/xrp/account',                            // XRP (Ripple)
            'xlm' => '/xlm/account',                            // Stellar
            'litecoin' => '/litecoin/wallet',                   // Litecoin
            'bitcoin-cash' => '/bcash/wallet',                  // Bitcoin Cash
            'binance-smart-chain' => '/v3/bsc/wallet',             // Binance Smart Chain
            'solana' => '/v3/solana/wallet',                       // Solana
            'tron' => '/v3/tron/wallet',                           // Tron
            'polygon' => '/v3/polygon/wallet',                     // Polygon (MATIC)
            'dogecoin' => '/v3/dogecoin/wallet',                   // Dogecoin
            'celo' => '/v3/celo/wallet',                           // Celo
            'algorand' => '/v3/algorand/wallet',                   // Algorand
            default => throw new \Exception("Unsupported blockchain: $blockchain"),
        };

        $response = Http::withHeaders(['x-api-key' => $this->apiKey])
            ->get("{$this->baseUrl}{$endpoint}");

        if ($response->failed()) {
            throw new \Exception('Failed to create wallet: ' . $response->body());
        }

        return $response->json();
    }
    public function createVirtualAccount(string $currency, string $customerId): array
    {
        $response = Http::withHeaders([
            'x-api-key' => $this->apiKey,
        ])->post("{$this->baseUrl}/ledger/account", [
                    'currency' => $currency,
                    'customerId' => $customerId,
                ]);

        return $response->json();
    }

    /**
     * Generate a deposit address for a virtual account.
     *
     * @param string $accountId
     * @return array
     */
    public function generateDepositAddress(string $accountId): array
    {
        $response = Http::withHeaders([
            'x-api-key' => $this->apiKey,
        ])->post("{$this->baseUrl}/ledger/account/{$accountId}/address");

        return $response->json();
    }

    /**
     * Transfer funds between virtual accounts.
     *
     * @param string $fromAccount
     * @param string $toAccount
     * @param string $amount
     * @param string $currency
     * @return array
     */
    public function transferFunds(string $fromAccount, string $toAccount, string $amount, string $currency): array
    {
        $response = Http::withHeaders([
            'x-api-key' => $this->apiKey,
        ])->post("{$this->baseUrl}/ledger/transaction", [
                    'senderAccountId' => $fromAccount,
                    'recipientAccountId' => $toAccount,
                    'amount' => $amount,
                    'currency' => $currency,
                ]);

        return $response->json();
    }
}
