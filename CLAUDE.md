# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

`AGENTS.md` holds the detailed code-style contract (imports, type declarations, docblocks, PHPStan ignores, `install.sh` internals). Read it before writing code; this file covers commands and the big picture.

## Commands

```bash
composer test          # full gate: debug-code check, pint, phpstan, pest
composer fix           # pint auto-format
composer test:unit     # pest with coverage, --min=95 (hard fail below)
composer test:types    # phpstan level 10 over src/
composer test:lint     # pint --test
composer test:debug    # laradumps: fails if dump/dd left in src or tests

./vendor/bin/pest tests/Feature/ExecuteTinkerControllerTest.php
./vendor/bin/pest tests/Feature/ExecuteTinkerControllerTest.php --filter="handles exception"

pnpm build             # vite build -> dist/sidecar.js (commit the result)
```

Tests run under Orchestra Testbench (no host Laravel app). CI matrix is PHP 8.2–8.5 × Laravel 11/12/13, with MySQL and Redis services; local default DB is sqlite `:memory:` (override with `DB_DRIVER`, `DB_HOST`, …).

If `resources/js/index.js` changed, run `pnpm build` and commit `dist/sidecar.js` — consumers never build the bundle.

## Architecture

A dev-only Laravel package: a browser panel that drives Tinker, Artisan commands, a fake clock, and user impersonation in a consumer app.

**Production is off by default, in three independent places.** `resources/routes.php` skips route registration entirely when `app()->isProduction()`; `SidecarServiceProvider::boot()` returns before routes/middleware/singleton when in production (only `publishes()` runs); individual controllers, `FakeClock`, `SidecarInjectJsMiddleware` and `SideCarExecuteTinkerJob` re-check it. Keep that layering when adding entry points — never rely on a single guard.

**Request path.** All routes live under the `__devsquad-sidecar` prefix in the `web` group. Public: `assets/js`, `data`, `login-as`, `login-as/{user}`. Everything destructive sits behind the `devsquad-sidecar-auth` alias (`SidecarMiddleware`), which enforces the `allowed_ips` list (exact / CIDR v4+v6 / octet-boundary prefix) — but only for paths starting `execute-`, and also rejects when `devsquad-sidecar.enabled` is false. Controllers are single-action `__invoke` classes paired with a Form Request in `src/Http/Requests/`.

**Two middlewares are appended to the `web` group by the provider**, so they run in every consumer request: `FakeClockMiddleware` (re-applies the session clock) and `SidecarInjectJsMiddleware` (string-replaces `</body>` with the script tag, gated on `auto_inject_assets`).

**Fake clock** (`src/FakeClock.php`) stores a *seconds offset*, not a frozen instant, and applies it via `Carbon::setTestNow(fn ($realNow) => $realNow->addSeconds($offset))` — time keeps ticking. The offset is written twice: to the **session** (so a clock never leaks to other browsers) and to the **cache** (so CLI processes pick it up — the provider calls `FakeClock::applyFromCache()` when `runningInConsole()`). Web controllers that must honour the clock use the `WithFakeClock` trait and call `setFakeClock()` first.

**Artisan commands** are split: `CommandCatalog` introspects `Artisan::all()`, keeps only app-owned commands (ClosureCommand, or a class whose reflection file is not under `vendor/`), drops hidden ones, filters `blocked_commands` patterns (matched against both the command name and its FQCN via `Str::is`), and strips inherited Symfony options. `CommandRunner` executes the command as a **subprocess** (`php artisan <name> … --no-interaction`) so `command_timeout` applies instead of the web server's request timeout.

**Tinker** runs in-process via `Artisan::call('tinker', ['--execute' => …])` (`ExecuteTinkerController`, output trimmed after Tinker's banner), or queued through `SideCarExecuteTinkerJob` when batching is on (`ExecuteTinkerOnQueueController`).

**Consumer-side configuration is static state** on `EliteDevSquad\SidecarLaravel\Sidecar`: `$userModel`, `$userMap`, `$userBuilder`, set from the app's `AppServiceProvider` inside a `class_exists()` guard. `GetSidecarDataController` is the panel's single bootstrap payload (users, branch, env, links, health, version, fake clock); the user list is cached forever under `sidecar_users` and `ClearUserCacheController` busts it.

**Frontend** is one vanilla-JS class in `resources/js/index.js`, built by Vite as a single IIFE to `dist/sidecar.js` (committed, served by `SidecarJsController`). It reads `window.__sidecarBaseUrl` at `DOMContentLoaded` to prefix API calls — needed because external frontends (Next/Nuxt) cannot inject env vars into a bundle built inside this package.

## Adding things

- **New endpoint**: Request in `src/Http/Requests/` → single-action controller → route in `resources/routes.php` (inside the auth group if it executes anything) → feature test in `tests/Feature/`.
- **New config key**: add to `resources/config/devsquad-sidecar.php` with `env()` + default, add the `DS_SIDECAR_*` var to `install.sh` via `env_ensure`, cover it with a test. `install.sh` is itself under test (`tests/Feature/InstallScriptTest.php`).
- Test doubles live at the root of `tests/` (`FakeTinkerCommand`, `FakeCatalogCommand`, `FakeCommandRunner`, `User`, `Role`, `TestDatabase`); `TestCase::afterApplicationCreated()` registers the fake commands into the console kernel.
