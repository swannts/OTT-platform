# Milestone 12 — Technical Operations Center

## Delivered

- Added permission-protected operations endpoints for dependency health, RabbitMQ queue aggregates, playback diagnostics, failed Laravel jobs, and incident management.
- Health reports check Laravel/database/Redis, RabbitMQ TCP and management API, plus the Go worker's internal `/health` endpoint. Health responses contain status and sanitized aggregate counts only.
- RabbitMQ management data is allowlisted to the OTT worker queues and returns message, ready, unacknowledged, and consumer counts. Dead-letter message contents are never returned.
- Backup monitoring reports per-configured-disk archive count, aggregate bytes, latest timestamp, and whether a backup is less than 24 hours old. It exposes no archive names or paths.
- Failed Laravel jobs are paginated and expose only queue, connection, basic job type, failure time, and an explicit retryable flag. The retry endpoint accepts only the two campaign jobs whose handlers recheck consent/publication and use idempotent delivery records.
- Playback diagnostic events are aggregated by event code over a fixed 24-hour window and require `analytics.playback.view`.
- Incidents support open, acknowledge, resolve, and reopen transitions. Updates are transactional and audited; summaries are bounded and affected service names are allowlisted.
- Added an internal Go worker health endpoint that reports process, RabbitMQ connection, Redis TCP reachability, and database ping. Docker Compose exposes it only on the internal network.
- Replaced the old operations summary page with a permission-aware health, queue, playback, failed-job, and incident UI.

## API and permissions

All routes require Sanctum authentication and `admin.access`:

| Method | Path | Permission |
|---|---|---|
| GET | `/api/admin/operations/health` | `operations.health.view` |
| GET | `/api/admin/operations/playback` | `analytics.playback.view` |
| GET | `/api/admin/operations/jobs` | `operations.jobs.view` |
| POST | `/api/admin/operations/jobs/{uuid}/retry` | `operations.jobs.retry` |
| GET | `/api/admin/operations/incidents` | `operations.incidents.manage` |
| POST | `/api/admin/operations/incidents` | `operations.incidents.manage` |
| PATCH | `/api/admin/operations/incidents/{id}` | `operations.incidents.manage` |

## Migration and deployment

Migration `2026_11_24_000020_create_operations_incidents_table.php` is additive. It refuses to drop incident history on rollback. The worker health endpoint listens on `WORKER_HEALTH_ADDR` (default `:8081`); Laravel calls `GO_WORKER_HEALTH_URL` (default `http://worker:8081/health`). RabbitMQ queue detail requires the management API and the `RABBITMQ_MANAGEMENT_URL`, `RABBITMQ_USER`, and `RABBITMQ_PASSWORD` settings. Credentials stay server-side.

Apply the Laravel migration, rebuild and restart the Go worker image, then verify the authenticated operations page and each service check. The currently running local worker image predates this endpoint, so the live health response correctly reports the Go worker as unavailable until it is rebuilt.

## Verification and limitations

- `TechnicalOperationsTest`, `AnalyticsOperationsTest`, `SupportOperationsTest`, and `AdminContentWorkflowTest`: 19 tests, 162 assertions passed.
- Admin UI production build passed; existing Next.js dynamic-export warnings and a Node URL deprecation warning remain. The repo has no ESLint config, so standalone ESLint could not run; Next.js build performed its integrated lint/type phase.
- The isolated Go health package test passed. Full `go test ./...` and rebuilding the worker could not complete because this environment cannot resolve `proxy.golang.org`; dependency downloads timed out. The live worker integration therefore remains unverified.
- RabbitMQ reports queue counts and consumer counts, but the UI does not read message bodies or offer arbitrary DLQ replay. CDN delivery, DRM license telemetry, host resource metrics, configurable alert routing, and sanitized application-log browsing have no configured telemetry/provider in this repository and remain unimplemented. Backup archive freshness is visible, but restore verification is not.
- No production-readiness claim is made from these local checks.
