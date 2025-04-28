<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AdminTransfer extends Model
{
    use HasFactory;
    protected $fillable=['blockchain','currency','address'];
}
