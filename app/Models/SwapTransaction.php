<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SwapTransaction extends Model
{
    use HasFactory;
    protected $fillable = [
        'user_id',
        'transaction_id',
        'currency',
        'fee',
        'amount_usd',
        'amount_naira',
        'status',
        'exchange_rate',
        'fee_naira',
        'amount','reference','network'
    ];
    public function user()
    {
        return $this->belongsTo(User::class);
    }
    public function transaction()
    {
        return $this->belongsTo(Transaction::class);
    }
}
