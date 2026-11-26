# Local demo data

The default `php artisan db:seed` remains safe and only initializes RBAC and optional local administrative test accounts. To populate the customer catalog and the main admin workflows, run the explicit additive demo seeder from the Laravel container:

```sh
docker compose exec api php artisan db:seed --class='Database\Seeders\LocalDemoDataSeeder'
```

The PHPUnit bootstrap forces test mode and targets a separate `swann_test` database; it creates that database if needed. Never point feature tests using `RefreshDatabase` at the local `swann` database.

Set a private `DEMO_SEED_PASSWORD` in the local environment before running the full `LocalDemoDataSeeder`. Seeders refuse non-local environments and do not truncate existing records. They can be run repeatedly; fixture records use stable demo identifiers. Existing account passwords are preserved.

## Administrative role test logins

For local authorization/UI testing, `LocalAdminRoleAccountsSeeder` creates one
admin login for each of the 12 canonical roles. It runs as part of the default
local `php artisan db:seed` when `DEMO_SEED_PASSWORD` is set. To run only this
seeder inside the API container:

```sh
docker compose exec api php artisan db:seed --class='Database\Seeders\LocalAdminRoleAccountsSeeder'
```

All listed accounts use the same password configured in the private local
`DEMO_SEED_PASSWORD` setting. Set your own value there; this document does not
store or reveal it. Seeding resets these demo accounts to that configured
password. These accounts are only for local/testing environments and must not
be created in production.

| Role | Login email |
| --- | --- |
| Super Admin | `admin@swann.com` |
| Platform Admin | `role.platform-admin@noorcinema.test` |
| Content Manager | `role.content-manager@noorcinema.test` |
| Content Editor | `role.content-editor@noorcinema.test` |
| Content Reviewer | `role.content-reviewer@noorcinema.test` |
| Media Manager | `role.media-manager@noorcinema.test` |
| Subscription Manager | `role.subscription-manager@noorcinema.test` |
| Finance Manager | `role.finance-manager@noorcinema.test` |
| Marketing Manager | `role.marketing-manager@noorcinema.test` |
| Customer Support | `role.customer-support@noorcinema.test` |
| Analytics Manager | `role.analytics-manager@noorcinema.test` |
| Technical Operations Admin | `role.technical-operations-admin@noorcinema.test` |

It creates local sample accounts, catalog titles, six active bilingual TV-show cards, and draft series/episode/video metadata. TV-show records are clearly labeled sample metadata and do not include playable episodes. It also adds an active Live TV demo channel mapped to a public KSA Qur’an TV YouTube broadcast for playback testing. The channel is clearly labeled as demo data; the platform does not own or re-host the broadcast. The seeder also creates six published, clearly labeled sample blog articles with categories and cover images so `/blog` and article detail routes have content to render. It also creates Bangladeshi currency and location references, plans and subscriptions, demo payment/reconciliation/refund records, a draft marketing campaign and coupon, a support ticket, content-review and audit records, analytics and operations examples, a playback diagnostic, a license window, customer notifications, customer profiles, watchlist/history/search examples, and sample editorial and customer-engagement rows. Demo payment rows use the `demo` gateway and do not contact a payment provider or deliver campaigns.

To seed only TV-show cards in a local environment, run `php artisan db:seed --class="Database\\Seeders\\LocalDemoTvShowsSeeder" --force` from `apps/api`.

This seeder does not create fake media files, upload jobs, credentials, or payments to external providers. Tables that record real runtime activity (uploads, playback sessions, login sessions, downloads, coupon reservations, and payment callbacks) remain empty until those actions occur. To seed only the Live TV fixture, run `docker compose exec api php artisan db:seed --class='Database\Seeders\LocalDemoLiveTvSeeder'`. To seed only the blog fixtures, run `docker compose exec api php artisan db:seed --class='Database\Seeders\LocalDemoBlogSeeder'`. The older full catalog replacement seeders remain separate and require `DEMO_CATALOG_REPLACE=true` in addition to a local/testing environment; do not enable that flag when you want to preserve an existing catalog.
