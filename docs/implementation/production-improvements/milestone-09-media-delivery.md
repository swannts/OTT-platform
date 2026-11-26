# Milestone 09 Separate playback authorization from high-bandwidth delivery

## Existing implementation and gaps

Laravel signs media URLs for 110 seconds and rechecks device, profile, title, entitlement, quality, and session state on each file request. Manifests and segments are served from a private local disk with private/no-store response headers.

## Changes

Provider and CDN changes are pending. The delivery design below defines the verification gates for an authorized staging environment.

## Files, database, API, and compatibility

No media transport, storage, database, or API contract changes. The playback controller extraction affects the authorization entry points only.

## Verification and measurements

Inspected signer and file controller. Private origin, CDN revocation, provider tokens, and DRM were not exercised.

## Remaining work

Inventory active providers and establish isolated media and CDN accounts. Verify private origin isolation, entitlement expiry, revocation, quality and geographic restrictions, and signed read access before release.

## Rollback

Keep the current signed Laravel delivery path available as a rollback option. Shift traffic back to that path using the pre-approved staging mechanism after provider integration exists.

## Commit references

`a0a9704`

## Delivery contract for staging

1. Keep original uploads and finished rendition sets in separately scoped private storage. Publish the rendition version only after the active worker lease commits successfully.
2. Laravel should continue to bind the device, profile, content, asset version, entitlement, and maximum quality before authorizing delivery. Use a provider mode that authorizes requests for the manifest and for every referenced object, including audio and subtitle tracks. Separate cache keys for different rendition versions and filter manifest variants before the player receives them.
3. Require origin access exclusively from the configured CDN or private service identity. CDN authorization must run even on cache hits. Verify anonymous origin and edge access in the authorized staging environment.
4. Measure maximum revocation delay as the lesser of its enforced token TTL and edge invalidation delay if both controls apply. If only expiry ends an edge token, the worst-case delay is the token TTL plus permitted clock skew. Test access at the boundaries after device revocation, expiry, and content replacement. The existing 110-second Laravel signature belongs to application-side delivery and is not an edge revocation SLA.
5. Treat signed URLs, encrypted HLS, and DRM license enforcement as distinct mechanisms. Require provider licensing and device compatibility tests before asserting DRM support.
