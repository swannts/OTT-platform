# Milestone 01 Separate backend responsibilities

## Existing implementation and gaps

Playback already uses a dedicated authorization action and session, URL, and entitlement services. Checkout already delegates fulfillment to an existing service.

## Changes

Moved details, heartbeat, and end into `PlaybackSessionController`. Added a regression for ownership by device token.

## Files, database, API, and compatibility

`apps/api/Modules/Entertainment/Http/Controllers/API/{EntertainmentsController,PlaybackSessionController}.php`, `apps/api/routes/api.php`, `apps/api/tests/Feature/PlaybackSessionTest.php`. No schema change. HTTP methods, paths, middleware, UUID constraints, response shapes, and transaction bodies are preserved.

## Verification and measurements

Compared the three route lines and verbatim moved method bodies. PHP syntax passed with PHP WASM. Laravel feature suite and route listing require Composer dependencies and the test database.

## Remaining work

Run the existing playback tests and compare runtime route exports. Audit remaining catalog, search, checkout, and webhook responsibilities. Establish a static-analysis baseline.

## Rollback

Revert the playback extraction and re-cache routes as part of application rollback.

## Commit references

`a0a9704`
