# Course outline ✦

The Eksponent Claude Code internal series, run by Mads. Six sessions × 30 minutes each. The same Drupal repo (this one) gets pushed further in each session, so the diff tells the story.

```
  ┌──────────────────────────────────────────────────────────────────┐
  │                                                                  │
  │   Session 01     S02       S03         S04        S05      S06   │
  │   ┌──────┐   ┌──────┐  ┌──────┐    ┌──────┐   ┌──────┐  ┌─────┐  │
  │   │Setup │──▶│Skills│─▶│ Sub- │───▶│Hooks │──▶│/loop │─▶│Ultra│  │
  │   │Plan  │   │      │  │agents│    │      │   │/sched│  │revw │  │
  │   │Debug │   │      │  │      │    │      │   │      │  │     │  │
  │   └──────┘   └──────┘  └──────┘    └──────┘   └──────┘  └─────┘  │
  │     bug      audit      research    enforce    automate  review  │
  │     fix      + refactor + design    workflow   ops cron  whole   │
  │              the fix    a feature              jobs      branch  │
  │                                                                  │
  └──────────────────────────────────────────────────────────────────┘
```

| # | Title | Date | Vibe | Recipe |
| --- | --- | --- | --- | --- |
| **01** | Install · First project · Tour of Stjernekig | **Mon 2026-05-18** | *Sherlock — install, build, tour, find the bug (don't fix it yet)* | [session-01-setup-debug.md](session-01-setup-debug.md) |
| **02** | Skills | TBD | *Drake meme — there's a skill for that* | [session-02-skills.md](session-02-skills.md) |
| **03** | Sub-agents | TBD | *Avengers Assemble — three Explore agents in parallel* | [session-03-subagents.md](session-03-subagents.md) |
| **04** | Hooks | TBD | *"This is fine" dog — life without pre-commit hooks* | [session-04-hooks.md](session-04-hooks.md) |
| **05** | `/loop` & `/schedule` | TBD | *Lumbergh — Claude is doing the cron now* | [session-05-loop-schedule.md](session-05-loop-schedule.md) |
| **06** | `/ultrareview` | TBD | *Galaxy brain max — 4 cloud agents on your branch* | [session-06-ultrareview.md](session-06-ultrareview.md) |

## Reading the recipes

Each session doc is structured the same way so they're easy to scan:

- **Vibe** — a meme reference Mads can riff on while opening
- **Recipe card** — serves / prep / cook / pairs well with
- **Ingredients** — what needs to exist before pressing play
- **Method** — minute-by-minute talk track
- **Plating** — the 3 takeaways the room should leave with
- **Notes** — gotchas and audience questions to expect
- **Code that should land in `main`** — the visible diff after the session

## How the repo grows across the series

Each session ships a real, reviewable diff on top of the previous one. By session 06, the repo has:

| Session | Net effect on the codebase |
| --- | --- |
| 01 | Initial scaffold · planted bug identified (not yet fixed) |
| 02 | Video-skip fix + security hardening (host allowlist, MIME check) · worker refactor |
| 03 | `drush stjernekig:import-range` feature, designed via parallel Explore |
| 04 | `.claude/settings.json` with `drupal-check` Stop hook · phpcs cleanups |
| 05 | `hook_cron()` retired · daily import is now a `/schedule` routine |
| 06 | Whatever the room triaged in `/ultrareview` |

It is a small, real Drupal project that has been through a believable journey. The git history is the textbook.

## Beyond Session 06

Phase 2 candidates (none locked in — see [future-sessions.md](future-sessions.md)):

- Tests · PHPUnit + Drupal Test Traits
- Migrations · D7 → D10 with `/drupal-migration`
- Drupal 11 upgrade · `upgrade_status` skill
- A real client repo · applying the patterns to live work

## Logistics cheat sheet

- **Length:** 30 min each. Time it. Run over and you lose the room.
- **Format:** live coding + minimal slides. The terminal IS the slide.
- **Language:** present in Danish, prompt in Danish, write code in English. Same as daily work.
- **Audience:** keep prompts in the room — let people suggest the next prompt.
- **Pre-flight:** every session has a "do this on Sunday" checklist. Don't skip it.

## For Claude (future sessions)

If you (Claude) are working in this repo and a recipe doc says "the code from session X is intentional," believe it. Some of the early code is rough on purpose so later sessions have something to fix. Check the relevant session doc before "improving" anything you didn't write yourself.
