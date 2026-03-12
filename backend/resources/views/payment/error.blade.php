<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Thanh toán thất bại - PC Shop</title>
    <style>
        body {
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
            margin: 0;
            background: #fef2f2;
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
        }
        .container {
            text-align: center;
            max-width: 480px;
            padding: 40px 20px;
        }
        .icon { font-size: 80px; margin-bottom: 16px; }
        h1 { color: #dc2626; margin-bottom: 8px; }
        p { color: #6b7280; margin-bottom: 24px; }
        .btn {
            display: inline-block;
            padding: 12px 32px;
            background: #3b82f6;
            color: white;
            text-decoration: none;
            border-radius: 8px;
            font-weight: 600;
            margin: 8px;
        }
        .btn:hover { background: #2563eb; }
    </style>
</head>
<body>
    <div class="container">
        <div class="icon">❌</div>
        <h1>Thanh toán thất bại</h1>
        @if($order_number)
            <p>Đơn hàng: <strong>{{ $order_number }}</strong></p>
        @endif
        <p>Đã có lỗi xảy ra trong quá trình thanh toán. Vui lòng thử lại hoặc chọn phương thức thanh toán khác.</p>
        <div>
            <a href="{{ config('app.frontend_url', 'http://localhost:8902') }}/checkout" class="btn">Thử thanh toán lại</a>
        </div>
    </div>
</body>
</html>
