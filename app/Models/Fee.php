<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;

class Fee extends BaseModel
{
    use HasFactory;

    protected $fillable = ['status', 'type', 'amount', 'percentage', 'amount_naira'];
}
