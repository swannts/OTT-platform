# Naming conventions

## Repository and applications

- Repository and engineering project: `ott-platform` / **OTT Platform**.
- Responsibility-based application directories: `apps/api`, `apps/web`,
  `apps/admin`, `apps/mobile`, and `apps/worker`.
- Docker Compose service names: `api`, `web`, `admin`, and `worker` for the
  application services; supporting infrastructure retains its established
  service names.
- TypeScript feature files follow the existing app conventions. Keep Next.js
  reserved route filenames (`page`, `layout`, `route`, `loading`, `error`,
  `not-found`) unchanged.
- Go packages remain short and lowercase; exported identifiers use PascalCase.
- Dart source uses `snake_case.dart`; PHP classes use PascalCase.

## Domain names and compatibility

Use business-responsibility names for new code: `WatchProgress`,
`PayPerView`, `SubscriptionPlan`, `MediaAsset`, `PlaybackSession`, and
`TranscodingJob`. Existing API fields, database columns, persisted enum
values, queued payloads, and client identifiers remain compatibility
contracts. Do not globally replace historical terms such as Entertainment,
TV Show, or `continue_watch`; introduce clear names only in additive code or
versioned contracts.

New database tables use plural `snake_case`; columns use `snake_case` and
explicit units where useful. API additions use resource-oriented kebab-case
paths. New permissions use dot notation. Existing Laravel API routes, schema,
and RBAC identifiers are not renamed by this standardization.

The Flutter package name `swann_ott`, Android/iOS application identifiers,
Firebase configuration identifiers, persisted `swann` database name, and
existing volume names are retained because they are external or persistent
identity boundaries, not new engineering names.
