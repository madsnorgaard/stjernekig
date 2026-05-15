# Session 02 — Skills

**Date:** TBD (after session 01 on 2026-05-18)
**Length:** 30 minutes
**Prereq:** Session 01 — APOD importer up and running, the video-skip fix shipped.

## Planned demos

1. **`drupal-security` skill audits `ApodClient`.**
   The fix from session 1 doesn't sanitize the URL it downloads. Run the skill on the file, watch it flag the missing host allowlist and the lack of file-type validation. Apply the fix it suggests.

2. **`drupal-expert` skill refactors the worker.**
   The queue worker has grown a bit. Use the skill to suggest improvements — dependency-injected services, separation of fetch vs. persist, easier testing.

3. **`drupal-migration` skill imports a legacy APOD CSV.**
   Hand Claude a CSV of historical APODs (say, 1995–2000) and ask it to use the migration skill to write a Migrate Plus YAML migration. Watch it scaffold the migration source, process, and destination.

## Open questions to answer before session 02

- Which Skill demo is the cleanest *single* one to anchor the session? (Probably `drupal-security`, since it's a continuation of session 01's fix.)
- Should the audience clone the repo and follow along, or watch-only?
