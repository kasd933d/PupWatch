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