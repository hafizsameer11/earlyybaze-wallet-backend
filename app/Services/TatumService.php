<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

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

    public function getUserAccounts($userId)
    {
        $externalId = $userId; // Ensure this matches the externalId used when creating VAs

        // Fetch all Virtual Accounts for the user with pagination
        $response = Http::withHeaders([
            'x-api-key' => $this->apiKey
        ])->get("{$this->baseUrl}/ledger/account/customer/$externalId", [
            'pageSize' => 50 // Fetch up to 50 accounts per request
        ]);

        if ($response->failed()) {
            Log::error('Failed to fetch user accounts: ' . $response->body());
            throw new \Exception('Failed to fetch user accounts');
        }

        return $response->json();
    }

    public function createWallet(string $blockchain): array
    {
        $endpoint = match ($blockchain) {
            'bitcoin' => '/bitcoin/wallet',                     // Bitcoin
            'bsc' => '/bsc/wallet',                     // Bitcoin
            'avalanche' => '/avalanche/wallet',                     // Bitcoin
            'fantom' => '/fantom/wallet',                     // Bitcoin
            'ethereum' => '/ethereum/wallet',                   // Ethereum
            'xrp' => '/xrp/account',                            // XRP (Ripple)
            'xlm' => '/xlm/account',                            // Stellar
            'litecoin' => '/litecoin/wallet',                   // Litecoin
            'bitcoin-cash' => '/bcash/wallet',                  // Bitcoin Cash
            'binance-smart-chain' => '/v3/bsc/wallet',             // Binance Smart Chain
            'solana' => '/solana/wallet',                       // Solana
            'tron' => '/tron/wallet',                           // Tron
            'polygon' => '/polygon/wallet',                     // Polygon (MATIC)
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
    public function estimateGasFee($chain, $fromAddress, $toAddress, $amount)
    {
        $response = Http::withHeaders([
            'x-api-key' => $this->apiKey
        ])->post("{$this->baseUrl}/v3/blockchain/estimate/gas", [
            "chain" => strtoupper($chain),
            "from" => $fromAddress,
            "to" => $toAddress,
            "amount" => (string) $amount
        ]);

        Log::info("Gas Fee Estimation Response: " . json_encode($response->json()));

        if ($response->failed()) {
            Log::error("Failed to estimate gas fee: " . $response->body());
            throw new \Exception('Failed to estimate gas fee.' . $response->body());
        }

        return $response->json();
    }

    /**
     * Send an On-Chain Transaction.
     */
    public function sendBlockchainTransaction(array $transactionData)
    {
        $response = Http::withHeaders([
            'x-api-key' => $this->apiKey
        ])->post("{$this->baseUrl}/v3/blockchain/transaction", $transactionData);

        Log::info("On-Chain Transaction Response: " . json_encode($response->json()));

        if ($response->failed()) {
            Log::error("On-Chain transaction failed: " . $response->body());
            throw new \Exception('On-Chain transaction failed.');
        }

        return $response->json();
    }
}
