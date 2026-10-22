# Deploying as a subdomain

This runbook assumes the production server pattern used by the other sites in
the portfolio: one Docker Compose project per site under `~/docker/<name>`,
Traefik on an external `web` network terminating TLS, and the shared deploy
workflow in `contabo-infrastructure` syncing code on every push to `main`.
Hostnames, ports and addresses live in `.env` files and infrastructure secrets,
never in this repository.

## 1. DNS

Add an `A` record for the chosen subdomain (for example `stjernekig` under the
parent domain) pointing at the production server, plus the matching `AAAA` if
the server has IPv6. Wait for it to resolve before starting the stack, or the
Let's Encrypt resolver will fail its first attempt.

## 2. First install on the server

```bash
mkdir -p ~/docker/stjernekig && cd ~/docker/stjernekig
git clone https://github.com/madsnorgaard/stjernekig .
cp .env.example .env
```

Edit `.env`:

| Variable | Value |
| --- | --- |
| `DOMAIN_NAME` | the subdomain, e.g. `stjernekig.example.net` |
| `DB_PASS` | `openssl rand -base64 24` |
| `HASH_SALT` | `openssl rand -base64 48` |
| `DRUPAL_ENV` | `production` |
| `HTTP_PORT` | leave as is, only the local override uses it |

Then:

```bash
mkdir -p web/sites/default/files data/private backups
docker compose up -d --build
docker compose exec drupal vendor/bin/drush site:install --existing-config -y \
  --account-name=admin --account-pass="$(openssl rand -base64 18 | tee /dev/stderr)"
docker compose exec drupal vendor/bin/drush stjernekig:import 30
docker compose exec drupal vendor/bin/drush status
```

The site is now live at `https://<DOMAIN_NAME>/` with the last month of
pictures. The `cron` container starts a minute later and keeps it current.

## 3. Backfill

```bash
docker compose exec -d drupal vendor/bin/drush stjernekig:backfill
```

Or `scripts/backfill.sh`, which logs to `backups/`. Progress can be followed
with `drush stjernekig:status` or at **Reports → Stjernekig archive status**.
The import is idempotent: if the container restarts midway, run it again.

Disk: roughly 11,500 pictures at a few hundred kilobytes to a few megabytes
each, plus image style derivatives as pages are first viewed. Plan for 10 GB.

## 4. Continuous deployment

The `deploy` job in `.github/workflows/ci.yml` dispatches to the
`contabo-infrastructure` deploy workflow after lint, analysis, tests and the
image build pass on `main`. For that to work the repository needs a
`DEPLOY_TOKEN` secret with permission to dispatch to the infrastructure
repository. The infrastructure workflow only deploys when
`~/docker/stjernekig/.env` exists on the server, so an unconfigured server is
simply skipped.

What the deploy does, in order: git checkout of `docker-compose.yml`,
`docker/`, `settings.php`, `composer.*`, `config/`, `web/modules/custom`,
`web/themes/custom` and `scripts/`; `docker compose build drupal`;
`docker compose up -d`; `drush cr`; `drush updb`; `drush cim`.

Because `config/sync` is the source of truth, change configuration locally,
export with `drush cex`, commit, and let the deploy import it. Changing
configuration directly on production will be overwritten on the next deploy.

## 5. Backups

The MariaDB data directory is `data/mysql`, pictures are in
`web/sites/default/files/apod/`, both bind mounts in the project directory.
Everything else is reproducible from the repository and from NASA. For a
database dump:

```bash
docker compose exec drupal vendor/bin/drush sql:dump --gzip --result-file=/opt/drupal/backups/stjernekig-$(date +%F).sql
```

## 6. Monitoring

Blackbox probes on `https://<DOMAIN_NAME>/apod` and `/apod/feed` cover the
site. For freshness, `drush stjernekig:status` reports the newest entry; a
gap of more than two days means cron or the source has a problem, and
**Reports → Stjernekig archive status** shows the last error.

## Rollback

```bash
cd ~/docker/stjernekig
git log --oneline -5              # find the previous commit
git checkout <sha> -- .           # restore code and config
docker compose up -d --build drupal
docker compose exec drupal vendor/bin/drush cim -y
```

Content is never touched by a deploy, so a rollback only concerns code and
configuration.
