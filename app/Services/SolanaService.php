<?php

namespace App\Services;

use App\Helpers\BlockChainHelper;
use App\Models\DepositAddress;
use App\Models\GasFeeLog;
use App\Models\Ledger;
use App\Models\MasterWallet;
use App\Models\MasterWalletTransaction;
use App\Models\WalletCurrency;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class SolanaService
{
    public function transferToMasterWallet($virtualAccount, $amount)
    {
        $user = $virtualAccount->user;
        $currency = strtoupper($virtualAccount->currency);

        $deposit = DepositAddress::where('virtual_account_id', $virtualAccount->id)->firstOrFail();
        $fromAddress = $deposit->address;
        $fromPrivateKey = Crypt::decryptString($deposit->private_key);

        $masterWallet = MasterWallet::where('blockchain', 'solana')->firstOrFail();
        $toAddress = $masterWallet->address;

        // Check balance of SOL for fees
        if ($currency !== 'SOL') {
            $solBalance = BlockChainHelper::checkAddressBalance($fromAddress, 'solana')['balance'];
            $requiredFee = '0.001'; // Default estimate, buffer handled below

            if (bccomp($solBalance, $requiredFee, 8) < 0) {
                $this->topUpSolanaUserWallet($fromAddress, bcsub($requiredFee, $solBalance, 8));
            }
        }

        $payload = [
            'from' => $fromAddress,
            'to' => $toAddress,
            'amount' => (string)$amount,
            'fromPrivateKey' => $fromPrivateKey,
            'fee' => 'SOL',
            'currency' => $currency,
        ];

        $endpoint = $currency === 'SOL'
            ? '/solana/transaction'
            : "/blockchain/token/transaction/SOL";

        if ($currency !== 'SOL') {
            $walletCurrency = WalletCurrency::where('currency', $currency)->where('blockchain', 'SOLANA')->firstOrFail();
            $payload['contractAddress'] = $walletCurrency->contract_address;
        }

        $response = Http::withHeaders(['x-api-key' => config('tatum.api_key')])
            ->post(config('tatum.base_url') . $endpoint, $payload);

        if ($response->failed()) {
            throw new \Exception("Transfer failed: " . $response->body());
        }

        $tx = $response->json();
        $txHash = $tx['txId'] ?? null;

        MasterWalletTransaction::create([
            'user_id' => $user->id,
            'master_wallet_id' => $masterWallet->id,
            'blockchain' => 'solana',
            'currency' => $currency,
            'to_address' => $toAddress,
            'amount' => $amount,
            'fee' => '0',
            'tx_hash' => $txHash,
        ]);

        Ledger::create([
            'user_id' => $user->id,
            'type' => 'transfer',
            'blockchain' => 'solana',
            'currency' => $currency,
            'amount' => $amount,
            'tx_hash' => $txHash,
        ]);

        return $tx;
    }

    public function topUpSolanaUserWallet($toAddress, $amount)
    {
        $masterWallet = MasterWallet::where('blockchain', 'solana')->firstOrFail();
        $fromPrivateKey = Crypt::decrypt($masterWallet->private_key);

        $payload = [
            'fromPrivateKey' => $fromPrivateKey,
            'to' => $toAddress,
            'amount' => $amount,
            'currency' => 'SOL',
        ];

        $response = Http::withHeaders(['x-api-key' => config('tatum.api_key')])
            ->post(config('tatum.base_url') . '/solana/transaction', $payload);

        if ($response->failed()) {
            throw new \Exception("Top-up failed: " . $response->body());
        }

        $txId = $response->json()['txId'] ?? null;

        GasFeeLog::create([
            'user_id' => null,
            'blockchain' => 'solana',
            'estimated_fee' => $amount,
            'fee_currency' => 'SOL',
            'tx_type' => 'topup',
            'tx_hash' => $txId,
        ]);
    }

    public function transferToExternalAddress($user, string $toAddress, string $amount, string $currency = 'SOL')
    {
        $masterWallet = MasterWallet::where('blockchain', 'solana')->firstOrFail();
        $fromPrivateKey = Crypt::decrypt($masterWallet->private_key);
        $fromAddress = $masterWallet->address;

        $payload = [
            'fromPrivateKey' => $fromPrivateKey,
            'to' => $toAddress,
            'amount' => (string)$amount,
            'currency' => $currency,
        ];

        if ($currency !== 'SOL') {
            $walletCurrency = WalletCurrency::where('currency', $currency)->where('blockchain', 'SOLANA')->firstOrFail();
            $payload['contractAddress'] = $walletCurrency->contract_address;
            $endpoint = '/blockchain/token/transaction/SOL';
        } else {
            $endpoint = '/solana/transaction';
        }

        $response = Http::withHeaders(['x-api-key' => config('tatum.api_key')])
            ->post(config('tatum.base_url') . $endpoint, $payload);

        if ($response->failed()) {
            throw new \Exception("Blockchain transfer failed: " . $response->body());
        }

        $txHash = $response->json()['txId'] ?? null;

        MasterWalletTransaction::create([
            'user_id' => $user->id,
            'master_wallet_id' => $masterWallet->id,
            'blockchain' => 'solana',
            'currency' => $currency,
            'to_address' => $toAddress,
            'amount' => $amount,
            'fee' => '0',
            'tx_hash' => $txHash,
        ]);

        Ledger::create([
            'user_id' => $user->id,
            'type' => 'withdrawal',
            'blockchain' => 'solana',
            'currency' => $currency,
            'amount' => $amount,
            'tx_hash' => $txHash,
        ]);

        return [
            'txHash' => $txHash,
            'sent' => $amount,
        ];
    }
}
