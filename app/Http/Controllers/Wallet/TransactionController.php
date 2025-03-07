<?php

namespace App\Http\Controllers\Wallet;

use App\Helpers\ResponseHelper;
use App\Http\Controllers\Controller;
use App\Http\Requests\BuyTransactionReceiptRequest;
use App\Http\Requests\BuyTransactionRequest;
use App\Http\Requests\InternalTransferRequest;
use App\Http\Requests\OnChainTransaction;
use App\Http\Requests\SwapTransactionRequest;
use App\Models\SwapTransaction;
use App\Models\TransactionSend;
use App\Services\BuyTransactionService;
use App\Services\SwapTransactionService;
use App\Services\TransactionSendService;
use App\Services\transactionService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class TransactionController extends Controller
{
    protected $transactionSendService, $transactionService, $swapTransactionService, $buyTransactionService;

    public function __construct(TransactionSendService $transactionSendService, transactionService $transactionService, SwapTransactionService $swapTransactionService, BuyTransactionService $buyTransactionService)
    {
        $this->transactionSendService = $transactionSendService;
        $this->transactionService = $transactionService;
        $this->swapTransactionService = $swapTransactionService;
        $this->buyTransactionService = $buyTransactionService;
    }
    public function sendInternalTransaction(InternalTransferRequest $request)
    {
        try {
            $transaction = $this->transactionSendService->sendInternalTransaction($request->all());
            return   ResponseHelper::success($transaction, 'Transaction sent successfully', 200);
        } catch (\Exception $e) {
            return ResponseHelper::error($e->getMessage(), 500);
        }
    }
    public function getSendTransactionforUser()
    {
        try {
            $user = Auth::user();
            $transactions = $this->transactionSendService->getTransactionforUser($user->id, 'user_id');
            return ResponseHelper::success($transactions, 'Transactions fetched successfully', 200);
        } catch (\Exception $e) {
            return ResponseHelper::error($e->getMessage(), 500);
        }
    }
    public function getReceiveTransactionforUser()
    {
        try {
            $user = Auth::user();
            $transactions = $this->transactionSendService->getTransactionforUser($user->id, 'receiver_id');
            return ResponseHelper::success($transactions, 'Transactions fetched successfully', 200);
        } catch (\Exception $e) {
            return ResponseHelper::error($e->getMessage(), 500);
        }
    }
    public function getTransactionsForUser()
    {
        try {
            $user = Auth::user();
            $transaction = $this->transactionService->getTransactionsForUser($user->id);
            return ResponseHelper::success($transaction, 'Transactions fetched successfully', 200);
        } catch (\Exception $e) {
            return ResponseHelper::error($e->getMessage(), 500);
        }
    }
    public function sendOnChain(OnChainTransaction $request)
    {
        try {
            $user = Auth::user();
            $transaction = $this->transactionSendService->sendOnChainTransaction($request->all());
            return ResponseHelper::success($transaction, 'Transaction sent successfully', 200);
        } catch (\Exception $e) {
            return ResponseHelper::error($e->getMessage(), 500);
        }
    }
    //swap transaction
    public function swap(SwapTransactionRequest $request)
    {
        try {
            $user = Auth::user();
            $transaction = $this->swapTransactionService->swap($request->all());
            return ResponseHelper::success($transaction, 'Transaction sent successfully', 200);
        } catch (\Exception $e) {
            return ResponseHelper::error($e->getMessage(), 500);
        }
    }
    public function singleSwapTransaction($id)
    {
        try {
            $user = Auth::user();
            $transaction = $this->swapTransactionService->singleSwapTransaction($id);
            return ResponseHelper::success($transaction, 'Transaction fetched successfully', 200);
        } catch (\Exception $e) {
            return ResponseHelper::error($e->getMessage(), 500);
        }
    }

    //buy transactiuon
    public function buy(BuyTransactionRequest $request)
    {
        try {
            $user = Auth::user();
            $transaction = $this->buyTransactionService->create($request->validated());
            return ResponseHelper::success($transaction, 'Transaction sent successfully', 200);
        } catch (\Exception $e) {
            return ResponseHelper::error($e->getMessage(), 500);
        }
    }
    public function attachSlip($id, BuyTransactionReceiptRequest $request)
    {
        try {
            $user = Auth::user();
            $transaction = $this->buyTransactionService->attachSlip($id, $request->validated());
            return ResponseHelper::success($transaction, 'Transaction sent successfully', 200);
        } catch (\Exception $e) {
            return ResponseHelper::error($e->getMessage(), 500);
        }
    }
    public function singleBuyTransaction($id)
    {
        try {
            $user = Auth::user();
            $transaction = $this->buyTransactionService->find($id);
            return ResponseHelper::success($transaction, 'Transaction fetched successfully', 200);
        } catch (\Exception $e) {
            return ResponseHelper::error($e->getMessage(), 500);
        }
    }
}
