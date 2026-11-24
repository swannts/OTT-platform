# Milestone 9 — Marketing and Campaign Management

## Delivered

- Added a marketing admin dashboard with campaign calendar/status queue, campaign builder, consent-based segment counts, homepage banner editor, approval actions, and delivery analytics.
- Added campaign types for email/push notifications, coupon promotions, public landing pages, and featured content collections. Public endpoints expose only published campaign data and aggregate campaign metrics; no audience email addresses or user identities are returned.
- Added draft, submit, independent approve/reject, scheduled publication, pause, and expiration states. Campaign and banner mutations, decisions, scheduled publication, and expiration are recorded in the admin audit log.
- Added channel-specific email/push consent preferences. Both channels default to opt-out. Recipient selection is server-side, filters to active accounts, requires verified email for email, and delivery jobs recheck current consent and segment membership immediately before sending.
- Added campaign email and Firebase push delivery jobs, deterministic A/B assignment, tracked click/open events, unsubscribe tokens, and queue idempotency. Email unsubscribe pages require a confirmation POST so link scanners do not revoke consent by previewing a URL.
- Linked promotional campaigns to active coupons and revalidate coupon eligibility on approval/scheduling. Campaign actions do not alter subscription or payment records.
- Added scheduled banner windows and enforced them across the public banner and OTT slider query paths. Public banner results ignore caller-supplied inactive status filters.
- Added public campaign landing and featured-collection APIs for the Next.js frontend.

## Validation

- Docker Laravel migrations through `2026_11_24_000015` applied successfully.
- Reconciled the canonical admin roles and permissions with `RbacFoundationSeeder`; this does not change user-to-role assignments.
- Focused marketing, finance, subscription, and playback PHPUnit suites: 34 tests and 147 assertions passed. PHPUnit reported one existing deprecation.
- Admin UI `npm run build` passed on Next.js 15.5.29. Existing warnings concern dynamic exports in unrelated pages and the Node `url.parse()` deprecation.
- At the time of this milestone, `admin-ui` had no ESLint configuration, so its declared lint command was unavailable. Milestone 15 added `admin-ui/eslint.config.mjs` and recorded a clean full lint scan; this historical limitation is resolved.
- `php artisan schedule:list` confirms `marketing:publish-scheduled` runs every minute.

## Operational setup and limits

- Production must run Laravel's scheduler (`schedule:run`) and workers for the `notifications` queue. Laravel mail transport and Firebase credentials must be configured before live delivery; live email/push providers were not exercised here.
- Push delivery uses the existing Firebase topic convention `user_{id}`. Client applications must subscribe to those topics and expose the authenticated `GET/PUT /api/me/marketing-consents` preference endpoints to customers.
- Email open counts depend on remote image loading and may be suppressed or proxied by mail clients. They are directional engagement metrics.
- The marketing landing and featured-collection APIs are backend endpoints; this milestone does not add a new public Next.js landing-page route.
