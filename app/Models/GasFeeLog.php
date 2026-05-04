<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;

class GasFeeLog extends BaseModel
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'blockchain',
        'currency',
        'estimated_fee',
        'fee_currency',
        'tx_type',
        'tx_hash',
        'status',
    ];
}
