<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;

class ReferralExchangeRate extends BaseModel
{
    use HasFactory;

    protected $fillable = ['amount', 'user_id', 'is_for_all'];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
