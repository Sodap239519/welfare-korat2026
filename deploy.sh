#!/bin/bash
# ════════════════════════════════════════════════════════════
#  Welfare Korat 2026 — Deploy Hook Script (Plesk)
#  ทุก output จะถูกเขียนลง deploy.log (เปิดอ่านผ่าน File Manager ได้)
# ════════════════════════════════════════════════════════════

# Plesk shell มี PATH ว่าง — set เองให้ครบ
export PATH="/usr/local/sbin:/usr/local/bin:/usr/sbin:/usr/bin:/sbin:/bin:/opt/plesk/php/8.3/bin:/opt/plesk/php/8.2/bin:/opt/plesk/php/8.1/bin:$PATH"

# ─── เขียนทุก output ลง deploy.log (เห็นทั้งใน Plesk UI และในไฟล์) ───
LOG="$(pwd)/deploy.log"
exec > >(tee -a "$LOG") 2>&1

echo ""
echo "══════════════════════════════════════════════════════════"
echo "  Deploy started at: $(date '+%Y-%m-%d %H:%M:%S' 2>/dev/null || echo unknown)"
echo "  PWD: $(pwd)"
echo "  USER: $(whoami 2>/dev/null || echo unknown)"
echo "  SHELL: $SHELL"
echo "══════════════════════════════════════════════════════════"

# ─── PROBE: ดูว่ามีเครื่องมืออะไรบนเครื่องบ้าง ───
echo ""
echo "── PROBE: searching for binaries ──"
echo "PATH = $PATH"
echo ""
echo "PHP candidates:"
ls -1 /opt/plesk/php/*/bin/php 2>/dev/null || echo "  (none in /opt/plesk/php)"
ls -1 /usr/bin/php* 2>/dev/null || echo "  (none in /usr/bin)"
ls -1 /usr/local/bin/php* 2>/dev/null || echo "  (none in /usr/local/bin)"
command -v php && php -v | head -n1 || echo "  command -v php → not found"
echo ""
echo "Composer candidates:"
command -v composer || echo "  composer → not found"
ls -1 /usr/local/bin/composer* 2>/dev/null || echo "  (none in /usr/local/bin)"
[ -f composer.phar ] && echo "  composer.phar exists in repo" || echo "  no composer.phar in repo"
echo ""
echo "NPM candidates:"
command -v npm || echo "  npm → not found"
command -v node || echo "  node → not found"
echo ""

# ─── DETECT PHP ───
echo "── DETECT: php ──"
if [ -x /opt/plesk/php/8.3/bin/php ]; then
    PHP_BIN=/opt/plesk/php/8.3/bin/php
elif [ -x /opt/plesk/php/8.2/bin/php ]; then
    PHP_BIN=/opt/plesk/php/8.2/bin/php
elif [ -x /opt/plesk/php/8.1/bin/php ]; then
    PHP_BIN=/opt/plesk/php/8.1/bin/php
elif command -v php8.3 >/dev/null 2>&1; then
    PHP_BIN=$(command -v php8.3)
elif command -v php >/dev/null 2>&1; then
    PHP_BIN=$(command -v php)
else
    echo "  [FATAL] ไม่พบ php — ดู PROBE ด้านบน แล้วบอก path จริงให้ Claude"
    exit 1
fi
echo "  PHP_BIN = $PHP_BIN"
$PHP_BIN -v | head -n1

# ─── DETECT Composer ───
echo ""
echo "── DETECT: composer ──"
if command -v composer >/dev/null 2>&1; then
    COMPOSER_CMD="$(command -v composer)"
elif [ -x /usr/local/bin/composer ]; then
    COMPOSER_CMD="/usr/local/bin/composer"
elif [ -f composer.phar ]; then
    COMPOSER_CMD="$PHP_BIN composer.phar"
else
    echo "  ไม่พบ composer → ดาวน์โหลด composer.phar..."
    $PHP_BIN -r "copy('https://getcomposer.org/installer', 'composer-setup.php');" || {
        echo "  [FATAL] ดาวน์โหลด composer installer ไม่ได้ (network blocked?)"
        exit 1
    }
    $PHP_BIN composer-setup.php --quiet
    rm -f composer-setup.php
    COMPOSER_CMD="$PHP_BIN composer.phar"
fi
echo "  COMPOSER_CMD = $COMPOSER_CMD"

# ─── DETECT NPM ───
echo ""
echo "── DETECT: npm ──"
if command -v npm >/dev/null 2>&1; then
    NPM_BIN=$(command -v npm)
    HAS_NPM=1
    echo "  NPM_BIN = $NPM_BIN ($(npm -v))"
else
    HAS_NPM=0
    echo "  npm not found — จะ skip การ build (ใช้ public/build/ ที่ commit ไว้)"
fi

# ─── 1. Composer install ───
echo ""
echo "── [1/7] composer install ──"
$COMPOSER_CMD install --no-dev --optimize-autoloader --no-interaction

# ─── 2. NPM build (skip ถ้าไม่มี npm) ───
echo ""
echo "── [2/7] npm build ──"
if [ "$HAS_NPM" = "1" ]; then
    if [ -f package-lock.json ]; then
        $NPM_BIN ci
    else
        $NPM_BIN install
    fi
    $NPM_BIN run build
else
    echo "  (skipped — no npm)"
fi

# ─── 3. Clear caches ───
echo ""
echo "── [3/7] artisan optimize:clear ──"
$PHP_BIN artisan optimize:clear

# ─── 4. Migrate ───
echo ""
echo "── [4/7] artisan migrate --force ──"
$PHP_BIN artisan migrate --force

# ─── 5. Storage link ───
echo ""
echo "── [5/7] artisan storage:link ──"
$PHP_BIN artisan storage:link 2>/dev/null || echo "  (symlink exists)"

# ─── 6. Cache for prod ───
echo ""
echo "── [6/7] cache config/route/view/event ──"
$PHP_BIN artisan config:cache
$PHP_BIN artisan route:cache
$PHP_BIN artisan view:cache
$PHP_BIN artisan event:cache

# ─── 7. Permissions ───
echo ""
echo "── [7/7] chmod storage + bootstrap/cache ──"
chmod -R 775 storage bootstrap/cache 2>/dev/null || echo "  (chmod skipped)"

echo ""
echo "══════════════════════════════════════════════════════════"
echo "  DEPLOY OK at $(date '+%Y-%m-%d %H:%M:%S' 2>/dev/null || echo unknown)"
echo "══════════════════════════════════════════════════════════"
