<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;

class MasterWalletTransaction extends BaseModel
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'master_wallet_id',
        'blockchain',
        'currency',
        'to_address',
        'amount',
        'fee',
        'tx_hash',
    ];
}
