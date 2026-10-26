<?php

namespace Modules\KbAdmin\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\ChatConversation;
use App\Models\ChatMessage;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use Modules\KbAdmin\Support\AdminDashboardStats;

class ChatController extends Controller
{
    public function index(AdminDashboardStats $dashboardStats): Response
    {
        return Inertia::render('chats/Index', [
            'conversations' => $this->conversationList(),
            ...$dashboardStats->summary(),
        ]);
    }

    public function conversations(): JsonResponse
    {
        return response()->json(['conversations' => $this->conversationList()]);
    }

    public function messages(Request $request, ChatConversation $conversation): JsonResponse
    {
        $validated = $request->validate([
            'after' => ['nullable', 'integer', 'min:0'],
        ]);

        $messages = $conversation->messages()
            ->where('id', '>', $validated['after'] ?? 0)
            ->orderBy('id')
            ->get();

        $conversation->messages()
            ->where('sender', ChatMessage::SENDER_VISITOR)
            ->whereNull('read_at')
            ->update(['read_at' => now()]);

        return response()->json(['messages' => $messages->map->toChatPayload()->values()]);
    }

    public function store(Request $request, ChatConversation $conversation): JsonResponse
    {
        $validated = $request->validate([
            'body' => ['required', 'string', 'max:2000'],
        ]);

        $message = $conversation->messages()->create([
            'sender' => ChatMessage::SENDER_ADMIN,
            'body' => trim($validated['body']),
        ]);

        $conversation->update(['last_message_at' => now()]);

        return response()->json(['message' => $message->toChatPayload()], 201);
    }

    public function updateStatus(Request $request, ChatConversation $conversation): RedirectResponse
    {
        $validated = $request->validate([
            'status' => ['required', 'string', Rule::in(['open', 'closed'])],
        ]);

        $conversation->update($validated);

        return to_route('admin.chats.index');
    }

    public function destroy(ChatConversation $conversation): RedirectResponse
    {
        $conversation->delete();

        return to_route('admin.chats.index');
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function conversationList(): array
    {
        return ChatConversation::query()
            ->withCount([
                'messages as unread_count' => fn ($query) => $query
                    ->where('sender', ChatMessage::SENDER_VISITOR)
                    ->whereNull('read_at'),
            ])
            ->with(['messages' => fn ($query) => $query->latest('id')->limit(1)])
            ->orderByDesc('last_message_at')
            ->limit(200)
            ->get()
            ->map(fn (ChatConversation $conversation): array => [
                'id' => $conversation->id,
                'visitor_name' => $conversation->visitor_name,
                'visitor_email' => $conversation->visitor_email,
                'visitor_phone' => $conversation->visitor_phone,
                'status' => $conversation->status,
                'unread_count' => $conversation->unread_count,
                'last_message' => $conversation->messages->first()?->body,
                'last_message_at' => $conversation->last_message_at?->toIso8601String(),
            ])
            ->all();
    }
}
