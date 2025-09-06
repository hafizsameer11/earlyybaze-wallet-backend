<?php

namespace App\Repositories;

use App\Models\Transaction;
use App\Models\TransactionIcon;
use App\Models\VirtualAccount;
use Illuminate\Support\Facades\DB;
 use Carbon\Carbon;

class transactionRepository
{
public function all(\Illuminate\Http\Request $request)
{
   

    // -------- Anchors
    $now            = Carbon::now();
    $todayStart     = $now->copy()->startOfDay();
    $todayEnd       = $now->copy()->endOfDay();

    $thisMonthStart = $now->copy()->startOfMonth();
    $thisMonthEnd   = $now->copy()->endOfMonth();

    $lastMonthStart = $now->copy()->subMonthNoOverflow()->startOfMonth();
    $lastMonthEnd   = $now->copy()->subMonthNoOverflow()->endOfMonth();

    $thisYearStart  = $now->copy()->startOfYear();
    $thisYearEnd    = $now->copy()->endOfYear();

    // -------- Base eager loads (kept identical to your original)
    $with = [
        'user',
        'sendtransaction',
        'recievetransaction',
        'buytransaction',
        'swaptransaction' => function ($query) {
            $query->where('status', 'completed');
        },
        'withdraw_transaction.withdraw_request.bankAccount',
    ];

    // -------- Overall totals (all-time)
    $totalTransactions = \App\Models\Transaction::count();
    $totalWallets      = \App\Models\VirtualAccount::count();
    $totalRevenue      = \App\Models\Transaction::sum('amount');

    // -------- Original full list (all-time, desc)
    $transactions = \App\Models\Transaction::with($with)
        ->orderBy('created_at', 'desc')
        ->get();

    // -------- Helper to compute period stats
    $periodStats = function (\Carbon\Carbon $start, \Carbon\Carbon $end) {
        return [
            'transactions_count' => \App\Models\Transaction::whereBetween('created_at', [$start, $end])->count(),
            'wallets_count'      => \App\Models\VirtualAccount::whereBetween('created_at', [$start, $end])->count(),
            'revenue'            => (float) \App\Models\Transaction::whereBetween('created_at', [$start, $end])->sum('amount'),
        ];
    };

    // -------- Helper to fetch transactions for a period (same eager loads)
    $periodTx = function (\Carbon\Carbon $start, \Carbon\Carbon $end) use ($with) {
        return \App\Models\Transaction::with($with)
            ->whereBetween('created_at', [$start, $end])
            ->orderBy('created_at', 'desc')
            ->get();
    };

    // -------- Build by_period stats
    $byPeriod = [
        'today' => $periodStats($todayStart, $todayEnd),
        'this_month' => $periodStats($thisMonthStart, $thisMonthEnd),
        'last_month' => $periodStats($lastMonthStart, $lastMonthEnd),
        'this_year' => $periodStats($thisYearStart, $thisYearEnd),
    ];

    // -------- Also return transactions grouped by period
    $transactionsByPeriod = [
        'today' => $periodTx($todayStart, $todayEnd),
        'this_month' => $periodTx($thisMonthStart, $thisMonthEnd),
        'last_month' => $periodTx($lastMonthStart, $lastMonthEnd),
        'this_year' => $periodTx($thisYearStart, $thisYearEnd),
    ];

    return [
        // original keys
        'transactions'       => $transactions,
        'totalTransactions'  => $totalTransactions,
        'totalWallets'       => $totalWallets,
        'totalRevenue'       => (float) $totalRevenue,

        // new keys
        'by_period'          => $byPeriod,
        'transactions_by_period' => $transactionsByPeriod,
    ];
}



    public function find($id)
    {
        // Add logic to find data  by ID
    }
    // use Illuminate\Support\Facades\DB;

    public function getTransactionsForUser($user_id)
    {
        // Totals
        $totalTransactions = Transaction::where('user_id', $user_id)->count();
        $totalWallets = VirtualAccount::where('user_id', $user_id)->count();

        // Transaction list
        $transactions = Transaction::where('user_id', $user_id)
        ->whereNotIn('type', ['withdrawTransaction'])
        ->with([
            'user',
            'sendtransaction',
            'recievetransaction',
            'buytransaction',
            'swaptransaction',
                    ])->orderBy('created_at', 'desc')->get();

        // Graphical Data (monthly grouped by type)
        $rawStats = DB::table('transactions')
            ->select(
                DB::raw("DATE_FORMAT(created_at, '%b, %y') as month"),
                'type',
                DB::raw("COUNT(*) as total")
            )
            ->where('user_id', $user_id)
            ->groupBy('month', 'type')
            ->orderByRaw("STR_TO_DATE(CONCAT('01 ', month), '%d %b, %y')")
            ->get();

        // Normalize
        $types = ['send', 'receive', 'buy', 'swap', 'withdrawTransaction'];
        $grouped = [];

        foreach ($rawStats as $stat) {
            $month = $stat->month;
            $type = $stat->type;
            $total = $stat->total;

            if (!isset($grouped[$month])) {
                $grouped[$month] = [
                    'month' => $month,
                    'send' => 0,
                    'receive' => 0,
                    'buy' => 0,
                    'swap' => 0,
                    'withdrawTransaction' => 0,
                ];
            }

            $grouped[$month][$type] = $total;
        }

        $graphicalData = array_values($grouped); // Ensure it's an indexed array

        return [
            'transactions' => $transactions,
            'totalTransactions' => $totalTransactions,
            'totalWallets' => $totalWallets,
            'graphicalData' => $graphicalData,
        ];
    }

    public function getTransactionnsForUserWithCurrency($user_id, $currency)
    {
        $totalTransactions = Transaction::where('user_id', $user_id)->where('currency', $currency)->count();
        $totalWallets = VirtualAccount::where('user_id', $user_id)->count();
        $transactions = Transaction::where('user_id', $user_id)->where('currency', $currency)->with('user')->get();
        $transactions = $transactions->map(function ($transaction) {
            $transactionIcon = TransactionIcon::where('type', $transaction->type)->first();
            //just add icon with transaction object
            $transaction->icon = $transactionIcon->icon ?? '';
            return $transaction;
        });
        return ['transactions' => $transactions, 'totalTransactions' => $totalTransactions, 'totalWallets' => $totalWallets];
    }
    public function create(array $data)
    {
        return Transaction::create($data);
    }

    public function update($id, array $data)
    {
        // Add logic to update data
    }

    public function delete($id)
    {
        // Add logic to delete data
    }
}
