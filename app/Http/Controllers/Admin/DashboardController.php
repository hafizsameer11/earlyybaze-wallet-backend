<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ExchangeRate;
use App\Models\Transaction;
use App\Models\User;
use App\Models\VirtualAccount;
use App\Models\WalletCurrency;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class DashboardController extends Controller
{
    public function dashboardData()
    {
        $data = [
            'statsCardData' => $this->statsCardData(),
            'recentTransactions' => $this->recentTransactions()
        ];
        return response()->json($data, 200);
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
        'totalRevenueUSD'   => $totalRevenueNgn / ($ngnRateUsd ?? 1), // in USD (approx)
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
