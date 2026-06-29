<?php

namespace App\Http\Controllers;

use App\Models\Message;
use App\Models\Company;
use App\Models\User;
use Illuminate\Http\Request;

class ChatController extends Controller
{
    public function index(Request $request)
    {
        $companyIds = Message::whereNull('conversation_ended_at')
            ->distinct()
            ->pluck('company_id');

        $companies = Company::whereIn('id', $companyIds)
            ->get()
            ->map(function ($company) {
                $lastMessage = Message::where('company_id', $company->id)
                    ->whereNull('conversation_ended_at')
                    ->orderByDesc('created_at')
                    ->first();

                $unreadCount = Message::where('company_id', $company->id)
                    ->whereNull('conversation_ended_at')
                    ->where('is_read', false)
                    ->count();

                return [
                    'company'      => $company,
                    'last_message' => $lastMessage,
                    'unread_count' => $unreadCount,
                ];
            });

        if ($request->expectsJson() || $request->query('json')) {
            return response()->json([
                'companies'    => $companies->values(),
                'total_unread' => $companies->sum('unread_count'),
            ]);
        }

        return view('chat.index', compact('companies'));
    }

    public function show(Request $request, Company $company)
    {
        $messages = Message::where('company_id', $company->id)
            ->whereNull('conversation_ended_at')
            ->with('sender')
            ->orderBy('created_at')
            ->get();

        if ($request->expectsJson() || $request->query('json')) {
            return response()->json([
                'messages' => $messages->map(fn($msg) => [
                    'id'          => $msg->id,
                    'body'        => $msg->body,
                    'sender_name' => $msg->sender?->name ?? 'Nieznany',
                    'created_at'  => $msg->created_at->format('H:i'),
                    'is_own'      => $msg->user_id === auth()->id(),
                ]),
            ]);
        }

        $archives = Message::where('company_id', $company->id)
            ->whereNotNull('conversation_ended_at')
            ->selectRaw('conversation_id, MIN(created_at) as started_at, MAX(conversation_ended_at) as ended_at, COUNT(*) as message_count')
            ->groupBy('conversation_id')
            ->orderByDesc('ended_at')
            ->get();

        $onlineUsers = User::whereHas('roles', fn($q) => $q->whereIn('name', ['auditor', 'auditor_senior', 'admin', 'superadmin']))
            ->where('last_seen_at', '>=', now()->subMinutes(5))
            ->get();

        return view('chat.show', compact('company', 'messages', 'archives', 'onlineUsers'));
    }

    public function send(Request $request, Company $company)
    {
        $data = $request->validate([
            'body' => ['required', 'string', 'max:1000'],
        ]);

        $activeConversationId = Message::where('company_id', $company->id)
            ->whereNull('conversation_ended_at')
            ->value('conversation_id');

        $message = Message::create([
            'company_id' => $company->id,
            'user_id' => auth()->id(),
            'body' => $data['body'],
            'is_read' => false,
            'conversation_id' => $activeConversationId,
        ]);

        $message->load('sender');

        return response()->json([
            'id' => $message->id,
            'body' => $message->body,
            'sender_name' => $message->sender->name,
            'created_at' => $message->created_at->format('H:i'),
        ]);
    }

    public function poll(Request $request, Company $company)
    {
        $lastId = $request->input('last_id', 0);

        $messages = Message::where('company_id', $company->id)
            ->whereNull('conversation_ended_at')
            ->where('id', '>', $lastId)
            ->with('sender')
            ->orderBy('created_at')
            ->get();

        return response()->json([
            'messages' => $messages->map(fn($msg) => [
                'id' => $msg->id,
                'body' => $msg->body,
                'sender_name' => $msg->sender->name,
                'created_at' => $msg->created_at->format('H:i'),
                'is_own' => $msg->user_id === auth()->id(),
            ]),
        ]);
    }

    public function endConversation(Company $company)
    {
        Message::where('company_id', $company->id)
            ->whereNull('conversation_ended_at')
            ->update([
                'conversation_ended_at' => now(),
                'ended_by' => auth()->user()->name,
            ]);

        if (request()->expectsJson()) {
            return response()->json(['success' => 'Rozmowa została zakończona.']);
        }

        return back()->with('success', 'Rozmowa została zakończona.');
    }

    public function archiveConversation(Request $request, Company $company, string $conversationId)
    {
        $messages = Message::where('company_id', $company->id)
            ->where('conversation_id', $conversationId)
            ->with('sender')
            ->orderBy('created_at')
            ->get();

        if ($messages->isEmpty() || $messages->first()->company_id !== $company->id) {
            return response()->json(['error' => 'Nie znaleziono rozmowy.'], 404);
        }

        return response()->json([
            'messages' => $messages->map(fn($msg) => [
                'id'          => $msg->id,
                'body'        => $msg->body,
                'sender_name' => $msg->sender?->name ?? 'Nieznany',
                'created_at'  => $msg->created_at->format('d.m.Y H:i'),
                'is_own'      => $msg->user_id === auth()->id(),
            ]),
            'ended_at' => $messages->last()?->conversation_ended_at?->format('d.m.Y H:i'),
            'ended_by' => $messages->last()?->ended_by,
        ]);
    }
    }
}
