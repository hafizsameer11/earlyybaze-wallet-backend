<?php

namespace App\Http\Controllers\Wallet;

use App\Helpers\ResponseHelper;
use App\Http\Controllers\Controller;
use App\Http\Requests\BankAccountRequest;
use App\Services\BankAccountService;
use Exception;
use Illuminate\Http\Request;

class BankAccountController extends Controller
{
    //
    protected $BankaccountService;
    public function __construct(BankAccountService $BankaccountService)
    {
        $this->BankaccountService = $BankaccountService;
    }
    public function store(BankAccountRequest $request)
    {
        try {
            $bankAccount = $this->BankaccountService->create($request->all());
            return ResponseHelper::success($bankAccount, 'Bank account created successfully', 200);
        } catch (Exception $e) {
            return ResponseHelper::error($e->getMessage(), 500);
        }
    }
    public function getForUser(Request $request)
    {
        try {
            $bankAccount = $this->BankaccountService->getForUser();
            return ResponseHelper::success($bankAccount, 'Bank account retrieved successfully', 200);
        } catch (Exception $e) {
            return ResponseHelper::error($e->getMessage(), 500);
        }
    }
    public function find($id)
    {
        try {
            $bankAccount = $this->BankaccountService->find($id);
            return ResponseHelper::success($bankAccount, 'Bank account retrieved successfully', 200);
        } catch (Exception $e) {
            return ResponseHelper::error($e->getMessage(), 500);
        }
    }
    public function update($id, BankAccountRequest $request)
    {
        try {
            $bankAccount = $this->BankaccountService->update($id, $request->all());
            return ResponseHelper::success($bankAccount, 'Bank account updated successfully', 200);
        } catch (Exception $e) {
            return ResponseHelper::error($e->getMessage(), 500);
        }
    }
    public function delete($id)
    {
        try {
            $bankAccount = $this->BankaccountService->delete($id);
            return ResponseHelper::success($bankAccount, 'Bank account deleted successfully', 200);
        } catch (Exception $e) {
            return ResponseHelper::error($e->getMessage(), 500);
        }
    }
}
