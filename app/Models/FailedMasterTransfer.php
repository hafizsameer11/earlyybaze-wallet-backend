<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FailedMasterTransfer extends BaseModel
{
    use HasFactory;

    protected $fillable = [
        'virtual_account_id',
        'webhook_response_id',
        'reason',
    ];

    public function virtualAccount(): BelongsTo
    {
        return $this->belongsTo(VirtualAccount::class);
    }

    public function webhookResponse(): BelongsTo
    {
        return $this->belongsTo(WebhookResponse::class);
    }
}
