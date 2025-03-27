<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Newsletter extends Model
{
  
    use HasFactory;
    protected $fillable = ['title', 'content', 'status', 'scheduled_at'];

    public function users()
    {
        return $this->belongsToMany(User::class, 'user_newsletters')->withTimestamps()->withPivot('is_read', 'sent_at');
    }
}
