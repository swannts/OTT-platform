# Milestone 3: Content and operational access

## Delivered

Milestone 3 adds a Laravel-backed review workflow for the `entertainments` catalog and standalone `videos`, plus domain-permission-protected operational summaries for the separate admin application. The standalone-video editor creates and updates drafts only; approval and publishing remain separate review actions. It uses the existing Spatie permission registry and administrative audit log; customer APIs and Flutter are unchanged.

Content revisions move through `IN_REVIEW → APPROVED` or `REJECTED`, then `APPROVED → PUBLISHED`. Submitting captures a snapshot and hash of the catalog fields in review. A second pending submission is rejected, a reviewer cannot approve or reject their own submission, and a changed snapshot cannot be approved or published. Publishing is a distinct `content.publish` action and requires a title, type, and artwork; standalone videos also need a processed attached asset or a configured legacy playback source. Transitions are recorded in `admin_audit_logs` without storing the snapshot or media URL in the audit payload.

## API

All endpoints require Sanctum authentication, active `admin.access`, and the operation permission shown.

| Method | Path | Permission | Purpose |
|---|---|---|---|
| GET | `/api/admin/content/review-queue?status=in_review` | `content.review` | Paginated pending submissions |
| GET | `/api/admin/content/review-queue?status=approved` | `content.publish` | Approved items ready for publishing |
| POST | `/api/admin/content/{content}/submit` | `content.submit` | Submit a catalog revision |
| GET | `/api/admin/videos` | `content.view` | Search and filter paginated standalone videos and active plans |
| POST | `/api/admin/videos` | `content.create` | Create a standalone video draft |
| GET | `/api/admin/videos/{video}` | `content.view` | Read standalone video details |
| PATCH | `/api/admin/videos/{video}` | `content.update` | Update an unpublished standalone video draft |
| POST | `/api/admin/content/reviews/{submission}/approve` | `content.approve` | Approve a pending revision |
| POST | `/api/admin/content/reviews/{submission}/reject` | `content.reject` | Reject with a required note |
| POST | `/api/admin/content/reviews/{submission}/publish` | `content.publish` | Publish an approved revision |
| GET | `/api/admin/media/overview` | `media.view` | Counts for media and pending reviews |
| GET | `/api/admin/subscriptions/overview` | `subscriptions.view` | Plan and active-account counts |
| GET | `/api/admin/finance/overview` | `finance.payments.view` | Transaction record counts only |
| GET | `/api/admin/marketing/overview` | `marketing.campaigns.manage` | Banner and notification-template counts |
| GET | `/api/admin/analytics/overview` | `analytics.audience.view` | Aggregate customer and catalog counts |
| GET | `/api/admin/operations/overview` | `operations.health.view` | App/database health and queue counts |

## Admin UI

`/dashboard/videos` provides searchable and filterable standalone-video catalog management, a draft editor for bilingual metadata and access rules, and review submission. `/dashboard/content/review` lets editors submit revisions, reviewers approve or reject them, and publishers publish approved revisions for either catalog type. Navigation and direct route access use the current effective permission list. Every state-changing action is still enforced and validated by Laravel.

## Database

`admin_content_review_submissions` stores revision number, state, submitter/reviewer, a JSON snapshot, hash, optional reviewer note, and UTC timestamps. It is additive and does not change or delete catalog records. Rollback drops only this new table; take a database backup and stop the admin application before rolling back if it contains review history that must be retained.

## Boundaries and remaining work

The operational endpoints intentionally return aggregate counts and health only. They do not expose financial details, credentials, customer PII, raw logs, or DRM data. Standalone-video revisions are snapshot-protected, but the video editor currently manages metadata and access rules; source upload and attachment remain in the media library. Scheduling, automatic scheduled publication, rights validation, episode-level approval, and localized editorial review notes are not included. Do not treat this milestone as production readiness or as complete entitlement/authentication hardening; those remain in later milestones.

## Verification

Focused backend coverage is in `tests/Feature/AdminContentWorkflowTest.php`; it checks permission boundaries, self-review rejection, content-change detection, separate publication, and audit events. Run it with `php vendor/bin/phpunit --filter=AdminContentWorkflowTest` from `swann-laravel-web`.
