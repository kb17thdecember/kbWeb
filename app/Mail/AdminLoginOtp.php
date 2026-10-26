<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class AdminLoginOtp extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public string $code, public int $validForMinutes) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Mã xác thực đăng nhập quản trị');
    }

    public function content(): Content
    {
        return new Content(view: 'emails.admin.login-otp');
    }
}
