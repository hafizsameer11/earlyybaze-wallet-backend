<?php

namespace App\Repositories;

use App\Models\SupportTicket;
use App\Models\TicketAgent;
use App\Models\User;
use Illuminate\Support\Facades\Auth;

class SupportTicketRepository
{
    public function all()
    {
        $unanswered = SupportTicket::where('answered', 'unanswered')->count();
        $answered = SupportTicket::where('answered', 'answered')->count();
        $total = SupportTicket::count();
        $tickets = SupportTicket::with('user')->get();
        return ['unanswered' => $unanswered, 'answered' => $answered, 'total' => $total, 'tickets' => $tickets];
    }

    public function find($id)
    {
        $ticket = SupportTicket::where('id', $id)->with('replies','user')->first();
        // $ticket->replies;
        if (!$ticket) {
            throw new \Exception('Ticket not found');
        }
        return $ticket;
    }

    public function create(array $data)
    {
        $user = Auth::user();
        $data['user_id'] = $user->id;
        $ticket = SupportTicket::where('user_id', $user->id)->where('status', 'open')->where('subject', $data['subject'])->first();
        if ($ticket) {
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
    public function assignToAgent($data)
    {
        $ticketId = $data['ticket_id'];
        $agentId = $data['user_id'];
        $ticket = SupportTicket::find($ticketId);
        if (!$ticket) {
            throw new \Exception('Ticket not found');
        }

        $agent = User::find($agentId);
        if (!$agent) {
            throw new \Exception('Agent not found');
        }
        $ticketAgnt = TicketAgent::where('ticket_id', $ticketId)->first();
        if ($ticketAgnt) {
            throw new \Exception('Ticket already assigned to an agent');
        }
        $ticketAgnt = new TicketAgent();
        $ticketAgnt->ticket_id = $ticketId;
        $ticketAgnt->user_id = $agentId;
        $ticketAgnt->save();
        $ticket->agent_assigned = true;
        $ticket->save();
        return $ticket;
    }
}
