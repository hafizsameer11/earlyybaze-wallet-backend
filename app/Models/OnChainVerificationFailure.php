<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OnChainVerificationFailure extends BaseModel
{
    public const TYPE_DEPOSIT = 'deposit';

    public const TYPE_FLUSH = 'flush';

    public const RESOLUTION_APPROVED = 'approved';

    public const RESOLUTION_DISMISSED = 'dismissed';

    protected $fillable = [
        'type',
        'received_asset_id',
        'tx_id',
        'currency',
        'chain',
        'expected_from',
        'expected_to',
        'expected_amount',
        'failure_code',
        'failure_message',
        'tatum_response',
        'webhook_payload',
        'reference',
        'user_id',
        'resolved_at',
        'resolved_by',
        'resolution',
    ];

    protected $casts = [
        'tatum_response' => 'array',
        'webhook_payload' => 'array',
        'resolved_at' => 'datetime',
    ];

    public function receivedAsset(): BelongsTo
    {
        return $this->belongsTo(ReceivedAsset::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
