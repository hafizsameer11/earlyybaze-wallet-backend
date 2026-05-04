<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;

class Ledger extends BaseModel
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'type',
        'blockchain',
        'currency',
        'amount',
        'tx_hash',
    ];
}
