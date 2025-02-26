<?php

namespace App\Repositories;

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

    public function create(array $data)
    {
        $ticket = SupportTicket::find($data['ticket_id']);
        if (!$ticket) {
            throw new \Exception('Ticket not found');
        }
        //handle attachament
        if (isset($data['attachment']) && $data['attachment']) {
            $path = $data['attachment']->store('attachment', 'public');
            $data['attachment'] = $path;
        }
        
        // Add logic to create data
        // return $ticket->replies()->create($data);
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
