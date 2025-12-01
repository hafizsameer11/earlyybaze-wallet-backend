<?php

namespace App\Repositories;

use App\Models\Transaction;
use App\Models\TransactionIcon;
use App\Models\VirtualAccount;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class transactionRepository
{
public function all(array $params)
{
    $search = $params['search'] ?? null;
    $period = $params['period'] ?? 'all'; // 'all', 'today', 'this_month', 'last_month', 'this_year'

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

    // -------- Base eager loads
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

    // -------- Query Builder
    $query = Transaction::with($with)->orderBy('created_at', 'desc');

    // -------- Apply period filter based on frontend request
    switch ($period) {
        case 'today':
            $query->whereBetween('created_at', [$todayStart, $todayEnd]);
            break;
        case 'this_month':
            $query->whereBetween('created_at', [$thisMonthStart, $thisMonthEnd]);
            break;
        case 'last_month':
            $query->whereBetween('created_at', [$lastMonthStart, $lastMonthEnd]);
            break;
        case 'this_year':
            $query->whereBetween('created_at', [$thisYearStart, $thisYearEnd]);
            break;
        case 'all':
        default:
            // No date filter - load all transactions
            break;
    }

    // -------- Apply search filter
    if ($search) {
        $query->whereHas('user', function ($q) use ($search) {
            $q->where('username', 'LIKE', "%{$search}%")
              ->orWhere('email', 'LIKE', "%{$search}%");
        });
    }

    // -------- GET ALL TRANSACTIONS FOR SELECTED PERIOD (no pagination)
    $transactions = $query->get();

    // -------- Overall totals (for stats - always calculated)
    $totalTransactions = Transaction::count();
    $totalWallets      = VirtualAccount::count();
    $totalRevenue      = Transaction::sum('amount');

    // -------- Period statistics (for dashboard stats)
    $periodStats = function ($start, $end) {
        return [
            'transactions_count' => Transaction::whereBetween('created_at', [$start, $end])->count(),
            'wallets_count'      => VirtualAccount::whereBetween('created_at', [$start, $end])->count(),
            'revenue'            => (float) Transaction::whereBetween('created_at', [$start, $end])->sum('amount'),
        ];
    };

    // -------- Period statistics for all periods (for frontend filters)
    $byPeriod = [
        'today'      => $periodStats($todayStart, $todayEnd),
        'this_month' => $periodStats($thisMonthStart, $thisMonthEnd),
        'last_month' => $periodStats($lastMonthStart, $lastMonthEnd),
        'this_year'  => $periodStats($thisYearStart, $thisYearEnd),
        'all'        => [
            'transactions_count' => $totalTransactions,
            'wallets_count'      => $totalWallets,
            'revenue'            => (float) $totalRevenue,
        ],
    ];

    return [
        'transactions'      => $transactions,
        'totalTransactions' => $totalTransactions,
        'totalWallets'      => $totalWallets,
        'totalRevenue'      => number_format($totalRevenue, 0, '.', ','),
        'by_period'         => $byPeriod,
        'current_period'    => $period, // Return selected period for frontend reference
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
        $startWindow = $now->copy()->subMonths(11)->startOfMonth(); // 12-month window for other stats
        $endWindow   = $now->copy()->endOfMonth();

        // ---- Base totals
        $totalTransactions = Transaction::where('user_id', $user_id)->count();
        $totalWallets      = VirtualAccount::where('user_id', $user_id)->count();

        // ---- Full list with eager loads
        $transactions = Transaction::where('user_id', $user_id)
            ->with([
                'user',
                'sendtransaction',
                'recievetransaction',
                'buytransaction',
                'swaptransaction' => function ($q) {
                    $q->where('status', 'completed');
                },
                'withdraw_transaction.withdraw_request.bankAccount',
            ])
            ->orderBy('created_at', 'desc')
            ->get();

        // ---- Canonical type keys
        $typeKeys = ['send', 'receive', 'buy', 'swap', 'withdraw'];

        // ---- Normalization function
        $normalizeType = function (?string $t) {
            if (!$t) return null;
            $t = strtolower($t);
            if ($t === 'recieve' || $t === 'received' || $t === 'receive_transaction') return 'receive';
            if ($t === 'send_transaction') return 'send';
            if ($t === 'swap_transaction') return 'swap';
            if ($t === 'withdrawtransaction' || $t === 'withdraw_transaction') return 'withdraw';
            return $t;
        };

        // ---- Totals by type (counts + sums)
        $rawTypeAgg = Transaction::where('user_id', $user_id)
            ->select('type', DB::raw('COUNT(*) as cnt'), DB::raw('COALESCE(SUM(amount),0) as sum_amount'))
            ->groupBy('type')
            ->get();

        $totalsByType = [];
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

        // ============================================================
        // ✅ DAILY GRAPHICAL DATA (for current month)
        // ============================================================

        $startOfMonth = $now->copy()->startOfMonth();
        $endOfToday   = $now->copy()->endOfDay();

        // Group by day
        $rawDaily = Transaction::where('user_id', $user_id)
            ->whereBetween('created_at', [$startOfMonth, $endOfToday])
            ->select(
                DB::raw("DATE_FORMAT(created_at, '%Y-%m-%d') as day"),
                'type',
                DB::raw('COUNT(*) as total')
            )
            ->groupBy('day', 'type')
            ->orderBy('day')
            ->get();

        // Build full day list (1st → today)
        $days = [];
        $cursor = $startOfMonth->copy();
        while ($cursor->lte($endOfToday)) {
            $days[] = [
                'day_key' => $cursor->format('Y-m-d'),
                'label'   => $cursor->format('d M'), // e.g. "01 Oct"
            ];
            $cursor->addDay();
        }

        // Seed with zeros
        $byDay = [];
        foreach ($days as $d) {
            $row = ['day' => $d['label']];
            foreach ($typeKeys as $k) $row[$k] = 0;
            $byDay[$d['day_key']] = $row;
        }

        // Fill data
        foreach ($rawDaily as $r) {
            $t = $normalizeType($r->type);
            if (!$t || !isset($byDay[$r->day]) || !in_array($t, $typeKeys, true)) continue;
            $byDay[$r->day][$t] = (int)$r->total;
        }

        // Final daily array (chronological)
        $dailyGraphicalData = array_values($byDay);

        // ============================================================

        return [
            'transactions'       => $transactions,
            'totalTransactions'  => $totalTransactions,
            'totalWallets'       => $totalWallets,

            // Totals by transaction type
            'totals_by_type'     => $totalsByType,

            // ✅ Daily graphical data for current month
            'graphicalData'      => $dailyGraphicalData,
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
