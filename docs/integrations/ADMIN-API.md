# Admin API – tài liệu đầy đủ

Tài liệu dành cho chủ shop và lập trình viên tích hợp (n8n, công cụ viết bài, AI agent, KIOT, báo cáo). Đây là bản tham chiếu đầy đủ cho cả 19 đường dẫn. Phần đơn hàng có thêm ví dụ dài, hướng dẫn n8n và webhook trong [ADMIN-API-ORDERS.md](ADMIN-API-ORDERS.md).

**Địa chỉ gốc:** `https://admin.laptopplus.vn/api/v1/admin-api`

Mục lục: 1. Tổng hợp 19 đường dẫn · 2. Xác thực · 3. Giới hạn và quy ước chung · 4. Sản phẩm · 5. Danh mục sản phẩm · 6. Danh mục bài viết · 7. Bài viết · 8. Thư viện ảnh/file · 9. Đơn hàng – đọc · 10. Đơn hàng – cập nhật (có **Đánh dấu đã thanh toán**) · 11. Idempotency-Key · 12. Bảng mã lỗi

---

## 1. Tổng hợp 19 đường dẫn

| Đường dẫn (sau `/api/v1/admin-api`) | Phương thức | Quyền | Mục đích |
|---|---|---|---|
| `/products` | GET | `content:read` | Danh sách sản phẩm (phân trang, tìm kiếm) |
| `/products/{id}` | GET | `content:read` | Chi tiết một sản phẩm |
| `/products/{id}` | PATCH | `content:write` | Sửa SEO, mô tả, thông số, alt ảnh, link mua ngoài; giá chỉ với SP `WEB-NEW-` |
| `/categories` | GET | `content:read` | Toàn bộ danh mục sản phẩm |
| `/categories` | POST | `content:write` | Tạo danh mục sản phẩm |
| `/categories/{id}` | PATCH | `content:write` | Sửa danh mục (không đổi slug) |
| `/post-categories` | GET | `content:read` | Toàn bộ danh mục bài viết |
| `/posts` | GET | `content:read` | Danh sách bài viết (không kèm nội dung) |
| `/posts/{id}` | GET | `content:read` | Chi tiết bài viết (kèm `body`) |
| `/posts` | POST | `content:write` | Tạo bài viết |
| `/posts/{id}` | PATCH | `content:write` | Sửa bài viết (không đổi slug) |
| `/media` | GET | `content:read` | Danh sách ảnh/file trong thư viện |
| `/media` | POST | `content:write` | Tải ảnh/file lên (multipart) |
| `/media/{id}` | PATCH | `content:write` | Sửa tên, alt, chú thích của ảnh/file |
| `/orders` | GET | `orders:read` | Danh sách đơn hàng |
| `/orders/{id}` | GET | `orders:read` | Chi tiết một đơn |
| `/orders/{id}/status` | PATCH | `orders:write` | Đổi trạng thái giao (chỉ chuyển tiếp, hoặc huỷ) |
| `/orders/{id}/payments` | POST | `orders:write` | **Đánh dấu đã thanh toán** / ghi nhận tiền đã nhận |
| `/orders/{id}/shipment` | PATCH | `orders:write` | Mã vận đơn, đơn vị vận chuyển, đã giao |

`{id}` luôn là số `id` (không phải slug hay mã đơn).

---

## 2. Xác thực

### 2.1. Header

Mọi yêu cầu gửi kèm:
```
Authorization: Bearer <TOKEN>
Accept: application/json
```
Gửi JSON thì thêm `Content-Type: application/json`. **Luôn gửi `Accept: application/json`**: nếu thiếu, một số lỗi (và `POST /media`) trả về chuyển hướng HTML thay vì JSON.

### 2.2. Tạo token trong admin

1. Đăng nhập admin bằng tài khoản có quyền **sửa cài đặt** (`settings.edit`).
2. Vào **Admin → Cài đặt → API token** (`/admin/api-tokens`).
3. Nhập:
   - **Tên**: tối đa 100 ký tự, **không được có dấu hai chấm** (`:`). Ví dụ `n8n-don-hang`.
   - **Loại** (xem bảng 2.3).
   - **Hết hạn sau (ngày)**: không bắt buộc, 1–3650. Bỏ trống = không hết hạn.
4. Bấm Tạo, **sao chép token ngay**: token chỉ hiện một lần (website chỉ lưu bản băm).
5. Thu hồi: bấm **Thu hồi** ở cùng trang, token bị chặn ngay lập tức.

Trang này cũng liệt kê người tạo, lần dùng cuối, ngày hết hạn của từng token.

### 2.3. Loại token và quyền (abilities)

| Loại (khi tạo) | Quyền token có | Dùng cho | Điều kiện người tạo |
|---|---|---|---|
| Nội dung (`content`) | `content:read`, `content:write` | Công cụ viết bài, AI agent | Có quyền sửa cài đặt |
| Chỉ đọc (`readonly`) | `content:read` | KIOT, báo cáo | Có quyền sửa cài đặt |
| Đơn hàng – chỉ đọc (`orders`) | `orders:read` | n8n báo đơn mới | + quyền **xem đơn hàng** (`orders.view`) |
| Đơn hàng – đọc + cập nhật (`orders_write`) | `orders:read`, `orders:write` | n8n cập nhật giao hàng, thanh toán | + quyền **xem** và **sửa đơn hàng** (`orders.edit`) |

Super-admin luôn đủ quyền xem/sửa đơn. Quy tắc quan trọng:

- Token Nội dung **không** đọc được đơn hàng; token Đơn hàng **không** đọc được sản phẩm/bài viết (403).
- Không có loại token nào sửa được tồn kho, danh mục của sản phẩm, trạng thái hiển thị hay slug.
- Token đơn hàng được kiểm tra lại quyền của người tạo **ở mỗi lần gọi**: người tạo bị bỏ quyền xem (hoặc sửa) đơn thì token nhận 403.
- Người tạo token phải còn là nhân viên (vai trò `admin` hoặc `staff`); nếu không, 401.
- Token đăng nhập của khách hàng hay phiên trình duyệt admin **không** dùng được ở đây (401 "Cần API token quản trị.").

---

## 3. Giới hạn và quy ước chung

| Mục | Giá trị |
|---|---|
| Giới hạn tốc độ | **120 yêu cầu / phút**. Bộ đếm tính theo **tài khoản người tạo token**, nên mọi token của cùng một người dùng chung 120 lần/phút. Vượt: 429, có header `Retry-After` (giây). |
| Định dạng | JSON, UTF-8 |
| Tiền | Số nguyên đồng (VND). Ở sản phẩm, `price`/`sale_price` trả về dạng số (có thể là số thực, ví dụ `9000000.0`). |
| Thời gian trả về | ISO 8601 theo **UTC** (`...+00:00`). Giờ Việt Nam = UTC + 7. |
| Thời gian gửi lên | Nên luôn kèm múi giờ, ví dụ `2026-11-25T09:30:00+07:00` (xem lưu ý ở từng API). |
| Phân trang | `?page=` và `?per_page=` (tối đa 100). Trả về `meta`: `current_page`, `last_page`, `per_page`, `total`. |
| Bộ nhớ đệm | Phản hồi đơn hàng có `Cache-Control: no-store, private`. |

### Dạng lỗi chung

| HTTP | Thân phản hồi | Khi nào |
|---|---|---|
| 401 | `{"message": "Cần API token quản trị."}` hoặc `{"message": "Unauthenticated."}` | Thiếu token, token sai, hết hạn, đã thu hồi, không phải token admin |
| 403 | `{"message": "Token này không có quyền thực hiện thao tác."}` | Token thiếu quyền cho đường dẫn này |
| 404 | `{"message": "..."}` | Không có bản ghi với `id` này (sản phẩm đã xoá cũng 404) |
| 422 | `{"message": "...", "errors": {"<trường>": ["..."]}}` | Dữ liệu sai |
| 429 | `{"message": "Too Many Attempts."}` | Quá 120 lần/phút |

Các API cập nhật đơn có thêm `error_code` (mục 12).

---

## 4. Sản phẩm

### 4.1. GET `/products` — quyền `content:read`

| Tham số | Kiểu | Ý nghĩa |
|---|---|---|
| `search` | chuỗi | Tìm trong tên hoặc SKU |
| `category_id` | số | Lọc theo danh mục |
| `updated_since` | thời gian | Chỉ lấy sản phẩm cập nhật từ thời điểm này (không kèm múi giờ thì tính là **UTC**) |
| `per_page` | số | 1–100, mặc định **50** |
| `page` | số | Trang |

Sắp xếp: `id` giảm dần. Gồm cả sản phẩm đang ẩn (`is_active: false`).

```bash
curl "https://admin.laptopplus.vn/api/v1/admin-api/products?search=7420&per_page=20" \
  -H "Authorization: Bearer <TOKEN>" -H "Accept: application/json"
```

### 4.2. GET `/products/{id}` — quyền `content:read`

Trả `{"data": <sản phẩm>}`.

### Dữ liệu một sản phẩm

| Trường | Ý nghĩa |
|---|---|
| `id`, `name`, `sku`, `slug`, `url` | Định danh, link công khai |
| `category` | `{id, name, slug}` hoặc `null` |
| `price`, `sale_price` | Giá bán, giá khuyến mãi (`null` nếu không có) |
| `price_editable` | `true` nếu API được sửa giá (xem 4.3) |
| `price_locked_reason` | `null`, `sku_not_web_new`, `kiot_product`, `has_variants` |
| `price_locked_message` | Lý do bằng tiếng Việt |
| `stock_quantity` | Số website đang bán (0 khi hết hàng hoặc ẩn) |
| `ready_quantity` | Số sẵn sàng bán (với hàng KIOT: không tính máy đang sửa, đã giữ) |
| `inventory_source` | Nguồn tồn kho, ví dụ `kiot` |
| `kiot` | Với hàng KIOT: `{stock_quantity, reserved_quantity, ready_quantity, synced_at}`; ngược lại `null` |
| `is_active`, `show_on_pc_website` | Hiển thị |
| `manufacture_year` | Năm sản xuất |
| `meta_title`, `meta_description`, `meta_robots`, `canonical_url` | SEO |
| `instant_key_url`, `external_buy_url`, `external_buy_label` | Link key bản quyền, link mua ở nơi khác |
| `short_description`, `description` | Mô tả (HTML) |
| `specifications` | Mảng `{label, value}` |
| `images` | Mảng `{id, url, alt, is_primary}` |
| `updated_at` | Lần sửa cuối |

Danh sách trả `{"data": [...], "meta": {...}}`.

### 4.3. PATCH `/products/{id}` — quyền `content:write`

Chỉ gửi trường cần sửa.

| Trường | Kiểu / giới hạn | Ghi chú |
|---|---|---|
| `meta_title` | chuỗi ≤ 255, nullable | |
| `meta_description` | chuỗi ≤ 1000, nullable | |
| `meta_robots` | `index,follow`, `noindex,follow`, `noindex,nofollow`, `index,nofollow` hoặc `null` | `null` = mặc định `index,follow` |
| `canonical_url` | ≤ 2048, nullable | Link `https://...` đầy đủ, hoặc đường dẫn bắt đầu bằng `/` (không có `?`, `#`) |
| `short_description` | chuỗi ≤ 500, nullable | |
| `description` | chuỗi ≤ 200.000, nullable | HTML |
| `specifications` | mảng ≤ 100 phần tử, nullable | `[{"label":"CPU","value":"i5"}]` hoặc dạng gọn `{"CPU":"i5"}`. `label` ≤ 255 (bắt buộc), `value` ≤ 2000. **Thay toàn bộ** thông số cũ. |
| `images` | mảng ≤ 50 | `[{"id": 12, "alt": "..."}]`: chỉ sửa **alt** của ảnh đã gắn với sản phẩm (`id` lấy từ `images[].id`). Không thêm/xoá ảnh. |
| `instant_key_url` | `https://...` ≤ 2048, nullable | |
| `external_buy_url` | `https://...` ≤ 2048, nullable | |
| `external_buy_label` | chuỗi ≤ 60, nullable | |
| `price` | số nguyên 1–10.000.000.000 | **Chỉ** khi `price_editable: true` |
| `sale_price` | số nguyên 0–10.000.000.000, nullable | **Chỉ** khi `price_editable: true`; phải nhỏ hơn giá bán; `0` hoặc `null` = bỏ khuyến mãi |

**Bị cấm (422):** `stock_quantity`, `category_id`, `is_active`, `slug`.

**Giá:** API chỉ sửa giá sản phẩm có SKU bắt đầu bằng `WEB-NEW-`, không liên kết KIOT và không có biến thể. Sản phẩm khác: 422, tin nhắn kèm `price_locked_reason`. Giá nhận cả chuỗi `"12.000.000"`, `"12,000,000"`, `"12000000đ"`, `"12 000 000 VNĐ"`.

Thành công: 200, `{"data": <sản phẩm sau khi sửa>}`.

```bash
curl -X PATCH https://admin.laptopplus.vn/api/v1/admin-api/products/88 \
  -H "Authorization: Bearer <TOKEN>" -H "Accept: application/json" -H "Content-Type: application/json" \
  -d '{"meta_title":"Dell Latitude 7420 i5 chính hãng","specifications":{"CPU":"i5-1145G7","RAM":"16GB"}}'
```

---

## 5. Danh mục sản phẩm

### 5.1. GET `/categories` — quyền `content:read`

Không phân trang, trả toàn bộ, sắp theo `parent_id`, `sort_order`, `id`.

Mỗi danh mục: `id, parent_id, name, slug, description, image, meta_title, meta_description, sort_order, is_active, provider, url`.

```bash
curl https://admin.laptopplus.vn/api/v1/admin-api/categories -H "Authorization: Bearer <TOKEN>" -H "Accept: application/json"
```

### 5.2. POST `/categories` — quyền `content:write`

| Trường | Bắt buộc | Kiểu / giới hạn |
|---|---|---|
| `name` | có | chuỗi ≤ 255 |
| `slug` | không | chuỗi ≤ 160. Bỏ trống: tự tạo từ tên (trùng thì thêm `-2`, `-3`...). Tự đặt mà trùng hoặc là slug hệ thống: 422. |
| `parent_id` | không | `id` danh mục cha có thật, hoặc `null` (không tạo vòng lặp cha–con) |
| `description` | không | chuỗi ≤ 20.000 |
| `image` | không | chuỗi ≤ 500 (đường dẫn ảnh) |
| `meta_title` | không | chuỗi ≤ 255 |
| `meta_description` | không | chuỗi ≤ 1000 |
| `sort_order` | không | số nguyên ≥ 0, mặc định 0 |

Danh mục mới luôn **đang hiển thị** (`is_active: true`). Thành công: **201**, `{"data": <danh mục>}`.

```bash
curl -X POST https://admin.laptopplus.vn/api/v1/admin-api/categories \
  -H "Authorization: Bearer <TOKEN>" -H "Accept: application/json" -H "Content-Type: application/json" \
  -d '{"name":"Laptop đồ hoạ","parent_id":3}'
```

### 5.3. PATCH `/categories/{id}` — quyền `content:write`

Các trường như 5.2 (trừ `slug`), đều không bắt buộc; `name` nếu gửi thì không được rỗng. Gửi `slug`: 422 ("Slug danh mục chỉ sửa trên trang admin."). Thành công: 200, `{"data": <danh mục>}`.

---

## 6. Danh mục bài viết

### GET `/post-categories` — quyền `content:read`

Không phân trang. Mỗi mục: `id, name, slug, description, sort_order, url`. Dùng `id` cho `post_category_id` khi tạo bài.

```bash
curl https://admin.laptopplus.vn/api/v1/admin-api/post-categories -H "Authorization: Bearer <TOKEN>" -H "Accept: application/json"
```

API không tạo/sửa danh mục bài viết.

---

## 7. Bài viết

### 7.1. GET `/posts` — quyền `content:read`

| Tham số | Ý nghĩa |
|---|---|
| `status` | `draft`, `published` hoặc `archived` |
| `per_page` | 1–100, mặc định **20** |
| `page` | Trang |

Sắp `id` giảm dần. Danh sách **không** có `body`.

### 7.2. GET `/posts/{id}` — quyền `content:read`

Dữ liệu bài viết: `id, post_category_id, title, slug, excerpt, featured_image, status, is_featured, meta_title, meta_description, meta_robots, canonical_url, author_id, body` (chỉ ở chi tiết), `url, published_at, is_scheduled, updated_at`.

`is_scheduled: true` = đã đặt `published` với ngày đăng trong tương lai; bài tự hiện khi tới `published_at`.

```bash
curl "https://admin.laptopplus.vn/api/v1/admin-api/posts?status=draft" -H "Authorization: Bearer <TOKEN>" -H "Accept: application/json"
```

### 7.3. POST `/posts` — quyền `content:write`

| Trường | Bắt buộc | Kiểu / giới hạn |
|---|---|---|
| `title` | có | chuỗi ≤ 255 |
| `body` | có | HTML. Website tự lọc thẻ/thuộc tính không an toàn. |
| `slug` | không | ≤ 160. Bỏ trống: tự tạo từ tiêu đề; tự đặt mà trùng: 422. |
| `excerpt` | không | ≤ 500 |
| `featured_image` | không | ≤ 500 (URL ảnh, ví dụ `url` từ `/media`) |
| `post_category_id` | không | `id` danh mục bài viết có thật |
| `status` | không | `draft` (mặc định), `published`, `archived` |
| `is_featured` | không | `true`/`false` |
| `published_at` | không | Thời gian. Không kèm múi giờ thì tính theo **giờ của website** (mặc định `Asia/Ho_Chi_Minh`). `published` không có ngày: đăng ngay. |
| `meta_title` | không | ≤ 255 |
| `meta_description` | không | ≤ 1000 |
| `meta_robots`, `canonical_url` | không | Như 4.3 |
| `author_id` | không | `id` tác giả có thật |

Người tạo bài là người tạo token. Thành công: **201**, `{"data": <bài viết>}`.

```bash
curl -X POST https://admin.laptopplus.vn/api/v1/admin-api/posts \
  -H "Authorization: Bearer <TOKEN>" -H "Accept: application/json" -H "Content-Type: application/json" \
  -d '{"title":"Chọn laptop cũ cho sinh viên","body":"<p>...</p>","post_category_id":2,"status":"published","published_at":"2026-11-26T08:00:00+07:00"}'
```

### 7.4. PATCH `/posts/{id}` — quyền `content:write`

Các trường như 7.3 trừ `slug`, đều không bắt buộc (`title`, `body` nếu gửi thì không rỗng). Gửi `slug`: 422. Chuyển sang `published` mà bài chưa có ngày đăng: lấy thời điểm hiện tại. Thành công: 200.

---

## 8. Thư viện ảnh/file

### 8.1. GET `/media` — quyền `content:read`

| Tham số | Ý nghĩa |
|---|---|
| `search` | Tìm theo tên hoặc alt |
| `per_page` | 1–100, mặc định **40** |
| `page` | Trang |

Mới nhất trước. Mỗi mục: `id, name, alt, url, thumbnail_url, mime_type, width, height, folder`.

### 8.2. POST `/media` — quyền `content:write`

Gửi **multipart/form-data** (không phải JSON) và **bắt buộc** header `Accept: application/json` (thiếu thì nhận chuyển hướng 302 thay vì JSON).

| Trường | Bắt buộc | Giới hạn |
|---|---|---|
| `files[]` | có | 1–**20** file mỗi lần, mỗi file ≤ **50 MB**. Định dạng: `jpg, jpeg, png, gif, webp, svg, mp4, webm, pdf, doc, docx, xls, xlsx, zip`. |
| `folder` | không | chuỗi ≤ 255, mặc định `/` |

Ảnh `jpg/jpeg/png/gif` được tự chuyển sang **WebP** và tạo ảnh nhỏ 300px. `alt` mặc định = tên file (không có đuôi).

Thành công: **201**, `{"files": [ {id, name, file_name, path, public_slug, disk, mime_type, size, width, height, alt, caption, folder, uploaded_by, created_at, updated_at, url, thumbnail_url, human_size, fixed_url}, ... ]}` (lưu ý khoá là `files`, không phải `data`).

```bash
curl -X POST https://admin.laptopplus.vn/api/v1/admin-api/media \
  -H "Authorization: Bearer <TOKEN>" -H "Accept: application/json" \
  -F "files[]=@dell-7420.jpg" -F "folder=/san-pham"
```

### 8.3. PATCH `/media/{id}` — quyền `content:write`

| Trường | Kiểu / giới hạn |
|---|---|
| `name` | chuỗi ≤ 255 (nếu gửi thì không rỗng) |
| `alt` | chuỗi ≤ 255, nullable |
| `caption` | chuỗi ≤ 1000, nullable |

Trả 200, `{"data": {id, name, alt, url, thumbnail_url, mime_type, width, height, folder}}` (không kèm `caption`, dù đã lưu).

---

## 9. Đơn hàng – đọc

### 9.1. GET `/orders` — quyền `orders:read`

| Tham số | Kiểu / giới hạn | Ý nghĩa |
|---|---|---|
| `status` | ≤ 200 ký tự, nhiều giá trị cách bằng dấu phẩy | `pending`, `confirmed`, `processing`, `shipping`, `delivered`, `cancelled`. Giá trị khác: 422. |
| `payment_status` | ≤ 200, dấu phẩy | `unpaid`, `deposited`, `paid`, `refunded` |
| `created_since` | thời gian | Đơn đặt từ lúc này. Không ghi múi giờ: giờ website (Việt Nam). |
| `created_until` | thời gian | Đơn đặt đến lúc này |
| `after_id` | số ≥ 0 | Chỉ lấy đơn có `id` lớn hơn, **cũ trước** (dùng để n8n không báo trùng) |
| `search` | ≤ 100 | Mã đơn, SĐT hoặc tên khách |
| `per_page` | 1–100, mặc định **50** | |
| `page` | | |

Không có `after_id`: đơn mới nhất trước.

```bash
curl "https://admin.laptopplus.vn/api/v1/admin-api/orders?after_id=1530&per_page=100" \
  -H "Authorization: Bearer <TOKEN>" -H "Accept: application/json"
```

### 9.2. GET `/orders/{id}` — quyền `orders:read`

Trả `{"data": <đơn>}`. Không có đơn: 404.

### Dữ liệu một đơn (rút gọn, ví dụ đầy đủ ở [ADMIN-API-ORDERS.md](ADMIN-API-ORDERS.md#dữ-liệu-mỗi-đơn))

| Nhóm | Trường |
|---|---|
| Định danh | `id`, `order_number`, `admin_url` (mở đơn trong admin) |
| Khách | `customer_name`, `customer_phone`, `customer_email`, `shipping_address`, `notes` |
| Sản phẩm | `items[]`: `product_id, name, variant, sku, quantity, price, warranty_months, warranty_price, total`; `has_web_only_products` |
| Tiền | `subtotal`, `discount`, `shipping_fee`, `total`, `referral_discount` |
| Giao hàng | `shipping_method`, `shipping_method_label`, `status`, `status_label`, `tracking_code`, `shipping_carrier`, `shipped_at`, `delivered_at` |
| Thanh toán | `payment_status`, `payment_status_label`, `payment_method`, `payment_option`, `deposit_amount`, `payment_stage`, `payment_stage_label`, `amount_paid`, `paid_at` |
| CTV | `referral_code`, `affiliate_id`, `affiliate_code`, `affiliate_commission`, `affiliate_commission_status(_label)`, `affiliate_commission_approve_after` |
| Nguồn | `utm_source`, `utm_medium`, `utm_campaign`, `utm_content`, `utm_term`, `gclid`, `fbclid`, `video_code`, `landing_page` |
| KIOT | `kiot_sync_status`, `kiot_sync_status_label`, `kiot_order_code`, `kiot_error`, `kiot_auto_resend_count` |
| Thời gian | `created_at`, `updated_at` |

Lỗi riêng: 403 "Người tạo token không còn quyền xem đơn hàng." khi người tạo token bị bỏ quyền `orders.view`.

---

## 10. Đơn hàng – cập nhật (quyền `orders:write`)

Chung cho 3 API:
- Header: `Authorization`, `Accept: application/json`, `Content-Type: application/json`, và `Idempotency-Key` (bắt buộc với `/payments`, xem mục 11).
- Thành công: `{"data": <đơn sau khi sửa>}` (cùng dạng mục 9).
- Lỗi: `{"message": "...", "error_code": "..."}`; lỗi dữ liệu có thêm `errors`.
- Mỗi lần gọi (kể cả lỗi) ghi vào nhật ký `admin_api_write_logs` (token, đơn, nội dung, mã trả về, IP). Mỗi thay đổi hiện trong **Lịch sử đơn hàng**, người thực hiện `Token: <tên token>`.
- Người tạo token phải còn quyền xem **và** sửa đơn, nếu không: 403 `FORBIDDEN`.

### 10.1. Đánh dấu đã thanh toán — POST `/orders/{id}/payments`

Ghi nhận tiền shop đã nhận ngoài SePay (chuyển khoản, tiền mặt, COD...). **Bắt buộc header `Idempotency-Key`.**

| Trường | Bắt buộc | Kiểu / giới hạn |
|---|---|---|
| `amount` | có | Số nguyên đồng, 1–10.000.000.000. Là số tiền **nhận thêm** lần này. |
| `paid_at` | không | Thời gian nhận tiền, mặc định lúc gọi. **Luôn kèm múi giờ** (`+07:00`): chuỗi không có múi giờ được hiểu là **UTC**. |
| `method` | không | `bank_transfer`, `cash`, `cod`, `sepay`, `other` |
| `reference` | không | Mã giao dịch / ghi chú, ≤ 100 ký tự |

Cách tính:
- `amount` được **cộng dồn** vào `amount_paid`.
- `amount_paid` đạt `total` → `payment_status = paid`, ghi `paid_at`.
- Chưa đủ nhưng đơn đang `unpaid` và đạt `deposit_amount` (hoặc đơn không yêu cầu cọc) → `deposited`.
- Muốn đánh dấu **đã thanh toán đủ**: gửi `amount` = `total − amount_paid` (lấy từ `GET /orders/{id}`).

| Lỗi | Khi nào |
|---|---|
| 422 `OVERPAYMENT` | Tổng đã thu vượt `total`; tin nhắn cho biết số còn phải thu |
| 422 `ORDER_CANCELLED` | Đơn đã huỷ |
| 422 `ORDER_REFUNDED` | Đơn đã hoàn tiền |
| 422 `PAYMENT_NOT_ACCEPTED` | Đơn trả bằng SePay mà KIOT chưa nhận đơn |
| 422 `IDEMPOTENCY_KEY_REQUIRED` / `IDEMPOTENCY_KEY_INVALID` | Thiếu khoá / khoá > 100 ký tự |
| 409 `IDEMPOTENCY_KEY_REUSED` / `IDEMPOTENCY_IN_PROGRESS` | Xem mục 11 |

Nếu bật "Tự tạo phiếu thu KIOT" (Admin → Tích hợp KIOT), khoản tiền được gửi sang KIOT thành phiếu thu. Webhook `order.payment_changed` được gửi nếu bật.

```bash
curl -X POST https://admin.laptopplus.vn/api/v1/admin-api/orders/1532/payments \
  -H "Authorization: Bearer <TOKEN>" -H "Accept: application/json" -H "Content-Type: application/json" \
  -H "Idempotency-Key: FT2611250001" \
  -d '{"amount":8800000,"method":"bank_transfer","reference":"FT2611250001","paid_at":"2026-11-25T09:30:00+07:00"}'
```

### 10.2. PATCH `/orders/{id}/status`

| Trường | Bắt buộc | Kiểu / giới hạn |
|---|---|---|
| `order_status` | có | `confirmed`, `processing`, `shipping`, `delivered`, `cancelled` |
| `reason` | không | ≤ 500 ký tự, ghi vào lịch sử đơn |

- Chỉ chuyển tiếp: `pending → confirmed → processing → shipping → delivered` (được nhảy bước, không được lùi). Lùi: 422 `INVALID_TRANSITION`.
- Gửi đúng trạng thái hiện tại: 200, không đổi gì.
- Đơn đã huỷ: 422 `ORDER_CANCELLED`. Đơn đã giao không huỷ được: 422 `INVALID_TRANSITION`.
- `cancelled` với đơn đã vào KIOT: website huỷ phiếu đặt bên KIOT trước. KIOT đã xuất hoá đơn / không cho huỷ: 422 `ORDER_NOT_CANCELLABLE`. Lỗi KIOT khác: 422 `KIOT_<mã>`. KIOT chưa trả lời: **202**, đơn huỷ khi KIOT xác nhận (`kiot_sync_status = cancel_pending`).

```bash
curl -X PATCH https://admin.laptopplus.vn/api/v1/admin-api/orders/1532/status \
  -H "Authorization: Bearer <TOKEN>" -H "Accept: application/json" -H "Content-Type: application/json" \
  -d '{"order_status":"shipping","reason":"Đã giao cho GHN"}'
```

### 10.3. PATCH `/orders/{id}/shipment`

| Trường | Kiểu / giới hạn |
|---|---|
| `tracking_code` | chuỗi ≤ 100; `null` hoặc `""` để xoá |
| `shipping_carrier` | chuỗi ≤ 100; `null` hoặc `""` để xoá |
| `delivered` | `true`: chuyển đơn sang **Đã giao** (`false` không làm gì) |

Phải gửi ít nhất một trường (nếu không: 422 `VALIDATION_FAILED`). `delivered: true` với đơn đã huỷ: 422 `ORDER_CANCELLED`. Lưu ý: `delivered: true` chuyển thẳng sang Đã giao từ **bất kỳ** trạng thái chưa huỷ nào (kể cả `pending`), không theo quy tắc chuyển tiếp của 10.2.

```bash
curl -X PATCH https://admin.laptopplus.vn/api/v1/admin-api/orders/1532/shipment \
  -H "Authorization: Bearer <TOKEN>" -H "Accept: application/json" -H "Content-Type: application/json" \
  -d '{"shipping_carrier":"GHN","tracking_code":"GHN8A1B2C"}'
```

---

## 11. Idempotency-Key (API cập nhật đơn)

| | `/payments` | `/status`, `/shipment` |
|---|---|---|
| Bắt buộc | **Có** | Không (gửi thì vẫn được áp dụng như dưới) |

- Giá trị: chuỗi bất kỳ ≤ **100** ký tự, mỗi thao tác một khoá riêng (UUID, hoặc mã giao dịch ngân hàng).
- Khoá được tính **riêng cho từng token**.
- **Cùng khoá + cùng nội dung + cùng đơn + cùng API**: không thực hiện lại, trả đúng kết quả lần đầu (cả mã HTTP), kèm header `Idempotent-Replayed: true`. Tiền không bị cộng hai lần.
- **Cùng khoá, khác nội dung/đơn/API**: 409 `IDEMPOTENCY_KEY_REUSED`.
- Hai yêu cầu cùng khoá chạy song song: yêu cầu sau nhận 409 `IDEMPOTENCY_IN_PROGRESS`, thử lại sau vài giây.
- Kết quả **lỗi** sau khi đã giữ khoá (404, 422 dữ liệu sai hoặc nghiệp vụ) cũng được lưu theo khoá: sửa dữ liệu rồi gửi lại thì phải dùng **khoá mới**. Lỗi 403 `FORBIDDEN` và lỗi về chính khoá (`IDEMPOTENCY_KEY_*`) không giữ khoá. Lỗi máy chủ (500) giải phóng khoá, gửi lại cùng khoá được.

---

## 12. Bảng mã lỗi

| HTTP | `error_code` | API | Nghĩa |
|---|---|---|---|
| 401 | — | tất cả | Thiếu/sai/hết hạn/thu hồi token, không phải token admin, người tạo không còn là nhân viên |
| 403 | — | tất cả | Token không có quyền cho đường dẫn này (ví dụ token Nội dung gọi `/orders`); hoặc người tạo mất quyền xem đơn (GET đơn) |
| 403 | `FORBIDDEN` | cập nhật đơn | Người tạo token không còn quyền xem/sửa đơn |
| 404 | — | GET/PATCH theo `{id}` | Không có bản ghi |
| 404 | `ORDER_NOT_FOUND` | cập nhật đơn | Không có đơn |
| 409 | `IDEMPOTENCY_KEY_REUSED` | cập nhật đơn | Khoá đã dùng cho yêu cầu khác |
| 409 | `IDEMPOTENCY_IN_PROGRESS` | cập nhật đơn | Yêu cầu cùng khoá đang chạy |
| 422 | — | nội dung, GET đơn | Dữ liệu/tham số sai, chi tiết trong `errors` |
| 422 | `VALIDATION_FAILED` | cập nhật đơn | Dữ liệu sai, chi tiết trong `errors` |
| 422 | `IDEMPOTENCY_KEY_REQUIRED` | `/payments` | Thiếu `Idempotency-Key` |
| 422 | `IDEMPOTENCY_KEY_INVALID` | cập nhật đơn | Khoá > 100 ký tự |
| 422 | `INVALID_TRANSITION` | `/status` | Chuyển lùi, hoặc huỷ đơn đã giao |
| 422 | `ORDER_CANCELLED` | cập nhật đơn | Đơn đã huỷ |
| 422 | `ORDER_NOT_CANCELLABLE` | `/status` | KIOT không cho huỷ (đã xuất hoá đơn) |
| 422 | `KIOT_<mã>` | `/status` | Chưa huỷ được bên KIOT |
| 422 | `OVERPAYMENT` | `/payments` | Vượt số còn phải thu |
| 422 | `PAYMENT_NOT_ACCEPTED` | `/payments` | Đơn SePay chưa được KIOT nhận |
| 422 | `ORDER_REFUNDED` | `/payments` | Đơn đã hoàn tiền |
| 202 | — | `/status` | Đã nhận lệnh huỷ, chờ KIOT xác nhận |
| 429 | — | tất cả | Quá 120 lần/phút (theo người tạo token) |

Webhook (website gửi sang hệ thống của bạn khi có đơn mới, đổi trạng thái, thanh toán, liên hệ mới) và tự tạo hoá đơn/phiếu thu KIOT: xem [ADMIN-API-ORDERS.md](ADMIN-API-ORDERS.md) mục 6–7.
