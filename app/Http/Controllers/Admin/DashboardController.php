<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ExchangeRate;
use App\Models\Transaction;
use App\Models\User;
use App\Models\VirtualAccount;
use App\Models\WalletCurrency;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class DashboardController extends Controller
{
    public function dashboardData()
    {
        $data = [
            'statsCardData' => $this->statsCardData(),
            'recentTransactions' => $this->recentTransactions(),
             'charts'             => $this->chartBundle(),   // <--- NEW
        ];
        return response()->json($data, 200);
    }
    private function chartBundle(): array
{
    // periods: last 14 days, last 12 weeks, last 12 months
    $tz = config('app.timezone', 'UTC');

    $daily   = $this->buildDailyFrames(14, $tz);
    $weekly  = $this->buildWeeklyFrames(12, $tz);
    $monthly = $this->buildMonthlyFrames(12, $tz);

    // USERS
    $usersDaily   = $this->countByFrames('users', 'created_at', $daily);
    $usersWeekly  = $this->countByFrames('users', 'created_at', $weekly);
    $usersMonthly = $this->countByFrames('users', 'created_at', $monthly);

    // TRANSACTIONS (count)
    $txDaily   = $this->countByFrames('transactions', 'created_at', $daily);
    $txWeekly  = $this->countByFrames('transactions', 'created_at', $weekly);
    $txMonthly = $this->countByFrames('transactions', 'created_at', $monthly);

    // WALLETS (virtual accounts count)
    $waDaily   = $this->countByFrames('virtual_accounts', 'created_at', $daily);
    $waWeekly  = $this->countByFrames('virtual_accounts', 'created_at', $weekly);
    $waMonthly = $this->countByFrames('virtual_accounts', 'created_at', $monthly);

    // REVENUE (NGN) – sums per frame with currency conversion
    $revDaily   = $this->revenueNgnByFrames($daily);
    $revWeekly  = $this->revenueNgnByFrames($weekly);
    $revMonthly = $this->revenueNgnByFrames($monthly);

    return [
        'daily' => [
            'labels' => array_column($daily, 'label'),
            'datasets' => [
                'Users'         => $usersDaily,
                'Transactions'  => $txDaily,
                'Revenue'       => $revDaily,   // NGN
                'Wallet'        => $waDaily,
            ],
        ],
        'weekly' => [
            'labels' => array_column($weekly, 'label'),
            'datasets' => [
                'Users'         => $usersWeekly,
                'Transactions'  => $txWeekly,
                'Revenue'       => $revWeekly,
                'Wallet'        => $waWeekly,
            ],
        ],
        'monthly' => [
            'labels' => array_column($monthly, 'label'),
            'datasets' => [
                'Users'         => $usersMonthly,
                'Transactions'  => $txMonthly,
                'Revenue'       => $revMonthly,
                'Wallet'        => $waMonthly,
            ],
        ],
        // optional color hints (frontend can override)
        'colors' => [
            'Users'        => '#126EB9',
            'Transactions' => '#78CA19',
            'Revenue'      => '#B95A12',
            'Wallet'       => '#CA1919',
        ],
    ];
}

/** ---------- frames ---------- */
private function buildDailyFrames(int $days, string $tz): array
{
    $now = Carbon::now($tz)->startOfDay();
    $frames = [];
    for ($i = $days - 1; $i >= 0; $i--) {
        $d = (clone $now)->subDays($i);
        $frames[] = [
            'label' => $d->format('M d'),
            'start' => (clone $d)->startOfDay()->utc(),
            'end'   => (clone $d)->endOfDay()->utc(),
        ];
    }
    return $frames;
}

private function buildWeeklyFrames(int $weeks, string $tz): array
{
    $now = Carbon::now($tz)->startOfWeek(); // ISO week start (Mon)
    $frames = [];
    for ($i = $weeks - 1; $i >= 0; $i--) {
        $wStart = (clone $now)->subWeeks($i);
        $wEnd   = (clone $wStart)->endOfWeek();
        $frames[] = [
            'label' => $wStart->format('M d') . '–' . $wEnd->format('M d'),
            'start' => (clone $wStart)->startOfDay()->utc(),
            'end'   => (clone $wEnd)->endOfDay()->utc(),
        ];
    }
    return $frames;
}

private function buildMonthlyFrames(int $months, string $tz): array
{
    $now = Carbon::now($tz)->startOfMonth();
    $frames = [];
    for ($i = $months - 1; $i >= 0; $i--) {
        $mStart = (clone $now)->subMonths($i);
        $mEnd   = (clone $mStart)->endOfMonth();
        $frames[] = [
            'label' => $mStart->format('M Y'),
            'start' => (clone $mStart)->startOfDay()->utc(),
            'end'   => (clone $mEnd)->endOfDay()->utc(),
        ];
    }
    return $frames;
}

/** ---------- generic counts per frame ---------- */
private function countByFrames(string $table, string $col, array $frames): array
{
    $out = array_fill(0, count($frames), 0);

    // Get all rows for the whole window once
    $globalStart = $frames[0]['start'];
    $globalEnd   = $frames[count($frames) - 1]['end'];

    $rows = DB::table($table)
        ->select($col)
        ->whereBetween($col, [$globalStart, $globalEnd])
        ->get();

    foreach ($rows as $r) {
        $ts = Carbon::parse($r->{$col});
        foreach ($frames as $idx => $f) {
            if ($ts->betweenIncluded($f['start'], $f['end'])) {
                $out[$idx]++;
                break;
            }
        }
    }
    return $out;
}

/** ---------- revenue (NGN) per frame with conversion ---------- */
private function revenueNgnByFrames(array $frames): array
{
    // Prepare rates (latest)
    $rawRates = \App\Models\ExchangeRate::select('currency', 'rate_usd')->get();
    $rates = [];
    foreach ($rawRates as $r) {
        if ($r->currency !== null && $r->rate_usd !== null) {
            $rates[strtoupper(trim($r->currency))] = (float)$r->rate_usd;
        }
    }
    $ngnUsd = $rates['NGN'] ?? null;

    $out = array_fill(0, count($frames), 0.0);
    $globalStart = $frames[0]['start'];
    $globalEnd   = $frames[count($frames) - 1]['end'];

    // Pull minimal columns for the whole window
    $txs = \App\Models\Transaction::query()
        ->select('amount', 'currency', 'created_at')
        ->whereBetween('created_at', [$globalStart, $globalEnd])
        ->get();

    foreach ($txs as $tx) {
        $amount = (float)$tx->amount;
        $code   = strtoupper(trim((string)$tx->currency));
        if ($amount === 0.0 || $code === '') continue;

        // convert to NGN using latest rates
        $ngn = 0.0;
        if ($code === 'NGN') {
            $ngn = $amount;
        } else {
            $curUsd = $rates[$code] ?? null;
            if ($curUsd !== null && $ngnUsd !== null) {
                $usd = $amount * $curUsd;
                $ngn = $usd * $ngnUsd;
            } else {
                // skip if missing rates
                continue;
            }
        }

        $ts = Carbon::parse($tx->created_at);
        foreach ($frames as $idx => $f) {
            if ($ts->betweenIncluded($f['start'], $f['end'])) {
                $out[$idx] += $ngn;
                break;
            }
        }
    }
    // optional: round
    return array_map(fn($v) => (float)round($v, 2), $out);
}
    // public function statsCardData()
    // {
    //     $totatUsers = User::count();
    //     $totalTransactions = Transaction::count();
    //     $totalWallets = VirtualAccount::count();
    //     $totalRevenue = Transaction::sum('amount');
    //     $data = [
    //         'totalUsers' => $totatUsers,
    //         'totalTransactions' => $totalTransactions,
    //         'totalWallets' => $totalWallets,
    //         'totalRevenue' => $totalRevenue
    //     ];
    //     return $data;
    // }
    
public function statsCardData()
{
    $totalUsers        = User::count();
    $totalTransactions = Transaction::count();
    $totalWallets      = VirtualAccount::count();

    // 1) Build a case-insensitive map: CURRENCY (upper) => rate_usd (float)
    $rawRates = ExchangeRate::select('currency', 'rate_usd')->get();
    $rates = [];
    foreach ($rawRates as $r) {
        if ($r->currency === null || $r->rate_usd === null) continue;
        $rates[strtoupper(trim($r->currency))] = (float)$r->rate_usd;
    }

    // 2) Require NGN rate to convert USD -> NGN for non-NGN txns
    $ngnRateUsd = $rates['NGN'] ?? null;

    $totalRevenueNgn = 0.0;

    Transaction::select('id', 'amount', 'currency')
        ->orderBy('id')
        ->chunkById(1000, function ($chunk) use (&$totalRevenueNgn, $rates, $ngnRateUsd) {
            foreach ($chunk as $tx) {
                $amount   = (float) $tx->amount;
                $code     = strtoupper(trim((string)$tx->currency));

                if ($amount === 0.0 || $code === '') {
                    // skip bad rows silently; you can log if you want
                    continue;
                }

                if ($code === 'NGN') {
                    // Already NGN
                    $totalRevenueNgn += $amount;
                } else {
                    // Need both the currency rate and NGN rate
                    $curRateUsd = $rates[$code] ?? null;
                    if ($curRateUsd !== null && $ngnRateUsd !== null) {
                        $usd = $amount * $curRateUsd;      // to USD
                        $ngn = $usd * $ngnRateUsd;         // USD -> NGN
                        $totalRevenueNgn += $ngn;
                    } else {
                        // If any rate missing, skip or handle as needed
                        Log::warning("Missing rate for {$code} or NGN");
                    }
                }
            }
        });

    return [
        'totalUsers'        => $totalUsers,
        'totalTransactions' => $totalTransactions,
        'totalWallets'      => $totalWallets,
        'totalRevenue'      => $totalRevenueNgn, // in NGN
        'totalRevenueUSD'   => $totalRevenueNgn / ($ngnRateUsd ?? 1520), // in USD (approx)
    ];
}

    public function recentTransactions()
    {
        $transactions = Transaction::orderBy('created_at', 'desc')->take(5)->get();
        $transactions = $transactions->map(function ($transaction) {
            // $user=User::find($transaction->user_id);
            $currency = WalletCurrency::where('currency', $transaction->currency)->first();
            $symbol = '';
            if ($currency) {
                $symbol = $currency->symbol;
            }
            return [
                'id' => $transaction->id,
                // 'user'=>$user->name,
                'currency' => $transaction->currency,
                'symbol' => $symbol,
                'amount' => $transaction->amount,
                'type' => $transaction->type,
                'status' => $transaction->status,
                'created_at' => $transaction->created_at
            ];
        });
        return $transactions;
    }
}
