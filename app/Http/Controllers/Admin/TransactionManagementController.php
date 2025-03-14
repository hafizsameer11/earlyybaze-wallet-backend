<?php

namespace App\Http\Controllers\Admin;

use App\Helpers\ResponseHelper;
use App\Http\Controllers\Controller;
use App\Services\transactionService;
use Exception;
use Illuminate\Http\Request;

class TransactionManagementController extends Controller
{
    protected $transactionService;
    public function __construct(transactionService $transactionService)
    {
        $this->transactionService = $transactionService;
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
}
