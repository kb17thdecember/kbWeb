<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Mã xác thực đăng nhập</title>
</head>
<body style="margin:0;padding:24px;background:#f4f6fa;color:#1f2b42;font-family:Arial,sans-serif">
    <main style="max-width:480px;margin:0 auto;padding:28px;background:#fff;border:1px solid #e5eaf2;border-radius:14px;text-align:center">
        <p style="margin:0 0 8px;color:#5066f6;font-size:12px;font-weight:bold;letter-spacing:.08em">KB TECH · QUẢN TRỊ</p>
        <h1 style="margin:0 0 16px;font-size:20px">Mã xác thực đăng nhập</h1>
        <p style="margin:0 0 18px;font-size:14px">Nhập mã dưới đây để hoàn tất đăng nhập:</p>
        <p style="margin:0 0 18px;font-size:34px;font-weight:bold;letter-spacing:.3em">{{ $code }}</p>
        <p style="margin:0;color:#7e899f;font-size:12px">Mã có hiệu lực trong {{ $validForMinutes }} phút. Nếu bạn không đăng nhập, hãy bỏ qua email này.</p>
    </main>
</body>
</html>
