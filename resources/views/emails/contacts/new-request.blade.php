<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Yêu cầu tư vấn mới</title>
</head>
<body style="margin:0;padding:24px;background:#f4f6fa;color:#1f2b42;font-family:Arial,sans-serif">
    <main style="max-width:680px;margin:0 auto;padding:28px;background:#fff;border:1px solid #e5eaf2;border-radius:14px">
        <p style="margin:0 0 8px;color:#5066f6;font-size:12px;font-weight:bold;letter-spacing:.08em">KB TECH · YÊU CẦU TƯ VẤN</p>
        <h1 style="margin:0 0 22px;font-size:22px">Có yêu cầu tư vấn mới</h1>

        <table style="width:100%;border-collapse:collapse;font-size:14px">
            <tbody>
                <tr>
                    <th style="width:190px;padding:10px;border-bottom:1px solid #e5eaf2;text-align:left">Mã yêu cầu</th>
                    <td style="padding:10px;border-bottom:1px solid #e5eaf2">#{{ $contact->id }}</td>
                </tr>
                <tr>
                    <th style="padding:10px;border-bottom:1px solid #e5eaf2;text-align:left">Họ và tên</th>
                    <td style="padding:10px;border-bottom:1px solid #e5eaf2">{{ $contact->name }}</td>
                </tr>
                <tr>
                    <th style="padding:10px;border-bottom:1px solid #e5eaf2;text-align:left">Số điện thoại</th>
                    <td style="padding:10px;border-bottom:1px solid #e5eaf2">{{ $contact->phone }}</td>
                </tr>
                <tr>
                    <th style="padding:10px;border-bottom:1px solid #e5eaf2;text-align:left">Email</th>
                    <td style="padding:10px;border-bottom:1px solid #e5eaf2">{{ $contact->email }}</td>
                </tr>
                <tr>
                    <th style="padding:10px;border-bottom:1px solid #e5eaf2;text-align:left">Hạng mục quan tâm</th>
                    <td style="padding:10px;border-bottom:1px solid #e5eaf2">{{ $contact->category_id ?: 'Chưa cung cấp' }}</td>
                </tr>
                <tr>
                    <th style="padding:10px;border-bottom:1px solid #e5eaf2;text-align:left">Dự án cần báo giá</th>
                    <td style="padding:10px;border-bottom:1px solid #e5eaf2">{{ $contact->project_name ?: 'Chưa cung cấp' }}</td>
                </tr>
                <tr>
                    <th style="padding:10px;border-bottom:1px solid #e5eaf2;text-align:left;vertical-align:top">Sản phẩm yêu cầu báo giá</th>
                    <td style="padding:10px;border-bottom:1px solid #e5eaf2;white-space:pre-wrap">{{ $contact->product ?: 'Chưa cung cấp' }}</td>
                </tr>
                <tr>
                    <th style="padding:10px;text-align:left;vertical-align:top">Mô tả nhu cầu</th>
                    <td style="padding:10px;white-space:pre-wrap">{{ $contact->message }}</td>
                </tr>
            </tbody>
        </table>

        <p style="margin:24px 0 0;color:#7e899f;font-size:12px">
            Có thể trả lời trực tiếp email này để liên hệ khách hàng.
        </p>
    </main>
</body>
</html>
