#!/usr/bin/env bash
# Import the complete archive in the background and log progress.
# Usage: scripts/backfill.sh [--from=YYYY-MM-DD] [--to=YYYY-MM-DD] [--limit=N]
set -euo pipefail
cd "$(dirname "$0")/.."
mkdir -p backups
LOG="backups/backfill-$(date -u +%Y%m%d-%H%M%S).log"
echo "Logging to $LOG"
nohup docker compose exec -T -u www-data drupal vendor/bin/drush stjernekig:backfill "$@" > "$LOG" 2>&1 &
echo "Started (pid $!). Follow with: tail -f $LOG"
