# Session 04 — Hooks 🪝

> **Vibe:** *"This is fine" dog in the burning room.*
> That's you, committing PHP without `phpcs`. The fire is real. The hook is the dog leaving the room.

**Date:** TBD (after Session 03)
**Length:** 30 min
**Prereq:** Sessions 01–03 shipped. Repo has enough PHP that style violations would feel real.

---

## Recipe card

| | |
| --- | --- |
| **Serves** | Eksponent dev team |
| **Prep**   | 5 min |
| **Cook**   | 25 min |
| **Pairs well with** | [Session 05 — `/loop` & `/schedule`](session-05-loop-schedule.md) |

**Ingredients**

- A small, deliberate phpcs violation introduced in a session-03 commit (Mads adds it on Sunday before the session — e.g., long line, missing docblock)
- `/update-config` skill (for editing `settings.json` safely)
- `/fewer-permission-prompts` skill (to clean up noise that's accumulated)
- A working `drupal-check` install in the `php` container (already there via composer dev-deps)

**Method (talk track)**

| Time | Segment | What Mads does on screen |
| --- | --- | --- |
| 0–4  | Hook              | Type a clean prompt: *"Tilføj en validator til ApodClient."* Claude writes code that violates Drupal coding standards (because there's no hook). Commit it. Nothing stops you. *"Det er fint. Alt er fint."* |
| 4–10 | What hooks are    | Open `~/.claude/settings.json`. Show the existing structure. Explain: hooks run as shell commands on lifecycle events (before tool use, on stop, etc.). The harness executes them — not Claude. |
| 10–18 | Add a Stop hook   | Use `/update-config` to add a Stop hook that runs `drupal-check` against modified PHP files. Demonstrate it triggering on your next edit. Watch Claude get the failure, propose a fix, retry. |
| 18–24 | `/fewer-permission-prompts` | Run the skill. Watch it scan transcript history and suggest an allowlist for common safe commands (`drush status`, `composer show`, etc.). Apply the suggestions. |
| 24–28 | Recap             | Show the new `settings.json` diff. Explain: hooks let you delegate trust to the harness, not the model. |
| 28–30 | Teaser            | *"Næste gang sætter vi Claude til at køre det daglige APOD-import helt selv — ingen knapper at trykke på."* |

## Plating

- A hook is the only way to make Claude *not* be able to skip a step. Memory and CLAUDE.md describe preferences; hooks enforce them.
- Hooks should be **fast and idempotent** — they run on every event.
- `/fewer-permission-prompts` is the inverse: instead of stopping things, it pre-approves the boring ones.

## Notes

- **`drupal-check` needs the php container.** Hook command: `docker compose exec -T php drupal-check $FILE` (or similar). Test it manually first.
- **Hooks can be invasive.** Don't add a `PreToolUse` hook that blocks Write — you'll wedge yourself. Stop hooks are safer to start with.
- **`/update-config` reads + writes `settings.json`.** Don't hand-edit during the demo — let the skill do it so the audience sees the workflow.
- **Audience question to expect:** *"Hvordan deler vi hooks med kolleger?"* Answer: commit `.claude/settings.json` to the repo; project-level settings get picked up.

## Code that should land in `main` after this session

```
+ .claude/settings.json                — Stop hook + permission allowlist
+ docs/session-04-notes.md             — copy of the hook command for reference
~ Whatever the hook flagged on the planted violation
```
