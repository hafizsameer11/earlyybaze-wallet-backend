<?php

namespace App\Http\Controllers\Wallet;

use App\Helpers\ResponseHelper;
use App\Http\Controllers\Controller;
use App\Http\Requests\InternalTransferRequest;
use App\Http\Requests\OnChainTransaction;
use App\Models\TransactionSend;
use App\Services\TransactionSendService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class TransactionController extends Controller
{
    protected $transactionService;

    public function __construct(TransactionSendService $transactionService)
    {
        $this->transactionService = $transactionService;
    }
    public function sendInternalTransaction(InternalTransferRequest $request)
    {
        try {
            $transaction = $this->transactionService->sendInternalTransaction($request->all());
            return   ResponseHelper::success($transaction, 'Transaction sent successfully', 200);
        } catch (\Exception $e) {
            return ResponseHelper::error($e->getMessage(), 500);
        }
    }
    public function getSendTransactionforUser()
    {
        try {
            $user = Auth::user();
            $transactions = $this->transactionService->getTransactionforUser($user->id, 'user_id');
            return ResponseHelper::success($transactions, 'Transactions fetched successfully', 200);
        } catch (\Exception $e) {
            return ResponseHelper::error($e->getMessage(), 500);
        }
    }
    public function getReceiveTransactionforUser()
    {
        try {
            $user = Auth::user();
            $transactions = $this->transactionService->getTransactionforUser($user->id, 'receiver_id');
            return ResponseHelper::success($transactions, 'Transactions fetched successfully', 200);
        } catch (\Exception $e) {
            return ResponseHelper::error($e->getMessage(), 500);
        }
    }
    public function sendOnChain(OnChainTransaction $request)
    {
        try {
            $user = Auth::user();
            $transaction = $this->transactionService->sendOnChainTransaction($user->id);
            return ResponseHelper::success($transaction, 'Transaction sent successfully', 200);
        } catch (\Exception $e) {
            return ResponseHelper::error($e->getMessage(), 500);
        }
    }
}
