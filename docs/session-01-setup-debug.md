# Session 01 — Install · First project · Tour of Stjernekig 🔭

> **Vibe:** *Sherlock with a magnifying glass.*
> The first session is onboarding, not deep debugging. We install Claude Code, build a tiny throwaway project together, then tour Stjernekig to set up the rest of the series. The planted bug gets *noticed* at the end — fixing it is Session 02's job.

**Date:** Monday 2026-05-18
**Length:** 30 min
**Next session:** [Skills](session-02-skills.md) — `/drupal-security` audits the bug we found.

---

## Recipe card

| | |
| --- | --- |
| **Serves** | Eksponent dev team (8–12 ppl) |
| **Prep**   | 30 min on Sunday 2026-05-17 (one full dry-run) |
| **Cook**   | 30 min live |
| **Pairs well with** | [Session 02 — Skills](session-02-skills.md) |

**Ingredients**

- A terminal that's big enough to read from the back of the room
- A working `claude` CLI on Mads's machine, logged in
- A blank `~/Code/hej-claude/` directory ready to demo from (delete it on Sunday so the live demo is genuinely empty)
- `stjernekig` working end-to-end (see [the project README](../README.md))
- One real `NASA_API_KEY` in `.env` so the demo doesn't hit the rate limit live
- Slack chat open with the install command pinned so people can copy-paste while Mads talks

**Method (talk track)**

| Time | Segment | What Mads does on screen |
| --- | --- | --- |
| 0–3   | **Survey the room** | *"Hvem kører Mac, hvem kører Linux? Hvem har installeret Claude Code allerede? Hvem har brugt den til rigtigt arbejde?"* Three quick hand-counts. Calibrates everything else. |
| 3–9   | **Install live**    | Show the install command in a terminal (don't actually re-install — `which claude` proves it's there). Walk through the options: `npm install -g @anthropic-ai/claude-code` (works on both Mac and Linux, needs Node 18+) · `brew install claude` (Mac convenience) · WSL for Windows. Mention the desktop app at <https://claude.ai/code>. Pin the command in the Slack channel as you say it. |
| 9–17  | **First project — `hej-claude`** | `mkdir ~/Code/hej-claude && cd ~/Code/hej-claude && claude`. Empty folder. First prompt: *"Lav `/init` så vi får en CLAUDE.md, og lav derefter et lille Python script der printer dagens dato på dansk. Brug plan mode først."* Watch plan mode kick in. Approve. Ship. Show the resulting CLAUDE.md and the script. Run it. *That's the workflow.* |
| 17–23 | **Switch to Stjernekig** | `switch stjernekig`. Open the project. Tour: `CLAUDE.md` (Claude reads this every session), `docker-compose.yml` (same shape as taenk), `site/web/modules/custom/stjernekig/` (the module), `docs/course-outline.md` (*"det her er hvad vi laver de næste 5 mandage"*). |
| 23–28 | **The planted bug**  | *"Jeg har lige importeret 10 dage, men der er kun 7 i galleriet. Hvad er der galt?"* Watch Claude read `drush wd-show`, find the video-day warnings, trace back to `ApodClient.php`. Enter plan mode. Two options: skip-and-log vs. ingest-as-video. **Don't fix it.** Stop after Claude has presented the plan. *"Det er præcis sådan en sag vi tager videre næste mandag — vi lader `/drupal-security` kigge på koden først."* |
| 28–30 | **Recap + tease**    | Three takeaways: (1) install command — pinned in Slack. (2) plan mode habit — ask before write. (3) project `CLAUDE.md` — Claude reads this. Tease Session 02. |

## Plating

- **Claude Code is a CLI, not a magic box.** Install, log in, use it. Same as `gh` or `drush`.
- **Plan first.** Two minutes of plan-mode beats twenty minutes of "wait, no, that's not what I meant".
- **`CLAUDE.md` is the conversation Claude has already had with this codebase.** Write it once, every future session benefits.

## Notes (gotchas)

- **Don't actually install live.** Have `claude --version` ready. Walking through `npm install` on stage is dead air — talk through the command while it's pinned in Slack, then move on.
- **The throwaway project must be FAST.** Cap at 8 minutes. If Claude asks 3 follow-up questions on the Python script, cut it short — the point is the workflow, not the script.
- **For the bug demo: do NOT take the fix.** Audience will *want* the fix. Hold the line — say it explicitly: *"Vi lader den ligge til næste gang, så `/drupal-security` har noget at finde."*
- **Rate limit.** Verify on Sunday that `.env` has a real API key, not `DEMO_KEY`. Otherwise the bug demo fails for the wrong reason and the story collapses.
- **/etc/hosts.** New machines need `127.0.0.1 stjernekig.docker`. Mads's machine already has it — but mention it in case anyone follows along.
- **Audience question to expect:** *"Hvad koster Claude Code?"* Honest answer: it's billed against your API account; for a typical day's coding it's a few dollars; cost goes up with cloud features like `/ultrareview` and `/schedule`. We'll cover budget controls in Session 05.

## Pre-flight checklist (Sunday 2026-05-17)

- [ ] `which claude && claude --version` — confirm install works
- [ ] `rm -rf ~/Code/hej-claude` — guarantee the throwaway folder is empty for Monday
- [ ] `cd ~/Code/stjernekig && docker compose down -v && rm -rf docker-volumes site/vendor site/web/core site/web/modules/contrib site/web/themes/contrib`
- [ ] `docker compose up -d && ./scripts/install.sh` — clean cold-install from a wiped repo
- [ ] Put a real `NASA_API_KEY` in `.env`
- [ ] `drush stjernekig:import 10` — confirm the bug fires (less than 10 nodes appear)
- [ ] `drush wd-show` — confirm warnings are clearly visible
- [ ] Time the whole live segment with a stopwatch — must fit in 25 minutes with a 5-min buffer
- [ ] Pin the install command in the Slack channel just before the session

## What does NOT happen this session

- ❌ No fix for the planted bug — that's S02
- ❌ No deep tour of `~/.claude/settings.json` — that's S04
- ❌ No skill demo — that's S02
- ❌ No talk about sub-agents, hooks, schedules, ultrareview — those are S03–S06

If the room asks for any of these, point at `docs/course-outline.md`. *"Det kommer."*
