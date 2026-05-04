<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;

class SupportReply extends BaseModel
{
    use HasFactory;

    protected $fillable = ['ticket_id', 'message', 'sender_type', 'attachment'];

    public function ticket()
    {
        return $this->belongsTo(SupportTicket::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
