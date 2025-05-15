<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ReferalPayOut extends Model
{
    use HasFactory;
    protected $fillable = [
        'user_id','referal_earning_id','status','amount','paid_to_account','paid_to_bank','paid_to_name','exchange_rate'
    ];
    public function user(){
        return $this->belongsTo(User::class);
    }
}
