# Milestone 15: Media Asset Management

**Implementation state: source changes complete for this milestone; runtime
behavior is untested.** This record distinguishes implemented slices from
work deferred for a later milestone.

## Phase status

| Phase | Status | Scope |
| --- | --- | --- |
| 15.1 Lifecycle and security | IMPLEMENTED — NOT TESTED | Safe archive dependencies, track lifecycle, chunk checksums/cancellation, queue outcome reporting, new permission checks and preview path constraints. |
| 15.2 Library and overview | PARTIALLY IMPLEMENTED | Paginated grid/table, search, available filters/sorting, bulk archive, operational counts and storage estimate. Trends, resolution/provider filters and caching are deferred. |
| 15.3 Upload manager | IMPLEMENTED — NOT TESTED | Multi-file queue, bounded concurrency, per-file/aggregate progress, speed/ETA, pause/resume/cancel/retry and same-file session recovery. |
| 15.4 Details and preview | PARTIALLY IMPLEMENTED | Asset detail route, source metadata, expiring private HLS preview URL, track/content/version details. Admin player uses native HLS only; DASH/quality selection, playback controls beyond native browser controls and preview error telemetry are deferred. |
| 15.5 Processing | PARTIALLY IMPLEMENTED | Paginated queue, status refresh, progress/stage, cancellation, worker settings and source-aware output ladder. Priority controls, queue duration metrics and interrupted-job recovery are deferred. |
| 15.6 Tracks and artwork | PARTIALLY IMPLEMENTED | Track inventory/add/remove with role and accessibility metadata; no centralized artwork library was created. |
| 15.7 Quality control | PARTIALLY IMPLEMENTED | Technical readiness inspection and maker-checker manual decisions. Publication is not yet gated on QC approval. |
| 15.8 Versions | PARTIALLY IMPLEMENTED | Linked source versions, processing isolation, draft activation and rollback. Published-title approval/activation flow is deferred. |
| 15.9 Content mapping | PARTIALLY IMPLEMENTED | Searchable entertainment/episode/video selector and attachment dependency protection. Search by series/season/episode context and broader content roles are deferred. |
| 15.10 Storage and delivery | DEFERRED | Existing private local storage and customer playback authorization remain. Provider-neutral processing, direct S3/R2 upload and protected CDN delivery need additional architecture and runtime validation. |
| 15.11 Storage and archive | PARTIALLY IMPLEMENTED | Source metadata estimates, largest/unattached/incomplete-session inventory, soft archive and recovery. Reconciliation, retention, dry-run cleanup, provider bytes and permanent purge are deferred. |
| 15.12 Integration and docs | IMPLEMENTED — NOT TESTED | Admin/API/worker paths, staging fixture compatibility, migration and architecture guides were updated. |

## Change inventory

- New additive migration: `2026_11_27_000001_extend_media_asset_operations`.
- New admin routes: `GET media/storage`, `GET media/archived`,
  `GET media/content-options`, `GET media/assets/{asset}/preview`,
  `PATCH media/assets/{asset}`, `GET media/assets/{asset}/versions`,
  `POST media/assets/{asset}/versions/activate`,
  `POST media/assets/{asset}/versions/{version}/rollback`,
  `GET media/assets/{asset}/quality`,
  `POST media/assets/{asset}/quality/review`, `GET media/processing-jobs`,
  `POST media/processing-jobs/{job}/cancel`, and
  `POST media/assets/{asset}/restore`.
- New narrowly scoped permissions: `media.preview`,
  `media.quality.review`, `media.version.activate`,
  `media.version.rollback`, `media.archive.restore`,
  `media.processing.manage`, and `media.storage.view`.
- The worker reports stage/progress/worker, polls cancellation ownership, keeps
  ready renditions available during reprocessing, and reads validated H.264
  ladder/preset settings.
- Staging media fixtures now populate category, display name, QC state, and
  active-version metadata. No fixture generator was executed.
- Added docs: media asset management, media processing, media storage/delivery,
  and this guide/report.

## Compatibility and operational notes

Customer playback routes and response shapes were not intentionally changed.
The migration adds nullable episode/video links and metadata, and backfills
active flags only for assets already linked to existing content. Its rollback
does not drop operational columns. Apply it only through the normal migration
process after review; it was not run here.

The worker still requires the local `private` disk mounted at its configured
storage path. Although Laravel has S3-compatible filesystem adapters, this
media worker and upload/preview implementation do not support switching assets
to them. No new storage secrets or paid-service credentials are required for
the local implementation. `WORKER_TRANSCODE_HEIGHTS`,
`WORKER_TRANSCODE_BITRATES`, and `WORKER_H264_PRESET` are optional validated
worker settings with defaults documented in the processing architecture guide.

No media transcode was run. No migration, build, automated test, lint,
benchmark, browser test, Docker rebuild, staging generation, or production
deployment was run. Graphify's checked-in graph snapshot predates the current
`apps/` layout; it was used only for navigation, and findings were checked
against current source.

## Deferred verification checklist

- [ ] Review migration SQL/rollback behavior on supported MySQL versions and confirm existing-content backfill.
- [ ] Exercise media API pagination, filters, metadata update, permissions, and audit records.
- [ ] Exercise chunk validation, interrupted resume, ownership, expiry, duplicate detection, cancellation, and queue-unavailable responses.
- [ ] Run worker integration against the actual MySQL schema and RabbitMQ event contract; test claim leases, cancellation, retries, failure cleanup, and output activation.
- [ ] Verify HLS/DASH outputs, source-aware resolution ladders, aspect ratio, audio/subtitle tracks, and preview URL expiry in browsers.
- [ ] Verify manual QC maker-checker, technical checks, and explicitly determine whether publication should require QC approval.
- [ ] Verify replacement failure preserves prior active playback; test draft activation/rollback and published-content blocking.
- [ ] Verify content search/attachment for entertainment, episode, and video records and archive dependency protection/restore.
- [ ] Check staging data relationships and generated files without confusing synthetic records with production media.
- [ ] Verify current private-local storage behavior and customer playback compatibility.
- [ ] Design and separately implement provider-neutral storage, storage reconciliation, CDN delivery, centralized artwork, rendition analytics, and published replacement approval before those capabilities are claimed.
- [ ] Run the required admin/API/worker build and test suites in a separately authorized verification session.
