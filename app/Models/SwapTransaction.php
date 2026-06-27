<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;

class SwapTransaction extends BaseModel
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'transaction_id',
        'currency',
        'fee',
        'amount_usd',
        'amount_naira',
        'amount_zar',
        'fiat_currency',
        'status',
        'exchange_rate',
        'fee_naira',
        'amount', 'reference', 'network',
        'balance_before',
        'reversed_fiat',
        'reversed_crypto',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function transaction()
    {
        return $this->belongsTo(Transaction::class);
    }

    public function reversals()
    {
        return $this->hasMany(SwapReversal::class)->orderByDesc('id');
    }
}
