# API đơn hàng cho n8n (đọc, cập nhật) và webhook

Dùng để n8n trên máy shop kiểm tra đơn mới định kỳ và báo về điện thoại, và (với token loại "đọc + cập nhật") cập nhật trạng thái giao, thanh toán và vận đơn. Ngoài ra website có thể tự gửi webhook sang `hook.laptopplus.vn` khi có đơn mới, đổi trạng thái, thanh toán hoặc liên hệ mới (mục 6).

Mục lục: 1. Tạo token · 2. Đọc đơn · 3. Gợi ý n8n · 4. Route `/api/v1/orders/{id}` · 5. Cập nhật đơn · 6. Webhook · 7. Tự tạo hoá đơn/phiếu thu KIOT · 8. Sửa giá sản phẩm WEB-NEW qua API

## 1. Tạo token

1. Vào Admin → Cài đặt → **API token**.
2. Đặt tên (ví dụ `n8n-don-hang`), chọn loại **Đơn hàng – chỉ đọc**, hoặc **Đơn hàng – đọc + cập nhật (trạng thái giao, thanh toán, vận đơn)** nếu n8n cần cập nhật đơn (mục 5), bấm Tạo.
3. Sao chép token ngay, vì token chỉ hiện một lần. Lưu token trong Credentials của n8n, đừng gửi qua chat.

Lưu ý:
- Chỉ tài khoản có quyền **xem đơn hàng** mới tạo được token Đơn hàng. Token **đọc + cập nhật** cần thêm quyền **sửa đơn hàng** (super-admin luôn được).
- Nếu người tạo token bị bỏ quyền xem (hoặc sửa) đơn hàng, token sẽ bị từ chối (403).
- Token chỉ đọc không gọi được các API cập nhật (403).
- Token Đơn hàng không đọc hay sửa được bài viết và sản phẩm. Token Nội dung (ví dụ `n8n-noi-dung`) cũng không đọc được đơn hàng.
- Muốn thu hồi token thì bấm Thu hồi ở cùng trang. Token bị chặn ngay.

## 2. Gọi API

Gửi token trong header:
```
Authorization: Bearer <token>
Accept: application/json
```

### Danh sách đơn

`GET https://admin.laptopplus.vn/api/v1/admin-api/orders`

| Tham số | Ý nghĩa |
|---|---|
| `status` | Trạng thái đơn. Có thể ghi nhiều giá trị, cách nhau bằng dấu phẩy. Các giá trị: `pending` (chờ xử lý), `confirmed`, `processing`, `shipping`, `delivered`, `cancelled`. |
| `payment_status` | Trạng thái thanh toán, ví dụ `unpaid`, `deposited`, `paid`. |
| `created_since` | Lấy đơn đặt từ thời điểm này trở đi. Dạng ISO 8601, ví dụ `2026-11-09T08:00:00+07:00`. Nếu không ghi múi giờ thì tính theo giờ Việt Nam. |
| `created_until` | Lấy đơn đặt đến thời điểm này. |
| `after_id` | Chỉ lấy đơn có `id` lớn hơn số này, đơn cũ trước. Dùng tham số này để không báo trùng đơn (xem mục 3). |
| `search` | Tìm theo mã đơn, SĐT hoặc tên khách. |
| `per_page` | Số đơn mỗi trang, tối đa 100, mặc định 50. Chuyển trang bằng `page`. |

Khi không có `after_id`, đơn mới nhất đứng đầu.

Ví dụ: lấy đơn chờ xử lý đặt trong 10 phút gần đây.
```
GET /api/v1/admin-api/orders?status=pending&created_since=2026-11-09T08:00:00%2B07:00
```

### Một đơn

`GET /api/v1/admin-api/orders/{id}`

### Dữ liệu mỗi đơn

```json
{
  "id": 1532,
  "order_number": "DH202611090042",
  "customer_name": "Nguyễn Văn A",
  "customer_phone": "0912345678",
  "customer_email": "a@example.com",
  "shipping_address": "12 Láng Hạ, Đống Đa, Hà Nội",
  "notes": "Gọi trước khi giao",
  "items": [
    {"product_id": 88, "name": "Dell Latitude 7420", "variant": null, "sku": "D7420",
     "quantity": 1, "price": 9000000, "warranty_months": null, "warranty_price": 0, "total": 9000000}
  ],
  "subtotal": 9000000, "discount": 200000, "shipping_fee": 0, "total": 8800000,
  "status": "pending", "status_label": "Chờ xử lý",
  "payment_status": "unpaid", "payment_status_label": "Chưa thanh toán",
  "payment_method": "cod", "payment_option": "full", "deposit_amount": 0, "amount_paid": 0, "paid_at": null,
  "tracking_code": null, "shipping_carrier": null, "shipped_at": null, "delivered_at": null,
  "referral_code": null,
  "kiot_sync_status": "synced", "kiot_sync_status_label": "Đã vào KIOT", "kiot_order_code": "DH000055",
  "created_at": "2026-11-09T01:05:12+00:00",
  "updated_at": "2026-11-09T01:05:12+00:00",
  "admin_url": "https://admin.laptopplus.vn/admin/orders/1532"
}
```

- Thời gian trả về theo giờ UTC. Giờ Việt Nam = UTC + 7.
- Tiền tính bằng đồng (số nguyên).
- `admin_url` mở thẳng đơn trong trang admin, tiện để chèn vào tin nhắn báo.
- `tracking_code`, `shipping_carrier`: mã vận đơn và đơn vị vận chuyển, do nhân viên nhập ở trang đơn (khung "Vận chuyển") hoặc gửi qua API (mục 5).
- `paid_at`, `delivered_at`, `shipped_at`: thời điểm đã thanh toán đủ, đã giao, bắt đầu giao.
- Dữ liệu còn có nguồn đơn (`utm_*`, `gclid`, `fbclid`, `video_code`, `landing_page`), CTV (`affiliate_*`) và tình trạng KIOT (`kiot_sync_status`, `kiot_error`...).

Danh sách trả thêm `meta`: `current_page`, `last_page`, `per_page`, `total`.

### Mã lỗi

| Mã | Nghĩa |
|---|---|
| 401 | Thiếu token, hoặc token sai hay đã bị thu hồi. |
| 403 | Token không phải loại Đơn hàng, hoặc người tạo không còn quyền xem đơn. |
| 404 | Không có đơn với `id` này. |
| 422 | Tham số không hợp lệ, ví dụ `status` sai. |
| 429 | Gọi quá 120 lần mỗi phút. |

## 3. Gợi ý cấu hình n8n

1. **Schedule Trigger**: chạy mỗi 3–5 phút.
2. **HTTP Request**:
   - `GET .../orders?after_id={{ $getWorkflowStaticData('global').lastId || 0 }}&per_page=100`
   - Header `Authorization: Bearer …`, lấy từ Credentials.
3. Nếu `data` có đơn:
   - Gửi thông báo (Telegram, Zalo…) cho từng đơn, gồm mã đơn, tên, SĐT, sản phẩm, tổng tiền và `admin_url`.
   - Lưu `id` lớn nhất vào `lastId`.

Lần chạy đầu, đặt `lastId` bằng `id` của đơn mới nhất hiện có để không báo lại đơn cũ.

Dùng `after_id` thì không bị báo trùng hay bỏ sót đơn, kể cả khi n8n hay máy tính tắt một lúc.

## 4. Về route `/api/v1/orders/{id}`

Route này dành cho trang "Đơn hàng của tôi" trên website. Chỉ người đặt mới xem được đơn, bằng một trong hai cách:
- đăng nhập đúng tài khoản đã đặt đơn;
- gửi mã truy cập riêng của đơn (header `X-Order-Access-Token`), mã này chỉ trình duyệt của người đặt có.

Mọi yêu cầu khác đều nhận **404**, dù đơn có tồn tại hay không, nên không đoán được mã đơn nào có thật. `/api/v1/orders/count` báo "không tìm thấy" vì `count` bị hiểu là mã đơn, đây không phải lỗ hổng. Bên shop dùng API `admin-api/orders` ở trên thay cho route này.

## 5. Cập nhật đơn (token "Đơn hàng – đọc + cập nhật")

Ba API dưới đây dùng token loại `orders_write` (quyền `orders:read` + `orders:write`). Mọi lần gọi (thành công hay lỗi) được ghi vào nhật ký `admin_api_write_logs` (token, đơn, nội dung gửi, mã trả về, IP). Mọi thay đổi hiện trong **Lịch sử đơn hàng** ở trang admin, người thực hiện là `Token: <tên token>`, ví dụ `Token: n8n`.

Gửi kèm:
```
Authorization: Bearer <token>
Accept: application/json
Content-Type: application/json
Idempotency-Key: <chuỗi riêng cho mỗi thao tác, ví dụ UUID>   (bắt buộc với /payments, không bắt buộc với 2 API còn lại)
```

Thành công trả `{"data": <đơn>}`, cùng dạng `GET /orders/{id}` (có `tracking_code`, `shipping_carrier`). Lỗi trả `{"message": "...", "error_code": "..."}`; lỗi dữ liệu có thêm `errors`.

### 5.1. Đổi trạng thái giao

`PATCH /api/v1/admin-api/orders/{id}/status`

| Trường | Bắt buộc | Ý nghĩa |
|---|---|---|
| `order_status` | có | `confirmed`, `processing`, `shipping`, `delivered` hoặc `cancelled`. |
| `reason` | không | Lý do, tối đa 500 ký tự, ghi vào lịch sử đơn. |

- Chỉ chuyển tiếp: chờ xử lý → đã xác nhận → đang xử lý → đang giao → đã giao. Chuyển lùi (ví dụ đã giao → đã xác nhận) hoặc đổi đơn đã huỷ trả 422.
- Gửi đúng trạng thái hiện tại: trả 200, không làm gì.
- `cancelled` huỷ đơn giống nút Huỷ ở trang admin: đơn đã vào KIOT thì website huỷ phiếu đặt hàng bên KIOT trước. KIOT đã xuất hoá đơn hoặc không cho huỷ: 422 `ORDER_NOT_CANCELLABLE`. KIOT chưa trả lời: 202, đơn huỷ khi KIOT xác nhận (`kiot_sync_status` = `cancel_pending`). Đơn đã giao không huỷ được qua API.
- Chuyển `delivered` ghi thời điểm giao; nếu bật mục 7, KIOT tạo hoá đơn.

```bash
curl -X PATCH https://admin.laptopplus.vn/api/v1/admin-api/orders/1532/status \
  -H "Authorization: Bearer $TOKEN" -H "Accept: application/json" -H "Content-Type: application/json" \
  -d '{"order_status":"shipping","reason":"Đã giao cho GHN"}'
```

### 5.2. Ghi nhận tiền đã nhận

`POST /api/v1/admin-api/orders/{id}/payments` (bắt buộc `Idempotency-Key`)

| Trường | Bắt buộc | Ý nghĩa |
|---|---|---|
| `amount` | có | Số tiền nhận thêm, số nguyên đồng, > 0. |
| `paid_at` | không | Thời điểm nhận, ISO 8601, ví dụ `2026-11-16T09:30:00+07:00`. Mặc định là lúc gọi. |
| `method` | không | `bank_transfer`, `cash`, `cod`, `sepay` hoặc `other`. |
| `reference` | không | Mã giao dịch hoặc ghi chú, tối đa 100 ký tự. |

- Số tiền được **cộng** vào `amount_paid`. Đủ tổng tiền: `payment_status` = `paid` (ghi `paid_at`). Chưa đủ nhưng đạt tiền cọc (hoặc đơn không yêu cầu cọc): `deposited`.
- Không được vượt tổng tiền: 422 `OVERPAYMENT`, tin nhắn cho biết số còn phải thu.
- Đơn thanh toán SePay chỉ ghi nhận được sau khi KIOT đã nhận đơn: 422 `PAYMENT_NOT_ACCEPTED`. Đơn đã huỷ: 422 `ORDER_CANCELLED`. Đơn đã hoàn tiền: 422 `ORDER_REFUNDED`.
- Lịch sử đơn ghi, ví dụ: "Ghi nhận thanh toán 5.000.000đ (bank_transfer, ref FT123). Đã thu 5.000.000đ/9.000.000đ."

**Idempotency-Key**: mỗi khoản tiền một khoá riêng (ví dụ UUID, hoặc mã giao dịch ngân hàng), tối đa 100 ký tự.
- Gửi lại **cùng khoá, cùng nội dung** (ví dụ n8n thử lại khi mạng lỗi): nhận lại đúng kết quả lần đầu, kèm header `Idempotent-Replayed: true`, tiền không bị cộng hai lần.
- **Cùng khoá, khác nội dung**: 409 `IDEMPOTENCY_KEY_REUSED`.
- Hai yêu cầu cùng khoá chạy song song: yêu cầu sau nhận 409 `IDEMPOTENCY_IN_PROGRESS`, thử lại sau ít giây.
- Kết quả lỗi (ví dụ 422) cũng được lưu theo khoá: sửa dữ liệu thì dùng khoá mới.

```bash
curl -X POST https://admin.laptopplus.vn/api/v1/admin-api/orders/1532/payments \
  -H "Authorization: Bearer $TOKEN" -H "Accept: application/json" -H "Content-Type: application/json" \
  -H "Idempotency-Key: 6f0c1e7a-3b0e-4a51-9a55-0c2b5f9a1d10" \
  -d '{"amount":5000000,"method":"bank_transfer","reference":"FT2611160001","paid_at":"2026-11-16T09:30:00+07:00"}'
```

### 5.3. Vận đơn

`PATCH /api/v1/admin-api/orders/{id}/shipment`

| Trường | Bắt buộc | Ý nghĩa |
|---|---|---|
| `tracking_code` | không | Mã vận đơn, tối đa 100 ký tự. Gửi `null` hoặc `""` để xoá. |
| `shipping_carrier` | không | Đơn vị vận chuyển (GHN, Viettel Post...), tối đa 100 ký tự. |
| `delivered` | không | `true`: chuyển đơn sang "Đã giao hàng" (đơn đã huỷ thì 422). |

Phải gửi ít nhất một trường.

```bash
curl -X PATCH https://admin.laptopplus.vn/api/v1/admin-api/orders/1532/shipment \
  -H "Authorization: Bearer $TOKEN" -H "Accept: application/json" -H "Content-Type: application/json" \
  -d '{"shipping_carrier":"GHN","tracking_code":"GHN8A1B2C","delivered":true}'
```

### 5.4. Mã lỗi

| HTTP | `error_code` | Nghĩa |
|---|---|---|
| 401 | | Thiếu token, token sai hoặc đã thu hồi. |
| 403 | `FORBIDDEN` hoặc trống | Token không có quyền cập nhật đơn (ví dụ token chỉ đọc), hoặc người tạo không còn quyền xem/sửa đơn. |
| 404 | `ORDER_NOT_FOUND` | Không có đơn với `id` này. |
| 409 | `IDEMPOTENCY_KEY_REUSED`, `IDEMPOTENCY_IN_PROGRESS` | Xem 5.2. |
| 422 | `VALIDATION_FAILED` | Dữ liệu sai, chi tiết trong `errors`. |
| 422 | `IDEMPOTENCY_KEY_REQUIRED`, `IDEMPOTENCY_KEY_INVALID` | Thiếu `Idempotency-Key` khi ghi thanh toán, hoặc khoá dài quá 100 ký tự. |
| 422 | `INVALID_TRANSITION`, `ORDER_CANCELLED` | Chuyển trạng thái không hợp lệ, đơn đã huỷ. |
| 422 | `ORDER_NOT_CANCELLABLE`, `KIOT_<mã>` | KIOT không cho huỷ, hoặc chưa huỷ được bên KIOT (kèm mã lỗi KIOT). |
| 422 | `OVERPAYMENT`, `PAYMENT_NOT_ACCEPTED`, `ORDER_REFUNDED` | Xem 5.2. |
| 429 | | Gọi quá 120 lần mỗi phút. |

## 6. Webhook gửi sang hook.laptopplus.vn

Admin → Cài đặt → **Webhook** (cần quyền sửa cài đặt):

1. Nhập URL nhận (chỉ `https://`), chọn sự kiện, bấm Lưu.
2. Bấm **Tạo secret**. Secret chỉ hiện một lần, lưu vào bên nhận (Credentials của n8n). Website lưu secret đã mã hoá. **Đổi secret** tạo secret mới, chữ ký bằng secret cũ hết hiệu lực ngay.
3. Bấm **Gửi thử** để gửi sự kiện `ping`.
4. Bật "Bật webhook" và Lưu. Mặc định webhook tắt.

Bảng bên phải liệt kê 50 lần gửi gần nhất (sự kiện, trạng thái, số lần thử, mã HTTP, lỗi gần nhất, thời gian). Dòng lỗi có nút **Gửi lại**.

### Sự kiện

| `event` | Khi nào | `data` |
|---|---|---|
| `order.created` | Khách đặt đơn trên website | Đơn, cùng dạng `GET /orders/{id}` |
| `order.status_changed` | Trạng thái đơn đổi, từ bất kỳ đâu (admin, API, KIOT huỷ, SePay) | Đơn + `previous.status` |
| `order.payment_changed` | `payment_status` hoặc `amount_paid` đổi (admin, API, SePay) | Đơn + `previous.payment_status`, `previous.amount_paid` |
| `contact.created` | Khách gửi form Liên hệ (`/lien-he`) | `id, name, phone, email, subject, message, page_url, status, created_at` |
| `ping` | Nút Gửi thử | `{"message": "Laptop Plus webhook test"}` |

### Định dạng

`POST <URL>` với body JSON:
```json
{
  "id": "0b8f3c2e-4c1a-4f7e-9a3d-2f1e5c6b7a80",
  "event": "order.status_changed",
  "created_at": "2026-11-16T02:30:00+00:00",
  "data": { "id": 1532, "order_number": "DH202611090042", "status": "shipping", "previous": { "status": "confirmed" } }
}
```
(`data` của đơn có đủ các trường như mục 2, ở đây rút gọn.)

Header:
```
Content-Type: application/json
X-Laptopplus-Event: order.status_changed
X-Laptopplus-Delivery: 0b8f3c2e-4c1a-4f7e-9a3d-2f1e5c6b7a80   (bằng "id", giữ nguyên khi gửi lại)
X-Laptopplus-Timestamp: 1794796200                          (giây Unix lúc gửi)
X-Laptopplus-Signature: sha256=<hex HMAC-SHA256(secret, timestamp + "." + body)>
```

Bên nhận nên: kiểm tra chữ ký trên **body thô** (chưa parse), từ chối nếu timestamp lệch quá 5 phút, và bỏ qua `X-Laptopplus-Delivery` đã xử lý (một sự kiện có thể đến hơn một lần khi gửi lại).

### Gửi lại

- Bên nhận trả 2xx trong 10 giây là thành công.
- Lỗi (mã khác 2xx, quá 10 giây, không kết nối được): gửi lại sau 1 phút, 5 phút, 30 phút, 2 giờ, 6 giờ. Sau 6 lần lỗi, trạng thái là "Ngừng gửi"; bấm **Gửi lại** ở trang Webhook để gửi tiếp.
- Webhook gửi sau khi đơn đã lưu xong. Webhook lỗi không làm hỏng việc đặt đơn hay đổi trạng thái.
- Việc gửi lại chạy bằng lệnh `php artisan webhooks:retry` mỗi phút (container `scheduler`), việc gửi chạy trên hàng đợi (container `queue`).

### Kiểm tra chữ ký: n8n (Code node, Node.js)

Trong Webhook node của n8n, bật **Raw Body** (Options → Raw Body) để có đúng body gốc.

```js
const crypto = require('crypto');
const secret = $env.LAPTOPPLUS_WEBHOOK_SECRET; // hoặc lấy từ Credentials
const item = $input.first();
const headers = item.json.headers;
const raw = Buffer.from(item.binary.data.data, 'base64').toString('utf8'); // Raw Body
const timestamp = headers['x-laptopplus-timestamp'];
const expected = 'sha256=' + crypto.createHmac('sha256', secret).update(`${timestamp}.${raw}`).digest('hex');
const given = headers['x-laptopplus-signature'] || '';
const valid = given.length === expected.length && crypto.timingSafeEqual(Buffer.from(given), Buffer.from(expected));
if (!valid || Math.abs(Date.now() / 1000 - Number(timestamp)) > 300) {
  throw new Error('Chữ ký webhook không hợp lệ');
}
return [{ json: JSON.parse(raw) }];
```

### Kiểm tra chữ ký: PHP

```php
$raw = file_get_contents('php://input');
$timestamp = $_SERVER['HTTP_X_LAPTOPPLUS_TIMESTAMP'] ?? '';
$given = $_SERVER['HTTP_X_LAPTOPPLUS_SIGNATURE'] ?? '';
$expected = 'sha256='.hash_hmac('sha256', $timestamp.'.'.$raw, getenv('LAPTOPPLUS_WEBHOOK_SECRET'));
if (! hash_equals($expected, $given) || abs(time() - (int) $timestamp) > 300) {
    http_response_code(401);
    exit;
}
$event = json_decode($raw, true);
http_response_code(200);
```

## 7. Tự tạo hoá đơn và phiếu thu KIOT

Admin → **Tích hợp KIOT** → khung "Hoá đơn và phiếu thu KIOT" → **Tự tạo hoá đơn KIOT khi đơn web Đã giao và phiếu thu khi Đã thanh toán**. Mặc định tắt.

Khi bật, với đơn **đã vào KIOT** (`kiot_sync_status` = `synced`) và khi "Đồng bộ đơn hàng" đang bật:
- Mỗi khoản tiền ghi nhận trên website (nhân viên bấm Đã thanh toán/Đã đặt cọc, API mục 5.2, SePay) được gửi sang KIOT thành **phiếu thu**, chỉ phần tiền mới, không gửi trùng.
- Khi đơn chuyển **Đã giao hàng** (trang admin, API, bất kỳ đâu), KIOT tạo **hoá đơn** từ phiếu đặt hàng, một lần. Mã vận đơn và đơn vị vận chuyển gửi kèm.
- Nếu tiền và giao hàng xảy ra cùng lúc, phiếu thu được gửi trước để tiền cọc được trừ khi tạo hoá đơn.
- Đơn chưa vào KIOT (đang gửi, KIOT từ chối...) thì chưa gửi gì. Khi đơn được gửi lại và vào KIOT, các khoản tiền và trạng thái đã giao còn thiếu được gửi tiếp.
- Kết quả ghi vào lịch sử đơn, ví dụ "KIOT đã tạo hoá đơn HD000123.", "KIOT đã ghi phiếu thu PT000777 5.000.000đ.".
- KIOT từ chối (chưa chọn Serial/IMEI, chưa có giá vốn, vượt số phải thu...) thì ghi lý do, ví dụ "KIOT chưa tạo hoá đơn: cần chọn Serial/IMEI trong KIOT (SERIAL_NOT_ALLOCATED: ...)", và không tự gửi lại. Sửa trong KIOT xong, bấm **Retry** ở bảng lỗi gần đây trang Tích hợp KIOT. Lỗi kết nối được tự gửi lại như đơn hàng.
- Trạng thái đồng bộ đơn (`kiot_sync_status`) không đổi vì hoá đơn hay phiếu thu.

## 8. Sửa giá sản phẩm WEB-NEW qua API (token Nội dung)

`PATCH /api/v1/admin-api/products/{id}` nhận `price`, `sale_price` chỉ với sản phẩm có `price_editable: true`. Mỗi sản phẩm (trong `GET /products`, `/products/{id}`) có thêm:

| Trường | Giá trị |
|---|---|
| `price_locked_reason` | `null` (sửa được), `sku_not_web_new` (SKU không bắt đầu bằng `WEB-NEW-`), `kiot_product` (sản phẩm đã liên kết KIOT: nguồn tồn kho hoặc nhà cung cấp là KIOT, giá lấy từ KIOT), `has_variants` (sản phẩm có biến thể). |
| `price_locked_message` | Lý do bằng tiếng Việt. |

Gửi giá cho sản phẩm bị khoá trả 422, tin nhắn nêu rõ lý do và `price_locked_reason`.

Giá là số nguyên đồng. API cũng nhận chuỗi `"12.000.000"`, `"12,000,000"`, `"12000000đ"`, `"12 000 000 VNĐ"` (đổi thành 12000000). Giá khuyến mãi phải nhỏ hơn giá bán.
