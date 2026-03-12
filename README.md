# 🖥️ PC Shop - Website Bán PC & Laptop

Website bán hàng PC, Laptop và linh kiện máy tính với tính năng xây dựng cấu hình thông minh (AI Builder).

## 🚀 Công nghệ

- **Backend**: Laravel 11 + PHP 8.3
- **Admin Panel**: Inertia.js + Vue 3
- **Frontend**: Nuxt 3 + Vue 3 + TypeScript
- **Database**: MySQL 8
- **Cache**: Redis 7
- **Search**: Meilisearch
- **CSS**: Tailwind CSS + NuxtUI

## 📁 Cấu trúc thư mục

```
├── backend/          # Laravel 11 API + Admin
├── frontend/         # Nuxt 3 Storefront
├── docker/           # Docker configurations
├── docker-compose.yml
├── PLAN.md           # Kế hoạch chi tiết
└── README.md
```

## 🐳 Docker Ports

| Service | Port | URL |
|---|---|---|
| Laravel (API + Admin) | 8901 | http://localhost:8901 |
| Nuxt (Storefront) | 8902 | http://localhost:8902 |
| MySQL | 33061 | localhost:33061 |
| Redis | 63791 | localhost:63791 |
| Meilisearch | 7701 | http://localhost:7701 |
| Mailpit (Email UI) | 8026 | http://localhost:8026 |

## 🛠️ Cài đặt & Chạy

### Yêu cầu
- Docker Desktop
- Docker Compose

### Bước 1: Clone & Start Docker

```bash
# Di chuyển đến thư mục project
cd d:\PC

# Build và start containers
docker-compose up -d --build
```

### Bước 2: Setup Laravel

```bash
# Vào container PHP
docker exec -it pc_php bash

# Cài dependencies
composer install

# Generate key (nếu chưa có)
php artisan key:generate

# Chạy migrations
php artisan migrate

# Seed data mẫu
php artisan db:seed
```

### Bước 3: Truy cập

- **Storefront**: http://localhost:8902
- **Admin**: http://localhost:8901/admin
- **API**: http://localhost:8901/api/v1

## 🔧 Commands thường dùng

```bash
# Start containers
docker-compose up -d

# Stop containers
docker-compose down

# View logs
docker-compose logs -f

# Vào container PHP
docker exec -it pc_php bash

# Vào container Frontend
docker exec -it pc_frontend sh

# Chạy Artisan command
docker exec -it pc_php php artisan <command>

# Chạy npm trong frontend
docker exec -it pc_frontend npm run <command>
```

## 📝 Database Connection (Local tools)

```
Host: localhost
Port: 33061
Database: pc_shop
Username: pc_user
Password: pc_secret
```

## 📧 Test Email

Mailpit UI: http://localhost:8026

## 🔍 Meilisearch Dashboard

http://localhost:7701
Master Key: `pc_meili_master_key_2026`

---

Xem chi tiết kế hoạch tại [PLAN.md](PLAN.md)
