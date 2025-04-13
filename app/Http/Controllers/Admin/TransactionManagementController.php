<?php

namespace App\Http\Controllers\Admin;

use App\Helpers\ResponseHelper;
use App\Http\Controllers\Controller;
use App\Models\WithdrawRequest;
use App\Services\BuyTransactionService;
use App\Services\SwapTransactionService;
use App\Services\TransactionSendService;
use App\Services\transactionService;
use App\Services\WithdrawRequestService;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class TransactionManagementController extends Controller
{
    protected $transactionSendService, $transactionService, $swapTransactionService, $buyTransactionService, $withdrawService;
    public function __construct(TransactionSendService $transactionSendService, transactionService $transactionService, SwapTransactionService $swapTransactionService, BuyTransactionService $buyTransactionService, WithdrawRequestService $withdrawService)
    {
        $this->transactionSendService = $transactionSendService;
        $this->transactionService = $transactionService;
        $this->swapTransactionService = $swapTransactionService;
        $this->buyTransactionService = $buyTransactionService;
        $this->withdrawService = $withdrawService;
    }
    public function getWithdrawRequests()
    {
        try {
            $withdrawRequests = WithdrawRequest::orderBy('created_at', 'desc')->get();
            return ResponseHelper::success($withdrawRequests, 'Withdraw Requests fetched successfully', 200);
        } catch (Exception $e) {
            return ResponseHelper::error($e->getMessage(), 500);
        }
    }
    public function getAll()
    {
        try {
            $data = $this->transactionService->all();
            return ResponseHelper::success($data, 'Transactions fetched successfully', 200);
        } catch (Exception $e) {
            return ResponseHelper::error($e->getMessage(), 500);
        }
    }
    public function getTransactionsForUser($userId)
    {
        try {
            $data = $this->transactionService->getTransactionsForUser($userId);
            return ResponseHelper::success($data, 'Transactions fetched successfully', 200);
        } catch (Exception $e) {
            return ResponseHelper::error($e->getMessage(), 500);
        }
    }
    // public function ReferalPaymentController(){

    // }
    public function getSingleInternalReceiveTransaction($id)
    {
        try {
            Log::info('Fetching single internal receive transaction', ['id' => $id]);
            $transaction = $this->transactionSendService->findByTransactionId($id, $type = "receive");
            return ResponseHelper::success($transaction, 'Transaction fetched successfully', 200);
        } catch (\Exception $e) {
            return ResponseHelper::error($e->getMessage(), 500);
        }
    }
    public function getSingleInternalSendTransaction($id)
    {
        try {
            $transaction = $this->transactionSendService->findByTransactionId($id);
            return ResponseHelper::success($transaction, 'Transaction fetched successfully', 200);
        } catch (\Exception $e) {
            return ResponseHelper::error($e->getMessage(), 500);
        }
    }
    public function getSingleBuyTransaction($id)
    {
        try {
            $transaction = $this->buyTransactionService->findByTransactionId($id);
            return ResponseHelper::success($transaction, 'Transaction fetched successfully', 200);
        } catch (\Exception $e) {
            return ResponseHelper::error($e->getMessage(), 500);
        }
    }
    public function getSingleSwapTransaction($id)
    {
        try {

            $transaction = $this->swapTransactionService->singleSwapTransaction($id);
            return ResponseHelper::success($transaction, 'Transaction fetched successfully', 200);
        } catch (\Exception $e) {
            return ResponseHelper::error($e->getMessage(), 500);
        }
    }
    public function getSingleWithdrawTransaction($id)
    {
        try {
            $withdraw = $this->withdrawService->getwithdrawRequestStatus($id);
            return ResponseHelper::success($withdraw, 'Withdraw fetched successfully', 200);
        } catch (Exception $e) {
            return ResponseHelper::error($e->getMessage(), 500);
        }
    }
    public function getSingleReceiveTransaction($id) {}
    public function getAllBuyRequest()
    {
        try {
            $buyRequests = $this->buyTransactionService->getAllBuyRequest();
            return ResponseHelper::success($buyRequests, 'Buy Requests fetched successfully', 200);
        } catch (Exception $e) {
            return ResponseHelper::error($e->getMessage(), 500);
        }
    }
    // public function getSingleBuyTransaction($id){}

}
