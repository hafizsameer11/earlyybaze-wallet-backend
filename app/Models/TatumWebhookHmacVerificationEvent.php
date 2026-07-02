<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TatumWebhookHmacVerificationEvent extends Model
{
    protected $table = 'tatum_webhook_hmac_verification_events';

    protected $fillable = [
        'channel',
        'path',
        'ip_address',
        'user_agent',
        'hmac_enabled',
        'enforce',
        'verified',
        'provided_payload_hash',
        'computed_payload_hash',
        'failure_reason',
        'raw_body_len',
        'request_id',
    ];

    protected $casts = [
        'hmac_enabled' => 'boolean',
        'enforce' => 'boolean',
        'verified' => 'boolean',
        'raw_body_len' => 'integer',
    ];
}

