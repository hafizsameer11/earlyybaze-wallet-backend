<?php

namespace App\Http\Controllers\Admin;

use App\Helpers\ResponseHelper;
use App\Http\Controllers\Controller;
use App\Http\Requests\InAppBannerRequest;
use App\Services\InAppBannerService;
use Exception;
use Illuminate\Http\Request;

class InAppBannerController extends Controller
{
     protected $inAppBannerService;

    public function __construct(InAppBannerService $inAppBannerService)
    {
        $this->inAppBannerService = $inAppBannerService;
    }

    public function index()
    {
        try {
            $data = $this->inAppBannerService->all();
            return ResponseHelper::success($data, 'Banners fetched successfully', 200);
        } catch (Exception $e) {
            return ResponseHelper::error($e->getMessage());
        }
    }

    public function show($id)
    {
        try {
            $data = $this->inAppBannerService->find($id);
            return ResponseHelper::success($data, 'Banner retrieved successfully', 200);
        } catch (Exception $e) {
            return ResponseHelper::error($e->getMessage());
        }
    }

    public function create(InAppBannerRequest $request)
    {
        try {
            $data = $this->inAppBannerService->create($request->validated());
            return ResponseHelper::success($data, 'Banner created successfully', 201);
        } catch (Exception $e) {
            return ResponseHelper::error($e->getMessage());
        }
    }

    public function update(InAppBannerRequest $request, $id)
    {
        try {
            $data = $this->inAppBannerService->update($id, $request->validated());
            return ResponseHelper::success($data, 'Banner updated successfully', 200);
        } catch (Exception $e) {
            return ResponseHelper::error($e->getMessage());
        }
    }

    public function delete($id)
    {
        try {
            $this->inAppBannerService->delete($id);
            return ResponseHelper::success(null, 'Banner deleted successfully', 200);
        } catch (Exception $e) {
            return ResponseHelper::error($e->getMessage());
        }
    }
}
