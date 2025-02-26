<?php

namespace App\Http\Controllers\Wallet;

use App\Helpers\ResponseHelper;
use App\Http\Controllers\Controller;
use App\Http\Requests\SupportTicketRequest;
use App\Services\SupportTicketService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class SupportController extends Controller
{
    protected $SupportTicketService;
    public function __construct(SupportTicketService $SupportTicketService)
    {
        $this->SupportTicketService = $SupportTicketService;
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
    public function getTicket($id)
    {
        try {
            $ticket = $this->SupportTicketService->find($id);
            return ResponseHelper::success($ticket, 'Ticket retrieved successfully', 200);
        } catch (\Exception $e) {
            return ResponseHelper::error($e->getMessage(), 500);
        }
    }
}
