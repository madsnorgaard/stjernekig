# Stjernekig — Claude instructions

A small Drupal 10 site that imports NASA's *Astronomy Picture of the Day* (APOD). Built as the running example for the Eksponent Claude Code internal sessions.

## Stack

- **Drupal 10** in `site/web/`, Composer-managed (`site/composer.json`)
- **PHP 8.2** (`eksponent/php8:8-2-fpm`)
- **MySQL 8**
- **nginx 1.23**
- **Docker Compose** (not DDEV) — Eksponent convention
- **Dory** proxy maps `https://stjernekig.docker` → the nginx container via the `VIRTUAL_HOST` env

## Common commands

```bash
# Bring up the stack
docker compose up -d

# Composer + Drupal install (first run, or after wiping DB)
./scripts/install.sh

# Import the last 10 days of APOD (plus enqueue + drain the queue)
docker compose exec php drush stjernekig:import 10

# Peek a single date's raw payload (debugging helper)
docker compose exec php drush stjernekig:peek 2024-12-25

# Watch dblog for warnings/errors
docker compose exec php drush wd-show --count=20

# Drop into the php container shell
docker compose exec php bash
```

## Layout

- `docker-compose.yml`, `.env.dist`, `docker-configs/` — stack
- `site/composer.json` — Drupal project
- `site/web/modules/custom/stjernekig/` — the demo module
  - `src/Service/ApodClient.php` — NASA API client *(planted bug lives here)*
  - `src/Plugin/QueueWorker/ApodImportWorker.php` — turns API responses into nodes
  - `src/Commands/StjernekigCommands.php` — `drush stjernekig:import|peek`
  - `src/Controller/ApodController.php` — `/apod` gallery
  - `stjernekig.install` — content type + fields + displays
- `docs/session-*.md` — talk tracks for each demo session

## Conventions

- Standard Drupal coding standards (PSR-12-ish, snake_case for hooks, PascalCase for classes).
- Use constructor property promotion + `final` on new classes.
- Inject services via the container; don't use `\Drupal::service()` in new code unless inside a `.module` hook.
- **No AI attribution in commit messages, PR descriptions, or code comments** (inherits from `~/.claude/CLAUDE.md`).
- Don't commit `.env`, `settings.local.php`, or anything matching the draft/note patterns in `.gitignore`. Internal notes live in `~/Notes/stjernekig/`, never here.

## What this project is *for*

This is a teaching repo. Each session adds something visible (a feature, a refactor, a Skill demo). Keep the changes legible — they should be readable to a colleague who just cloned the repo. Don't over-engineer. Don't add abstractions until the second use forces them.

If you (Claude) are asked to "fix" or "improve" something here without an explicit task, **pause and ask** what the session goal is — sometimes the code is intentionally suboptimal to support a future demo.
