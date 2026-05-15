# Session 02 — Skills 🛒

> **Vibe:** *Drake meme.*
> 🙅 Bottom panel: writing 14 paragraphs of "you are a senior Drupal security expert, please consider..."
> 🙆 Top panel: `/drupal-security`

**Date:** TBD (after 2026-05-18)
**Length:** 30 min
**Prereq:** Session 01 shipped. The video-day bug was *identified* but not fixed — this session picks up exactly where S01 left off, and asks `/drupal-security` to weigh in before we touch anything.

---

## Recipe card

| | |
| --- | --- |
| **Serves** | Eksponent dev team (8–12 ppl) |
| **Prep**   | 5 min (audience reads the diff from session 1) |
| **Cook**   | 25 min |
| **Pairs well with** | [Session 03 — Sub-agents](session-03-subagents.md) |

**Ingredients**

- The S01-noted bug still unfixed (the whole point — we let the skill weigh in first)
- `/drupal-security` skill (installed globally — confirm with `ls ~/.claude/skills/` before the session)
- `/drupal-expert` skill
- Optional: a NASA video-day URL in scope to discuss attack surface honestly

**Method (talk track)**

| Time | Segment | What Mads does on screen |
| --- | --- | --- |
| 0–3   | Recap from S01    | Show the diff state from Monday — the video-day bug we identified but didn't fix. Read the plan-mode output back. *"Vi lod skillen kigge først. Lad os se hvad den finder."* |
| 3–13  | `/drupal-security` | Run the skill on `src/Service/ApodClient.php` + `ApodImportWorker.php`. Watch it surface: the original video bug PLUS no host allowlist (SSRF risk), no MIME/extension validation on the downloaded file, log message lacks the date that failed. |
| 13–20 | Apply the fix     | Approve plan-mode fix that bundles both: the video-day skip from S01 + allowlist `apod.nasa.gov` and `*.gsfc.nasa.gov` + validate extension in `[png, jpg, jpeg, gif, webp]` before `writeData()` + enrich the warning log. |
| 20–27 | `/drupal-expert`  | Brief demo. Hand it the worker; let it suggest splitting fetch/persist into separate methods or services. Don't take every suggestion — pick one to ship live. |
| 27–30 | Recap + teaser    | Show the diff. Note: "vi spørger ikke Claude pænt om sikkerhed — vi kalder den skill der er bygget til det." Tease Session 03. |

## Plating (what should land for the audience)

- The skill is *less typing*, not *more magic*. It's a contract: "this kind of file gets this kind of audit."
- Skills shine when their scope matches your task. Don't use `/drupal-security` to format code.
- `/drupal-expert` is opinionated. You don't have to take its suggestions. Disagreement is data.

## Notes (gotchas)

- **Skills must be installed**. Check `ls ~/.claude/skills/` first. If `drupal-security` is missing, the demo dies — install via `agent-resources` per the global MEMORY.md.
- **The room will ask: "do skills replace prompts?"** Answer: no. Skills are pre-built prompts with curated context. You still write prompts for everything else.
- **Watch for over-fitting.** `/drupal-security` will flag the planted bug too. That's fine — show how to triage which findings are urgent.
- **`/drupal-expert` is chatty.** Cap the demo at 5–7 min or it eats the recap.

## Code that should land in `main` after this session

```
+ ApodClient::validateHost()        — allowlist check
+ ApodClient::validateExtension()   — file extension allowlist
~ ApodImportWorker::processItem()   — uses the validators, richer log
```

(Or whatever variant survives the live discussion.)
