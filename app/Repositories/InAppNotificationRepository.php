<?php

namespace App\Repositories;

use App\Models\InAppNotification;

class InAppNotificationRepository
{
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