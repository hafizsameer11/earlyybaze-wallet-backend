<?php

namespace App\Services;

use App\Models\UserAccount;
use App\Repositories\WithdrawRequestRepository;
use Exception;
use Illuminate\Support\Facades\Auth;

class WithdrawRequestService
{
    protected $WithdrawRequestRepository;

    public function __construct(WithdrawRequestRepository $WithdrawRequestRepository)
    {
        $this->WithdrawRequestRepository = $WithdrawRequestRepository;
    }

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
            //check if user has enough balance
            $userAccount = UserAccount::where('user_id', $user->id)->first();
            if (!$userAccount) {
                throw new Exception('User Account not found');
            }
            $data['user_id'] = $user->id;
            $data['status'] = 'pending';
            //use time stamp for reference
            $refferece = 'EarlyBaze' . time();
            $data['reference'] = $refferece;
            $total = $data['amount'] + $data['fee'];
            if ($userAccount->naira_balance < $total) {
                throw new Exception('Insufficient Balance');
            }
            $data['total'] = $total;
            return $this->WithdrawRequestRepository->create($data);
        } catch (Exception $e) {
            throw new Exception('Withdraw Request Creation Failed ' . $e->getMessage());
        }
    }

    public function updateStatus($id, array $data)
    {
        try {
            return $this->WithdrawRequestRepository->updateStatus($id, $data);
        } catch (Exception $e) {
            throw new Exception('Update Withdraw Request Status Failed ' . $e->getMessage());
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
            throw new Exception('Get Withdraw Request Status Failed' . $e->getMessage());
        }
    }
    public function getWithdrawRequestforAuthenticatedUser()
    {
        try {
            $user = Auth::user();

            return $this->WithdrawRequestRepository->getWithDrawRequestByUserId($user->id);
        } catch (Exception $e) {
            throw new Exception('Get Withdraw Request By User Id Failed' . $e->getMessage());
        }
    }
    public function getAllwithdrawRequests()
    {
        try {
            return $this->WithdrawRequestRepository->getAllwithdrawRequests();
        } catch (Exception $e) {
            throw new Exception('Get All Withdraw Requests Failed' . $e->getMessage());
        }
    }
}
