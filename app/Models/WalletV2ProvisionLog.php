<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WalletV2ProvisionLog extends Model
{
    public $timestamps = false;

    protected $table = 'wallet_v2_provision_logs';

    protected $fillable = [
        'user_id',
        'job_type',
        'trigger',
        'status',
        'error_message',
        'error_json',
        'raw_error',
        'created_at',
    ];

    protected $casts = [
        'error_json' => 'array',
        'created_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
