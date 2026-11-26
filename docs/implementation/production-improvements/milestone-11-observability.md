# Milestone 11 Make production failures traceable

## Existing implementation and gaps

The operations UI already exposes health, logs, jobs, incidents, and playback summaries. The worker has health and structured log output.

## Changes

Added per-request Laravel correlation and surfaced RabbitMQ setup errors at the configuration boundary.

## Files, database, API, and compatibility

See the API and worker reports for changed files and contracts. No database change.

## Verification and measurements

PHP syntax and Go formatting checks passed. Cross-service log searches, metrics overhead, and alert delivery remain unverified.

## Remaining work

Trace a real web-to-Laravel request and a media job through logs. Measure API latency and error rates, queue age, playback errors, backup freshness, and VPS resource headroom. Use bounded labels and verify one delivered test alert before adding tooling.

## Rollback

Use the associated API or worker rollback procedure. Keep existing operations health checks enabled.

## Commit references

`dabdb61`, `59f4652`
