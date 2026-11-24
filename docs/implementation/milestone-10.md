# Milestone 10 — Customer Support Operations

## Delivered

- Added permission-gated customer search and Customer 360° data with explicit customer projections. Subscription details appear only when the staff member also has `subscriptions.view`.
- Added ticket inbox and lifecycle operations: create, assign to an authorized support agent, update status/priority, add internal notes, escalate to Finance or Technical Operations, and resolve/close tickets.
- Added support dashboard counts plus median first response and resolution durations. First response is measured from ticket creation to the first internal staff note; resolution is measured from creation to `resolved_at`.
- Added read-only subscription and payment investigation. Support payment references are masked, and the support routes cannot request or execute refunds.
- Added registered device and playback session inspection. Authorized session revocation ends matching playback sessions, removes the Sanctum token, preserves the device record, and records an audit event.
- Added standardized playback diagnostics for entitlement denial, media readiness, successful session starts, and client-reported player errors. Client reports must match the authenticated customer’s active session and use an allowlisted error code; free-form messages, URLs, and credentials are not stored.
- Added audit events for ticket changes and sensitive customer, payment, device, playback, and ticket-detail views. Customer search audit records contain a query hash and result count, not the searched email or name.
- Added the admin workspace at `/dashboard/support` with ticket inbox/detail, customer search, subscription/payment summaries, device/session inspection, playback diagnostics, assignment, escalation, and session revocation controls.
- The Next.js customer player now reports deduplicated, standardized network, decoder, manifest, or unknown playback errors against its active playback session. Provider messages, raw URLs, and free-form error detail are not sent.
- Added the narrow `support.payments.view` permission to the Customer Support role. Kept the Finance Manager refund review workflow and Media Manager DRM workflow permissions aligned with the OTT role specification; the RBAC policy assertions now match those role capabilities.

## Data and API

Migration `2026_11_24_000016_create_support_operations_tables` creates `support_tickets`, `support_ticket_notes`, and `playback_diagnostic_events`. Rollback refuses to delete these tables once records exist.

Admin endpoints are under `/api/admin/support`; each uses `admin.access` plus the applicable `support.*` permission. Customer playback errors are submitted to `POST /api/v3/playback/diagnostics` with `session_id` and one of `network_error`, `decoder_error`, `manifest_error`, `license_error`, or `unknown_error`.

## Validation

- Migration applied to the local Docker database and RBAC foundation seeder rerun.
- Focused Laravel tests cover ticket workflow/audit, authorization, sensitive field projections, masked payments, refund denial, device/token revocation, and session-bound client diagnostics.
- Admin UI production build includes `/dashboard/support`.

The API accepts only standardized client error codes and checks that each report belongs to the caller's active playback session. Sensitive account recovery remains outside this milestone; no recovery or impersonation action is exposed.
