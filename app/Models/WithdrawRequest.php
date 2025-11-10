<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes; // ← Import trait

class WithdrawRequest extends Model
{
    use HasFactory, SoftDeletes; // ← Apply SoftDeletes

    protected $fillable = [
        'user_id',
        'amount',
        'status',
        'reference',
        'fee',
        'total',
        'asset',
        'bank_account_id',
        'send_account',
        'balance_before',
    ];

    // 🧠 When fetching balance_before, auto-calculate if missing
    public function getBalanceBeforeAttribute($value)
    {
        // If already set, return it
        if (!is_null($value)) {
            return $value;
        }

        // Otherwise calculate dynamically
        $userAccount = \App\Models\UserAccount::where('user_id', $this->user_id)->first();

        if ($userAccount && $this->total) {
            // Reverse calculate balance before
            return $userAccount->naira_balance + $this->total;
        }

        // Fallback default (if no account found)
        return 0;
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
