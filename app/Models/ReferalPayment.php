<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ReferalPayment extends Model
{
    use HasFactory;
    protected $fillable = [
        'type',
        'amount',
        'percentage'
    ];
    protected $hidden = [
        'updated_at'
    ];
}
