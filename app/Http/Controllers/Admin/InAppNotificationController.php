<?php

namespace App\Http\Controllers\Admin;

use App\Helpers\ResponseHelper;
use App\Http\Controllers\Controller;
use App\Http\Requests\InAppNotificationRequest;
use App\Services\InAppNotificationService;
use Exception;
use Illuminate\Http\Request;

class InAppNotificationController extends Controller
{
    //
    protected $inAppNotificationService;

    public function __construct(InAppNotificationService $inAppNotificationService)
    {
        $this->inAppNotificationService = $inAppNotificationService;
    }

    public function index()
    {
        try {
            $data = $this->inAppNotificationService->all();
            return ResponseHelper::success($data, 'Notifications fetched successfully', 200);
        } catch (Exception $e) {
            return ResponseHelper::error($e->getMessage());
        }
    }

    public function show($id)
    {
        try {
            $data = $this->inAppNotificationService->find($id);
            return ResponseHelper::success($data, 'Notification retrieved successfully', 200);
        } catch (Exception $e) {
            return ResponseHelper::error($e->getMessage());
        }
    }

    public function store(InAppNotificationRequest $request)
    {
        try {
            $validatedData = $request->validated();

            // Handle file upload if an attachment is included
            if ($request->hasFile('attachment')) {
                $validatedData['attachment'] = $request->file('attachment')->store('notifications', 'public');
            }

            $data = $this->inAppNotificationService->create($validatedData);
            return ResponseHelper::success($data, 'Notification created successfully', 201);
        } catch (Exception $e) {
            return ResponseHelper::error($e->getMessage());
        }
    }

    public function update(InAppNotificationRequest $request, $id)
    {
        try {
            $validatedData = $request->validated();

            // Handle file upload if an attachment is included
            if ($request->hasFile('attachment')) {
                $validatedData['attachment'] = $request->file('attachment')->store('notifications', 'public');
            }

            $data = $this->inAppNotificationService->update($id, $validatedData);
            return ResponseHelper::success($data, 'Notification updated successfully', 200);
        } catch (Exception $e) {
            return ResponseHelper::error($e->getMessage());
        }
    }

    public function destroy($id)
    {
        try {
            $this->inAppNotificationService->delete($id);
            return ResponseHelper::success(null, 'Notification deleted successfully', 200);
        } catch (Exception $e) {
            return ResponseHelper::error($e->getMessage());
        }
    }
}
