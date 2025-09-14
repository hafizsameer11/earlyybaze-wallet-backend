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
             if ($currency === 'LTC') {
        return $this->flushLtcBatch($groups, $items, $destination, $dryRun);
    }
            if (in_array($currency, ['TRON','USDT_TRON'], true)) {
                return $this->flushTron($currency, $groups, $items, $destination, $dryRun);
            }

            return $this->flushAccountBased($currency, $groups, $items, $destination, $dryRun);

        } catch (\Throwable $e) {
            Log::error('Flush fatal', ['currency'=>$currency, 'err'=>$e->getMessage()]);
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    // ----- BTC (UTXO) -----
    /**
 * Litecoin batch sweep (UTXO), same plan shape as BTC so your UI works.
 * Tatum endpoint: /v3/litecoin/transaction
 */
private function flushLtcBatch(array $groups, $items, string $destination, bool $dryRun): array
{
    $apiKey = $this->apiKey();
    $base   = $this->baseUrl();

    // Build inputs & sum total
    $fromAddress = [];
    $total = 0.0;

    foreach ($groups as $sender => $agg) {
        $wif = $this->decryptWifForSender($sender);
        if (!$wif) { Log::warning('LTC: missing/bad key for sender', ['sender'=>$sender]); continue; }
        $fromAddress[] = ['address' => $sender, 'privateKey' => $wif];
        $total += (float)$agg['amount'];
    }

    if (empty($fromAddress)) {
        return ['success'=>false, 'message'=>'LTC: No decryptable inputs'];
    }

    /**
     * Fee heuristic:
     *  - Litecoin fees are cheaper than BTC; still keep a safety floor.
     *  - Use a small per-input bump. Tune these to your network conditions.
     */
    $minFee = 0.00010; // floor
    $perIn  = 0.00001; // per-input bump
    $fee    = round(max($minFee, count($fromAddress) * $perIn), 8);

    $send = round($total - $fee, 8);

    // dust threshold (conservative)
    $dust = 0.00001;
    if ($send <= $dust) {
        return ['success'=>false, 'message'=>'LTC: total after fee below dust'];
    }

    $payload = [
        'fromAddress'   => $fromAddress,
        'to'            => [[ 'address' => $destination, 'value' => $send ]],
        'fee'           => number_format($fee, 8, '.', ''),
        'changeAddress' => $destination,
    ];

    $plan = [
        'chain'       => 'LTC',
        'uniqSenders' => count($fromAddress),
        'rows'        => $items->count(),
        'totalIn'     => round($total, 8),
        'fee'         => round($fee,   8),
        'toSend'      => $send,
        'destination' => $destination,
        'dry_run'     => $dryRun,
    ];

    if ($dryRun) {
        return ['success'=>true, 'message'=>'Dry run', 'plan'=>$plan];
    }

    $resp = Http::withHeaders($this->headers($apiKey))
        ->timeout(120)->post($base.'/litecoin/transaction', $payload);

    if ($resp->failed()) {
        Log::error('LTC batch failed', ['http'=>$resp->status(), 'body'=>$resp->json()]);
        return ['success'=>false, 'message'=>'LTC batch failed', 'tatum'=>$resp->json(), 'plan'=>$plan];
    }

    $body = $resp->json();
    $txId = $body['txId'] ?? null;
    if (!$txId) {
        return ['success'=>false, 'message'=>'LTC: missing txId in response', 'tatum'=>$body, 'plan'=>$plan];
    }

    DB::transaction(function () use ($items, $destination, $txId, $body) {
        TransferLog::create([
            'from_address' => 'BATCH',
            'to_address'   => $destination,
            'amount'       => (float) array_reduce($items->all(), fn($c,$i)=>$c+(float)$i->amount, 0),
            'currency'     => 'LTC',
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

    return ['success'=>true, 'message'=>'LTC batch submitted', 'txId'=>$txId, 'plan'=>$plan];
}

   /**
 * BTC batch sweep (UTXO) with weight-aware fee calculation.
 *
 * @param array  $groups       sender => ['amount' => float, 'ids' => [rowIds...]]
 * @param \Illuminate\Support\Collection $items  the rows you’re flushing
 * @param string $destination  consolidation/target address (bech32)
 * @param bool   $dryRun       if true, returns plan only
 * @return array
 */
private function flushBtcBatch(array $groups, $items, string $destination, bool $dryRun): array
{
    $apiKey = $this->apiKey();
    $base   = $this->baseUrl();

    // === 1) Build inputs & total ===
    $fromAddress = [];
    $total = 0.0;

    foreach ($groups as $sender => $agg) {
        $wif = $this->decryptWifForSender($sender);
        if (!$wif) {
            Log::warning('BTC: missing/bad key for sender', ['sender' => $sender]);
            continue;
        }
        $fromAddress[] = ['address' => $sender, 'privateKey' => $wif];
        $total += (float) $agg['amount'];
        Log::info('BTC: adding input', ['sender' => $sender, 'amount' => $agg['amount']]);
    }

    if (empty($fromAddress)) {
        return ['success' => false, 'message' => 'BTC: No decryptable inputs'];
    }

    // === 2) Fee model (weight-aware) ===
    // Rough P2WPKH size (vbytes): 10 + 68*inputs + 31*outputs
    $inputs  = count($fromAddress);

    // MODE: sweep to ONE output by default (no change output)
    // If you want a normal payment w/ change, set $sweep = false.
    $sweep = true;

    $outputs = $sweep ? 1 : 2;

    $baseVb  = 10;
    $inVb    = 68; // ~68 vB per P2WPKH input
    $outVb   = 31; // ~31 vB per P2WPKH output

    $txVbytes = $baseVb + ($inVb * $inputs) + ($outVb * $outputs);

    // Target fee rate (sat/vB). Make configurable.
    // e.g., put in config/tatum.php: 'btc' => ['fee_satvb' => 12]
    $feeRateSatVb = (int) (config('tatum.btc.fee_satvb', 12));

    $feeSats = (int) ceil($txVbytes * max(1, $feeRateSatVb));
    $feeBtc  = round($feeSats / 1e8, 8);

    // Dust threshold for BTC (546 sats)
    $dustBtc = 0.00000546;

    // === 3) Compute outputs ===
    if ($sweep) {
        // Sweep: send = total - fee, no change output
        $send = round($total - $feeBtc, 8);

        if ($send <= $dustBtc) {
            return [
                'success' => false,
                'message' => 'BTC: total after fee below dust (sweep). Add more inputs or lower fee rate.',
                'plan'    => [
                    'chain'       => 'BTC',
                    'inputs'      => $inputs,
                    'outputs'     => 1,
                    'vbytes'      => $txVbytes,
                    'fee_satvb'   => $feeRateSatVb,
                    'fee_btc'     => $feeBtc,
                    'total_in'    => round($total, 8),
                    'destination' => $destination,
                    'mode'        => 'sweep',
                ],
            ];
        }

        $payload = [
            'fromAddress'   => $fromAddress,
            'to'            => [[ 'address' => $destination, 'value' => $send ]],
            'fee'           => number_format($feeBtc, 8, '.', ''),
            // Using destination as changeAddress is fine because change is zero in sweep.
            'changeAddress' => $destination,
        ];

        $plan = [
            'chain'       => 'BTC',
            'mode'        => 'sweep',
            'uniqSenders' => $inputs,
            'rows'        => $items->count(),
            'totalIn'     => round($total, 8),
            'fee'         => round($feeBtc, 8),
            'toSend'      => $send,
            'vbytes'      => $txVbytes,
            'fee_satvb'   => $feeRateSatVb,
            'destination' => $destination,
            'dry_run'     => $dryRun,
        ];

    } else {
        // Normal payment: choose a target send, leave change = total - fee - send
        // If you want to send a specific amount (e.g., $targetSend), set it here.
        // For demonstration, we try to send as much as possible while keeping change > dust.
        $targetSend = round($total - $feeBtc - $dustBtc, 8);
        if ($targetSend <= $dustBtc) {
            return [
                'success' => false,
                'message' => 'BTC: cannot build non-sweep tx without dust change. Add inputs or increase total.',
            ];
        }

        // Compute change
        $change = round($total - $feeBtc - $targetSend, 8);

        // If change ended up dust, fold it into send (convert effectively to sweep)
        if ($change <= $dustBtc) {
            $sweep = true;
            $outputs = 1;
            $txVbytes = $baseVb + ($inVb * $inputs) + ($outVb * 1);
            $feeSats  = (int) ceil($txVbytes * max(1, $feeRateSatVb));
            $feeBtc   = round($feeSats / 1e8, 8);
            $targetSend = round($total - $feeBtc, 8);
            $change = 0.0;
        }

        $payload = [
            'fromAddress'   => $fromAddress,
            'to'            => [[ 'address' => $destination, 'value' => $targetSend ]],
            'fee'           => number_format($feeBtc, 8, '.', ''),
            // In normal mode, change goes back to an address you control.
            // Prefer a dedicated change address you own (not the same as destination if destination is external).
            // If destination is your consolidation wallet, using it as change is acceptable.
            'changeAddress' => $destination,
        ];

        $plan = [
            'chain'       => 'BTC',
            'mode'        => $sweep ? 'sweep-folded' : 'normal',
            'uniqSenders' => $inputs,
            'rows'        => $items->count(),
            'totalIn'     => round($total, 8),
            'fee'         => round($feeBtc, 8),
            'toSend'      => $targetSend,
            'change'      => $change,
            'vbytes'      => $txVbytes,
            'fee_satvb'   => $feeRateSatVb,
            'destination' => $destination,
            'dry_run'     => $dryRun,
        ];
    }

    if ($dryRun) {
        return ['success' => true, 'message' => 'Dry run', 'plan' => $plan];
    }

    // === 4) Call Tatum ===
    $resp = Http::withHeaders($this->headers($apiKey))
        ->timeout(120)
        ->post($base . '/bitcoin/transaction', $payload);

    if ($resp->failed()) {
        Log::error('BTC batch failed', ['http' => $resp->status(), 'body' => $resp->json(), 'plan' => $plan, 'payload' => $payload]);
        return ['success' => false, 'message' => 'BTC batch failed', 'tatum' => $resp->json(), 'plan' => $plan];
    }

    $body = $resp->json();
    $txId = $body['txId'] ?? null;
    if (!$txId) {
        return ['success' => false, 'message' => 'BTC: missing txId in response', 'tatum' => $body, 'plan' => $plan];
    }

    // === 5) Persist logs & mark items ===
    DB::transaction(function () use ($items, $destination, $txId, $body) {
        TransferLog::create([
            'from_address' => 'BATCH',
            'to_address'   => $destination,
            'amount'       => (float) array_reduce($items->all(), fn($c, $i) => $c + (float) $i->amount, 0),
            'currency'     => 'BTC',
            'tx'           => json_encode($body),
        ]);

        foreach ($items as $it) {
            $sender = $this->senderOf($it);
            if (!$sender) continue;
            $it->status            = 'completed';
            $it->transfer_address  = $sender;
            $it->transfered_tx     = $txId;
            $it->transfered_amount = (float) $it->amount;
            $it->gas_fee           = null;
            $it->address_to_send   = $destination;
            $it->save();
        }
    });

    return ['success' => true, 'message' => 'BTC batch submitted', 'txId' => $txId, 'plan' => $plan];
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

    $map = $this->endpointFor($currency);
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

        // ensure gas on sender for native/tokens
        $this->ensureNativeGas($map['chain'], $sender, $apiKey, $base);

        $payload = [
            'fromPrivateKey' => $pk,
            'to'             => $destination,
            'amount'         => (string)$agg['amount'],
        ];

        if (!empty($map['needsCurrency']) && !empty($map['currencyValue'])) {
            $payload['currency'] = $map['currencyValue']; // ETH / BSC / MATIC
        }
        if (!empty($map['contractAddress'])) {
            $payload['contractAddress'] = $map['contractAddress']; // exact name for Tatum
        }

        $resp = Http::withHeaders($this->headers($apiKey))
            ->timeout(90)->post($base.$map['endpoint'], $payload);

        if ($resp->failed()) {
            Log::warning('AB send failed', ['sender'=>$sender, 'http'=>$resp->status(), 'body'=>$resp->json(), 'payload'=>$payload]);
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
        $amt    = (float) $it->amount;

        if (!$sender || $amt <= 0) {
            Log::info("skipping row and bad sender/amount",[
                'row_id' => $it->id,
                'sender_candidate' => [$it->transfer_address, $it->deposit_address, $it->address, $it->to_address, $it->from_address],
                'amount' => $it->amount,
            ])
;            // Log::channel('withdrawals')->warning('Skipping row: bad sender/amount', );
            continue;
        }
        if (!isset($groups[$sender])) $groups[$sender] = ['amount'=>0.0, 'ids'=>[]];
        $groups[$sender]['amount'] += $amt;
        $groups[$sender]['ids'][]   = $it->id;
    }
    return $groups;
}


private function senderOf($it): ?string
{
    // Try the most common columns that may contain the address we control
    $candidates = array_filter([
        $it->transfer_address ?? null,
        $it->deposit_address ?? null,
        $it->address ?? null,
        $it->to_address ?? null,     // funds received "to" our deposit address (very common)
        $it->from_address ?? null,   // fallback if your schema uses from_address as our own
    ], fn($v) => is_string($v) && strlen($v) > 20);

    foreach ($candidates as $maybe) {
        if (DepositAddress::where('address', $maybe)->exists()) {
            return $maybe; // only accept addresses we actually control
        }
    }
    return null;
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
    $c = strtoupper($currency);

    return match ($c) {
        // ===== ETH native =====
        'ETH' => [
            'chain'         => 'ETH',
            'endpoint'      => '/ethereum/transaction',
            'needsCurrency' => true,
            'currencyValue' => 'ETH',
        ],

        // ===== ETH tokens (ERC-20) =====
        'USDT', 'USDT_ETH' => [
            'chain'           => 'ETH',
            'endpoint'        => '/ethereum/transaction',
            'needsCurrency'   => true,
            'contractAddress' => '0xdAC17F958D2ee523a2206206994597C13D831ec7', // USDT ERC-20
              'currencyValue' => 'USDT',
        ],
        'USDC', 'USDC_ETH' => [
            'chain'           => 'ETH',
            'endpoint'        => '/ethereum/transaction',
            'needsCurrency'   => true,

'currencyValue' => 'USDC',            'contractAddress' => '0xA0b86991c6218b36c1d19D4a2e9Eb0cE3606eB48', // USDC ERC-20
        ],

        // ===== BSC native (BNB) =====
        'BNB', 'BSC' => [
            'chain'         => 'BSC',
            'endpoint'      => '/bsc/transaction',
            'needsCurrency' => true,
            'currencyValue' => 'BSC',
        ],

        // ===== BSC tokens (BEP-20) =====
        'USDT_BSC' => [
            'chain'           => 'BSC',
            'endpoint'        => '/bsc/transaction',
            'needsCurrency'   => true,
            'contractAddress' => '0x55d398326f99059fF775485246999027B3197955', // USDT BEP-20
            'currencyValue'   => 'USDT_BSC',
        ],
        'USDC_BSC' => [
            'chain'           => 'BSC',
            'endpoint'        => '/bsc/transaction',
            'needsCurrency'   => true,
            'contractAddress' => '0x64544969ed7EBf5f083679233325356EbE738930', // USDC BEP-20
            'currencyValue'   => 'USDC_BSC',
        ],

        // ===== Polygon native (MATIC) =====
        'MATIC', 'POLYGON' => [
            'chain'         => 'POLYGON',
            'endpoint'      => '/polygon/transaction',
            'needsCurrency' => true,
            'currencyValue' => 'MATIC',
        ],

        // ===== Polygon tokens (ERC-20 on Polygon) =====
        'USDT_POLYGON' => [
            'chain'           => 'POLYGON',
            'endpoint'        => '/polygon/erc20/transaction',
            'needsCurrency'   => false,
            'contractAddress' => '0xc2132D05D31c914a87C6611C10748AaCB9fC6fC', // USDT on Polygon
        ],
        'USDC_POLYGON' => [
            'chain'           => 'POLYGON',
            'endpoint'        => '/polygon/erc20/transaction',
            'needsCurrency'   => false,
            'contractAddress' => '0x2791Bca1f2de4661ED88A30C99A7a9449Aa84174', // USDC.e on Polygon
        ],

        default => null,
    };
}


 private function ensureNativeGas(string $chain, string $sender, string $apiKey, string $base): void
{
    [$endpoint, $min] = match ($chain) {
        'ETH'     => ['/ethereum/account/balance/', 0.002],
        'BSC'     => ['/bsc/account/balance/',      0.010],
        'POLYGON' => ['/polygon/account/balance/',  1.000],
        default   => [null, 0],
    };
    if (!$endpoint) return;

    $res = Http::withHeaders(['x-api-key'=>$apiKey])->get($base.$endpoint.$sender);
    $bal = $res->ok() ? (float)($res->json('balance') ?? 0) : 0.0;
    if ($bal >= $min) return;

    $mwKey = match ($chain) {
        'ETH' => 'ETHEREUM',
        'BSC' => 'BSC',
        'POLYGON' => 'POLYGON',
        default => null,
    };
    if (!$mwKey) return;

    $mw = MasterWallet::where('blockchain', $mwKey)->first();
    if (!$mw) return;

    $pk = Crypt::decrypt($mw->private_key);
    $top = $min * 1.2;

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
        'amount'         => (string)$top,
        'currency'       => match ($chain) {
            'ETH'     => 'ETH',
            'BSC'     => 'BSC',
            'POLYGON' => 'MATIC',
            default   => null,
        },
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
