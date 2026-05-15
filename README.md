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

Six 30-minute sessions, each ships a real diff. Full course outline in [`docs/course-outline.md`](docs/course-outline.md).

| # | Title | Vibe | Recipe |
| --- | --- | --- | --- |
| **01** | Install · First project · Tour | *Sherlock — install, build, tour, spot the bug* | [docs/session-01-setup-debug.md](docs/session-01-setup-debug.md) |
| **02** | Skills | *Drake meme — there's a skill for that* | [docs/session-02-skills.md](docs/session-02-skills.md) |
| **03** | Sub-agents | *Avengers Assemble — three Explore agents in parallel* | [docs/session-03-subagents.md](docs/session-03-subagents.md) |
| **04** | Hooks | *"This is fine" dog* | [docs/session-04-hooks.md](docs/session-04-hooks.md) |
| **05** | `/loop` & `/schedule` | *Lumbergh — Claude is doing the cron now* | [docs/session-05-loop-schedule.md](docs/session-05-loop-schedule.md) |
| **06** | `/ultrareview` | *Galaxy brain max — 4 cloud agents on your branch* | [docs/session-06-ultrareview.md](docs/session-06-ultrareview.md) |

Phase 2 ideas live in [docs/future-sessions.md](docs/future-sessions.md).

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
