# Monorepo migration report

## Outcome

The repository now groups its five existing applications under `apps/` and
uses responsibility-oriented Compose service and image names. Laravel remains
the modular monolith and system of record. Existing client, route, database,
role, permission, payment, playback, and queue contracts were preserved.

The migration is **not fully complete**. Flutter still has its legacy
screen-oriented directory layout, full product brand independence is not
implemented, Go could not be checked locally, and GitHub-hosted workflows have
not run in this checkout. See the milestone table in
[`repository-migration-plan.md`](repository-migration-plan.md) for the exact
status of every phase.

## Repository structure

```text
apps/
  api/       Laravel 12 modular monolith and APIs
  web/       Next.js customer website
  admin/     Next.js administrative portal and BFF
  mobile/    Flutter mobile and TV client
  worker/    Go queue and media worker
compose.yaml
compose.production.yaml
docs/architecture/
scripts/
.github/workflows/
```

The application moves were:

| Previous path | Final path |
| --- | --- |
| `swann-laravel-web/` | `apps/api/` |
| `nextjs-web/` | `apps/web/` |
| `admin-ui/` | `apps/admin/` |
| `flutter-app/` | `apps/mobile/` |
| `go-worker/` | `apps/worker/` |

No empty shared packages were added. Tests, assets, lockfiles, dependencies,
and internal application structures remain within their owning applications.

## Laravel class names

The clearly scoped names were updated without changing routes or response
contracts:

- `AdminChartService` → `AnalyticsChartService`
- `PushNotificationHelperService` → `PushNotificationService`
- `CommonContentDetails` → `ContentDetailsResource`
- `PerviewPaymentController` → `PayPerViewPaymentController`
- `WatchlistAndContinueWatchService` split into `WatchlistService` and
  `WatchProgressService`

`EntertainmentsController` still handles playback/session responsibilities in
addition to catalog routes, and `WebPaymentApiController` still owns checkout,
subscription, billing, and provider webhooks. They were retained because
their names cannot be changed safely without a wider contract and boundary
review.

## Configuration and branding

- `docker-compose.yml` became local `compose.yaml`; the historical production
  definition is restored as `compose.production.yaml`.
- Compose service names use `api`, `web`, `admin`, and `worker`; canonical app
  image names and `ott-dev`/`ott-prod` project names are documented.
- Existing Docker volume names, database name, RabbitMQ contracts, and running
  containers were not renamed or recreated.
- Laravel framework defaults and repository documentation use `OTT Platform`;
  current customer-facing defaults remain `NOOR Islamic Cinema`.
- Existing Laravel Settings remains the source for stored branding values.
  The customer website still has NOOR-specific source labels, URLs, and assets,
  and no single sanitized brand document is consumed by Next.js yet.
- Flutter `swann_ott`, Android/iOS `com.swann.ott`, Firebase configuration,
  storage paths, and signing identities remain unchanged to preserve installed
  client and persisted-data compatibility.

## API, database, and RBAC compatibility

- Laravel still registers 323 API routes. No API path or response field was
  intentionally changed by this migration.
- No historical migration or database table/column was renamed.
- `RbacRegistry` still defines 12 fixed administrative roles and 79
  permissions. Canonical identifiers, including `super-admin`, are unchanged.
- Payment callbacks, playback authorization, and worker RabbitMQ exchange,
  queue, and routing-key values remain intact.
- No production database, external integration, or deployed environment was
  modified.

## Validation performed

| Area | Result |
| --- | --- |
| Customer web | `npm run lint`, `npx tsc --noEmit`, and `npm run build` passed. Cypress passed 59/59 tests across 12 specs after the layout correction. |
| Admin portal | `npm run lint`, `npm run test:bff` (1/1), and `npm run build` passed. The build retains pre-existing Next.js warnings about re-exported `dynamic` route configuration. |
| Laravel | Composer autoload and PHP syntax checks passed; route listing returned 323 API routes. On an isolated disposable MySQL container, migrations completed and PHPUnit passed 154 tests / 762 assertions with one PHPUnit deprecation. |
| RBAC catalog | Source registry reports 12 roles and 79 permissions. |
| Docker Compose | Local and production Compose files both parse. Production validation used fake CI-only environment values; no images were built or deployed. |
| Workflow YAML | Both GitHub Actions workflow files parse locally. The hosted actions were not run. |
| Go worker | Not verified locally: the Go CLI is unavailable. Workflow config includes formatting, vet, and test checks. |
| Flutter | Not verified: Dart 3.11.3 cannot resolve `flutter_custom_tabs 2.6.0`, which requires Dart 3.12+. A read-only formatter check reports 300 of 338 Dart files would be reformatted. |
| Security | Generated Playwright artifacts were checked with a path-only heuristic for common secret patterns; no matches were found. The configured Gitleaks and dependency-review workflows have not run. |

The disposable MySQL test container was stopped and removed. The existing
development Compose stack was left running and untouched. No image build,
production deployment, payment-provider call, Firebase call, or restore test
was performed.

## Remaining legacy identifiers

The following names remain deliberately:

- `swann` database and `swann-ott-local_*` volumes: identify existing local
  persistent data. Rename only through a verified data/restore migration.
- `swann_ott` and `com.swann.ott`: application/store identities used by
  installed Flutter, Android, iOS, deep-link, signing, and Firebase setup.
- `swann-laravel` media paths and related storage directories: persisted file
  locations; moving them requires a data migration.
- NOOR product labels, URLs, images, and legal references: current product
  identity is intentionally preserved. Full brand independence is incomplete.
- Seeded local administrator test identity and third-party attribution: test
  fixtures or ownership/history, not engineering project defaults.

## Rollback and commit record

Milestones were committed independently on branch
`refactor/brand-independent-monorepo`. To undo a phase, review dependencies and
revert only that phase's commit; do not reset or force-push. Repository changes
do not roll back modified local database state, external provider settings, or
production secrets. No such external changes were made here.

| Milestone | Commit |
| --- | --- |
| M1 baseline | `81616d8` |
| M2 generated artifact cleanup | `d776de1` |
| M3 application directory migration | `13fc7bd` |
| M4 Docker and configuration | `6074824` |
| M5 Laravel naming | `22b855f` |
| M6 frontend organization | `5f789a0` |
| M7 worker architecture | `0a504b9` |
| M8 mobile assessment | `37d6688` |
| M9 branding configuration | `7560944` |
| M10 API/database/RBAC docs | `db27ca7` |
| M11 CI and architecture docs | `d7ef263` |
| M12 regression report | This document's commit |
