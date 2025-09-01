<?php

namespace App\Services;

use App\Models\Fee;
use App\Models\UserAccount;
use App\Repositories\WithdrawRequestRepository;
use Exception;
use Google\Service\Docs\Request;
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

   public function create(Request $request)
{
    try {
        $user = Auth::user();

        // Check if user has account
        $userAccount = UserAccount::where('user_id', $user->id)->first();
        if (!$userAccount) {
            throw new Exception('User Account not found');
        }

        // Get latest withdraw fee config
        $fee = Fee::where('type', 'withdraw')->orderBy('id', 'desc')->first();
        if (!$fee) {
            throw new Exception("No withdraw fee defined.");
        }

        $amount = $request->amount;

        // calculate percentage fee
        $percentageFee = bcmul($amount, bcdiv($fee->percentage, '100', 8), 8);

        // fixed fee if available
        $fixedFee = $fee->amount ?? 0;

        // final fee
        $calculatedFee = bcadd($percentageFee, $fixedFee, 8);

        // Prepare withdrawal data
        $data['user_id']   = $user->id;
        $data['status']    = 'pending';
        $data['reference'] = 'EarlyBaze' . time();
        $data['amount']    = $amount;
        $data['fee']       = $calculatedFee;
        $data['total']     = bcadd($amount, $calculatedFee, 8);

        // Check balance
        if ($userAccount->naira_balance < $data['total']) {
            throw new Exception('Insufficient Balance');
        }

        return $this->WithdrawRequestRepository->create($data);

    } catch (Exception $e) {
        throw new Exception('Withdraw Request Creation Failed: ' . $e->getMessage());
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
