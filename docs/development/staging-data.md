# Staging data generation

The staging seeder is intentionally separate from `DatabaseSeeder`, the explicit local demo seeders, and test factories. It is opt-in and fails closed unless `STAGING_SEED_ENABLED=true`, the active database name exactly matches `STAGING_SEED_ALLOWED_DATABASE`, cache/queue/search/media isolation is explicitly confirmed, mail uses `array` or `log`, and the synthetic payment gateway is selected. `--force` does not bypass these checks.

## Current implementation status

Milestone 14 is in progress. Implemented generation currently covers RBAC, synthetic staff and customers, profiles and devices, bilingual genres and unpublished movies/series, season/episode hierarchies, bilingual editorial articles, shared fictional cast/crew credits, synthetic licensing windows/territories, bounded local media fixtures, staging plans and subscription histories, plan-eligible synthetic coupons, and synthetic legacy subscription transaction attempts. Editorial entries use reserved slugs, English/Bengali prose, historical publish dates, draft/published states, and synthetic estimated view counts. Coupons have dataset-scoped codes, date/status scenarios, redemption limits, and owned eligible-plan mappings; no redemptions or provider operations are fabricated. Media fixtures are generated test-pattern clips (5/25/50 for Small/Standard/Large, capped at 50), with FFprobe metadata, MP4 source, HLS 360p/720p renditions and audio, DASH, poster, preview, VTT/SRT tracks, and dataset-owned database rows. Only linked titles have media; all titles remain unpublished and playback authorization is unchanged. Payment attempts are local records only: no provider request, `verified_payments` row, payment fulfillment, refund, or reconciliation is generated.

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
STAGING_SEED_FFMPEG_BINARY=ffmpeg
STAGING_SEED_FFPROBE_BINARY=ffprobe
```

Also configure `CACHE_PREFIX` to exactly match `STAGING_SEED_CACHE_PREFIX`, use a lock-capable cache store (`file`, `database`, `redis`, or `memcached`), keep the queue on the isolated staging database, set the active mailer to `array` or `log`, and use a local staging media disk. The generator writes directly to its isolated database and does not call payment fulfillment or send messages. Do not copy real credentials or production database names into test fixtures.

## Commands

```bash
php artisan migrate --force
php artisan ott:seed-staging --profile=small --history-months=12 --seed=2026 --reference-date=2026-11-26 --dry-run
php artisan ott:seed-staging --profile=small --counts='{"staff":12,"customers":20,"profiles":30,"devices":25,"genres":12,"cast_crew":12,"movies":10,"series":2,"seasons":3,"episodes":20,"plans":5,"subscriptions":24,"payments":30,"media_assets":2}' --dry-run
php artisan ott:seed-staging --profile=small --history-months=12 --seed=2026 --reference-date=2026-11-26
php artisan ott:staging-data-status
php artisan ott:verify-staging-data --format=json
```

The dry run reports all configured safety failures and performs no writes. A real run also queries MySQL for the database name actually reached and compares it with `STAGING_SEED_ALLOWED_DATABASE` before acquiring a lock or writing a manifest. A successful run currently leaves a manifest in `partial` state because refunds/reconciliation, playback activity, watchlists/reviews/search history, marketing, support, notifications, operational history, analytics, and search indexing are pending. Media generation requires local FFmpeg and FFprobe and stops if an executable or expected output is unavailable. Files stay on the private disk under `media/staging/{dataset}`; assets, tracks, jobs and title links have ownership records. Source size and SHA-256 are read from the generated MP4, and the integrity validator checks the actual files through the application readiness service. Editorial rows are bilingual and use synthetic publication/view assumptions; no scripture, historical assertions or religious rulings are invented. Coupons are synthetic test codes linked only to dataset-owned plans; they have no redemption records and cannot trigger external payment actions. Four cast/crew credits are linked to each generated movie or series; talent profiles are shared across titles. Most licensed titles are restricted to Bangladesh, with some synthetic territory entitlements and expired/future windows for testing. Customers use reserved `example.test` emails, and no usable auth/device tokens are created. Profile volume is an approximate household distribution; device volume follows the selected profile target. The command refuses a completed manifest and refuses to change the configuration for an existing dataset. It can resume an identical partial/failed run using registered ownership rows; existing unregistered accounts are never adopted or modified. Ownership stores stable fixture keys and row identifiers because several legacy schemas do not retain fixture slugs.

Each generated subscription references an owned customer and staging plan, carries a plan snapshot and a valid period, and is marked active or inactive consistently with the fixed reference date. Trials use the plan's seven-day trial. Every generated transaction references an owned subscription and customer, uses `payment_type=synthetic-staging` and BDT, and records `external_request_sent=false`; failed retries stay failed and never trigger fulfillment. The legacy transaction table uses an additive currency migration.

### Verified disposable Standard run

On 2026-11-26, the Standard profile was generated against disposable MySQL 8.4.13 database `ott_staging_test` using local Redis only for the dataset lock. It generated 36 staff, 10,000 customers, 18,000 profiles, 25,000 devices, 25 genres, 2,000 fictional cast/crew, 1,000 movies, 150 series, 450 seasons, 4,500 episodes, 4,600 title credits, 1,035 license windows, 1,701 territory restrictions, 8 plans, 15,000 subscriptions, and 45,000 synthetic payment attempts. Initial core generation took 99 seconds and peaked at 305 MiB PHP allocated memory; adding shared cast/crew took 7.4 seconds, and adding licensing policies took 7.9 seconds. The staging integrity report completed in about 2.6 seconds with `valid=true`, zero violations, and `complete=false` because other domains remain unimplemented. A deterministic rerun of all currently implemented domains created zero new records in under 8 seconds. Storage was not measured per dataset. The Large profile was not run.

There is deliberately no reset command yet. Until every generated table has ownership tracking, selective deletion cannot be proven safe. Use a disposable database and recreate it through the database operator's isolated staging workflow when a run must be discarded.

## Test boundaries

The full Laravel API suite passed against fresh disposable MySQL (175 tests, 922 assertions; one existing PHPUnit deprecation). The focused relational staging feature test passed against that isolated database (1 test, 44 assertions; one existing PHPUnit deprecation). A separate one-asset run passed the application media-readiness checks with zero integrity violations and confirmed the H.264/AAC source plus HLS renditions/audio, DASH, poster and preview files via FFprobe and file checks. The validator intentionally exits nonzero while a manifest is `partial`, even when its currently supported checks report `valid=true` and no violations.

No staging account password is documented here. Supply a private staging-only password through `STAGING_SEED_STAFF_PASSWORD` and rotate it after use if it is shared outside the staging team.
