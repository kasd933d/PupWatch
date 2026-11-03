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
