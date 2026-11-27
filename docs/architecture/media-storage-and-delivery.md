# Media storage and delivery

## Current behavior

Admin uploads are resumable Laravel-owned chunks on the `private` disk. The
API verifies per-chunk SHA-256 values, assembles the source through a streamed
temporary file, calculates the final checksum server-side, validates the
completed file, and then creates the asset and job. Session IDs are owner
scoped. Cancellation removes temporary chunks; expired sessions remain subject
to the existing cleanup command.

Source and rendition paths remain private. The worker currently requires the
local private disk mounted into its container. The Laravel filesystem
configuration has other adapters, including S3-compatible and Bunny, but the
admin upload, preview, and Go processing paths are not provider-neutral. Do not
switch the media disk to a remote provider expecting this feature to work.

Admin preview links are short-lived signed URLs for a single asset and
normalized rendition path. Customer playback continues through the existing
playback session and entitlement checks in `PlaybackMediaController`; the
media manager does not alter that API or direct customer delivery behavior.

The storage page reports source-size estimates from asset metadata, grouped by
category/provider, plus largest assets, unattached asset count, and active
upload sessions. These figures are not provider-reported usage and do not
include rendition, track, artwork, or temporary bytes unless those bytes are
represented by a source asset size.

## Deferred architecture

Cloudflare R2/S3-compatible direct multipart uploads, provider-neutral worker
storage, provider reconciliation, rendition/track byte accounting, retention
policies, dry-run orphan cleanup, CDN token delivery, cache isolation,
revocation, and signing-key rotation are not implemented. No production bucket,
CDN, Bunny zone, DRM provider, or paid service was configured. Existing
customer authorization remains in Laravel; signed URLs and encrypted HLS are
not commercial DRM.

Keep private-origin access closed when adding a future CDN. A follow-up should
define object ownership and checksum verification, worker staging semantics,
short-lived session token renewal, cache keys, revocation, and signing-key
rotation before enabling direct delivery.
