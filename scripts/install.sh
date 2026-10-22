#!/usr/bin/env bash
# First-time local install: builds the image, installs Drupal from the exported
# configuration in config/sync and imports the last week of pictures.
set -euo pipefail
cd "$(dirname "$0")/.."

if [ ! -f .env ]; then
  cp .env.example .env
  echo "Created .env from .env.example. Edit it if you want different ports or passwords."
fi
# shellcheck disable=SC1091
set -a; source .env; set +a

mkdir -p web/sites/default/files data/private backups

echo "→ Building and starting the stack"
scripts/dev.sh up -d --build

echo "→ Waiting for the database"
for _ in $(seq 1 60); do
  if scripts/dev.sh exec -T stjernekig_db healthcheck.sh --connect --innodb_initialized >/dev/null 2>&1; then break; fi
  sleep 2
done

DRUSH="scripts/dev.sh exec -T -u www-data drupal vendor/bin/drush"

if $DRUSH status --field=bootstrap 2>/dev/null | grep -q Successful; then
  echo "→ Drupal is already installed; importing configuration instead"
  $DRUSH cim -y
else
  echo "→ Installing Drupal from config/sync"
  $DRUSH site:install --existing-config -y \
    --account-name=admin \
    --account-pass="${ADMIN_PASSWORD:-admin}" \
    --account-mail="admin@${DOMAIN_NAME:-stjernekig.localhost}"
fi

echo "→ Fixing file permissions"
scripts/dev.sh exec -T -u root drupal chown -R www-data:www-data /opt/drupal/web/sites/default/files /opt/drupal/private

echo "→ Importing the last 7 days"
$DRUSH stjernekig:import 7 || echo "   (import failed - is the source reachable? Retry with: $DRUSH stjernekig:import 7)"

echo
echo "Done. Open http://localhost:${HTTP_PORT:-8095}/"
echo "Admin: admin / ${ADMIN_PASSWORD:-admin}   or   $DRUSH uli"
echo "Whole archive since 1995:   $DRUSH stjernekig:backfill"
