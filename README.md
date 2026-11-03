# PupWatch

> An uptime monitor with a pixel-art dog. Add your websites, and PupWatch checks them every
> minute. The dog is happy when your site is up, panting when it is slow, sad when it is down.

**Stack:** Next.js (TypeScript) · Tailwind CSS · Laravel (PHP 8.3) · MySQL 8 · Telegram Bot API ·
Discord Webhooks

📖 Full requirements: [PupWatch_PRD.md](PupWatch_PRD.md) · 🧠 Build decisions: [docs/decisions.md](docs/decisions.md)

## Architecture

```
┌──────────────────┐   HTTPS   ┌────────────────────────────┐  SQL  ┌──────────┐
│ Next.js (web)    │──────────▶│ Laravel API                │──────▶│ MySQL 8  │
│ SSR status pages │           │ Sanctum · Policies         │       └──────────┘
└──────────────────┘           │ Scheduler · Queue workers  │
                               └──────┬──────────┬──────────┘
                                      │          │
                          HTTP checks ▼          ▼ Alerts
                              user websites   Telegram API / Discord / Mail
```

- **`apps/web`** — Next.js App Router frontend: landing page, dashboard, monitor detail,
  status page builder and the public `/status/[slug]` pages. Auth happens through the API with
  an httpOnly cookie managed by Next.js route handlers, so the token is never reachable from
  browser JavaScript.
- **`apps/api`** — Laravel REST API (`/api/v1`), the check engine, the incident state machine
  and alert delivery. Business logic lives in `app/Services`; controllers stay thin.
- **MySQL 8** — `monitors`, `check_results` (high volume, 30-day retention), `incidents`,
  `daily_stats` (400-day rollups), channels and status pages.

### Scheduling design

1. `monitors.next_check_at` (indexed) holds when each monitor is due.
2. `DispatchDueChecks` runs every minute and, for each due monitor, performs an **atomic claim**:
   `UPDATE monitors SET next_check_at = … WHERE id = ? AND next_check_at <= now()` — the job is
   dispatched only when exactly one row was affected, so two workers can never check the same slot.
3. `RunMonitorCheck` runs on the `checks` queue, performs the SSRF-guarded HTTP request, stores a
   `check_results` row and feeds the incident state machine. Checks are never retried: a failed
   check is data.
4. `RecoverStuckClaims` (every 5 minutes) releases claims whose worker died.
5. Alerts go to a separate `alerts` queue so delivery is never starved by check traffic.

## Getting started

### Docker (recommended)

```bash
cp apps/api/.env.example apps/api/.env
cp apps/web/.env.example apps/web/.env.local
docker compose up --build
```

- Web → http://localhost:3000
- API → http://localhost:8000/api/v1/up
- MySQL → localhost:3306 (`pupwatch` / `secret`)

### Local development

API (SQLite, no database service needed):

```bash
cd apps/api
composer install
cp .env.example .env            # then set DB_CONNECTION=sqlite, DB_DATABASE=database/database.sqlite
php artisan key:generate
touch database/database.sqlite
php artisan migrate
php artisan serve               # http://localhost:8000
php artisan queue:work --queue=checks,alerts
php artisan schedule:run        # run every minute, e.g. via cron
```

Web:

```bash
cd apps/web
npm install
cp .env.example .env.local
npm run dev                     # http://localhost:3000
```

### Tests

```bash
cd apps/api && php artisan test    # PHPUnit, in-memory SQLite, HTTP faked
cd apps/web  && npm run lint && npx tsc --noEmit && npm run build
```

CI runs both on every push and pull request (`.github/workflows/ci.yml`).

## Repository layout

```
pupwatch/
├── apps/
│   ├── web/                      # Next.js (App Router, TypeScript, Tailwind)
│   │   └── src/app/…
│   └── api/                      # Laravel
│       ├── app/Services/         # CheckService, SsrfGuard, IncidentService, AlertService…
│       ├── app/Jobs/
│       ├── app/Http/Controllers/Api/
│       ├── app/Policies/
│       └── tests/
├── docker-compose.yml
├── .github/workflows/ci.yml
├── docs/decisions.md
└── PupWatch_PRD.md
```

## Milestones

| # | Milestone | Status |
|---|---|---|
| 0 | Setup: monorepo, Laravel + Next.js, Docker Compose, env examples, CI | ✅ |
| 1 | Auth (Sanctum cookie flow) + monitor CRUD + dashboard list | ✅ |
| 2 | SSRF guard + check engine, dispatcher with atomic claim | 🚧 |
| 3 | Incidents + Telegram/Discord alert channels | ⬜ |
| 4 | Dashboard, charts, uptime, mascot moods | ⬜ |
| 5 | Daily rollups, retention, public status pages | ⬜ |
| 6 | Polish: landing page, rate limits, seed data, docs | ⬜ |