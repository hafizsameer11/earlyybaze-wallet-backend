<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class WebhookController extends Controller
{
    public function webhook(Request $request)
    {
        Log::info($request->all());
        return response()->json(['message' => 'Webhook received'], 200);
        // return $request->all();
    }
}
