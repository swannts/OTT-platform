# Milestone 15 — Production security, verification, and deployment

Status: **partially verified; production acceptance remains open**.

This milestone closes local verification gaps and records the controls needed to
deploy the work from Milestones 6–14. A production deployment, provider-backed
reconciliation, restore exercise, capacity test, and CDN/DRM setup were not
available in this workspace. Passing local checks does not establish production
readiness.

## Security and deployment changes

- Admin Next.js BFF mutations check the request `Origin` and Fetch Metadata before
  forwarding login, logout, invitation acceptance, or proxied mutations. The
  admin bearer token remains in the server-managed cookie flow.
- Admin login is tested to return HTTP 429 after five attempts in one minute.
- The admin BFF awaits Next.js 16 asynchronous cookie APIs for login, session lookup,
  logout, and authenticated backend forwarding.
- Administrative permission checks re-read account eligibility from the database and deny
  inactive or banned accounts, including requests using an already-issued token. Staff
  deactivation locks the account row, updates status, and revokes all Sanctum tokens in one
  transaction. Admin login issues its token while holding the same row lock, preventing a
  login/deactivation race from leaving a newly issued session behind.
- Granting or revoking Super Admin access now requires the dedicated
  admin.roles.super_admin.assign permission, password re-entry, and the existing
  protections against self-change and removing the final active Super Admin. This
  source change was reviewed statically; tests remain deferred.
- Laravel admin role lookup and authorization continue to use the dedicated admin
  role scope. Sensitive role operations require explicit permissions and the
  role policy prevents assignment of inactive/non-admin roles and protects fixed
  roles.
- Stripe, Razorpay, Paystack, and RevenueCat webhook handlers verify their
  provider signatures and record deliveries through the provider event ledger to
  make duplicate event IDs idempotent. RevenueCat also enforces a signed-body
  timestamp window and requires a provider event ID. Focused signature rejection
  and replay-deduplication tests cover all four handlers; they use signed
  non-payment events and do not establish payment fulfillment or reconciliation.
- CORS origins can be set explicitly with `CORS_ALLOWED_ORIGINS`. The production
  Compose overlay requires exact browser origins and secure session cookies.
- Import-job diagnostics no longer write raw user rows, email addresses, local
  file paths, or externally supplied artwork URLs to application logs. Login
  failure logs and FCM access-token logs also omit those values.
- Removed FCM request/response payload logging, notification data dumps, raw
  payment-provider response logging, and exception details from the reviewed
  payment, cache, event-publisher, settings, VAST-ad, and notification-job paths.
  Provider failures now retain only bounded status/exception-class metadata;
  these changes were reviewed statically and not runtime-tested.
- The committed Compose APP_KEY fallback was removed. The ignored local `.env`
  retains the active key for the existing local data; `.env.example` contains
  placeholders only. Production configuration requires secrets to be injected
  through a deployment secret store.
- Reworked `.env.production.example` so it no longer contains a reusable APP_KEY,
  local HTTP origins, root database credentials, empty-password allowance, or
  default RabbitMQ credentials. Production origins, independent DB/root secrets,
  app image references, and an explicit CORS allowlist are represented as
  deployment-specific values.
- Production `db:seed` now bypasses legacy demo seeders and preserves uploaded
  storage. It applies the additive RBAC foundation and can create one initial
  Super Admin only from an explicit deployment email and a 16-character
  bootstrap secret; reruns do not reset an existing password. The destructive
  permission-truncating auth seeders reject production, and local demo accounts
  require a private `DEMO_SEED_PASSWORD`.
- Removed hard-coded demo account credentials from the Next.js and Laravel
  login screens and replaced seeded sample passwords with the private local
  seed setting.
- `docker-compose.production.yml` sets `APP_DEBUG=false`, requires an HTTPS API,
  admin, and customer origin, requires immutable image references for all
  application and infrastructure containers, binds web ports to loopback for a TLS reverse proxy, and
  leaves MySQL, Redis, RabbitMQ, and Meilisearch ports unpublished. It adds
  HTTP health checks for Laravel and both Next.js services; the existing
  infrastructure health checks remain enabled.
- Added a Laravel scheduler sidecar and a distinct private S3-compatible backup
  destination. Laravel backups encrypt and verify archives, gzip database dumps,
  and schedule daily backup, cleanup, and freshness checks. Production Compose
  requires the bucket, region, and archive password; inject either scoped S3
  credentials or workload identity. This is application configuration only:
  no production bucket or credentials are configured in this workspace.
- The operations dashboard reports Linux container memory, cumulative CPU, and
  local storage availability when cgroup/filesystem data is exposed. These are
  container metrics and do not replace host-level monitoring or alert routing.
- `NEXT_PUBLIC_API_URL` is required for both the customer Next.js build and its
  runtime environment so browser requests do not fall back to localhost.
- Added an unauthenticated, middleware-free Laravel `/up` process-health route.
  The production Compose probe now checks for HTTP 200, so a 404 or 500 can no
  longer be reported healthy merely because the server returned a response body.
- Restored the missing World module admin list Blade view so its existing admin
  route and feature test work.

## Validation performed

- Laravel PHPUnit suite: **118 tests, 530 assertions passed** in the backend
  container using its isolated in-memory SQLite test configuration; one
  existing PHPUnit deprecation remains. Production seeder safety coverage is
  **4 tests and 11 assertions**.
- The new public `/up` route passes its regression test. The live backend returns
  HTTP 200 there, and the production health-check expression accepts that status
  while rejecting an HTTP 404.
- Admin RBAC feature suite: **9 tests, 37 assertions passed**, including login rate limiting,
  immediate session revocation on staff deactivation, and denial of inactive or banned account tokens.
- Composer locked dependency audit: **0 advisories** after updating the vulnerable
  `web-token/jwt-library` lock from 3.4.10 to 3.4.13 and
  `spomky-labs/pki-framework` from 1.6.0 to 1.6.3 within the existing version
  constraints. The Laravel suite passed again after this update.
- Admin UI dependency audit: **0 vulnerabilities**. Next.js 16.4.6 production
  build passed with Webpack (the project has a custom SVG Webpack loader).
- Customer Next.js dependency audit: **0 vulnerabilities**. TypeScript check and
  production build passed on Next.js 16.4.6.
- Changed admin BFF/auth files, admin authentication/RBAC pages, OTT operations
  pages, and the UI cleanup pass focused ESLint. The full admin lint scan covers
  1,364 files and has **0 errors and 0 warnings** after replacing unsafe
  React Hook Form `watch()` subscriptions, correcting the calendar callback
  dependency, using optimized decorative images, and removing obsolete lint
  suppressions. Full customer lint covers 158 files with **0 errors and 0
  warnings** after migrating the remaining reported image elements to
  `next/image`. Customer TypeScript check and Next.js 16.4.6 production build
  pass. The latest build also completed static generation
  without the earlier transient blog-table query error. The admin UI has no
  lint warnings.
- Go worker: `go test ./...` passed in the Go 1.22 container; handler and health
  packages have tests, while other packages report no test files.
- Flutter dependency resolution and tests passed on Flutter 3.50.0 / Dart 3.14.0:
  **5 tests passed**, including UUID/numeric content and coming-soon ID
  compatibility. Whole-app
  `flutter analyze --no-pub` exits nonzero with 383 findings (one warning and
  382 info-level lints, no Dart errors).
- Production Compose configuration parsed with placeholder values. The merged
  configuration sets debug off, publishes only loopback-bound web ports, keeps
  infrastructure ports private, and injects the customer API URL into the
  frontend image build and runtime. Laravel and both Next.js services also have
  configured HTTP health checks.
- Rebuilding the admin container exposed two Next.js 16 runtime failures missed
  by static compilation: the global settings and language loaders read async
  cookies synchronously. Both now await `cookies()`. The admin image also uses
  Node 24 to satisfy the engine requirements of its locked Firebase packages.
- The rebuilt local Docker admin UI runs Next.js 16.4.6 on Node 24.21.0. Its
  homepage and sign-in route return HTTP 200, an unauthenticated `/api/admin/me/`
  returns 401, and a cross-origin logout returns 403. The other OTT containers
  and persistent volumes were left running and unchanged.
- `npm run test:bff` and `npm run test:bff:production` pass against a controlled
  Laravel API stub. They cover login, HttpOnly/SameSite cookie storage,
  production Secure cookie storage, rendered homepage and sign-in pages in
  development and production modes, authenticated `/me` forwarding,
  invalid-session cookie clearing, logout/revocation forwarding, and
  cross-origin login rejection. The development render regression was caused by
  React development elements with circular owner references nested in MUI theme
  default props; the theme now removes those values during MUI's recursive merge
  and restores them afterward.
- PHP syntax checks passed for all changed PHP files.
- A tracked-source scan found no private-key, AWS access-key, or Stripe live
  secret literals. It found one Google-style API key in the app-configuration
  seeder under the public `apiKey` setting; that client configuration should be
  restricted to the required APIs and app identifiers. The scan is heuristic
  and is not a substitute for a repository-history secret scanner.

## Deployment configuration

1. Create a private deployment environment from `.env.production.example`
   outside the repository (for example `/run/secrets/ott-production.env`); do
   not overwrite an existing local `.env`. Inject a unique Laravel `APP_KEY`,
   database and MySQL root passwords, RabbitMQ credentials, Meilisearch master
   key, exact HTTPS origins, and a public `NEXT_PUBLIC_API_URL` from the secret
   manager or deployment environment. Configure `MAIL_HOST`, a verified
   `MAIL_FROM_ADDRESS`, and the remaining `MAIL_*` settings if outbound email is
   used. Replace every application and
   infrastructure image variable with a tested immutable image reference.
2. Set `ADMIN_UI_IMAGE`, `BACKEND_IMAGE`, `FRONTEND_IMAGE`, `WORKER_IMAGE`,
   `MYSQL_IMAGE`, `REDIS_IMAGE`, `RABBITMQ_IMAGE`, and `MEILISEARCH_IMAGE` to
   tested immutable image references (prefer digests), not floating tags. Build
   and publish the four application images in CI; bake the production
   `NEXT_PUBLIC_API_URL` into the customer frontend image. Choose loopback ports
   reachable by the deployment's TLS reverse proxy.
   Also create a private backup bucket separate from customer media, inject its
   region and a dedicated archive-encryption password, and configure a narrowly
   scoped S3 identity (or workload identity). Configure SMTP and set
   `BACKUP_NOTIFICATION_EMAIL` for backup alerts; set `OPERATIONS_ALERT_EMAIL`
   and `OPERATIONS_ALERT_MINIMUM_SEVERITY` for incident alerts. Size
   `BACKUP_MAX_STORAGE_MB` for expected archive size and retention. Run one
   scheduler service against the shared
   Laravel cache; a production bucket and restore rehearsal are still required
   before treating backups as operationally verified.
3. For a new MySQL data volume, Compose creates the configured least-privilege
   application account. For an existing MySQL volume, create/verify that account
   and its database grants explicitly; MySQL initialization variables do not
   retrofit users into an already initialized volume.
4. Validate configuration and deploy in this order:

   ```bash
   docker compose --env-file /run/secrets/ott-production.env \
     -f docker-compose.yml -f docker-compose.production.yml config --quiet
   docker compose --env-file /run/secrets/ott-production.env \
     -f docker-compose.yml -f docker-compose.production.yml pull
   docker compose --env-file /run/secrets/ott-production.env \
     -f docker-compose.yml -f docker-compose.production.yml up -d mysql redis rabbitmq meilisearch
   docker compose --env-file /run/secrets/ott-production.env \
     -f docker-compose.yml -f docker-compose.production.yml run --rm backend php artisan migrate --force
   docker compose --env-file /run/secrets/ott-production.env \
     -f docker-compose.yml -f docker-compose.production.yml up -d
   ```

   Verify `/up`, queue/worker health, admin sign-in and permissions, customer
   sign-in, playback session creation/termination, and payment webhook delivery
   before opening traffic.
5. Keep private media storage and database/queue credentials off public networks.
   Terminate TLS at the reverse proxy and forward only to the loopback-published
   application ports.

## Rollback and data protection

- Added `scripts/production-backup.sh` for host-side backups to an off-repository,
  off-host destination. It streams the MySQL logical dump and public/private media
  archive through `age` before writing them, records checksums and the source
  revision, and leaves prior archives untouched if a run fails. It requires
  `BACKUP_DIR`, `BACKUP_AGE_RECIPIENT`, and `COMPOSE_ENV_FILE`; schedule it from
  the deployment host with a protected age recipient and a destination that has
  encryption at rest and independent retention. The private age identity must
  be stored separately from the backup target.
- Added `scripts/verify-production-backup.sh` to check the archive entry set,
  ciphertext checksums, decryptability, and compressed database/media stream
  structure without writing decrypted database or media contents to disk. Run
  it with `BACKUP_AGE_IDENTITY=/secure/path/ott-age-identity` and the archive
  path. This verifies archive readability, not database importability, archive
  provenance, or a complete restore rehearsal.
- Laravel's scheduled backup workflow is a second backup path: it writes
  encrypted, verified Laravel archives to the configured private S3-compatible
  destination and applies retention through `backup:clean`. It does not replace
  the host-side `age` workflow above, and neither path has been exercised against
  a production destination or restored into an isolated environment.
- Run backups during a maintenance window with media ingest and destructive media
  changes paused: MySQL and media are captured sequentially, so this script does
  not create an atomic cross-system snapshot. Before restoring, verify `SHA256SUMS`,
  decrypt into a restricted temporary location, and restore into a separate
  database and media volume first. Point-in-time recovery and an actual restore
  rehearsal remain deployment responsibilities.
- Take and verify a database backup and media-storage snapshot before deployment.
  This workspace has no configured production backup target, and no backup/restore
  rehearsal was performed.
- Preserve the database, RabbitMQ, Redis, Meilisearch, and media volumes during
  an application rollback. Redeploy the previous image and compatible
  configuration first; do not run `docker compose down -v`.
- Migrations added for these milestones are intended to be additive, but rollback
  safety is migration-specific. Review each migration's `down()` before using it;
  preserve incident, payment, audit, playback, and entitlement history. Restore a
  verified snapshot only through the operations runbook if a forward fix is not
  safe.
- Payment reconciliation, webhook replay, and media/CDN recovery must be checked
  against the actual provider records and object storage before declaring a
  rollback complete.

## Remaining production blockers

- No real production secret store, HTTPS reverse proxy, database backup target,
  immutable image registry/publishing pipeline, backup/restore rehearsal, alert
  destination, or deployment rollback exercise was configured.
- No load test, expected-traffic profile, production database query benchmark,
  CDN origin/signing integration, DRM license provider, or real payment-provider
  reconciliation was exercised.
- Milestone 13 remains partial: a trusted geolocation signal and authoritative
  licensing-window data, provider/CDN/DRM delivery, and multi-node/load
  verification remain outstanding; session lifecycle diagnostics and
  support-initiated revocation are implemented locally. See
  [Milestone 13](milestone-13-playback-security.md).
- Milestone 14 remains partially verified: a Firebase client for `com.swann.ott`,
  physical-device smoke tests, and iOS build/device validation on macOS are not
  available; see [Milestone 14](milestone-14-flutter-compatibility.md).
- Full admin and customer lint scans exit successfully with zero errors or
  warnings.
- Webhook tests cover signature rejection and replay deduplication for all four
  providers. Provider-backed payment fulfillment, refund reconciliation, and
  settlement reconciliation remain unverified.

This document records partial local verification only. Milestone 15 and the
overall milestones 6–15 goal remain incomplete until the external and repository
quality blockers above are resolved or explicitly accepted.

The Laravel-managed backup integration and container-resource dashboard documented
in this revision were reviewed statically only. Their build, runtime behavior,
remote storage access, and restore behavior remain unverified because build and
test commands were deferred.
