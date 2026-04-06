<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserBlockchainWallet extends Model
{
    protected $fillable = [
        'user_id',
        'chain_key',
        'mnemonic_ciphertext',
        'private_key_ciphertext',
        'xpub',
        'primary_address',
        'tatum_wallet_response',
    ];

    protected $casts = [
        'tatum_wallet_response' => 'array',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
