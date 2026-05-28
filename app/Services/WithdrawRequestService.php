<?php

namespace App\Services;

use App\Models\Fee;
use App\Models\UserAccount;
use App\Repositories\WithdrawRequestRepository;
use Exception;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Schema;

class WithdrawRequestService
{
    public function __construct(
        protected WithdrawRequestRepository $WithdrawRequestRepository,
    ) {}

    public function all()
    {
        return $this->WithdrawRequestRepository->all();
    }

    public function find($id)
    {
        return $this->WithdrawRequestRepository->find($id);
    }

    public function create(array $data): \App\Models\WithdrawRequest
    {
        try {
            $user = Auth::user();

            $userAccount = UserAccount::where('user_id', $user->id)->first();
            if (! $userAccount) {
                throw new Exception('User Account not found');
            }

            $data['user_id'] = $user->id;
            $data['status'] = 'pending';
            $data['reference'] = 'EarlyBaze'.time();

            // Legacy NGN flow — do not pass currency unless column exists (avoids prod migration issues).
            unset($data['currency']);
            if (Schema::hasColumn('withdraw_requests', 'currency')) {
                $data['currency'] = 'NGN';
            }

            $amount = (string) $data['amount'];

            $fee = Fee::where('type', 'withdraw')->orderByDesc('id')->first();
            if (! $fee) {
                throw new Exception('No withdraw fee defined.');
            }

            $percentageFee = bcmul($amount, bcdiv((string) $fee->percentage, '100', 8), 8);
            $fixedFee = (string) ($fee->amount ?? 0);
            $calculatedFee = bcadd($percentageFee, $fixedFee, 8);

            $data['fee'] = $calculatedFee;
            $data['total'] = bcadd($amount, $calculatedFee, 8);

            $currentBalance = (string) $userAccount->naira_balance;
            if (bccomp($currentBalance, $data['total'], 8) < 0) {
                throw new Exception('Insufficient Balance');
            }

            return $this->WithdrawRequestRepository->create($data);
        } catch (Exception $e) {
            throw new Exception('Withdraw Request Creation Failed '.$e->getMessage());
        }
    }

    public function updateStatus($id, array $data)
    {
        try {
            return $this->WithdrawRequestRepository->updateStatus($id, $data);
        } catch (Exception $e) {
            throw new Exception('Update Withdraw Request Status Failed '.$e->getMessage());
        }
    }

    public function delete($id)
    {
        return $this->WithdrawRequestRepository->delete($id);
    }

    public function getwithdrawRequestStatus($id)
    {
        try {
            return $this->WithdrawRequestRepository->getwithdrawRequestStatus($id);
        } catch (Exception $e) {
            throw new Exception('Get Withdraw Request Status Failed'.$e->getMessage());
        }
    }

    public function getWithdrawRequestforAuthenticatedUser()
    {
        try {
            $user = Auth::user();

            return $this->WithdrawRequestRepository->getWithDrawRequestByUserId($user->id);
        } catch (Exception $e) {
            throw new Exception('Get Withdraw Request By User Id Failed'.$e->getMessage());
        }
    }

    public function getAllwithdrawRequests()
    {
        try {
            return $this->WithdrawRequestRepository->getAllwithdrawRequests();
        } catch (Exception $e) {
            throw new Exception('Get All Withdraw Requests Failed'.$e->getMessage());
        }
    }
}
