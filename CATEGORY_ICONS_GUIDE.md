# 📦 Hướng dẫn sử dụng Icon cho Categories

## ✅ Đã hoàn thành tích hợp Icon System

### Tính năng mới:
1. ✅ Thêm field `icon` vào database (categories table)
2. ✅ Thêm MediaPicker cho Icon trong Admin
3. ✅ Frontend tự động hiển thị icon từ database
4. ✅ Hỗ trợ SVG và WebP
5. ✅ Tự động convert sang WebP khi upload

---

## 🎨 Bộ icon đề xuất cho ngành PC/Tech:

### 1. **Tabler Icons** (Miễn phí, Open Source)
- Website: https://tabler.io/icons
- Download SVG: https://github.com/tabler/tabler-icons/releases
- Icons phù hợp:
  - `cpu.svg` - CPU
  - `device-desktop.svg` - PC
  - `device-laptop.svg` - Laptop
  - `circuit-board.svg` - Mainboard
  - `chip.svg` - RAM/GPU
  - `device-gamepad-2.svg` - Gaming
  - `usb.svg` - Thiết bị ngoại vi
  - `keyboard.svg` - Bàn phím
  - `mouse.svg` - Chuột
  - `headphones.svg` - Tai nghe
  - `monitor.svg` - Màn hình
  - `power.svg` - Nguồn

### 2. **Iconify** (Tổng hợp nhiều bộ icon)
- Website: https://icon-sets.iconify.design/
- Collections phù hợp:
  - `mdi` (Material Design Icons)
  - `carbon` (IBM Carbon)
  - `fluent` (Microsoft Fluent)
  - `heroicons` (Tailwind)

### 3. **Custom SVG Icons**
- Tạo icon riêng trên Figma/Illustrator
- Export as SVG
- Optimize tại: https://jakearchibald.github.io/svgomg/

---

## 📋 Cách sử dụng:

### Bước 1: Download Icon Pack
```bash
# Option 1: Download Tabler Icons
curl -L https://github.com/tabler/tabler-icons/archive/refs/tags/v3.28.0.zip -o tabler-icons.zip

# Option 2: Download individual icons từ website
# Vào https://tabler.io/icons → Search → Download SVG
```

### Bước 2: Upload vào Media Library
1. Vào Admin → Media Library
2. Click "Upload Files"
3. Chọn các file SVG icon
4. Hệ thống tự động convert sang WebP (hoặc giữ nguyên SVG)

### Bước 3: Gán Icon cho Category
1. Vào Admin → Categories → Edit
2. Click "Chọn icon" (Icon field)
3. Chọn icon từ Media Library
4. Save

### Bước 4: Kiểm tra Frontend
- Icon sẽ tự động hiển thị trong Quick Category Grid (dưới banner)
- Nếu category chưa có icon → hiển thị chữ cái đầu (fallback)

---

## 🎯 Icon mapping gợi ý:

| Danh mục | Icon File | Tabler Icon |
|----------|-----------|-------------|
| CPU | `cpu.svg` | `cpu` |
| Mainboard | `circuit-board.svg` | `circuit-board` |
| RAM | `chip.svg` | `chip` |
| Card màn hình | `device-gamepad-2.svg` | `device-gamepad-2` |
| Ổ cứng | `device-hard-drive.svg` | `device-hard-drive` |
| SSD | `brand-solid.svg` | `database` |
| Case | `box.svg` | `box` |
| Nguồn | `power.svg` | `power` |
| Tản nhiệt | `air-conditioning.svg` | `air-conditioning` |
| Màn hình | `monitor.svg` | `monitor` |
| Bàn phím | `keyboard.svg` | `keyboard` |
| Chuột | `mouse.svg` | `mouse` |
| Tai nghe | `headphones.svg` | `headphones` |

---

## 🔧 Technical Details:

### Backend changes:
- Migration: `2026_02_26_100000_add_icon_to_categories_table.php`
- Model: Added `icon` to `Category` fillable
- Admin Forms: Added MediaPicker for icon field

### Frontend changes:
- Homepage: Icon display with fallback to first letter
- CSS: Conditional background (gray for icon, color for letter)
- Type: Added `icon?: string` to Category interface

### Performance:
- SVG tải nhanh (~1-5KB/icon)
- WebP tối ưu tự động (quality 85%)
- Lazy loading images

---

## 📦 Download Icon Pack nhanh:

### Tabler Icons (Recommended):
```bash
# Download toàn bộ pack
wget https://github.com/tabler/tabler-icons/archive/refs/tags/v3.28.0.zip

# Hoặc download individual icons tại:
# https://tabler.io/icons
```

### Material Design Icons:
```bash
# Download từ:
# https://fonts.google.com/icons
```

### Heroicons:
```bash
# Download từ:
# https://heroicons.com/
```

---

## 💡 Tips:

1. **Màu sắc icon**: Upload icon màu trắng/đen, hệ thống sẽ tự style
2. **Kích thước**: Icon 40x40px hiển thị tốt, nhưng SVG scale được
3. **Format ưu tiên**: SVG > WebP > PNG
4. **Naming**: Đặt tên file rõ ràng: `cpu-icon.svg`, `ram-icon.svg`
5. **Optimize**: Dùng SVGOMG trước khi upload để giảm file size

---

## 🚀 Quick Start - Upload Icon ngay:

1. Download Tabler Icons: https://tabler.io/icons
2. Search các icon: cpu, ram, gpu, motherboard...
3. Download SVG từng icon hoặc cả pack
4. Upload vào Media Library
5. Gán cho categories tương ứng
6. Done! ✅

---

Để được các icon pack đề xuất, bạn có thể:
- Download từ links trên
- Hoặc tôi có thể tạo custom icon pack dựa trên danh sách categories hiện có
