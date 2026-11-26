# Milestone 05 Keep homepage availability during partial API failure

## Existing implementation and gaps

The homepage dynamically renders but cached a partially failed aggregate for an hour when any section contained data.

## Changes

Keep successful sections visible during a partial failure. A degraded payload never replaces or seeds the shared aggregate cache. Avoid a second set of upstream calls when the first load already has partial data.

## Files, database, API, and compatibility

`apps/web/src/server/dal/home.dal.ts`, `apps/web/src/app/page.tsx`, `apps/web/scripts/test-home-cache.mjs`. Shared cache key moves to v2. Stable page inputs, URLs, and UI remain in place.

## Verification and measurements

Locked dependencies installed with scripts disabled. `npm run lint`, `tsc --noEmit`, and `npm run build` passed on Next.js 16.4.6 with Node 24. The production-server fixture passed on repeated runs. Backend calls were 1 per source during the outage, 1 more per source on recovery, and 0 on the subsequent cache hit.

## Remaining work

Measure LCP, INP, CLS, accessibility, and playback telemetry with a real backend, documented device, and network. Review server fallback behavior across the remaining customer pages.

## Rollback

Revert the homepage change and rebuild the customer image. Keep the previous image for application rollback.

## Commit references

`dffb43a`

All five existing customer Playwright guest journeys passed against a production Next.js server backed by a local API fixture. The first fixture omitted all banners, so the existing hero check failed; the rerun supplied a representative hero record.
