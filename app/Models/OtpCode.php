<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class OtpCode extends Model
{
    use HasFactory;
       protected $fillable = [
        'user_id','purpose','code','expires_at','attempts','consumed','ip','user_agent'
    ];
    protected $casts = ['expires_at' => 'datetime', 'consumed' => 'bool'];

    public function user() {
        return $this->belongsTo(User::class);
    }
}
