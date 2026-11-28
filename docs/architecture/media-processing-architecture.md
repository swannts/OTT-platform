# Media processing architecture

Laravel persists each processing request in `media_processing_jobs` and
publishes the existing `media.transcode.requested` RabbitMQ event. The Go
worker consumes that contract; event names, routing keys, retry/DLX behavior,
and module identity remain unchanged.

## Job lifecycle

Jobs record `queued`, `processing`, `completed`, `failed`, or `cancelled`
status, attempts, maximum attempts, an ownership token, worker name, stage,
progress, timestamps, and sanitized failure information. A worker claims a
queued job or a stale processing lease with a conditional update. Heartbeats
and completion updates require the same processing token, preventing a stale
worker from overwriting a newer attempt. Cancellation changes job state in the
API; the worker observes loss of its lease and cancels FFmpeg work. Outputs
remain job-specific until processing completes.

The worker probes source media, builds source-aware HLS variants and DASH,
includes external audio/subtitle tracks, and generates a poster. It never
upscales above the source height. The configured default rendition heights are
360p, 720p, and 1080p; the bitrate map can include other heights, but those
heights are not enabled unless selected in the configured ladder. Codec output
is H.264/AAC. Reported progress is a stage estimate, not FFmpeg frame-level
completion.

## Configuration

- `WORKER_TRANSCODE_HEIGHTS`: comma-separated unique supported heights; default
  `360,720,1080`.
- `WORKER_TRANSCODE_BITRATES`: comma-separated `height=kbps` entries. Defaults
  include profiles through 2160p, but only selected ladder heights are used.
- `WORKER_H264_PRESET`: validated H.264 encoder preset; default `veryfast`.

The worker currently reads private local files from its mounted storage path.
It rejects asset disks other than `private`; remote object storage is not yet
supported by this worker.

## Operational limits

The admin queue page is paginated, filters by status, shows stage/progress,
worker, attempts, and sanitized failure detail, and polls at a 10-second
interval only while visible active work exists. Authorized users can cancel
active jobs. Retry remains exposed from the asset library for eligible failed
jobs. Priority adjustment, recovery tooling for arbitrary interrupted jobs,
date/type filters, queue wait and execution durations are not implemented.

No video transcoding, Go tests, vet, builds, or runtime worker checks were run
for this implementation. Validate worker/database schema compatibility and
HLS/DASH output in the deferred verification session.
