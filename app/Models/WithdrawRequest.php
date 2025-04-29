<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class WithdrawRequest extends Model
{
    use HasFactory;
    protected $fillable = [
        'user_id',
        'amount',
        'status',
        'reference',
        'fee',
        'total',
        'asset',
        'bank_account_id',
        'send_account'
    ];
    public function user()
    {
        return $this->belongsTo(User::class);
    }
    public function bankAccount()
    {
        return $this->belongsTo(BankAccount::class);
    }
}
