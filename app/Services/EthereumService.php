<?php

namespace App\Services;

use App\Helpers\BlockChainHelper;
use App\Models\DepositAddress;
use App\Models\GasFeeLog;
use App\Models\MasterWallet;
use App\Models\MasterWalletTransaction;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class EthereumService
{
    // use BlockChainHelper;

    /**
     * Transfer asset from user's virtual account to master wallet.
     *
     * @param $virtualAccount
     * @param $amount
     * @return array
     * @throws \Exception
     */

    public function transferToMasterWallet($virtualAccount, $amount)
    {
        $user = $virtualAccount->user;
        $currency = strtoupper($virtualAccount->currency);

        $deposit = DepositAddress::where('virtual_account_id', $virtualAccount->id)->firstOrFail();
        $fromAddress = $deposit->address;
        $fromPrivateKey = Crypt::decryptString($deposit->private_key);

        $masterWallet = MasterWallet::where('blockchain', 'ethereum')->firstOrFail();
        $toAddress = $masterWallet->address;

        // 1. Estimate gas
        $gasEstimation = BlockChainHelper::estimateGasFee($fromAddress, $toAddress, $amount, $currency);
        $requiredGasWei = bcmul($gasEstimation['gasPrice'], $gasEstimation['gasLimit']);
        $requiredGasEth = bcdiv($requiredGasWei, bcpow('10', '18'), 18);

        // 2. Check ETH balance of user address
        $ethBalance = BlockChainHelper::checkAddressBalance($fromAddress, 'ethereum');
        $ethBalance = $ethBalance['balance'];
        if ($ethBalance < $requiredGasEth) {
            // 3. Top-up gas if insufficient
            $tx = $this->topUpUserForGas($masterWallet, $fromAddress, $requiredGasEth);
            $txDetails = $this->getTransactionDetails($tx['txId']);

            if (!($txDetails['status'] ?? false)) {
                throw new \Exception("Gas top-up failed. Cannot proceed.");
            }

            // 4. Log actual gas used for top-up
            $this->logActualGasFee($user->id, $tx['txId'], 'ETH', 'gas-topup');
        }

        // 5. Perform transfer to master wallet
        return $this->executeAssetTransfer($fromPrivateKey, $fromAddress, $toAddress, $amount, $currency, $user, $masterWallet);
    }

    public function topUpUserForGas($masterWallet, $toAddress, $requiredGasEth)
    {
        $fromPrivateKey = Crypt::decryptString($masterWallet->private_key);
        $bufferedAmount = bcadd($requiredGasEth, '0.0002', 18);

        $payload = [
            'fromPrivateKey' => $fromPrivateKey,
            'to' => $toAddress,
            'amount' => (string) $bufferedAmount,
            'currency' => 'ETH',
        ];

        $response = Http::withHeaders(['x-api-key' => config('tatum.api_key')])
            ->post(config('tatum.base_url') . '/ethereum/transaction', $payload);
        Log::info('Top-up response: for address ' . $toAddress, ['response' => $response->json()]);
        if ($response->failed()) {
            throw new \Exception("Top-up failed: " . $response->body());
        }

        return $response->json();
    }

    public function getTransactionDetails($txHash)
    {
        $response = Http::withHeaders(['x-api-key' => config('tatum.api_key')])
            ->get(config('tatum.base_url') . "/ethereum/transaction/{$txHash}");

        if ($response->failed()) {
            throw new \Exception("Failed to fetch tx details: " . $response->body());
        }

        return $response->json();
    }

    public function executeAssetTransfer($fromPrivateKey, $fromAddress, $toAddress, $amount, $currency, $user, $masterWallet)
    {
        $gasfee = BlockChainHelper::estimateGasFee($fromAddress, $toAddress, $amount, $currency);
        $gasLimit = (int) $gasfee['gasLimit'] + 70000;
        $gasPriceGwei = (string) max(1, intval(ceil(intval($gasfee['gasPrice']) / 1e9)));

        $payload = [
            'fromPrivateKey' => $fromPrivateKey,
            'to' => $toAddress,
            'amount' => (string) $amount,
            'currency' => $currency,
            'fee' => [
                'gasLimit' => (string) $gasLimit,
                'gasPrice' => $gasPriceGwei
            ]
        ];

        $response = Http::withHeaders(['x-api-key' => config('tatum.api_key')])
            ->post(config('tatum.base_url') . '/ethereum/transaction', $payload);
        //log response
        Log::info('Transfer response: for address ' . $toAddress, ['response' => $response->json()]);
        if ($response->failed()) {
            throw new \Exception("Transfer failed: " . $response->body());
        }

        $tx = $response->json();
        $txHash = $tx['txId'] ?? null;

        MasterWalletTransaction::create([
            'user_id' => $user->id,
            'master_wallet_id' => $masterWallet->id,
            'blockchain' => 'ethereum',
            'currency' => $currency,
            'to_address' => $toAddress,
            'amount' => $amount,
            'fee' => '0',
            'tx_hash' => $txHash,
        ]);

        $this->logActualGasFee($user->id, $txHash, $currency, 'transfer');

        return $tx;
    }

    public function logActualGasFee($userId, $txHash, $currency, $type)
    {
        $txDetails = $this->getTransactionDetails($txHash);
        $gasUsed = $txDetails['gasUsed'] ?? null;
        $gasPrice = $txDetails['gasPrice'] ?? null;

        if ($gasUsed && $gasPrice) {
            $feeWei = bcmul($gasUsed, $gasPrice);
            $feeEth = bcdiv($feeWei, bcpow('10', '18'), 18);

            GasFeeLog::create([
                'user_id' => $userId,
                'blockchain' => 'ethereum',
                'estimated_fee' => $feeEth,
                'fee_currency' => $currency,
                'tx_type' => $type,
                'tx_hash' => $txHash,
            ]);
        }
    }
    public function transferToExternalAddress(string $toAddress, string $amount, string $currency = 'ETH', array $fee = [])
    {
        // Get master wallet and decrypt private key
        $masterWallet = MasterWallet::where('blockchain', 'ETHEREUM')->firstOrFail();
        $fromPrivateKey = Crypt::decryptString($masterWallet->private_key);
        $fromAddress = $masterWallet->address;

        // Build payload
        $payload = [
            'fromPrivateKey' => $fromPrivateKey,
            'to' => $toAddress,
            'amount' => (string) $amount,
            'currency' => strtoupper($currency),
        ];

        // Include fee if provided
        if (!empty($fee)) {
            $payload['fee'] = [
                'gasLimit' => (string) $fee['gasLimit'],
                'gasPrice' => (string) $fee['gasPrice'],
            ];
        }

        // Send transaction
        $response = Http::withHeaders([
            'x-api-key' => config('tatum.api_key'),
        ])->post(config('tatum.base_url') . '/ethereum/transaction', $payload);

        if ($response->failed()) {
            throw new \Exception("Transfer to external address failed: " . $response->body());
        }

        return $response->json(); // Includes txId
    }
}
