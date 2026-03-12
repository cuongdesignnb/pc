<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Hủy thanh toán - PC Shop</title>
    <style>
        body {
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
            margin: 0;
            background: #fffbeb;
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
        }
        .container {
            text-align: center;
            max-width: 480px;
            padding: 40px 20px;
        }
        .icon { font-size: 80px; margin-bottom: 16px; }
        h1 { color: #d97706; margin-bottom: 8px; }
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
        .btn-outline {
            background: transparent;
            color: #3b82f6;
            border: 2px solid #3b82f6;
        }
        .btn-outline:hover { background: #eff6ff; }
    </style>
</head>
<body>
    <div class="container">
        <div class="icon">⚠️</div>
        <h1>Đã hủy thanh toán</h1>
        @if($order_number)
            <p>Đơn hàng: <strong>{{ $order_number }}</strong></p>
        @endif
        <p>Bạn đã hủy thanh toán. Đơn hàng vẫn được giữ nguyên, bạn có thể thanh toán lại bất cứ lúc nào.</p>
        <div>
            <a href="{{ config('app.frontend_url', 'http://localhost:8902') }}/checkout" class="btn">Thanh toán lại</a>
            <a href="{{ config('app.frontend_url', 'http://localhost:8902') }}/products" class="btn btn-outline">Tiếp tục mua sắm</a>
        </div>
    </div>
</body>
</html>
