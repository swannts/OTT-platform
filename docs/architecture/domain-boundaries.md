# Domain boundaries

The platform remains a Laravel modular monolith with independently deployed
customer web, admin web, mobile/TV, and Go worker applications. Laravel APIs
and existing persistence remain the integration boundary; no new microservice
or shared runtime package is introduced.

## Current backend modules

The existing `apps/api/Modules/` tree includes catalog and media domains
(`Entertainment`, `Episode`, `Season`, `Genres`, `CastCrew`, `Video`,
`LiveTV`, `Banner`), identity and platform configuration (`User`, `Setting`,
`Language`, `Onboarding`), commerce (`Subscriptions`, `Coupon`, `Currency`,
`Tax`), and customer-facing information (`SEO`, `Page`, `FAQ`,
`NotificationTemplate`). `Ad`, `Filemanager`, `Frontend`, `Installer`, and
`Constant` remain framework/product modules. New security, analytics,
financial operations, support, and playback work also exists under
`apps/api/app/` and additive migrations; those areas have not all been
consolidated into module directories.

These are observed boundaries, not an instruction to move every class. Before
splitting or renaming a module, trace route registration, service providers,
model morph values, queued payloads, migrations, and client dependencies.

## Application ownership

- `apps/web` owns the customer streaming experience and consumer routes.
- `apps/admin` owns administrative workflows and the server-side admin BFF.
- `apps/mobile` owns Flutter mobile and TV clients.
- `apps/worker` owns asynchronous media and queue processing.
- `apps/api` owns shared business rules, persistence, authentication,
  authorization, API compatibility, payments, playback entitlements, and
  integrations.

Customer subscription status and viewer profiles are not administrative
roles. They remain separate account and entitlement concepts.
