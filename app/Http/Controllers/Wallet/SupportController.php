<?php

namespace App\Http\Controllers\Wallet;

use App\Helpers\ResponseHelper;
use App\Http\Controllers\Controller;
use App\Http\Requests\SupportReplyRequest;
use App\Http\Requests\SupportTicketRequest;
use App\Services\SupportReplyService;
// use App\Models\SupportReply;
use App\Services\SupportTicketService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class SupportController extends Controller
{
    protected $SupportTicketService;
    protected $SupportReplyService;
    public function __construct(SupportTicketService $SupportTicketService, SupportReplyService $SupportReplyService)
    {
        $this->SupportTicketService = $SupportTicketService;
        $this->SupportReplyService = $SupportReplyService;
    }
    public function crateTicket(SupportTicketRequest $request)
    {
        try {

            $ticket = $this->SupportTicketService->create($request->validated());
            // return ResponseHelper::success($bankAccount, 'Bank account updated successfully', 200);
            return ResponseHelper::success($ticket, 'Ticket created successfully', 200);
        } catch (\Exception $e) {
            return ResponseHelper::error($e->getMessage(), 500);
        }
    }
    public function getTicketsForAuthUser()
    {
        try {
            $user = Auth::user();
            $tickets = $this->SupportTicketService->getAllforUser($user->id);
            return ResponseHelper::success($tickets, 'Tickets retrieved successfully', 200);
        } catch (\Exception $e) {
            return ResponseHelper::error($e->getMessage(), 500);
        }
    }
    public function markTicketResolved($id)
    {
        try {
            $user = Auth::user();
            $ticket = $this->SupportTicketService->markResolved($id);
            return ResponseHelper::success($ticket, 'Ticket resolved successfully', 200);
        } catch (\Exception $e) {
            return ResponseHelper::error($e->getMessage(), 500);
        }
    }
    public function getTicket($id)
    {
        try {
            $ticket = $this->SupportTicketService->find($id);
            return ResponseHelper::success($ticket, 'Ticket retrieved successfully', 200);
        } catch (\Exception $e) {
            return ResponseHelper::error($e->getMessage(), 500);
        }
    }
    public function createReplyByUser(SupportReplyRequest $request)
    {
        try {
            $user = Auth::user();
            $ticket = $this->SupportReplyService->createByUser($request->all(), $user->id);
            return ResponseHelper::success($ticket, 'Reply created successfully', 200);
        } catch (\Exception $e) {
            return ResponseHelper::error($e->getMessage(), 500);
        }
    }
    public function createReplyByAdmin(SupportReplyRequest $request)
    {
        try {
            $user = Auth::user();
            $ticket = $this->SupportReplyService->createByAdmin($request->all());
            return ResponseHelper::success($ticket, 'Reply created successfully', 200);
        } catch (\Exception $e) {
            return ResponseHelper::error($e->getMessage(), 500);
        }
    }
    public function assignToAgent(Request $request)
    {
        try {
            $ticket = $this->SupportTicketService->assignToAgent($request->all());
            return ResponseHelper::success($ticket, 'Ticket assigned to agent successfully', 200);
        } catch (\Exception $e) {
            return ResponseHelper::error($e->getMessage(), 500);
        }
    }
    public function getAllTickets()
    {
        try {
            $tickets = $this->SupportTicketService->all();
            return ResponseHelper::success($tickets, 'Tickets retrieved successfully', 200);
        } catch (\Exception $e) {
            return ResponseHelper::error($e->getMessage(), 500);
        }
    }
}
