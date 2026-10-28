<?php

use Illuminate\Support\Facades\Mail;
use Resend\Laravel\Transport\ResendTransportFactory;

test('resend mail transport is registered and can use the configured API key', function () {
    config()->set('services.resend.key', 're_test_api_key');

    expect(Mail::mailer('resend')->getSymfonyTransport())
        ->toBeInstanceOf(ResendTransportFactory::class);
});
