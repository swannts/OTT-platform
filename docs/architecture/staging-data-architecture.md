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
- `staging_dataset_manifests` records configuration and run state. `staging_dataset_records` tracks stable fixture keys and row identifiers so resume checks do not rely on removed legacy slug columns. A composite dataset/entity/record lookup index supports integrity checks over larger datasets.
- `StagingStaffGenerator` creates reserved-domain staff accounts and applies one canonical administrative role to each new account. `StagingCustomerGenerator` creates synthetic customer accounts, profile rows, and registered devices with historical registration dates. `StagingCatalogGenerator` creates bilingual genres, draft movies and series, genre mappings, and valid season/episode hierarchies. `StagingEditorialGenerator` creates bilingual editorial articles with synthetic publication/view estimates, draft and published states, and owned slugs. `StagingCouponGenerator` creates date-scoped test offers linked to owned plans, without inventing redemptions. `StagingCastCrewGenerator` creates fictional actor/director profiles and links shared talent across titles. `StagingContentLicensingGenerator` adds synthetic date windows and territory restrictions to catalog rows. `StagingMediaFixtureGenerator` builds a bounded set of actual local H.264/AAC MP4 test clips, HLS 360p/720p plus audio, DASH, poster, preview and VTT/SRT files, and stores measured source metadata/checksum. `StagingSubscriptionGenerator` creates staging-only plans and limitation mappings, subscription histories, and non-provider synthetic payment attempts. These generators register ownership alongside inserts and refuse to adopt unrelated rows.
- Status and integrity commands expose manifest state plus staff/customer/profile/device ownership, catalog hierarchy/publication/media links, actual media readiness, subtitle-track and transcode-record ownership, bilingual editorial integrity, coupon-to-plan mappings, and plan/subscription/transaction relationships. The validator reports `valid` separately from `complete`; current datasets remain partial while later domains are pending.

The manifest remains `partial` while downstream generation domains are not implemented. Catalog entries intentionally remain unpublished; only the configured subset receives local media. There is no reset operation until ownership is complete for every generated table.

## Run sequence and side effects

The command validates all options and safety settings before writing. It acquires a dataset-specific lock from the isolated cache namespace, seeds the canonical RBAC reference set, creates staff in bounded transactions, and records each generated account in the ownership registry. Direct database inserts are used for relational fixtures; media processing is performed locally by FFmpeg/FFprobe only, without contacting a provider or dispatching queue jobs. Payment provider requests and fulfillment, notification, mail and search indexing are not invoked. Synthetic legacy subscription transactions explicitly record their local origin and never create provider-verified payment records. Subsequent domain generators must preserve this boundary and register ownership before they can support reset.

The code presently supports RBAC/staff/customer/profile/device generation, a bilingual unpublished catalog with genre mappings and series hierarchy, bilingual editorial articles, shared fictional cast/crew credits, licensing windows and territory restrictions, actual bounded local media fixtures, staging plans, customer subscription histories, and synthetic payment attempts. Publication workflows, refunds/reconciliation, viewing activity, customer engagement, marketing/support, notifications/operations, analytics consistency, search integration, safe reset, and application/browser verification remain open work.
