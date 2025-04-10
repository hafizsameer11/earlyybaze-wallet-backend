<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TransactionFee extends Model
{
    use HasFactory;
    protected $fillable = [
        'user_id',
        'tx',
        'transaction_type',
        'currency',
        'amount',

        'platform_fee_usd',
        'blockchain_fee_usd',
        'total_fee_usd',
        'fee_currency',
        'fee_naira',

        'gas_limit',
        'gas_price',
        'native_fee',
        'native_fee_doubled',
        'native_currency',

        'status',
    ];
}
