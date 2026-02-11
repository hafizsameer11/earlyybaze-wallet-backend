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
            $withdrawRequests = WithdrawRequest::orderBy('created_at', 'desc')
                ->with('bankAccount', 'user')
                ->get();
            
            // Transform to include formatted bank_account object
            $transformed = $withdrawRequests->map(function ($withdrawRequest) {
                $data = $withdrawRequest->toArray();
                
                // Get formatted bank account (uses relationship if bank_account_id exists, otherwise direct fields)
                $bankAccountData = $withdrawRequest->getFormattedBankAccount();
                
                // Remove raw bank account fields from response
                unset($data['bank_account_id']);
                unset($data['bank_account_name']);
                unset($data['bank_account_code']);
                unset($data['account_name']);
                unset($data['account_number']);
                
                // Remove bankAccount relationship (we'll use formatted version)
                if (isset($data['bank_account'])) {
                    unset($data['bank_account']);
                }
                
                // Add formatted bank_account object
                $data['bank_account'] = $bankAccountData;
                $data['type'] = 'withdraw';
                
                // Include user relationship if loaded
                if ($withdrawRequest->relationLoaded('user') && $withdrawRequest->user) {
                    $data['user'] = $withdrawRequest->user->toArray();
                }
                
                return $data;
            });
            
            return ResponseHelper::success($transformed, 'Withdraw Requests fetched successfully', 200);
        } catch (Exception $e) {
            return ResponseHelper::error($e->getMessage(), 500);
        }
    }
 public function getAll(Request $request)
{
    try {
        // Normalize and validate date range params (only use range when BOTH are non-empty)
        $startDate = $request->query('start_date');
        $endDate = $request->query('end_date');
        if ($startDate !== null && $startDate !== '') {
            $startDate = trim((string) $startDate);
        } else {
            $startDate = null;
        }
        if ($endDate !== null && $endDate !== '') {
            $endDate = trim((string) $endDate);
        } else {
            $endDate = null;
        }
        $useDateRange = $startDate !== null && $endDate !== null;

        if ($useDateRange) {
            if (!strtotime($startDate)) {
                return ResponseHelper::error('Invalid start_date format. Use Y-m-d (e.g. 2026-01-01)', 422);
            }
            if (!strtotime($endDate)) {
                return ResponseHelper::error('Invalid end_date format. Use Y-m-d (e.g. 2026-01-31)', 422);
            }
            if (strtotime($startDate) > strtotime($endDate)) {
                return ResponseHelper::error('start_date must be before or equal to end_date', 422);
            }
        }

        $isExport = $request->query('export', false) === 'true' || $request->query('export', false) === true;

        $params = [
            'search' => $request->query('search'),
            'period' => $request->query('period', 'all'),
            'start_date' => $useDateRange ? $startDate : null,
            'end_date' => $useDateRange ? $endDate : null,
            'status' => $request->query('status'), // 'completed', 'pending', 'rejected', or 'all' (ignore if 'all')
            'type' => $request->query('type'), // 'send', 'receive', 'buy', 'swap', 'withdrawTransaction', or 'all' (ignore if 'all')
            'transfer_type' => $request->query('transfer_type'), // 'internal', 'external', or 'all' (ignore if 'all')
            'export' => $isExport, // Export mode flag
            'page' => $isExport ? 1 : $request->query('page', 1), // Ignore page for export
            'per_page' => $isExport ? null : $request->query('per_page', 20), // Ignore per_page for export
        ];

        $data = $this->transactionService->all($params);

        return ResponseHelper::success($data, 'Transactions fetched successfully with params '.json_encode($params), 200);

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
            // Log::info('Fetching single internal receive transaction', ['id' => $id]);
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
        } catch (Exception $e) {
            return ResponseHelper::error($e->getMessage(), 500);
        }
    }
    public function getSingleSwapTransaction($id)
    {
        try {

            $transaction = $this->swapTransactionService->singleSwapTransaction($id);
            return ResponseHelper::success($transaction, 'Transaction fetched successfully', 200);
        } catch (Exception $e) {
            return ResponseHelper::error($e->getMessage(), 500);
        }
    }
    public function getSingleWithdrawTransaction($id)
    {
        try {
            // Use service which uses repository (already transforms the data)
            $withdraw = $this->withdrawService->getwithdrawRequestStatus($id);
            if (!$withdraw) {
                return ResponseHelper::error('Withdraw Request not found', 404);
            }
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
    public function updateBuyTransaction(Request $request, $id)
    {
        try {
            $transaction = $this->buyTransactionService->update($id, $request->all());
            return ResponseHelper::success($transaction, 'Transaction updated successfully', 200);
        } catch (Exception $e) {
            return ResponseHelper::error($e->getMessage(), 500);
        }
    }
}
