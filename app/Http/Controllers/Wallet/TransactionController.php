<?php

namespace App\Http\Controllers\Wallet;

use App\Helpers\ResponseHelper;
use App\Http\Controllers\Controller;
use App\Http\Requests\InternalTransferRequest;
use App\Models\TransactionSend;
use App\Services\TransactionSendService;
use Illuminate\Http\Request;

class TransactionController extends Controller
{
    protected $transactionService;

    public function __construct(TransactionSendService $transactionService)
    {
        $this->transactionService = $transactionService;
    }
    public function sendInternalTransaction(InternalTransferRequest $request)
    {
        try{
            $transaction = $this->transactionService->sendInternalTransaction($request->all());
         return   ResponseHelper::success($transaction, 'Transaction sent successfully', 200);
        } catch (\Exception $e) {
            return ResponseHelper::error($e->getMessage(), 500);
        }
    }
}
