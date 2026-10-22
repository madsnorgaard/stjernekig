#!/usr/bin/env bash
# docker compose with the local override, e.g.  scripts/dev.sh up -d --build
set -euo pipefail
cd "$(dirname "$0")/.."
docker network inspect web >/dev/null 2>&1 || docker network create web >/dev/null
exec docker compose -f docker-compose.yml -f docker-compose.local.yml "$@"
