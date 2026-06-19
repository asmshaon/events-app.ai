# Architecture & Implementation Notes

A short record of the decisions made while building the Event Visuals feature,
and the trade-offs behind them.

## Architecture

- The application follows a **Clean Architecture** approach to keep the codebase
  modular, maintainable, and easy to extend. Controllers stay thin, validation
  lives in Form Requests, and all query/business logic is isolated in
  `app/Services/` so it can be tested and reused independently of the HTTP layer.
- It is a **full-stack** application, with both the frontend (Inertia + Vue 3 +
  TypeScript) and the backend (Laravel) contained within the same codebase.
- The primary database is **MySQL**. For the test suite, **SQLite (in-memory)**
  is used to simplify setup and keep test runs fast and isolated from dev data.

## Browsing & performance

- The two visual pages use a **shell-then-fetch** pattern: the page ships a light
  shell, then lazy-loads events from a JSON endpoint (`/events/data`) with offset
  pagination and infinite scroll. The large seeded dataset is never passed
  through Inertia props.
- The `events` table keeps its rich data in a `payload` JSON blob; frequently
  filtered/sorted fields (`starts_at`, `timezone`, `city`, `country`) were
  promoted to **indexed columns** so filtering by date and location hits indexes
  rather than scanning JSON.

## Testing data

- For demonstration and testing, the database is seeded with **100 sample
  events** (configurable via `SEED_ROWS`).
- Event **addresses are generated during the seeding process** (reverse-geocoded
  once against a small set of anchor coordinates and cached), so no external
  geocoding or real-time API calls are required when browsing events.
- **Reminder emails are configured to be sent immediately during testing**
  (the scheduled command runs with a widened lookahead window) to simplify
  verification of the notification flow. The real 3-day / 24-hour windows remain
  the default behaviour of the command.

## Email & queues

- Confirmation and reminder emails are **queued** rather than sent inline, so the
  RSVP request returns immediately.
- Queue jobs currently use the **database driver** for simplicity. In a
  production environment I would prefer **Redis** for better performance and
  scalability.
- Locally, all outgoing mail is caught by **Mailpit**. In production it is sent
  via **Resend**.

## Timezones

- Event times are displayed using the **event's local timezone**, not the
  viewer's timezone. Events are global, so showing each event in the timezone
  where it actually takes place is the least ambiguous choice for browsing.

## Not implemented

The following are intentionally left out of this version to keep the scope
focused on the assignment:

- Authentication and authorization (the auth scaffold from the starter kit is
  present but unused; all event browsing and RSVP routes are public).
- API rate limiting.
- Viewer-local timezone display (times are shown in the event's local timezone,
  as noted above).
