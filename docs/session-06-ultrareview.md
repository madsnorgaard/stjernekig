# Session 06 — `/ultrareview` 🔍

> **Vibe:** *Galaxy brain meme, max level.*
> 🧠 Reading the diff yourself
> 🧠🧠 Asking a colleague
> 🧠🧠🧠 Asking Claude to review
> 🧠🧠🧠🧠 Four specialised cloud agents reading your branch in parallel, each from a different angle

**Date:** TBD (after Session 05)
**Length:** 30 min
**Prereq:** Sessions 01–05 shipped. Repo now has ~6 commits worth of real changes — security fix, refactor, new feature, hooks, routine.

---

## Recipe card

| | |
| --- | --- |
| **Serves** | Eksponent dev team |
| **Prep**   | 10 min (Mads pushes a `session-06-review` branch with the cumulative diff so `/ultrareview` has a target) |
| **Cook**   | 25 min |
| **Pairs well with** | A team retro — see the parking lot for follow-ups |

**Ingredients**

- The `session-06-review` branch (or main, if you've kept everything on main)
- `/ultrareview` skill (it's the multi-agent cloud review — user-triggered, billable; you cannot invoke it from inside Claude Code, only manually)
- A printed (or screen-shared) version of the diff for the room to follow
- ~10 minutes of audience attention for the triage discussion

**Method (talk track)**

| Time | Segment | What Mads does on screen |
| --- | --- | --- |
| 0–3   | Hook                | *"Vi har shippet 5 sessioner. Hvor mange af jer har faktisk læst hele diff'en?"* (Probably none.) |
| 3–8   | Run `/ultrareview`  | Trigger it on the branch. Show what's happening — 4 cloud agents spinning up, each with a different perspective (security, perf, style, architecture). |
| 8–18  | Wait + read         | The review takes a few minutes. Use the wait time to talk about *when* this is worth running: before merging a long-lived branch, before a release, after an AI-heavy work block. Not for every PR. |
| 18–27 | Triage the report   | The report lands. Read out findings as a room. Mark each: 🟢 fix now (small wins) · 🟡 ticket it · 🔴 disagree (here's why). Apply 1–2 fixes live. |
| 27–30 | Recap + close       | *"Vi er færdige med Phase 1 af serien. Hvad er det næste vi skal lære?"* Open the floor. Capture into [future-sessions.md](future-sessions.md). |

## Plating

- `/ultrareview` is **a tool for moments, not for every PR.** Use it when the cost of a missed bug is high (releases, refactors, AI-heavy work).
- The value isn't the findings — it's the **forced triage conversation**. A team that disagrees with a finding is a team that's thinking.
- "I disagree with the reviewer" is a valid output. AI is not the architect.

## Notes

- **It costs real money.** Confirm with the team before triggering, and make the cost visible during the session ("denne kører nu — ~$X").
- **It can be slow.** Plan for 5–10 minutes of wait time. Have a backup discussion topic ready.
- **You cannot trigger it from inside Claude Code.** Only manually. This is a feature — keeps spend predictable.
- **The 4 agents will sometimes disagree with each other.** That's the most useful output — read those carefully.

## Code that should land in `main` after this session

```
~ Whatever the team agreed to fix during triage
+ docs/session-06-findings.md          — the review report + decisions per finding
~ future-sessions.md                   — updated parking lot from the closing discussion
```

## Closing the series

After Session 06, the team has experienced:

- **Local setup, plan-mode, and debugging** (S01)
- **Skills** for specialised work (S02)
- **Sub-agents** for parallel research (S03)
- **Hooks** for enforced workflow (S04)
- **`/loop` & `/schedule`** for autonomous tasks (S05)
- **`/ultrareview`** for high-stakes review (S06)

That covers the breadth of Claude Code in 6 sessions × 30 min = 3 hours total. Anything deeper goes into a Phase 2 series — see [future-sessions.md](future-sessions.md).
