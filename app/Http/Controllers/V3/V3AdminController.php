<?php

namespace App\Http\Controllers\V3;

use App\Helpers\ResponseHelper;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\InAppNotificationController;
use App\Http\Controllers\Admin\NewsletterController;
use App\Http\Controllers\Admin\TransactionManagementController;
use App\Http\Controllers\Admin\UserManagementController;
use App\Http\Controllers\Controller;
use App\Jobs\SendPushNotificationToAudience;
use App\Models\ExchangeRate;
use App\Models\SwapTransaction;
use App\Models\Transaction;
use App\Models\User;
use App\Services\InAppNotificationService;
use Carbon\Carbon;
use Illuminate\Http\Request;

class V3AdminController extends Controller
{
    public function __construct(
        private DashboardController $dashboardController,
        private TransactionManagementController $transactionManagementController,
        private UserManagementController $userManagementController,
        private InAppNotificationService $inAppNotificationService,
    ) {}

    public function dashboard(Request $request)
    {
        $statsPeriod = $request->query('stats_period', 'month');
        $base = $this->dashboardController->dashboardData();
        $payload = $base->getData(true);

        $payload['meta'] = [
            'stats_period' => $statsPeriod,
            'default_chart_period' => 'monthly',
        ];

        $payload['statsCardData'] = $this->scopedStats($statsPeriod);
        $payload['statsCardData']['lifetimeRevenueNgn'] = null;

        return response()->json($payload, 200);
    }

    public function transactions(Request $request)
    {
        return $this->transactionManagementController->getAll($request);
    }

    public function userBalances()
    {
        return $this->userManagementController->getUserBalances();
    }

    public function swapReport(Request $request)
    {
        $start = $request->query('start_date');
        $end = $request->query('end_date');

        $query = SwapTransaction::query()->where('status', 'completed');

        if ($start && $end) {
            $query->whereBetween('created_at', [
                Carbon::parse($start)->startOfDay(),
                Carbon::parse($end)->endOfDay(),
            ]);
        }

        $rows = $query->get();

        $totalUsd = (float) $rows->sum('amount_usd');
        $totalNgn = (float) $rows->sum('amount_naira');

        $zarRate = ExchangeRate::where('currency', 'ZAR')->first();
        $ngnRate = ExchangeRate::where('currency', 'NGN')->first();
        $totalZar = 0.0;

        if ($zarRate && $ngnRate && (float) $ngnRate->rate_usd > 0 && (float) $zarRate->rate_usd > 0) {
            $totalZar = $totalUsd * ((float) $ngnRate->rate_usd / (float) $zarRate->rate_usd);
        }

        $byCurrency = Transaction::query()
            ->where('type', 'swap')
            ->where('status', 'completed')
            ->when($start && $end, function ($q) use ($start, $end) {
                $q->whereBetween('created_at', [
                    Carbon::parse($start)->startOfDay(),
                    Carbon::parse($end)->endOfDay(),
                ]);
            })
            ->selectRaw('currency, COUNT(*) as count, SUM(amount_usd) as volume_usd')
            ->groupBy('currency')
            ->get();

        return ResponseHelper::success([
            'summary' => [
                'total_swaps' => $rows->count(),
                'total_volume_usd' => round($totalUsd, 2),
                'total_naira' => round($totalNgn, 2),
                'total_zar_estimated' => round($totalZar, 2),
            ],
            'by_currency' => $byCurrency,
            'start_date' => $start,
            'end_date' => $end,
        ], 'Swap report fetched', 200);
    }

    public function createNotification(Request $request, InAppNotificationController $legacy)
    {
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'message' => 'required|string',
            'status' => 'required|in:active,inactive',
            'audience' => 'nullable|in:all,verified,unverified,selected',
            'user_ids' => 'nullable|array',
            'user_ids.*' => 'integer|exists:users,id',
            'attachment' => 'nullable|file|mimes:jpg,jpeg,png,pdf|max:2048',
        ]);

        if (isset($validated['attachment'])) {
            $validated['attachment'] = $request->file('attachment')->store('notifications', 'public');
        }

        $data = $this->inAppNotificationService->create($validated);

        $audience = $validated['audience'] ?? 'all';
        $userIds = $validated['user_ids'] ?? [];

        SendPushNotificationToAudience::dispatch(
            $validated['title'],
            $validated['message'],
            $audience,
            $userIds
        );

        return ResponseHelper::success($data, 'Notification created successfully', 201);
    }

    public function createNewsletter(Request $request, NewsletterController $newsletter)
    {
        return $newsletter->store($request);
    }

    private function scopedStats(string $period): array
    {
        $now = Carbon::now();
        [$from, $to] = match ($period) {
            'today' => [$now->copy()->startOfDay(), $now->copy()->endOfDay()],
            'week' => [$now->copy()->startOfWeek(), $now->copy()->endOfWeek()],
            'year' => [$now->copy()->startOfYear(), $now->copy()->endOfYear()],
            default => [$now->copy()->startOfMonth(), $now->copy()->endOfMonth()],
        };

        $users = User::whereBetween('created_at', [$from, $to])->count();
        $transactions = Transaction::whereBetween('created_at', [$from, $to])->count();
        $wallets = \App\Models\VirtualAccount::whereBetween('created_at', [$from, $to])->count();

        $rates = ExchangeRate::pluck('rate_usd', 'currency')
            ->mapWithKeys(fn ($rate, $currency) => [strtoupper(trim($currency)) => (float) $rate]);

        $ngnUsd = $rates['NGN'] ?? null;
        $revenueNgn = 0.0;

        Transaction::whereBetween('created_at', [$from, $to])
            ->select('amount', 'currency')
            ->chunkById(500, function ($chunk) use (&$revenueNgn, $rates, $ngnUsd) {
                foreach ($chunk as $tx) {
                    $amount = (float) $tx->amount;
                    $code = strtoupper(trim((string) $tx->currency));
                    if ($amount === 0.0 || $code === '') {
                        continue;
                    }
                    if ($code === 'NGN') {
                        $revenueNgn += $amount;
                    } elseif ($ngnUsd && isset($rates[$code])) {
                        $revenueNgn += $amount * $rates[$code] * $ngnUsd;
                    }
                }
            });

        return [
            'totalUsers' => $users,
            'totalTransactions' => $transactions,
            'totalWallets' => $wallets,
            'totalRevenue' => round($revenueNgn, 2),
            'totalRevenueUSD' => $ngnUsd ? round($revenueNgn / $ngnUsd, 2) : 0,
            'period' => $period,
            'period_start' => $from->toIso8601String(),
            'period_end' => $to->toIso8601String(),
        ];
    }
}
