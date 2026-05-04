<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;

class WebhookResponse extends BaseModel
{
    use HasFactory;

    protected $fillable = [
        'account_id',
        'subscription_type',
        'amount',
        'reference',
        'currency',
        'tx_id',
        'block_height',
        'block_hash',
        'from_address',
        'to_address',
        'transaction_date',
        'index',
    ];
}
