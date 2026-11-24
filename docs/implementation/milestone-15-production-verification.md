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
- The committed Compose APP_KEY fallback was removed. The ignored local `.env`
  retains the active key for the existing local data; `.env.example` contains
  placeholders only. Production configuration requires secrets to be injected
  through a deployment secret store.
- `docker-compose.production.yml` sets `APP_DEBUG=false`, requires an HTTPS API,
  admin, and customer origin, requires immutable image references for all
  application and infrastructure containers, binds web ports to loopback for a TLS reverse proxy, and
  leaves MySQL, Redis, RabbitMQ, and Meilisearch ports unpublished. It adds
  HTTP health checks for Laravel and both Next.js services; the existing
  infrastructure health checks remain enabled.
- `NEXT_PUBLIC_API_URL` is required for both the customer Next.js build and its
  runtime environment so browser requests do not fall back to localhost.
- Restored the missing World module admin list Blade view so its existing admin
  route and feature test work.

## Validation performed

- Laravel PHPUnit suite: **99 tests, 482 assertions passed**; one existing
  PHPUnit deprecation remains.
- Admin RBAC feature suite: **6 tests, 32 assertions passed**, including the
  login rate limit.
- Composer locked dependency audit: **0 advisories**.
- Admin UI dependency audit: **0 vulnerabilities**. Next.js 16.4.6 production
  build passed with Webpack (the project has a custom SVG Webpack loader).
- Customer Next.js dependency audit: **0 vulnerabilities**. TypeScript check and
  production build passed on Next.js 16.4.6.
- Changed admin BFF/auth files, admin authentication/RBAC pages, OTT operations
  pages, 35 React component files, and the product list pass focused ESLint. Full
  admin lint still reports **91 errors and 23 warnings** across the existing UI.
  Full customer lint now has zero errors and 50 warnings after fixing Hook
  ordering, playback ref/state issues, and
  client data loading. Customer type-check and production build pass; the
  repository-wide admin lint backlog remains unresolved.
- Go worker: `go test ./...` passed in the Go 1.22 container; handler and health
  packages have tests, while other packages report no test files.
- Flutter dependency resolution and tests passed on Flutter 3.47.0 / Dart 3.13.0:
  **3 tests passed**. Whole-app `flutter analyze --no-pub` exits nonzero with
  163 existing findings (one warning and 162 info-level lints, no errors).
- Production Compose configuration parsed with placeholder values. The merged
  configuration sets debug off, publishes only loopback-bound web ports, keeps
  infrastructure ports private, and injects the customer API URL into the
  frontend image build and runtime. Laravel and both Next.js services also have
  configured HTTP health checks.
- A live Next.js BFF smoke test returned HTTP 403 for cross-origin login,
  logout, invitation-acceptance, and proxied mutation requests.
- PHP syntax checks passed for all changed PHP files.
- A tracked-source scan found no private-key, AWS access-key, or Stripe live
  secret literals. It found one Google-style API key in the app-configuration
  seeder under the public `apiKey` setting; that client configuration should be
  restricted to the required APIs and app identifiers. The scan is heuristic
  and is not a substitute for a repository-history secret scanner.

## Deployment configuration

1. Create a private deployment environment from `.env.example` outside the
   repository (for example `/run/secrets/ott-production.env`); do not overwrite
   an existing local `.env`. Inject a unique Laravel `APP_KEY`, database and
   MySQL root passwords, RabbitMQ credentials, Meilisearch master key, exact
   HTTPS origins, and a public `NEXT_PUBLIC_API_URL` from the secret manager or
   deployment environment.
2. Set `ADMIN_UI_IMAGE`, `BACKEND_IMAGE`, `FRONTEND_IMAGE`, `WORKER_IMAGE`,
   `MYSQL_IMAGE`, `REDIS_IMAGE`, `RABBITMQ_IMAGE`, and `MEILISEARCH_IMAGE` to
   tested immutable image references (prefer digests), not floating tags. Build
   and publish the four application images in CI; bake the production
   `NEXT_PUBLIC_API_URL` into the customer frontend image. Choose loopback ports
   reachable by the deployment's TLS reverse proxy.
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

- Take and verify a database backup and media-storage snapshot before deployment.
  The workspace has no configured production backup target, and no backup/restore
  rehearsal was possible here.
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
- Milestone 13 remains partial: trusted geolocation and licensing-window data,
  provider/CDN/DRM delivery, Redis-backed multi-node concurrency, and full session
  lifecycle audit/revocation operations remain outstanding; see
  [Milestone 13](milestone-13-playback-security.md).
- Milestone 14 remains partially verified: a Firebase client for `com.swann.ott`,
  physical-device smoke tests, and iOS build/device validation on macOS are not
  available; see [Milestone 14](milestone-14-flutter-compatibility.md).
- Repository-wide Next.js lint has the findings listed above. The changed admin
  BFF files pass focused lint, but full lint acceptance is not met.
- Webhook tests cover signature rejection and replay deduplication for all four
  providers. Provider-backed payment fulfillment, refund reconciliation, and
  settlement reconciliation remain unverified.

This document records partial local verification only. Milestone 15 and the
overall milestones 6–15 goal remain incomplete until the external and repository
quality blockers above are resolved or explicitly accepted.
