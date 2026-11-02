# Stage 1: Build the Go binary
FROM golang:1.22-alpine AS builder

WORKDIR /app

# Copy dependency definitions and sources
COPY go.mod ./
COPY . .

# Fetch dependencies & generate go.sum
RUN go mod tidy

# Build statically linked binary
RUN CGO_ENABLED=0 GOOS=linux go build -ldflags="-s -w" -o /worker ./cmd/worker

# Stage 2: Minimal runtime image
FROM alpine:3.19

WORKDIR /app

COPY --from=builder /worker /app/worker

ENTRYPOINT ["/app/worker"]
