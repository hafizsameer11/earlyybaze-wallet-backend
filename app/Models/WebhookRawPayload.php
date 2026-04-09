<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class WebhookRawPayload extends Model
{
    protected $fillable = [
        'channel',
        'payload',
        'headers',
        'ip_address',
        'user_agent',
    ];

    protected $casts = [
        'payload' => 'array',
        'headers' => 'array',
    ];

    public const CHANNEL_V1 = 'v1';

    public const CHANNEL_V2 = 'v2';

    /**
     * Persist the incoming request for auditing / replay; never throws to callers.
     */
    public static function recordIncoming(Request $request, string $channel): void
    {
        try {
            self::create([
                'channel' => $channel,
                'payload' => $request->all(),
                'headers' => $request->headers->all(),
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
            ]);
        } catch (\Throwable $e) {
            Log::warning('WebhookRawPayload store failed', [
                'channel' => $channel,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
