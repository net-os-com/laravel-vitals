# Changelog

All notable changes to `laravel-vitals` will be documented in this file.

## v0.2.0 - 2026-11-10

### Added

- Mute known problems from the dashboard. You can mute one check for one tenant, a central check, one check for all tenants, or a whole tenant.
  - A mute lasts for 1 hour, 4 hours, 1 day or 1 week, until the muted checks recover, or until you unmute it, with an optional reason.
  - Muted problems keep their real status, with a "Muted" badge, but no longer count in the totals, in "Needs attention" or in `status.json`.
  - `status.json` lists muted problems under `muted`, with `muted_until`, `until_recovered` and `reason`.
  - The overview has a "Muted" list with an Unmute button.
  - `CheckFinished` and `CheckStatusChanged` get a `muted` flag, so your own notifications can skip muted checks.
  - `vitals:prune` also removes expired mutes.
  
- A System, Light and Dark theme toggle. The choice is saved per browser.
- The logo is used as the favicon.

### Fixed

- Searching and filtering in "All tenants" now hides rows. Before, every row stayed visible.

### Upgrading

Publish and run the new migration:

```bash
php artisan vendor:publish --tag=vitals-migrations
php artisan migrate

```
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
