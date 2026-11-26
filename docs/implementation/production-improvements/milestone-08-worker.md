# Milestone 08 Bound per-worker media concurrency

## Existing implementation and gaps

Media consumer concurrency was fixed at four. The broker ignored prefetch and dead-letter binding errors. Existing transcode handlers use database leases and FFmpeg deadlines.

## Changes

Set `WORKER_MEDIA_CONCURRENCY` to an integer from 1 to 4. The default remains 4. Consumer startup and topology setup now surface RabbitMQ errors and close the failed consumer channel.

## Files, database, API, and compatibility

`apps/worker/cmd/worker/main.go`, `apps/worker/internal/config/{config.go,config_test.go}`, `apps/worker/internal/broker/rabbitmq.go`, `compose.yaml`, `compose.production.yaml`. Existing queue names, prefetch factor, message contracts and persisted leases are preserved.

## Verification and measurements

Changed Go files formatted and parsed using WebAssembly gofmt. Both YAML files parsed and contain the worker setting. Compose-specific override tags were not interpreted. Go compiler, Go vet/race tests, Docker Compose validation, and broker integration were unavailable.

## Remaining work

Run the Go suite and isolated RabbitMQ tests. Verify clean cancellation, reconnect, stuck consumers, lease recovery, DLQ handling, and CPU and memory under concurrent transcodes.

## Rollback

Unset the variable to restore the default of 4, or roll back the worker and compose files together.

## Commit references

`59f4652`
