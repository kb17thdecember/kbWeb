<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class AdminLoginOtp extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public string $code, public int $validForMinutes) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            from: new Address(
                (string) config('mail.from.address', 'kbnguyen0311@gmail.com'),
                (string) config('mail.from.name', 'HVAC Mart VietNam'),
            ),
            subject: '[HVAC Mart VietNam] Mã xác thực đăng nhập quản trị',
        );
    }

    public function content(): Content
    {
        return new Content(view: 'emails.admin.login-otp');
    }
}
