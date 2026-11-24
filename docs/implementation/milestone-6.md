# Milestone 6: Media management and processing

## Delivered

- Added private media asset, version, upload-session, processing-job, and subtitle/audio-track records. Asset files are stored on Laravel's private local disk and shared only between the backend and Go worker containers.
- Added owner-bound 8 MiB resumable upload chunks, a 10 GiB declared upload ceiling, upload-profile MIME/extension validation, SHA-256 verification, ffprobe metadata inspection, and cleanup of expired incomplete uploads.
- Added permission-checked media library, upload, asset detail, QC, retry, track, archive, attach-to-content, DRM-configuration status, and storage/processing metric APIs. Source paths and hashes are excluded from API payloads.
- Added an admin Media page with resumable upload recovery, processing status, asset QC, track management, retry, archival confirmation, and publication attachment controls. Binary request bodies are preserved by the admin proxy.
- Extended the Go worker to package HLS and DASH, create a poster, transcode audio/subtitle tracks, classify failures, use private path validation, and claim jobs with an ownership token plus a heartbeat lease. A stale worker cannot publish over a newer attempt.
- Content publication now blocks on a linked asset until the source, probe metadata, HLS/DASH manifests, poster, and tracks pass readiness checks.

## Authorization and routes

All media APIs are under the authenticated admin API group. They enforce existing `media.view`, `media.upload`, `media.update`, `media.delete`, `media.transcode.retry`, and `media.drm.configure` permissions; track operations use their specific subtitle/audio permissions. `Content Manager` receives only the asset-view/update permissions needed to attach media to catalog content. The Media Manager UI is hidden when permissions are absent.

Endpoints are under `/api/admin/`: `GET media/overview`, `GET media/assets`, `GET media/assets/{asset}`, `GET media/assets/{asset}/quality`, `POST media/uploads`, `PUT media/uploads/{session}/chunks/{index}`, `POST media/uploads/{session}/complete`, `POST media/assets/{asset}/retry`, `DELETE media/assets/{asset}`, `POST|DELETE media/assets/{asset}/tracks`, `GET media/drm`, and `PUT media/assets/{asset}/content`.

## Migration and configuration

Apply migrations with `php artisan migrate --force`. The media tables are additive. `2026_11_24_000003_add_media_asset_link_to_entertainments.php` adds the optional content foreign key after the catalog table exists. Rollbacks refuse to remove media records or content links; restore compatible application code and retain these schema objects once data exists.

The backend uses `storage/app/private`. Production Compose mounts this directory into both backend and worker; keep it on private persistent storage with restrictive filesystem access and backups. The worker needs FFmpeg/ffprobe and `PRIVATE_STORAGE_PATH=/var/www/html/storage/app/private`. PHP upload settings are raised only enough to accept the bounded 8 MiB multipart chunks. DRM API output reports provider/configuration presence only; this milestone does not provision a license provider or expose secrets.

## Verification

- Laravel media and content workflow suites: 13 tests / 79 assertions passed.
- Go worker suite: `go test -mod=readonly ./...` passed.
- Admin UI production build completed and includes `/dashboard/media`; Next.js emitted existing warnings about dynamic fields on unrelated routes.
- Docker worker image built. Backend image build was attempted but blocked while Composer downloaded `psr/http-message` from GitHub (`curl error 35`, TLS connection ended unexpectedly); application tests ran in the already running backend container.
- `git diff --check` passed apart from line-ending normalization notices on two legacy PHP files.

## Known limits

- Storage is local private disk. A production object store/CDN migration and load testing are not included.
- The DRM panel only reports configuration presence; no license issuance, key rotation, or provider verification exists.
- HLS/DASH output and local private HLS delivery are implemented, but provider-side CDN signing and DRM need deployment credentials and provider configuration.
- No real media source was uploaded and transcoded through the production container topology during this check; worker unit tests and image build are not an end-to-end production transcoding sign-off.
