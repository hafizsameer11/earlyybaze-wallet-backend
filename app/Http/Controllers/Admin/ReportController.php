<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ReferalEarning;
use App\Models\Transaction;
use App\Models\User;
use App\Models\WithdrawRequest;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ReportController extends Controller
{
    public function index()
    {
        $timeFrames = [
            'today' => [Carbon::today(), Carbon::now()],
            'week' => [Carbon::now()->startOfWeek(), Carbon::now()],
            'month' => [Carbon::now()->startOfMonth(), Carbon::now()],
        ];

        $data = [];

        foreach ($timeFrames as $key => [$from, $to]) {
            $totalUsers = User::whereBetween('created_at', [$from, $to])->count();
            $activeUsers = User::where('is_active', operator: true)->whereBetween('created_at', [$from, $to])->count();
            $newUsers = $totalUsers;
            $payingUsers = User::whereHas('transactions', fn($q) => $q->whereBetween('created_at', [$from, $to]))->count();
            $deletedUsers = User::onlyTrashed()->whereBetween('deleted_at', [$from, $to])->count();

            // placeholder static data
            $engagedSessions = 0;
            $bouncedUsers = 0;
            $engagementRate = 0;

            $totalRevenue = Transaction::whereIn('type', ['swap', 'buy'])
                ->where('status', 'completed')
                ->whereBetween('created_at', [$from, $to])
                ->sum('amount_usd');

            $totalSwapTransactions = Transaction::where('type', 'swap')->whereBetween('created_at', [$from, $to])->count();
            $totalBuyTransactions = Transaction::where('type', 'buy')->whereBetween('created_at', [$from, $to])->count();
            $totalReceiveTransactions = Transaction::where('type', 'receive')->whereBetween('created_at', [$from, $to])->count();
            $totalSendTransactions = Transaction::where('type', 'send')->whereBetween('created_at', [$from, $to])->sum('amount_usd');
            $totalWithdrawals = WithdrawRequest::where('status', 'approved')->whereBetween('created_at', [$from, $to])->count();

            $highestTrader = Transaction::select('user_id', DB::raw('SUM(amount_usd) as total'))
                ->where('status', 'completed')
                ->whereBetween('created_at', [$from, $to])
                ->groupBy('user_id')
                ->orderByDesc('total')
                ->first();

            $mostTradedWallet = Transaction::select('network')
                ->where('status', 'completed')
                ->whereBetween('created_at', [$from, $to])
                ->groupBy('network')
                ->orderByRaw('COUNT(*) DESC')
                ->limit(1)
                ->pluck('network')
                ->first();

            $totalBuyRevenue = Transaction::where('type', 'buy')
                ->where('status', 'completed')
                ->whereBetween('created_at', [$from, $to])
                ->sum('amount_usd');

            $totalSwapRevenue = Transaction::where('type', 'swap')
                ->where('status', 'completed')
                ->whereBetween('created_at', [$from, $to])
                ->sum('amount_usd');

            $totalReferralEarning = ReferalEarning::whereBetween('created_at', [$from, $to])->sum('amount');
            $signupBonus = 5000; // placeholder

            $data[$key] = [
                'userAnalytics' => [
                    'totalUsers' => $totalUsers,
                    'newUsers' => $newUsers,
                    'activeUsers' => $activeUsers,
                    'payingUsers' => $payingUsers,
                    'engagedSessions' => $engagedSessions,
                    'deletedUsers' => $deletedUsers,
                    'bouncedUsers' => $bouncedUsers,
                    'engagementRate' => $engagementRate,
                ],
                'transactionAnalytics' => [
                    'totalRevenue' => '$' . number_format($totalRevenue),
                    'swapTransactions' => $totalSwapTransactions,
                    'buyTransactions' => $totalBuyTransactions,
                    'withdrawalTransactions' => $totalWithdrawals,
                    'sendTransactions' => '$' . number_format($totalSendTransactions),
                    'receiveTransactions' => $totalReceiveTransactions,
                    'highestTrade' => '$' . number_format(optional($highestTrader)->total ?? 0),
                    'mostTradedWallet' => $mostTradedWallet ?? 'N/A',
                ],
                'revenueBreakdown' => [
                    'totalRevenue' => '$' . number_format($totalRevenue),
                    'swapRevenue' => $totalSwapRevenue,
                    'buyRevenue' => $totalBuyRevenue,
                    'withdrawalUsers' => $totalWithdrawals,
                    'totalReferralPayout' => '$' . number_format($totalReferralEarning),
                    'totalSignupBonusPayout' => '$' . number_format($signupBonus),
                ],
            ];
        }

        return response()->json($data);
    }
}
