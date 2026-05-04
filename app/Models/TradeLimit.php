<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;

class TradeLimit extends BaseModel
{
    use HasFactory;

    protected $fillable = [
        'type',
        'amount',
        'status',
    ];
}
