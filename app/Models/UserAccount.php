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
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
