# Where the data comes from

## The short version

NASA's Astronomy Picture of the Day is published as a WordPress custom post
type on `science.nasa.gov`. Its public REST API is the source for everything in
this archive:

```
GET https://science.nasa.gov/wp-json/wp/v2/image-article
      ?categories=22766          # the APOD category
      &orderby=date&order=asc
      &per_page=100&page=N       # up to 100 per request, X-WP-TotalPages header tells you how many
      &after=...&before=...      # ISO 8601, exclusive, on the post's local date
      &_fields=id,date,modified_gmt,slug,link,title,content,featured_image_url,migration_source_url
```

No API key, no documented rate limit. The importer still pauses between bulk
requests (`source.request_delay_ms`, default 250 ms) and sends a descriptive
User-Agent.

## Why not api.nasa.gov

The classic `https://api.nasa.gov/planetary/apod` endpoint was a scraper of
the `apod.nasa.gov` HTML pages. When APOD moved to `science.nasa.gov` (every
old URL now redirects there), that endpoint stopped returning real entries: as
of October 2026 it answers every date with the same "NASA Science" placeholder
and today's date with a 500. The whole 1995-to-today catalogue, 11,445 entries
at the time of writing, is in the WordPress API.

## What one entry looks like

The REST item carries the rendered article HTML. `ApodParser` picks it apart:

| Field in `ApodEntry` | From |
| --- | --- |
| `date` | `date` (post date, local time, the day the picture is for) |
| `title` | `title.rendered` with the `APOD: 2024 December 25 –` prefix removed; falls back to the `<h1>` in content |
| `mediaType`, `videoUrl` | An `<iframe>` (YouTube or Vimeo) or `<video>` in the hero block before the `<h1>` means a video day |
| `imageUrl` | The `<a href>` of the hero `<figure>` (full resolution asset), else the `<img src>`, else `featured_image_url`; CDN resize parameters stripped; generic placeholder thumbnails ignored |
| `explanationHtml` | `<p class="media-detail-hero__description">` with the "Explanation:" lead-in and the "Tomorrow's picture" trailer removed; only `a, em, strong, b, i, br, sub, sup, cite` kept; links to `apYYMMDD.html` rewritten to `/apod/YYYY-MM-DD` |
| `creditLabel`, `creditHtml` | The first row of the metadata table whose label mentions credit, copyright or licence. NASA sometimes writes "Image Credit & Copyright:" inside the value cell; that prefix becomes the label |
| `authors` | The "Authors & editors" row |
| `sourceUrl` | `link` |
| `legacyUrl` | `migration_source_url`, or derived as `https://apod.nasa.gov/apod/apYYMMDD.html` for entries created after the move |
| `remoteId`, `modified` | `id`, `modified_gmt` |

`tests/src/Unit/fixtures/` holds three trimmed real payloads (an image day, a
video day, the very first entry from 1995) and the parser tests run against
them. If NASA changes the markup, those tests are the first thing to fail.

## Rights

Most APOD pictures are copyrighted by the photographer and shown by NASA with
permission; NASA's own pictures are public domain. The credit label tells the
two apart, and `field_apod_copyrighted` records it. The setting
**Download pictures that carry a copyright notice** (on by default) controls
whether copyrighted pictures are stored locally. With it off, those entries
keep their text and metadata and link out to NASA for the picture.

## Gaps

APOD did not publish every single day, especially in 1995, and occasionally
skipped days since. When the source confirms it has nothing for a date, the
date is remembered in state (`stjernekig.known_missing`) so cron and
`fill-gaps` do not keep asking. Cron re-checks known-empty days once a week
in case NASA backfills; `drush stjernekig:forget-missing` clears the list.
