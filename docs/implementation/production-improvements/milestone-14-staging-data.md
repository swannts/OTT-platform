# Milestone 14: Production-like relational staging data

## Status: partially implemented

### Delivered in this increment

- Audited the current migration/model/factory/seeder footprint and documented important actual key types, logical links, unique constraints, soft deletion, and schema gaps.
- Hardened production/local administrator bootstrap to preserve existing passwords and refuse soft-deleted, non-admin, inactive, or unassigned accounts rather than silently granting Super Admin.
- Hardened local role/demo accounts so reruns preserve existing credentials and refuse unowned account collisions or new role assignments.
- Added opt-in Small/Standard/Large target profiles, fixed seed/reference-date context, exact database-name/host and cache isolation checks, explicit integration-isolation gates, and separate private staff/customer password requirements. Large profile checks PHP memory, local disk space, and requires operator confirmation of database capacity.
- Added manifest and per-record ownership tables, dataset-locked RBAC/staff/customer/profile/device generation, read-only status, and machine-readable ownership validation for those generated relationships.
- Added bilingual genre and catalog generation, genre mappings, and correctly linked series/season/episode trees. Catalog content is explicitly draft/unpublished and has no media asset, avoiding false playable or licensed claims. Catalog records use registry-stored UUIDs because the final entertainment/episode schema does not have slug columns.
- Added dataset-owned staging plans and plan-limitation mappings, customer subscription lifecycle rows, and synthetic BDT paid/failed transaction attempts. Attempts are recorded only in the legacy subscription transaction table; no provider calls, verified-payment rows, or fulfillment paths are used. Customer subscription flags are synchronized from current active staging subscriptions.
- Extended relational validation to plans, plan mappings, subscriptions and synthetic transactions. Added a composite ownership index so the Standard dataset's transaction checks complete efficiently. Added a feature test for generation, stable reruns, ownership counts, and validator results.
- Corrected local demo staff `user_type` seeding and aligned the livestream fixture test with its playable demo fixture.
- Added focused configuration and determinism tests.

### Validation

- PHP syntax checks passed for modified/new seeders and staging classes.
- PHP lint and `git diff --check` passed. The full Laravel API suite passed against the disposable MySQL 8.4.13 database: 175 tests and 900 assertions, with one existing PHPUnit deprecation.
- The Small-profile relational feature dataset validated with zero violations and reran without duplicate ownership rows.
- The Standard profile was generated in the disposable `ott_staging_test` database in 99 seconds: 10,000 customers, 18,000 profiles, 25,000 devices, 1,000 movies, 150 series, 450 seasons, 4,500 episodes, 8 plans, 15,000 subscriptions, and 45,000 synthetic payment attempts. The integrity report completed in about 3.8 seconds with `valid=true`, zero violations, and `complete=false` due to pending domains. PHP peak allocated memory was 305 MiB. A deterministic Standard rerun completed in 6.9 seconds and created zero new rows.
- Per-dataset storage was not measured. Large profile, frontend/admin workflows, browser verification, analytics, and search integration remain unverified.
- Only explicitly isolated disposable test databases were migrated or seeded; the project development database was not seeded or reset.

### Remaining acceptance work

Cast/crew, media fixtures, publication workflow, refunds/reconciliation, playback, engagement, marketing, support, notification/operations and analytics generators are not implemented. Integrity checks cover the domains listed above but do not yet cover the remaining domains. Search indexing, safe reset, peak-memory and per-dataset storage benchmarks, browser checks, and wider integration tests remain pending. The seeder command reports the manifest as `partial` and warns which configured targets remain ungenerated. Large profile is not verified. No CI/CD or Flutter work is included.
