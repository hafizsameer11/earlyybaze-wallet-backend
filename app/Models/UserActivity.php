<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;

class UserActivity extends BaseModel
{
    use HasFactory;
    // public $timestamps = false; // since we are only using created_at manually

    protected $fillable = [
        'user_id',
        'content',
        // 'created_at',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
