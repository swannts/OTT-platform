# Media Manager guide

The Media Manager is available at `/dashboard/media` in the admin portal.
Visible sections are based on the signed-in user's effective permissions.
Asset details are available at `/dashboard/media/assets/{id}`.

## Sections

- **Overview:** Laravel-derived asset/job counts, source-size estimate by
  category, and recent processing failures.
- **Media library:** server pagination, table/grid display, title/filename and
  entertainment search, status/category/QC filters, language, date range,
  attachment state, sort order, selection, safe archive and retry actions.
- **Upload manager:** multiple files, up to three concurrent transfers,
  chunk-level checksums, session recovery when the same file is selected,
  pause/resume/cancel/retry, and queue-unavailable/duplicate states. Upload
  tasks remain mounted while switching manager tabs. Navigation away from the
  page requires selecting the same file again to resume its locally remembered
  session.
- **Processing queue:** paginated job state and progress; authorized cancellation
  of queued/processing work.
- **Quality control:** technical readiness checks and maker-checker decisions
  with required review notes.
- **Audio and subtitles:** inventory of assets with tracks and a link to their
  detail page.
- **Storage:** approximate source inventory only; see
  [storage and delivery](../architecture/media-storage-and-delivery.md).
- **Archive:** retained, soft-deleted records with search and permission-scoped
  restore.

## Uploads and replacement versions

Choose the category before starting an upload. The browser hashes each chunk
independently, while Laravel independently verifies chunk hashes and the
assembled source checksum. Upload completion and queue publication are
separate outcomes. An exact duplicate is reported and is not inserted again.

From an asset detail page, a replacement upload creates a linked source
version. A ready source remains available while the new one is processed.
Version activation requires technical readiness and approved QC. Existing
published content references cannot be switched through this page; use the
content review/publication process.

## Preview and safety

Preview URLs expire quickly and serve private processed HLS outputs only. The
current admin player relies on native browser HLS support. Source files,
storage credentials, and permanent private URLs are not exposed. Restore does
not publish content. Archive checks published content references and retains
the underlying files.

## Permissions

The API independently enforces `media.view`, `media.upload`, `media.update`,
`media.delete`, `media.transcode.retry`, `media.preview`,
`media.quality.review`, `media.version.activate`,
`media.version.rollback`, `media.archive.restore`,
`media.processing.manage`, `media.storage.view`,
`media.audio.manage`, `media.subtitles.manage`, and existing content view/update
permissions where applicable. Hiding a control in the UI is not the security
boundary.

## Limitations

The overview does not show upload/processing trend graphs or reliable rendition,
track, and artwork storage totals. Resolution/provider filters, rendition
inventory, activity history, artwork library, remote storage uploads, CDN
delivery, permanent purge, and published-title replacement approval are not
part of this implementation. No migration or runtime check was run; complete
the deferred checklist in the milestone implementation report before relying
on this workflow operationally.
