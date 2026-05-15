#!/usr/bin/env bash
#
# Imports the last 10 days of APOD. Safe to re-run — already-imported dates are skipped.

set -euo pipefail

cd "$(dirname "$0")/.."

docker compose exec -T php drush stjernekig:import 10
