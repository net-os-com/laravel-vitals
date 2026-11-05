# Changelog

All notable changes to `laravel-vitals` will be documented in this file.

## v0.1.0 - 2026-11-05

First release of Laravel Vitals: health checks for Laravel, centrally and per tenant, with a Horizon-style dashboard.

### Added

- `Check` and `Result` classes with the statuses ok, warning, failed, skipped and crashed. A check that throws or exceeds its timeout is recorded as crashed.
- Registration in code with `Vitals::central()` and `Vitals::tenant()`, frequencies per check, `onlyFor()` to limit tenant checks, and a per-tenant jitter.
- Storage in the central database: `vitals_results` for the history (14 days by default) and `vitals_states` for the latest state.
- `vitals:dispatch` and `vitals:prune` are scheduled automatically. `vitals:run` and `vitals:list` are available as well.
- Tenancy through a `TenancyAdapter`. stancl/tenancy is detected automatically, and all checks of a tenant run in one job.
- Generic checks: database, cache, Redis, disk space, failed jobs, Horizon, scheduler heartbeat, debug mode, environment, optimized app, tenant database and pending migrations.
- A Blade dashboard with an overview, a tenant page and a check page. It refreshes automatically and has a Run now button.
- `status.json` for external monitors. It returns 200 or 503 and accepts either the gate or the `X-Vitals-Token` header.
- Access through `Vitals::auth()`, then the `viewVitals` gate, otherwise only the local environment.
- The `CheckFinished` and `CheckStatusChanged` events, for notifications of your own.

Requires PHP 8.3+ and Laravel 11, 12 or 13.
