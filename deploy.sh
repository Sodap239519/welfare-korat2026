#!/bin/bash
# ════════════════════════════════════════════════════════════
#  Welfare Korat 2026 — Deploy Hook Script (Plesk)
#  เขียนทุก output ลง deploy.log (เปิดผ่าน File Manager)
# ════════════════════════════════════════════════════════════

# Plesk shell มี PATH ว่าง + ไม่มี tee + ไม่รองรับ process substitution
export PATH="/usr/local/sbin:/usr/local/bin:/usr/sbin:/usr/bin:/sbin:/bin:/opt/plesk/php/8.3/bin:/opt/plesk/php/8.2/bin:/opt/plesk/php/8.1/bin:$PATH"

# เขียน output ลงไฟล์อย่างเดียว — ใช้ shell redirect ล้วน (ไม่ใช้ tee/process substitution)
exec > deploy.log 2>&1

echo "══════════════════════════════════════════════════════════"
echo "  Deploy started"
echo "  PWD: $PWD"
echo "  SHELL: $SHELL"
echo "  BASH_VERSION: $BASH_VERSION"
echo "══════════════════════════════════════════════════════════"

# ─── PROBE: ดูว่ามีอะไรบนเครื่องบ้าง (ไม่ใช้ command ภายนอกถ้าหลีกเลี่ยงได้) ───
echo ""
echo "── PROBE: PATH ──"
echo "$PATH"
echo ""

echo "── PROBE: PHP binaries ──"
for p in \
    /opt/plesk/php/8.4/bin/php /opt/plesk/php/8.3/bin/php \
    /opt/plesk/php/8.2/bin/php /opt/plesk/php/8.1/bin/php /opt/plesk/php/8.0/bin/php \
    /usr/bin/php8.4 /usr/bin/php8.3 /usr/bin/php8.2 /usr/bin/php8.1 \
    /usr/bin/php /usr/local/bin/php /usr/sbin/php; do
    if [ -x "$p" ]; then
        echo "  EXISTS: $p"
    fi
done
echo "  which php: $(which php 2>/dev/null || echo 'not found')"
echo "  find /opt/plesk/php: $(find /opt/plesk/php -name 'php' -type f 2>/dev/null | tr '\n' ' ' || echo 'not found')"
echo "  ls /opt/plesk: $(ls /opt/plesk/ 2>/dev/null || echo 'empty')"

echo ""
echo "── PROBE: Composer binaries ──"
for c in /usr/local/bin/composer /usr/bin/composer /opt/plesk/composer/composer; do
    if [ -x "$c" ]; then
        echo "  EXISTS: $c"
    fi
done
if [ -f composer.phar ]; then
    echo "  EXISTS: composer.phar (in repo)"
fi

echo ""
echo "── PROBE: NPM binaries ──"
for n in /usr/bin/npm /usr/local/bin/npm /opt/plesk/node/*/bin/npm; do
    if [ -x "$n" ]; then
        echo "  EXISTS: $n"
    fi
done

echo ""
echo "── PROBE: Other utils ──"
for u in /bin/sh /bin/bash /usr/bin/tee /bin/chmod /usr/bin/date /bin/date; do
    if [ -x "$u" ]; then
        echo "  EXISTS: $u"
    fi
done

# ─── DETECT PHP ───
echo ""
echo "── DETECT: php ──"
PHP_BIN=""
for p in \
    /opt/plesk/php/8.4/bin/php \
    /opt/plesk/php/8.3/bin/php \
    /opt/plesk/php/8.2/bin/php \
    /opt/plesk/php/8.1/bin/php \
    /opt/plesk/php/8.0/bin/php \
    /usr/bin/php8.4 /usr/bin/php8.3 /usr/bin/php8.2 /usr/bin/php8.1 /usr/bin/php8.0 \
    /usr/bin/php /usr/local/bin/php /usr/sbin/php; do
    if [ -x "$p" ]; then
        PHP_BIN="$p"
        break
    fi
done
# fallback: ลองหาจาก PATH โดยตรง
if [ -z "$PHP_BIN" ]; then
    PHP_BIN=$(command -v php 2>/dev/null || which php 2>/dev/null || true)
fi
# probe เพิ่มเติม: ค้นหา php ใน /opt/plesk ทั้งหมด
if [ -z "$PHP_BIN" ]; then
    PHP_BIN=$(find /opt/plesk/php -name "php" -type f 2>/dev/null | sort -rV | head -1)
fi
if [ -z "$PHP_BIN" ]; then
    echo "  [FATAL] ไม่พบ php — ดู PROBE ด้านบน"
    exit 1
fi
echo "  PHP_BIN = $PHP_BIN"
"$PHP_BIN" -v 2>&1 | head -n1 || echo "  (php -v failed)"

# ─── DETECT Composer ───
echo ""
echo "── DETECT: composer ──"
COMPOSER_CMD=""
for c in /usr/local/bin/composer /usr/bin/composer /opt/plesk/composer/composer; do
    if [ -x "$c" ]; then
        COMPOSER_CMD="$c"
        break
    fi
done
if [ -z "$COMPOSER_CMD" ] && [ -f composer.phar ]; then
    COMPOSER_CMD="$PHP_BIN composer.phar"
fi
if [ -z "$COMPOSER_CMD" ]; then
    echo "  ไม่พบ composer → ดาวน์โหลด composer.phar..."
    "$PHP_BIN" -r "copy('https://getcomposer.org/installer', 'composer-setup.php');" 2>&1
    if [ -f composer-setup.php ]; then
        "$PHP_BIN" composer-setup.php --quiet
        rm -f composer-setup.php
        if [ -f composer.phar ]; then
            COMPOSER_CMD="$PHP_BIN composer.phar"
        fi
    fi
fi
if [ -z "$COMPOSER_CMD" ]; then
    echo "  [FATAL] ไม่พบ composer และดาวน์โหลดไม่ได้"
    exit 1
fi
echo "  COMPOSER_CMD = $COMPOSER_CMD"

# ─── DETECT NPM ───
echo ""
echo "── DETECT: npm ──"
NPM_BIN=""
for n in /usr/bin/npm /usr/local/bin/npm; do
    if [ -x "$n" ]; then
        NPM_BIN="$n"
        break
    fi
done
if [ -z "$NPM_BIN" ]; then
    HAS_NPM=0
    echo "  npm ไม่พบ — skip build (ใช้ public/build/ ที่ commit มา)"
else
    HAS_NPM=1
    echo "  NPM_BIN = $NPM_BIN"
fi

# ─── 1. Composer install ───
echo ""
echo "── [1/7] composer install ──"
$COMPOSER_CMD install --no-dev --optimize-autoloader --no-interaction || {
    echo "  [WARN] composer install ล้มเหลว — ดู error ด้านบน"
    exit 1
}

# ─── 2. NPM build ───
echo ""
echo "── [2/7] npm build ──"
if [ "$HAS_NPM" = "1" ]; then
    if [ -f package-lock.json ]; then
        "$NPM_BIN" ci || "$NPM_BIN" install
    else
        "$NPM_BIN" install
    fi
    "$NPM_BIN" run build
else
    echo "  (skipped)"
fi

# ─── 3. Clear caches ───
echo ""
echo "── [3/7] artisan optimize:clear ──"
"$PHP_BIN" artisan optimize:clear || echo "  [WARN] อาจเป็นเพราะ .env ยังไม่ตั้ง — ข้ามไปก่อน"

# ─── 4. Migrate ───
echo ""
echo "── [4/7] artisan migrate --force ──"
"$PHP_BIN" artisan migrate --force || echo "  [WARN] migrate ล้มเหลว — ตรวจ .env DB"

# ─── 5. Storage link ───
echo ""
echo "── [5/7] artisan storage:link ──"
"$PHP_BIN" artisan storage:link 2>/dev/null || echo "  (symlink exists or failed)"

# ─── 6. Cache for prod ───
echo ""
echo "── [6/7] cache config/route/view/event ──"
"$PHP_BIN" artisan config:cache  || echo "  config:cache failed"
"$PHP_BIN" artisan route:cache   || echo "  route:cache failed"
"$PHP_BIN" artisan view:cache    || echo "  view:cache failed"
"$PHP_BIN" artisan event:cache   || echo "  event:cache failed"

# ─── 7. Permissions ───
echo ""
echo "── [7/7] chmod storage + bootstrap/cache ──"
if [ -x /bin/chmod ] || [ -x /usr/bin/chmod ]; then
    chmod -R 775 storage bootstrap/cache 2>/dev/null || echo "  (chmod skipped)"
else
    echo "  chmod ไม่มี — ข้าม"
fi

echo ""
echo "══════════════════════════════════════════════════════════"
echo "  DEPLOY FINISHED (exit OK)"
echo "══════════════════════════════════════════════════════════"
