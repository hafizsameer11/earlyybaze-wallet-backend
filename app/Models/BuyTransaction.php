<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class BuyTransaction extends Model
{
    use HasFactory;
    protected $fillable = [
        'user_id',
        'transaction_id',
        'bank_account_id',
        'status',
        'currency',
        'network',
        'amount_coin',
        'amount_usd',
        'amount_naira',
        'receipt',
        'name_on_account',
        'amount_paid',
    ];
    public function transaction()
    {
        return $this->belongsTo(Transaction::class);
    }
    public function user()
    {
        return $this->belongsTo(User::class);
    }
    public function bankAccount()
    {
        return $this->belongsTo(BankAccount::class);
    }
}
