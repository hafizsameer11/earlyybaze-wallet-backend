<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TransferLog extends Model
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
