<!doctype html>
<html lang="vi">
<body style="margin:0;background:#f4f7fb;color:#172554;font-family:Arial,sans-serif">
  <main style="max-width:680px;margin:24px auto;background:#fff;border-radius:12px;overflow:hidden;border:1px solid #dbeafe">
    <header style="padding:24px;background:#0b55bd;color:#fff">
      <p style="margin:0 0 6px;font-size:12px;font-weight:700;text-transform:uppercase;letter-spacing:.08em">{{ $site_name }}</p>
      <h1 style="margin:0;font-size:24px">Có đơn hàng mới {{ $order->order_number }}</h1>
    </header>
    <section style="padding:24px">
      <p style="margin-top:0">Khách hàng <strong>{{ $order->shipping_name }}</strong> vừa đặt hàng lúc {{ optional($order->created_at)->format('H:i d/m/Y') }}.</p>
      <table style="width:100%;border-collapse:collapse;font-size:14px">
        <tr><td style="padding:7px 0;color:#64748b">Điện thoại</td><td style="padding:7px 0;text-align:right;font-weight:600">{{ $order->shipping_phone }}</td></tr>
        <tr><td style="padding:7px 0;color:#64748b">Email</td><td style="padding:7px 0;text-align:right;font-weight:600">{{ $order->customer_email }}</td></tr>
        <tr><td style="padding:7px 0;color:#64748b">Địa chỉ</td><td style="padding:7px 0;text-align:right;font-weight:600">{{ implode(', ', array_filter([$order->shipping_address, $order->shipping_ward, $order->shipping_district, $order->shipping_city])) }}</td></tr>
        <tr><td style="padding:7px 0;color:#64748b">Thanh toán</td><td style="padding:7px 0;text-align:right;font-weight:600">{{ strtoupper($order->payment_method) }}</td></tr>
      </table>
      <h2 style="margin:24px 0 10px;font-size:17px">Sản phẩm</h2>
      <table style="width:100%;border-collapse:collapse;font-size:14px">
        @foreach ($order->items as $item)
          <tr>
            <td style="padding:10px 0;border-top:1px solid #e2e8f0">
              <strong>{{ $item->product_name }}</strong>
              @if ($item->variant_name)<br><span style="color:#64748b">{{ $item->variant_name }}</span>@endif
            </td>
            <td style="padding:10px 0;border-top:1px solid #e2e8f0;text-align:center">×{{ $item->quantity }}</td>
            <td style="padding:10px 0;border-top:1px solid #e2e8f0;text-align:right;font-weight:700">{{ number_format((float) $item->total, 0, ',', '.') }}đ</td>
          </tr>
        @endforeach
      </table>
      <p style="margin:20px 0 0;text-align:right;font-size:18px">Tổng cộng: <strong style="color:#dc2626">{{ number_format((float) $order->total, 0, ',', '.') }}đ</strong></p>
      <p style="margin:24px 0 0"><a href="{{ url('/admin/orders/'.$order->id) }}" style="display:inline-block;border-radius:6px;background:#1264d8;color:#fff;padding:11px 16px;text-decoration:none;font-weight:700">Mở đơn hàng trong quản trị</a></p>
    </section>
  </main>
</body>
</html>
