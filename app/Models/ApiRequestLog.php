<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;

class ApiRequestLog extends BaseModel
{
    use HasFactory;

    protected $fillable = ['email', 'method', 'url', 'headers', 'body', 'ip'];

    protected $casts = [
        'headers' => 'array',
        'body' => 'array',
    ];
}
