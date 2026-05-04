<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;

class TransferLog extends BaseModel
{
    use HasFactory;

    protected $fillable = [
        'amount',
        'currency',
        'from_address',
        'to_address',
        'tx',
        'status',
        'fee',
    ];
}
