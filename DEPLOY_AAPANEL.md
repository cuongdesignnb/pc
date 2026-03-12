# Hướng Dẫn Deploy PC Shop lên aaPanel

> **Server IP**: 194.233.66.28  
> **Frontend**: `pcjs.cuongdesign.net` (Nuxt 3 - Trang khách hàng)  
> **Backend**: `adminpc.cuongdesign.net` (Laravel - Admin + API)  
> Mỗi domain là 1 site riêng trên aaPanel, folder riêng.

---

## BƯỚC 1: Cài đặt môi trường trên aaPanel

### 1.1 Vào aaPanel (`http://194.233.66.28:8888`)

Cài đặt các phần mềm cần thiết trong **App Store**:
- **Nginx** (bản mới nhất)
- **MySQL 5.7** hoặc **8.0**
- **PHP 8.3** (BẮT BUỘC - packages yêu cầu 8.3+. Extensions: `fileinfo`, `redis`, `mbstring`, `pdo_mysql`, `openssl`, `tokenizer`, `xml`, `ctype`, `json`, `bcmath`)
- **PM2 Manager** (trong App Store aaPanel)

### 1.2 Cài PHP Extensions

Vào **App Store > PHP 8.3 > Settings > Install Extensions**, cài thêm:
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
- **Domain**: `adminpc.cuongdesign.net`
- **Document Root**: để mặc định (`/www/wwwroot/adminpc.cuongdesign.net`)
- **PHP Version**: PHP 8.3
- **Database**: MySQL → tên DB: `pc_shop`, user: `pc_user`

### 2.2 Site Frontend (Trang khách hàng)

Vào **Website > Add Site**:
- **Domain**: `pcjs.cuongdesign.net`
- **Document Root**: để mặc định (`/www/wwwroot/pcjs.cuongdesign.net`)
- **PHP Version**: **Static** (không cần PHP)

---

## BƯỚC 3: Pull code Backend

```bash
# Xóa file mặc định aaPanel tạo sẵn
cd /www/wwwroot/adminpc.cuongdesign.net
rm -rf .htaccess 404.html index.html .user.ini

# Clone repo vào thư mục tạm rồi copy backend
cd /tmp
git clone https://github.com/cuongdesignnb/pc.git pc_temp
cp -rf /tmp/pc_temp/backend/* /www/wwwroot/adminpc.cuongdesign.net/
cp -rf /tmp/pc_temp/backend/.* /www/wwwroot/adminpc.cuongdesign.net/ 2>/dev/null
rm -rf /tmp/pc_temp

# Cài dependencies (--ignore-platform-reqs vì một số package yêu cầu PHP 8.3)
cd /www/wwwroot/adminpc.cuongdesign.net
composer install --no-dev --optimize-autoloader --ignore-platform-reqs
```

---

## BƯỚC 4: Cấu hình Backend

### 4.1 Tạo file .env

```bash
cd /www/wwwroot/adminpc.cuongdesign.net
cp .env.server .env
nano .env
```

**Sửa các dòng quan trọng:**
```env
APP_URL=https://adminpc.cuongdesign.net

DB_HOST=127.0.0.1
DB_DATABASE=pc_shop
DB_USERNAME=pc_user
DB_PASSWORD=MẬT_KHẨU_Ở_BƯỚC_2.1

SANCTUM_STATEFUL_DOMAINS=pcjs.cuongdesign.net
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
chown -R www:www /www/wwwroot/adminpc.cuongdesign.net
chmod -R 755 /www/wwwroot/adminpc.cuongdesign.net
chmod -R 775 /www/wwwroot/adminpc.cuongdesign.net/storage
chmod -R 775 /www/wwwroot/adminpc.cuongdesign.net/bootstrap/cache
```

### 4.4 Chỉnh Document Root trên aaPanel

Vào **Website > adminpc.cuongdesign.net > Site directory**:  
Đổi **Running directory** thành `/public`

### 4.5 Cấu hình Nginx Backend

Vào **Website > adminpc.cuongdesign.net > Config**, tìm block `location /` và **thay thành**:

```nginx
location / {
    try_files $uri $uri/ /index.php?$query_string;
}

location /api {
    add_header 'Access-Control-Allow-Origin' 'https://pcjs.cuongdesign.net' always;
    add_header 'Access-Control-Allow-Methods' 'GET, POST, PUT, PATCH, DELETE, OPTIONS' always;
    add_header 'Access-Control-Allow-Headers' 'Content-Type, Authorization, X-Requested-With, X-Cart-Session' always;
    add_header 'Access-Control-Allow-Credentials' 'true' always;

    if ($request_method = 'OPTIONS') {
        add_header 'Access-Control-Allow-Origin' 'https://pcjs.cuongdesign.net' always;
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

Vào **Website > adminpc.cuongdesign.net > SSL > Let's Encrypt** → Xin chứng chỉ

---

## BƯỚC 5: Pull code Frontend

```bash
# Xóa file mặc định aaPanel tạo sẵn
cd /www/wwwroot/pcjs.cuongdesign.net
rm -rf .htaccess 404.html index.html .user.ini

# Clone repo vào thư mục tạm rồi copy frontend
cd /tmp
git clone https://github.com/cuongdesignnb/pc.git pc_temp2
cp -rf /tmp/pc_temp2/frontend/* /www/wwwroot/pcjs.cuongdesign.net/
cp -rf /tmp/pc_temp2/frontend/.* /www/wwwroot/pcjs.cuongdesign.net/ 2>/dev/null
rm -rf /tmp/pc_temp2

cd /www/wwwroot/pcjs.cuongdesign.net
```

---

## BƯỚC 6: Cấu hình & Build Frontend

### 6.1 Cấu hình .env

```bash
cd /www/wwwroot/pcjs.cuongdesign.net
cp .env.server .env
nano .env
```

**Sửa nội dung:**
```env
NUXT_PUBLIC_API_BASE=https://adminpc.cuongdesign.net/api/v1
NUXT_API_PROXY_TARGET=https://adminpc.cuongdesign.net
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
    cwd: '/www/wwwroot/pcjs.cuongdesign.net',
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

Vào **Website > pcjs.cuongdesign.net > Config**, **thay toàn bộ** block `location /`:

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

Vào **Website > pcjs.cuongdesign.net > SSL > Let's Encrypt** → Xin chứng chỉ

---

## BƯỚC 7: Kiểm tra

| URL | Kết quả mong đợi |
|-----|-------------------|
| `https://pcjs.cuongdesign.net` | Trang chủ PC Shop (khách hàng) |
| `https://adminpc.cuongdesign.net/admin` | Trang quản trị Admin |
| `https://adminpc.cuongdesign.net/api/v1/products` | JSON danh sách sản phẩm |

---

## Cập nhật code sau này

### Cập nhật Backend:
```bash
cd /tmp
git clone https://github.com/cuongdesignnb/pc.git pc_update
cp -rf /tmp/pc_update/backend/* /www/wwwroot/adminpc.cuongdesign.net/
cp -rf /tmp/pc_update/backend/.* /www/wwwroot/adminpc.cuongdesign.net/ 2>/dev/null
rm -rf /tmp/pc_update

cd /www/wwwroot/adminpc.cuongdesign.net
composer install --no-dev --optimize-autoloader --ignore-platform-reqs
php artisan migrate --force
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

### Cập nhật Frontend:
```bash
cd /tmp
git clone https://github.com/cuongdesignnb/pc.git pc_update2
cp -rf /tmp/pc_update2/frontend/* /www/wwwroot/pcjs.cuongdesign.net/
cp -rf /tmp/pc_update2/frontend/.* /www/wwwroot/pcjs.cuongdesign.net/ 2>/dev/null
rm -rf /tmp/pc_update2

cd /www/wwwroot/pcjs.cuongdesign.net
npm install
npm run build
pm2 restart pcshop-frontend
```

---

## Ghi chú quan trọng

1. Domain đã cấu hình: `pcjs.cuongdesign.net` (frontend) + `adminpc.cuongdesign.net` (backend)
2. **Admin login**: sau khi `php artisan migrate --seed`, kiểm tra file seeder để biết tài khoản admin mặc định
3. **SePay**: cấu hình `SEPAY_*` trong `.env` backend để thanh toán hoạt động
4. **Upload ảnh**: đảm bảo `storage:link` đã chạy và thư mục `storage` có quyền ghi
