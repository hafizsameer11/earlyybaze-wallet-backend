<?php

namespace App\Repositories\V3;

use App\Models\WithdrawRequest;
use App\Services\FiatBalanceService;
use App\Services\NotificationService;
use Exception;
use Illuminate\Support\Facades\DB;

/**
 * ZAR withdraw requests — v3 only.
 * Legacy NGN withdrawals use App\Repositories\WithdrawRequestRepository unchanged.
 */
class V3WithdrawRequestRepository
{
    public function __construct(
        protected V3WithdrawTransactionRepository $withdrawTransactionRepository,
        protected FiatBalanceService $fiatBalanceService,
    ) {}

    public function create(array $data): WithdrawRequest
    {
        return DB::transaction(function () use ($data) {
            $currency = strtoupper((string) ($data['currency'] ?? ''));
            if ($currency !== 'ZAR') {
                throw new Exception('V3 withdraw route supports ZAR only. Use legacy /withdraw/create for NGN.');
            }

            $userId = (int) $data['user_id'];
            $total = (string) $data['total'];

            $balanceBefore = $this->fiatBalanceService->deduct($userId, 'ZAR', $total);
            $data['balance_before'] = $balanceBefore;
            $data['currency'] = 'ZAR';
            $data['asset'] = $data['asset'] ?? 'zar';

            $withdraw = WithdrawRequest::create($data);

            DB::afterCommit(function () use ($withdraw) {
                app(NotificationService::class)->notifyUser(
                    (int) $withdraw->user_id,
                    'Withdrawal requested',
                    'Your R'.$withdraw->total.' withdrawal is pending review.',
                    'withdraw_pending'
                );
            });

            return $withdraw;
        });
    }

    public function updateStatus(int $id, array $data): WithdrawRequest
    {
        return DB::transaction(function () use ($id, $data) {
            $withdraw = WithdrawRequest::where('id', $id)->lockForUpdate()->first();
            if (! $withdraw) {
                throw new Exception('Withdraw Request not found');
            }

            if (strtoupper((string) ($withdraw->currency ?? 'NGN')) !== 'ZAR') {
                throw new Exception('This withdraw is not a ZAR request. Use legacy admin route for NGN.');
            }

            $status = $data['status'] ?? null;
            if ($status && $withdraw->status === $status) {
                return $withdraw;
            }

            if (isset($data['send_account'])) {
                $withdraw->send_account = $data['send_account'];
            }

            if ($status === 'approved') {
                $withdraw->status = 'approved';
                $withdraw->save();

                $this->withdrawTransactionRepository->create([
                    'withdraw_request_id' => $withdraw->id,
                    'user_id' => $withdraw->user_id,
                ]);

                DB::afterCommit(function () use ($withdraw) {
                    app(NotificationService::class)->notifyUser(
                        (int) $withdraw->user_id,
                        'Withdrawal approved',
                        'Your rand withdraw request has been approved.',
                        'withdraw_approved'
                    );
                });
            } elseif ($status === 'rejected') {
                $withdraw->status = 'rejected';
                $withdraw->save();

                $this->fiatBalanceService->credit(
                    (int) $withdraw->user_id,
                    'ZAR',
                    (string) $withdraw->total
                );

                $this->withdrawTransactionRepository->create([
                    'withdraw_request_id' => $withdraw->id,
                    'user_id' => $withdraw->user_id,
                ]);

                DB::afterCommit(function () use ($withdraw) {
                    app(NotificationService::class)->notifyUser(
                        (int) $withdraw->user_id,
                        'Withdrawal rejected',
                        'Your rand withdraw request was rejected. The amount has been refunded to your account.',
                        'withdraw_rejected'
                    );
                });
            }

            return $withdraw->fresh();
        });
    }
}
