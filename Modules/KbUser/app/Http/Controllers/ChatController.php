<?php

namespace Modules\KbUser\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Mail\ChatMessageReceived;
use App\Models\ChatConversation;
use App\Models\ChatMessage;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Throwable;

class ChatController extends Controller
{
    private const SESSION_KEY = 'chat_conversation_token';

    public function messages(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'after' => ['nullable', 'integer', 'min:0'],
        ]);

        $conversation = $this->currentConversation($request);

        if ($conversation === null) {
            return response()->json(['messages' => []]);
        }

        $messages = $conversation->messages()
            ->where('id', '>', $validated['after'] ?? 0)
            ->orderBy('id')
            ->get();

        $conversation->messages()
            ->where('sender', ChatMessage::SENDER_ADMIN)
            ->whereNull('read_at')
            ->update(['read_at' => now()]);

        return response()->json([
            'messages' => $messages->map->toChatPayload()->values(),
            'status' => $conversation->status,
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'body' => ['required', 'string', 'max:2000'],
            'name' => ['nullable', 'string', 'max:255'],
            'email' => ['nullable', 'email:rfc', 'max:255'],
            'phone' => ['nullable', 'string', 'regex:/^[0-9+().\-\s]{8,30}$/'],
        ]);

        $user = $request->user();
        $visitorName = $user?->name ?: ($validated['name'] ?? null);
        $visitorEmail = $user?->email ?: ($validated['email'] ?? null);
        $visitorPhone = $user?->phone ?: ($validated['phone'] ?? null);

        $conversation = $this->currentConversation($request);

        if ($conversation === null) {
            $conversation = ChatConversation::create([
                'token' => Str::random(40),
                'visitor_name' => $visitorName,
                'visitor_email' => $visitorEmail,
                'visitor_phone' => $visitorPhone,
            ]);

            $request->session()->put(self::SESSION_KEY, $conversation->token);
        } else {
            $conversation->fill(array_filter([
                'visitor_name' => $visitorName,
                'visitor_email' => $visitorEmail,
                'visitor_phone' => $visitorPhone,
            ]));
        }

        $hasUnreadVisitorMessage = $conversation->messages()
            ->where('sender', ChatMessage::SENDER_VISITOR)
            ->whereNull('read_at')
            ->exists();

        $message = $conversation->messages()->create([
            'sender' => ChatMessage::SENDER_VISITOR,
            'body' => trim($validated['body']),
        ]);

        $conversation->update([
            'status' => 'open',
            'last_message_at' => now(),
        ]);

        // Chỉ gửi mail cho tin đầu tiên chưa đọc để tránh spam hộp thư.
        if (! $hasUnreadVisitorMessage) {
            $notification = new ChatMessageReceived($conversation, $message);

            defer(function () use ($notification): void {
                try {
                    Mail::to(config('mail.to.address'))->send($notification);
                } catch (Throwable $exception) {
                    report($exception);
                }
            });
        }

        return response()->json(['message' => $message->toChatPayload()], 201);
    }

    private function currentConversation(Request $request): ?ChatConversation
    {
        $token = $request->session()->get(self::SESSION_KEY);

        if (! is_string($token) || $token === '') {
            return null;
        }

        return ChatConversation::query()->where('token', $token)->first();
    }
}
