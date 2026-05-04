<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;

class ReferralWalletTopUp extends BaseModel
{
    use HasFactory;

    protected $guarded = [];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
