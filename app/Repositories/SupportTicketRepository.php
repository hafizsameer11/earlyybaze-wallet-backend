<?php

namespace App\Repositories;

use App\Models\SupportTicket;
use Illuminate\Support\Facades\Auth;

class SupportTicketRepository
{
    public function all()
    {
        // Add logic to fetch all data
    }

    public function find($id)
    {
        $ticket = SupportTicket::find($id);
        if (!$ticket) {
            throw new \Exception('Ticket not found');
        }
        return $ticket;
    }

    public function create(array $data)
    {
        $user = Auth::user();
        $data['user_id'] = $user->id;
        $ticket=SupportTicket::where('user_id',$user->id)->where('status','open')->where('subject',$data['subject'])->first();
        if($ticket){
            throw new \Exception('You already have an open ticket for ' . $data['subject']);
        }
        // Add logic to create data
        return SupportTicket::create($data);
    }
    public function getAllforUser($userId)
    {
        return SupportTicket::where('user_id', $userId)->orderBy('created_at', 'desc')->get();
    }
    public function update($id, array $data)
    {
        $ticket = SupportTicket::find($id);
        if (!$ticket) {
            throw new \Exception('Ticket not found');
        }
        $ticket->update($data);
        return $ticket;
    }

    public function delete($id)
    {
        // Add logic to delete data
    }
}
