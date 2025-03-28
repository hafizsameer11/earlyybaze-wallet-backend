<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MasterWalletTransaction extends Model
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
