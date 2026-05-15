# Stjernekig ✦

> *"Stargazing"* — a small Drupal 10 site that archives NASA's [Astronomy Picture of the Day](https://api.nasa.gov/).

This is the running example for the **Eksponent Claude Code** internal session series. Each session adds something on top of the previous one. The repo grows across sessions, so the diffs tell a story.

```
┌──────────────────────────────────────────────────────────────────────┐
│  Stjernekig ✦                                                        │
│  Drupal 10 · PHP 8.2 · MySQL 8 · nginx · Docker Compose (Eksponent)  │
└──────────────────────────────────────────────────────────────────────┘
```

## Quick start

```bash
git clone https://github.com/madsnorgaard/stjernekig ~/Code/stjernekig
cd ~/Code/stjernekig

# One-time: add the .docker domain to /etc/hosts
sudo sh -c 'echo "127.0.0.1 stjernekig.docker" >> /etc/hosts'

cp .env.dist .env
# Recommended: get a free key at https://api.nasa.gov/ (DEMO_KEY is 30 req/hour).
# Then edit .env: NASA_API_KEY=your-key-here

docker compose up -d
./scripts/install.sh
```

Then open <http://stjernekig.docker/apod> in a browser.

## Session arc

| Session | Topic                                                                         | What gets built / shown                                                                                |
| ------- | ----------------------------------------------------------------------------- | ------------------------------------------------------------------------------------------------------ |
| **01**  | Local setup · Best practices · Plan mode + debug                              | This repo's scaffold. Plant: APOD importer silently drops video days. Live debug + plan-mode fix.       |
| **02**  | Skills                                                                        | `drupal-security` audits `ApodClient`. `drupal-expert` refactors. `drupal-migration` imports a CSV.    |
| **03**  | Sub-agents · parallel research                                                | Explore agent finds every external API call in the codebase.                                            |
| **04**  | Hooks · settings · custom commands                                             | Add a pre-commit `drupal-check` hook. `/update-config` and `/fewer-permission-prompts`.                |
| **05**  | `/loop`, `/schedule`, autonomous agents                                       | The daily APOD cron, but as a remote-scheduled routine.                                                 |

Each session has a talk track in `docs/session-NN-*.md`.

## What's inside

- **`docker-compose.yml`** — three-service stack (`php`, `webserver`, `db`), Eksponent conventions.
- **`docker-configs/`** — nginx, php-fpm, php.ini, Drupal `settings.php` and `settings.local.php` (mounted into the container).
- **`site/`** — Drupal `recommended-project` layout. `composer.json` here.
- **`site/web/modules/custom/stjernekig/`** — the custom module:
  - `ApodClient` — talks to api.nasa.gov
  - `ApodImportWorker` — turns responses into `apod_entry` nodes
  - `StjernekigCommands` — `drush stjernekig:import N`
  - `ApodController` — the `/apod` gallery
- **`docs/`** — session talk tracks.
- **`scripts/`** — `install.sh`, `seed.sh`.

## Working with Claude Code on this repo

See `CLAUDE.md` for the rules of engagement.

TL;DR:

- Use plan mode for anything bigger than a one-liner.
- Don't fix things without context — some code here is intentionally rough to support a future demo.
- No AI attribution in commits.
- Internal notes live in `~/Notes/stjernekig/`, never in this repo.
