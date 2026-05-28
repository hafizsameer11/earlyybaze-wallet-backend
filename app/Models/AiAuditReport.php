<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;

class AiAuditReport extends BaseModel
{
    use HasFactory;

    protected $fillable = [
        'success',
        'message',
        'summary',
        'analysis',
        'triggered_by',
    ];

    protected $casts = [
        'success' => 'boolean',
        'summary' => 'array',
    ];
}
