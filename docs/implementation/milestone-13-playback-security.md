# Milestone 13: Playback Security and Entitlement Hardening

## Delivered in this increment

- Device records are bound to server-issued Sanctum token row IDs. Login, registration, profile selection, TV pairing, and logout use that binding instead of request IP addresses or client-supplied device IDs.
- TV pairing codes are consumed when a token is issued so polling cannot mint repeated device sessions.
- Playback validates account, token-bound device, selected account-owned profile, publication state, child restrictions, subscription plan and exact expiration time, and PPV rental window.
- Playback entitlement checks re-read account status from the database, and session creation and heartbeats recheck active/non-banned status while holding the account row lock. A suspended or banned account cannot start or refresh playback through an already-authenticated request context.
- Paid PPV playback now requires a fulfilled `verified_payments` receipt linked to the provider transaction. A legacy row with only `payment_status=paid` is not sufficient. Zero-cost purchases remain supported.
- Playback sessions support idempotent starts, account-level concurrency serialization, heartbeats, expiration, and owner/device-bound termination. The default active stream limit is one; an active plan may raise it through its existing simultaneous-stream limitation.
- M6 private HLS assets are served through short-lived signed URLs. Manifests are rewritten so child playlists and segments receive their own signed URLs. Each file request rechecks the live session, device, profile, media assignment, and current entitlement. Ending/revoking the session prevents subsequent requests from using copied links.
- The Next.js VOD player starts a session through the playback authorization endpoint, refreshes its manifest during playback, and ends the session on teardown.
- Device, profile, and concurrent-stream limits are now read through a shared parser that handles the nested Laravel resource snapshots stored on existing subscriptions. Missing limits fail to the one-device/one-profile/one-stream defaults.
- A configured maximum video height filters higher variants from the HLS master playlist and rejects direct requests for higher variant playlists or segments, so clients cannot bypass the quality restriction by skipping the master playlist.
- Added an additive `content_license_windows` table and permission-checked admin API/UI for UTC licensing windows on movies, series, episodes, and videos. Schedule changes are audited; episode playback must fit both its own window and its parent series window. Existing catalog items without a configured window retain their current availability.
- Playback denies content before a configured licensing start, at or after its end, and for invalid stored ranges. UTC-normalized `DATETIME` storage avoids database/server timezone shifts.
- Added a separate country allowlist per content item. It does not infer viewing rights from the catalog's country-of-origin metadata. Episodes must satisfy both their own and their parent series' allowlists. An item with no allowlist remains unrestricted by territory.
- The admin licensing endpoint and page now read/update ISO country allowlists alongside UTC dates. The additive `content_license_territories` migration preserves existing temporal policies and content data.
- Playback resolves the configurable edge country header only when the immediate socket peer matches an explicitly configured trusted proxy CIDR, then validates the code against active catalog countries. Missing, invalid, or untrusted geo data denies playback only for items that have a territory allowlist.
- Territory and time-window entitlement is rechecked at playback start, each heartbeat, and each signed private-media request. Signed media requests preserve the actual edge headers and socket peer for country resolution instead of constructing a synthetic request.

## Changed database and API

Additive migrations:

- `2026_11_24_000002_create_playback_sessions_table.php`
- `2026_11_24_000004_add_consumed_at_to_tv_login_sessions.php`
- `2026_11_24_000005_add_sanctum_binding_to_devices.php`
- `2026_11_24_000021_create_content_license_windows_table.php`
- `2026_11_24_000022_create_content_license_territories_table.php`

Milestone 7 adds plan limitation definitions and configuration. Until a maximum quality is configured on a subscriber plan, playback has no plan-specific quality ceiling.

Playback API additions: `POST /api/v3/playback/details`, `POST /api/v3/playback/{sessionId}/heartbeat`, and `POST /api/v3/playback/{sessionId}/end`. Admin licensing endpoints are `GET` and `PUT /api/admin/content/{contentType}/{contentId}/license-window`. Support staff with `support.sessions.revoke` can end one customer playback session at `POST /api/admin/support/customers/{customer}/playback-sessions/{session}/revoke`; the action is customer-scoped and audited. Private media requests use a short-lived signed route generated by the backend.

Migration 000005 associates legacy devices to Sanctum tokens by matching the existing token name to the previous device identifier. Unmatched legacy device rows cannot be securely attributed and require users to sign in again.

## Validation

- After the account-status recheck change and regression coverage for expired subscriptions, expired PPV rentals, and an already-issued media URL after a ban, `PlaybackEntitlementTest` and `PlaybackSessionTest` passed on the isolated `ott_m13_test` MySQL schema: **20 tests, 44 assertions** (one existing PHPUnit deprecation). The test schema was migrated from scratch; the active application database was not used.
- Focused playback-session and device-limit tests: **6 tests, 31 assertions passed** against an isolated MySQL test schema. This covers signed-manifest expiry while the playback session remains active and login rejection at the registered-device limit.
- Earlier focused playback, session, TV device-binding, license-window, and support-operations checks passed **30 tests, 113 assertions**. The earlier full-suite result was **117 tests, 528 assertions**; it was not rerun for this increment. One existing PHPUnit deprecation remains.
- Admin support UI lint passed, and the Next.js production build passed with the existing route-export warnings described in the project verification log.
- PHP syntax checks passed for all touched PHP files.
- No build or tests were run for the new territory policy slice in this increment, per the current instruction to focus on implementation only.
- Next.js TypeScript check and production build passed on Next.js 16.4.6.
- `git diff --check` passed; Git reports line-ending normalization warnings for two legacy CRLF files.

## Remaining before this milestone is complete

- Deployment must configure `PLAYBACK_GEO_TRUSTED_PROXY_CIDRS` with the actual edge CIDRs and ensure the edge strips any client-supplied country header before setting its own. No production edge CIDRs or per-title territory policies are configured in this workspace. Existing country-of-origin mappings remain descriptive metadata and are not treated as viewing rights.
- DRM license authorization, CDN/origin signing integration, and production-scale delivery have not been configured or verified. Current private HLS delivery streams files through Laravel and is suitable for local validation, not a production CDN capacity claim.
- The session concurrency gate serializes on the account row in the shared database, which provides cross-instance atomicity without requiring Redis. Multi-node/load validation remains outstanding.
- Device-limit enforcement is retained in the existing authentication flow, but cross-platform device registration/revocation journeys need broader end-to-end testing.
- Audit/diagnostic events cover playback start, customer-initiated end, license-window changes, and support-initiated session revocation. Customer end events are written once even when the client retries the idempotent end request.

The support-session revocation endpoint and UI are implemented and audited. Customer-initiated session-end events are added to playback diagnostics.

This document records a partial implementation, not production readiness or completion of Milestone 13 acceptance criteria.
