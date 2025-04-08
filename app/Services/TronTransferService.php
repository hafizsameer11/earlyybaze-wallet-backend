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

class TronTransferService
{
    public static function transferTronToMasterWalletWithAutoFeeHandling($virtualAccount, $amount)
    {
        $user = $virtualAccount->user;
        $currency = strtoupper($virtualAccount->currency); // TRX or USDT_TRON
        $deposit = DepositAddress::where('virtual_account_id', $virtualAccount->id)->firstOrFail();
        $fromAddress = $deposit->address;
        $fromPrivateKey = Crypt::decryptString($deposit->private_key);
        $masterWallet = MasterWallet::where('blockchain', 'TRON')->firstOrFail();
        $toAddress = $masterWallet->address;

        // Step 1: Check balance of TRX
        $trxBalance = BlockChainHelper::checkAddressBalance($fromAddress, 'tron');

        // Step 2: If balance < estimated fee (≈ 2 TRX), top up
        $estimatedFee = 2.5;
        if ($trxBalance < $estimatedFee) {
            $topupTx = self::topUpTrxToUser($masterWallet, $fromAddress, $estimatedFee);
            $topupDetails = self::checkTransactionDetails($topupTx['txID'] ?? null);

            if (!($topupDetails['ret'][0]['contractRet'] ?? null) === 'SUCCESS') {
                throw new \Exception("Top-up failed, can't continue.");
            }

            // Optionally log fee used in topup
            self::logActualGasFee($user->id, $topupTx['txID'], 'TRX', 'gas-topup');
        }

        // Step 3: Transfer to master
        return self::transferTronAssetsToMaster($fromPrivateKey, $fromAddress, $toAddress, $amount, $currency, $user, $masterWallet);
    }

    public static function topUpTrxToUser($masterWallet, $toAddress, $amount = 2.5)
    {
        $fromPrivateKey = Crypt::decryptString($masterWallet->private_key);

        $payload = [
            'fromPrivateKey' => $fromPrivateKey,
            'to' => $toAddress,
            'amount' => (string) $amount,
        ];

        $response = Http::withHeaders(['x-api-key' => config('tatum.api_key')])
            ->post(config('tatum.base_url') . '/tron/transaction', $payload);

        if ($response->failed()) {
            throw new \Exception("TRX Top-up failed: " . $response->body());
        }

        return $response->json();
    }

    public static function checkTransactionDetails($txHash)
    {
        $response = Http::withHeaders(['x-api-key' => config('tatum.api_key')])
            ->get(config('tatum.base_url') . "/tron/transaction/{$txHash}");

        if ($response->failed()) {
            throw new \Exception("TRX Tx Check failed: " . $response->body());
        }

        return $response->json();
    }

    public static function transferTronAssetsToMaster($fromPrivateKey, $fromAddress, $toAddress, $amount, $currency, $user, $masterWallet)
    {
        if ($currency === 'TRX') {
            $endpoint = '/tron/transaction';
            $payload = [
                'fromPrivateKey' => $fromPrivateKey,
                'to' => $toAddress,
                'amount' => (string) $amount,
            ];
        } elseif ($currency === 'USDT_TRON') {
            $endpoint = '/tron/trc20/transaction';
            $usdtContract = config('tatum.tron_usdt_contract', 'TR7NHqjeKQxGTCi8q8ZY4pL8otSzgjLj6t');
            $payload = [
                'fromPrivateKey' => $fromPrivateKey,
                'to' => $toAddress,
                'amount' => (string) $amount,
                'tokenAddress' => $usdtContract,
                'feeLimit' => 10000000 // ~10 TRX
            ];
        } else {
            throw new \Exception("Unsupported TRON currency: $currency");
        }

        $response = Http::withHeaders(['x-api-key' => config('tatum.api_key')])
            ->post(config('tatum.base_url') . $endpoint, $payload);

        if ($response->failed()) {
            throw new \Exception("TRON Transfer Failed: " . $response->body());
        }

        $tx = $response->json();
        $txHash = $tx['txID'] ?? null;

        MasterWalletTransaction::create([
            'user_id' => $user->id,
            'master_wallet_id' => $masterWallet->id,
            'blockchain' => 'tron',
            'currency' => $currency,
            'to_address' => $toAddress,
            'amount' => $amount,
            'fee' => '0', // Can be replaced after fetching gas later
            'tx_hash' => $txHash,
        ]);

        self::logActualGasFee($user->id, $txHash, $currency, 'transfer');

        return $tx;
    }
    public static function sendFromMasterToExternal(string $toAddress, string $amount, string $currency = 'TRX')
    {
        $currency = strtoupper($currency);
        $blockchain = 'tron';

        // Get master wallet
        $masterWallet = MasterWallet::where('blockchain', strtoupper($blockchain))->firstOrFail();
        $fromPrivateKey = Crypt::decryptString($masterWallet->private_key);
        $fromAddress = $masterWallet->address;

        if ($currency === 'TRX') {
            $endpoint = '/tron/transaction';
            $payload = [
                'fromPrivateKey' => $fromPrivateKey,
                'to' => $toAddress,
                'amount' => (string) $amount,
            ];
        } elseif ($currency === 'USDT_TRON') {
            $endpoint = '/tron/trc20/transaction';
            $usdtContract = config('tatum.tron_usdt_contract', 'TR7NHqjeKQxGTCi8q8ZY4pL8otSzgjLj6t');

            $payload = [
                'fromPrivateKey' => $fromPrivateKey,
                'to' => $toAddress,
                'amount' => (string) $amount,
                'tokenAddress' => $usdtContract,
                'feeLimit' => 10000000 // ~10 TRX
            ];
        } else {
            throw new \Exception("Unsupported TRON currency for external transfer: $currency");
        }

        $response = Http::withHeaders(['x-api-key' => config('tatum.api_key')])
            ->post(config('tatum.base_url') . $endpoint, $payload);

        if ($response->failed()) {
            throw new \Exception("Failed to send to external TRON address: " . $response->body());
        }

        $tx = $response->json();
        $txHash = $tx['txID'] ?? null;

        // Optionally log fee
        self::logActualGasFee(null, $txHash, $currency, 'external-send');

        return [
            'txHash' => $txHash,
            'sent' => $amount,
            'currency' => $currency,
            'to' => $toAddress,
        ];
    }

    public static function logActualGasFee($userId, $txHash, $currency, $txType)
    {
        $tx = self::checkTransactionDetails($txHash);
        $fee = (isset($tx['fee']) ? $tx['fee'] : ($tx['netFee'] ?? 0)) / 1e6; // Convert SUN to TRX

        GasFeeLog::create([
            'user_id' => $userId,
            'blockchain' => 'tron',
            'estimated_fee' => $fee,
            'fee_currency' => $currency,
            'tx_type' => $txType,
            'tx_hash' => $txHash,
        ]);
    }
}
