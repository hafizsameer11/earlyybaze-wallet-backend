<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;

class TicketAgent extends BaseModel
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'ticket_id',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function ticket()
    {
        return $this->belongsTo(SupportTicket::class, 'ticket_id');
    }
}
