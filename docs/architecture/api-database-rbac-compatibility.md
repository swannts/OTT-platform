# API, database, and RBAC compatibility

This migration does not rename existing API routes, response fields, database
tables or columns, role names, permission identifiers, queued class names,
payment callbacks, or playback contracts. New conventions apply to additive
work only; a breaking contract change needs a separately versioned migration
and client rollout plan.

## API boundaries

Laravel routes currently include legacy endpoints and versioned `/v3` routes.
Some `/v3` routes are public and throttled; authenticated routes use Sanctum
and API-device checks. Playback authorization, subscription/payment flows,
webhooks, Flutter clients, and admin BFF calls depend on existing paths and
response shapes. Swagger/OpenAPI documentation should describe the current
routes accurately rather than imply that recommended future URLs are already
implemented.

## Database boundaries

Existing migrations are treated as deployed history. Recent additive
operations tables include `admin_audit_logs`, `playback_sessions`,
`payment_provider_events`, `payment_disputes`, `payment_reconciliation_runs`,
`support_tickets`, `support_ticket_notes`, and `playback_diagnostic_events`.
This list is illustrative, not a complete schema inventory. Never rename
applied tables or columns for cosmetic consistency. Use additive migrations
with a compatibility and rollback plan when a data change is required.

The `swann` database name and persistent Docker volume names remain unchanged
for existing local data. They must only be renamed through an explicit data
migration and restore plan.

## Canonical administrative RBAC

`apps/api/app/Support/Rbac/RbacRegistry.php` is the source of truth for the
12 fixed administrative roles and recognized permission catalog:

| Canonical role identifier | Display name |
| --- | --- |
| `super-admin` | Super Admin |
| `platform_admin` | Platform Admin |
| `content_manager` | Content Manager |
| `content_editor` | Content Editor |
| `content_reviewer` | Content Reviewer |
| `media_manager` | Media Manager |
| `subscription_manager` | Subscription Manager |
| `finance_manager` | Finance Manager |
| `marketing_manager` | Marketing Manager |
| `customer_support` | Customer Support |
| `analytics_manager` | Analytics Manager |
| `technical_operations_admin` | Technical Operations Admin |

Permissions use dot notation, for example `content.review`,
`media.transcode.retry`, `finance.refunds.approve`, and `operations.jobs.view`.
The existing canonical identifiers stay as defined; in particular,
`super-admin` is not changed to `super_admin`. Super Admin intentionally does
not receive `finance.refunds.approve` by default; the explicit assignment and
maker-checker control remain. Guest, registered user, subscriber, and child
profile are customer access states/types, not administrative roles.

`RbacFoundationSeeder` maintains the fixed registry. A source-level catalog
does not prove current database assignments; verify live data separately in
an authorized database before making operational claims.
