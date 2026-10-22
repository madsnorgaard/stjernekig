# Stjernekig ✦

*Stjernekig* is Danish for "stargazing". This is a self-hosted archive of NASA's
[Astronomy Picture of the Day](https://science.nasa.gov/apod/): every entry
since 16 June 1995, imported with its picture, explanation and credit line, kept
up to date automatically and browsable by day, month, year, "on this day",
random pick, search, RSS and JSON:API.

Built on Drupal 11 with one custom module and one custom theme. No contributed
modules beyond the admin toolbar.

## What it does

- **Imports from NASA's current source.** APOD moved from `apod.nasa.gov` to
  WordPress on `science.nasa.gov` in 2024, and the old `api.nasa.gov` endpoint
  now returns placeholders. Stjernekig reads the WordPress REST API directly,
  which carries the complete back-catalogue with structured titles, dates,
  credits and media.
- **Handles pictures and videos.** Image days are downloaded at full resolution
  and served through image styles (card, calendar thumbnail, hero, social card).
  Video days embed the YouTube or Vimeo player through the privacy-friendly
  `youtube-nocookie` domain.
- **Respects rights.** Every entry records whether its credit line carries a
  copyright notice. A setting lets you keep copyrighted pictures as link-outs
  only, while NASA's own public domain pictures are always stored.
- **Stays current.** Cron queues the newest days plus any recent gaps; a queue
  worker imports them. A `drush stjernekig:backfill` command fetches the whole
  archive and is safe to re-run.
- **Links the archive to itself.** Explanations are full of links to earlier
  APODs. Those are rewritten to point into this archive instead of NASA.
- **Public pages:** latest, per-day, per-month calendar, per-year, all years,
  on-this-day, random, search, RSS feed, XML sitemap. Open Graph and Twitter
  card metadata on every entry.

## Quick start (local)

Requirements: Docker with Compose v2.

```bash
git clone https://github.com/madsnorgaard/stjernekig
cd stjernekig
scripts/install.sh
```

That copies `.env.example` to `.env`, builds the image, installs Drupal from
the committed configuration, and imports the last week of pictures. Then open
<http://localhost:8095/>. Admin login is `admin` / `admin` (change
`ADMIN_PASSWORD` in `.env` before running the installer to pick another).

Everyday commands go through `scripts/dev.sh`, which is `docker compose` with
the local override file:

```bash
scripts/dev.sh up -d                     # start
scripts/dev.sh logs -f drupal            # watch
scripts/dev.sh exec drupal vendor/bin/drush uli   # one-time login link
scripts/dev.sh down                      # stop (data stays in data/ and web/sites/default/files/)
```

## Importing

All import commands are Drush commands inside the `drupal` container. Shorten
`scripts/dev.sh exec drupal vendor/bin/drush` to `drush` in your head.

| Command | What it does |
| --- | --- |
| `drush stjernekig:import [N]` | Import the newest N entries (default 7). |
| `drush stjernekig:import-date 2024-12-25` | Import one date. `--update` refreshes an existing entry. |
| `drush stjernekig:import-range 2024-01-01 2024-12-31` | Import a date range, 100 entries per request. |
| `drush stjernekig:backfill` | The whole archive, oldest first. `--from`, `--to`, `--limit` narrow it. Re-run to resume. |
| `drush stjernekig:fill-gaps` | Find days with no entry and try them again. `--dry-run` lists them. |
| `drush stjernekig:status` | Coverage: counts, first and last date, gaps, queue length, entries at the source. |
| `drush stjernekig:peek [date]` | Fetch one entry and print what the parser made of it, without saving. |
| `drush stjernekig:forget-missing` | Clear the list of dates the source said were empty. |

The full backfill is about 11,500 entries. Expect a few hours and several
gigabytes of pictures. `scripts/backfill.sh` runs it detached and logs to
`backups/`. Days APOD never published (there are a few hundred over 30 years)
are remembered so they are not requested over and over; cron re-checks them
weekly.

The same report is available in the admin UI at **Reports → Stjernekig archive
status**, and settings at **Configuration → Web services → Stjernekig**.

## Production

The repository is laid out for the deploy pipeline in
[contabo-infrastructure](https://github.com/madsnorgaard/contabo-infrastructure):
`docker-compose.yml` at the root, the image built from `docker/Dockerfile`,
`settings.php` reading everything from the environment, configuration exported
to `config/sync`, and custom code under `web/modules/custom` and
`web/themes/custom`. See [docs/deployment.md](docs/deployment.md) for the
subdomain runbook.

In short, on the server:

```bash
mkdir -p ~/docker/stjernekig && cd ~/docker/stjernekig
git clone https://github.com/madsnorgaard/stjernekig .
cp .env.example .env && $EDITOR .env        # DOMAIN_NAME, DB_PASS, HASH_SALT, DRUPAL_ENV=production
docker compose up -d --build
docker compose exec drupal vendor/bin/drush site:install --existing-config -y --account-pass="$(openssl rand -base64 18)"
docker compose exec drupal vendor/bin/drush stjernekig:import 30
docker compose exec -d drupal vendor/bin/drush stjernekig:backfill
```

The `cron` service runs `drush cron` every hour, which keeps the archive
current without any host crontab. Pushes to `main` pass CI (lint, static
analysis, unit tests, image build) and then dispatch a deploy.

## Layout

```
composer.json, composer.lock     Drupal 11 project (drupal/core-recommended, drush, admin_toolbar)
docker/Dockerfile                Production image: drupal:11-php8.4-apache + our code
docker-compose.yml               Production stack: mariadb, drupal (Traefik labels), cron
docker-compose.local.yml         Local override: published port, no Traefik
settings.php                     Environment-driven Drupal settings
config/sync/                     Exported site configuration
web/modules/custom/stjernekig/   The module
  src/Client/                    ApodClient, ApodParser, ApodEntry: talk to NASA, normalise the payload
  src/Importer/                  ApodImporter: entries -> nodes + files + aliases
  src/Archive/                   ArchiveRepository: all read queries behind the pages
  src/Controller/                Archive pages, feed and sitemap, admin report
  src/Drush/Commands/            drush stjernekig:*
  src/Plugin/QueueWorker/        Cron-driven import queue
  config/install/                Content type, fields, displays, image styles, text format, settings
  templates/                     Hero, meta, navigation, calendar, year and month lists
  tests/src/Unit/                Parser tests against real NASA payloads
web/themes/custom/stjernekig_theme/   Dark-sky theme, no base theme, no blocks needed
scripts/                         install.sh, dev.sh, backfill.sh
docs/                            Data model, source notes, deployment runbook
```

## Data model

One content type, `apod_entry`, one node per day. Fields:

| Field | Type | Notes |
| --- | --- | --- |
| `field_apod_date` | date | Unique per entry; the alias `/apod/YYYY-MM-DD` is derived from it |
| `field_apod_explanation` | formatted text | `apod_html` format: links and inline emphasis only |
| `field_apod_credit`, `field_apod_credit_label` | formatted text, string | "Jaroslav Fous" under the label NASA used, e.g. "Image Credit & Copyright" |
| `field_apod_copyrighted` | boolean | Derived from the credit line |
| `field_apod_authors` | string | The APOD editors credited that day |
| `field_apod_media_type` | list | `image` or `video` |
| `field_apod_image` | image | The picture, or a still for video days; stored under `files/apod/YYYY/MM/` |
| `field_apod_video_url` | string | Embed URL for video days |
| `field_apod_remote_url` | string | Full resolution original at NASA |
| `field_apod_source_url`, `field_apod_legacy_url` | string | The science.nasa.gov page and the classic apod.nasa.gov address |
| `field_apod_remote_id` | integer | WordPress post ID at the source |

Everything is also available read-only through JSON:API at
`/jsonapi/node/apod_entry`.

## Development

```bash
composer install
vendor/bin/phpcs            # Drupal coding standards
vendor/bin/phpstan analyse  # level 5 with phpstan-drupal
vendor/bin/phpunit --testsuite unit
```

To change site configuration, edit it in the admin UI of the local site and
export it: `scripts/dev.sh exec drupal vendor/bin/drush cex -y`. The export
lands in `config/sync` and is applied on deploy with `drush cim`.

## Credits and licence

Pictures and explanations belong to NASA and the individually credited
photographers and institutions. Each entry shows the credit line NASA
published and links back to the original. This project is not affiliated with
NASA.

The code is GPL-2.0-or-later, like Drupal.
