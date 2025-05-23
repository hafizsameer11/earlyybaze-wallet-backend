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
        $originalGasLimit = $gasEstimation['gasLimit'];
        $minGasPrice = 10000000000; // 10 Gwei
        $gasPrice = max((int)$gasEstimation['gasPrice'], $minGasPrice);



        // Apply a 10% buffer to gas limit
        $bufferedGasLimit = ceil($originalGasLimit * 1.3); // Round up

        $requiredGasWei = bcmul((string)$gasPrice, (string)$bufferedGasLimit);
        $requiredGasEth = bcdiv($requiredGasWei, bcpow('10', '18'), 18);

        // 2. Check ETH balance of user address
        $ethBalance = BlockChainHelper::checkAddressBalance($fromAddress, 'ethereum');
        $ethBalance = $ethBalance['balance'];

        // Normalize both values to 18 decimals
        $ethBalanceFormatted = number_format((float)$ethBalance, 18, '.', '');
        $requiredGasEthFormatted = number_format((float)$requiredGasEth, 18, '.', '');

        Log::info('Gas Check Debug', [
            'ethBalance' => $ethBalanceFormatted,
            'requiredGasEth' => $requiredGasEthFormatted,
            'bufferedGasLimit' => $bufferedGasLimit,
            'gasPrice' => $gasPrice
        ]);

        if (bccomp($ethBalanceFormatted, $requiredGasEthFormatted, 18) < 0) {
            Log::info("ETH balance is insufficient. Initiating gas top-up.");

            // 3. Top-up gas if insufficient
            $tx = $this->topUpUserForGas($masterWallet, $fromAddress, $requiredGasEthFormatted, $currency);
            $txDetails = $this->getTransactionDetailsWithPolling($tx['txId']);
            Log::info('Gas top-up transaction details', [
                'txId' => $tx['txId'],
                'txDetails' => $txDetails,
            ]);

            if (!($txDetails['status'] ?? false)) {
                throw new \Exception("Gas top-up failed. Cannot proceed.");
            }
            $this->logActualGasFee($user->id, $tx['txId'], 'ETH', 'gas-topup');
        } else {
            Log::info("ETH balance is sufficient. Proceeding with asset transfer.");
        }

        return $this->executeAssetTransfer($fromPrivateKey, $fromAddress, $toAddress, $amount, $currency, $user, $masterWallet);
    }



    public function topUpUserForGas($masterWallet, $toAddress, $requiredGasEth, $currency)
    {

        $fromPrivateKey = Crypt::decrypt($masterWallet->private_key);
        Log::info('Top-up for gas initiated', [
            'fromPrivateKey' => $fromPrivateKey,
            'toAddress' => $toAddress,
            'requiredGasEth' => $requiredGasEth,
            'masterWallet' => $masterWallet,
        ]);
        $fromAddress = $masterWallet->address;
        $bufferedAmount = bcadd($requiredGasEth, '0.0002', 18);
        $gasfee = BlockChainHelper::estimateGasFee($fromAddress, $toAddress, $bufferedAmount, $currency);
        $gasLimit = (int) $gasfee['gasLimit'] + 70000;
        $gasPriceGwei = (string) max(1, intval(ceil(intval($gasfee['gasPrice']) / 1e9)));
        $payload = [
            'fromPrivateKey' => $fromPrivateKey,
            'to' => $toAddress,
            'amount' => (string) $bufferedAmount,
            'currency' => 'ETH',
            'fee' => [
                'gasLimit' => (string) $gasLimit,
                'gasPrice' => $gasPriceGwei
            ]
        ];

        $response = Http::withHeaders(['x-api-key' => config('tatum.api_key')])
            ->post(config('tatum.base_url') . '/ethereum/transaction', $payload);
        Log::info('Top-up response: for address ' . $toAddress, ['response' => $response->json()]);
        if ($response->failed()) {
            throw new \Exception("Top-up failed: " . $response->body());
        }

        return $response->json();
    }

    public function getTransactionDetailsWithPolling($txHash, $maxRetries = 5, $delaySeconds = 40)
    {
        for ($i = 0; $i < $maxRetries; $i++) {
            $response = Http::withHeaders(['x-api-key' => config('tatum.api_key')])
                ->get(config('tatum.base_url') . "/ethereum/transaction/{$txHash}");
            Log::info('Transaction details response', $response->json());
            if ($response->ok()) {
                $data = $response->json();
                if (isset($data['status'])) {
                    return $data;
                }
            }

            sleep($delaySeconds);
        }
        $data = [
            'status' => true,
            'txId' => $txHash,
        ];
        Log::info("Transaction still not able to fin now sending status true by sysstem");

        return $data ?? null;
        // throw new \Exception("Transaction not confirmed within timeout.");
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
    public function transferToExternalAddress($user, string $toAddress, string $amount, string $currency = 'ETH', array $fee = [])
    {
        $blockchain = 'ethereum';
        $currency = strtoupper($currency);

        // 1. Decrypt master wallet
        $masterWallet = MasterWallet::where('blockchain', $blockchain)->firstOrFail();
        $fromPrivateKey = Crypt::decrypt($masterWallet->private_key);
        $fromAddress = $masterWallet->address;

        // 2. Estimate gas fee
        $gasEstimation = BlockChainHelper::estimateGasFee($fromAddress, $toAddress, $amount, $currency, 'ETH');

        $originalGasLimit = (int) ($gasEstimation['gasLimit'] ?? 21000);
        $estimatedGasPrice = (int) ($gasEstimation['gasPrice'] ?? 1000000000); // default to 1 Gwei

        // 3. Apply 70,000 gas buffer and minimum gas price logic
        $gasLimit = isset($fee['gasLimit'])
            ? (int) $fee['gasLimit']
            : $originalGasLimit + 70000;

        $gasPrice = isset($fee['gasPrice'])
            ? (int) $fee['gasPrice']
            : max($estimatedGasPrice, 1000000000); // Ensure at least 1 Gwei

        // 4. Calculate gas fee in ETH
        $requiredGasWei = bcmul((string) $gasPrice, (string) $gasLimit);
        $gasFeeEth = bcdiv($requiredGasWei, bcpow('10', '18'), 18);
        $amount = number_format((float) $amount, 4, '.', '');

        // 5. Prepare the payload for blockchain transaction
        $payload = [
            'fromPrivateKey' => $fromPrivateKey,
            'to' => $toAddress,
            'amount' => (string) $amount,
            'currency' => $currency,

        ];

        // 6. Send transaction using Tatum API
        $response = Http::withHeaders([
            'x-api-key' => config('tatum.api_key'),
        ])->post(config('tatum.base_url') . '/ethereum/transaction', $payload);

        if ($response->failed()) {
            throw new \Exception("Blockchain transaction failed: " . $response->body());
        }

        $txHash = $response->json()['txId'] ?? null;

        // 7. Log Master Wallet Transaction
        MasterWalletTransaction::create([
            'user_id' => $user->id,
            'master_wallet_id' => $masterWallet->id,
            'blockchain' => $blockchain,
            'currency' => $currency,
            'to_address' => $toAddress,
            'amount' => $amount,
            'fee' => $gasFeeEth,
            'tx_hash' => $txHash,
        ]);

        // 8. Update Ledger
        Ledger::create([
            'user_id' => $user->id,
            'type' => 'withdrawal',
            'blockchain' => $blockchain,
            'currency' => $currency,
            'amount' => $amount,
            'tx_hash' => $txHash,
        ]);

        return [
            'txHash' => $txHash,
            'sent' => $amount,
            'fee' => $gasFeeEth,
            'total' => $amount,
        ];
    }



    public function getEthereumMasterBalances()
    {
        $masterWallet = MasterWallet::where('blockchain', 'ETHEREUM')->firstOrFail();
        $address = $masterWallet->address;

        // Get all ERC-20 tokens configured for Ethereum
        $tokens = WalletCurrency::where('blockchain', 'ETHEREUM')
            ->where('is_token', true)
            ->get();
        $ethResponse = Http::withHeaders([
            'x-api-key' => config('tatum.api_key'),
        ])->get(config('tatum.base_url') . "/ethereum/account/balance/{$address}");

        if ($ethResponse->failed()) {
            throw new \Exception("Failed to fetch ETH balance: " . $ethResponse->body());
        }

        $ethBalance = $ethResponse->json()['balance'] ?? '0';

        // 2. Get ERC-20 token balances
        $tokenBalances = [];

        foreach ($tokens as $token) {
            Log::info('Fetching token balance for ' . $token->currency);
            $payload = ['chain' => 'ETH'];
            $tokenResponse = Http::withHeaders([
                'x-api-key' => config('tatum.api_key'),
            ])->get(config('tatum.base_url') . "/blockchain/token/balance/ETH/{$token->contract_address}/{$address}");

            if ($tokenResponse->ok()) {
                $balance = $tokenResponse->json()['balance'] ?? '0';
                $tokenBalances[$token->currency] = $balance;
            } else {
                $tokenBalances[$token->currency] = 'Error: ' . $tokenResponse->status();
            }
        }

        return [
            'address' => $address,
            'eth_balance' => $ethBalance,
            'token_balances' => $tokenBalances,
        ];
    }

    public function transferFromMasterToUserETH(string $toAddress, string $amount, string $currency, ?array $fee = null)
    {
        $currency = strtoupper($currency);

        // 1. Get master wallet
        $masterWallet = MasterWallet::where('blockchain', 'ETHEREUM')->firstOrFail();
        $fromPrivateKey = Crypt::decrypt($masterWallet->private_key);
        $fromAddress = $masterWallet->address;

        // 2. Validate WalletCurrency
        $walletCurrency = WalletCurrency::where([
            'blockchain' => 'ETHEREUM',
            'currency' => $currency,
        ])->firstOrFail();

        // 3. Estimate gas fee
        $gasEstimation = BlockChainHelper::estimateGasFee($fromAddress, $toAddress, $amount, $currency);
        $originalGasLimit = $gasEstimation['gasLimit'];
        $minGasPrice = 1000000000; // 1 Gwei
        $gasPrice = max((int) $gasEstimation['gasPrice'], $minGasPrice);
        $bufferedGasLimit = ceil($originalGasLimit * 1.3); // Apply 30% buffer

        $requiredGasWei = bcmul((string)$gasPrice, (string)$bufferedGasLimit);
        $requiredGasEth = bcdiv($requiredGasWei, bcpow('10', '18'), 18);

        Log::info('Gas estimation for transfer from master to user', [
            'from' => $fromAddress,
            'to' => $toAddress,
            'currency' => $currency,
            'originalGasLimit' => $originalGasLimit,
            'bufferedGasLimit' => $bufferedGasLimit,
            'gasPrice' => $gasPrice,
            'requiredGasWei' => $requiredGasWei,
            'requiredGasEth' => $requiredGasEth,
        ]);

        // 4. Build payload
        $payload = [
            'fromPrivateKey' => $fromPrivateKey,
            'to' => $toAddress,
            'amount' => (string) $amount,
            'currency' => $currency,
            'fee' => [
                'gasLimit' => (string) ($fee['gasLimit'] ?? $bufferedGasLimit),
                'gasPrice' => (string) ($fee['gasPrice'] ?? $gasPrice),
            ]
        ];

        // 5. Call the Tatum API
        $response = Http::withHeaders([
            'x-api-key' => config('tatum.api_key'),
        ])->post(config('tatum.base_url') . '/ethereum/transaction', $payload);

        // 6. Log response
        Log::info('Transfer response: for address ' . $toAddress, [
            'response' => $response->json(),
        ]);

        if ($response->failed()) {
            throw new \Exception("Transfer failed: " . $response->body());
        }

        return [
            'txId' => $response->json()['txId'] ?? null,
            'from' => $fromAddress,
            'to' => $toAddress,
            'amount' => $amount,
            'currency' => $currency,
            'estimatedGasEth' => $requiredGasEth,
        ];
    }
}
