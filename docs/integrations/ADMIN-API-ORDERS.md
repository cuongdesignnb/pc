# API đơn hàng (chỉ đọc) cho n8n

Dùng để n8n trên máy shop kiểm tra đơn mới định kỳ và báo về điện thoại. API chỉ đọc, không sửa, huỷ hay xoá được đơn.

## 1. Tạo token

1. Vào Admin → Cài đặt → **API token**.
2. Đặt tên (ví dụ `n8n-don-hang`), chọn loại **Đơn hàng – chỉ đọc**, bấm Tạo.
3. Sao chép token ngay, vì token chỉ hiện một lần. Lưu token trong Credentials của n8n, đừng gửi qua chat.

Lưu ý:
- Chỉ tài khoản có quyền **xem đơn hàng** mới tạo được loại token này.
- Nếu người tạo token bị bỏ quyền xem đơn hàng, token sẽ bị từ chối (403).
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
  "payment_status": "unpaid", "payment_method": "cod", "payment_option": "full", "amount_paid": 0,
  "referral_code": null,
  "created_at": "2026-11-09T01:05:12+00:00",
  "updated_at": "2026-11-09T01:05:12+00:00",
  "admin_url": "https://admin.laptopplus.vn/admin/orders/1532"
}
```

- Thời gian trả về theo giờ UTC. Giờ Việt Nam = UTC + 7.
- Tiền tính bằng đồng (số nguyên).
- `admin_url` mở thẳng đơn trong trang admin, tiện để chèn vào tin nhắn báo.

Danh sách trả thêm `meta`: `current_page`, `last_page`, `per_page`, `total`.

### Mã lỗi

| Mã | Nghĩa |
|---|---|
| 401 | Thiếu token, hoặc token sai hay đã bị thu hồi. |
| 403 | Token không phải loại Đơn hàng, hoặc người tạo không còn quyền xem đơn. |
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
