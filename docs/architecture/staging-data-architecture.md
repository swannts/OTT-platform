# Staging data architecture

## Separation

The Laravel API keeps four initialization paths separate:

| Path | Responsibility | Entry point |
| --- | --- | --- |
| Production foundation | Canonical RBAC and explicit first-administrator bootstrap; no sample business data | `DatabaseSeeder` outside local/testing |
| Local demo | Small explicit UI fixtures; no broad default reset | `LocalDemoDataSeeder` and focused catalog seeders |
| Staging | Isolated, identified, reproducible business datasets | `ott:seed-staging` |
| Automated tests | Scenario-specific data, isolated by the test suite | Factories and focused test fixtures |

## Current staging components

- `config/staging-data.php` defines opt-in safety settings and Small/Standard/Large volume targets.
- `StagingDataSafety` checks environment, exact database name, unique cache prefix, isolated queue/search/media, suppressed external delivery, synthetic payments, and Large-profile capacity confirmation.
- `StagingProfile` validates profile names, custom counts, history duration, and batch size.
- `StagingGenerationContext` holds the dataset ID, fixed seed, fixed reference date, history start, and a seeded PHP `Randomizer`.
- `staging_dataset_manifests` records configuration and run state. `staging_dataset_records` registers ownership for generated staff accounts.
- `StagingStaffGenerator` creates reserved-domain staff accounts and applies one canonical administrative role to each new account. `StagingCustomerGenerator` creates synthetic customer accounts, profile rows, and registered devices with historical registration dates. Both refuse to adopt existing accounts and preserve passwords and profile data on reruns.
- Status and integrity commands expose manifest and currently supported staff ownership checks.

The manifest remains `partial` while the other generation domains are not implemented. There is no reset operation until ownership is complete for each generated table.

## Run sequence and side effects

The command validates all options and safety settings before writing. It acquires a dataset-specific lock from the isolated cache namespace, seeds the canonical RBAC reference set, creates staff in bounded transactions, and records each generated account in the ownership registry. Direct database inserts are used for fixtures; payment, notification, mail, search indexing, media processing, and queue services are not invoked. Subsequent domain generators must preserve this boundary and register ownership before they can support reset.

The code presently supports RBAC/staff/customer/profile/device generation. Bilingual catalog/media assets, subscription/financial lifecycles, viewing activity, marketing/support, notifications, analytics consistency, search integration, safe reset, and application/browser verification remain open work.
