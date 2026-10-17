#!/usr/bin/env bash
# =====================================================================
#  SeeInside Travel – apply the latest version from GitHub
#  Run as root on the server:  bash deploy.sh
# =====================================================================
set -euo pipefail
SITE="${SITE:-/www/wwwroot/ta.seeinsidesolution.com}"
PHP="${PHP:-/www/server/php/84/bin/php}"
cd "$SITE"

before="$(git rev-parse -q --verify HEAD 2>/dev/null || echo none)"
echo "▶ Превземање на новата верзија"
git fetch --quiet origin main
git reset --hard --quiet origin/main
after="$(git rev-parse HEAD)"
git log --oneline -1

# Composer (downloaded once into the project, git-ignored)
if [ ! -f composer.phar ]; then
  "$PHP" -r "copy('https://getcomposer.org/installer', 'composer-setup.php');"
  "$PHP" composer-setup.php --quiet && rm -f composer-setup.php
fi
export COMPOSER_ALLOW_SUPERUSER=1
if [ "$before" = "none" ] || git diff --name-only "$before" "$after" | grep -q '^composer.json$'; then
  echo "▶ Пакети (composer.json е променет)"
  "$PHP" composer.phar update --no-dev --no-interaction --no-progress --optimize-autoloader
else
  "$PHP" composer.phar install --no-dev --no-interaction --no-progress --optimize-autoloader
fi

echo "▶ База и кеш"
"$PHP" artisan migrate --force
"$PHP" artisan db:seed --force
"$PHP" artisan filament:assets
[ -e public/storage ] || "$PHP" artisan storage:link
"$PHP" artisan optimize:clear
"$PHP" artisan filament:optimize-clear || true
"$PHP" artisan optimize
"$PHP" artisan filament:optimize || true

find "$SITE" -mindepth 1 -maxdepth 1 ! -name '.user.ini' ! -name '.git' -exec chown -R www:www {} + 2>/dev/null || true
chmod -R ug+rwX storage bootstrap/cache
echo "✔ Готово"
