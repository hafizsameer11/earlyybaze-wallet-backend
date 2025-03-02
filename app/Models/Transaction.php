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
        'network',
        'amount_usd',
    ];
    public function user()
    {
        return $this->belongsTo(User::class);
    }
    public function sendtransaction()
    {
        return $this->belongsTo(TransactionSend::class);
    }
}
