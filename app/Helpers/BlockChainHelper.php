<?php

namespace App\Helpers;

use App\Models\DepositAddress;
use App\Models\GasFeeLog;
use App\Models\MasterWallet;
use App\Models\MasterWalletTransaction;
use App\Models\Ledger;
use App\Models\VirtualAccount;
use App\Models\WalletCurrency;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Log;

class BlockChainHelper
{
    public static function sendToExternalAddress($user, $blockchain, $currency, $toAddress, $amount)
    {
        $blockchain = strtoupper($blockchain);
        $currency = strtoupper($currency);
        $fee = 0; // Placeholder fee logic

        $walletCurrency = WalletCurrency::where([
            'blockchain' => $blockchain,
            'currency' => $currency,
        ])->first();

        if (!$walletCurrency) {
            throw new \Exception("Unsupported currency $currency on $blockchain.");
        }

        $isToken = $walletCurrency->is_token;
        $contractAddress = $walletCurrency->contract_address;

        $masterWallet = MasterWallet::where('blockchain', $blockchain)->first();

        if (!$masterWallet) {
            throw new \Exception("Master wallet not found for $blockchain.");
        }

        $privateKey = Crypt::decrypt($masterWallet->private_key);
        $sendAmount = $amount - $fee;

        if ($sendAmount <= 0) {
            throw new \Exception("Amount after fee must be greater than 0.");
        }

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
            'amount' => (string) $amount,
            'fee' => (string) $fee,
            'attr' => 'User withdrawal to external wallet',
        ]);

        if ($withdrawalResponse->failed()) {
            throw new \Exception("Ledger withdrawal failed: " . $withdrawalResponse->body());
        }

        $withdrawalId = $withdrawalResponse->json()['id'] ?? null;

        $endpoint = '';
        $payload = [];

        if ($isToken) {
            $endpoint = match ($blockchain) {
                'ETHEREUM' => '/ethereum/transaction/token',
                'BSC'      => '/bsc/transaction/token',
                'TRON'     => '/tron/transaction',
                'SOLANA'   => '/solana/transaction/spl',
                default    => throw new \Exception("Token transfers not supported on $blockchain"),
            };

            $payload = match ($blockchain) {
                'TRON' => [
                    'to' => $toAddress,
                    'amount' => (string)$sendAmount,
                    'fromPrivateKey' => $privateKey,
                    'tokenId' => $contractAddress,
                ],
                'SOLANA' => [
                    'to' => $toAddress,
                    'amount' => (string)$sendAmount,
                    'fromPrivateKey' => $privateKey,
                    'contractAddress' => $contractAddress,
                ],
            };
        } else {
            $endpoint = match ($blockchain) {
                'ETHEREUM' => '/ethereum/transaction',
                'BSC'      => '/bsc/transaction',
                'BITCOIN'  => '/bitcoin/transaction',
                'LITECOIN' => '/litecoin/transaction',
                'SOLANA'   => '/solana/transaction',
                'TRON'     => '/tron/transaction', // ✅ Added native TRON support
                default    => throw new \Exception("Native transfer not supported for $blockchain"),
            };

            $payload = match ($blockchain) {
                'ETHEREUM' => [
                    'fromPrivateKey' => $privateKey,
                    'to' => $toAddress,
                    'amount' => (string)$sendAmount,
                    'currency' => $currency,
                ],
                'BSC', 'SOLANA', 'TRON' => [
                    'fromPrivateKey' => $privateKey,
                    'to' => $toAddress,
                    'amount' => (string)$sendAmount,
                ],
                'BITCOIN', 'LITECOIN' => [
                    'fromAddress' => [[
                        'address' => $masterWallet->address,
                        'privateKey' => $privateKey,
                    ]],
                    'to' => [[
                        'address' => $toAddress,
                        'value' => (string)$sendAmount,
                    ]],
                ],
            };
        }


        $response = Http::withHeaders([
            'x-api-key' => config('tatum.api_key'),
        ])->post(config('tatum.base_url') . $endpoint, $payload);

        if ($response->failed()) {
            if ($withdrawalId) {
                Http::withHeaders([
                    'x-api-key' => config('tatum.api_key'),
                ])->delete(config('tatum.base_url') . "/offchain/withdrawal/{$withdrawalId}");
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
            'amount' => $sendAmount,
            'fee' => $fee,
            'tx_hash' => $txHash,
        ]);

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
            'sent' => $sendAmount,
            'fee' => $fee,
            'total' => $amount,
        ];
    }
    public static function dispatchTransferToMasterWallet($virtualAccount, $amount)
    {
        $blockchain = strtolower($virtualAccount->blockchain);
        $currency = strtoupper($virtualAccount->currency);

        try {
            return match (true) {
                $blockchain === 'ethereum' => self::transferETHToMasterWallet($virtualAccount, $amount),
                $blockchain === 'bsc' => self::transferBSCToMasterWallet($virtualAccount, $amount),

                $blockchain === 'tron' && $currency == 'TRON' => self::transferTRXToMasterWallet($virtualAccount, $amount),

                $blockchain === 'tron' && $currency == 'USDT_TRON' => self::transferUSDTTronToMasterWallet($virtualAccount, $amount),

                default => throw new \Exception("Unsupported blockchain or currency: $blockchain / $currency"),
            };
        } catch (\Exception $e) {
            Log::error("Transfer dispatch failed for user ID {$virtualAccount->user_id}: " . $e->getMessage());
            throw $e;
        }
    }

    public static function transferToMasterWallet($virtualAccount, $amount)
    {
        $blockchain = strtolower($virtualAccount->blockchain);
        $user = $virtualAccount->user;
        $walletCurrency = strtoupper($virtualAccount->currency);

        // Skip BTC
        if ($blockchain === 'bitcoin') {
            return 'BTC transfers to master wallet are handled via batching.';
        }

        // Get deposit address + private key
        $deposit = \App\Models\DepositAddress::where('virtual_account_id', $virtualAccount->id)->first();
        if (!$deposit) {
            throw new \Exception("Deposit address not found for VA ID: {$virtualAccount->id}");
        }
        $encryptedKey = $deposit->private_key;
        $fromPrivateKey = Crypt::decryptString($encryptedKey); // ✅ correct


        // Get master wallet
        $masterWallet = \App\Models\MasterWallet::where('blockchain', strtoupper($blockchain))->first();
        if (!$masterWallet) {
            throw new \Exception("Master wallet not configured for blockchain: {$blockchain}");
        }
        $beforeTransactionBalance = BlockChainHelper::checkAddressBalance($masterWallet->address, $blockchain, $masterWallet->contract_address);

        // Endpoint mapping
        $endpoint = match ($blockchain) {
            'ethereum' => '/ethereum/transaction',
            'bsc' => '/bsc/transaction',
            'litecoin' => '/litecoin/transaction',
            'tron' => '/tron/transaction',
            'solana' => '/solana/transaction',
            default => null
        };

        if (!$endpoint) return false;

        // Contract tokens
        $tokenContracts = [
            'USDT_TRON' => 'TXLAQ63Xg1NAzckPwKHvzw7CSEmLMEqcdj',
            'USDT_BSC' => '0x55d398326f99059fF775485246999027B3197955',
            'USDT' => '0xdAC17F958D2ee523a220620699459C13D831ec7',
            'USDC_BSC' => '0x8ac76a51cc950d9822d68b83fe1ad97b32cd580d',
            'USDC_ETH' => '0xA0b86991C6218b36c1d19D4a2e9Eb0cE3606EB48',
            'USDC_SOL' => 'EPjFWdd5AufqSSqeM2qN1xzybapC8G4wEGGkZwyTDt1v',
        ];

        $payload = [
            'from' => $deposit->address,
            'fromPrivateKey' => $fromPrivateKey,
            'to' => $masterWallet->address,
            'amount' => (string)$amount,
        ];
        if ($blockchain == 'ethereum') {
            $payload['currency'] = $walletCurrency;
        }

        // Handle token transfer
        if (array_key_exists($walletCurrency, $tokenContracts)) {
            $contractAddress = $tokenContracts[$walletCurrency];

            if ($blockchain === 'tron') {
                $payload['tokenId'] = $contractAddress;
            } elseif ($blockchain === 'solana') {
                $endpoint = '/solana/transaction/spl';
                $payload['contractAddress'] = $contractAddress;
            } else {
                $payload['contractAddress'] = $contractAddress;
                $endpoint = match ($blockchain) {
                    // 'ethereum' => '/ethereum/transaction/token',
                    'bsc' => '/bsc/transaction/token',
                    default => $endpoint,
                };
            }
        }

        $response = Http::withHeaders([
            'x-api-key' => config('tatum.api_key')
        ])->post(config('tatum.base_url') . $endpoint, $payload);

        Log::info("Transfer to master wallet response: " . json_encode($response->json()));

        if ($response->failed()) {
            throw new \Exception("Failed to transfer to master wallet: " . $response->body());
        }


        $tx = $response->json();
        // $afterTransactionBalance = BlockChainHelper::checkAddressBalance($masterWallet->address, $blockchain, $masterWallet->contract_address);

        // //fee will be the difference between the amoun + befoe addres and after actual balance

        // $estimatedBalance = $amount + $beforeTransactionBalance['balance'];
        // $fee = $estimatedBalance - $afterTransactionBalance['balance'];
        // Record transaction
        \App\Models\MasterWalletTransaction::create([
            'user_id' => $user->id,
            'master_wallet_id' => $masterWallet->id,
            'blockchain' => $blockchain,
            'currency' => $walletCurrency,
            'to_address' => $masterWallet->address,
            'amount' => $amount,
            'fee' => '0',
            'tx_hash' => $tx['txId'] ?? null,
        ]);
        GasFeeLog::create([
            'user_id' => $user->id,
            'blockchain' => $blockchain,
            'estimated_fee' => '0',
            'fee_currency' => $walletCurrency,
            'tx_type' => 'transfer',
            'tx_hash' => $tx['txId'] ?? null,
        ]);
        return $tx;
    }
    public static function transferETHToMasterWallet($virtualAccount, $amount)
    {
        try {
            $user = $virtualAccount->user;
            $walletCurrency = strtoupper($virtualAccount->currency); // ETH, USDT, USDC

            $deposit = \App\Models\DepositAddress::where('virtual_account_id', $virtualAccount->id)->firstOrFail();
            $fromPrivateKey = Crypt::decryptString($deposit->private_key);

            $masterWallet = \App\Models\MasterWallet::where('blockchain', 'ETHEREUM')->firstOrFail();
            $gasfee = self::estimateGasFee($deposit->address, $masterWallet->address, $amount, 'ETH');
            Log::info("gas fee for transaction is ", $gasfee);

            $estimatedLimit = (int) $gasfee['gasLimit'];
            $bufferedLimit = (string) ($estimatedLimit + 70000); // Add 5,000 gas units buffer

            $gasPriceWei = $gasfee['gasPrice'];
            $gasPriceGwei = (string) max(1, intval(ceil(intval($gasfee['gasPrice']) / 1e9)));

            $payload = [
                'fromPrivateKey' => $fromPrivateKey,
                'to' => $masterWallet->address,
                'amount' => (string) $amount,
                'currency' => $walletCurrency, // ETH, USDT, etc.
            ];
            $payload['fee'] = [
                'gasLimit' => $bufferedLimit, // Example for ERC20
                'gasPrice' => $gasPriceGwei // 60 Gwei
            ];

            $response = Http::withHeaders([
                'x-api-key' => config('tatum.api_key'),
            ])->post(config('tatum.base_url') . '/ethereum/transaction', $payload);

            if ($response->failed()) {
                throw new \Exception("ETH/ERC20 Transfer Failed: " . $response->body());
            }

            $tx = $response->json();
            $txHash = $tx['txId'] ?? null;

            \App\Models\MasterWalletTransaction::create([
                'user_id' => $user->id,
                'master_wallet_id' => $masterWallet->id,
                'blockchain' => 'ethereum',
                'currency' => $walletCurrency,
                'to_address' => $masterWallet->address,
                'amount' => $amount,
                'fee' => '0',
                'tx_hash' => $txHash,
            ]);

            return $tx;
        } catch (\Exception $e) {
            Log::error("Transfer ETH to master wallet failed: " . $e->getMessage());
            throw $e;
        }
    }
    public static function transferTRXToMasterWallet($virtualAccount, $amount)
    {
        $user = $virtualAccount->user;

        $deposit = \App\Models\DepositAddress::where('virtual_account_id', $virtualAccount->id)->firstOrFail();
        $fromPrivateKey = Crypt::decryptString($deposit->private_key);

        $masterWallet = \App\Models\MasterWallet::where('blockchain', 'TRON')->firstOrFail();

        $payload = [
            'fromPrivateKey' => $fromPrivateKey,
            'to' => $masterWallet->address,
            'amount' => (string) $amount,
        ];

        $response = Http::withHeaders([
            'x-api-key' => config('tatum.api_key'),
        ])->post(config('tatum.base_url') . '/tron/transaction', $payload);

        if ($response->failed()) {
            throw new \Exception("TRX Transfer Failed: " . $response->body());
        }

        $tx = $response->json();
        $txHash = $tx['txId'] ?? null;

        \App\Models\MasterWalletTransaction::create([
            'user_id' => $user->id,
            'master_wallet_id' => $masterWallet->id,
            'blockchain' => 'tron',
            'currency' => 'TRX',
            'to_address' => $masterWallet->address,
            'amount' => $amount,
            'fee' => '0',
            'tx_hash' => $txHash,
        ]);

        return $tx;
    }
    public static function transferUSDTTronToMasterWallet($virtualAccount, $amount)
    {
        $user = $virtualAccount->user;

        $deposit = \App\Models\DepositAddress::where('virtual_account_id', $virtualAccount->id)->firstOrFail();
        $fromPrivateKey = Crypt::decryptString($deposit->private_key);

        $masterWallet = \App\Models\MasterWallet::where('blockchain', 'TRON')->firstOrFail();

        $payload = [
            'fromPrivateKey' => $fromPrivateKey,
            'to' => $masterWallet->address,
            'amount' => (string) $amount,
            'tokenAddress' => 'TR7NHqjeKQxGTCi8q8ZY4pL8otSzgjLj6t', // USDT_TRON
            'feeLimit' => 10000, // max fee in TRX
        ];

        $response = Http::withHeaders([
            'x-api-key' => config('tatum.api_key'),
        ])->post(config('tatum.base_url') . '/tron/trc20/transaction', $payload);

        if ($response->failed()) {
            throw new \Exception("USDT_TRON Transfer Failed: " . $response->body());
        }

        $tx = $response->json();
        $txHash = $tx['txId'] ?? null;

        \App\Models\MasterWalletTransaction::create([
            'user_id' => $user->id,
            'master_wallet_id' => $masterWallet->id,
            'blockchain' => 'tron',
            'currency' => 'USDT_TRON',
            'to_address' => $masterWallet->address,
            'amount' => $amount,
            'fee' => '0',
            'tx_hash' => $txHash,
        ]);

        return $tx;
    }
    public static function transferBSCToMasterWallet($virtualAccount, $amount)
    {
        $user = $virtualAccount->user;
        $currency = strtoupper($virtualAccount->currency); // BNB, USDT_BSC, USDC_BSC

        $deposit = \App\Models\DepositAddress::where('virtual_account_id', $virtualAccount->id)->firstOrFail();
        $fromPrivateKey = Crypt::decryptString($deposit->private_key);

        $masterWallet = \App\Models\MasterWallet::where('blockchain', 'BSC')->firstOrFail();

        $payload = [
            'fromPrivateKey' => $fromPrivateKey,
            'to' => $masterWallet->address,
            'amount' => (string) $amount,
            'currency' => $currency,
        ];

        $response = Http::withHeaders([
            'x-api-key' => config('tatum.api_key'),
        ])->post(config('tatum.base_url') . '/bsc/transaction', $payload);

        if ($response->failed()) {
            throw new \Exception("BSC Transfer Failed: " . $response->body());
        }

        $tx = $response->json();
        $txHash = $tx['txId'] ?? null;

        \App\Models\MasterWalletTransaction::create([
            'user_id' => $user->id,
            'master_wallet_id' => $masterWallet->id,
            'blockchain' => 'bsc',
            'currency' => $currency,
            'to_address' => $masterWallet->address,
            'amount' => $amount,
            'fee' => '0',
            'tx_hash' => $txHash,
        ]);

        return $tx;
    }
    public static function estimateGasFee(string $from, string $to, string $amount, string $currency, $chain = 'ETH')
    {
        $apiKey = config('tatum.api_key');
        $baseUrl = 'https://api.tatum.io/v4';
        $endpoint = '/blockchainOperations/gas';

        $payload = [
            'from' => $from,
            'to' => $to,
            'chain' => $chain,
        ];

        $currency = strtoupper($currency);

        if (in_array($currency, ['USDT', 'USDC', 'USDT_BSC', 'USDC_BSC'])) {
            $contractAddresses = [
                'USDT' => '0xdAC17F958D2ee523a2206206994597C13D831ec7',
                'USDC' => '0xA0b86991C6218b36c1d19D4a2e9Eb0cE3606EB48',
                'USDT_BSC' => '0x55d398326f99059fF775485246999027B3197955',
                'USDC_BSC' => '0x8ac76a51cc950d9822d68b83fe1ad97b32cd580d',
            ];

            if (!isset($contractAddresses[$currency])) {
                throw new \Exception("Contract address for {$currency} is not defined.");
            }

            $payload['contractAddress'] = $contractAddresses[$currency];
            $payload['amount'] = "0.000001";
        } else {
            // Only add amount for native token transfers like ETH
            $payload['amount'] = $amount;
        }

        $response = Http::withHeaders([
            'x-api-key' => $apiKey,
        ])->post("{$baseUrl}{$endpoint}", $payload);

        if ($response->failed()) {
            throw new \Exception("Gas estimation failed: " . $response->body());
        }

        return $response->json();
    }

    public static function batchSweepBTCToMasterWallet()
    {
        $unswept = \App\Models\DepositAddress::where('blockchain', 'BTC')
            ->where('swept', false)
            ->take(5)
            ->get();

        if ($unswept->count() < 5) return 'Not enough deposits yet.';

        $fromAddress = [];
        $totalAmount = 0;

        foreach ($unswept as $deposit) {
            $balance = BlockChainHelper::checkAddressBalance($deposit->address); // via Tatum
            if ($balance <= 0) continue;

            $fromAddress[] = [
                'address' => $deposit->address,
                'privateKey' => Crypt::decrypt($deposit->private_key)
            ];
            $totalAmount += $balance;
        }

        if (count($fromAddress) < 2 || $totalAmount <= 0) {
            return 'Insufficient balance or UTXOs.';
        }

        $masterWallet = MasterWallet::where('blockchain', 'BTC')->first();

        $payload = [
            'fromAddress' => $fromAddress,
            'to' => [[
                'address' => $masterWallet->address,
                'value' => (string) $totalAmount,
            ]],
        ];

        $response = Http::withHeaders([
            'x-api-key' => config('tatum.api_key'),
        ])->post(config('tatum.base_url') . '/bitcoin/transaction', $payload);

        if ($response->failed()) {
            throw new \Exception("Batch sweep failed: " . $response->body());
        }

        $txId = $response->json()['txId'] ?? null;

        foreach ($unswept as $deposit) {
            $deposit->update(['swept' => true]);
            // Optionally log the swept amount, fee, txId etc.
        }

        return "Swept {$totalAmount} BTC to master wallet in tx: $txId";
    }

    public static function checkAddressBalance(string $address, string $blockchain = 'bitcoin', string $tokenContract = null)
    {
        $blockchain = strtolower($blockchain);
        $baseUrl = config('tatum.base_url');
        $apiKey = config('tatum.api_key');

        $headers = [
            'x-api-key' => $apiKey,
        ];
        // Log::info

        try {
            switch ($blockchain) {
                case 'bitcoin':
                case 'litecoin': {
                        $endpoint = "$baseUrl/{$blockchain}/address/balance/$address";
                        $response = Http::withHeaders($headers)->get($endpoint);
                        // return $response->ok() ? (float)$response->json()['balance'] : 0;
                        return $response->json();
                    }

                case 'ethereum':
                case 'bsc': {
                        if ($tokenContract) {
                            // Token balance (e.g. USDT, USDC)
                            $endpoint = "$baseUrl/{$blockchain}/token/balance/{$tokenContract}/$address";
                            $response = Http::withHeaders($headers)->get($endpoint);
                            // return $response->ok() ? (float)$response->json()['balance'] : 0;
                            return $response->json();
                        } else {
                            // Native coin (ETH, BNB)
                            $endpoint = "$baseUrl/{$blockchain}/account/balance/$address";
                            $response = Http::withHeaders($headers)->get($endpoint);
                            // return $response->ok() ? (float)$response->json()['balance'] : 0;
                            return $response->json();
                        }
                    }

                case 'tron': {
                        $endpoint = "$baseUrl/tron/account/$address";
                        $response = Http::withHeaders($headers)->get($endpoint);
                        //return complete json
                        if ($response->ok()) {
                            $sun = $response->json()['balance'] ?? 0;

                            return  $response->json();
                        }

                        return  $response->json();
                        // return $response->ok() ? (float)$response->json()['balance']['availableBalance'] / 1_000_000 : 1;
                    }

                case 'solana': {
                        $endpoint = "$baseUrl/solana/account/balance/$address";
                        $response = Http::withHeaders($headers)->get($endpoint);
                        // return $response->ok() ? (float)$response->json()['balance'] : 0;
                        return $response->json();
                    }

                default:
                    throw new \Exception("Unsupported blockchain: $blockchain");
            }
        } catch (\Throwable $e) {
            // Log::error("Balance check failed for $blockchain ($address): " . $e->getMessage());
            // return 0;
            throw new \Exception("Balance check failed for $blockchain ($address): " . $e->getMessage());
        }
    }

    public static function logAndEstimateGasFee(array $params): array
    {
        $blockchain = strtolower($params['blockchain']);
        $userId = $params['user_id'];
        $txType = $params['tx_type'] ?? 'transfer';
        $txHash = $params['tx_hash'] ?? null;
        $beforeBalance = $params['before_balance'] ?? null;
        $afterBalance = $params['after_balance'] ?? null;

        $apiKey = config('tatum.api_key');
        $baseUrl = config('tatum.base_url');
        $fee = 0;
        $currency = strtoupper($blockchain);

        try {
            switch ($blockchain) {
                case 'btc':
                case 'ltc': {
                        $response = Http::withHeaders([
                            'x-api-key' => $apiKey,
                        ])->get("{$baseUrl}/blockchain/fee/" . strtoupper($blockchain));

                        if ($response->failed()) break;

                        $feePerByte = $response->json()['medium']; // sat/byte
                        $estimatedTxSize = 250; // adjust if needed
                        $fee = $feePerByte * $estimatedTxSize / 100_000_000;
                        break;
                    }

                case 'eth':
                case 'bsc': {
                        $response = Http::withHeaders([
                            'x-api-key' => $apiKey,
                        ])->post("{$baseUrl}/{$blockchain}/gas");

                        if ($response->failed()) break;

                        $gasPriceWei = $response->json()['gasPrice'];
                        $gasLimit = match ($txType) {
                            'transfer' => 21000,
                            'token' => 60000,
                            default => 21000
                        };

                        $fee = ($gasPriceWei * $gasLimit) / pow(10, 18);
                        $currency = $blockchain === 'eth' ? 'ETH' : 'BNB';
                        break;
                    }

                case 'sol': {
                        $rpc = 'https://api.mainnet-beta.solana.com';
                        $rpcResponse = Http::post($rpc, [
                            'jsonrpc' => '2.0',
                            'id' => 1,
                            'method' => 'getRecentBlockhash',
                            'params' => [],
                        ]);

                        if ($rpcResponse->failed()) break;

                        $feeLamports = $rpcResponse->json()['result']['value']['feeCalculator']['lamportsPerSignature'];
                        $fee = $feeLamports / 1_000_000_000;
                        $currency = 'SOL';
                        break;
                    }

                case 'tron': {
                        $currency = 'TRX';
                        if ($beforeBalance !== null && $afterBalance !== null) {
                            $fee = round($beforeBalance - $afterBalance, 6); // high precision
                        } else {
                            Log::warning("TRON fee estimation skipped: missing balance comparison.");
                        }
                        break;
                    }
            }

            // Save to gas_fee_logs
            // GasFeeLog::create([
            //     'user_id' => $userId,
            //     'blockchain' => strtoupper($blockchain),
            //     'estimated_fee' => $fee,
            //     'fee_currency' => $currency,
            //     'tx_type' => $txType,
            //     'tx_hash' => $txHash,
            // ]);

            return [
                'fee' => $fee,
                'currency' => $currency
            ];
        } catch (\Exception $e) {
            Log::error("Fee logging failed for {$blockchain}: " . $e->getMessage());
            return ['fee' => 0, 'currency' => $currency];
        }
    }
    public static function sendFromVirtualToExternalTron($accountId, $currency, $toAddress, $amount)
    {
        $blockchain = 'TRON';
        $currency = strtoupper($currency);
        $fee = 0; // Placeholder, you can update your fee logic
        $sendAmount = $amount - $fee;

        if ($sendAmount <= 0) {
            throw new \Exception("Amount after fee must be greater than 0.");
        }
        $virtualAccountId = VirtualAccount::where('account_id', $accountId)->first();

        // Get contract address only for USDT
        $contractAddress = null;
        if ($currency === 'USDT_TRON') {
            $walletCurrency = WalletCurrency::where([
                'blockchain' => $blockchain,
                'currency' => 'USDT_TRON',
            ])->first();

            if (!$walletCurrency || !$walletCurrency->contract_address) {
                throw new \Exception("USDT_TRON contract address not found.");
            }

            $contractAddress = $walletCurrency->contract_address;
        }

        // Get user's deposit address and private key
        $deposit = DepositAddress::where('virtual_account_id', $virtualAccountId->id)->first();
        if (!$deposit) {
            throw new \Exception("Deposit address not found for VA ID: {$virtualAccountId->id}");
        }

        $privateKey = Crypt::decryptString($deposit->private_key);

        // Step 1: Withdraw from virtual ledger
        $withdrawalResponse = Http::withHeaders([
            'x-api-key' => config('tatum.api_key'),
        ])->post(config('tatum.base_url') . '/offchain/withdrawal', [
            'senderAccountId' => $accountId,
            'address' => $toAddress,
            'amount' => (string) $amount,
            'fee' => (string) $fee,
            'attr' => "Withdrawal to $currency address on TRON",
        ]);

        if ($withdrawalResponse->failed()) {
            throw new \Exception("Ledger withdrawal failed: " . $withdrawalResponse->body());
        }

        $withdrawalId = $withdrawalResponse->json()['id'] ?? null;

        // Step 2: Send on-chain transaction
        $endpoint = $currency === 'USDT_TRON' ? '/tron/trc20/transaction' : '/tron/transaction';

        $payload = [
            'fromPrivateKey' => $privateKey,
            'to' => $toAddress,
            'amount' => (string) $sendAmount,
        ];

        if ($currency === 'USDT_TRON') {
            $payload['tokenAddress'] = $contractAddress;
            // $payload['feeLimit'] = 100000000; // 100 TRX for safety margin
        }

        $txResponse = Http::withHeaders([
            'x-api-key' => config('tatum.api_key'),
        ])->post(config('tatum.base_url') . $endpoint, $payload);

        if ($txResponse->failed()) {
            if ($withdrawalId) {
                Http::withHeaders([
                    'x-api-key' => config('tatum.api_key'),
                ])->delete(config('tatum.base_url') . "/offchain/withdrawal/{$withdrawalId}");
            }
            throw new \Exception("Blockchain transaction failed: " . $txResponse->body());
        }

        $txHash = $txResponse->json()['txId'] ?? null;

        // Step 3: Link ledger withdrawal with blockchain tx
        if ($withdrawalId && $txHash) {
            Http::withHeaders([
                'x-api-key' => config('tatum.api_key'),
            ])->post(config('tatum.base_url') . "/offchain/withdrawal/{$withdrawalId}/{$txHash}");
        }

        return [
            'txHash' => $txHash,
            'sent' => $sendAmount,
            'fee' => $fee,
            'total' => $amount,
        ];
    }
}
