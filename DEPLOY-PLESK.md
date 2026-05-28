# 🚀 คู่มือ Deploy Welfare Korat 2026 บน Plesk

**Target domain:** `welfare.koratnopoor-sm-survey.nrru.ac.th`
**Stack:** Laravel 11 + Vue 3 + MariaDB + PHP 8.3

---

## ✅ Pre-flight Checklist

ก่อนเริ่ม ตรวจให้แน่ใจว่ามี:
- [ ] เข้า Plesk Panel ได้
- [ ] สิทธิ์เพิ่ม Subdomain ใน domain `koratnopoor-sm-survey.nrru.ac.th`
- [ ] PHP 8.3+ พร้อมใช้ใน Plesk
- [ ] สิทธิ์สร้าง MySQL Database
- [ ] Code ขึ้น GitHub แล้ว (`https://github.com/Sodap239519/welfare-korat2026`)
- [ ] (ถ้า private repo) Deploy key หรือ Personal Access Token

---

## Phase 1: สร้าง Subdomain ใน Plesk

### 1.1 เพิ่ม Subdomain

```
Plesk Panel → Subscriptions → koratnopoor-sm-survey.nrru.ac.th
→ "Add Subdomain" button
```

กรอก:
| Field | Value |
|---|---|
| **Subdomain name** | `welfare` |
| **Parent domain** | koratnopoor-sm-survey.nrru.ac.th |
| **Document root** | `/welfare.koratnopoor-sm-survey.nrru.ac.th/public` |

> ⚠ **สำคัญ:** Document root ต้องชี้ไปที่ `public/` ของ Laravel ไม่ใช่ root ของโปรเจกต์

### 1.2 ตั้ง PHP Version
- เลือก **PHP 8.3** (หรือใหม่กว่า)
- Tab "PHP Settings":
  - `memory_limit` = `256M` (ขั้นต่ำ)
  - `max_execution_time` = `300`
  - `upload_max_filesize` = `20M`
  - `post_max_size` = `25M`

---

## Phase 2: สร้าง Database

### 2.1 ใน Plesk
```
Subscriptions → koratnopoor-sm-survey.nrru.ac.th → Databases
→ "Add Database"
```

กรอก:
| Field | Value |
|---|---|
| **Database name** | `welfare_korat_prod` |
| **Database user** | `welfare_user` |
| **Password** | (สร้างใหม่ · จด/copy เก็บไว้) |

### 2.2 (Optional) Import ข้อมูล Demo
ถ้าต้องการเริ่มต้นด้วย demo data:
1. กดเข้า **phpMyAdmin** ของ database
2. Import → เลือกไฟล์ `backup-db.sql` (จาก USB หรือเครื่อง dev)
3. กด Go → รอ import เสร็จ

---

## Phase 3: Git Repository

### 3.1 เพิ่ม Repository ใน Plesk

```
Plesk → Subscriptions → welfare.koratnopoor-sm-survey.nrru.ac.th
→ "Git" (อยู่ใน sidebar)
→ "Add Repository"
```

กรอก:
| Field | Value |
|---|---|
| **Repository name** | `welfare-korat2026` |
| **Repository source** | `Remote Git hosting` |
| **Remote Git repository URL** | `https://github.com/Sodap239519/welfare-korat2026.git` |
| **Branch** | `main` (หรือ `master`) |
| **Deployment mode** | `Automatic` (auto-pull on push) |
| **Server path** | `/welfare.koratnopoor-sm-survey.nrru.ac.th` |

### 3.2 (ถ้า repo เป็น Private)
- เลือก "I'd like to use a deploy key for this repository"
- Plesk สร้าง SSH public key → Copy
- ไปที่ GitHub → repo → Settings → Deploy keys → Add deploy key → paste

### 3.3 ตั้ง Auto-Deploy Webhook
- ใน Plesk Git → tab "**Pull Updates Automatically**" → ON
- หรือ ใน GitHub → Settings → Webhooks → Add webhook → paste URL ที่ Plesk บอก

### 3.4 ตั้ง Post-Deploy Script
ใน Plesk Git → tab **"Additional deployment actions"** → ใส่:
```bash
bash deploy.sh
```

หรือถ้า Plesk ไม่รองรับ bash script ใส่ commands ตรงๆ:
```bash
composer install --no-dev --optimize-autoloader
npm ci && npm run build
php artisan optimize:clear
php artisan migrate --force
php artisan storage:link
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

### 3.5 ครั้งแรก: กด "Deploy" Manually
- กดปุ่ม "Deploy" สีน้ำเงิน → รอ git pull + script ทำงาน
- ดู log ของการ deploy → ตรวจว่าไม่มี error

---

## Phase 4: Configure .env

หลัง git pull เสร็จ ในโฟลเดอร์โปรเจกต์จะยังไม่มี `.env` (ถูก ignore)

### 4.1 สร้าง .env บน server

ใช้ Plesk **File Manager**:
1. ไปที่ `/welfare.koratnopoor-sm-survey.nrru.ac.th/`
2. Copy `.env.production` → rename เป็น `.env`
3. Edit ให้ตรงกับค่าจริง:

```env
APP_KEY=                                              # ขั้นถัดไปจะ generate
APP_URL=https://welfare.koratnopoor-sm-survey.nrru.ac.th

DB_DATABASE=welfare_korat_prod                        # ตามที่สร้างใน Phase 2
DB_USERNAME=welfare_user
DB_PASSWORD=<password ที่จด>

# LINE — copy จาก .env เดิม (มี token + group IDs แล้ว)
LINE_MESSAGING_TOKEN=...
LINE_ADMIN_TARGET_ID=...
LINE_REPORT_TARGET_ID=...
```

### 4.2 Generate APP_KEY
ใน Plesk → **Tools & Settings → Scheduled Tasks → Run Now** (one-time):
```bash
cd /var/www/vhosts/.../welfare.koratnopoor-sm-survey.nrru.ac.th && php artisan key:generate
```

หรือ SSH:
```bash
cd <project-path> && php artisan key:generate
```

> 💡 ค่าจะถูกใส่ในไฟล์ `.env` ที่บรรทัด `APP_KEY=...` อัตโนมัติ

---

## Phase 5: Storage Permissions

ใน Plesk File Manager:
1. คลิกขวาที่ folder `storage/` → **Change Permissions** → `775`
2. ติ๊ก **"Apply changes recursively"** → OK
3. ทำซ้ำกับ `bootstrap/cache/`

หรือ SSH:
```bash
chmod -R 775 storage bootstrap/cache
```

---

## Phase 6: Scheduled Tasks (Cron)

### 6.1 เพิ่ม Cron Job
```
Plesk → Subscriptions → welfare.koratnopoor-sm-survey.nrru.ac.th
→ "Scheduled Tasks"
→ "Add Task"
```

กรอก:
| Field | Value |
|---|---|
| **Task type** | `Run a command` |
| **Run** | `Cron style` → `* * * * *` (ทุก 1 นาที) |
| **Command** | `php /var/www/vhosts/<path>/welfare.koratnopoor-sm-survey.nrru.ac.th/artisan schedule:run` |
| **Description** | `Laravel Scheduler — Welfare Korat 2026` |
| **Notify** | `Errors only` (กัน inbox เต็ม) |

> 💡 หา path ที่ถูกต้องได้จาก: คลิก File Manager → ขวาบนแสดง full path

### 6.2 Verify
รอ 2 นาที → ดู log:
```bash
tail -20 /var/www/vhosts/<path>/welfare.koratnopoor-sm-survey.nrru.ac.th/storage/logs/laravel.log
```
ควรเห็น "Scheduled tasks ran successfully" หรือไม่มี error

---

## Phase 7: SSL (Let's Encrypt — ฟรี)

```
Plesk → Subscriptions → welfare.koratnopoor-sm-survey.nrru.ac.th
→ "SSL/TLS Certificates"
→ "Install" Let's Encrypt
```

ติ๊ก:
- [✓] Secure the wildcard domain
- [✓] Include www subdomain (ไม่จำเป็น แต่กันลืม)
- [✓] Assign certificate to mail domain (ไม่จำเป็น)
- Email: ใส่ email ผู้ดูแล

กด **"Get it free"** → รอ ~30 วินาที → Certificate ติดตั้งอัตโนมัติ + ต่ออายุเองทุก 90 วัน

---

## Phase 8: Update LINE Webhook URL

หลังเปิด HTTPS แล้ว → update webhook ใน LINE Developer Console:

```
URL ใหม่: https://welfare.koratnopoor-sm-survey.nrru.ac.th/api/line/webhook
```

1. ไป https://developers.line.biz/console/
2. Provider: Welfare Korat 2026 → Channel
3. Tab "Messaging API" → Webhook URL → **Edit** → paste URL ใหม่
4. กด **Verify** → ✅ Success
5. ปิด Cloudflare Tunnel เก่าได้ (ไม่ต้องใช้แล้ว)

---

## Phase 9: ทดสอบขั้นสุดท้าย

### 9.1 เปิดเว็บ
```
https://welfare.koratnopoor-sm-survey.nrru.ac.th
```
ควรเห็นหน้า Login พร้อมโลโก้ + ตัวเลขเป้าหมาย

### 9.2 Login
ใช้บัญชี Demo:
- Super Admin: `0900000001` / `123456`

### 9.3 Test LINE Bot
- สมัครผู้ใช้ใหม่ → ดูว่าเข้ากลุ่ม Admin LINE
- รัน command manually (ผ่าน Plesk Scheduled Tasks → Run Now):
  ```
  php artisan report:three-day
  ```
- ดูว่าเข้ากลุ่ม Report LINE

### 9.4 Verify Schedule Auto-run
รอ 5 นาที → check log:
```
storage/logs/laravel.log
```
ควรเห็นรายการ scheduled tasks ทำงาน

---

## 🔄 Workflow หลังจาก Deploy แล้ว

### พัฒนาเพิ่ม / แก้บั๊ก:
1. แก้ code บนเครื่อง dev
2. `git commit` + `git push origin main`
3. **Plesk จะ auto-deploy ภายใน 30 วินาที** (ถ้าตั้ง webhook ไว้)
4. ไม่ต้อง SSH เข้า server เลย!

### Manual deploy (ถ้าไม่ใช้ auto):
- เข้า Plesk → Git → กดปุ่ม "Deploy"

### Rollback:
- Git → "Logs" → เลือก commit เก่า → "Checkout"

---

## 🐛 Trouble-shooting

### ❌ "500 Internal Server Error"
- ตรวจ `storage/logs/laravel.log` หา error
- บ่อยที่สุด: permissions (chmod 775 storage)
- หรือ APP_KEY ยังไม่ generate

### ❌ "404 Not Found" ที่ทุก URL
- Document root ตั้งถูกหรือยัง? ต้องชี้ไปที่ `public/`
- เปิด `.htaccess` ใน `public/` อยู่ครบไหม

### ❌ Vue assets โหลดไม่ขึ้น
- รัน `npm run build` แล้วหรือยัง?
- ตรวจ `public/build/manifest.json` มีไหม
- Hard reload browser (Ctrl+Shift+R)

### ❌ Database connection failed
- ตรวจ `.env` → DB_HOST, DB_DATABASE, DB_USERNAME, DB_PASSWORD
- ตรวจว่า user มี GRANT ALL บน database

### ❌ Schedule ไม่ทำงาน
- ตรวจ Plesk → Scheduled Tasks → Last Run + Status
- ดู log: `storage/logs/laravel.log`
- ทดสอบ manual: รัน `php artisan schedule:run` → ดู output

### ❌ LINE Bot ไม่ส่งข้อความ
- Webhook URL update เป็น production URL แล้วหรือยัง?
- LINE Developer Console → Verify webhook
- ตรวจ `storage/logs/laravel.log` หา "LINE" errors

---

## 📞 ติดต่อมหาวิทยาลัย

ถ้าติดเรื่อง:
- Domain/Subdomain ไม่ resolve → ติดต่อ IT มหาวิทยาลัย (DNS)
- Plesk permissions → ติดต่อ admin หลัก
- SSL ไม่ออก → ตรวจ firewall ของ HTTPS (port 443)

---

## 📦 ไฟล์ที่เกี่ยวข้องในโปรเจกต์

| ไฟล์ | ใช้ทำอะไร |
|---|---|
| `.env.production` | Template ของ `.env` สำหรับ production |
| `deploy.sh` | Post-deploy script (เรียกจาก Plesk Git) |
| `schedule-runner.bat` | (ใช้บน Windows dev เท่านั้น) |
| `DEPLOY-PLESK.md` | คู่มือนี้ |

---

🎉 **Happy Deploying!**
