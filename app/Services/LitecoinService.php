<?php

namespace App\Services\Blockchain;

use App\Models\MasterWallet;
use App\Models\VirtualAccount;
use App\Models\DepositAddress;
use App\Models\Ledger;
use App\Models\MasterWalletTransaction;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class LitecoinService
{
    protected string $blockchain = 'litecoin';

    public function transferToMasterWallet(VirtualAccount $virtualAccount, string $amount)
    {
        $fromAddress = DepositAddress::where('virtual_account_id', $virtualAccount->id)->value('address');
        $fromPrivateKey = Crypt::decryptString(DepositAddress::where('virtual_account_id', $virtualAccount->id)->value('private_key'));

        $masterWallet = MasterWallet::where('blockchain', $this->blockchain)->firstOrFail();
        $toAddress = $masterWallet->address;

        $feeInfo = $this->estimateFee($fromAddress, $toAddress, $amount);
        $feeLtc = $feeInfo['feeLtc'];
        $adjustedAmount = bcsub($amount, $feeLtc, 8);

        $payload = [
            'fromAddress' => [$fromAddress],
            'to' => [[
                'address' => $toAddress,
                'value' => $adjustedAmount
            ]],
            'fee' => [
                'gasLimit' => $feeInfo['vsize'],
                'gasPrice' => $feeInfo['feePerByte']
            ],
            'fromPrivateKey' => [$fromPrivateKey]
        ];

        $response = Http::withHeaders([
            'x-api-key' => config('tatum.api_key'),
        ])->post(config('tatum.base_url') . '/litecoin/transaction', $payload);

        if ($response->failed()) {
            throw new \Exception("LTC Transfer Failed: " . $response->body());
        }

        $txHash = $response->json()['txId'];

        MasterWalletTransaction::create([
            'user_id' => $virtualAccount->user_id,
            'master_wallet_id' => $masterWallet->id,
            'blockchain' => $this->blockchain,
            'currency' => 'LTC',
            'to_address' => $toAddress,
            'amount' => $adjustedAmount,
            'fee' => $feeLtc,
            'tx_hash' => $txHash,
        ]);

        Ledger::create([
            'user_id' => $virtualAccount->user_id,
            'type' => 'transfer',
            'blockchain' => $this->blockchain,
            'currency' => 'LTC',
            'amount' => $adjustedAmount,
            'tx_hash' => $txHash,
        ]);

        return $txHash;
    }

    public function transferToExternalAddress($user, string $toAddress, string $amount)
    {
        $masterWallet = MasterWallet::where('blockchain', $this->blockchain)->firstOrFail();
        $fromAddress = $masterWallet->address;
        $fromPrivateKey = Crypt::decrypt($masterWallet->private_key);

        $feeInfo = $this->estimateFee($fromAddress, $toAddress, $amount);
        $feeLtc = $feeInfo['feeLtc'];
        $adjustedAmount = bcsub($amount, $feeLtc, 8);

        $payload = [
            'fromAddress' => [$fromAddress],
            'to' => [[
                'address' => $toAddress,
                'value' => $adjustedAmount
            ]],
            'fee' => [
                'gasLimit' => $feeInfo['vsize'],
                'gasPrice' => $feeInfo['feePerByte']
            ],
            'fromPrivateKey' => [$fromPrivateKey]
        ];

        $response = Http::withHeaders([
            'x-api-key' => config('tatum.api_key'),
        ])->post(config('tatum.base_url') . '/litecoin/transaction', $payload);

        if ($response->failed()) {
            throw new \Exception("LTC External Transfer Failed: " . $response->body());
        }

        $txHash = $response->json()['txId'];

        MasterWalletTransaction::create([
            'user_id' => $user->id,
            'master_wallet_id' => $masterWallet->id,
            'blockchain' => $this->blockchain,
            'currency' => 'LTC',
            'to_address' => $toAddress,
            'amount' => $adjustedAmount,
            'fee' => $feeLtc,
            'tx_hash' => $txHash,
        ]);

        Ledger::create([
            'user_id' => $user->id,
            'type' => 'withdrawal',
            'blockchain' => $this->blockchain,
            'currency' => 'LTC',
            'amount' => $adjustedAmount,
            'tx_hash' => $txHash,
        ]);

        return $txHash;
    }

    public function estimateFee(string $fromAddress, string $toAddress, string $amount): array
    {
        $response = Http::withHeaders([
            'x-api-key' => config('tatum.api_key'),
        ])->post(config('tatum.base_url') . '/blockchain/estimate', [
            'from' => $fromAddress,
            'to' => $toAddress,
            'amount' => $amount,
            'chain' => 'LTC'
        ]);

        if ($response->failed()) {
            throw new \Exception("LTC Fee Estimation Failed: " . $response->body());
        }

        $data = $response->json();

        $vsize = $data['gasLimit'] ?? 250;
        $feePerByte = $data['gasPrice'] ?? 20;
        $feeLtc = bcdiv(bcmul((string)$vsize, (string)$feePerByte), bcpow('10', 8), 8);

        return [
            'vsize' => $vsize,
            'feePerByte' => $feePerByte,
            'feeLtc' => $feeLtc
        ];
    }
}
