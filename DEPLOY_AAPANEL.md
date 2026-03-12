# Hướng Dẫn Deploy PC Shop lên aaPanel

> **Server IP**: 194.233.66.28  
> **Frontend**: `pcjs.YOUR_DOMAIN` (Nuxt 3 - Trang khách hàng)  
> **Backend**: `pcadmin.YOUR_DOMAIN` (Laravel - Admin + API)

---

## BƯỚC 1: Cài đặt môi trường trên aaPanel

### 1.1 Vào aaPanel (`http://194.233.66.28:8888`)

Cài đặt các phần mềm cần thiết trong **App Store**:
- **Nginx** (bản mới nhất)
- **MySQL 5.7** hoặc **8.0**
- **PHP 8.2** (cần extensions: `fileinfo`, `redis`, `mbstring`, `pdo_mysql`, `openssl`, `tokenizer`, `xml`, `ctype`, `json`, `bcmath`)
- **Node.js** (v18 hoặc v20 - cài qua Node.js Version Manager)
- **PM2** (quản lý Node process)

### 1.2 Cài PHP Extensions

Vào **App Store > PHP 8.2 > Settings > Install Extensions**, cài thêm:
- `fileinfo` (bắt buộc cho Laravel)
- `redis` (nếu dùng Redis)

### 1.3 Cài Composer

```bash
curl -sS https://getcomposer.org/installer | php
mv composer.phar /usr/local/bin/composer
```

---

## BƯỚC 2: Clone dự án

```bash
cd /www/wwwroot
git clone https://github.com/cuongdesignnb/pc.git
```

---

## BƯỚC 3: Setup Backend (Laravel)

### 3.1 Cài dependencies

```bash
cd /www/wwwroot/pc/backend
composer install --no-dev --optimize-autoloader
```

### 3.2 Cấu hình .env

```bash
cp .env.production .env
nano .env
```

**Sửa các giá trị quan trọng:**
```env
APP_URL=https://pcadmin.YOUR_DOMAIN

DB_HOST=127.0.0.1
DB_DATABASE=pc_shop
DB_USERNAME=pc_user
DB_PASSWORD=mật_khẩu_database_ở_bước_3.3

SANCTUM_STATEFUL_DOMAINS=pcjs.YOUR_DOMAIN
SESSION_DOMAIN=.YOUR_DOMAIN
```

### 3.3 Tạo Database

Vào **aaPanel > Databases > Add Database**:
- Database name: `pc_shop`
- Username: `pc_user`
- Password: (tự đặt, copy vào .env)

### 3.4 Khởi tạo Laravel

```bash
php artisan key:generate
php artisan migrate --seed
php artisan storage:link
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

### 3.5 Phân quyền

```bash
chown -R www:www /www/wwwroot/pc/backend
chmod -R 755 /www/wwwroot/pc/backend
chmod -R 775 /www/wwwroot/pc/backend/storage
chmod -R 775 /www/wwwroot/pc/backend/bootstrap/cache
```

---

## BƯỚC 4: Tạo Site Backend trên aaPanel

### 4.1 Vào **Website > Add Site**
- Domain: `pcadmin.YOUR_DOMAIN`
- Document Root: `/www/wwwroot/pc/backend/public`
- PHP Version: **PHP 8.2**
- Database: (đã tạo ở bước 3.3)

### 4.2 Cấu hình Nginx cho Backend

Vào **Website > pcadmin.YOUR_DOMAIN > Config** (Nginx config), **thay toàn bộ** nội dung `location /` thành:

```nginx
location / {
    try_files $uri $uri/ /index.php?$query_string;
}

# CORS cho API - cho phép frontend gọi
location /api {
    # CORS headers
    add_header 'Access-Control-Allow-Origin' 'https://pcjs.YOUR_DOMAIN' always;
    add_header 'Access-Control-Allow-Methods' 'GET, POST, PUT, PATCH, DELETE, OPTIONS' always;
    add_header 'Access-Control-Allow-Headers' 'Content-Type, Authorization, X-Requested-With, X-Cart-Session' always;
    add_header 'Access-Control-Allow-Credentials' 'true' always;

    if ($request_method = 'OPTIONS') {
        add_header 'Access-Control-Allow-Origin' 'https://pcjs.YOUR_DOMAIN' always;
        add_header 'Access-Control-Allow-Methods' 'GET, POST, PUT, PATCH, DELETE, OPTIONS' always;
        add_header 'Access-Control-Allow-Headers' 'Content-Type, Authorization, X-Requested-With, X-Cart-Session' always;
        add_header 'Access-Control-Max-Age' 1728000;
        add_header 'Content-Type' 'text/plain charset=UTF-8';
        add_header 'Content-Length' 0;
        return 204;
    }

    try_files $uri $uri/ /index.php?$query_string;
}
```

### 4.3 Cài SSL

Vào **Website > pcadmin.YOUR_DOMAIN > SSL > Let's Encrypt** → Xin chứng chỉ miễn phí

---

## BƯỚC 5: Setup Frontend (Nuxt 3)

### 5.1 Cài dependencies & build

```bash
cd /www/wwwroot/pc/frontend

# Cấu hình .env production
cp .env.production .env
nano .env
```

**Sửa domain thực tế:**
```env
NUXT_PUBLIC_API_BASE=https://pcadmin.YOUR_DOMAIN/api/v1
NUXT_API_PROXY_TARGET=https://pcadmin.YOUR_DOMAIN
NUXT_PUBLIC_APP_NAME="PC Shop"
```

```bash
# Cài dependencies
npm install

# Build production
npm run build
```

### 5.2 Chạy bằng PM2

```bash
# Tạo file ecosystem
cat > ecosystem.config.cjs << 'EOF'
module.exports = {
  apps: [{
    name: 'pcshop-frontend',
    port: 3000,
    script: '.output/server/index.mjs',
    cwd: '/www/wwwroot/pc/frontend',
    env: {
      NODE_ENV: 'production',
      NITRO_PORT: 3000,
      NITRO_HOST: '127.0.0.1',
    }
  }]
}
EOF

# Chạy
pm2 start ecosystem.config.cjs
pm2 save
pm2 startup
```

### 5.3 Tạo Site Frontend trên aaPanel

Vào **Website > Add Site**:
- Domain: `pcjs.YOUR_DOMAIN`
- Chọn: **Static** (không cần PHP)

### 5.4 Cấu hình Nginx Reverse Proxy cho Frontend

Vào **Website > pcjs.YOUR_DOMAIN > Config**, **thay toàn bộ** block `location /`:

```nginx
location / {
    proxy_pass http://127.0.0.1:3000;
    proxy_http_version 1.1;
    proxy_set_header Upgrade $http_upgrade;
    proxy_set_header Connection 'upgrade';
    proxy_set_header Host $host;
    proxy_set_header X-Real-IP $remote_addr;
    proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
    proxy_set_header X-Forwarded-Proto $scheme;
    proxy_cache_bypass $http_upgrade;
}
```

### 5.5 Cài SSL

Vào **Website > pcjs.YOUR_DOMAIN > SSL > Let's Encrypt** → Xin chứng chỉ

---

## BƯỚC 6: Kiểm tra

| URL | Kết quả mong đợi |
|-----|-------------------|
| `https://pcjs.YOUR_DOMAIN` | Trang chủ PC Shop |
| `https://pcadmin.YOUR_DOMAIN/admin` | Trang quản trị Admin |
| `https://pcadmin.YOUR_DOMAIN/api/v1/products` | JSON danh sách sản phẩm |

---

## Cập nhật code sau này

Khi có thay đổi code mới:

```bash
cd /www/wwwroot/pc
git pull origin main

# Backend
cd backend
composer install --no-dev --optimize-autoloader
php artisan migrate --force
php artisan config:cache
php artisan route:cache
php artisan view:cache

# Frontend
cd ../frontend
npm install
npm run build
pm2 restart pcshop-frontend
```

---

## Ghi chú quan trọng

1. **Thay `YOUR_DOMAIN`** bằng domain thực tế ở TẤT CẢ các bước trên
2. **Admin login**: sau khi `php artisan migrate --seed`, kiểm tra file seeder để biết tài khoản admin mặc định
3. **SePay**: cấu hình `SEPAY_*` trong `.env` backend để thanh toán hoạt động
4. **Upload ảnh**: đảm bảo `storage:link` đã chạy và thư mục `storage` có quyền ghi
