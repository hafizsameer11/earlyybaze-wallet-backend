<?php

namespace App\Repositories;

use App\Models\UserAccount;
use App\Models\UserNotification;
use App\Models\WithdrawRequest;

class WithdrawRequestRepository
{
    protected $withdrawTransactionRepository;

    public function __construct(WithdrawTransactionRepository $withdrawTransactionRepository)
    {
        $this->withdrawTransactionRepository = $withdrawTransactionRepository;
    }

    public function all()
    {
        // Add logic to fetch all data
    }

    public function find($id)
    {
        // Add logic to find data by ID
    }

    public function create(array $data)
    {
        return \Illuminate\Support\Facades\DB::transaction(function () use ($data) {
            $userAccount = UserAccount::where('user_id', $data['user_id'])
                ->lockForUpdate()
                ->first();

            if (! $userAccount) {
                throw new \Exception('User Account not found');
            }

            $data['balance_before'] = $userAccount->naira_balance;

            $currentBalance = (string) $userAccount->naira_balance;
            $requiredTotal = $data['total'];

            if (bccomp($currentBalance, $requiredTotal, 8) < 0) {
                throw new \Exception('Insufficient Balance');
            }

            $withdraw = WithdrawRequest::create($data);

            $newBalance = bcsub($currentBalance, $requiredTotal, 8);
            $userAccount->naira_balance = $newBalance;
            $userAccount->save();

            return $withdraw;
        });
    }

    public function updateStatus($id, array $data)
    {
        return \Illuminate\Support\Facades\DB::transaction(function () use ($id, $data) {
            $withdraw = WithdrawRequest::where('id', $id)->lockForUpdate()->first();
            if (! $withdraw) {
                throw new \Exception('Withdraw Request not found');
            }

            if (isset($data['status'])) {
                if ($withdraw->status === $data['status']) {
                    return $withdraw;
                }
            }

            $status = $data['status'] ?? null;

            if (isset($data['send_account'])) {
                $withdraw->send_account = $data['send_account'];
            }

            if ($status == 'approved') {
                $withdraw->status = 'approved';
                $withdraw->save();
                $this->withdrawTransactionRepository->create([
                    'withdraw_request_id' => $withdraw->id,
                    'user_id' => $withdraw->user_id,
                ]);

                UserNotification::create([
                    'user_id' => $withdraw->user_id,
                    'type' => 'withdraw_approved',
                    'message' => 'Your withdraw request has been approved.',
                ]);
            } elseif ($status == 'rejected') {
                $withdraw->status = 'rejected';

                $userAccount = UserAccount::where('user_id', $withdraw->user_id)
                    ->lockForUpdate()
                    ->first();

                if ($userAccount) {
                    $refundAmount = (string) $withdraw->total;
                    $currentBalance = (string) $userAccount->naira_balance;
                    $newBalance = bcadd($currentBalance, $refundAmount, 8);
                    $userAccount->naira_balance = $newBalance;
                    $userAccount->save();
                }

                $withdraw->save();
                $this->withdrawTransactionRepository->create([
                    'withdraw_request_id' => $withdraw->id,
                    'user_id' => $withdraw->user_id,
                ]);
                UserNotification::create([
                    'user_id' => $withdraw->user_id,
                    'type' => 'withdraw_rejected',
                    'message' => 'Your withdraw request has been rejected. The amount has been refunded to your account.',
                ]);
            }

            return $withdraw->fresh();
        });
    }

    public function delete($id)
    {
        // Add logic to delete data
    }

    private function transformWithdrawRequest($withdraw)
    {
        if (! $withdraw) {
            return null;
        }

        $bankAccountData = $withdraw->getFormattedBankAccount();
        $data = $withdraw->toArray();

        unset($data['bank_account_id']);
        unset($data['bank_account_name']);
        unset($data['bank_account_code']);
        unset($data['account_name']);
        unset($data['account_number']);

        if (isset($data['bank_account'])) {
            unset($data['bank_account']);
        }

        $data['bank_account'] = $bankAccountData;

        return $data;
    }

    public function getwithdrawRequestStatus($id)
    {
        $withdraw = WithdrawRequest::where('id', $id)->with('bankAccount')->first();
        if (! $withdraw) {
            return null;
        }

        return $this->transformWithdrawRequest($withdraw);
    }

    public function getWithDrawRequestByUserId($userId)
    {
        $withdraws = WithdrawRequest::where('user_id', $userId)
            ->with('bankAccount')
            ->orderBy('created_at', 'desc')
            ->get();

        return $withdraws->map(function ($withdraw) {
            $transformed = $this->transformWithdrawRequest($withdraw);
            $transformed['type'] = 'withdraw';

            return $transformed;
        });
    }

    public function findByTransactionId($transactionId)
    {
        // return WithdrawRequest::where('transaction_id', $transactionId)->first();
    }

    public function getAllwithdrawRequests()
    {
        $withdraws = WithdrawRequest::where('status', '!=', 'approved')
            ->with('bankAccount', 'user')
            ->orderBy('created_at', 'desc')
            ->get();

        return $withdraws->map(function ($withdraw) {
            $transformed = $this->transformWithdrawRequest($withdraw);

            if ($withdraw->relationLoaded('user') && $withdraw->user) {
                $transformed['user'] = $withdraw->user->toArray();
            }

            return $transformed;
        });
    }
}
