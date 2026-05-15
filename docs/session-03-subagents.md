# Session 03 — Sub-agents 🤖🤖🤖

> **Vibe:** *Avengers Assemble.*
> One agent looking at one file is fine. Three Explore agents searching three corners of the codebase in parallel — now we're cooking.

**Date:** TBD (after Session 02)
**Length:** 30 min
**Prereq:** Sessions 01 and 02 shipped. Repo has at least one real feature and one security pass.

---

## Recipe card

| | |
| --- | --- |
| **Serves** | Eksponent dev team |
| **Prep**   | 5 min (audience sees the new feature request) |
| **Cook**   | 25 min |
| **Pairs well with** | [Session 04 — Hooks](session-04-hooks.md) |

**Ingredients**

- A vague-but-realistic feature request (Mads brings it): *"Vi vil have at man kan importere en custom date range, ikke kun de sidste N dage."*
- The `Agent` tool with the `Explore` subagent type
- Access to at least one other Eksponent project (e.g., `taenk-test-administration`) so cross-repo search has something to find

**Method (talk track)**

| Time | Segment | What Mads does on screen |
| --- | --- | --- |
| 0–3   | Hook                | Read the feature request out loud. Resist the urge to start typing. Ask: *"Hvad gør I først?"* — most people say "read the code." |
| 3–7   | One agent, no plan  | Show what it looks like to ask Claude to "just implement it" — Claude flails, asks 4 questions, gets confused. Stop after 2 prompts. |
| 7–18  | Three Explore agents in parallel | Re-prompt: *"Inden du skriver kode — undersøg 1) hvilke patterns vi allerede har til date-range queries i stjernekig, 2) hvordan taenk-test-administration importerer fra eksterne kilder, 3) om der findes contrib-moduler vi burde overveje."* Watch three parallel Explore agents in one tool call. |
| 18–25 | Synthesize          | Claude reports back, picks a pattern, presents a plan. Approve it. Implement (short — 5 min budget). |
| 25–30 | Recap + teaser      | Show the diff. The feature works AND fits existing patterns. Tease Session 04: *"Næste gang får vi en hook til at fange det her, før vi committer."* |

## Plating

- Sub-agents are a **context budget** tool. Three Explore agents can read 30 files without flooding the main conversation.
- Use them for *research* before *change*. Don't delegate writing — delegate looking.
- Parallel ≠ always faster; parallel = independent. Use it when the questions don't depend on each other.

## Notes

- **The Explore agent is read-only.** It cannot edit. Make this explicit to the room — it's a feature, not a limitation.
- **Cross-repo search.** Explore can read anything Claude can read. Mention `~/.claude/CLAUDE.md` constraints if any.
- **The room will compare to Cursor's @-symbol.** Honest answer: different tool. Cursor is a smarter IDE, sub-agents are a delegation pattern.
- **If parallel agents return contradicting results**, that's also valuable data — it means the codebase is inconsistent.

## Code that should land in `main` after this session

```
+ drush stjernekig:import-range <from> <to>
+ Tests for boundary cases (if there's time — otherwise note as TODO)
~ docs/session-03-notes.md             — short list of patterns discovered
```
