<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;

class TransactionIcon extends BaseModel
{
    use HasFactory;

    protected $fillable = [
        'icon',
        'type',
    ];
}
