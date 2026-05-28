<?php

namespace App\Repositories;

use App\Models\SupportReply;
use App\Models\SupportTicket;
use App\Services\NotificationService;

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

    public function createByUser(array $data, $userId)
    {
        $ticket = SupportTicket::find($data['ticket_id']);

        if (!$ticket) {
            throw new \Exception('Ticket not found');
        }
        $ticket->update(['answered' => 'unanswered']);

        if (isset($data['attachment']) && $data['attachment']) {
            $path = $data['attachment']->store('attachment', 'public');
            $data['attachment'] = $path;
        }
        $data['sender_type'] = 'user';
        return SupportReply::create($data);
    }
    public function createByAdmin(array $data)
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
        $data['sender_type'] = 'support';
        $reply = SupportReply::create($data);
        app(NotificationService::class)->notifyUser(
            (int) $ticket->user_id,
            'Support reply',
            'You have a new reply on your support ticket.',
            'support_reply'
        );

        return $reply;
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
