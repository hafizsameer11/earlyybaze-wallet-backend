<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Transaction extends Model
{
    use HasFactory;
    protected $fillable = [
        'user_id',
        'amount',
        'currency',
        'status',
        'transaction_type',
        'transaction_id',
        'reference',
        'type',
        'network',
        'amount_usd',
        'fee',
        'fee_usd'
    ];
    public function user()
    {
        return $this->belongsTo(User::class);
    }
    public function sendtransaction()
    {
        return $this->belongsTo(TransactionSend::class);
    }
    public function recievetransaction()
    {
        return $this->belongsTo(ReceiveTransaction::class);
    }
    public function buytransaction()
    {
        return $this->belongsTo(BuyTransaction::class);
    }
    public function swaptransaction()
    {
        return $this->belongsTo(SwapTransaction::class);
    }
    public function withdraw_transaction()
    {
        return $this->hasOne(WithdrawTransaction::class, 'transaction_id');
    }



    protected $appends = ['details'];

    public function getDetailsAttribute()
    {
        switch ($this->type) {
            case 'send':
                return $this->sendtransaction;
            case 'receive':
                return $this->recievetransaction;
            case 'buy':
                return $this->buytransaction;
            case 'swap':
                return $this->swaptransaction;
            case 'withdrawTransaction':
                return $this->withdraw_transaction?->load('withdraw_request');
            default:
                return null;
        }
    }
}
