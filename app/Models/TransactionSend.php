<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TransactionSend extends Model
{
    use HasFactory;
    protected $fillable = [
        'transaction_type',
        'sender_virtual_account_id',
        'receiver_virtual_account_id',
        'sender_address',
        'receiver_address',
        'amount',
        'currency',
        'tx_id',
        'block_height',
        'block_hash',
        'gas_fee',
        'status',
        'blockchain'
    ];
}
