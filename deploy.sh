#!/bin/bash
# ════════════════════════════════════════════════════════════
#  Welfare Korat 2026 — Deploy Hook Script (Plesk)
#  ใช้ใน Plesk → Git → Additional deployment actions
#  Plesk จะรัน script นี้อัตโนมัติทุกครั้งที่ git pull เสร็จ
# ════════════════════════════════════════════════════════════

set -e  # หยุดทันทีถ้า command ใดล้มเหลว

echo ""
echo "════════════════════════════════════════════════════"
echo "  Deploying Welfare Korat 2026..."
echo "  Time: $(date '+%Y-%m-%d %H:%M:%S')"
echo "════════════════════════════════════════════════════"

# ─── 0. Auto-detect binaries (Plesk เก็บไว้ไม่เหมือนกัน) ───
echo ""
echo "[0/7] Detecting binaries..."

# หา PHP 8.3 (Plesk ใช้ path แบบ /opt/plesk/php/8.3/bin/php)
if [ -x /opt/plesk/php/8.3/bin/php ]; then
    PHP_BIN=/opt/plesk/php/8.3/bin/php
elif [ -x /opt/plesk/php/8.2/bin/php ]; then
    PHP_BIN=/opt/plesk/php/8.2/bin/php
elif command -v php8.3 >/dev/null 2>&1; then
    PHP_BIN=$(command -v php8.3)
elif command -v php >/dev/null 2>&1; then
    PHP_BIN=$(command -v php)
else
    echo "  [ERROR] ไม่พบ php binary"
    exit 1
fi
echo "  PHP : $PHP_BIN ($($PHP_BIN -v | head -n1))"

# หา composer
if command -v composer >/dev/null 2>&1; then
    COMPOSER_CMD="$(command -v composer)"
elif [ -x /usr/local/bin/composer ]; then
    COMPOSER_CMD="/usr/local/bin/composer"
elif [ -f composer.phar ]; then
    COMPOSER_CMD="$PHP_BIN composer.phar"
else
    echo "  composer ไม่พบ → กำลังดาวน์โหลด composer.phar..."
    $PHP_BIN -r "copy('https://getcomposer.org/installer', 'composer-setup.php');"
    $PHP_BIN composer-setup.php --quiet
    rm composer-setup.php
    COMPOSER_CMD="$PHP_BIN composer.phar"
fi
echo "  Composer : $COMPOSER_CMD"

# หา npm (อาจไม่มีบน Plesk shared hosting — จะ skip ถ้าไม่มี)
if command -v npm >/dev/null 2>&1; then
    NPM_BIN=$(command -v npm)
    HAS_NPM=1
    echo "  NPM : $NPM_BIN ($(npm -v))"
else
    HAS_NPM=0
    echo "  NPM : (not found — จะ skip การ build ใหม่ ใช้ public/build ที่ commit ไว้)"
fi

# ─── 1. Composer install (PHP dependencies) ───
echo ""
echo "[1/7] Installing PHP dependencies (composer)..."
$COMPOSER_CMD install --no-dev --optimize-autoloader --no-interaction

# ─── 2. NPM install + build (Vue + Tailwind) ───
echo ""
echo "[2/7] Building frontend assets (npm)..."
if [ "$HAS_NPM" = "1" ]; then
    if [ -f package-lock.json ]; then
        $NPM_BIN ci --silent
    else
        $NPM_BIN install --silent
    fi
    $NPM_BIN run build
else
    echo "  (skipped — ไม่มี npm บน server ให้ build ที่เครื่อง dev แล้ว commit public/build/ มา)"
fi

# ─── 3. Clear all caches ───
echo ""
echo "[3/7] Clearing caches..."
$PHP_BIN artisan optimize:clear

# ─── 4. Run migrations (ปลอดภัย: ไม่ลบข้อมูล) ───
echo ""
echo "[4/7] Running database migrations..."
$PHP_BIN artisan migrate --force

# ─── 5. Storage symlink (ครั้งแรกพอ ไม่กระทบถ้ามีอยู่แล้ว) ───
echo ""
echo "[5/7] Creating storage symlink..."
$PHP_BIN artisan storage:link 2>/dev/null || echo "  (symlink already exists, skipped)"

# ─── 6. Cache for production (เร็วขึ้น) ───
echo ""
echo "[6/7] Caching config + routes + views (production)..."
$PHP_BIN artisan config:cache
$PHP_BIN artisan route:cache
$PHP_BIN artisan view:cache
$PHP_BIN artisan event:cache

# ─── 7. Set permissions (ให้ web server เขียน storage + cache ได้) ───
echo ""
echo "[7/7] Setting permissions..."
chmod -R 775 storage bootstrap/cache 2>/dev/null || true

echo ""
echo "════════════════════════════════════════════════════"
echo "  ✅ Deploy completed successfully!"
echo "════════════════════════════════════════════════════"
