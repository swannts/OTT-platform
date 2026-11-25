# Admin UI functional completion plan

Status: planned; implementation has not started from this plan.

## Scope

This plan covers the OTT administration routes in the Next.js admin UI and their
Laravel APIs. The dashboard is English-only. It excludes the customer-facing
Next.js site, Flutter, and generic Minimal Kit demo pages unless a route is
reachable from the OTT admin navigation.

The current admin has implemented screens and API connections for RBAC, content
review/licensing, media assets, subscriptions, finance, marketing, support,
analytics, and technical operations. Laravel has corresponding protected
endpoints for most of those workflows. This is a source-level inventory, not
proof that each screen works end to end with representative data. The clearest
product gap is a full content catalog workflow: the visible content pages focus
on review and licensing, rather than creating and editing movie, series,
episode, and live-channel metadata.

## Completion standard

A section is complete when its authorized user can complete the intended task
against persisted backend data; refresh and see the saved result; receive useful
loading, empty, validation, permission, and server-error feedback; and cannot
perform the action through a direct API call without the required permission.
Critical flows must have Laravel feature coverage and browser-level checks.
Dashboard metrics and tables must come from backend responses, with no demo
values presented as live platform data.

## Milestones

### 1. Establish a trustworthy baseline

- Inventory every visible admin route, action, permission, and Laravel endpoint.
- Exercise every route with a super-admin and a least-privilege role; record
  broken requests, missing permissions, and UI actions without a backend route.
- Add browser smoke coverage for sign-in, navigation, loading/error/empty states,
  and responsive layouts.
- Replace any remaining template/demo identity or sample values in the OTT admin
  shell with authenticated account/platform data or remove them.

**Acceptance:** every visible route loads; unauthorized users are denied at both
the UI and API; no admin page silently presents mock data as real data.

### 2. Complete content catalog and media workflows

- Add or connect catalog management for movies, series, seasons, episodes, and
  live channels, including metadata, genres, artwork, publish state, and search.
- Connect catalog changes to the existing review/approve/publish and licensing
  workflows so editorial state and rights windows remain consistent.
- Verify media upload, chunk retry/resume, processing status, quality inspection,
  attachment to content, replacement, subtitle/audio track management, and
  archive behavior against the Go worker and storage configuration.
- Add safe confirmations and clear recovery actions for destructive or failed
  operations.

**Acceptance:** create/edit/review/publish a representative movie and episode;
upload and attach media; verify processing and failure/retry states; check
country/time licensing boundaries and permission enforcement.

### 3. Complete subscriptions and finance operations

- Verify plan and regional-price CRUD, archive behavior, coupon creation and
  redemption limits, subscriber lookup, and entitlement simulation.
- Verify payment filtering/details/export, invoice access, reconciliation,
  refunds, disputes, and failed provider events.
- Keep provider-backed actions gated by configured credentials and expose a clear
  unavailable state when a provider is not configured; never imply a test/local
  reconciliation is a real settlement.
- Add idempotency and audit coverage for financial mutations and test currency
  minor-unit handling.

**Acceptance:** exercise plan-to-entitlement and payment-to-refund/reconciliation
flows using test fixtures/provider sandbox data; unauthorized finance actions
fail at the API; exports match applied filters.

### 4. Complete customer support and marketing workflows

- Verify customer search and account 360 data across subscriptions, payments,
  devices, sessions, and playback diagnostics, including privacy/permission
  boundaries.
- Complete ticket assignment, status/priority updates, internal notes, escalation,
  and session revocation with confirmation and audit trails.
- Verify campaign draft, audience consent, approval, scheduling, pause, delivery,
  analytics, and banner lifecycle. Use server-side audience eligibility and
  consent as the source of truth.
- Replace prompt-based date/rejection inputs with accessible forms, validation,
  and timezone-aware controls.

**Acceptance:** support agents can resolve a seeded ticket and safely revoke a
  session; marketing staff can create a consent-safe campaign and schedule a
  banner; all lifecycle changes are audited and reflected after reload.

### 5. Finish analytics, operations, and access administration

- Validate analytics definitions and date-range boundaries against database
  queries; test every permission-scoped export and scheduled-report download.
- Verify operational health, sanitized events, queue/job retry allowlists,
  playback aggregates, and incident transitions. Clearly label checks that need
  external SMTP, CDN, DRM, or provider configuration.
- Finish RBAC regression coverage for role creation/edit/archive, staff invites,
  role assignment, account deactivation, session revocation, permission catalog,
  and immutable audit export.
- Make the overview dashboard aggregate real endpoints with consistent date,
  currency, and empty-data behavior.

**Acceptance:** report totals reconcile with focused backend queries; operations
  never exposes secrets, raw job payloads, or personal data beyond permissions;
  role changes take effect immediately and appear in audit history.

### 6. Release verification

- Run the full Laravel suite, admin lint/build, and browser end-to-end suite.
- Run a role matrix across Super Admin, Content Editor/Reviewer, Media Manager,
  Subscription Manager, Finance Manager, Marketing Manager, Customer Support,
  Analytics Manager, and Technical Operations Admin.
- Verify migration/seed behavior on a clean local database and preserve existing
  user/content data on an upgrade path.
- Record external prerequisites and distinguish locally verified behavior from
  sandbox-only or unconfigured provider integrations.

**Acceptance:** all critical journeys pass under both authorized and denied
  roles, and the release report states exactly which external integrations were
  exercised.

## Suggested execution order

Deliver these as six reviewable milestones in order. Milestones 2 and 3 are the
first product-critical vertical slices because content and entitlement/payment
flows are the platform's core operations. Do not mark a milestone complete from
lint or compilation alone; use persisted-data tests and browser journeys.
