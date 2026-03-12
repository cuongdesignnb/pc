# Hướng Dẫn Deploy PC Shop lên aaPanel

> **Server IP**: 194.233.66.28  
> **Frontend**: domain 1 (Nuxt 3 - Trang khách hàng) — ví dụ: `pcjs.example.com`  
> **Backend**: domain 2 (Laravel - Admin + API) — ví dụ: `pcadmin.example.com`  
> Mỗi domain là 1 site riêng trên aaPanel, folder riêng.

---

## BƯỚC 1: Cài đặt môi trường trên aaPanel

### 1.1 Vào aaPanel (`http://194.233.66.28:8888`)

Cài đặt các phần mềm cần thiết trong **App Store**:
- **Nginx** (bản mới nhất)
- **MySQL 5.7** hoặc **8.0**
- **PHP 8.2** (cần extensions: `fileinfo`, `redis`, `mbstring`, `pdo_mysql`, `openssl`, `tokenizer`, `xml`, `ctype`, `json`, `bcmath`)
- **PM2 Manager** (trong App Store aaPanel)

### 1.2 Cài PHP Extensions

Vào **App Store > PHP 8.2 > Settings > Install Extensions**, cài thêm:
- `fileinfo` (bắt buộc cho Laravel)
- `redis` (nếu dùng Redis)

### 1.3 Cài Composer & Node.js

```bash
# Composer
curl -sS https://getcomposer.org/installer | php
mv composer.phar /usr/local/bin/composer

# Node.js 20 (nếu chưa có)
curl -fsSL https://deb.nodesource.com/setup_20.x | bash -
apt-get install -y nodejs

# PM2
npm install -g pm2
```

---

## BƯỚC 2: Tạo 2 Site trên aaPanel

### 2.1 Site Backend (Admin + API)

Vào **Website > Add Site**:
- **Domain**: `pcadmin.YOUR_DOMAIN` (domain thực tế của bạn)
- **Document Root**: để mặc định (`/www/wwwroot/pcadmin.YOUR_DOMAIN`)
- **PHP Version**: PHP 8.2
- **Database**: MySQL → tên DB: `pc_shop`, user: `pc_user`

### 2.2 Site Frontend (Trang khách hàng)

Vào **Website > Add Site**:
- **Domain**: `pcjs.YOUR_DOMAIN` (domain thực tế của bạn)
- **Document Root**: để mặc định (`/www/wwwroot/pcjs.YOUR_DOMAIN`)
- **PHP Version**: **Static** (không cần PHP)

---

## BƯỚC 3: Pull code Backend

```bash
# Xóa file mặc định aaPanel tạo sẵn
cd /www/wwwroot/pcadmin.YOUR_DOMAIN
rm -rf .htaccess 404.html index.html .user.ini

# Clone chỉ phần backend bằng sparse checkout
git init
git remote add origin https://github.com/cuongdesignnb/pc.git
git config core.sparseCheckout true
echo "backend/*" > .git/info/sparse-checkout
git pull origin main

# Di chuyển nội dung backend ra ngoài (aaPanel cần public/ ở gốc site)
mv backend/* .
mv backend/.* . 2>/dev/null
rmdir backend

# Cài dependencies
composer install --no-dev --optimize-autoloader
```

---

## BƯỚC 4: Cấu hình Backend

### 4.1 Tạo file .env

```bash
cd /www/wwwroot/pcadmin.YOUR_DOMAIN
cp .env.production .env
nano .env
```

**Sửa các dòng này (thay YOUR_DOMAIN bằng domain thực):**
```env
APP_URL=https://pcadmin.YOUR_DOMAIN

DB_HOST=127.0.0.1
DB_DATABASE=pc_shop
DB_USERNAME=pc_user
DB_PASSWORD=MẬT_KHẨU_Ở_BƯỚC_2.1

SANCTUM_STATEFUL_DOMAINS=pcjs.YOUR_DOMAIN
SESSION_DOMAIN=null
```

### 4.2 Khởi tạo Laravel

```bash
php artisan key:generate
php artisan migrate --seed
php artisan storage:link
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

### 4.3 Phân quyền

```bash
chown -R www:www /www/wwwroot/pcadmin.YOUR_DOMAIN
chmod -R 755 /www/wwwroot/pcadmin.YOUR_DOMAIN
chmod -R 775 /www/wwwroot/pcadmin.YOUR_DOMAIN/storage
chmod -R 775 /www/wwwroot/pcadmin.YOUR_DOMAIN/bootstrap/cache
```

### 4.4 Chỉnh Document Root trên aaPanel

Vào **Website > pcadmin.YOUR_DOMAIN > Site directory**:  
Đổi **Running directory** thành `/public`

### 4.5 Cấu hình Nginx Backend

Vào **Website > pcadmin.YOUR_DOMAIN > Config**, tìm block `location /` và **thay thành**:

```nginx
location / {
    try_files $uri $uri/ /index.php?$query_string;
}

location /api {
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

### 4.6 Cài SSL cho Backend

Vào **Website > pcadmin.YOUR_DOMAIN > SSL > Let's Encrypt** → Xin chứng chỉ

---

## BƯỚC 5: Pull code Frontend

```bash
# Xóa file mặc định aaPanel tạo sẵn
cd /www/wwwroot/pcjs.YOUR_DOMAIN
rm -rf .htaccess 404.html index.html .user.ini

# Clone chỉ phần frontend bằng sparse checkout
git init
git remote add origin https://github.com/cuongdesignnb/pc.git
git config core.sparseCheckout true
echo "frontend/*" > .git/info/sparse-checkout
git pull origin main

# Di chuyển nội dung frontend ra ngoài
mv frontend/* .
mv frontend/.* . 2>/dev/null
rmdir frontend
```

---

## BƯỚC 6: Cấu hình & Build Frontend

### 6.1 Cấu hình .env

```bash
cd /www/wwwroot/pcjs.YOUR_DOMAIN
cp .env.production .env
nano .env
```

**Sửa domain thực tế:**
```env
NUXT_PUBLIC_API_BASE=https://pcadmin.YOUR_DOMAIN/api/v1
NUXT_API_PROXY_TARGET=https://pcadmin.YOUR_DOMAIN
NUXT_PUBLIC_APP_NAME="PC Shop"
```

### 6.2 Build

```bash
npm install
npm run build
```

### 6.3 Chạy bằng PM2

```bash
# Tạo file PM2 config
cat > ecosystem.config.cjs << 'EOF'
module.exports = {
  apps: [{
    name: 'pcshop-frontend',
    port: 3000,
    script: '.output/server/index.mjs',
    cwd: '/www/wwwroot/pcjs.YOUR_DOMAIN',
    env: {
      NODE_ENV: 'production',
      NITRO_PORT: 3000,
      NITRO_HOST: '127.0.0.1',
    }
  }]
}
EOF

pm2 start ecosystem.config.cjs
pm2 save
pm2 startup
```

### 6.4 Cấu hình Nginx Reverse Proxy

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

### 6.5 Cài SSL

Vào **Website > pcjs.YOUR_DOMAIN > SSL > Let's Encrypt** → Xin chứng chỉ

---

## BƯỚC 7: Kiểm tra

| URL | Kết quả mong đợi |
|-----|-------------------|
| `https://pcjs.YOUR_DOMAIN` | Trang chủ PC Shop (khách hàng) |
| `https://pcadmin.YOUR_DOMAIN/admin` | Trang quản trị Admin |
| `https://pcadmin.YOUR_DOMAIN/api/v1/products` | JSON danh sách sản phẩm |

---

## Cập nhật code sau này

### Cập nhật Backend:
```bash
cd /www/wwwroot/pcadmin.YOUR_DOMAIN
git pull origin main
# Vì sparse checkout, file sẽ nằm trong backend/, cần copy ra:
cp -rf backend/* .
cp -rf backend/.* . 2>/dev/null

composer install --no-dev --optimize-autoloader
php artisan migrate --force
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

### Cập nhật Frontend:
```bash
cd /www/wwwroot/pcjs.YOUR_DOMAIN
git pull origin main
# Vì sparse checkout, file sẽ nằm trong frontend/, cần copy ra:
cp -rf frontend/* .
cp -rf frontend/.* . 2>/dev/null

npm install
npm run build
pm2 restart pcshop-frontend
```

---

## Ghi chú quan trọng

1. **Thay `YOUR_DOMAIN`** bằng domain thực tế ở TẤT CẢ các bước
2. **Admin login**: sau khi `php artisan migrate --seed`, kiểm tra file seeder để biết tài khoản admin mặc định
3. **SePay**: cấu hình `SEPAY_*` trong `.env` backend để thanh toán hoạt động
4. **Upload ảnh**: đảm bảo `storage:link` đã chạy và thư mục `storage` có quyền ghi
