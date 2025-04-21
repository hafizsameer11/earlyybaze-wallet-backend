<?php

namespace App\Repositories;

use App\Models\InAppNotification;
use App\Models\User;
use App\Services\NotificationService;

class InAppNotificationRepository

{
    protected $notificationService;
    public function __construct(NotificationService $notificationService)
    {
        $this->notificationService = $notificationService;
    }
    public function all()
    {
        return InAppNotification::all(); // Fetch all notifications
    }

    public function find($id)
    {
        return InAppNotification::findOrFail($id); // Fetch single notification
    }

    public function create(array $data)
    {
        $users=User::where('role', 'user')->get();
        foreach ($users as $user) {
            $this->notificationService->sendToUserById($user->id, $data['title'], $data['message']);
        }

        return InAppNotification::create($data); // Create a new notification
    }

    public function update($id, array $data)
    {
        $notification = InAppNotification::findOrFail($id);
        $notification->update($data);
        return $notification;
    }

    public function delete($id)
    {
        $notification = InAppNotification::findOrFail($id);
        return $notification->delete();
    }
}
