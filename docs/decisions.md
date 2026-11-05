# Decisions

Decisions made while building PupWatch, per PRD §16.1 ("pick the simplest option that
satisfies the acceptance criteria and record the decision here").

## D1 — Laravel 12 instead of Laravel 11 (Milestone 0)

**PRD says:** Laravel 11, PHP 8.3.

**Decision:** use `laravel/framework ^12.0` on PHP 8.3.

**Why:** `composer create-project laravel/laravel ^11.0` fails today because Composer's
security-advisory policy blocks the *entire* `^11.31` range (advisories PKSA-b41t-wbzk-9q51,
PKSA-d5tc-s1qs-h781, PKSA-3r5d-mb8f-1qw9, PKSA-m5cs-t1y6-qpcs, PKSA-mdq4-51ck-6kdq,
PKSA-q46n-4fdk-zjr4, PKSA-qzrn-rnz3-85w1, PKSA-8qx3-n5y5-vvnd). Installing anyway would mean
shipping dependencies flagged as vulnerable. Laravel 12 installs cleanly with no advisories,
keeps PHP 8.3, and every API used by the PRD (Sanctum, policies, form requests, queues,
scheduler, encrypted casts) is unchanged. No PRD requirement depends on the framework major.

**Rejected alternatives:** ignore the advisories (`policy.advisories.block=false`) — ships known
vulnerable code; hand-pin an unflagged 11.x — none exists.

## D2 — SQLite locally and in tests, MySQL 8 in Docker/production (Milestone 0)

**PRD says:** MySQL 8 everywhere.

**Decision:** `DB_CONNECTION=mysql` is the default in `.env.example` and the Docker Compose
stack runs MySQL 8.4. Local development and the whole test suite run on SQLite
(`phpunit.xml` already sets `:memory:`), and `docs/decisions.md` + README document the switch.

**Why:** the acceptance criteria for CI ("CI is green") must be provable without a database
service, and SQLite keeps `php artisan test` instant on any machine. All migrations, queries
and Eloquent usage stay ANSI-ish and are exercised against MySQL by the Docker stack.

**Rejected alternatives:** require Docker for every test run (CI slower, contributors blocked);
use MySQL-only SQL such as `JSON_EXTRACT` raw clauses in portable code paths.

## D3 — Next.js 16 / React 19 / Tailwind 4 (Milestone 0)

`create-next-app@latest` scaffolds Next 16 (App Router, `src/`), React 19 and Tailwind 4 with
CSS-first config (`@theme` in `globals.css` instead of `tailwind.config.ts`). The PRD's route
table and "App Router" requirement are unaffected.

## D4 — API is served by `php artisan serve` in Docker for v1

The Compose stack uses the PHP CLI image with `artisan serve` (api), `queue:work` (worker) and a
`schedule:run` loop (scheduler). That is enough for v1's single-region, single-pool assumption
(PRD §13). Swapping the API container for php-fpm + nginx is a deployment change only; nothing in
the application depends on it.
## D5 — DNS is resolved at save time, but an unresolvable host still saves (Milestone 2)

**PRD says:** SSRF rules are enforced "at save time AND at every check" (§5).

**Decision:** at save time the guard always enforces the structural rules (scheme, port,
credentials, blocked hostnames, blocked IP literals) and additionally resolves the hostname,
rejecting the save when any resolved IP is private or reserved. If DNS itself fails, the save
is allowed and the first check records a `dns_failure` result.

**Why:** a save must not depend on the user's DNS being up at that moment, and the failure
belongs in the check history rather than in a validation error. Every check re-resolves and
re-validates from scratch, so a host that later starts answering with a private IP is blocked
at check time. Tests bind `Tests\Support\FakeDnsResolver` through the container so the whole
suite is deterministic and offline-safe.

## D6 — Redirects are followed by the application, not by curl (Milestone 2)

`CheckService` sends every request with `allow_redirects = false` and walks up to 5 `Location`
headers itself, validating each hop (scheme, host, port, DNS, pinned IP) before requesting it.
Delegating to curl's `CURLOPT_FOLLOWLOCATION` would perform hops that never pass the guard.

## D7 — Charts read raw `check_results` until Milestone 5 (Milestone 2)

`GET /monitors/{id}/results` buckets rows in PHP (5 min for 24h, 1 h for 7d, 6 h for 30d) and
caps the response at a few hundred points. Reading 30 days of raw rows is acceptable for v1's
volume but is exactly what `daily_stats` replaces in Milestone 5; the endpoint shape will not
change.

## D8 — Lifecycle of an incident lives in `IncidentService` (Milestone 3)

`RunMonitorCheck` only stores the result and updates the monitor row; it then hands the
`CheckResult` to `IncidentService`, which owns opening (on the check that crosses
`alert_after_failures`), resolving (on the first success) and queuing exactly one alert per
transition. Keeping this out of the job makes the state machine testable without HTTP fakes and
keeps the "no repeated down alerts" guarantee in one place.

## D9 — Flapping guard suppresses every alert while unstable (Milestone 3)

The PRD asks for recovery alerts to be suppressed for an hour after a monitor opens 4+ incidents
in an hour, plus a single "unstable" alert. Implemented with `monitors.unstable_until`: when the
fourth incident in the window opens, one unstable alert is queued and the column is set an hour
ahead. While it is in the future, incidents are still recorded but neither down nor recovery
alerts are sent; once it has passed the monitor behaves normally again. Suppressing the down
alerts too avoids the obvious follow-on spam the PRD is trying to prevent.

## D10 — Test alerts have no monitor (Milestone 3)

The "Test alert" button sends from the channel screen, so `alert_logs.monitor_id` is nullable and
such rows carry `event = test` with no monitor or incident. Every other alert row always has a
monitor.

## D11 — Telegram chats are found by decrypting channel config (Milestone 3)

`notification_channels.config` is encrypted at rest, so a chat id cannot be used in a SQL
`where`. The webhook loads the Telegram channels and filters on the decrypted `chat_id`. At v1
scale (hundreds of channels) this is cheap; a hashed `chat_id` lookup column is the upgrade path
if the table grows.

## D12 — Alert delivery is retried by the queue, logged on the final failure (Milestone 3)

`SendAlert` runs on the `alerts` queue with `tries = 3` and backoff 10s/60s. Success writes a
`sent` row and revives the channel; `failed()` (called after the last attempt) writes the `failed`
row and marks the channel `needs_attention`. Test alerts are sent synchronously so the UI can
report the result immediately.

## D13 — The response-time chart is a small SVG component (Milestone 4)

The PRD allows Recharts or Chart.js. For a single line series with failure markers, a ~150 line
component avoids a large client dependency, a hydration-only bundle and chart-library theming
work; the SVG is also readable in the accessibility tree (`role="img"` with a summary label).
If the dashboard later needs several chart types, swapping this component for Recharts is a
contained change because pages pass it `{ t, avg_ms, failures }` points only.

## D14 — Mood is derived from monitor state on the client (Milestone 4)

`lib/mood.ts` maps a monitor to one of the five PRD moods (paused → sleeping, down → sad, no
checks yet → curious, slow but up → panting, otherwise happy); the dashboard aggregates them by
taking the most concerned mood. Keeping the mapping in one pure function means the detail page,
dashboard cards and empty states cannot disagree. CSS handles tail wag, rain and Zzz animations
and disables them under `prefers-reduced-motion`.

## D15 — Dynamic routes use a server wrapper with `instant = false` (Milestone 4)

Next 16's `cacheComponents` refuses to prerender a page whose client component reads
`useParams`. Route segment config cannot be exported from a `"use client"` file, so
`/monitors/[id]` and `/monitors/[id]/edit` are thin server components that declare
`export const instant = false` and render the client view inside a `Suspense` fallback. Config
files stay server-only and the client views remain ordinary hook-based components.
