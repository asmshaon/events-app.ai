# events-app.ai

Two distinct **Event Visuals** browsing experiences built on a realistic, fully
seeded events dataset. The app adds image support end to end, reverse-geocodes
latitude/longitude into human-readable addresses, supports date & location
filtering, and includes an attendee/RSVP flow with confirmation and reminder
emails (3 days and 24 hours before an event).

**Stack:** Laravel 13 (PHP 8.3) · Inertia v3 · Vue 3 + TypeScript · Tailwind v4 ·
shadcn-vue (reka-ui) · Wayfinder · Fortify · Pest 4. Runs in Docker against
MySQL 8; the test suite uses in-memory SQLite.

---

## Quick start (Docker — recommended)

Docker is the primary dev path. Everything (app, Vite, MySQL, Mailpit, queue,
scheduler) comes up with one command.

**Prerequisites:** Docker + Docker Compose.

```bash
# 1. Clone
git clone https://github.com/asmshaon/events-app.ai.git
cd events-app.ai

# 2. Create the env file (the entrypoint syncs DB/mail settings into it)
cp .env.example .env

# 3. Build and start the whole stack
docker compose up -d --build
```

The first run installs PHP/Node dependencies, waits for MySQL, generates the app
key, and runs migrations automatically. Tail progress with:

```bash
docker compose logs -f app
```

### Seed the dataset

The seeder bulk-inserts events (with reverse-geocoded addresses and local
placeholder images) plus users. `SEED_ROWS` controls the count (defaults to
100 — keep it small for fast iteration; the full assignment dataset is far
larger).

```bash
docker compose exec app php artisan db:seed
```

> Reverse geocoding uses the OpenStreetMap Nominatim API and is rate-limited.
> Set `GEOCODER_ENABLED=false` in `.env` to skip live lookups and use the
> offline fallback when seeding large batches.

### Open the app

| Service   | URL                     | Notes                                            |
|-----------|-------------------------|--------------------------------------------------|
| App       | http://localhost:8080   | `/` redirects to the events visuals              |
| Vite      | http://localhost:5173   | dev server + HMR                                 |
| Mailpit   | http://localhost:8026   | catches all outgoing mail (confirmations etc.)   |
| MySQL     | localhost:3308          | db `events-app-ai`, user `events_db_usr` / `pass_not_secure` |

The two browsing pages live at **`/events-visual-1`** and **`/events-visual-2`**.

---

## Running artisan / composer / npm

Run any command inside the `app` container:

```bash
docker compose exec app php artisan migrate
docker compose exec app php artisan test          # uses in-memory SQLite
docker compose exec app composer lint
docker compose exec app npm run build
```

Host edits are live (the project root is bind-mounted), so **no rebuild is
needed for code changes**. Only rebuild (`docker compose build`) when changing
the Dockerfile or PHP/Node dependencies.

Stop the stack with `docker compose down` (add `-v` to also drop the MySQL
volume).

---

## Emails (RSVP confirmations & reminders)

- Confirmation mail is sent when someone RSVPs from an event detail page.
- Reminder mail fires **3 days** and **24 hours** before an event; the
  `scheduler` container runs `schedule:work` and the `queue` container processes
  the queued mailables.
- In local dev, all mail is caught by **Mailpit** at http://localhost:8026.

Fire reminders manually for testing (the `--days` override widens the lookahead
so far-out seeded events qualify):

```bash
docker compose exec app php artisan events:send-reminders            # real 3-day/24h windows
docker compose exec app php artisan events:send-reminders --days=1825 # ~5 years, for demos
```

In **production**, mail is sent via [Resend](https://resend.com). Set in `.env`:

```dotenv
MAIL_MAILER=resend
RESEND_API_KEY=your_resend_api_key
MAIL_FROM_ADDRESS="events@your-verified-domain.com"
MAIL_FROM_NAME="Events App"
```

> Resend only delivers to arbitrary recipients once you've **verified a domain**;
> the `onboarding@resend.dev` sender only reaches your own Resend account email.
> A queue worker must be running for queued mail to actually send.

---

## Quality gates

```bash
docker compose exec app composer test     # config:clear + pint + phpstan + artisan test
docker compose exec app composer ci:check # what CI runs: eslint + prettier + vue-tsc + composer test
```

Individual tools:

```bash
docker compose exec app composer lint        # Pint (PHP formatter)
docker compose exec app composer types:check # PHPStan (larastan, level 7)
docker compose exec app npm run lint         # ESLint
docker compose exec app npm run format       # Prettier
docker compose exec app npm run types:check  # vue-tsc
```

Run a single test:

```bash
docker compose exec app php artisan test --filter='filters the data'
docker compose exec app php artisan test tests/Feature/EventListingTest.php
```

---

## Native setup (without Docker)

Requires PHP 8.3+, Composer, Node, and a MySQL database.

```bash
composer setup   # install, copy .env, key:generate, migrate, npm build
composer dev     # serve + queue:listen + pail logs + vite, all at once
```

---

## Architecture

### Directory structure

```
app/
├── Console/Commands/SendEventReminders.php   # events:send-reminders (3-day / 24h)
├── Enums/ReminderType.php                     # 3_day | 24_hour
├── Http/
│   ├── Controllers/                           # tiny: wire request → service → response
│   │   ├── EventController.php                # index, visualOne, visualTwo, data, show
│   │   └── AttendeeController.php             # RSVP store
│   └── Requests/                              # validation lives here
│       ├── EventListingRequest.php            # filter querystring → filters()
│       └── StoreAttendeeRequest.php           # RSVP input, lowercases email
├── Mail/{AttendeeConfirmation,EventReminder}.php   # queued mailables
├── Models/                                    # Event, EventImage, Attendee, EmailReminder, User
└── Services/                                  # ← all DB/query/business logic
    ├── EventListingService.php                # paginate(), mapCard(), filterOptions()
    ├── AttendeeService.php                    # register() + queue confirmation
    └── ReverseGeocoder.php                    # lat/lng → address/city/country/timezone

database/
├── migrations/2026_06_19_0000{01..05}_*.php   # display columns + 4 new tables
├── factories/                                 # Event, EventImage, Attendee, EmailReminder
└── seeders/EventSeeder.php                    # bulk insert + geocode + placeholder images

resources/
├── js/
│   ├── app.ts                                 # bootstraps Inertia; assigns layouts by page name
│   ├── pages/Events/{Index,VisualOne,VisualTwo,Show}.vue
│   ├── components/ (+ components/ui = shadcn-vue)
│   ├── layouts/{app,auth,settings}/
│   ├── types/events.ts                        # card / filter DTOs
│   └── routes/ actions/ wayfinder/            # GENERATED by Wayfinder — don't hand-edit
└── views/
    ├── app.blade.php                          # the single HTML shell Inertia mounts into
    └── mail/{attendee-confirmation,event-reminder}.blade.php

routes/{web,console,settings}.php              # console.php schedules the reminder command
docker/php/{Dockerfile,entrypoint.sh}          # shared PHP image + startup logic
.github/workflows/deploy.yml                   # SSH deploy on push to main
tests/Feature/                                 # EventListing, Rsvp, EventReminder (Pest)
```

### Request flow (Inertia, not a REST API)

```
Browser → routes/web.php → Controller → Inertia::render('Page/Name', props)
        → app.ts resolves resources/js/pages/Page/Name.vue → Vue renders
```

There is one HTML shell (`app.blade.php`); each navigation is Inertia swapping the
Vue page component and props over XHR. Layouts are assigned **by page-name
convention** in `app.ts` (no per-page imports). Shared props (auth user, app name,
sidebar state) come from `HandleInertiaRequests::share()`. **Wayfinder** generates
typed route/action helpers from PHP into `resources/js/routes` + `actions` at build
time — those folders are generated, never hand-edited.

### Shell-then-fetch data pattern

The dataset is large, so lists are never passed through Inertia props. Instead:

1. The controller renders a light page shell (filter options + statuses only).
2. Vue mounts, then fetches `GET /events/data?<filters>` over XHR.
3. An `IntersectionObserver` requests more pages — offset `paginate(50)`, infinite scroll.
4. `/events/data` returns `{ data, current_page, last_page, total, stats: { ms, bytes } }`
   so query time + payload size stay visible.

All four event pages follow this approach.

### Backend layering

```
Controller (tiny) → Form Request (validation) → Service (all logic) → Model
```

Controllers contain no queries; Form Requests own validation; Services hold every
DB query and business rule (`EventListingService` builds the filtered/paginated card
list and cached filter options; `AttendeeService` does dedup-safe RSVP + queues the
confirmation; `ReverseGeocoder` turns coordinates into addresses with a cache and
offline fallback). See `.claude/CODING_GUIDELINES.md`.

### Event data model

UUID primary key, `$guarded = []`, deliberately thin columns plus a **`payload` JSON
blob** holding the rich info (`name`, `description`, `venue`, `pricing`, `schedule`,
`tags`). Promoted, indexed columns (`starts_at`, `timezone`, `address`, `city`,
`country`) were added so filtering/sorting hit indexes instead of JSON. `created_time`
is a Unix **start** time, not a creation timestamp. New tables: `event_images`,
`attendees`, `email_reminders`, and `geocoded_locations` (the geocode cache).

### Async work (queue + scheduler)

```
RSVP      → AttendeeService queues AttendeeConfirmation ─┐
scheduler → events:send-reminders (hourly) queues EventReminder ─┤→ database queue
                                                                  └→ worker → Resend / Mailpit
```

Reminders are idempotent: an `email_reminders` row (unique on attendee + type) is
written before queuing, so repeated runs never double-send.

---

## Deployment

Pushing to `main` triggers the GitHub Actions workflow
(`.github/workflows/deploy.yml`), which SSHes into the server, pulls the latest
code, installs dependencies, builds frontend assets, runs migrations, and
reloads PHP-FPM. The server `.env` (DB credentials, `APP_KEY`, mail settings) is
managed on the server and never committed.
