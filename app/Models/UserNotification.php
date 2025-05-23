<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class UserNotification extends Model
{
    use HasFactory;
    protected $fillable = [
        'user_id',
        'title',
        'message',
        'status',
        'type',
        'attachment',
    ];
    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
