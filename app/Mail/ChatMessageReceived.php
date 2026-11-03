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
            from: new Address(
                (string) config('mail.from.address', 'kbnguyen0311@gmail.com'),
                (string) config('mail.from.name', 'HVAC Mart VietNam'),
            ),
            replyTo: $this->conversation->visitor_email
                ? [new Address($this->conversation->visitor_email, $this->conversation->visitor_name ?? '')]
                : [],
            subject: "[HVAC Mart VietNam] Tin nhắn chat mới từ {$name}",
        );
    }

    public function content(): Content
    {
        return new Content(view: 'emails.chats.new-message');
    }
}
