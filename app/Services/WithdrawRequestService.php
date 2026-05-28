<?php

namespace App\Services;

use App\Models\Fee;
use App\Repositories\WithdrawRequestRepository;
use Exception;
use Illuminate\Support\Facades\Auth;

class WithdrawRequestService
{
    public function __construct(
        protected WithdrawRequestRepository $WithdrawRequestRepository,
        protected FiatBalanceService $fiatBalanceService,
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
            $currency = FiatBalanceService::normalizeCurrency($data);

            $data['user_id'] = $user->id;
            $data['status'] = 'pending';
            $data['reference'] = 'EarlyBaze'.time();
            $data['currency'] = $currency;
            $data['asset'] = FiatBalanceService::assetLabel($currency);

            $amount = (string) $data['amount'];

            $feeType = FiatBalanceService::withdrawFeeType($currency);
            $fee = Fee::where('type', $feeType)->orderByDesc('id')->first()
                ?? Fee::where('type', 'withdraw')->orderByDesc('id')->first();

            if (! $fee) {
                throw new Exception('No withdraw fee defined.');
            }

            $percentageFee = bcmul($amount, bcdiv((string) $fee->percentage, '100', 8), 8);
            $fixedFee = (string) ($fee->amount ?? 0);
            $calculatedFee = bcadd($percentageFee, $fixedFee, 8);

            $data['fee'] = $calculatedFee;
            $data['total'] = bcadd($amount, $calculatedFee, 8);

            $currentBalance = $this->fiatBalanceService->getAvailableBalance($user->id, $currency);
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
