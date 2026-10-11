# Swann Laravel Backend

Laravel web application and API for the Swann OTT platform.

## Setup

See [MANUAL_SETUP.md](MANUAL_SETUP.md) for local setup and seed data instructions. Copy `.env.example` to `.env`, configure the database and external service credentials, then follow the Laravel setup steps.

The Flutter client lives in the sibling [`../flutter-app/`](../flutter-app/README.md) directory. The repository overview is in [`../README.md`](../README.md).

## External services

Configure provider-issued credentials for Firebase, payments, email, and media delivery before enabling those integrations. Do not commit private credentials.
