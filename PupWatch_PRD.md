# PupWatch — Product Requirements Document

> An uptime monitor with a pixel-art dog. Add your websites, and PupWatch checks them every minute. The dog is happy when your site is up, panting when it is slow, sad when it is down. Get alerts on Telegram or Discord, and share a public status page with your clients.

**Version:** 1.0
**Owner:** Abdullah
**Builders:** Abdullah + AI coding agent
**Stack:** Next.js (TypeScript) + Tailwind CSS · Laravel 11 (PHP 8.3) REST API · MySQL 8 · Telegram Bot API · Discord Webhooks

---

## 1. Why this project

### 1.1 Purpose
Freelancers, small agencies and indie developers host client websites and side projects, and they usually find out a site is down from an angry client. Big tools (UptimeRobot, Better Stack) work, but they are plain. PupWatch is a small, friendly, genuinely useful monitor: it checks sites on a schedule, alerts you where you already chat, and gives you a clean public status page to share.

### 1.2 Why it works as a portfolio project
This is deliberately different from a CRUD app. It shows backend depth:

| Skill | Where it shows up |
|---|---|
| Background processing | Scheduler plus queue workers running thousands of checks per hour |
| Concurrency safety | Atomic claiming of due checks so nothing runs twice |
| Security engineering | SSRF protection (the app makes HTTP requests to user-supplied URLs) |
| Integrations | Telegram bot (webhook + account linking), Discord webhooks |
| Data engineering | High-volume time-series table, daily rollups, retention policy |
| Multi-tenancy | Every user has their own monitors, channels and status pages |
| Frontend | Charts, live-ish dashboard, public SEO status pages, animated mascot |
| Engineering practice | Tests with faked HTTP, CI, Docker, observability |

### 1.3 Goals
1. Reliable checks with no duplicates and no missed runs under normal load.
2. Alerts that are fast and not noisy (no flapping spam).
3. A public status page good enough to hand to a real client.
4. A mascot and UI with personality, without hurting clarity.
5. Safe by design: users must not be able to use PupWatch to attack internal networks.

### 1.4 Non-goals (v1)
- No multi-region checks (single region only).
- No paid plans or billing (plan limits are hardcoded).
- No browser or synthetic transaction monitoring.
- No team or organization accounts.
- No mobile app (responsive web only).

---

## 2. Target users

| Persona | Description | Need |
|---|---|---|
| **Freelancer Farhan** | Hosts 5 to 15 client sites | Knows before the client does; can send a status link |
| **Indie Imran** | Runs side projects and small SaaS | Cheap, simple alerts on Telegram/Discord |
| **Agency Amna** | Small agency lead | One place to see all client sites, branded status pages |

---

## 3. Core concepts

- **Monitor:** a URL (or host/port) checked on a schedule.
- **Check result:** one execution of a monitor (status, response time, error).
- **Incident:** a period during which a monitor is considered down.
- **Channel:** a destination for alerts (Telegram chat, Discord webhook, email).
- **Status page:** a public page showing selected monitors and incident history.
- **Mood:** the mascot's state derived from monitor health.

### 3.1 Mascot moods

| Mood | Condition | Visual |
|---|---|---|
| `happy` | Last check success and response under 1000 ms | Tail wagging |
| `panting` | Success but response 1000 ms or more, or recent flapping | Tongue out, sweat drop |
| `sad` | Monitor is down (open incident) | Ears down, rain cloud |
| `sleeping` | Monitor paused | Zzz |
| `curious` | No checks yet / first check pending | Head tilt, question mark |

Mascot is drawn as inline SVG/CSS sprites (original artwork, no copyrighted characters), animated with CSS only and respecting `prefers-reduced-motion`.

---

## 4. Features

Priority: **P0** = MVP, **P1** = should ship, **P2** = stretch.

### 4.1 Authentication (P0)
- Register and login with email and password (Laravel Sanctum).
- Email must be unique; password minimum 10 characters; hashed with bcrypt/argon2.
- Logout revokes the token. Password change in settings.
- GitHub OAuth login (P1).
- Account deletion removes all data.

**Acceptance criteria**
- Tokens never reachable from browser JavaScript (httpOnly cookie managed by Next.js route handlers).
- Login and register endpoints are rate limited (5 attempts per minute per IP+email).

### 4.2 Monitors (P0)
Create, edit, pause/resume, delete.

**Fields**
- `name` (required, max 100)
- `url` (required, http or https only)
- `method`: `GET` or `HEAD` (default `GET`)
- `interval_seconds`: 60, 300, 900 (default 300)
- `timeout_seconds`: 5 to 30 (default 10)
- `expected_status`: list of accepted codes/ranges (default `200-399`)
- `keyword` (optional) and `keyword_mode`: `must_contain` | `must_not_contain`
- `follow_redirects` (default true, max 5 hops)
- `custom_headers` (optional, max 5, values encrypted at rest)
- `alert_after_failures`: 1 to 5 (default 2)

**Plan limits (hardcoded in v1):** 10 monitors per user, minimum interval 60 s.

**Acceptance criteria**
- Creating a monitor triggers a first check within 60 seconds.
- Invalid or blocked URLs are rejected at save time with a clear error (see SSRF section).
- Pausing stops checks immediately; resuming schedules the next check in under one interval.

### 4.3 Check engine (P0)
A check is one HTTP request performed by a queued job.

**Procedure**
1. Resolve DNS for the host, validate every resolved IP against the SSRF blocklist, pin the connection to the validated IP.
2. Send the request with timeout, redirect limit and response-size cap (read at most 1 MB when a keyword is used, 0 bytes for `HEAD`).
3. Record: `status_code`, `response_time_ms`, `is_success`, `error_type`, `error_message`, `checked_at`, `dns_ms` (P1), `ssl_days_remaining` (P1).
4. Evaluate against expectations (status match, keyword match).
5. Pass the result to the incident state machine.

**Error types:** `timeout`, `dns_failure`, `connection_refused`, `ssl_error`, `unexpected_status`, `keyword_missing`, `keyword_found`, `too_many_redirects`, `blocked_target`, `unknown`.

**Acceptance criteria**
- Checks are claimed atomically so a monitor is never checked twice for the same slot.
- A slow or hanging target never blocks other checks (hard timeout enforced).
- Check timing jitter (±5 s) is applied to avoid thundering herds.

### 4.4 Incident state machine (P0)
- A monitor goes `down` after **N consecutive failures** (`alert_after_failures`).
- It goes back `up` after **1 successful check** (configurable to 2 in P2).
- Opening a down incident stores `started_at`, `first_error`; recovery stores `ended_at` and computed duration.
- One alert on down, one alert on recovery. No repeated "still down" spam (optional reminder every 6 hours is P2).
- A flapping guard: if a monitor opens 4+ incidents in 1 hour, suppress recovery alerts for that hour and send a single "unstable" alert.

**Acceptance criteria**
- Unit tests prove: 1 failure with threshold 2 does not alert; 2 failures does; success after down recovers; flapping guard triggers.

### 4.5 Alert channels (P0 Telegram + Discord, P1 Email)

**Telegram**
- User clicks "Connect Telegram" and gets a one-time code (valid 15 minutes).
- User sends `/start CODE` to the PupWatch bot; the bot webhook links `chat_id` to the user.
- Webhook endpoint is protected by a secret path/token header.
- Bot commands: `/status` (list monitors with mood emoji), `/pause <name>`, `/resume <name>`, `/help` (P1 for commands, P0 for linking and alerts).

**Discord**
- User pastes a Discord webhook URL; validated with a regex (`https://discord.com/api/webhooks/{id}/{token}`) and tested with a "Test alert" button.
- Alerts are sent as embeds (red for down, green for recovery, yellow for unstable).

**Email (P1)**
- Sent via Laravel Mail (SMTP/Mailgun/Resend, env-configured).

**Behavior**
- Each monitor can be linked to any number of channels.
- Failed alert deliveries are logged and retried 3 times with backoff; persistent failures mark the channel `needs_attention`.
- "Test alert" button on every channel.

**Alert content (down)**
```
🐶 PupWatch: example.com is DOWN
Reason: timeout after 10s
Since: 14:32 UTC · Failed checks: 2
https://app.pupwatch.dev/monitors/123
```

### 4.6 Dashboard (P0)
- Monitor list with mascot mood, current status, 24h uptime %, last response time, last checked.
- Summary bar: total monitors, up, down, paused, open incidents.
- Auto-refresh by polling every 30 seconds.
- Filter by status, search by name.

### 4.7 Monitor detail page (P0)
- Large mascot and current status.
- Response time chart (24h, 7d, 30d) with down periods highlighted.
- Uptime percentages for 24h, 7d, 30d, 90d.
- Recent checks table (last 50) and incident history with durations.
- Edit, pause/resume, delete, "Check now" (throttled to once per 30 seconds per monitor).

### 4.8 Public status pages (P0)
- User creates a status page: title, slug (unique), description, selected monitors with optional public display names.
- Public URL `/status/[slug]`, server-rendered, no login.
- Shows overall banner ("All systems operational" / "Partial outage" / "Major outage"), per-monitor 90-day uptime bars (one bar per day, colored by uptime), current status and the mascot, and the last 14 days of incidents.
- Option to hide response times and hide error messages (default hidden for privacy).
- Cached for 60 seconds.
- SEO: proper title/meta/OG tags; `noindex` toggle.
- Optional header logo URL and accent color (P1).

### 4.9 Rollups and retention (P0)
- Raw `check_results` retained for **30 days**, then deleted.
- Nightly job aggregates per monitor per day into `daily_stats` (total, successes, avg/min/max response, p95). Kept for **400 days**.
- 90-day status bars and 30d/90d uptime read from `daily_stats` (plus today's live data).

### 4.10 Settings (P0)
- Profile, password, timezone.
- Manage alert channels.
- Delete account.

### 4.11 Stretch ideas (P2)
- Maintenance windows (suppress alerts and mark on status page).
- TCP port monitor and SSL expiry warnings (30/14/7 days).
- Embeddable SVG badge for uptime (`/badge/{monitorToken}.svg`).
- Weekly email report.
- Subscribe to status page updates by email.
- GitHub OAuth login, CSV export of check history.

---

## 5. Security: SSRF protection (critical)

Because PupWatch fetches user-supplied URLs, it must never be usable to reach internal resources. This is a headline feature for the portfolio; implement and test it carefully.

**Rules (enforced at save time AND at every check):**
1. Only `http` and `https` schemes.
2. Only ports 80, 443, 8080, 8443 (v1 allowlist).
3. Resolve the hostname and **block** if any resolved IP is in:
   - loopback (`127.0.0.0/8`, `::1`)
   - private (`10.0.0.0/8`, `172.16.0.0/12`, `192.168.0.0/16`, `fc00::/7`)
   - link-local (`169.254.0.0/16`, `fe80::/10`) including cloud metadata `169.254.169.254`
   - unspecified/broadcast/multicast ranges, `0.0.0.0/8`
   - IPv4-mapped IPv6 forms of the above
4. **Pin the validated IP** for the actual connection (curl `CURLOPT_RESOLVE`) to defeat DNS rebinding.
5. Re-validate every redirect hop (host, IP, port, scheme). Max 5 hops.
6. Block URLs with embedded credentials (`user:pass@`), and hostnames like `localhost`, `*.internal`, `*.local`.
7. Cap response body read size and total time.
8. Do not return full response bodies to users; only status, timing and a short error.

**Other security**
- Laravel Policies on every resource (users can only see their own monitors, channels, status pages).
- Encrypt custom headers, Telegram chat id and Discord webhook URLs at rest (Laravel `encrypted` cast).
- Strict CORS to the web origin; CSRF-safe cookie flow; validated input via Form Requests.
- Webhook secret for the Telegram endpoint; verify it on every call.
- Never log secrets.

---

## 6. User flows

**Onboarding:** Landing → Register → empty dashboard with mascot "curious" → "Add your first site" → monitor created → first check in under a minute → dog turns happy.

**Alert setup:** Settings → Channels → Connect Telegram → copy code → send to bot → bot replies "Linked!" → channel appears → attach to monitors.

**Incident:** Site fails twice → monitor goes down → Telegram/Discord alert → dashboard dog turns sad → site recovers → recovery alert with downtime duration → incident closed.

**Sharing:** Status Pages → New → pick monitors → copy public link → send to client.

---

## 7. Pages (Next.js App Router)

| Route | Purpose | Auth |
|---|---|---|
| `/` | Landing page with animated mascot and live demo monitor | Public |
| `/login`, `/register` | Auth | Public |
| `/dashboard` | Monitor overview | Required |
| `/monitors/new` | Create monitor | Required |
| `/monitors/[id]` | Monitor detail, charts, incidents | Required |
| `/monitors/[id]/edit` | Edit monitor | Required |
| `/incidents` | All incidents with filters | Required |
| `/channels` | Manage alert channels | Required |
| `/status-pages` | List and manage status pages | Required |
| `/status-pages/[id]/edit` | Configure a status page | Required |
| `/status/[slug]` | Public status page (SSR, cached) | Public |
| `/settings` | Profile, timezone, danger zone | Required |

**UI requirements**
- Responsive, mobile first, Tailwind.
- Clean modern layout with the pixel-dog as personality, not clutter.
- Charts via a lightweight library (Recharts or Chart.js).
- Skeleton loaders and clear empty/error states.
- Accessible: semantic HTML, keyboard navigation, WCAG AA contrast, status never conveyed by color alone (icons and text too).

---

## 8. Architecture

```
┌──────────────────┐   HTTPS   ┌────────────────────────────┐  SQL  ┌──────────┐
│ Next.js (Vercel) │──────────▶│ Laravel API (Docker)       │──────▶│ MySQL 8  │
│ SSR status pages │           │ Sanctum · Policies         │       └──────────┘
└──────────────────┘           │ Scheduler · Queue workers  │
                               └──────┬──────────┬──────────┘
                                      │          │
                          HTTP checks ▼          ▼ Alerts
                              user websites   Telegram API / Discord / Mail
```

### 8.1 Scheduling design
- `monitors.next_check_at` (indexed) holds when each monitor is due.
- **Dispatcher:** runs every minute via scheduler. Query: active monitors where `next_check_at <= now()`, in chunks of 200, ordered by `next_check_at`.
- **Atomic claim:** `UPDATE monitors SET next_check_at = now() + interval + jitter, claimed_at = now() WHERE id = ? AND next_check_at <= now()`. Only dispatch the job if affected rows = 1.
- **Job `RunMonitorCheck`:** performs the check, stores result, runs the state machine, queues alerts. Idempotent, `timeout` slightly above check timeout, `tries = 1` for checks (a failed check is data, not a retry).
- **Queues:** `checks` (high throughput, several workers) and `alerts` (separate so alert delivery is never starved by checks).
- **Queue driver:** database for v1 (Redis optional upgrade, documented).
- **Cron:** single `* * * * * php artisan schedule:run`.

### 8.2 Scheduled jobs
| Frequency | Job |
|---|---|
| every minute | `DispatchDueChecks` |
| every 5 min | `RecoverStuckClaims` (monitors claimed but never completed) |
| nightly | `RollupDailyStats`, `PruneOldCheckResults` |
| every 10 min | `PruneExpiredTelegramTokens` |

### 8.3 Suggested repository layout (monorepo)
```
pupwatch/
├── apps/
│   ├── web/                       # Next.js
│   │   ├── src/app/...
│   │   ├── src/components/mascot/ # PupMascot.tsx + moods
│   │   ├── src/lib/api.ts
│   │   └── src/types/
│   └── api/                       # Laravel
│       ├── app/Services/          # CheckService, SsrfGuard, IncidentService, UptimeService, AlertService
│       ├── app/Services/Alerts/   # TelegramChannel, DiscordChannel, MailChannel
│       ├── app/Jobs/
│       ├── app/Http/Controllers/Api/
│       ├── app/Policies/
│       └── tests/
├── docker-compose.yml
├── .github/workflows/ci.yml
├── docs/
└── README.md
```

---

## 9. Data model (MySQL)

All tables: `id` bigint unsigned PK, `created_at`, `updated_at` unless noted.

**users**
`name`, `email` (unique), `password`, `timezone` (default `UTC`)

**monitors**
`user_id` FK, `name`, `url`, `host`, `method`, `interval_seconds`, `timeout_seconds`, `expected_status` (string), `keyword` (nullable), `keyword_mode` (nullable), `follow_redirects`, `custom_headers` (encrypted JSON, nullable), `alert_after_failures`, `is_paused`, `current_status` (`pending`|`up`|`down`|`paused`), `consecutive_failures`, `last_checked_at`, `last_response_ms`, `next_check_at`, `claimed_at`
Indexes: `(is_paused, next_check_at)`, `(user_id)`

**check_results** *(high volume)*
`monitor_id` FK, `checked_at`, `status_code` (nullable), `response_time_ms` (nullable), `is_success`, `error_type` (nullable), `error_message` (nullable, max 255)
Index: `(monitor_id, checked_at)`. No `updated_at`. Delete rows older than 30 days in chunks.

**incidents**
`monitor_id` FK, `started_at`, `ended_at` (nullable), `duration_seconds` (nullable), `first_error_type`, `first_error_message`, `status` (`open`|`resolved`)
Index: `(monitor_id, started_at)`, `(status)`

**daily_stats**
`monitor_id` FK, `date`, `total_checks`, `successful_checks`, `avg_response_ms`, `min_response_ms`, `max_response_ms`, `p95_response_ms`, `downtime_seconds`
Unique: `(monitor_id, date)`

**notification_channels**
`user_id` FK, `type` (`telegram`|`discord`|`email`), `name`, `config` (encrypted JSON), `status` (`active`|`needs_attention`), `last_used_at`

**monitor_channel** (pivot)
`monitor_id` FK, `notification_channel_id` FK. Unique pair.

**alert_logs**
`incident_id` FK (nullable), `monitor_id` FK, `notification_channel_id` FK, `event` (`down`|`recovered`|`unstable`|`test`), `status` (`sent`|`failed`), `error` (nullable), `sent_at`

**telegram_link_tokens**
`user_id` FK, `token` (unique), `expires_at`, `used_at` (nullable)

**status_pages**
`user_id` FK, `title`, `slug` (unique), `description`, `show_response_times` (default false), `show_error_details` (default false), `is_indexable` (default true), `accent_color` (nullable), `logo_url` (nullable)

**status_page_monitors**
`status_page_id` FK, `monitor_id` FK, `display_name` (nullable), `position`. Unique pair.

---

## 10. REST API

Base path `/api/v1`. JSON only. Error shape: `{ "message": string, "errors"?: object }`. Pagination: `?page=`, `?per_page=` (max 50).

### Auth
| Method | Path | Description |
|---|---|---|
| POST | `/auth/register` | Create account |
| POST | `/auth/login` | Returns token |
| POST | `/auth/logout` | Revoke token |
| GET | `/me` | Current user |
| PATCH | `/me` | Update profile, timezone, password |
| DELETE | `/me` | Delete account |

### Monitors
| Method | Path | Description |
|---|---|---|
| GET | `/monitors` | List with status, 24h uptime |
| POST | `/monitors` | Create (SSRF-validated) |
| GET | `/monitors/{id}` | Detail with uptime summary |
| PATCH | `/monitors/{id}` | Update |
| DELETE | `/monitors/{id}` | Delete |
| POST | `/monitors/{id}/pause` | Pause |
| POST | `/monitors/{id}/resume` | Resume |
| POST | `/monitors/{id}/check` | Check now (throttled 30 s) |
| GET | `/monitors/{id}/results?range=24h\|7d\|30d` | Chart data (downsampled) |
| GET | `/monitors/{id}/checks` | Recent raw checks |
| GET | `/monitors/{id}/incidents` | Incident history |

### Incidents
| Method | Path | Description |
|---|---|---|
| GET | `/incidents?status=&monitor_id=` | All user incidents |

### Channels
| Method | Path | Description |
|---|---|---|
| GET | `/channels` | List |
| POST | `/channels/discord` | Add Discord webhook |
| POST | `/channels/telegram/link` | Generate link code |
| POST | `/channels/{id}/test` | Send test alert |
| DELETE | `/channels/{id}` | Remove |
| PUT | `/monitors/{id}/channels` | Set linked channels |

### Status pages
| Method | Path | Description |
|---|---|---|
| GET | `/status-pages` | List mine |
| POST | `/status-pages` | Create |
| PATCH | `/status-pages/{id}` | Update (incl. monitors) |
| DELETE | `/status-pages/{id}` | Delete |
| GET | `/public/status/{slug}` | Public data (cached 60 s) |

### Webhooks and public
| Method | Path | Description |
|---|---|---|
| POST | `/telegram/webhook/{secret}` | Telegram updates |
| GET | `/up` | Health check |

**Rate limits:** 60 req/min per user, 30 req/min per IP for public endpoints, auth endpoints 5/min.

---

## 11. Uptime calculation

- `uptime % = successful_checks / total_checks * 100` over the range, using `daily_stats` for full past days plus live `check_results` for today.
- Display with 2 decimals; show "No data" when total checks is 0.
- Status page daily bar color: 100% green, 99–99.99% light green, 95–99% yellow, below 95% red, no data grey.
- Paused periods are excluded (no checks are recorded while paused).

---

## 12. Non-functional requirements

| Area | Requirement |
|---|---|
| Throughput | Handle 1,000 monitors at 5-minute intervals (about 200 checks/min) on a small instance |
| Accuracy | A check starts within 30 seconds of its scheduled time under normal load |
| Reliability | Alert delivery retried 3 times with exponential backoff; dispatcher survives worker crashes via `RecoverStuckClaims` |
| Performance | Dashboard API p95 under 300 ms; public status page p95 under 200 ms when cached |
| Security | See section 5; OWASP basics covered |
| Privacy | No response bodies stored; minimal PII; full account deletion |
| Accessibility | WCAG AA, reduced-motion respected |
| Observability | Structured logs, failed jobs table, `/up` endpoint, optional "dispatcher heartbeat" metric |
| Data volume | 1,000 monitors × 288 checks/day ≈ 288k rows/day; retention job must delete in chunks |

---

## 13. Constraints and assumptions

- Single region, single worker pool in v1; documented upgrade path for multi-region.
- Free-tier friendly deployment: Vercel (web), Railway/Render/Fly.io or a small VPS for API + queue worker + scheduler, managed MySQL.
- Queue and cache on the database driver; Redis is an optional improvement.
- Telegram bot token comes from BotFather; the webhook requires a public HTTPS URL (use a tunnel such as ngrok in development).
- All timestamps stored in UTC; converted for display using the user's timezone.
- Scope must remain shippable in roughly 4 to 6 weeks of part-time work.

---

## 14. Testing strategy

**Backend (Pest or PHPUnit)**
- Unit: `SsrfGuard` (a long table of blocked and allowed URLs/IPs including IPv6 and encoded forms), incident state machine, uptime calculations, rollups, claim logic.
- Feature: every endpoint (auth, validation, policies: user A cannot access user B's data), dispatcher + check job using `Http::fake()` and a fake DNS resolver, alert sending with faked Telegram/Discord, Telegram webhook linking flow.
- Concurrency test: two dispatchers run at once and a monitor is still claimed once.

**Frontend**
- Component tests: mascot mood mapping, uptime bars, monitor form validation.
- One Playwright flow with mocked API: register → add monitor → see status → create status page.

**CI (GitHub Actions)**
- Lint (ESLint, Pint), type check (`tsc --noEmit`), backend tests, frontend tests, build.

**Definition of done for any task:** code merged, tests added and passing, lint clean, docs updated if behavior changed.

---

## 15. Build plan (milestones)

### Milestone 0 — Setup (1–2 days)
Monorepo, Laravel + Next.js scaffolds, Docker Compose (api, worker, scheduler, web, mysql), env examples, CI skeleton.
**Done when:** `docker compose up` runs all services and CI is green.

### Milestone 1 — Auth and monitor CRUD (3–4 days)
Register/login, Sanctum cookie flow through Next.js, monitor CRUD with validation, policies, dashboard list (static status).
**Done when:** a user can register, add a monitor and see it listed; another user cannot access it.

### Milestone 2 — SSRF guard and check engine (5–7 days)
`SsrfGuard`, `CheckService`, dispatcher with atomic claim, `RunMonitorCheck`, `check_results`, current status on monitors, stuck claim recovery.
**Done when:** real sites are checked on schedule, results appear, blocked targets are rejected, and the SSRF test table passes.

### Milestone 3 — Incidents and alerts (5–6 days)
State machine, incidents, channels (Discord first, then Telegram linking + webhook), alert queue, retries, test alert, alert logs, flapping guard.
**Done when:** taking a test site down produces exactly one down alert and one recovery alert on Telegram and Discord.

### Milestone 4 — Dashboard, charts, mascot (4–5 days)
Detail page, response charts, uptime numbers, incident history, mascot component with all moods, polling.
**Done when:** the dog changes mood correctly as a test site goes up, slow, down and paused.

### Milestone 5 — Rollups and status pages (4–5 days)
`daily_stats` rollup, retention pruning, uptime from rollups, status page builder, public SSR page with 90-day bars, caching, OG tags.
**Done when:** a public status page for 3 monitors loads fast and shows accurate history.

### Milestone 6 — Polish and launch (3–5 days)
Landing page, accessibility pass, rate limits, security review, seed/demo data, deployment, README with architecture diagram, screenshots and a demo GIF.
**Done when:** live on a public URL with a README that explains the scheduler design and SSRF protections.

### Milestone 7 — Stretch (optional)
Email alerts, SSL expiry, TCP checks, maintenance windows, uptime badge.

---

## 16. Instructions for the coding agent

1. Read this whole document first. It is the source of truth; when something is ambiguous, pick the simplest option that satisfies the acceptance criteria and record the decision in `docs/decisions.md`.
2. Work milestone by milestone. Do not start the next until "Done when" is true.
3. Keep controllers thin. Business logic goes into `app/Services`. Use Form Requests, API Resources and Policies.
4. Write the `SsrfGuard` and its tests **before** any code that makes outbound HTTP requests.
5. Every outbound request in the check engine must go through `CheckService`; no other class may call user URLs.
6. Do not retry failed checks; a failed check is a recorded result. Do retry alert deliveries.
7. Never store response bodies. Never log secrets or webhook URLs.
8. UTC everywhere in storage; convert only at display time.
9. TypeScript strict mode; share API types in `apps/web/src/types`.
10. Small conventional commits (`feat:`, `fix:`, `test:`, `docs:`, `chore:`). Update the README after each milestone.

### Environment variables

**API (`apps/api/.env.example`)**
```
APP_URL=
FRONTEND_URL=
DB_HOST= DB_PORT= DB_DATABASE= DB_USERNAME= DB_PASSWORD=
QUEUE_CONNECTION=database
CACHE_STORE=database
SANCTUM_STATEFUL_DOMAINS=
TELEGRAM_BOT_TOKEN=
TELEGRAM_BOT_USERNAME=
TELEGRAM_WEBHOOK_SECRET=
MAIL_MAILER= MAIL_HOST= MAIL_USERNAME= MAIL_PASSWORD= MAIL_FROM_ADDRESS=
CHECK_USER_AGENT="PupWatch/1.0 (+https://your-domain/about)"
```

**Web (`apps/web/.env.example`)**
```
NEXT_PUBLIC_APP_URL=
API_BASE_URL=
```

---

## 17. Risks and mitigations

| Risk | Impact | Mitigation |
|---|---|---|
| SSRF abuse | Critical | Full guard with IP pinning, redirect re-validation, port allowlist, extensive tests |
| Duplicate or missed checks | Wrong uptime data | Atomic claim, stuck-claim recovery, concurrency test |
| Alert spam from flapping | User annoyance | Consecutive-failure threshold, flapping guard |
| `check_results` table growth | Slow queries | Indexes, chunked pruning, rollups, downsampled chart queries |
| Telegram webhook spoofing | Fake commands | Secret token verification, link only via one-time code |
| Scope creep | Never ships | P0 first; stretch only after launch |
| Abuse as a free load generator | Resource cost | Plan limits, min interval, user rate limits, user-agent identifies PupWatch |

---

## 18. Success metrics

**Portfolio goals**
- Deployed publicly with README, architecture diagram, screenshots and demo GIF.
- SSRF guard and incident state machine fully unit tested; 80%+ coverage on service classes.
- Lighthouse 90+ on landing and public status pages.
- Runs your own real sites for at least 2 weeks to prove reliability (put the live status page in your README).

**Product goals (if shared)**
- 30 real users with at least 1 monitor.
- 60% of users connect an alert channel.
- 25% create a public status page.

---

## 19. Resume copy (use after you build it)

**PupWatch — Uptime monitoring with mascot-driven dashboard** · Next.js, TypeScript, Tailwind, Laravel, MySQL, Telegram Bot API
- Built a full-stack uptime monitoring platform with a Laravel API and Next.js frontend, running scheduled HTTP checks through queued workers with atomic job claiming to prevent duplicate runs.
- Engineered SSRF protection (DNS resolution validation, IP pinning, redirect re-validation, private-range blocking) so user-supplied URLs cannot reach internal networks.
- Implemented an incident state machine with failure thresholds and flapping suppression, delivering de-duplicated alerts through Telegram (webhook account linking) and Discord.
- Designed a time-series data pipeline with daily rollups and chunked retention pruning to keep high-volume check data fast to query.
- Shipped server-rendered public status pages with 90-day uptime history, caching and SEO metadata; set up CI, Docker and production deployment.

---

## 20. Glossary

- **SSRF:** Server-Side Request Forgery; tricking a server into requesting internal resources.
- **Atomic claim:** a single database update that guarantees only one worker takes a job.
- **Flapping:** a service rapidly switching between up and down.
- **Rollup:** pre-aggregated statistics replacing many raw rows.
- **DNS rebinding:** an attack where a domain resolves to a safe IP at validation time and a private IP at request time; prevented by pinning the validated IP.
