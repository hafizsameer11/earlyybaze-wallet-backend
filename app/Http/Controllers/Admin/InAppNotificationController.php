<?php

namespace App\Http\Controllers\Admin;

use App\Helpers\ResponseHelper;
use App\Http\Controllers\Controller;
use App\Http\Requests\InAppNotificationRequest;
use App\Jobs\SendPushNotificationToAllUsers;
use App\Models\InAppNotification;
use App\Models\UserNotification;
use App\Services\InAppNotificationService;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class InAppNotificationController extends Controller
{
    //
    protected $inAppNotificationService;

    public function __construct(InAppNotificationService $inAppNotificationService)
    {
        $this->inAppNotificationService = $inAppNotificationService;
    }
    public function getUnreadCount()
    {
        try {
            $user = Auth::user();
            $unreadCount = UserNotification::where('user_id', $user->id)
                ->where('status', 'unread')
                ->count();

            return ResponseHelper::success(['unread_count' => $unreadCount], 'Unread notifications count fetched successfully', 200);
        } catch (Exception $e) {
            return ResponseHelper::error($e->getMessage());
        }
    }
    public function index()
    {
        try {
            $user = Auth::user();

            // Fetch in-app notifications and append type
            $inappNotifications = InAppNotification::all()->map(function ($item) {
                $item->type = 'announcement';
                return $item;
            });

            // Fetch user notifications
            $userNotifications = UserNotification::where('user_id', $user->id)
                ->orderBy('created_at', 'desc')
                ->get();

            // Merge and sort all notifications by created_at (if needed)
            $mergedNotifications = $inappNotifications
                ->merge($userNotifications)
                ->sortByDesc('created_at')
                ->values(); // Reindex
            //mark all user notifications as read
            UserNotification::where('user_id', $user->id)
                ->update(['status' => 'read']);
            return ResponseHelper::success($mergedNotifications, 'Notifications fetched successfully', 200);
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
            if (isset($validatedData['attachment'])) {
                $validatedData['attachment'] = $request->file('attachment')->store('notifications', 'public');
            }
            SendPushNotificationToAllUsers::dispatch($validatedData['title'], $validatedData['message']);


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
