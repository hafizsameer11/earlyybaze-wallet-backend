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
