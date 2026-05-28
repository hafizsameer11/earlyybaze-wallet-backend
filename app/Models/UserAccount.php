<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;

class UserAccount extends BaseModel
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'account_number',
        'referral_earning_naira',
        'naira_balance',
        'zar_balance',
    ];

    protected $appends = ['rand_balance'];

    public function getRandBalanceAttribute(): string
    {
        return (string) ($this->attributes['zar_balance'] ?? '0');
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
