#!/usr/bin/env bash
#
# Stjernekig — first-run installer.
#
# - Copies .env.dist to .env if missing
# - Installs composer dependencies
# - Runs `drush site-install` against an empty DB
# - Enables the stjernekig + admin_toolbar modules
# - Seeds the last 10 days of APOD

set -euo pipefail

cd "$(dirname "$0")/.."

if [ ! -f .env ]; then
  cp .env.dist .env
  echo "Copied .env.dist → .env"
fi

# Eksponent local convention: .docker domains resolve via /etc/hosts.
if ! getent hosts stjernekig.docker >/dev/null 2>&1; then
  echo
  echo "  ⚠  stjernekig.docker is not in /etc/hosts."
  echo "      Add it once with:"
  echo "        sudo sh -c 'echo \"127.0.0.1 stjernekig.docker\" >> /etc/hosts'"
  echo "      Then re-run this script."
  echo
  exit 1
fi

# Get a real NASA API key from https://api.nasa.gov/ — DEMO_KEY is rate-limited to 30/hour.
if grep -q "NASA_API_KEY=DEMO_KEY" .env 2>/dev/null; then
  echo "  ℹ  Using DEMO_KEY for NASA APOD (30 requests/hour). For real demos, set NASA_API_KEY in .env."
fi

# shellcheck disable=SC1091
set -a
source .env
set +a

echo "→ Bringing up the stack"
docker compose up -d

echo "→ Waiting for MySQL to accept connections"
until docker compose exec -T db mysqladmin ping -h"127.0.0.1" --silent >/dev/null 2>&1; do
  sleep 1
done

echo "→ composer install"
docker compose exec -T -w /var/www/site php composer install --prefer-dist --no-interaction --no-ansi

echo "→ drush site-install (DB config comes from settings.php via env vars — no --db-url)"
docker compose exec -T php drush site-install standard \
  --site-name='Stjernekig' \
  --account-name=admin \
  --account-pass=admin \
  -y

echo "→ Enabling stjernekig + admin_toolbar"
docker compose exec -T php drush en stjernekig admin_toolbar admin_toolbar_tools -y

echo "→ Fixing file permissions"
docker compose exec -T php bash -c 'mkdir -p /var/www/site/web/sites/default/files /var/www/private-files /var/www/temp-files && chown -R www-data:www-data /var/www/site/web/sites/default/files /var/www/private-files /var/www/temp-files && chmod -R 775 /var/www/site/web/sites/default/files /var/www/private-files /var/www/temp-files'

echo "→ Seeding the last 10 days of APOD"
./scripts/seed.sh

echo
echo "Done. Open https://stjernekig.docker/apod"
echo "Admin login: admin / admin (or run: docker compose exec php drush uli)"
