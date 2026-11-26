# Local demo data

The default `php artisan db:seed` remains safe and only initializes RBAC and an optional local administrator. To populate the customer catalog and the main admin workflows, run the explicit additive demo seeder from the Laravel container:

```sh
docker compose exec api php artisan db:seed --class='Database\Seeders\LocalDemoDataSeeder'
```

The PHPUnit bootstrap forces test mode and targets a separate `swann_test` database; it creates that database if needed. Never point feature tests using `RefreshDatabase` at the local `swann` database.

Set a private `DEMO_SEED_PASSWORD` in the local environment first. The seeder refuses non-local environments and does not truncate existing records. It can be run repeatedly; its fixture records use stable demo identifiers and `example.test` accounts. Existing account passwords are preserved.

It creates local sample accounts, catalog titles and draft series/episodes/video/live-channel metadata, Bangladeshi currency and location references, plans and subscriptions, demo payment/reconciliation/refund records, a draft marketing campaign and coupon, a support ticket, content-review and audit records, analytics and operations examples, a playback diagnostic, a license window, customer notifications, customer profiles, watchlist/history/search examples, and sample editorial and customer-engagement rows. Demo payment rows use the `demo` gateway and do not contact a payment provider or deliver campaigns.

This seeder does not create fake media files, upload jobs, credentials, payments to external providers, or playable streams. Tables that record real runtime activity (uploads, playback sessions, login sessions, downloads, coupon reservations, and payment callbacks) remain empty until those actions occur. The older full catalog replacement seeders remain separate and require `DEMO_CATALOG_REPLACE=true` in addition to a local/testing environment; do not enable that flag when you want to preserve an existing catalog.
