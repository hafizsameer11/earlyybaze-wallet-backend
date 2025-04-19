<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MinimumTrade extends Model
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
