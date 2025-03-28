<?php

namespace App\Helpers;

use App\Models\MasterWallet;
use App\Models\MasterWalletTransaction;
use App\Models\Ledger;
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
                'ETHEREUM', 'BSC' => [
                    'to' => $toAddress,
                    'amount' => (string)$sendAmount,
                    'contractAddress' => $contractAddress,
                    'fromPrivateKey' => $privateKey,
                ],
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
                default    => throw new \Exception("Native transfer not supported for $blockchain"),
            };

            $payload = match ($blockchain) {
                'ETHEREUM', 'BSC', 'SOLANA' => [
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

    public static function transferToMasterWallet($virtualAccount, $amount)
    {
        $blockchain = strtolower($virtualAccount->blockchain);
        $user = $virtualAccount->user;
        $walletCurrency = strtoupper($virtualAccount->currency); // e.g. USDT_TRON

        // Skip BTC → use batching logic for that
        if ($blockchain === 'bitcoin') {
            return 'BTC transfers to master wallet are handled via batching.';
        }

        $masterWallet = MasterWallet::where('blockchain', strtoupper($blockchain))->first();
        if (!$masterWallet) return false;

        $privateKey = Crypt::decrypt($virtualAccount->private_key);

        // Define endpoint
        $endpoint = match ($blockchain) {
            'ethereum' => '/ethereum/transaction',
            'bsc' => '/bsc/transaction',
            'litecoin' => '/litecoin/transaction',
            'tron' => '/tron/transaction',
            'solana' => '/solana/transaction',
            default => null
        };

        if (!$endpoint) return false;

        // Contract-based tokens
        $tokenContracts = [
            'USDT_TRON' => 'TXLAQ63Xg1NAzckPwKHvzw7CSEmLMEqcdj',
            'USDT_BSC' => '0x55d398326f99059fF775485246999027B3197955',
            'USDT_ETH' => '0xdAC17F958D2ee523a2206206994597C13D831ec7',
            'USDC_BSC' => '0x8ac76a51cc950d9822d68b83fe1ad97b32cd580d',
            'USDC_ETH' => '0xA0b86991C6218b36c1d19D4a2e9Eb0cE3606EB48',
            'USDC_SOL' => 'EPjFWdd5AufqSSqeM2qN1xzybapC8G4wEGGkZwyTDt1v',
        ];

        $payload = [
            'fromPrivateKey' => $privateKey,
            'to' => $masterWallet->address,
            'amount' => (string)$amount,
        ];

        // If token transfer is needed
        if (array_key_exists($walletCurrency, $tokenContracts)) {
            $contractAddress = $tokenContracts[$walletCurrency];

            if ($blockchain === 'tron') {
                $payload['tokenId'] = $contractAddress;
            } elseif ($blockchain === 'sol') {
                $endpoint = '/solana/transaction/spl';
                $payload['contractAddress'] = $contractAddress;
            } else {
                // ERC20 / BEP20
                $payload['contractAddress'] = $contractAddress;
                $endpoint = match ($blockchain) {
                    'eth' => '/ethereum/transaction/token',
                    'bsc' => '/bsc/transaction/token',
                    default => $endpoint,
                };
            }
        }

        $response = Http::withHeaders([
            'x-api-key' => config('tatum.api_key')
        ])->post(config('tatum.base_url') . $endpoint, $payload);
        Log::info("Transer to master waller response json: " . json_encode($response->json()));
        if ($response->failed()) {
            throw new \Exception("Failed to transfer to master wallet: " . $response->body());
        }

        $tx = $response->json();

        // Transaction::create([
        //     'user_id' => $user->id,
        //     'blockchain' => strtoupper($blockchain),
        //     'currency' => $walletCurrency,
        //     'from_address' => $virtualAccount->address,
        //     'to_address' => $masterWallet->address,
        //     'amount' => $amount,
        //     'tx_hash' => $tx['txId'] ?? null,
        //     'type' => 'to_master',
        // ]);
        MasterWalletTransaction::create([
            'user_id' => $user->id,
            'master_wallet_id' => $masterWallet->id,
            'blockchain' => $blockchain,
            'currency' => $walletCurrency,
            'to_address' => $masterWallet->address,
            'amount' => $amount,
            'fee' => '0',
            'tx_hash' => $tx['txId'] ?? null,
        ]);

        // // Estimate gas fee
        // $fee = estimateGasFee($blockchain, $amount, true); // pass `true` to indicate token tx if needed

        // GasFeeLog::create([
        //     'user_id' => $user->id,
        //     'blockchain' => strtoupper($blockchain),
        //     'estimated_fee' => $fee['fee'],
        //     'fee_currency' => $fee['currency'],
        //     'tx_type' => 'to_master',
        //     'tx_hash' => $tx['txId'] ?? null,
        // ]);

        return $tx;
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
                            $endpoint = "$baseUrl/{$blockchain}/address/balance/{$tokenContract}/$address";
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
                            return (float) $sun / 1_000_000;
                        }

                        return 0;
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
}
