<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Mã khôi phục mật khẩu</title>
</head>
<body style="margin:0;padding:24px;background:#f5f2eb;color:#29271f;font-family:Arial,sans-serif">
    <main style="max-width:480px;margin:0 auto;padding:28px;background:#fffdf8;border:1px solid #e6dfd1;border-radius:14px;text-align:center">
        <p style="margin:0 0 8px;color:#b59a65;font-size:12px;font-weight:bold;letter-spacing:.08em">HVAC MART · KHÔI PHỤC TÀI KHOẢN</p>
        <h1 style="margin:0 0 16px;font-size:20px">Mã xác thực đặt lại mật khẩu</h1>
        <p style="margin:0 0 18px;font-size:14px">
            Xin chào{{ $userName ? ' '.$userName : '' }}, nhập mã gồm 6 chữ số dưới đây để đặt lại mật khẩu tài khoản của bạn:
        </p>
        <p style="margin:0 0 18px;font-size:34px;font-weight:bold;letter-spacing:.3em;color:#18352e">{{ $code }}</p>
        <p style="margin:0;color:#888272;font-size:12px">
            Mã có hiệu lực trong {{ $validForMinutes }} phút. Nếu bạn không yêu cầu đặt lại mật khẩu, vui lòng bỏ qua email này.
        </p>
    </main>
</body>
</html>
