# Media asset management

The media manager is owned by `apps/admin/src/features/media`; Laravel owns
the media registry and all lifecycle authorization. The customer web and
mobile playback contracts remain unchanged.

## Lifecycle and records

`media_assets` represents an uploaded source and its current processed output.
`replaces_asset_id` links replacement sources into a version chain;
`is_active_version` records the selected source version. Processing attempts
are stored in `media_processing_jobs`, tracks in `media_asset_tracks`, and
resumable upload ownership and chunk checksums in `media_upload_sessions`.
Entertainment, episode, and video records can reference an asset through
`media_asset_id`. The migration backfills active-version state for records
already referenced by content.

An upload is assembled and checksummed by Laravel before an asset and job are
created. Queue publication failure is reported distinctly. A ready asset keeps
its current rendition available while track changes or retries are processed.
New renditions use job-specific paths; failed or cancelled work cannot replace
the active rendition. Manual QC status is separate from technical readiness.

## Admin API surface

All routes below are under the existing authenticated `/api/admin` boundary.
Authorization is checked on the server.

| Route | Purpose | Permission |
| --- | --- | --- |
| `GET media/overview`, `GET media/assets` | Operational metrics and filtered, paginated library | `media.view` |
| `GET media/assets/{asset}`, `PATCH media/assets/{asset}` | Details and editable catalog metadata | `media.view`, `media.update` |
| `GET media/assets/{asset}/preview` | Issue short-lived private preview links | `media.preview` |
| `GET media/assets/{asset}/versions` | Version chain | `media.view` |
| `POST media/assets/{asset}/versions/activate` | Activate eligible source for draft content | `media.version.activate` |
| `POST media/assets/{asset}/versions/{version}/rollback` | Select a previous eligible source | `media.version.rollback` |
| `GET media/assets/{asset}/quality`, `POST .../quality/review` | Technical checks and maker-checker review | `media.view`, `media.quality.review` |
| `GET media/processing-jobs`, `POST media/processing-jobs/{job}/cancel` | Paginated job visibility and cancellation | `media.view`, `media.processing.manage` |
| `GET media/content-options`, `PUT media/assets/{asset}/content` | Search and attach supported draft content | `content.view`, `media.update` + `content.update` |
| `GET media/storage`, `GET media/archived`, `POST media/assets/{asset}/restore` | Storage estimates and archive recovery | `media.storage.view`, `media.view`, `media.archive.restore` |
| `POST/PUT/DELETE media/uploads...` | Owner-scoped resumable upload lifecycle | `media.upload` |
| `POST media/assets/{asset}/retry`, `DELETE media/assets/{asset}` | Retry and safe archive | `media.transcode.retry`, `media.delete` |
| `POST/DELETE media/assets/{asset}/tracks...` | Track changes, checked per track type | `media.audio.manage`, `media.subtitles.manage` |

Preview paths use an expiring signed capability bound to the asset and a
normalized rendition path. They do not expose source paths or permanent URLs.
Archive is soft-delete only, retains source/output files, and refuses assets
referenced by published content. Restore checks that retained source and
required rendition files still exist and never republishes a title.

## Permissions and audit

The existing Media Manager role receives the narrowly scoped preview, QC,
version, archive-restore, processing-management, and storage permissions.
Technical Operations receives read-only media visibility plus queue/storage
operations. Other administrative roles do not receive these permissions by
default. Sensitive metadata, QC, version, archive, upload, track, retry, and
processing mutations use the existing admin audit logger.

## Known workflow boundaries

- Manual QC decisions are recorded, but the existing customer publication
  workflow does not yet require `quality_status=approved`; do not infer that
  QC is a publication gate.
- Activation and rollback are restricted to draft content. Replacement of a
  published title requires an integrated content-review approval path that is
  not implemented here.
- The library supports table/grid, pagination, search, status/category/QC,
  language, upload date, attachment and sorting filters. Resolution, provider,
  and broad artwork-specific filtering are not available.
- The per-asset page shows source metadata, current processing state, tracks,
  attachment references, and source versions. Detailed rendition inventory,
  rendition byte accounting, review history, and audit activity are not yet
  exposed there.
- Artwork remains in existing content/file-manager flows. There is no separate
  centralized artwork library in this milestone.

## Schema migration

`2026_11_27_000001_extend_media_asset_operations` adds optional catalog/QC/
archive/version fields, job progress fields, upload checksums, track metadata,
and nullable episode/video asset links. It is additive and backfills the
display name and active marker for already-linked assets. Its `down()` is
intentionally non-destructive and retains operational state, so rolling back
the migration does not remove these columns. No migration was executed during
implementation.
