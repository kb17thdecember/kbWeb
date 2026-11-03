<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class UserPasswordResetOtp extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public string $code,
        public int $validForMinutes,
        public string $userName = '',
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            from: new Address(
                (string) config('mail.from.address', 'kbnguyen0311@gmail.com'),
                (string) config('mail.from.name', 'HVAC Mart VietNam'),
            ),
            subject: 'Mã khôi phục mật khẩu - HVAC Mart VietNam',
        );
    }

    public function content(): Content
    {
        return new Content(view: 'emails.auth.password-reset-otp');
    }
}
