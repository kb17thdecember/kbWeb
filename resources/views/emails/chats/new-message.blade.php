<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Tin nhắn chat mới</title>
</head>
<body style="margin:0;padding:24px;background:#f4f6fa;color:#1f2b42;font-family:Arial,sans-serif">
    <main style="max-width:520px;margin:0 auto;padding:28px;background:#fff;border:1px solid #e5eaf2;border-radius:14px">
        <p style="margin:0 0 8px;color:#5066f6;font-size:12px;font-weight:bold;letter-spacing:.08em">KB TECH · CHAT KHÁCH HÀNG</p>
        <h1 style="margin:0 0 16px;font-size:20px">Có tin nhắn mới</h1>
        <p style="margin:0 0 4px;font-size:14px"><b>Khách:</b> {{ $conversation->visitor_name ?: 'Chưa để tên' }}</p>
        <p style="margin:0 0 4px;font-size:14px"><b>Email:</b> {{ $conversation->visitor_email ?: 'Chưa để email' }}</p>
        <p style="margin:0 0 16px;font-size:14px"><b>Điện thoại:</b> {{ $conversation->visitor_phone ?: 'Chưa để số' }}</p>
        <p style="margin:0 0 16px;padding:14px;background:#f4f6fa;border-radius:10px;font-size:14px;line-height:1.5;white-space:pre-wrap">{{ $chatMessage->body }}</p>
        <p style="margin:0"><a href="{{ url('/admin/chats') }}" style="color:#5066f6;font-size:14px">Mở hộp thư chat để trả lời</a></p>
    </main>
</body>
</html>
