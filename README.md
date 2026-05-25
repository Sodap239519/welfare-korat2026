# Welfare Korat 2569

ระบบติดตามการลงทะเบียนบัตรสวัสดิการแห่งรัฐ รอบใหม่ ปี 2569 จังหวัดนครราชสีมา

**Stack**: Laravel 11 + Vue 3 + Vite + Pinia + Tailwind CSS + ApexCharts + MySQL · Plesk-ready

## โครงสร้าง

- `app/`, `config/`, `database/`, `routes/` — Laravel backend
- `resources/js/` — Vue 3 SPA (Pinia + Router + ApexCharts)
  - `pages/` — แต่ละหน้าใน app
  - `components/` — Sidebar, TopBar, BottomNav
  - `layouts/AppLayout.vue` — เลย์เอาต์หลัก (sidebar + topbar + bottom nav)
  - `stores/theme.js` — Pinia store สำหรับ dark mode + ขนาดตัวอักษร
- `resources/css/app.css` — Tailwind + Prompt font + Flaticon UIcons
- `mockup/` — HTML/Tailwind mockup (reference design — minimal cards, น้ำเงิน/ฟ้า/ขาว/เขียว/แดง/ส้ม)
- `Data/` — ไฟล์ต้นทาง (ใส่ `.gitignore` — มีรหัสบ้าน)

## Setup local

```bash
# 1. install deps
composer install
npm install

# 2. env
cp .env.example .env
php artisan key:generate

# 3. db (XAMPP MySQL/MariaDB)
mysql -u root -e "CREATE DATABASE welfare_korat2026 CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci"
php artisan migrate

# 4. dev
npm run dev          # vite (terminal 1)
php artisan serve    # laravel (terminal 2)

# build
npm run build
```

เปิด http://127.0.0.1:8000

## Mockup preview

เปิดไฟล์ใน `mockup/` ด้วย browser (ไม่ต้องรัน server) — `index.html` คือ hub
