# Milestone 14: Production-like relational staging data

## Status: partially implemented

### Delivered in this increment

- Audited the current migration/model/factory/seeder footprint and documented important actual key types, logical links, unique constraints, soft deletion, and schema gaps.
- Hardened production/local administrator bootstrap to preserve existing passwords and refuse soft-deleted, non-admin, inactive, or unassigned accounts rather than silently granting Super Admin.
- Hardened local role/demo accounts so reruns preserve existing credentials and refuse unowned account collisions or new role assignments.
- Added opt-in Small/Standard/Large target profiles, fixed seed/reference-date context, exact database-name/host and cache isolation checks, explicit integration-isolation gates, and separate private staff/customer password requirements. Large profile checks PHP memory, local disk space, and requires operator confirmation of database capacity.
- Added manifest and per-record ownership tables, dataset-locked RBAC/staff/customer/profile/device generation, read-only status, and machine-readable ownership validation for those generated relationships.
- Added focused configuration and determinism tests.

### Validation

- PHP syntax checks passed for modified/new seeders and staging classes.
- The six staging foundation unit tests passed (17 assertions) using PHPUnit without the repository's DB-preparing bootstrap.
- The normal PHPUnit bootstrap cannot connect to the configured `swann_test` MySQL database (`Access denied for user 'root'@'172.21.0.1'`, no password supplied). Feature integration tests, migrations against MySQL, staging generation, UI verification, and benchmarks have not run.
- No existing project database was seeded or reset.

### Remaining acceptance work

Customer/catalog/media/subscription/payment/playback/engagement/marketing/support/notification/operations and analytics generators are not implemented. Full business-level integrity checks, search/cache integration, safe reset, Standard generation and benchmark, browser checks, bilingual data coverage, and the remaining integration tests are pending. The seeder command reports the manifest as `partial` and explicitly warns that configured business targets are not generated. Large profile is not verified. No CI/CD or Flutter work is included.
