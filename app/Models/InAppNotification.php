<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;

class InAppNotification extends BaseModel
{
    use HasFactory;

    protected $fillable = [
        'title',
        'message',
        'status',
        'attachment',
    ];
}
