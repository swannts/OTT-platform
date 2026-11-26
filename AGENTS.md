# OTT Platform engineering instructions

## Purpose

Maintain this production-oriented OTT monorepo with small, verifiable changes.
Prefer existing architecture over rewrites. Preserve customer and admin
behavior, authorization, payment contracts, playback security, database
compatibility, mobile identifiers, queue contracts, and persistent data.
Never claim a build or test passed unless it ran successfully.

Before changing code, inspect the owning application, its package/dependency
manifest, configuration, relevant tests, and current Git diff. Keep unrelated
user changes untouched. Do not deploy, publish, push, merge, or run destructive
database or Docker volume commands unless explicitly requested.

## Application ownership

| Path | Responsibility | Main conventions |
| --- | --- | --- |
| `apps/api` | Laravel modular monolith, REST APIs, persistence, business rules, auth, RBAC, payments, playback authorization, integrations | PHP/PSR-4; controllers delegate to existing domain services/actions; validate requests; protect mutations with backend authorization |
| `apps/web` | Customer streaming website | Next.js App Router; feature-based code; Server Components by default; preserve Axios/Redux thunks, DAL, HttpOnly `noor_session`, Cypress and Playwright |
| `apps/admin` | Administrative portal and API BFF | Next.js App Router; preserve OTT feature modules, the Minimals template boundary, server-only Sanctum credentials, and backend permission enforcement |
| `apps/mobile` | Flutter mobile and TV clients | Dart `snake_case.dart`; preserve app IDs, Firebase, signing, deep links, secure storage, notifications, payments, and media playback |
| `apps/worker` | Go queue and media worker | Idiomatic Go; short lowercase packages; preserve module identity, RabbitMQ names/routing keys, retry/DLX behavior, FFmpeg and storage semantics |
| `compose.yaml` | Local development stack | Preserve existing named volumes and data; do not use `down -v` |
| `compose.production.yaml` | Production topology definition | Validate configuration only unless deployment is explicitly authorized |

Read the relevant reference before cross-cutting changes:

- `docs/architecture/repository-migration-plan.md`
- `docs/architecture/domain-boundaries.md`
- `docs/architecture/naming-conventions.md`
- `docs/architecture/api-database-rbac-compatibility.md`
- `docs/architecture/frontend-architecture.md`
- `docs/architecture/worker-architecture.md`
- `docs/architecture/mobile-architecture.md`
- `docs/architecture/branding-configuration.md`

## Architecture and naming

- Repository identity is `ott-platform`; application directories use the
  responsibility names under `apps/`.
- Keep the customer-facing NOOR Islamic Cinema brand until an authorized
  platform setting changes it. Engineering defaults should be brand-neutral.
- Reuse the Laravel Settings module as the existing administrative source for
  configured branding; do not introduce a competing source of truth.
- Use feature/domain ownership and explicit public exports. Avoid circular
  dependencies, giant shared utilities, speculative packages, and empty layers.
- Use PascalCase for PHP/React types and components; camelCase for functions
  and variables; `snake_case` for Dart filenames; lowercase Go packages; and
  plural `snake_case` for new database tables.
- Use dot notation for new permissions. Do not rename existing routes, API
  fields, persisted role/permission identifiers, schema names, payment
  callbacks, serialized jobs, queue names, or client IDs as a cleanup task.
- Keep Next.js reserved route filenames unchanged. Keep secrets and private
  credentials server-side. The backend independently enforces authorization;
  hidden UI controls and proxy redirects are not security boundaries.

## Next.js practices

- Inspect the installed Next.js/React versions and app-specific configuration;
  do not assume generic examples fit the installed version.
- Keep Server Components as the default and client boundaries narrow. Avoid
  hydration mismatches, duplicate data requests, and exposing secrets to
  browser code.
- Preserve the customer web's shared Axios clients, server DAL, Redux store
  factory, typed async thunks, cookie authentication, route guards, and UI.
- Preserve the admin BFF and its HttpOnly cookie boundary. Never trust client
  permission claims for API operations.
- Use accessible semantic controls and the existing design system. Do not
  redesign a screen unless requested.

## Laravel, data, and security

- Keep Laravel a modular monolith. Trace route registrations, providers,
  model morph maps, jobs, migrations, and consumers before moving a class.
- Use Form Requests for complex input validation, API Resources for response
  transformations, Policies/middleware for authorization, and transactions at
  existing consistency boundaries.
- Do not alter applied migrations. Database/schema changes must be additive
  and backward compatible, with rollback guidance and isolated validation.
- Never expose payment secrets, tokens, passwords, signing material, or
  internal stack traces. Do not print `.env` contents or seed credentials.
- Preserve the 12 canonical administrative roles and registry-defined
  permissions in `apps/api/app/Support/Rbac/RbacRegistry.php`.

## Verification

Run the narrow checks for changed code, then broader checks when practical.
From each application directory:

```bash
# apps/web
npm run lint
npx tsc --noEmit
npm run build
npm run cypress:run -- --browser chromium

# apps/admin
npm run lint
npm run test:bff
npm run build

# apps/api
composer validate --no-check-publish
composer dump-autoload --no-scripts
php artisan route:list --path=api
vendor/bin/phpunit

# apps/worker
test -z "$(gofmt -l $(find . -name '*.go' -type f))"
go vet ./...
go test ./...

# apps/mobile
flutter analyze
flutter test
```

Use the package manager and exact scripts in the owning application's
manifest. If a local toolchain, database, or external service blocks a check,
report the command and the actual blocker. Do not conceal failures or claim
runtime readiness based only on compilation.
Format only changed Dart files; do not turn a touched-file change into a
whole-application formatting rewrite.

Validate infrastructure separately:

```bash
docker compose --env-file .env.example -f compose.yaml config -q
docker compose --env-file .env.production.example -f compose.production.yaml config -q
```

Configuration parsing does not prove image builds, service connectivity,
database migration safety, or production readiness.

## Change workflow

1. Understand the request and inspect the current source and worktree.
2. For nontrivial work, state a concise plan and identify compatibility risks.
3. Implement a complete, focused change following existing conventions.
4. Run relevant validation and inspect the final diff for accidental changes.
5. Update architecture or operational docs when behavior or ownership changes.
6. Report exact results, limitations, and files/commits changed.

Use Conventional Commits. Keep each migration milestone in its own commit.
Never stage unrelated user edits. Do not rewrite Git history or rename the
hosted repository automatically.
