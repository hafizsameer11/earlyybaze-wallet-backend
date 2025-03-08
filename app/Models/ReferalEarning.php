<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ReferalEarning extends Model
{
    use HasFactory;
    protected $fillable = ['user_id', 'amount', 'currency', 'referal_id', 'type', 'status'];
    public function user()
    {
        return $this->belongsTo(User::class);
    }
    public function referal()
    {
        return $this->belongsTo(User::class);
    }
}
