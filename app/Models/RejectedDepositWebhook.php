<?php

namespace App\Models;

use App\Support\AllowedFungibleContracts;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RejectedDepositWebhook extends BaseModel
{
    public const REASON_NON_ALLOWLISTED_CONTRACT = AllowedFungibleContracts::REJECT_NON_ALLOWLISTED_CONTRACT;

    public const REASON_FUNGIBLE_ON_NATIVE_WALLET = AllowedFungibleContracts::REJECT_FUNGIBLE_ON_NATIVE_WALLET;

    public const REASON_CONTRACT_WALLET_MISMATCH = AllowedFungibleContracts::REJECT_CONTRACT_WALLET_MISMATCH;

    public const REASON_MISSING_CONTRACT = AllowedFungibleContracts::REJECT_MISSING_CONTRACT;

    protected $fillable = [
        'channel',
        'rejection_reason',
        'subscription_type',
        'tx_id',
        'log_index',
        'contract_address',
        'payload_currency',
        'account_currency',
        'amount',
        'chain',
        'from_address',
        'to_address',
        'account_id',
        'user_id',
        'token_symbol',
        'token_name',
        'reference',
        'payload',
    ];

    protected $casts = [
        'payload' => 'array',
        'log_index' => 'integer',
        'amount' => 'string',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
