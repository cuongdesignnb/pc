<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Đang chuyển hướng đến trang thanh toán...</title>
    <style>
        body {
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
            margin: 0;
            background: #f3f4f6;
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
        }
        .loading {
            text-align: center;
        }
        .spinner {
            border: 4px solid #e5e7eb;
            border-top: 4px solid #3b82f6;
            border-radius: 50%;
            width: 48px;
            height: 48px;
            animation: spin 1s linear infinite;
            margin: 0 auto 16px;
        }
        @keyframes spin {
            to { transform: rotate(360deg); }
        }
        p { color: #6b7280; font-size: 16px; }
    </style>
</head>
<body>
    <div class="loading">
        <div class="spinner"></div>
        <p>Đang chuyển hướng đến cổng thanh toán SePay...</p>
        <p style="font-size: 14px;">Đơn hàng: <strong>{{ $order->order_number }}</strong> — {{ number_format($order->total, 0, ',', '.') }}₫</p>
    </div>

    <form id="sepay-form" method="POST" action="{{ $endpoint }}" style="display: none;">
        <input type="hidden" name="merchant_id" value="{{ $merchant_id }}">
        <input type="hidden" name="order_invoice_number" value="{{ $order->order_number }}">
        <input type="hidden" name="order_amount" value="{{ (int) $order->total }}">
        <input type="hidden" name="success_url" value="{{ $success_url }}">
        <input type="hidden" name="error_url" value="{{ $error_url }}">
        <input type="hidden" name="cancel_url" value="{{ $cancel_url }}">
        <input type="hidden" name="ipn_url" value="{{ $ipn_url }}">
    </form>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            document.getElementById('sepay-form').submit();
        });
    </script>
</body>
</html>
