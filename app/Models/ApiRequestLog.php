<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ApiRequestLog extends Model
{
    use HasFactory;
     protected $fillable = ['email', 'method', 'url', 'headers', 'body','ip'];

    protected $casts = [
        'headers' => 'array',
        'body'    => 'array',
    ];
}
