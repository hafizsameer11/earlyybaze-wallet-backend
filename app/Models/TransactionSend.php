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
        'blockchain',
        'user_id',
        'receiver_id',
        'transaction_id',
        'original_amount',
        'amount_after_fee',
        'network_fee',
        'platform_fee',
        'fee_summary',
        'fee_actual_transaction',
        'rejection_reason',
        'amount_usd',
        'network_fee',
    ];
    public function user()
    {
        return $this->belongsTo(User::class);
    }
    public function receiver()
    {
        return $this->belongsTo(User::class);
    }
    public function transaction()
    {
        return $this->belongsTo(Transaction::class);
    }
}
