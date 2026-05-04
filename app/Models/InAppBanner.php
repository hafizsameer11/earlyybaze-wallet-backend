<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;

class InAppBanner extends BaseModel
{
    use HasFactory;

    protected $fillable = [
        'title',
        'url',
        'attachment',
    ];
}
