<?php

namespace App\Repositories;

use App\Models\Transaction;
use App\Models\TransactionIcon;
use App\Models\VirtualAccount;
use Illuminate\Support\Facades\DB;
 use Carbon\Carbon;

class transactionRepository
{
public function all()
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
      'totalRevenue' => number_format((float) $totalRevenue, 0, '.', ','),


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
    // ---- Anchors
    $now = Carbon::now();
    $startWindow = $now->copy()->subMonths(11)->startOfMonth(); // include this month back to 11 months ago
    $endWindow   = $now->copy()->endOfMonth();

    // ---- Base totals
    $totalTransactions = Transaction::where('user_id', $user_id)->count();
    $totalWallets      = VirtualAccount::where('user_id', $user_id)->count();

    // ---- Full list with eager loads (unchanged)
    $transactions = Transaction::where('user_id', $user_id)
        ->with([
            'user',
            'sendtransaction',
            'recievetransaction',
            'buytransaction',
            'swaptransaction' => function ($q) { $q->where('status', 'completed'); },
            'withdraw_transaction.withdraw_request.bankAccount',
        ])
        ->orderBy('created_at', 'desc')
        ->get();

    // ---- Canonical type keys you care about
    // If your DB uses another spelling (e.g. 'withdrawTransaction'), we normalize below.
    $typeKeys = ['send', 'receive', 'buy', 'swap', 'withdraw'];

    // Map any DB aliases -> canonical keys (adjust if your DB differs)
    $normalizeType = function (?string $t) {
        if (!$t) return null;
        $t = strtolower($t);
        // common aliases
        if ($t === 'recieve' || $t === 'received' || $t === 'receive_transaction') return 'receive';
        if ($t === 'send_transaction') return 'send';
        if ($t === 'swap_transaction') return 'swap';
        if ($t === 'withdrawtransaction' || $t === 'withdraw_transaction') return 'withdraw';
        return $t; // already canonical
    };

    // ---- Totals by type (counts + sums)
    $rawTypeAgg = Transaction::where('user_id', $user_id)
        ->select('type', DB::raw('COUNT(*) as cnt'), DB::raw('COALESCE(SUM(amount),0) as sum_amount'))
        ->groupBy('type')
        ->get();

    $totalsByType = [];
    // seed with zeros
    foreach ($typeKeys as $k) {
        $totalsByType[$k] = ['count' => 0, 'sum' => 0.0];
    }
    foreach ($rawTypeAgg as $row) {
        $t = $normalizeType($row->type);
        if ($t && array_key_exists($t, $totalsByType)) {
            $totalsByType[$t]['count'] = (int)$row->cnt;
            $totalsByType[$t]['sum']   = (float)$row->sum_amount;
        }
    }

    // ---- Monthly grouped data (last 12 months, per type)
    // Use YYYY-MM for grouping for correct ordering
    $rawMonthly = Transaction::where('user_id', $user_id)
        ->whereBetween('created_at', [$startWindow, $endWindow])
        ->select(
            DB::raw("DATE_FORMAT(created_at, '%Y-%m') as ym"),
            'type',
            DB::raw('COUNT(*) as total')
        )
        ->groupBy('ym', 'type')
        ->orderBy('ym')
        ->get();

    // Build a continuous month axis (12 months)
    $months = [];
    $cursor = $startWindow->copy();
    while ($cursor->lte($endWindow)) {
        $months[] = [
            'ym'    => $cursor->format('Y-m'),
            'label' => $cursor->format('M, y'), // e.g. "Sep, 25"
        ];
        $cursor->addMonth();
    }

    // Seed structure with zeros
    $byMonth = [];
    foreach ($months as $m) {
        $row = ['month' => $m['label']];
        foreach ($typeKeys as $k) $row[$k] = 0;
        $byMonth[$m['ym']] = $row;
    }

    // Fill counts from DB
    foreach ($rawMonthly as $r) {
        $t = $normalizeType($r->type);
        if (!$t || !isset($byMonth[$r->ym]) || !in_array($t, $typeKeys, true)) continue;
        $byMonth[$r->ym][$t] = (int)$r->total;
    }

    // Final array in chronological order
    $graphicalData = array_values($byMonth);

    return [
        'transactions'       => $transactions,
        'totalTransactions'  => $totalTransactions,
        'totalWallets'       => $totalWallets,

        // NEW: totals per type (count + sum)
        'totals_by_type'     => $totalsByType,

        // Monthly series (last 12 months), each item:
        // { month: "Sep, 25", send: 3, receive: 1, buy: 0, swap: 2, withdraw: 1 }
        'graphicalData'      => $graphicalData,
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
