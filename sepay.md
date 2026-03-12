# Tích hợp cổng thanh toán SePay vào hệ thống Laravel + Vue

Tài liệu này mô tả cách tích hợp cổng thanh toán SePay (quét mã QR chuyển khoản ngân hàng) vào backend **Laravel** và frontend **Vue**, đồng thời cấu hình **IPN** để **tự động cập nhật trạng thái đơn hàng** khi thanh toán thành công.

---

## 1. Tổng quan luồng hoạt động

1. Khách hàng tạo đơn hàng trên website (frontend Vue).
2. Frontend gửi dữ liệu đơn hàng lên API Laravel.
3. Laravel:

   * Lưu đơn hàng (trạng thái `pending`).
   * Chuẩn bị form/redirect sang cổng thanh toán SePay.
4. Trình duyệt được chuyển sang trang thanh toán của SePay.
5. Khách thanh toán thành công/thất bại/hủy:

   * SePay **redirect** người dùng về các URL:

     * `success_url` (thành công),
     * `error_url` (thất bại),
     * `cancel_url` (hủy).
   * Đồng thời, SePay **gửi IPN** (Instant Payment Notification) đến endpoint IPN của bạn, kèm JSON chi tiết giao dịch.
6. IPN nhận JSON, xác định đơn hàng tương ứng và cập nhật trạng thái (ví dụ `paid`).
7. Frontend hiển thị trang kết quả (success / error / cancel) dựa trên route Laravel hoặc Vue.

---

## 2. Chuẩn bị tài khoản SePay & thông tin tích hợp

### 2.1. Đăng ký và kích hoạt cổng thanh toán

1. Đăng ký tài khoản tại: `https://my.sepay.vn/register`.
2. Sau khi có tài khoản, truy cập mục **Cổng thanh toán**:

   * Vào `CỔNG THANH TOÁN` → `Đăng ký`.
   * Ở màn hình **Phương thức thanh toán**, chọn **Bắt đầu ngay**.
3. Chọn bắt đầu với **Sandbox** để test, sau đó bấm **Bắt đầu hướng dẫn tích hợp**.
4. Ở bước chọn phương thức tích hợp, chọn **SDK PHP** (hoặc API thuần nếu muốn).
5. Màn hình “Thông tin tích hợp” hiển thị:

   * `MERCHANT ID`
   * `SECRET KEY`

> **Quan trọng:** Lưu lại `MERCHANT ID` và `SECRET KEY` cho môi trường Sandbox. Khi Go Live, bạn sẽ được cấp `MERCHANT ID` & `SECRET KEY` Production riêng.

---

## 3. Cấu trúc tổng quan trong Laravel + Vue

### 3.1 Backend (Laravel)

* **Model**: `Order`
* **Bảng**: `orders`
* **Các route chính**:

  * `POST /api/orders` – tạo đơn hàng và chuẩn bị bước thanh toán.
  * `GET  /payment/checkout/{invoice}` – render form SePay & auto submit.
  * `GET  /payment/success` – trang thành công.
  * `GET  /payment/error` – trang thất bại.
  * `GET  /payment/cancel` – trang hủy.
  * `POST /payment/ipn` – endpoint IPN nhận JSON từ SePay.

### 3.2 Frontend (Vue)

* Giao diện giỏ hàng / thanh toán gửi API tạo đơn hàng.
* Redirect sang trang checkout.
* Sau thanh toán, SePay trả về success/error/cancel.

---

## 4. Cấu hình Laravel

### 4.1 Biến môi trường `.env`

```env
SEPAY_MERCHANT_ID=your_merchant_id_sandbox
SEPAY_SECRET_KEY=your_secret_key_sandbox
SEPAY_ENDPOINT_SANDBOX="<<Endpoint sandbox từ màn hình thông tin tích hợp>>"
SEPAY_ENDPOINT_PRODUCTION="https://pay.sepay.vn/v1/checkout/init"
SEPAY_ENV=sandbox
```

### 4.2 `config/services.php`

```php
' sepay' => [
    'merchant_id' => env('SEPAY_MERCHANT_ID'),
    'secret_key'  => env('SEPAY_SECRET_KEY'),
    'env'         => env('SEPAY_ENV', 'sandbox'),
    'endpoints'   => [
        'sandbox'    => env('SEPAY_ENDPOINT_SANDBOX'),
        'production' => env('SEPAY_ENDPOINT_PRODUCTION'),
    ],
],
```

---

## 5. Thiết kế bảng `orders`

### Migration

```php
Schema::create('orders', function (Blueprint $table) {
    $table->id();
    $table->string('invoice_number')->unique();
    $table->unsignedBigInteger('user_id')->nullable();
    $table->decimal('amount', 15, 2);
    $table->string('currency')->default('VND');
    $table->string('status')->default('pending');
    $table->string('description')->nullable();
    $table->json('meta')->nullable();
    $table->timestamps();
});
```

### Model

```php
class Order extends Model
{
    protected $fillable = [
        'invoice_number', 'user_id', 'amount', 'currency', 'status', 'description', 'meta'
    ];

    protected $casts = ['meta' => 'array'];
}
```

---

## 6. API tạo đơn hàng (Laravel)

### Route

```php
Route::post('/api/orders', [OrderController::class, 'store']);
```

### Controller

```php
$invoiceNumber = 'INV-' . now()->timestamp . '-' . Str::upper(Str::random(6));

$order = Order::create([
    'invoice_number' => $invoiceNumber,
    'amount' => $validated['amount'],
    'currency' => 'VND',
    'status' => 'pending',
]);
```

---

## 7. Tạo form thanh toán và chuyển sang SePay

### Route checkout

```php
Route::get('/payment/checkout/{invoice}', [PaymentController::class, 'checkout'])->name('payment.checkout');
```

### Controller

```php
return view('payment.checkout', [
    'endpoint' => $endpoint,
    'merchant_id' => $config['merchant_id'],
    'order' => $order,
    'success_url' => route('payment.success'),
    'error_url' => route('payment.error'),
    'cancel_url' => route('payment.cancel'),
    'ipn_url '=> route('payment.ipn')
]);
```

### View Blade

```html
<form id="sepay-form" method="POST" action="{{ $endpoint }}">
    <input type="hidden" name="merchant_id" value="{{ $merchant_id }}">
    <input type="hidden" name="order_invoice_number" value="{{ $order->invoice_number }}">
    <input type="hidden" name="order_amount" value="{{ $order->amount }}">
    <input type="hidden" name="success_url" value="{{ $success_url }}">
    <input type="hidden" name="error_url" value="{{ $error_url }}">
    <input type="hidden" name="cancel_url" value="{{ $cancel_url }}">
    <input type="hidden" name="ipn_url" value="{{ $ipn_url }}">
</form>
```

---

## 8. Callback URL: success / error / cancel

### Routes

```php
Route::get('/payment/success', fn() => view('payment.success'))->name('payment.success');
Route::get('/payment/error', fn() => view('payment.error'))->name('payment.error');
Route::get('/payment/cancel', fn() => view('payment.cancel'))->name('payment.cancel');
```

---

## 9. IPN – Tự động xác nhận giao dịch

### Route IPN

```php
Route::post('/payment/ipn', [PaymentController::class, 'ipn'])->name('payment.ipn');
```

### Controller xử lý IPN

```php
$data = $request->json()->all();

if ($data['notification_type'] === 'ORDER_PAID') {
    $order = Order::where('invoice_number', $data['order']['order_invoice_number'])->first();
    if ($order) {
        $order->status = 'paid';
        $order->save();
    }
}

return response()->json(['success' => true]);
```

---

## 10. Tích hợp Vue

### Gọi API tạo đơn

```js
const res = await axios.post('/api/orders', {
  amount: this.amount,
  description: this.description,
});

window.location.href = res.data.checkout_url;
```

---

## 11. Go Live (Production)

* Cập nhật `.env`:

```env
SEPAY_MERCHANT_ID=merchant_id_production
SEPAY_SECRET_KEY=secret_key_production
SEPAY_ENV=production
```

* Cập nhật IPN URL trên trang quản trị SePay.

---

## 12. Ghi chú cho dev

* Không hard-code key.
* Cần đọc docs SDK PHP chính thức của SePay để tạo signature.
* Log payload IPN để debug.
* Kiểm thử bằng Sandbox trước khi Go Live.

---

**Hệ thống thanh toán SePay đã sẵn sàng để tích hợp Laravel + Vue với IPN tự động cập nhật trạng thái giao dịch.**
