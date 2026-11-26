# Monorepo structure

```text
apps/
  api/       Laravel modular monolith and APIs
  web/       Next.js customer website
  admin/     Next.js administrative portal and BFF
  mobile/    Flutter mobile and TV client
  worker/    Go queue and media worker
compose.yaml                 Local development stack
compose.production.yaml      Production topology definition
docs/architecture/          Architecture, boundaries, migration records
scripts/                     Operational and development scripts
```

Application-specific lockfiles, migrations, tests, assets, and configuration
remain inside their owning application. Root-level `packages/` or additional
`infrastructure/` subtrees are introduced only when they contain real shared
functionality; the current Laravel APIs and deployment files already provide
the necessary cross-application boundaries.

Persistent Docker volumes and runtime service names are managed in the root
Compose definitions. Keep application-relative paths in scripts and container
build contexts aligned with the `apps/` layout. Do not remove or rename a
volume to make names look uniform.
