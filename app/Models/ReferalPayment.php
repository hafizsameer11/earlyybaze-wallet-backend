<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;

class ReferalPayment extends BaseModel
{
    use HasFactory;

    protected $fillable = [
        'type',
        'amount',
        'percentage',
    ];

    protected $hidden = [
        'updated_at',
    ];
}
