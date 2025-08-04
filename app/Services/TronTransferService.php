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

        // Step 1: Check TRX balance
        $trxBalance = self::getTrxBalance($fromAddress);
        $estimatedFee = 18;

        if ($trxBalance < $estimatedFee) {
            $topupTx = self::topUpTrxToUser($masterWallet, $fromAddress, $estimatedFee);
            $topupDetails = self::checkTransactionDetails($topupTx['txID'] ?? null);
            if (!($topupDetails['ret'][0]['contractRet'] ?? null) === 'SUCCESS') {
                throw new \Exception("TRX top-up failed.");
            }
            self::logActualGasFee($user->id, $topupTx['txID'], 'TRX', 'gas-topup');
        }
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

        $response = Http::withHeaders([
            'x-api-key' => config('tatum.api_key'),
        ])->post(config('tatum.base_url') . '/tron/transaction', $payload);

        if ($response->failed()) {
            throw new \Exception("TRX Top-up failed: " . $response->body());
        }

        return $response->json();
    }

    public static function checkTransactionDetails($txHash)
    {
        $response = Http::withHeaders([
            'x-api-key' => config('tatum.api_key'),
        ])->get(config('tatum.base_url') . "/tron/transaction/{$txHash}");

        if ($response->failed()) {
            throw new \Exception("TRX Tx Check failed: " . $response->body());
        }

        return $response->json();
    }

    public static function transferTronAssetsToMaster($fromPrivateKey, $fromAddress, $toAddress, $amount, $currency, $user, $masterWallet)
    {
        $endpoint = $currency === 'USDT_TRON' ? '/tron/trc20/transaction' : '/tron/transaction';
        $payload = [
            'fromPrivateKey' => $fromPrivateKey,
            // 'to' => $toAddress,
            'to'=>'TLV3GeAjdGdLPXLNinPGXEyRhgbWBd1hxW',
            'amount' => (string) $amount,
        ];

        if ($currency === 'USDT_TRON') {
            $payload['tokenAddress'] = config('tatum.tron_usdt_contract', 'TR7NHqjeKQxGTCi8q8ZY4pL8otSzgjLj6t');
            $payload['feeLimit'] = 10000000; // ~10 TRX
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
            'blockchain' => 'TRON',
            'currency' => $currency,
            'to_address' => $toAddress,
            'amount' => $amount,
            'fee' => 0,
            'tx_hash' => $txHash,
        ]);

        self::logActualGasFee($user->id, $txHash, $currency, 'to-master');

        return [
            'txHash' => $txHash,
            'sent' => $amount,
            'fee' => 0,
            'total' => $amount,
        ];
    }

    public static function sendFromMasterToExternal(string $toAddress, string $amount, string $currency = 'TRX')
    {
        $currency = strtoupper($currency);
        $masterWallet = MasterWallet::where('blockchain', 'TRON')->firstOrFail();
        $fromPrivateKey = Crypt::decryptString($masterWallet->private_key);
        $fromAddress = $masterWallet->address;

        $endpoint = $currency === 'USDT_TRON' ? '/tron/trc20/transaction' : '/tron/transaction';
        $payload = [
            'fromPrivateKey' => $fromPrivateKey,
            'to' => $toAddress,
            'amount' => (string) $amount,
        ];

        if ($currency === 'USDT_TRON') {
            $payload['tokenAddress'] = config('tatum.tron_usdt_contract', 'TR7NHqjeKQxGTCi8q8ZY4pL8otSzgjLj6t');
            $payload['feeLimit'] = 10000000;
        }

        $response = Http::withHeaders(['x-api-key' => config('tatum.api_key')])
            ->post(config('tatum.base_url') . $endpoint, $payload);

        if ($response->failed()) {
            throw new \Exception("TRON external send failed: " . $response->body());
        }

        $tx = $response->json();
        $txHash = $tx['txID'] ?? null;

        self::logActualGasFee(null, $txHash, $currency, 'external-send');

        return [
            'txHash' => $txHash,
            'sent' => $amount,
            'fee' => 0,
            'total' => $amount,
        ];
    }

    public static function logActualGasFee($userId, $txHash, $currency, $txType)
    {
        $tx = self::checkTransactionDetails($txHash);
        $fee = ($tx['fee'] ?? $tx['netFee'] ?? 0) / 1e6;

        GasFeeLog::create([
            'user_id' => $userId,
            'blockchain' => 'TRON',
            'estimated_fee' => $fee,
            'fee_currency' => $currency,
            'tx_type' => $txType,
            'tx_hash' => $txHash,
        ]);
    }

    public static function getTrxBalance(string $address): float
    {
        $res = Http::withHeaders(['x-api-key' => config('tatum.api_key')])
            ->get(config('tatum.base_url') . "/tron/account/{$address}");

        if ($res->failed()) {
            throw new \Exception("TRX balance fetch failed: " . $res->body());
        }

        $balanceSun = $res->json('balance') ?? 0;
        return (float) bcdiv((string) $balanceSun, '1000000', 6);
    }
}
