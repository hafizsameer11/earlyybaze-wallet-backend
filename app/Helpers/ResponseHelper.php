<?php

namespace App\Helpers;

class ResponseHelper
{
    public static function success($data, $message = 'Success', $status = 200)
    {
        return response()->json([
            'status' => 'success',
            'data' => $data,
            'message' => $message
        ], $status);
    }

    public static function error($message, $status = 500)
    {
        return response()->json([
            'status' => 'error',
            'message' => $message
        ], $status);
    }
}
