# Session 01 — Local setup, best practices, plan mode + debug

**Date:** Monday 2026-05-18
**Length:** 30 minutes
**Theme:** *Hvordan man sætter Claude Code op lokalt, best practice og et eksempel på plan mode og tilrettelæggelse af en given opgave eller debug.*
**Next session:** Skills (drupal-security, drupal-expert, drupal-migration).

---

## Goal

By the end of the session, the room should be able to:

1. Install Claude Code locally and understand the three tiers of config (`~/.claude/CLAUDE.md`, project `CLAUDE.md`, MEMORY.md).
2. Recognise when to use **plan mode** vs. just letting Claude write code.
3. See a realistic debug-and-fix loop driven from a single Danish-language prompt.

## Pre-flight (Sunday 2026-05-17)

Do this once before the session:

- [ ] `cd ~/Code/stjernekig && docker compose down -v && rm -rf docker-volumes site/vendor site/web/core site/web/modules/contrib site/web/themes/contrib`
- [ ] `docker compose up -d && ./scripts/install.sh` — confirm clean cold-install from a wiped repo
- [ ] `drush stjernekig:import 10` — confirm the bug fires (less than 10 nodes appear)
- [ ] `drush wd-show` — confirm the warnings show up clearly
- [ ] Time it with a stopwatch. The whole live segment must fit in 25 minutes (5 min buffer).

If `DEMO_KEY` rate-limits during the dry run, generate a real key at <https://api.nasa.gov/> and put it in `.env`.

## Talk track

### 0–7 min · Local setup

Open a terminal next to the slides.

- `cat ~/.claude/CLAUDE.md` — show the user-level preferences (no AI attribution, where notes live, devops scope).
- `cat ~/.claude/settings.json` — show the permission model. Explain: allowlist for read-only commands, prompts for risky ones.
- `cat ~/.claude/projects/-home-mno/memory/MEMORY.md` — show auto-memory. *"Claude knows where my projects live, my Dory setup, my Slack fix."*
- `switch-project --help` or just `switch taenktu` — show the alias.
- Mention Dory: *"Det er Dory der gør, at `stjernekig.docker` virker uden /etc/hosts-redigering."*

### 7–12 min · Project tour

- `git clone https://github.com/madsnorgaard/stjernekig ~/Code/stjernekig` (or `cd` into existing)
- `cat CLAUDE.md` — note the project-specific Claude file. *"Hver gang Claude åbner repo'et, læser den denne fil."*
- `docker compose up -d`
- `./scripts/install.sh` (or skip if already done — explain it)
- Open `https://stjernekig.docker/` in a browser. Show the menu. Click `APOD Archive` — empty.
- `docker compose exec php drush stjernekig:import 10`
- Refresh the gallery. *"Hmm, der er færre end 10."*

### 12–15 min · Best practices

Three slides / talking points while the import runs:

1. **Tell Claude what you've ruled out.** *"I've already checked the API key works"* saves a tool call.
2. **Use plan mode for anything you'd want a second pair of eyes on.** It's not just for big features — even a debug session benefits from "what's your theory before you start changing things."
3. **Don't write AI attribution into commits, PR titles, or code comments.** Eksponent's audit trail should look like Eksponent, not like Cursor for Drupal.

### 15–25 min · Live debug + plan mode

The actual demo. Type in Danish, as you would in real work:

```
Jeg bad om 10 APOD-entries men jeg ser kun 7 i /apod-galleriet.
Find ud af hvorfor, og foreslå en plan før du retter noget.
```

What Claude should do (and what to point out as it happens):

1. Read `drush wd-show` → finds three warnings: "APOD import failed for 2024-12-25: ...".
2. Read `src/Plugin/QueueWorker/ApodImportWorker.php` → notes the try/catch swallows the failure.
3. Read `src/Service/ApodClient.php` → spots `'image_url' => $data['hdurl']`. The NASA API returns `media_type: 'video'` and `'url': '...youtube...'` with no `hdurl` for video days.
4. **Enters plan mode.** Presents two options:
   - **A) Skip-and-log:** detect `media_type !== 'image'` in `ApodClient`, return `image_url => null` plus a `media_type` field; worker checks and explicitly skips with a clean log line.
   - **B) Ingest video as a different media bundle:** more invasive, requires a `video` field or media bundle.
5. Pick **A** in the live demo. Approve. Claude edits `ApodClient` to return `media_type` and the worker to skip+log.
6. Re-run `drush stjernekig:import 10`. Gallery still shows 7 entries (the video days don't have images), but `drush wd-show` now shows clean "skipped video for date X" infos instead of warnings.

If time allows: discuss why option B would be the right call in production (Eksponent loves taking *everything* the API offers), but for the demo, skip-and-log is the honest minimum.

### 25–30 min · Recap + teaser

- Recap the three best-practice points from earlier.
- Mention `git log --oneline` — *"Ingen 'Co-Authored-By Claude' her, ingen 🤖 i PR-beskrivelsen. Sådan skal det være."*
- Tease session 2: *"Næste gang prøver vi at sætte `drupal-security` Skill'en på `ApodClient` og se hvad den finder. Spoiler: vi henter en URL fra en ekstern API uden at validere den."*

## Things that can go wrong

- **API rate limit.** `DEMO_KEY` is 30/hour. Have a real key in `.env` as backup.
- **Docker volume permissions.** Run `docker compose exec php chown -R www-data:www-data /var/www/site/web/sites/default/files /var/www/private-files /var/www/temp-files` if file uploads fail.
- **Dory not running.** `dory status` — if dead, `dory up`.
- **Audience asks about tests.** Honest answer: this demo doesn't ship tests; we'll cover testing in a later session.
