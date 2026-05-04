<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;

class MinimumTrade extends BaseModel
{
    use HasFactory;

    protected $fillable = [
        'type',
        'amount',
        'amount_naira',
        'percentage',
        'status',
    ];
}
