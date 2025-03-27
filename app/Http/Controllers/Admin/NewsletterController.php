<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Newsletter;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;

class NewsletterController extends Controller
{
    public function index()
    {
        return response()->json(Newsletter::withCount('users')->get());
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'title' => 'required|string',
            'content' => 'required|string',
            'users' => 'required|array',
        ]);

        $newsletter = Newsletter::create([
            'title' => $data['title'],
            'content' => $data['content'],
            'status' => 'in_progress',
        ]);

        $userIds = $data['users'];
        $newsletter->users()->attach($userIds);

        foreach (User::whereIn('id', $userIds)->get() as $user) {
            $newsletter->users()->updateExistingPivot($user->id, [
                'sent_at' => now()
            ]);
        }

        $newsletter->update(['status' => 'completed']);

        return response()->json(['message' => 'Newsletter sent successfully.']);
    }

    public function show($id)
    {
        $newsletter = Newsletter::with('users')->findOrFail($id);
        return response()->json($newsletter);
    }

    // For users to fetch their newsletters
    public function userNewsletters($userId)
    {
        $user = User::findOrFail($userId);
        return response()->json($user->newsletters()->get());
    }
}
