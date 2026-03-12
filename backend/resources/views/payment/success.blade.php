<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Thanh toán thành công - PC Shop</title>
    <style>
        body {
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
            margin: 0;
            background: #f0fdf4;
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
        }
        .container {
            text-align: center;
            max-width: 480px;
            padding: 40px 20px;
        }
        .icon { font-size: 80px; margin-bottom: 16px; }
        h1 { color: #16a34a; margin-bottom: 8px; }
        p { color: #6b7280; margin-bottom: 24px; }
        .order-number { font-size: 20px; font-weight: bold; color: #1f2937; }
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
        <div class="icon">✅</div>
        <h1>Thanh toán thành công!</h1>
        @if($order_number)
            <p class="order-number">Đơn hàng: {{ $order_number }}</p>
        @endif
        <p>Cảm ơn bạn đã thanh toán. Đơn hàng của bạn đã được xác nhận.</p>
        <div>
            <a href="{{ config('app.frontend_url', 'http://localhost:8902') }}/products" class="btn btn-outline">Tiếp tục mua sắm</a>
            @if($order_number)
                <a href="{{ config('app.frontend_url', 'http://localhost:8902') }}/account/orders" class="btn">Theo dõi đơn hàng</a>
            @endif
        </div>

        <script>
            // Auto redirect to frontend after 5 seconds
            @if($order_number)
            setTimeout(function() {
                window.location.href = '{{ config("app.frontend_url", "http://localhost:8902") }}/orders/{{ $order_number }}/success';
            }, 5000);
            @endif
        </script>
    </div>
</body>
</html>
