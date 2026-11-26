# Repository migration plan

## Scope and baseline

This plan starts the brand-independent monorepo migration on branch
`refactor/brand-independent-monorepo`. The Git remote remains
`git@github.com:swannts/OTT-platform.git`; renaming the hosted repository is an
owner action and is outside this migration.

The baseline was captured on 2026-11-26 before repository restructuring. The
working tree already contained unrelated edits to `docker-compose.yml`,
`nextjs-web/.gitignore`, and `nextjs-web/playwright.config.ts`; these were
present while checks ran and must remain separate from migration commits.

| Area | Baseline command | Result |
| --- | --- | --- |
| Customer web | `npm run lint` in `nextjs-web` | Passed |
| Customer web | `npx tsc --noEmit` in `nextjs-web` | Passed |
| Customer web | `npm run build` in `nextjs-web` | Passed; Next.js 16.4.6 |
| Customer journeys | `npm run cypress:run -- --browser chromium` in `nextjs-web` | 59 tests passed across 12 specs in the preceding validation run |
| Admin portal | `npm run lint` in `admin-ui` | Passed |
| Admin portal | `npm run test:bff` in `admin-ui` | Passed; 1 test |
| Admin portal | `npm run build` in `admin-ui` | Passed with existing Next.js warnings about re-exported `dynamic` route config |
| Laravel | `composer dump-autoload --optimize --no-scripts` | Passed; 52,178 classes |
| Laravel | `php artisan route:list --path=api --except-vendor` | Passed; 323 routes listed |
| Laravel | `php artisan test` | Not available: this Laravel install does not define the `test` command |
| Laravel | `vendor/bin/phpunit` | Blocked: test bootstrap cannot connect to the isolated `swann_test` database (MySQL access denied for root with no password) |
| Go worker | `go test ./...`, `go vet ./...` | Not run: Go CLI is not installed |
| Mobile | Flutter analyze and tests | Blocked: installed Dart 3.11.3 does not meet `flutter_custom_tabs 2.6.0`'s Dart `^3.12.0` requirement |
| Docker | `docker compose config -q` | Passed for the current working-tree Compose file |

These checks are a source/runtime baseline, not a production-readiness claim.

## Current repository structure

The repository currently keeps five independently managed applications at the
root:

| Current path | Responsibility and technology | Proposed path |
| --- | --- | --- |
| `swann-laravel-web/` | Laravel 12 modular monolith, API, admin APIs, database, queues and integrations | `apps/api/` |
| `nextjs-web/` | Next.js 16 customer website, Redux/Axios data flow, Cypress and Playwright tests | `apps/web/` |
| `admin-ui/` | Next.js 16 administrative portal, API BFF and RBAC UI | `apps/admin/` |
| `flutter-app/` | Flutter mobile and TV application | `apps/mobile/` |
| `go-worker/` | Go media/background worker using RabbitMQ and MySQL | `apps/worker/` |

Root-level `docs/`, `scripts/`, environment templates, `README.md`,
`AGENTS.md`, and `docker-compose.yml` support the applications. Each app owns
its dependencies, lockfiles, Dockerfiles, tests and configuration. Docker
Compose currently uses service names `admin-ui`, `frontend`, `backend`, and
`worker`, with persistent MySQL, Redis, RabbitMQ and Meilisearch volumes. There
are no tracked GitHub Actions workflows in this checkout.

The current checkout has 6,673 tracked files and is about 4.8 GB excluding
`.git`; installed dependencies, build outputs and local caches account for
much of the on-disk size. `nextjs-web/test-results/` contains 99 tracked
Playwright result/artifact files and is targeted for cleanup in M2. Keep
application source assets and lockfiles; do not delete local dependency
directories simply to make a move smaller.

## Proposed structure

```text
ott-platform/
├── apps/{api,web,admin,mobile,worker}/
├── docs/architecture/
├── scripts/
├── compose.yaml
├── compose.production.yaml   # only if a production Compose definition exists
├── AGENTS.md
└── README.md
```

Create `packages/`, `infrastructure/`, or test subdirectories only when a
real shared implementation or operational artifact belongs there. Do not add
empty architecture layers. Keep each app's existing technology and internal
layout intact during the path migration.

## Affected configuration and references

The initial reference inventory found old application paths in the root
Compose file, root and Laravel READMEs, environment templates, production
backup script, RBAC and implementation documents, package metadata, Laravel
tests/controllers, and the customer Playwright configuration. Dockerfiles are
app-local and will move with their app, but their relative `COPY` paths and
Compose build contexts must be checked after relocation. CI workflows were
not found. Environment variable names and externally consumed settings must be
reviewed before changing them.

The Laravel module namespaces and routes are established contracts. Customer
web authentication, Axios, Redux, Cypress/Playwright, and admin BFF/RBAC
behavior must remain unchanged. Go module path `ott-worker`, Flutter package
and Android/iOS identifiers, API URLs, database schema, serialized queue
contracts, payment callbacks, and persistent volume names are compatibility
boundaries. The customer-facing NOOR branding is a configured product brand
and must remain intact; only legacy engineering/product identity should be
removed from active engineering defaults. Third-party attribution and
historical implementation records remain intact.

## Migration sequence and compatibility risks

1. **M1 — Analysis and baseline:** capture this document, the current layout,
   dependencies, references, and baseline check results.
2. **M2 — Generated artifacts:** remove tracked generated browser test output
   from Git and extend ignore rules without deleting source assets or rewriting
   history. Review tracked test artifacts locally for sensitive material; do
   not print discovered values.
3. **M3 — Application paths:** use Git-aware moves into `apps/`; update every
   path reference in small batches. Preserve the app-internal package names,
   namespaces, identifiers, module path, lockfiles and app behavior.
4. **M4 — Docker and scripts:** rename Compose files and service names only
   after updating contexts, mounts, service DNS, health checks and scripts.
   Keep existing named volumes and database/queue/cache identifiers unchanged.
5. **M5–M8 — Internal code organization:** assess Laravel, customer/admin,
   Go and Flutter naming independently. Rename only where the class/file
   responsibility is clear; preserve public API, serialized class and client
   contracts.
6. **M9–M11 — Branding, conventions, CI and docs:** use existing platform
   settings and authorization for configurable branding; avoid a new settings
   source of truth or multi-tenancy. Document API/database/RBAC naming without
   cosmetic schema or role-ID migrations.
7. **M12 — Regression:** run the full available app, integration and Compose
   checks in an environment with compatible SDKs and isolated test databases.
   Report missing toolchains or external services explicitly.

Key risks are stale absolute/relative paths, ignored dependency directories
when moving large apps, runtime behavior that depends on Compose service DNS,
production scripts that expect current Compose filenames, undocumented
external use of app URLs or environment keys, and queued jobs that serialize
PHP class names. Generated Playwright traces may contain request data and must
be reviewed without exposing values. No database table/column, role identifier,
permission registry entry, application bundle ID, payment endpoint, queue
namespace, Redis prefix, or persistent volume will be cosmetically renamed.

## Rollback

Each milestone is committed separately on the feature branch. If a phase
fails, stop at that commit and revert only the migration commit(s) after
reviewing dependent work. Git path moves and configuration edits are
reversible; do not use `git reset --hard` or force-push. Database data,
persistent Docker volumes, external callback registrations, account settings,
and deployed secrets are not changed by the planned repository-only migration.
For any future additive data migration or external configuration change, write
and validate a separate rollback procedure before execution.

## Current milestone status

| Milestone | Status | Evidence / remaining work |
| --- | --- | --- |
| M1 — Repository analysis and baseline | Implemented and verified | This plan and baseline table; route/autoload/Compose checks pass, with platform limitations recorded. |
| M2 — Generated artifact cleanup | Implemented and verified | 99 generated Playwright outputs removed from Git tracking, preserved locally, and covered by root ignore rules. A path-only heuristic scan found no common token/password patterns in the tracked artifacts. |
| M3 — Application directory migration | Implemented and verified | All five applications moved under `apps/`; root README, API docs, test help text, Compose build contexts/mounts, and customer/admin package names updated. Compose validation, web lint/typecheck, admin lint/BFF test passed. |
| M4 — Docker, scripts and configuration | Implemented but not fully verified | Local and historical production Compose definitions are now `compose.yaml` and `compose.production.yaml`; service DNS and application image names are standardized. Existing Docker volume names are explicitly preserved. Both Compose configurations parse with validation inputs. The running stack was intentionally left untouched; production deployment is not exercised. |
| M5 — Laravel internal naming | Implemented and verified | Renamed the clearly scoped chart, push notification, content resource, and PayPerView classes; split watchlist and watch progress services without changing routes or response data. Composer autoload, syntax, 323-route, and 154-test PHPUnit checks pass (762 assertions; one PHPUnit deprecation). `EntertainmentsController` also owns playback/session endpoints and `WebPaymentApiController` owns subscription, billing, and webhook operations, so both remain unchanged. |
| M6 — Web/admin organization | Implemented and verified | Existing feature-based customer and OTT-admin areas were reviewed and retained; template sections, stable aliases, Redux/Axios, DAL, BFF, auth, and tests remain in place. Architecture is documented without creating unused shared packages or moving established template UI. Fixed the customer root layout's invalid script placement. Customer web lint, TypeScript check, and production build pass; Cypress passes all 59 tests across 12 specs. Admin lint, BFF test, and production build pass (the build reports existing Next.js config warnings). |
| M7 — Go worker organization | Implemented but not fully verified | The existing worker already follows `cmd/worker` plus `internal/{broker,config,domain,handlers,health,services}`. Documented the package boundaries and confirmed queue/exchange contracts and the `ott-worker` module path are unchanged. Go is unavailable here, so gofmt, vet, and tests could not run. |
| M8 — Flutter organization | Partially implemented | Documented existing screen-oriented feature areas and protected mobile identities. No mass file move was attempted: 337 Dart files and 276 package-import consumers would be affected, while installed Dart 3.11.3 cannot resolve `flutter_custom_tabs 2.6.0` (requires Dart 3.12+). Flutter analysis/tests remain blocked pending a compatible SDK. A read-only formatter check reports 300 of 338 Dart files need formatting; bulk formatting is deferred as unrelated churn. |
| M9 — Brand-independent configuration | Partially implemented | Genericized Laravel framework defaults and engineering documentation while retaining NOOR customer branding and existing database, volume, Flutter, Firebase, and storage identities. Documented the existing Laravel Settings source and gaps. Full brand independence remains incomplete: web screens/URLs/assets remain NOOR-specific and there is no single sanitized public brand contract consumed by Next.js. |
| M10 — API/database/RBAC documentation | Implemented and verified | Added domain, naming, and API/database/RBAC compatibility references grounded in current Laravel modules, routes, migrations, and `RbacRegistry`. Documented all 12 canonical administrative roles and key compatibility boundaries; no route, schema, role, or permission identifiers changed. |
| M11 — CI/CD and architecture docs | Implemented but not fully verified | Added path-filtered GitHub Actions for web/admin checks, Laravel syntax/migrations/tests with MySQL, Go tests/vet/gofmt, Flutter analysis/tests, Compose parsing, secret scanning, and pull-request dependency review. Updated root engineering guidance and README links; added overview, structure, and backend architecture docs. Workflow YAML parses locally and both Compose configs parse with validation inputs. Hosted workflow execution and any production deployment remain unverified. |
| M12 — Full regression | Partially implemented | Customer web lint/typecheck/build and all 59 Cypress tests pass; admin lint/BFF/build pass; isolated Laravel migrations and all 154 PHPUnit tests pass (762 assertions; one deprecation); 323 Laravel API routes and both Compose configurations validate. Go is unavailable, and Flutter dependency resolution is blocked by Dart 3.11.3 versus the package requirement of 3.12+. GitHub-hosted CI and external integrations remain unverified. |
