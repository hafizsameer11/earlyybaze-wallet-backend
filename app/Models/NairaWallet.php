<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;

class NairaWallet extends BaseModel
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'balance',
    ];
}
