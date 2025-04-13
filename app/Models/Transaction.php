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

    protected $appends = ['details'];

    // ✅ Correct relationships using hasOne
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function sendtransaction()
    {
        return $this->hasOne(TransactionSend::class, 'transaction_id', 'id');
    }

    public function recievetransaction()
    {
        return $this->hasOne(ReceiveTransaction::class, 'transaction_id', 'id');
    }

    public function buytransaction()
    {
        return $this->hasOne(BuyTransaction::class, 'transaction_id', 'id');
    }

    public function swaptransaction()
    {
        return $this->hasOne(SwapTransaction::class, 'transaction_id', 'id');
    }

    public function withdraw_transaction()
    {
        return $this->hasOne(WithdrawTransaction::class, 'transaction_id', 'id');
    }

    // ✅ Clean details accessor
    public function getDetailsAttribute()
    {
        switch ($this->type) {
            case 'send':
                return $this->sendtransaction;
            case 'receive':
                return $this->recievetransaction;
            case 'buy':
                return $this->buytransaction->load('bankAccount');
            case 'swap':
                return $this->swaptransaction;
            case 'withdrawTransaction':
                $withdraw = $this->withdraw_transaction;

                if ($withdraw && $withdraw->withdraw_request) {
                    $withdrawRequest = $withdraw->withdraw_request->load('bankAccount');
                    // Merge withdraw_transaction + withdraw_request (excluding nested object)
                    return array_merge(
                        $withdraw->toArray(),
                        $withdrawRequest->toArray()
                    );
                }

                return $withdraw;
            default:
                return null;
        }
    }
}
