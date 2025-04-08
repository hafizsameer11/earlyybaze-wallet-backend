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

class BscTransferService
{
    public static function transferToMasterWalletWithAutoFeeHandling($virtualAccount, $amount)
    {
        $user = $virtualAccount->user;
        $currency = strtoupper($virtualAccount->currency); // BNB, USDT_BSC, USDC_BSC

        $deposit = DepositAddress::where('virtual_account_id', $virtualAccount->id)->firstOrFail();
        $fromAddress = $deposit->address;
        $fromPrivateKey = Crypt::decryptString($deposit->private_key);

        $masterWallet = MasterWallet::where('blockchain', 'BSC')->firstOrFail();
        $toAddress = $masterWallet->address;

        // 1. Estimate gas
        $gasEstimation = BlockChainHelper::estimateGasFee($fromAddress, $toAddress, $amount, $currency, 'BSC');
        $requiredGasWei = bcmul($gasEstimation['gasPrice'], $gasEstimation['gasLimit']);
        $requiredGasBNB = bcdiv($requiredGasWei, bcpow('10', '18'), 18);

        // 2. Check native balance (BNB)
        $bnbBalance = BlockChainHelper::checkAddressBalance($fromAddress, 'bsc');

        // 3. If not enough, top-up
        if ($bnbBalance < $requiredGasBNB) {
            $topupTx = self::topUpUserWithGas($masterWallet, $fromAddress, $requiredGasBNB);
            $topupDetails = self::checkTransactionDetails($topupTx['txId']);

            if (!($topupDetails['status'] ?? false)) {
                throw new \Exception("Top-up failed. Cannot proceed.");
            }

            // BlockChainHelper::logActualGasFee($user->id, $topupTx['txId'], 'BNB', 'gas-topup');
        }

        // 4. Proceed to master transfer
        return self::transferAssetsToMaster($fromPrivateKey, $fromAddress, $toAddress, $amount, $currency, $user, $masterWallet);
    }

    public static function topUpUserWithGas($masterWallet, $toAddress, $requiredGasBNB)
    {
        $fromPrivateKey = Crypt::decryptString($masterWallet->private_key);
        $bufferedAmount = bcadd($requiredGasBNB, '0.0002', 18);

        $payload = [
            'fromPrivateKey' => $fromPrivateKey,
            'to' => $toAddress,
            'amount' => (string) $bufferedAmount,
            'currency' => 'BSC',
        ];

        $response = Http::withHeaders(['x-api-key' => config('tatum.api_key')])
            ->post(config('tatum.base_url') . '/bsc/transaction', $payload);

        if ($response->failed()) {
            throw new \Exception("BSC Top-up failed: " . $response->body());
        }

        return $response->json();
    }

    public static function checkTransactionDetails($txHash)
    {
        $response = Http::withHeaders(['x-api-key' => config('tatum.api_key')])
            ->get(config('tatum.base_url') . "/bsc/transaction/{$txHash}");

        if ($response->failed()) {
            throw new \Exception("Failed to fetch BSC transaction details: " . $response->body());
        }

        return $response->json();
    }

    public static function transferAssetsToMaster($fromPrivateKey, $fromAddress, $toAddress, $amount, $currency, $user, $masterWallet)
    {
        $gasfee = BlockChainHelper::estimateGasFee($fromAddress, $toAddress, $amount, $currency, 'BSC');
        $gasLimit = (int) $gasfee['gasLimit'] + 50000;
        $gasPriceGwei = (string) max(1, intval(ceil(intval($gasfee['gasPrice']) / 1e9)));

        $payload = [
            'fromPrivateKey' => $fromPrivateKey,
            'to' => $toAddress,
            'amount' => (string) $amount,
            'currency' => $currency,
            'fee' => [
                'gasLimit' => (string) $gasLimit,
                'gasPrice' => $gasPriceGwei,
            ]
        ];

        $response = Http::withHeaders(['x-api-key' => config('tatum.api_key')])
            ->post(config('tatum.base_url') . '/bsc/transaction', $payload);

        if ($response->failed()) {
            throw new \Exception("Transfer to master failed: " . $response->body());
        }

        $tx = $response->json();
        $txHash = $tx['txId'] ?? null;

        MasterWalletTransaction::create([
            'user_id' => $user->id,
            'master_wallet_id' => $masterWallet->id,
            'blockchain' => 'bsc',
            'currency' => $currency,
            'to_address' => $toAddress,
            'amount' => $amount,
            'fee' => '0',
            'tx_hash' => $txHash,
        ]);

        // BlockChainHelper::logActualGasFee($user->id, $txHash, $currency, 'transfer');

        return $tx;
    }

    public static function sendFromMasterToExternal(string $toAddress, string $amount, string $currency = 'BSC')
    {
        $currency = strtoupper($currency);
        $blockchain = 'bsc';

        $masterWallet = MasterWallet::where('blockchain', strtoupper($blockchain))->firstOrFail();
        $fromPrivateKey = Crypt::decryptString($masterWallet->private_key);
        $fromAddress = $masterWallet->address;

        $gasFee = BlockChainHelper::estimateGasFee($fromAddress, $toAddress, $amount, $currency, 'BSC');
        $gasLimit = (int) $gasFee['gasLimit'] + 50000;
        $gasPriceGwei = (string) max(1, intval(ceil(intval($gasFee['gasPrice']) / 1e9)));

        $payload = [
            'fromPrivateKey' => $fromPrivateKey,
            'to' => $toAddress,
            'amount' => (string) $amount,
            'currency' => $currency,
            'fee' => [
                'gasLimit' => (string) $gasLimit,
                'gasPrice' => $gasPriceGwei,
            ],
        ];

        $response = Http::withHeaders(['x-api-key' => config('tatum.api_key')])
            ->post(config('tatum.base_url') . '/bsc/transaction', $payload);

        if ($response->failed()) {
            throw new \Exception("BSC External Transfer Failed: " . $response->body());
        }

        $tx = $response->json();
        $txHash = $tx['txId'] ?? null;

        // BlockChainHelper::logActualGasFee(null, $txHash, $currency, 'external-send');

        return [
            'txHash' => $txHash,
            'sent' => $amount,
            'currency' => $currency,
            'to' => $toAddress,
        ];
    }
}
