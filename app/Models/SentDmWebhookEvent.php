<?php

namespace App\Models;

class SentDmWebhookEvent extends BaseModel
{
    protected $fillable = [
        'event_key',
        'field',
        'sub_type',
        'event_type',
        'message_id',
        'message_status',
        'channel',
        'phone',
        'template_id',
        'failure_reason',
        'payload',
        'headers',
        'signature_valid',
    ];

    protected $casts = [
        'payload' => 'array',
        'headers' => 'array',
        'signature_valid' => 'boolean',
    ];
}
