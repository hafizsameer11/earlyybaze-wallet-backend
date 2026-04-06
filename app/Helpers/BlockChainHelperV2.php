<?php

namespace App\Helpers;

use App\Models\DepositAddress;
use App\Models\Ledger;
use App\Models\VirtualAccount;
use App\Models\WalletCurrency;
use App\Support\WalletFlowV2;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Http;

class BlockChainHelperV2
{
    public static function sendToExternalAddressFromUserDeposit(
        $user,
        VirtualAccount $virtualAccount,
        WalletCurrency $walletCurrency,
        string $blockchain,
        string $currency,
        string $toAddress,
        float $amount
    ): array {
        if (! WalletFlowV2::currencyAllowedForV2($walletCurrency)) {
            throw new \Exception('Wallet v2 withdrawals only support Bitcoin, Ethereum, BSC, and TRON with USDT/USDC or native BTC, ETH, BNB, TRX.');
        }

        $chainKey = WalletFlowV2::resolveChainKey((string) $walletCurrency->blockchain);
        if (! $chainKey) {
            throw new \Exception('Could not resolve chain for this account.');
        }

        $tatumBlockchain = WalletFlowV2::tatumBlockchainUpper($chainKey);

        $reqNorm = strtoupper(trim($blockchain));
        $reqNorm = match ($reqNorm) {
            'BTC' => 'BITCOIN',
            'ETH' => 'ETHEREUM',
            default => $reqNorm,
        };
        if ($reqNorm !== $tatumBlockchain) {
            throw new \Exception('Blockchain does not match the selected currency account.');
        }

        $accountCurrency = strtoupper(trim((string) $walletCurrency->currency));
        if (strtoupper(trim($currency)) !== $accountCurrency) {
            throw new \Exception('Currency does not match account.');
        }
        $currency = $accountCurrency;

        $fee = 0;

        $isToken = $walletCurrency->is_token;
        $contractAddress = $walletCurrency->contract_address;

        $deposit = DepositAddress::where('virtual_account_id', $virtualAccount->id)->first();
        if (! $deposit || ! $deposit->private_key) {
            throw new \Exception('User deposit address or key not found for withdrawal.');
        }

        $privateKey = Crypt::decryptString($deposit->private_key);
        $sendAmount = $amount - $fee;

        if ($sendAmount <= 0) {
            throw new \Exception('Amount after fee must be greater than 0.');
        }

        $endpoint = '';
        $payload = [];

        if ($isToken) {
            $endpoint = match ($tatumBlockchain) {
                'ETHEREUM' => '/ethereum/transaction/token',
                'BSC' => '/bsc/transaction/token',
                'TRON' => '/tron/transaction',
                default => throw new \Exception("Token transfers not supported on $tatumBlockchain"),
            };

            $payload = match ($tatumBlockchain) {
                'ETHEREUM', 'BSC' => [
                    'fromPrivateKey' => $privateKey,
                    'to' => $toAddress,
                    'amount' => (string) $sendAmount,
                    'contractAddress' => $contractAddress,
                ],
                'TRON' => [
                    'to' => $toAddress,
                    'amount' => (string) $sendAmount,
                    'fromPrivateKey' => $privateKey,
                    'tokenId' => $contractAddress,
                ],
            };
        } else {
            $endpoint = match ($tatumBlockchain) {
                'ETHEREUM' => '/ethereum/transaction',
                'BSC' => '/bsc/transaction',
                'BITCOIN' => '/bitcoin/transaction',
                'TRON' => '/tron/transaction',
                default => throw new \Exception("Native transfer not supported for $tatumBlockchain"),
            };

            $payload = match ($tatumBlockchain) {
                'ETHEREUM' => [
                    'fromPrivateKey' => $privateKey,
                    'to' => $toAddress,
                    'amount' => (string) $sendAmount,
                    'currency' => $currency,
                ],
                'BSC', 'TRON' => [
                    'fromPrivateKey' => $privateKey,
                    'to' => $toAddress,
                    'amount' => (string) $sendAmount,
                ],
                'BITCOIN' => [
                    'fromAddress' => [[
                        'address' => $deposit->address,
                        'privateKey' => $privateKey,
                    ]],
                    'to' => [[
                        'address' => $toAddress,
                        'value' => (string) $sendAmount,
                    ]],
                ],
            };
        }

        $response = Http::withHeaders([
            'x-api-key' => config('tatum.api_key'),
        ])->post(config('tatum.base_url') . $endpoint, $payload);

        if ($response->failed()) {
            throw new \Exception('Blockchain transaction failed: ' . $response->body());
        }

        $txHash = $response->json()['txId'] ?? null;

        Ledger::create([
            'user_id' => $user->id,
            'type' => 'withdrawal',
            'blockchain' => $tatumBlockchain,
            'currency' => $currency,
            'amount' => $amount,
            'tx_hash' => $txHash,
        ]);

        return [
            'txHash' => $txHash,
            'sent' => $sendAmount,
            'fee' => $fee,
            'total' => $amount,
        ];
    }
}
