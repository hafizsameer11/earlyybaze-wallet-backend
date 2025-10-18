<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ReceiveTransaction extends Model
{
    use HasFactory;
    protected $fillable = [
        'user_id',
        'virtual_account_id',
        'transaction_id',
        'transaction_type',
        'sender_address',
        'reference',
        'tx_id',
        'amount',
        'currency',
        'blockchain',
        'amount_usd',
        'status',
        'balance_before',
    ];
    public function user()
    {
        return $this->belongsTo(User::class);
    }
    public function virtualAccount()
    {
        return $this->belongsTo(VirtualAccount::class);
    }
    public function transaction()
    {
        return $this->belongsTo(Transaction::class);
    }
}
