# Internal Content Hub Design

## Goal

Replace LinkedIn-backed knowledge cards with a first-party publishing system inside the existing Laravel application. Administrators can create articles, lessons, lectures, and courses without depending on an external platform.

## Publishing model

Every content record is free and public by default. An administrator may switch a record to `subscribers`, which means the visitor provides a valid email address and consent; it never means a paid subscription. Content supports draft, scheduled, published, and archived lifecycle states.

Courses compose existing lessons and lectures into ordered sections. The same content and access rules power the website, homepage, sitemap, and public API, so there is one source of truth.

## Editor and media

The admin editor uses locally bundled open-source Tiptap packages through Vite, with no CDN dependency. It supports RTL text, headings, lists, tasks, links, images, tables, code, highlights, YouTube, word counts, fullscreen editing, JSON/HTML synchronization, and preview.

Uploaded media is validated on the server, stored under randomized public paths, and recorded in the database. HTML is sanitized through a server-side allowlist before it is stored or rendered.

## Admin experience

The existing authenticated admin panel gains:

- Searchable, filterable content management.
- Create, edit, schedule, publish, archive, and restore actions.
- Explicit public or email-subscriber access selection.
- Course curriculum management.
- Subscriber management and CSV export.
- Media upload and selection.

## Public experience

- `/blog` lists published articles, lessons, lectures, and courses with type filters.
- `/blog/{slug}` shows the full public record or an email gate for subscriber records.
- Email unlock persists in the session and is also available to API clients through `X-Content-Token`.
- Homepage knowledge cards point to the latest internal records.
- Published records are added to the sitemap.

## Security and privacy

Admin routes use the existing authentication and admin middleware. Uploads reject executable and SVG payloads. Subscriber tokens are random and stored only as SHA-256 hashes. Consent time and source are recorded. API and Blade use the same access service to prevent policy drift.

## Acceptance criteria

The feature is complete when all content types can be authored and published from the admin panel, the public library no longer depends on LinkedIn, gated bodies never leak before email unlock, courses render their curriculum, API and sitemap consume internal content, the editor builds locally, and focused automated tests pass.
