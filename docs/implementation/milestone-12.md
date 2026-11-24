# Milestone 12 — Technical Operations Center

## Delivered

- Added permission-protected operations endpoints for dependency health, RabbitMQ queue aggregates, playback diagnostics, failed Laravel jobs, and incident management.
- Health reports check Laravel/database/Redis, RabbitMQ TCP and management API, plus the Go worker's internal `/health` endpoint. Health responses contain status and sanitized aggregate counts only.
- RabbitMQ management data is allowlisted to the OTT worker queues and returns message, ready, unacknowledged, and consumer counts. Dead-letter message contents are never returned.
- Backup monitoring reports per-configured-disk archive count, aggregate bytes, latest timestamp, and whether a backup is less than 24 hours old. It exposes no archive names or paths.
- Added Laravel-managed encrypted database/application backups to configured Flysystem destinations, with a dedicated private S3-compatible disk for production, gzip database dumps, archive verification, and a separate scheduler service for backup, cleanup, and freshness-monitoring commands.
- The legacy Laravel admin backup screen now lists each configured destination, supports remote-disk downloads, requires POST plus CSRF for creation/deletion, validates archive names, and reports command failures without displaying internal output.
- The operations health response and admin dashboard now report bounded Laravel-container memory, cumulative CPU, and local storage availability where Linux cgroup/filesystem data is readable. These metrics are explicitly container-scoped, not host-wide telemetry.
- Failed Laravel jobs are paginated and expose only queue, connection, basic job type, failure time, and an explicit retryable flag. The retry endpoint accepts only the two campaign jobs whose handlers recheck consent/publication and use idempotent delivery records.
- Playback diagnostic events are aggregated by event code over a fixed 24-hour window and require `analytics.playback.view`; the customer player now submits deduplicated standardized failures tied to its active session.
- Incidents support open, acknowledge, resolve, and reopen transitions. Updates are transactional and audited; summaries are bounded and affected service names are allowlisted.
- Optional queued email alerts route newly opened or state-changed incidents at or above a configurable severity threshold. Alert messages contain only the incident ID, status, and severity; recipients and SMTP delivery must be configured through deployment secrets.
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

Production backup storage requires a private bucket, region, and dedicated archive password through the `AWS_BACKUP_*` and `BACKUP_ARCHIVE_PASSWORD` settings. Workload identity may supply S3 credentials; otherwise use a scoped key pair. `BACKUP_MAX_STORAGE_MB` controls both the cleanup ceiling and freshness monitor limit; tune it to the real database and media archive footprint. Configure `BACKUP_NOTIFICATION_EMAIL` to enable backup mail notifications and `OPERATIONS_ALERT_EMAIL` plus `OPERATIONS_ALERT_MINIMUM_SEVERITY` for incident email routing. Production Compose requires the SMTP host and verified sender address. The scheduler service runs the application schedule, so deploy only one scheduler unless the shared cache supports Laravel's single-server locking for every scheduled entry.

Apply the Laravel migration, rebuild and restart the Go worker image, then
verify the authenticated operations page and each service check. The local
development worker has been rebuilt with the health endpoint; its internal
`/health` response reports `status: ok` for process, database, Redis, RabbitMQ,
and consumers. Keep the development overlay when rebuilding:
`docker compose -f docker-compose.yml -f docker-compose.dev.yml build worker`.

## Verification and limitations

- `TechnicalOperationsTest`, `AnalyticsOperationsTest`, `SupportOperationsTest`, and `AdminContentWorkflowTest`: 19 tests, 162 assertions passed.
- Admin UI production build passed; existing Next.js dynamic-export warnings and a Node URL deprecation warning remain. The repo has no ESLint config, so standalone ESLint could not run; Next.js build performed its integrated lint/type phase.
- The full Go suite passed under Go 1.22 in a disposable container. The worker image rebuilt successfully, and its internal live health endpoint reports all five checks as healthy. Queue inspection remains aggregate-only; no dead-letter payloads were read or replayed.
- RabbitMQ reports queue counts and consumer counts, but the UI does not read message bodies or offer arbitrary DLQ replay. CDN delivery, DRM license telemetry, host-wide resource metrics, and sanitized application-log browsing have no configured telemetry/provider in this repository and remain unimplemented. Incident email routing is implemented, but SMTP configuration and delivery remain unverified. Backup freshness and encrypted archive verification are configured, but an actual database/media restore rehearsal remains outstanding.
- No production-readiness claim is made from these local checks.
