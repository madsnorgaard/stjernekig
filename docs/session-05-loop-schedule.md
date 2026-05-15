# Session 05 — `/loop` and `/schedule` ⏰

> **Vibe:** *Lumbergh from Office Space.*
> "Mads, hvis du kunne nå at sætte den daglige APOD-import op som en routine i dag... det ville være rigtig godt."
> Two minutes later: it's running. Without you.

**Date:** TBD (after Session 04)
**Length:** 30 min
**Prereq:** Sessions 01–04 shipped. Repo currently uses `hook_cron()` for the daily APOD pull, which is fragile (requires real Drupal cron + a running site).

---

## Recipe card

| | |
| --- | --- |
| **Serves** | Eksponent dev team |
| **Prep**   | 10 min (set up the routine on Sunday so a first fire is visible during the session) |
| **Cook**   | 25 min |
| **Pairs well with** | [Session 06 — `/ultrareview`](session-06-ultrareview.md) |

**Ingredients**

- `/loop` skill (interactive recurring tasks during a session)
- `/schedule` skill (cron-style remote routines)
- The current `stjernekig_cron()` hook in `stjernekig.module`
- Anthropic-side scheduling credentials (this costs money — Mads to confirm beforehand)

**Method (talk track)**

| Time | Segment | What Mads does on screen |
| --- | --- | --- |
| 0–4   | Hook                | Show `stjernekig.module`'s `hook_cron()`. Ask: *"Hvornår køres den her i produktion?"* Pause. Honest answer: only if someone set up `drush cron` in a real cron. For a demo site? Maybe never. |
| 4–10  | `/loop`             | Live demo: *"Kør `drush stjernekig:peek $(date +%Y-%m-%d)` hver 2. minut og fortæl mig hvis svaret ændrer sig"*. Watch it fire twice during the session. |
| 10–22 | `/schedule`         | Build a remote routine: "Every weekday 07:00 København-tid, run `drush stjernekig:import 1` and report the result in Slack." Show the schedule list, confirm the next run time. Discuss costs and how to stop. |
| 22–27 | Retire `hook_cron`  | Delete the cron hook from `stjernekig.module`. The routine has replaced it. Honest tradeoff: production sites still need real cron; but for dev/demo/ops chores, the routine is more reliable. |
| 27–30 | Recap + teaser      | *"Næste gang lader vi 4 cloud-agenter læse hele projektet igennem og fortælle os hvad der er galt."* |

## Plating

- `/loop` is for **the current session** — a recurring task during a focused work block.
- `/schedule` is for **the cron slot you don't want to babysit** — a remote routine.
- Pick by *cache cost* and *autonomy level*: `/loop` keeps your local conversation warm; `/schedule` runs Claude in the cloud while you're at lunch.
- Routines should be **observable**: log to Slack, email, or somewhere you'd notice the silence.

## Notes

- **Cost.** Scheduled runs bill against your Anthropic account. Set a cap before the session. Test with a 1-day TTL.
- **NASA rate limit.** A daily routine with `DEMO_KEY` won't survive — must use a real API key.
- **`hook_cron` is not "wrong".** It's the right tool for in-app cron. The session is about *operational* cron, not in-app cron. Be clear about the distinction.
- **Audience question to expect:** *"Kan jeg bruge det til at babysitte en PR?"* Yes — `/schedule` is great for that. See parking lot.

## Code that should land in `main` after this session

```
- stjernekig.module: hook_cron()       — retired
+ docs/routines.md                     — description of the daily APOD routine + how to stop it
+ .claude/schedule.yaml (if the skill writes one) — the routine config
```
