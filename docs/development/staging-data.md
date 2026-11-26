# Staging data generation

The staging seeder is intentionally separate from `DatabaseSeeder`, the explicit local demo seeders, and test factories. It is opt-in and fails closed unless `STAGING_SEED_ENABLED=true`, the active database name exactly matches `STAGING_SEED_ALLOWED_DATABASE`, cache/queue/search/media isolation is explicitly confirmed, mail uses `array` or `log`, and the synthetic payment gateway is selected. `--force` does not bypass these checks.

## Current implementation status

Milestone 14 is in progress. The command currently creates the RBAC foundation and synthetic administrative staff only. It does **not** yet generate the configured customer, content, billing, playback, marketing, support, notification, or analytics volumes. The profile counts are targets for the remaining generators, not claims about rows currently present.

The configured default profile is `standard`; `small` is recommended while developing the remaining generators. `large` additionally requires explicit database-capacity confirmation, the configured PHP memory limit, and local free-storage thresholds. These local checks do not measure a remote database volume; database capacity confirmation remains an operator responsibility. Profiles and deterministic run inputs live in `apps/api/config/staging-data.php`.

## Configure an isolated target

Use a disposable staging database and unique infrastructure resources. Set these variables only in the staging service environment:

```dotenv
STAGING_SEED_ENABLED=true
STAGING_SEED_ALLOWED_DATABASE=your_actual_staging_database
STAGING_SEED_ALLOWED_DB_HOST=your_actual_staging_database_host
STAGING_SEED_DATASET_ID=staging-small
STAGING_SEED_PROFILE=small
STAGING_SEED_RANDOM_SEED=2026
STAGING_SEED_HISTORY_MONTHS=12
STAGING_SEED_REFERENCE_DATE=2026-11-26
STAGING_SEED_BATCH_SIZE=500
STAGING_SEED_STAFF_PASSWORD=<private password of at least 16 characters>
STAGING_SEED_CUSTOMER_PASSWORD=<separate private password of at least 16 characters>
STAGING_SEED_CACHE_PREFIX=<unique staging cache prefix>
STAGING_SEED_QUEUE_ISOLATED=true
STAGING_SEED_SEARCH_ISOLATED=true
STAGING_SEED_MEDIA_ISOLATED=true
STAGING_SEED_EXTERNAL_DELIVERY_DISABLED=true
STAGING_SEED_PAYMENT_GATEWAY=synthetic
STAGING_SEED_LARGE_CAPACITY_CONFIRMED=false
STAGING_SEED_LARGE_MIN_MEMORY_MB=2048
STAGING_SEED_LARGE_MIN_FREE_DISK_GB=10
```

Also configure `CACHE_PREFIX` to exactly match `STAGING_SEED_CACHE_PREFIX`, use a lock-capable cache store (`file`, `database`, `redis`, or `memcached`), keep the queue on the isolated staging database, set the active mailer to `array` or `log`, and use a local staging media disk. The generator writes directly to its isolated database and does not call payment fulfillment or send messages. Do not copy real credentials or production database names into test fixtures.

## Commands

```bash
php artisan migrate --force
php artisan ott:seed-staging --profile=small --history-months=12 --seed=2026 --reference-date=2026-11-26 --dry-run
php artisan ott:seed-staging --profile=small --history-months=12 --seed=2026 --reference-date=2026-11-26
php artisan ott:staging-data-status
php artisan ott:verify-staging-data --format=json
```

The dry run reports all safety failures and performs no writes. A successful initial generation currently leaves a manifest in `partial` state because catalog, subscription, payment, playback, engagement, marketing, support, notification, and analytics generators are still pending. The seed command creates RBAC, staff, synthetic customer accounts, household profiles, and registered devices. Customers use reserved `example.test` emails, and no usable auth/device tokens are created. Profile volume is an approximate household distribution; device volume follows the selected profile target. The command refuses a completed manifest and refuses to change the configuration for an existing dataset. It can resume an identical partial/failed run using registered ownership rows; existing unregistered accounts are never adopted or modified.

There is deliberately no reset command yet. Until every generated table has ownership tracking, selective deletion cannot be proven safe. Use a disposable database and recreate it through the database operator's isolated staging workflow when a run must be discarded.

## Test boundaries

The unit foundation suite checks profile targets, deterministic random inputs, and closed safety defaults. Feature tests for the existing bootstrap seeders require the isolated `swann_test` MySQL database. The current checkout's configured MySQL credentials are rejected, so those integration tests and any generated staging database have not been verified in this environment.

No staging account password is documented here. Supply a private staging-only password through `STAGING_SEED_STAFF_PASSWORD` and rotate it after use if it is shared outside the staging team.
