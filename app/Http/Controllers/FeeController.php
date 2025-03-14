<?php

namespace App\Http\Controllers;

use App\Helpers\ResponseHelper;
use App\Http\Requests\FeeRequest;
use App\Services\FeeService;
use Exception;
use Illuminate\Http\Request;

class FeeController extends Controller
{
    protected $feeService;
    // protected $responseHelper;
    public function __construct(FeeService $feeService)
    {
        $this->feeService = $feeService;
    }
    public function create(FeeRequest $request)
    {
        try {
            $fee = $this->feeService->create($request->all());
            return ResponseHelper::success($fee, 'Fee created successfully', 200);
        } catch (Exception $e) {
            return ResponseHelper::error($e->getMessage(), 500);
        }
    }
    public function update(FeeRequest $request, $id)
    {
        try {
            $fee = $this->feeService->update($id, $request->all());
            return ResponseHelper::success($fee, 'Fee updated successfully', 200);
        } catch (Exception $e) {
            return ResponseHelper::error($e->getMessage(), 500);
        }
    }
    public function getByType($type)
    {
        try {
            $fee = $this->feeService->getByType($type);
            return ResponseHelper::success($fee, 'Fee fetched successfully', 200);
        } catch (Exception $e) {
            return ResponseHelper::error($e->getMessage(), 500);
        }
    }
    public function getAll()
    {
        try {
            $fee = $this->feeService->getAll();
            return ResponseHelper::success($fee, 'Fee fetched successfully', 200);
        } catch (Exception $e) {
            return ResponseHelper::error($e->getMessage(), 500);
        }
    }
}
