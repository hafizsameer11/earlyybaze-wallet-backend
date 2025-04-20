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

class BscService
{
    public function transferToMasterWallet($virtualAccount, $amount)
    {
        $user = $virtualAccount->user;
        $currency = strtoupper($virtualAccount->currency);

        $deposit = DepositAddress::where('virtual_account_id', $virtualAccount->id)->firstOrFail();
        $fromAddress = $deposit->address;
        $fromPrivateKey = Crypt::decryptString($deposit->private_key);

        $masterWallet = MasterWallet::where('blockchain', 'BSC')->firstOrFail();
        $toAddress = $masterWallet->address;

        $gasEstimation = BlockChainHelper::estimateGasFee($fromAddress, $toAddress, $amount, $currency, 'BSC');
        $originalGasLimit = $gasEstimation['gasLimit'];
        $minGasPrice = 1000000000;
        $gasPrice = max((int) $gasEstimation['gasPrice'], $minGasPrice);
        $bufferedGasLimit = ceil($originalGasLimit * 1.3);

        $requiredGasWei = bcmul((string) $gasPrice, (string) $bufferedGasLimit);
        $requiredGasBNB = bcdiv($requiredGasWei, bcpow('10', '18'), 18);

        $bnbBalance = BlockChainHelper::checkAddressBalance($fromAddress, 'bsc')['balance'];
        $bnbBalanceFormatted = number_format((float)$bnbBalance, 18, '.', '');
        $requiredGasFormatted = number_format((float)$requiredGasBNB, 18, '.', '');

        if (bccomp($bnbBalanceFormatted, $requiredGasFormatted, 18) < 0) {
            $tx = $this->topUpUserForGas($masterWallet, $fromAddress, $requiredGasFormatted);
            $txDetails = $this->getTransactionDetailsWithPolling($tx['txId']);

            if (!($txDetails['status'] ?? false)) {
                throw new \Exception("Gas top-up failed. Cannot proceed.");
            }

            $this->logActualGasFee($user->id, $tx['txId'], 'BNB', 'gas-topup');
        }

        return $this->executeAssetTransfer($fromPrivateKey, $fromAddress, $toAddress, $amount, $currency, $user, $masterWallet);
    }

    public function topUpUserForGas($masterWallet, $toAddress, $requiredGasBNB)
    {
        $fromPrivateKey = Crypt::decrypt($masterWallet->private_key);
        $bufferedAmount = bcadd($requiredGasBNB, '0.0002', 18);

        $payload = [
            'fromPrivateKey' => $fromPrivateKey,
            'to' => $toAddress,
            'amount' => (string) $bufferedAmount,
            'currency' => 'BNB',
        ];
        $response = Http::withHeaders(['x-api-key' => config('tatum.api_key')])
            ->post(config('tatum.base_url') . '/bsc/transaction', $payload);

        if ($response->failed()) {
            throw new \Exception("Top-up failed: " . $response->body());
        }

        return $response->json();
    }

    public function getTransactionDetailsWithPolling($txHash, $maxRetries = 5, $delaySeconds = 3)
    {
        for ($i = 0; $i < $maxRetries; $i++) {
            $response = Http::withHeaders(['x-api-key' => config('tatum.api_key')])
                ->get(config('tatum.base_url') . "/bsc/transaction/{$txHash}");

            if ($response->ok()) {
                $data = $response->json();
                if (isset($data['status'])) {
                    return $data;
                }
            }

            sleep($delaySeconds);
        }

        throw new \Exception("Transaction not confirmed within timeout.");
    }

    public function getTransactionDetails($txHash)
    {
        $response = Http::withHeaders(['x-api-key' => config('tatum.api_key')])
            ->get(config('tatum.base_url') . "/bsc/transaction/{$txHash}");

        if ($response->failed()) {
            throw new \Exception("Failed to fetch tx details: " . $response->body());
        }

        return $response->json();
    }

    public function executeAssetTransfer($fromPrivateKey, $fromAddress, $toAddress, $amount, $currency, $user, $masterWallet)
    {
        $gasFee = BlockChainHelper::estimateGasFee($fromAddress, $toAddress, $amount, $currency, 'BSC');
        $gasLimit = (int)$gasFee['gasLimit'] + 70000;
        $gasPriceGwei = (string) max(1, intval(ceil(intval($gasFee['gasPrice']) / 1e9)));

        $payload = [
            'fromPrivateKey' => $fromPrivateKey,
            'to' => $toAddress,
            'amount' => (string) $amount,
            'currency' => $currency,
            'fee' => [
                'gasLimit' => (string)$gasLimit,
                'gasPrice' => $gasPriceGwei,
            ]
        ];

        $response = Http::withHeaders(['x-api-key' => config('tatum.api_key')])
            ->post(config('tatum.base_url') . '/bsc/transaction', $payload);

        if ($response->failed()) {
            throw new \Exception("Transfer failed: " . $response->body());
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
            $feeBnb = bcdiv($feeWei, bcpow('10', '18'), 18);

            GasFeeLog::create([
                'user_id' => $userId,
                'blockchain' => 'bsc',
                'estimated_fee' => $feeBnb,
                'fee_currency' => $currency,
                'tx_type' => $type,
                'tx_hash' => $txHash,
            ]);
        }
    }

    public function transferToExternalAddress($user, string $toAddress, string $amount, string $currency = 'BNB', array $fee = [])
    {
        $blockchain = 'BSC';
        $currency = strtoupper($currency);
        $feeAmount = 0;

        $ledgerAccountId = $user->virtualAccounts()
            ->where('blockchain', $blockchain)
            ->where('currency', $currency)
            ->value('account_id');

        if (!$ledgerAccountId) {
            throw new \Exception("User ledger account not found.");
        }

        $withdrawalResponse = Http::withHeaders([
            'x-api-key' => config('tatum.api_key'),
        ])->post(config('tatum.base_url') . '/offchain/withdrawal', [
            'senderAccountId' => $ledgerAccountId,
            'address' => $toAddress,
            'amount' => (string)$amount,
            'fee' => (string)$feeAmount,
            'attr' => 'Withdrawal to external wallet',
        ]);

        if ($withdrawalResponse->failed()) {
            throw new \Exception("Ledger withdrawal failed: " . $withdrawalResponse->body());
        }

        $withdrawalId = $withdrawalResponse->json()['id'] ?? null;

        $masterWallet = MasterWallet::where('blockchain', $blockchain)->firstOrFail();
        $fromPrivateKey = Crypt::decrypt($masterWallet->private_key);

        $payload = [
            'fromPrivateKey' => $fromPrivateKey,
            'to' => $toAddress,
            'amount' => (string)$amount,
            'currency' => $currency,
        ];

        if (!empty($fee)) {
            $payload['fee'] = [
                'gasLimit' => (string)$fee['gasLimit'],
                'gasPrice' => (string)$fee['gasPrice'],
            ];
        }

        $response = Http::withHeaders([
            'x-api-key' => config('tatum.api_key'),
        ])->post(config('tatum.base_url') . '/bsc/transaction', $payload);

        if ($response->failed()) {
            if ($withdrawalId) {
                Http::withHeaders(['x-api-key' => config('tatum.api_key')])
                    ->delete(config('tatum.base_url') . "/offchain/withdrawal/{$withdrawalId}");
            }
            throw new \Exception("Blockchain transaction failed: " . $response->body());
        }

        $txHash = $response->json()['txId'] ?? null;

        if ($withdrawalId && $txHash) {
            Http::withHeaders([
                'x-api-key' => config('tatum.api_key'),
            ])->post(config('tatum.base_url') . "/offchain/withdrawal/{$withdrawalId}/{$txHash}");
        }

        MasterWalletTransaction::create([
            'user_id' => $user->id,
            'master_wallet_id' => $masterWallet->id,
            'blockchain' => $blockchain,
            'currency' => $currency,
            'to_address' => $toAddress,
            'amount' => $amount,
            'fee' => $feeAmount,
            'tx_hash' => $txHash,
        ]);

        // Ledger::create([
        //     'user_id' => $user->id,
        //     'type' => 'withdrawal',
        //     'blockchain' => $blockchain,
        //     'currency' => $currency,
        //     'amount' => $amount,
        //     'tx_hash' => $txHash,
        // ]);

        return [
            'txHash' => $txHash,
            'sent' => $amount,
            'fee' => $feeAmount,
            'total' => $amount,
        ];
    }

    public function getBscMasterBalances()
    {
        $masterWallet = MasterWallet::where('blockchain', 'BSC')->firstOrFail();
        $address = $masterWallet->address;

        $tokens = WalletCurrency::where('blockchain', 'BSC')
            ->where('is_token', true)
            ->get();

        $bnbResponse = Http::withHeaders([
            'x-api-key' => config('tatum.api_key'),
        ])->get(config('tatum.base_url') . "/bsc/account/balance/{$address}");

        if ($bnbResponse->failed()) {
            throw new \Exception("Failed to fetch BNB balance: " . $bnbResponse->body());
        }

        $bnbBalance = $bnbResponse->json()['balance'] ?? '0';

        $tokenBalances = [];

        foreach ($tokens as $token) {
            $response = Http::withHeaders(['x-api-key' => config('tatum.api_key')])
                ->get(config('tatum.base_url') . "/blockchain/token/balance/BSC/{$token->contract_address}/{$address}");

            $tokenBalances[$token->currency] = $response->ok()
                ? $response->json()['balance'] ?? '0'
                : 'Error: ' . $response->status();
        }

        return [
            'address' => $address,
            'bnb_balance' => $bnbBalance,
            'token_balances' => $tokenBalances,
        ];
    }

    public function transferFromMasterToUserBSC(string $toAddress, string $amount, string $currency, ?array $fee = null)
    {
        $currency = strtoupper($currency);

        $masterWallet = MasterWallet::where('blockchain', 'BSC')->firstOrFail();
        $fromPrivateKey = Crypt::decrypt($masterWallet->private_key);
        $fromAddress = $masterWallet->address;

        $walletCurrency = WalletCurrency::where([
            'blockchain' => 'BSC',
            'currency' => $currency,
        ])->firstOrFail();

        $gasEstimation = BlockChainHelper::estimateGasFee($fromAddress, $toAddress, $amount, $currency, 'bsc');
        $originalGasLimit = $gasEstimation['gasLimit'];
        $minGasPrice = 1000000000;
        $gasPrice = max((int) $gasEstimation['gasPrice'], $minGasPrice);
        $bufferedGasLimit = ceil($originalGasLimit * 1.3);

        $requiredGasWei = bcmul((string)$gasPrice, (string)$bufferedGasLimit);
        $requiredGasBNB = bcdiv($requiredGasWei, bcpow('10', '18'), 18);

        Log::info('Gas estimation for BSC transfer', [
            'from' => $fromAddress,
            'to' => $toAddress,
            'currency' => $currency,
            'bufferedGasLimit' => $bufferedGasLimit,
            'gasPrice' => $gasPrice,
            'requiredGasBNB' => $requiredGasBNB,
        ]);

        $payload = [
            'fromPrivateKey' => $fromPrivateKey,
            'to' => $toAddress,
            'amount' => (string)$amount,
            'currency' => $currency,
            'fee' => [
                'gasLimit' => (string)($fee['gasLimit'] ?? $bufferedGasLimit),
                'gasPrice' => (string)($fee['gasPrice'] ?? $gasPrice),
            ]
        ];

        $response = Http::withHeaders(['x-api-key' => config('tatum.api_key')])
            ->post(config('tatum.base_url') . '/bsc/transaction', $payload);

        if ($response->failed()) {
            throw new \Exception("Transfer failed: " . $response->body());
        }

        return [
            'txId' => $response->json()['txId'] ?? null,
            'from' => $fromAddress,
            'to' => $toAddress,
            'amount' => $amount,
            'currency' => $currency,
            'estimatedGasBNB' => $requiredGasBNB,
        ];
    }
}
