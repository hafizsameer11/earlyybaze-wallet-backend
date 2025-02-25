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

    public function create(array $data)
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

    public function update($id, array $data)
    {
        return $this->WithdrawRequestRepository->update($id, $data);
    }

    public function delete($id)
    {
        return $this->WithdrawRequestRepository->delete($id);
    }
}
