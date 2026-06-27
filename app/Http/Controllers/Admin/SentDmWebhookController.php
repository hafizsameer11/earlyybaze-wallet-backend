<?php

namespace App\Http\Controllers\Admin;

use App\Helpers\ResponseHelper;
use App\Http\Controllers\Controller;
use App\Models\SentDmWebhookEvent;
use Illuminate\Http\Request;

class SentDmWebhookController extends Controller
{
    /**
     * List recent Sent.dm webhook events for debugging OTP / WhatsApp delivery.
     */
    public function index(Request $request)
    {
        $status = strtoupper((string) $request->query('status', ''));
        $phone = trim((string) $request->query('phone', ''));
        $messageId = trim((string) $request->query('message_id', ''));
        $perPage = min(100, max(1, (int) $request->query('per_page', 25)));

        $query = SentDmWebhookEvent::query()->orderByDesc('id');

        if ($status !== '') {
            $query->where('message_status', $status);
        }

        if ($phone !== '') {
            $query->where('phone', 'like', '%'.$phone.'%');
        }

        if ($messageId !== '') {
            $query->where('message_id', $messageId);
        }

        $paginated = $query->paginate($perPage);

        return ResponseHelper::success($paginated, 'Sent.dm webhook events fetched', 200);
    }
}
