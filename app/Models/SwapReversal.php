<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SwapReversal extends BaseModel
{
    public const TYPE_CANCEL_PENDING = 'cancel_pending';

    public const TYPE_FULL = 'full';

    public const TYPE_PARTIAL = 'partial';

    protected $fillable = [
        'swap_transaction_id',
        'user_id',
        'admin_id',
        'reversal_type',
        'fiat_currency',
        'fiat_amount_recovered',
        'crypto_currency',
        'crypto_network',
        'crypto_amount_returned',
        'original_fiat_amount',
        'original_crypto_amount',
        'exchange_rate_used',
        'user_fiat_balance_before',
        'admin_note',
        'metadata',
    ];

    protected $casts = [
        'metadata' => 'array',
    ];

    public function swapTransaction(): BelongsTo
    {
        return $this->belongsTo(SwapTransaction::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function admin(): BelongsTo
    {
        return $this->belongsTo(User::class, 'admin_id');
    }
}
