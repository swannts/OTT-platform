# Frontend application architecture

## Customer website (`apps/web`)

The customer website uses Next.js App Router routes for navigation, route
metadata, and server-side composition. Domain functionality lives in
`src/features/` (account, blog, home, live TV, movies, notifications, player,
pricing, TV shows, and videos). Shared presentation is in `src/components/`.

Initial server data is fetched through `src/server/dal/` and the shared HTTP
clients in `src/lib/http/api-client.ts`. Browser requests use the existing
Axios clients and same-origin backend/session routes. Redux Toolkit is already
configured through a store factory in `src/store/`, typed async thunks are
created in `createAppAsyncThunk.ts`, and feature slices hold interactive client
state. Authentication uses the existing HttpOnly `noor_session` cookie
boundary; the Redux store does not become a credential store.

The app retains the `@/*` TypeScript alias. Server and client providers stay
separate, and route pages remain thin compositions of feature components.

## Administrative portal (`apps/admin`)

The admin portal keeps Next.js routes in `src/app/`, reusable UI and layouts in
the existing Minimals directories, and OTT business areas in
`src/features/`: analytics, finance, marketing, media, operations, RBAC,
subscriptions, and support. Template demonstrations remain in `src/sections/`
and are not moved into OTT features. This keeps vendor-template structure
recognizable while business-specific code stays feature-owned.

The administrative API BFF is under `src/app/api/admin/`; it keeps the
Sanctum credential server-side in an HttpOnly cookie. Feature API modules call
the existing Axios configuration, and RBAC permission checks continue to
control both navigation visibility and feature actions. The existing `src/*`
import convention from `jsconfig.json` remains stable.

## Shared-code boundary

These are independently deployed applications with different visual systems
and client/server boundaries. There is no existing shared runtime package
that merits extraction. Do not create `packages/shared-types` or
`packages/design-tokens` until a concrete contract or token set has multiple
consumers and a clear ownership/versioning policy. Laravel API responses
remain the cross-application integration contract; changing or generating
those contracts is a separate, backward-compatible API task.

## Verification

The M6 source review preserves the existing Redux/Axios client flow, DAL,
admin BFF, cookie authentication, protected routes, permission navigation,
and UI structure. Validation results for this migration are recorded in
`repository-migration-plan.md`; browser tests remain app-owned under
`apps/web/cypress/` and `apps/web/e2e/`, and admin BFF tests remain under
`apps/admin/tests/`.
