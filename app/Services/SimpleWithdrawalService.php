<?php
// app/Services/SimpleWithdrawalService.php

namespace App\Services;

use App\Models\DepositAddress;
use App\Models\ReceivedAsset;
use App\Models\TransferLog;
use App\Models\MasterWallet;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class SimpleWithdrawalService
{
    // ----- Public API -----

    public function flush(string $currency, string $destination, int $limit = 0, bool $dryRun = false): array
    {
        // 1) Load candidates (simple, no reservations; keep it single-run)
        $q = ReceivedAsset::query()
            ->where('currency', $currency)
            ->whereIn('status', ['inWallet','pending'])
            ->orderBy('id');

        if ($limit > 0) $q->limit($limit);

        $items = $q->get();
        if ($items->isEmpty()) {
            return ['success' => true, 'message' => "No pending {$currency} items.", 'count' => 0];
        }

        // 2) Group by sender and aggregate
        $groups = $this->groupBySender($items);
        if (empty($groups)) {
            return ['success' => false, 'message' => 'No valid items found (missing sender/amount).'];
        }

        // 3) Branch by family
        try {
            if ($currency === 'BTC') {
                return $this->flushBtcBatch($groups, $items, $destination, $dryRun);
            }

            if (in_array($currency, ['TRX','USDT_TRON'], true)) {
                return $this->flushTron($currency, $groups, $items, $destination, $dryRun);
            }

            return $this->flushAccountBased($currency, $groups, $items, $destination, $dryRun);

        } catch (\Throwable $e) {
            Log::error('Flush fatal', ['currency'=>$currency, 'err'=>$e->getMessage()]);
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    // ----- BTC (UTXO) -----

    private function flushBtcBatch(array $groups, $items, string $destination, bool $dryRun): array
    {
        $apiKey = $this->apiKey();
        $base   = $this->baseUrl();

        // Build inputs & sum total
        $fromAddress = [];
        $total = 0.0;

        foreach ($groups as $sender => $agg) {
            $wif = $this->decryptWifForSender($sender);
            if (!$wif) { Log::warning('BTC: missing/bad key for sender', ['sender'=>$sender]); continue; }
            $fromAddress[] = ['address' => $sender, 'privateKey' => $wif];
            $total += (float)$agg['amount'];
        }

        if (empty($fromAddress)) {
            return ['success'=>false, 'message'=>'BTC: No decryptable inputs'];
        }

        // simple fee heuristic
        $fee  = max(0.00002, round(count($fromAddress) * 0.000003, 8));
        $send = round($total - $fee, 8);
        $dust = 0.00000546;
        if ($send <= $dust) {
            return ['success'=>false, 'message'=>'BTC: total after fee below dust'];
        }

        $payload = [
            'fromAddress'   => $fromAddress,
            'to'            => [[ 'address' => $destination, 'value' => $send ]],
            'fee'           => number_format($fee, 8, '.', ''),
            'changeAddress' => $destination,
        ];

        $plan = [
            'chain'      => 'BTC',
            'uniqSenders'=> count($fromAddress),
            'rows'       => $items->count(),
            'totalIn'    => round($total,8),
            'fee'        => round($fee,8),
            'toSend'     => $send,
            'destination'=> $destination,
            'dry_run'    => $dryRun,
        ];

        if ($dryRun) return ['success'=>true, 'message'=>'Dry run', 'plan'=>$plan];

        $resp = Http::withHeaders($this->headers($apiKey))
            ->timeout(120)->post($base.'/bitcoin/transaction', $payload);

        if ($resp->failed()) {
            Log::error('BTC batch failed', ['http'=>$resp->status(), 'body'=>$resp->json()]);
            return ['success'=>false, 'message'=>'BTC batch failed', 'tatum'=>$resp->json(), 'plan'=>$plan];
        }

        $body = $resp->json();
        $txId = $body['txId'] ?? null;
        if (!$txId) {
            return ['success'=>false, 'message'=>'BTC: missing txId in response', 'tatum'=>$body, 'plan'=>$plan];
        }

        DB::transaction(function () use ($items, $destination, $txId, $body) {
            TransferLog::create([
                'from_address' => 'BATCH',
                'to_address'   => $destination,
                'amount'       => (float) array_reduce($items->all(), fn($c,$i)=>$c+(float)$i->amount, 0),
                'currency'     => 'BTC',
                'tx'           => json_encode($body),
            ]);

            foreach ($items as $it) {
                $sender = $this->senderOf($it);
                if (!$sender) continue;
                $it->status            = 'completed';
                $it->transfer_address  = $sender;
                $it->transfered_tx     = $txId;
                $it->transfered_amount = (float)$it->amount;
                $it->gas_fee           = null;
                $it->address_to_send   = $destination;
                $it->save();
            }
        });

        return ['success'=>true, 'message'=>'BTC batch submitted', 'txId'=>$txId, 'plan'=>$plan];
    }

    // ----- TRON (TRX / USDT_TRON) -----

    private function flushTron(string $currency, array $groups, $items, string $destination, bool $dryRun): array
    {
        $apiKey = $this->apiKey();
        $base   = $this->baseUrl();

        $isToken      = ($currency === 'USDT_TRON');
        $usdtContract = config('tatum.tron.usdt_contract', 'TR7NHqjeKQxGTCi8q8ZY4pL8otSzgjLj6t');
        $feeLimitSun  = (int)config('tatum.tron.default_fee_limit_sun', 17);
        $minTrx       = (float)config('tatum.tron.gas_topup_min_trx', 18);
        $topupTrx     = (float)config('tatum.tron.gas_topup_amount_trx', 20);

        $plan = ['chain'=>'TRON', 'currency'=>$currency, 'destination'=>$destination, 'groups'=>[], 'dry_run'=>$dryRun];
        foreach ($groups as $sender => $agg) {
            $plan['groups'][] = ['from'=>$sender, 'amount'=>$agg['amount'], 'rows'=>count($agg['ids'])];
        }
        if ($dryRun) return ['success'=>true, 'message'=>'Dry run', 'plan'=>$plan];

        $ok=0; $fail=0; $txs=[];

        foreach ($groups as $sender => $agg) {
            $pk = $this->decryptPkForSender($sender);
            if (!$pk) { Log::warning('TRON: missing key', ['sender'=>$sender]); $fail++; continue; }

            // Ensure gas if token
            if ($isToken) {
                $trxBal = $this->getTrxBalance($sender, $apiKey, $base);
                if ($trxBal < $minTrx) {
                    $topOk = $this->topUpTrx($sender, $topupTrx, $apiKey, $base);
                    if (!$topOk) { $fail++; continue; }
                }
            }

            // Prepare payload
            if ($isToken) {
                $endpoint = '/tron/trc20/transaction';
                $payload = [
                    'fromPrivateKey' => $pk,
                    'to'             => $destination,
                    'amount'         => number_format((float)$agg['amount'], 6, '.', ''),
                    'tokenAddress'   => $usdtContract,
                    'feeLimit'       => $feeLimitSun,
                ];
            } else {
                $endpoint = '/tron/transaction';
                $payload = [
                    'fromPrivateKey' => $pk,
                    'to'             => $destination,
                    'amount'         => number_format((float)$agg['amount'], 6, '.', ''),
                    'feeLimit'       => $feeLimitSun,
                ];
            }

            $resp = Http::withHeaders($this->headers($apiKey))
                ->timeout(90)->post($base.$endpoint, $payload);

            if ($resp->failed()) {
                Log::warning('TRON send failed', ['sender'=>$sender, 'http'=>$resp->status(), 'body'=>$resp->json()]);
                $fail++; continue;
            }

            $body = $resp->json();
            $txId = $body['txId'] ?? $body['txID'] ?? null;
            $txs[] = $txId;

            DB::transaction(function () use ($items, $sender, $destination, $currency, $agg, $body, $txId) {
                TransferLog::create([
                    'from_address' => $sender,
                    'to_address'   => $destination,
                    'amount'       => (float)$agg['amount'],
                    'currency'     => $currency,
                    'tx'           => json_encode($body),
                ]);

                foreach ($items as $it) {
                    if ($this->senderOf($it) !== $sender) continue;
                    $it->status            = 'completed';
                    $it->transfer_address  = $sender;
                    $it->transfered_tx     = $txId;
                    $it->transfered_amount = (float)$it->amount;
                    $it->gas_fee           = null;
                    $it->address_to_send   = $destination;
                    $it->save();
                }
            });

            $ok++;
        }

        return ['success'=>$ok>0, 'message'=>"TRON flush ok={$ok} fail={$fail}", 'txIds'=>$txs, 'plan'=>$plan];
    }

    // ----- Account-based generic (ETH / BSC / Polygon + tokens) -----

    private function flushAccountBased(string $currency, array $groups, $items, string $destination, bool $dryRun): array
    {
        $apiKey = $this->apiKey();
        $base   = $this->baseUrl();

        $map = $this->endpointFor($currency); // endpoint + optional tokenAddress
        if (!$map) {
            return ['success'=>false, 'message'=>"Unsupported currency {$currency}"];
        }

        $plan = ['chain'=>$map['chain'], 'currency'=>$currency, 'destination'=>$destination, 'groups'=>[], 'dry_run'=>$dryRun];
        foreach ($groups as $sender => $agg) {
            $plan['groups'][] = ['from'=>$sender, 'amount'=>$agg['amount'], 'rows'=>count($agg['ids'])];
        }
        if ($dryRun) return ['success'=>true, 'message'=>'Dry run', 'plan'=>$plan];

        $ok=0; $fail=0; $txs=[];

        foreach ($groups as $sender => $agg) {
            $pk = $this->decryptPkForSender($sender);
            if (!$pk) { Log::warning('AB: missing key', ['sender'=>$sender]); $fail++; continue; }

            // Make sure native gas exists (simple threshold per chain)
            $this->ensureNativeGas($map['chain'], $sender, $apiKey, $base);

            $payload = [
                'fromPrivateKey' => $pk,
                'to'             => $destination,
                'amount'         => (string)$agg['amount'],
            ];
            if (!empty($map['tokenAddress'])) $payload['tokenAddress'] = $map['tokenAddress'];

            $resp = Http::withHeaders($this->headers($apiKey))
                ->timeout(90)->post($base.$map['endpoint'], $payload);

            if ($resp->failed()) {
                Log::warning('AB send failed', ['sender'=>$sender, 'http'=>$resp->status(), 'body'=>$resp->json()]);
                $fail++; continue;
            }

            $body = $resp->json();
            $txId = $body['txId'] ?? $body['hash'] ?? null;
            $txs[] = $txId;

            DB::transaction(function () use ($items, $sender, $destination, $currency, $agg, $body, $txId) {
                TransferLog::create([
                    'from_address' => $sender,
                    'to_address'   => $destination,
                    'amount'       => (float)$agg['amount'],
                    'currency'     => $currency,
                    'tx'           => json_encode($body),
                ]);

                foreach ($items as $it) {
                    if ($this->senderOf($it) !== $sender) continue;
                    $it->status            = 'completed';
                    $it->transfer_address  = $sender;
                    $it->transfered_tx     = $txId;
                    $it->transfered_amount = (float)$it->amount;
                    $it->gas_fee           = null;
                    $it->address_to_send   = $destination;
                    $it->save();
                }
            });

            $ok++;
        }

        return ['success'=>$ok>0, 'message'=>"Account flush ok={$ok} fail={$fail}", 'txIds'=>$txs, 'plan'=>$plan];
    }

    // ----- Small helpers -----

    private function groupBySender($items): array
    {
        $groups = [];
        foreach ($items as $it) {
            $sender = $this->senderOf($it);
            $amt    = (float)$it->amount;
            if (!$sender || $amt <= 0) continue;
            if (!isset($groups[$sender])) $groups[$sender] = ['amount'=>0.0, 'ids'=>[]];
            $groups[$sender]['amount'] += $amt;
            $groups[$sender]['ids'][]   = $it->id;
        }
        return $groups;
    }

    private function senderOf($it): ?string
    {
        return $it->transfer_address ?: $it->address ?: $it->deposit_address;
    }

    private function decryptWifForSender(string $sender): ?string
    {
        $dep = DepositAddress::where('address', $sender)->first();
        if (!$dep) return null;
        try {
            $wif = Crypt::decryptString($dep->private_key);
            return (is_string($wif) && strlen($wif) >= 50) ? $wif : null;
        } catch (\Throwable $e) {
            return null;
        }
    }

    private function decryptPkForSender(string $sender): ?string
    {
        $dep = DepositAddress::where('address', $sender)->first();
        if (!$dep) return null;
        try {
            $pk = Crypt::decryptString($dep->private_key);
            return (is_string($pk) && strlen($pk) >= 32) ? $pk : null;
        } catch (\Throwable $e) {
            return null;
        }
    }

    private function endpointFor(string $currency): ?array
    {
        // Map the currencies you use; add token contracts via .env if you like
        return match ($currency) {
            // ETH
            'ETH'        => ['chain'=>'ETH', 'endpoint'=>'/ethereum/transaction'],
            'USDT_ETH'   => ['chain'=>'ETH', 'endpoint'=>'/ethereum/erc20/transaction', 'tokenAddress'=>env('USDT_ETH_CONTRACT')],
            'USDC_ETH'   => ['chain'=>'ETH', 'endpoint'=>'/ethereum/erc20/transaction', 'tokenAddress'=>env('USDC_ETH_CONTRACT')],

            // BSC
            'BNB','BSC'  => ['chain'=>'BSC', 'endpoint'=>'/bsc/transaction'],
            'USDT_BSC'   => ['chain'=>'BSC', 'endpoint'=>'/bsc/bep20/transaction', 'tokenAddress'=>env('USDT_BSC_CONTRACT')],
            'USDC_BSC'   => ['chain'=>'BSC', 'endpoint'=>'/bsc/bep20/transaction', 'tokenAddress'=>env('USDC_BSC_CONTRACT')],

            // Polygon
            'MATIC','POLYGON' => ['chain'=>'POLYGON', 'endpoint'=>'/polygon/transaction'],
            'USDT_POLYGON'    => ['chain'=>'POLYGON', 'endpoint'=>'/polygon/erc20/transaction', 'tokenAddress'=>env('USDT_POLYGON_CONTRACT')],
            'USDC_POLYGON'    => ['chain'=>'POLYGON', 'endpoint'=>'/polygon/erc20/transaction', 'tokenAddress'=>env('USDC_POLYGON_CONTRACT')],

            default => null,
        };
    }

    private function ensureNativeGas(string $chain, string $sender, string $apiKey, string $base): void
    {
        // super simple thresholds; tune as needed
        [$endpoint, $min] = match ($chain) {
            'ETH'     => ['/ethereum/account/balance/', 0.002],   // ~ gas for simple transfer
            'BSC'     => ['/bsc/account/balance/',      0.01],
            'POLYGON' => ['/polygon/account/balance/',  1.0],
            default   => [null, 0],
        };
        if (!$endpoint) return;

        $res = Http::withHeaders(['x-api-key'=>$apiKey])->get($base.$endpoint.$sender);
        $bal = $res->ok() ? (float)($res->json('balance') ?? 0) : 0.0;

        if ($bal >= $min) return;

        // top up from master
        $mwKey = match ($chain) {
            'ETH' => 'ETHEREUM',
            'BSC' => 'BSC',
            'POLYGON' => 'POLYGON',
            default => null,
        };
        if (!$mwKey) return;

        $mw = MasterWallet::where('blockchain', $mwKey)->first();
        if (!$mw) return;

        $pk = Crypt::decryptString($mw->private_key);
        $top = $min * 1.2; // small buffer

        $txEndpoint = match ($chain) {
            'ETH'     => '/ethereum/transaction',
            'BSC'     => '/bsc/transaction',
            'POLYGON' => '/polygon/transaction',
            default   => null,
        };
        if (!$txEndpoint) return;

        $payload = [
            'fromPrivateKey' => $pk,
            'to'             => $sender,
            'amount'         => number_format($top, 6, '.', ''),
        ];

        $resp = Http::withHeaders($this->headers($apiKey))
            ->timeout(90)->post($base.$txEndpoint, $payload);

        if ($resp->failed()) {
            Log::warning('Gas top-up failed', ['chain'=>$chain, 'sender'=>$sender, 'resp'=>$resp->json()]);
        }
    }

    private function getTrxBalance(string $address, string $apiKey, string $base): float
    {
        $res = Http::withHeaders(['x-api-key'=>$apiKey])->get($base."/tron/account/{$address}");
        if ($res->failed()) return 0.0;
        $sun = (int)($res->json('balance') ?? 0);
        return $sun / 1e6;
    }

    private function topUpTrx(string $to, float $amount, string $apiKey, string $base): bool
    {
        $mw = MasterWallet::where('blockchain','TRON')->first();
        if (!$mw) { Log::warning('TRON master wallet missing'); return false; }
        $pk = Crypt::decrypt($mw->private_key);

        $payload = [
            'fromPrivateKey' => $pk,
            'to'             => $to,
            'amount'         => number_format($amount, 6, '.', ''),
        ];

        $resp = Http::withHeaders($this->headers($apiKey))
            ->timeout(90)->post($base.'/tron/transaction', $payload);

        if ($resp->failed()) {
            Log::warning('TRON top-up failed', ['to'=>$to, 'body'=>$resp->json()]);
            return false;
        }
        return true;
    }

    // ----- tiny utils -----

    private function apiKey(): string
    {
        $k = config('tatum.api_key', env('TATUM_API_KEY'));
        if (!$k) throw new \RuntimeException('Tatum API key missing');
        return $k;
    }

    private function baseUrl(): string
    {
        return rtrim(config('tatum.base_url', env('TATUM_BASE_URL', 'https://api.tatum.io/v3')), '/');
    }

    private function headers(string $apiKey): array
    {
        return [
            'x-api-key'    => $apiKey,
            'accept'       => 'application/json',
            'content-type' => 'application/json',
        ];
    }
}
