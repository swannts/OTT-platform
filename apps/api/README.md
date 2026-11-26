# Swann Laravel Backend

Laravel web application and API for the Swann OTT platform.

## Setup

See [MANUAL_SETUP.md](MANUAL_SETUP.md) for local setup and seed data instructions. Copy `.env.example` to `.env`, configure the database and external service credentials, then follow the Laravel setup steps.

The Flutter client lives in the sibling [`../mobile/`](../mobile/README.md) directory. The repository overview is in [`../../README.md`](../../README.md).

## External services

Configure provider-issued credentials for Firebase, payments, email, and media delivery before enabling those integrations. Do not commit private credentials.

## API documentation

In the local environment, open Swagger UI at [`http://localhost:8000/docs/swagger`](http://localhost:8000/docs/swagger). The OpenAPI 3.1 specification is available at [`http://localhost:8000/docs/api.json`](http://localhost:8000/docs/api.json) and is generated from Laravel's `/api` routes. Sanctum-protected endpoints are documented with bearer authentication. Documentation routes are restricted to local environments unless the application defines the `viewApiDocs` authorization gate.
