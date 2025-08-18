<?php

namespace App\Services;

use App\Models\MasterWallet;
use App\Models\VirtualAccount;
use App\Models\DepositAddress;
use App\Models\Ledger;
use App\Models\MasterWalletTransaction;
use App\Models\TransferLog;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class BitcoinService
{
    protected string $blockchain = 'bitcoin';
    public function transferToMasterWallet(VirtualAccount $virtualAccount, string $amount)
    {
        $fromAddress = DepositAddress::where('virtual_account_id', $virtualAccount->id)->value('address');
        $fromPrivateKey = Crypt::decryptString(DepositAddress::where('virtual_account_id', $virtualAccount->id)->value('private_key'));

        $masterWallet = MasterWallet::where('blockchain', $this->blockchain)->firstOrFail();
        $toAddress = 'bc1qqhapyfgxqcns6zsccqq2qkejg9g65gkluca2gg';

        $feeInfo = $this->estimateFee();
       $feeBtc = '0.00003320';

    // Subtract fee from the sending amount
    $adjustedAmount = bcsub($amount, $feeBtc, 8);

    if (bccomp($adjustedAmount, '0', 8) <= 0) {
        throw new \Exception("Amount too low after subtracting fee");
    }
        $payload = [
            'fromAddress' => [[
                'address' => $fromAddress,
                'privateKey' => $fromPrivateKey,
            ]],
            'to' => [[
                'address' => $toAddress,
                'value' => (float) number_format($adjustedAmount, 8, '.', '')
            ]],
            'fee' => number_format($feeBtc, 8, '.', ''),
            'changeAddress' => $toAddress,
        ];

        $response = Http::withHeaders([
            'x-api-key' => config('tatum.api_key'),
        ])->post(config('tatum.base_url') . '/bitcoin/transaction', $payload);

        if ($response->failed()) {
            throw new \Exception("BTC Transfer Failed: " . $response->body());
        }

        $txHash = $response->json()['txId'];
        Log::info("BTC Transfer Success: " . $txHash);
        MasterWalletTransaction::create([
            'user_id' => $virtualAccount->user_id,
            'master_wallet_id' => $masterWallet->id,
            'blockchain' => $this->blockchain,
            'currency' => 'BTC',
            'to_address' => $toAddress,
            'amount' => $adjustedAmount,
            'fee' => $feeBtc,
            'tx_hash' => $txHash,
        ]);

        TransferLog::create([
            'user_id' => $virtualAccount->user_id,
            'amount' => $adjustedAmount,
            'fee' => $feeBtc,
            'currency' => 'BTC',
            'from_address' => $fromAddress,
            'to_address' => $toAddress,
        ]);
        // Ledger::create([
        //     'user_id' => $virtualAccount->user_id,
        //     'type' => 'transfer',
        //     'blockchain' => $this->blockchain,
        //     'currency' => 'BTC',
        //     'amount' => $adjustedAmount,
        //     'tx_hash' => $txHash,
        // ]);

        return $txHash;
    }
    public function transferToExternalAddress($user, string $toAddress, string $amount)
    {
        $masterWallet = MasterWallet::where('blockchain', $this->blockchain)->firstOrFail();
        $fromAddress = $masterWallet->address;
        $fromPrivateKey = Crypt::decrypt($masterWallet->private_key);

        $feeInfo = $this->estimateFee();
        $feeBtc = $feeInfo['feeBtc'];
        $adjustedAmount = bcsub($amount, $feeBtc, 8);

        $payload = [
            'fromAddress' => [
                [
                    'address' => $fromAddress,
                    'privateKey' => $fromPrivateKey // ✅ Move the key here!
                ]
            ],
            'to' => [
                [
                    'address' => $toAddress,
                    'value' => (float) $adjustedAmount // ✅ Must be a positive float with max 8 decimals
                ]
            ],
            'fee' => number_format((float) $feeBtc, 8, '.', ''), // ✅ convert to string with max 8 decimals

            'changeAddress' => $fromAddress // ✅ Required when fee is set manually
        ];

        $response = Http::withHeaders([
            'x-api-key' => config('tatum.api_key'),
        ])->post(config('tatum.base_url') . '/bitcoin/transaction', $payload);

        if ($response->failed()) {
            throw new \Exception("BTC External Transfer Failed: " . $response->body());
        }

        $txHash = $response->json()['txId'];

        MasterWalletTransaction::create([
            'user_id' => $user->id,
            'master_wallet_id' => $masterWallet->id,
            'blockchain' => $this->blockchain,
            'currency' => 'BTC',
            'to_address' => $toAddress,
            'amount' => $adjustedAmount,
            'fee' => $feeBtc,
            'tx_hash' => $txHash,
        ]);

        Ledger::create([
            'user_id' => $user->id,
            'type' => 'withdrawal',
            'blockchain' => $this->blockchain,
            'currency' => 'BTC',
            'amount' => $adjustedAmount,
            'tx_hash' => $txHash,
        ]);

        return [
            'txHash' => $txHash,
            'sent' => $adjustedAmount,
            'fee' => $feeBtc,
            'total' => $amount,
        ];
    }

    public function estimateFee(string $chain = 'BTC'): array
    {
        $response = Http::withHeaders([
            'x-api-key' => config('tatum.api_key'),
        ])->get(config('tatum.base_url') . "/blockchain/fee/{$chain}");

        if ($response->failed()) {
            throw new \Exception("{$chain} Fee Estimation Failed: " . $response->body());
        }

        $data = $response->json();
        $vsize = 250; // average size in bytes
        $feePerByte = $data['medium']; // or 'medium' / 'slow' depending on urgency
        $feeTotal = bcmul((string)$feePerByte, (string)$vsize); // in satoshis
        $feeLtc = bcdiv($feeTotal, bcpow('10', 8), 8); // Convert to LTC

        return [
            'vsize' => $vsize,
            'feePerByte' => $feePerByte,
            'feeBtc' => $feeLtc
        ];
    }
    public function getAddressBalance(string $address): float
    {
        $response = Http::withHeaders([
            'x-api-key' => config('tatum.api_key'),
        ])->get(config('tatum.base_url') . "/bitcoin/address/balance/{$address}");

        if ($response->failed()) {
            throw new \Exception("BTC Address Balance Fetch Failed: " . $response->body());
        }
        $incoming = $response->json()['incoming'] ?? 0;
        $outgoing = $response->json()['outgoing'] ?? 0;
        Log::info("wallet balance of bitcoin is ", [$response->json()]);
        return (float) bcsub($incoming, $outgoing, 8);
    }
}
