# 📋 KẾ HOẠCH XÂY DỰNG WEBSITE BÁN HÀNG PC/LAPTOP

> **Ngày tạo:** 20/02/2026  
> **Kiến trúc:** Laravel 11 (Admin + API) + Nuxt 3 (Storefront)  
> **Database:** MySQL 8 | **CSS:** Tailwind CSS + NuxtUI  

---

## Tổng quan kiến trúc

```
                    Cloudflare CDN
                         │
              ┌──────────┴──────────┐
              │                     │
        store.example.vn      admin.example.vn
         (Nuxt 3 SSR)         (Laravel + Inertia)
              │                     │
              │      Nginx Reverse Proxy
              │      ┌──────┴──────┐
              │      │             │
         Node.js    PHP-FPM    Queue Worker
         (port 3000) (port 9000) (Horizon)
              │      │             │
              │      └──────┬──────┘
              │             │
              ├─── MySQL ───┤
              ├─── Redis ───┤
              └─ Meilisearch┘
```

**Mô hình:** API-first Monolith — một Laravel app phục vụ 2 consumer:
- **Admin Panel**: Inertia.js (Vue render phía server bên trong Laravel)
- **Storefront API**: RESTful JSON API cho Nuxt 3 consume

---

## Tech Stack

| Layer | Công nghệ | Ghi chú |
|---|---|---|
| **Backend** | Laravel 11, PHP 8.3 | API + Admin |
| **Admin Panel** | Inertia.js + Vue 3 | Render trong Laravel |
| **Frontend (Storefront)** | Nuxt 3, Vue 3, TypeScript | SSR/ISR cho SEO |
| **CSS** | Tailwind CSS + NuxtUI | Admin dùng Tailwind, Store dùng NuxtUI |
| **Database** | MySQL 8 | JSON columns cho specs linh hoạt |
| **Search** | Meilisearch + Laravel Scout | Tìm kiếm sản phẩm nhanh, faceted |
| **Cache & Queue** | Redis 7 | Sessions, cache, queue |
| **Auth** | Laravel Sanctum | Email + Password |
| **Payment** | Sepay (VietQR webhook) | Chuyển khoản ngân hàng |
| **File Storage** | Local / S3-compatible | Ảnh sản phẩm |
| **DevOps** | Docker Compose, GitHub Actions | CI/CD |

---

## Packages Laravel chính

| Package | Vai trò |
|---|---|
| `inertiajs/inertia-laravel` | Admin panel rendering |
| `laravel/sanctum` | API authentication |
| `spatie/laravel-permission` | Phân quyền: admin, customer |
| `spatie/laravel-medialibrary` | Quản lý ảnh sản phẩm |
| `spatie/laravel-sluggable` | SEO-friendly URL |
| `spatie/laravel-activitylog` | Audit trail admin |
| `laravel/scout` + Meilisearch | Full-text search |
| `intervention/image` | Xử lý ảnh upload |

---

## Database Schema (~28 bảng)

### 1. Users & Auth
| Bảng | Mô tả |
|---|---|
| `users` | id, name, email, password, phone, role, email_verified_at, avatar |
| `addresses` | id, user_id, label, full_name, phone, province, district, ward, street, is_default |

### 2. Product Catalog
| Bảng | Mô tả |
|---|---|
| `categories` | id, parent_id, name, slug, description, image, sort_order, is_active, meta_title, meta_description |
| `brands` | id, name, slug, logo, website, is_active |
| `component_types` | id, name, slug, sort_order (CPU, Mainboard, RAM, GPU, PSU, Case, Storage, Cooler) |
| `products` | id, category_id, brand_id, component_type_id, name, slug, sku, short_description, description, price, sale_price, cost_price, stock_quantity, is_active, is_featured, weight, warranty_months, meta_title, meta_description, views_count, sold_count |
| `product_images` | id, product_id, url, alt_text, sort_order, is_primary |

### 3. PC Configurator (AI Builder)
| Bảng | Mô tả |
|---|---|
| `specification_keys` | id, component_type_id, name (socket_type, ram_type, form_factor...), data_type, unit |
| `product_specifications` | id, product_id, specification_key_id, value_string, value_numeric |
| `compatibility_rules` | id, name, source_component_type_id, target_component_type_id, source_spec_key_id, target_spec_key_id, rule_type (exact_match/subset/less_than_or_equal/fits_in), severity (error/warning), message_template, is_active |
| `component_supported_values` | id, product_id, specification_key_id, supported_value (cho multi-value như case hỗ trợ ATX + mATX) |
| `power_requirements` | product_id, typical_tdp, peak_tdp, requires_pcie_power, pcie_connectors_needed |
| `saved_builds` | id, user_id, name, products (JSON), total_price, total_tdp |

### 4. Orders & Payments
| Bảng | Mô tả |
|---|---|
| `carts` | id, user_id, session_id |
| `cart_items` | id, cart_id, product_id, quantity, price |
| `orders` | id, user_id, order_number, subtotal, discount, shipping_fee, total, payment_status, order_status, shipping_name, shipping_phone, shipping_address, notes, paid_at |
| `order_items` | id, order_id, product_id, product_name, sku, quantity, price, total |
| `transactions` | id, order_id, sepay_transaction_id, gateway, amount, reference_code, content, transaction_date |
| `coupons` | id, code, type, value, min_order_amount, max_uses, used_count, starts_at, expires_at |

### 5. Reviews & Content
| Bảng | Mô tả |
|---|---|
| `reviews` | id, user_id, product_id, order_id, rating (1-5), title, body, is_approved, admin_reply |
| `posts` | id, user_id, title, slug, excerpt, body, featured_image, category, is_published, published_at, meta_title, meta_description, views_count |
| `banners` | id, title, image, link, position, sort_order, is_active, starts_at, ends_at |
| `pages` | id, title, slug, body (cho About Us, Chính sách...) |

---

## Chức năng chi tiết

### 🔧 1. Xây dựng cấu hình PC (AI Builder)

**Cách hoạt động:**  
Đây là hệ thống **Rule-based Constraint Engine** — "AI" ở đây là engine thông minh dùng bộ quy tắc tương thích đã cấu trúc trong database, KHÔNG cần ML training.

**Luồng xử lý:**
```
Người dùng chọn Mainboard (VD: ASUS ROG STRIX B760-A, LGA1700, DDR5)
    │
    ▼
Hệ thống đọc product_specifications của mainboard:
    socket_type = "LGA1700"
    ram_type = "DDR5"  
    form_factor = "ATX"
    max_ram_capacity = 128GB
    ram_slots = 4
    │
    ▼
Với MỖI loại linh kiện khác, áp dụng compatibility_rules:
    CPU  → WHERE socket_type = 'LGA1700'
    RAM  → WHERE ram_type = 'DDR5' AND capacity <= 128GB
    Case → WHERE supported_values INCLUDES 'ATX'
    PSU  → Tính toán sau khi chọn đủ (dựa trên total TDP)
    │
    ▼
Trả về danh sách sản phẩm tương thích + cảnh báo nếu có
```

**Service chính:** `CompatibilityService`
- `getSuggestions(selectedProducts[])` — trả về linh kiện tương thích
- `validateFullBuild(products[])` — kiểm tra toàn bộ cấu hình
- `calculatePowerBudget(products[])` — tính tổng TDP, đề xuất PSU

**Admin quản lý:**
- CRUD specification keys cho mỗi loại linh kiện
- CRUD compatibility rules (với preview test)
- Nhập specs khi thêm sản phẩm

**Nâng cao (Phase 2):**
- OpenAI API để giải thích bằng ngôn ngữ tự nhiên: "PSU này phù hợp vì cấu hình của bạn tiêu thụ ~450W, PSU 650W cho headroom 44%"
- Collaborative filtering: "Người dùng chọn CPU này thường chọn kèm tản nhiệt..."
- Điểm hiệu năng/giá tiền để xếp hạng linh kiện tương thích

---

### 🛒 2. Giỏ hàng & Thanh toán Sepay

**Giỏ hàng:**
- Guest cart lưu theo session_id, merge vào user cart khi đăng nhập
- Realtime cập nhật số lượng, xóa item
- Kiểm tra stock trước khi checkout

**Luồng thanh toán Sepay:**
```
1. Khách hoàn tất đơn → Laravel tạo Order (payment_status: unpaid)
2. Frontend hiển thị mã QR VietQR:
   https://qr.sepay.vn/img?bank=BANK&acc=ACCOUNT&amount=TOTAL&des=DHORDER_ID
3. Khách quét QR, chuyển khoản qua app ngân hàng
4. Ngân hàng thông báo Sepay → Sepay gửi webhook POST đến endpoint
5. Laravel webhook controller:
   - Verify API key từ Authorization header
   - Trích order ID từ nội dung chuyển khoản (regex: /DH(\d+)/)
   - Match order theo ID + amount + status='unpaid'
   - Update payment_status → 'paid'
   - Chống duplicate bằng sepay_transaction_id
6. Frontend poll GET /api/v1/orders/{id}/payment-status mỗi 3 giây
7. Khi status = 'paid' → hiện trang thành công
```

---

### ⭐ 3. Đánh giá sản phẩm

- Chỉ user đã mua sản phẩm mới được đánh giá (kiểm tra order_items)
- Rating 1-5 sao + tiêu đề + nội dung
- Admin duyệt trước khi hiển thị (is_approved)
- Admin có thể trả lời đánh giá (admin_reply)
- Hiển thị rating trung bình + phân bố sao trên trang sản phẩm
- Sắp xếp: mới nhất, rating cao/thấp

---

### 📰 4. Bài viết / Tin tức

- WYSIWYG editor (TipTap) cho admin viết bài
- Phân loại bài viết (Tin tức, Hướng dẫn, Review, Khuyến mãi)
- SEO: meta title, meta description, open graph ảnh
- Bài viết liên quan (cùng category)
- Lượt xem (views_count)
- Render SSG trên Nuxt cho tốc độ tải nhanh

---

## Cấu trúc thư mục

```
d:\PC\
├── backend/                          # Laravel 11
│   ├── app/
│   │   ├── Http/
│   │   │   ├── Controllers/
│   │   │   │   ├── Api/              # Storefront API
│   │   │   │   └── Admin/            # Admin (Inertia)
│   │   │   ├── Middleware/
│   │   │   ├── Requests/             # Form Request validation
│   │   │   └── Resources/            # API Resources (JSON transform)
│   │   ├── Models/                   # ~18 models
│   │   └── Services/                 # Business logic
│   ├── database/
│   │   ├── migrations/
│   │   └── seeders/
│   ├── resources/js/                 # Admin Vue + Inertia
│   ├── routes/
│   │   ├── api.php                   # Storefront API routes  
│   │   └── web.php                   # Admin Inertia routes
│   └── config/services.php
│
├── frontend/                         # Nuxt 3 (Storefront)
│   ├── components/
│   ├── composables/
│   ├── stores/                       # Pinia
│   ├── layouts/
│   ├── pages/
│   ├── nuxt.config.ts
│   └── tailwind.config.ts
│
├── docker/
├── docker-compose.yml
└── README.md
```

---

## Docker Ports (Unique)

| Service | Port | Mô tả |
|---|---|---|
| Nginx (Backend) | 8901 | Laravel API + Admin |
| Nuxt (Frontend) | 8902 | Storefront SSR |
| MySQL | 33061 | Database |
| Redis | 63791 | Cache & Queue |
| Meilisearch | 7701 | Search engine |
| Mailpit | 8026 | Email testing UI |

---

## Các bước triển khai (Phases)

### Phase 1: Foundation (Tuần 1-2)
1. Khởi tạo Laravel project + cài packages
2. Tạo tất cả migrations + models + relationships
3. Seed data mẫu: component_types, specification_keys, compatibility_rules
4. Setup Inertia.js cho admin panel
5. Khởi tạo Nuxt 3 project + cấu hình Tailwind/NuxtUI
6. Setup Sanctum auth (register, login, logout)

### Phase 2: Product Catalog & Admin (Tuần 3-4)
7. Admin CRUD: Categories, Brands, Products (với upload ảnh)
8. Admin: quản lý specification keys + nhập specs khi tạo sản phẩm
9. Admin: quản lý compatibility rules
10. API endpoints cho products, categories, brands
11. Storefront: trang chủ, danh sách sản phẩm, chi tiết sản phẩm
12. Tích hợp Meilisearch cho search + filters

### Phase 3: PC Configurator (Tuần 5-6)
13. Backend: `CompatibilityService` — getSuggestions, validateBuild, calculatePower
14. API: `/configurator/suggest` + `/configurator/check`
15. Frontend: BuilderLayout, ComponentSlot, PowerMeter, PriceSummary
16. Admin: trang test compatibility rules
17. Seed thêm data thật: specs cho CPU, Mainboard, RAM phổ biến

### Phase 4: Cart & Checkout + Sepay (Tuần 7-8)
18. Backend: CartService, giỏ hàng API
19. Backend: CheckoutService, tạo order, validate stock
20. Tích hợp Sepay: webhook controller, QR generation
21. Frontend: CartDrawer, Checkout page, QR payment, polling status
22. Email notification khi order paid

### Phase 5: Reviews & Blog (Tuần 9)
23. Backend: Review API (chỉ cho user đã mua)
24. Frontend: hiển thị reviews, form đánh giá
25. Admin: duyệt reviews, trả lời
26. Admin: CRUD bài viết (TipTap editor)
27. Frontend: trang blog, chi tiết bài viết

### Phase 6: Polish & Deploy (Tuần 10)
28. Admin Dashboard: thống kê doanh thu, đơn hàng, sản phẩm bán chạy
29. SEO optimization: meta tags, sitemap, structured data
30. Performance: cache, lazy loading images, optimize queries
31. Testing: PHPUnit (backend), Vitest (frontend)
32. Docker Compose setup + deploy lên VPS

---

## Quyết định đã xác nhận

| Quyết định | Lựa chọn | Lý do |
|---|---|---|
| Frontend storefront | **Nuxt 3** | Cùng Vue ecosystem, SSR/ISR cho SEO |
| Database | **MySQL 8** | Phổ biến VN, dễ hosting |
| CSS | **Tailwind + NuxtUI** | Component sẵn + utility-first |
| Auth | **Email + Password** (Sanctum) | Đơn giản, bảo mật |
| PC Builder "AI" | **Rule-based constraint engine** | Chính xác, dễ debug, không cần ML |
| Payment | **Sepay webhook + VietQR** | 0 phí, verify tự động |
| Search | **Meilisearch** | Nhanh, typo-tolerant, faceted |
| Admin panel | **Inertia.js** (trong Laravel) | Không cần API riêng cho admin |
