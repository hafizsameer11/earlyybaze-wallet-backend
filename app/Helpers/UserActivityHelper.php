<?php

namespace App\Helpers;

use App\Models\UserActivity;
use Illuminate\Support\Facades\Log;

class UserActivityHelper
{
    public static function logActivity($userId, $activity)
    {
        $userActivity = new UserActivity();
        $userActivity->user_id = $userId;
        $userActivity->content = $activity;
        $userActivity->save();
        // Log user activity to the database or a log file
        // Log::info("User ID: {$userId}, Activity: {$activity}");
    }
    public static function LoggedInUserActivity($activity)
    {
        $userId = auth()->id();
        if ($userId) {
            self::logActivity($userId, $activity);
        }
    }
}
