#!/bin/bash
# ==============================================================
# Post-Deploy Script - Universal Card Central (system.universalcardcentral.com)
# Dijalankan otomatis oleh Plesk setelah git pull
# ==============================================================

export PATH=$PATH:/usr/local/bin:/usr/bin:/bin:/usr/local/sbin:/usr/sbin

if command -v composer >/dev/null 2>&1; then
    composer install --no-dev --optimize-autoloader --no-interaction || true
fi

if command -v php >/dev/null 2>&1; then
    php artisan migrate --force || true
    php artisan storage:link || true
    php artisan config:cache || true
    php artisan route:cache || true
    php artisan view:cache || true
fi

echo "✅ Deploy selesai! Universal Card Central siap di https://system.universalcardcentral.com"
