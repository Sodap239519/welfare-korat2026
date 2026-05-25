# Welfare Korat 2569

ระบบติดตามการลงทะเบียนบัตรสวัสดิการแห่งรัฐ รอบใหม่ ปี 2569 จังหวัดนครราชสีมา

**Stack**: Laravel 11 + Vue 3 + Vite + Pinia + Tailwind CSS + ApexCharts + MySQL/MariaDB · Plesk-ready

## ฟีเจอร์หลัก

- 📊 **Dashboard real-time** — KPI + Charts (Apex donut/area/bar) + filter ตาม อ./ต./ม.
- 🗂️ **จัดการรายชื่อเป้าหมาย** 1,506+ ราย (ค้นหา/กรอง/แก้สถานะ 4.1-4.7)
- 🏛️ **ช่องทางลงทะเบียน 4 ทาง** + ธนาคารย่อย 5 แห่ง (KTB/GSB/BAAC/GHB/IBANK)
- 👥 **ผู้กำกับติดตามรายหมู่บ้าน** CRUD
- 📥 **Import xlsx/csv** + Auto-fix "27-ม.ค." → "27/1" (Thai date trap)
- 📈 **รายงาน Daily + Bottleneck** + Excel export
- 🔐 **Auth Sanctum SPA** — username = เบอร์โทร, password ≥ 6 ตัว, 3 roles
- ⏰ **Cron 16:30 ทุกวัน** — สรุปยอด snapshot อัตโนมัติ
- 🌓 **Dark mode + ปรับขนาดตัวอักษร** persist localStorage
- 📱 **Mobile-first** + bottom nav + ไม่ scroll แนวนอน

## โครงสร้าง

```
app/
  Http/Controllers/Api/    # 11 controllers
  Models/                  # 14 models
  Services/                # XlsxImportService
  Support/                 # HouseNoResolver (แก้ Excel date trap)
  Console/Commands/        # DailySnapshot + WeeklyBottleneck
config/banks.php           # รายชื่อ 5 ธนาคารรับลงทะเบียน
database/
  migrations/              # 16 migrations
  seeders/                 # 6 seeders (incl. xlsx import)
resources/
  css/app.css              # Tailwind + Prompt + Flaticon + custom card/button classes
  js/
    app.js                 # Vue 3 bootstrap
    router/                # 10 routes + guards
    stores/                # auth + theme (Pinia)
    components/            # Sidebar, TopBar, BottomNav
    layouts/AppLayout.vue
    pages/                 # 10 pages — ทำงานจริงกับ API
    composables/useApi.js
routes/
  api.php                  # 42 endpoints (auth, dashboard, targets, import, reports, admin, ref)
  web.php                  # SPA catch-all
  console.php              # cron schedule
mockup/                    # HTML reference design (กดทุกหน้าได้จาก index.html)
Data/                      # ไฟล์ต้นทาง (gitignored — มีรหัสบ้าน)
```

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
php artisan migrate --seed     # สร้าง + นำเข้าข้อมูล demo (1,506 targets จาก ต.ปากช่อง.xlsx)

# 4. dev
npm run dev          # vite (terminal 1)
php artisan serve    # laravel (terminal 2)
```

เปิด **http://127.0.0.1:8000** → login ด้วย:

| Role | Phone | Password |
|---|---|---|
| Super Admin | `0900000001` | `123456` |
| Admin | `0900000002` | `123456` |
| Tracker | `0812345678` | `123456` |

## Build production

```bash
npm run build              # → public/build/
php artisan optimize       # cache config + routes + views
```

## Deploy บน Plesk

### 1. Upload code

```bash
# วิธีที่ 1: Git clone (แนะนำ)
cd /var/www/vhosts/your-domain.com/
git clone https://github.com/Sodap239519/welfare-korat2026.git
cd welfare-korat2026

# วิธีที่ 2: Upload zip ผ่าน Plesk File Manager
# แยกไฟล์ไปที่ httpdocs/ หรือ subdir แล้ว set Document Root = .../public
```

### 2. Document root → `public/`

Plesk → Domain → Hosting Settings → Document root = `welfare-korat2026/public`

### 3. PHP version + extensions

ใน Plesk → PHP Settings:
- เลือก **PHP 8.3** ขึ้นไป
- เปิด extensions: `bcmath`, `ctype`, `fileinfo`, `json`, `mbstring`, `openssl`, `pdo_mysql`, `tokenizer`, `xml`, `zip`, `gd`
- `upload_max_filesize` = 50M
- `post_max_size` = 50M
- `memory_limit` = 256M

### 4. Composer + Node build

```bash
cd /var/www/vhosts/your-domain.com/welfare-korat2026
composer install --no-dev --optimize-autoloader
npm ci
npm run build
```
(ถ้า Plesk ไม่มี Node — build ไฟล์ `public/build/` ที่เครื่อง local แล้ว upload ขึ้นแทน)

### 5. .env production

```bash
cp .env.production.example .env
nano .env       # ใส่ DB_*, APP_URL, SANCTUM_STATEFUL_DOMAINS, APP_KEY
php artisan key:generate
```

### 6. Database

Plesk → Databases → Add New Database `welfare_korat2026` (utf8mb4)
สร้าง user `welfare_user` + grant privileges แล้ว:

```bash
php artisan migrate --force
# สำหรับ production ไม่ต้อง --seed (จะใช้ Import xlsx จาก UI แทน)
```

### 7. Permissions

```bash
chmod -R 775 storage bootstrap/cache
chown -R psaserv:psaserv storage bootstrap/cache   # ปรับ user ตาม Plesk
```

### 8. Storage symlink

```bash
php artisan storage:link
```

### 9. Cron (Plesk Scheduled Tasks)

เพิ่ม cron entry **ทุก 1 นาที**:

```
* * * * * cd /var/www/vhosts/your-domain.com/welfare-korat2026 && /usr/bin/php artisan schedule:run >> /dev/null 2>&1
```

Laravel scheduler จะรัน:
- `reports:daily-snapshot` ทุกวัน **16:30** (Asia/Bangkok)
- `reports:weekly-bottleneck` ทุก **จันทร์ 06:00**

### 10. Queue worker (ถ้ามี import ไฟล์ใหญ่)

ตอนนี้ import รันแบบ synchronous แต่ถ้าจะเปลี่ยนเป็น queue:

Plesk Scheduled Task:
```
* * * * * cd /path && /usr/bin/php artisan queue:work --stop-when-empty --tries=1
```

### 11. Optimize ขั้นสุดท้าย

```bash
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan event:cache
```

ถ้าจะเปลี่ยน config ภายหลัง รัน `php artisan optimize:clear` ก่อน

## Cron commands (ทดสอบเอง)

```bash
php artisan reports:daily-snapshot           # สรุปยอดวันนี้
php artisan reports:daily-snapshot --date=2026-05-25
php artisan reports:weekly-bottleneck         # วิเคราะห์สัปดาห์ที่แล้ว
php artisan schedule:list                     # ดู schedule ทั้งหมด
php artisan schedule:run                      # ทดสอบ trigger schedule ครั้งเดียว
```

ไฟล์ output อยู่ที่ `storage/app/reports/daily-villages-YYYY-MM-DD.xlsx` + snapshot row ใน `report_snapshots` table

## API endpoints (42)

| Group | Endpoints |
|---|---|
| Auth | `POST /api/auth/{register,login,logout}`, `GET /api/auth/me` |
| Dashboard | `GET /api/dashboard/{stats,trends,by-channel,top-villages}` |
| Targets | `GET /api/targets`, `GET /api/targets/{id}`, `PATCH /api/targets/{id}/status` |
| Import | `POST /api/import/{preview,run}`, `GET /api/import/logs[/id]` |
| Reports | `GET /api/reports/{daily-villages,bottleneck,daily-villages/export}` |
| Trackers | `GET/POST/PATCH/DELETE /api/trackers[/id]` |
| Admin (super_admin only) | `GET/PATCH/DELETE /api/admin/users[/id]`, `POST /api/admin/users/{id}/{approve,suspend}`, `GET /api/admin/activity` |
| Lookups | `GET /api/ref/{statuses,channels,banks,amphurs,tambons,villages,project-phases,overview-metrics}` |

## Mockup preview (offline)

เปิด `mockup/index.html` ใน browser (ไม่ต้องรัน server) — hub ของ 10 หน้า reference design

## Memory / Known constraints

- **Thai date trap**: บ้านเลขที่ "27/1" Excel ไทย auto-convert เป็น "27-ม.ค." (date serial) — `App\Support\HouseNoResolver` ตรวจ + แปลงกลับใน import flow ทุกครั้ง
- **Performance**: targets ใช้ denormalized `village_id/tambon_id/amphur_id` + index composite สำหรับ aggregate scale ~60k+ records
- **Sensitive data**: รหัสบ้านเก็บ hash + Crypt-encrypted, แสดงผลที่ UI เฉพาะ address_no
- **MariaDB strict mode**: ORDER BY ห้ามใช้ alias ของ aggregate — ใช้ raw expression แทน (เห็นใน Dashboard/Report controllers)
