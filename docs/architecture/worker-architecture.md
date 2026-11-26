# Go worker architecture

The Go worker is isolated in `apps/worker`. Its executable entry point is
`cmd/worker/main.go`; implementation details are under `internal/`, so they
cannot become accidental public Go packages. Runtime configuration, RabbitMQ
transport, event types, handlers, health checks, and supporting media services
have separate packages.

The worker's module path remains `ott-worker`. This local module identity is
already used by the application and does not need a cosmetic change. RabbitMQ
exchange names, queue names, and routing keys in `internal/broker/rabbitmq.go`
are message contracts and remain unchanged. The worker retains its existing
retry behavior, dead-letter exchange, FFmpeg processing, database integration,
and Docker build files.

## Verification status

Source inspection confirms the expected `cmd/worker` and `internal` layout.
The Go toolchain is not installed in the current environment, so `gofmt`,
`go vet ./...`, and `go test ./...` could not be run for this migration. No
message, queue, database, or module-path changes were made.
