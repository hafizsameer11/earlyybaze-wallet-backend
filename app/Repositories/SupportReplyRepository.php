<?php

namespace App\Repositories;

use App\Models\SupportReply;
use App\Models\SupportTicket;

class SupportReplyRepository
{
    public function all()
    {
        // Add logic to fetch all data
    }

    public function find($id)
    {
        // Add logic to find data by ID
    }

    public function createByUser(array $data)
    {
        $ticket = SupportTicket::find($data['ticket_id']);

        if (!$ticket) {
            throw new \Exception('Ticket not found');
        }
        $ticket->update(['answered' => 'answered']);

        if (isset($data['attachment']) && $data['attachment']) {
            $path = $data['attachment']->store('attachment', 'public');
            $data['attachment'] = $path;
        }
        $data['sender_type'] = 'user';
        return SupportReply::create($data);
    }
    public function getAllByTicket($ticketId)
    {
        $supportticket = SupportTicket::where('id', $ticketId)->with('replies')->first();
        if (!$supportticket) {
            throw new \Exception('Ticket not found');
        }
        return $supportticket;
    }

    public function update($id, array $data)
    {
        // Add logic to update data
    }

    public function delete($id)
    {
        // Add logic to delete data
    }
}
