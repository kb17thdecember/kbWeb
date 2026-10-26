<?php

namespace App\Mail;

use App\Models\ChatConversation;
use App\Models\ChatMessage;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class ChatMessageReceived extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public ChatConversation $conversation, public ChatMessage $chatMessage) {}

    public function envelope(): Envelope
    {
        $name = $this->conversation->visitor_name ?: 'Khách chưa để tên';

        return new Envelope(
            replyTo: $this->conversation->visitor_email
                ? [new Address($this->conversation->visitor_email, $this->conversation->visitor_name ?? '')]
                : [],
            subject: "Tin nhắn chat mới từ {$name}",
        );
    }

    public function content(): Content
    {
        return new Content(view: 'emails.chats.new-message');
    }
}
