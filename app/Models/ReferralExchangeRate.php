<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ReferralExchangeRate extends Model
{
    use HasFactory;
    protected $fillable = ['amount', 'user_id', 'is_for_all'];
    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
