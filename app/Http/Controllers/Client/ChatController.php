<?php

namespace App\Http\Controllers\Client;

use App\Http\Controllers\Controller;
use App\Models\Message;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class ChatController extends Controller
{
    public function index()
    {
        $company = auth()->user()->companies->first();

        if (!$company) {
            abort(403, 'Nie jesteś przypisany do żadnej firmy.');
        }

        $messages = Message::where('company_id', $company->id)
            ->whereNull('conversation_ended_at')
            ->with('sender')
            ->orderBy('created_at')
            ->get();

        $archives = Message::where('company_id', $company->id)
            ->whereNotNull('conversation_ended_at')
            ->selectRaw('conversation_id, MIN(created_at) as started_at, MAX(conversation_ended_at) as ended_at, COUNT(*) as message_count')
            ->groupBy('conversation_id')
            ->orderByDesc('ended_at')
            ->get();

        $onlineUsers = User::whereHas('roles', fn($q) => $q->whereIn('name', ['auditor', 'auditor_senior', 'admin', 'superadmin']))
            ->where('last_seen_at', '>=', now()->subMinutes(5))
            ->get();

        return view('client.chat', compact('company', 'messages', 'archives', 'onlineUsers'));
    }

    public function send(Request $request)
    {
        $data = $request->validate([
            'body' => ['required', 'string', 'max:1000'],
        ]);

        $company = auth()->user()->companies->first();

        if (!$company) {
            return response()->json(['error' => 'Nie jesteś przypisany do żadnej firmy.'], 403);
        }

        $activeConversationId = Message::where('company_id', $company->id)
            ->whereNull('conversation_ended_at')
            ->value('conversation_id');

        $message = Message::create([
            'company_id' => $company->id,
            'user_id' => auth()->id(),
            'body' => $data['body'],
            'is_read' => false,
            'conversation_id' => $activeConversationId ?? Str::uuid(),
        ]);

        $message->load('sender');

        return response()->json([
            'id' => $message->id,
            'body' => $message->body,
            'sender_name' => $message->sender->name,
            'created_at' => $message->created_at->format('H:i'),
        ]);
    }

    public function poll(Request $request)
    {
        $company = auth()->user()->companies->first();

        if (!$company) {
            return response()->json(['error' => 'Nie jesteś przypisany do żadnej firmy.'], 403);
        }

        $lastId = $request->input('last_id', 0);

        $messages = Message::where('company_id', $company->id)
            ->whereNull('conversation_ended_at')
            ->where('id', '>', $lastId)
            ->with('sender')
            ->orderBy('created_at')
            ->get();

        $onlineUsers = User::whereHas('roles', fn($q) => $q->whereIn('name', ['auditor', 'auditor_senior', 'admin', 'superadmin']))
            ->where('last_seen_at', '>=', now()->subMinutes(5))
            ->get();

        return response()->json([
            'messages' => $messages->map(fn($msg) => [
                'id' => $msg->id,
                'body' => $msg->body,
                'sender_name' => $msg->sender->name,
                'created_at' => $msg->created_at->format('H:i'),
                'is_own' => $msg->user_id === auth()->id(),
            ]),
            'onlineUsers' => $onlineUsers,
        ]);
    }

    public function endConversation(Request $request)
    {
        $company = auth()->user()->companies->first();

        if (!$company) {
            return $request->expectsJson()
                ? response()->json(['error' => 'Nie jesteś przypisany do żadnej firmy.'], 403)
                : back()->withErrors(['error' => 'Nie jesteś przypisany do żadnej firmy.']);
        }

        Message::where('company_id', $company->id)
            ->whereNull('conversation_ended_at')
            ->update([
                'conversation_ended_at' => now(),
                'ended_by' => auth()->user()->name,
            ]);

        if ($request->expectsJson()) {
            return response()->json(['success' => 'Rozmowa została zakończona.']);
        }

        return back()->with('success', 'Rozmowa została zakończona.');
    }
}

