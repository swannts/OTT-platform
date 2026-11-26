# Production improvement progress

**Baseline** `e2b4f71ad7f3abca818c2972b67856129a4084c1`. See the [plan](production-improvement-plan.md) for initial source assessment and verification gates.

| Milestone | Status | Implementation | Verification | Commit | Blockers |
| --- | --- | --- | --- | --- | --- |
| M1 Backend | In progress | Playback extraction selected | Source reviewed | | PHP and Composer |
| M2 API | In progress | Route and middleware structure reviewed | Source reviewed | | PHP and Composer |
| M3 Database and cache | In progress | Versioned cache invalidation reviewed | Source reviewed | | Isolated MySQL and Redis |
| M4 Admin | In progress | Prior admin functional plan reviewed | Source reviewed | | Browser-to-API stack |
| M5 Customer web | In progress | Partial-failure homepage cache identified | Source reviewed | | Next.js dependencies and API stack |
| M6 Localization | In progress | Blog regression and latest bilingual changes identified | Source reviewed | | PHP and full browser stack |
| M7 Branding | In progress | Existing Settings ownership confirmed | Source reviewed | | Full stack |
| M8 Worker | In progress | Hardcoded consumer limits and shutdown reviewed | Source reviewed | | Go compiler and RabbitMQ |
| M9 Media delivery | In progress | Signed delivery and existing provider gaps reviewed | Source reviewed | | Private CDN/origin and DRM credentials |
| M10 Security | In progress | Existing webhook, RBAC and BFF controls identified | Source reviewed | | Isolated app stack and provider sandboxes |
| M11 Observability | In progress | Existing operations and health endpoints identified | Source reviewed | | Running services and alert destination |
| M12 Testing | In progress | Existing test trees and reported validation inspected | Source reviewed | | Runtime stack and load tool |
| M13 Recovery/staging | In progress | Two backup paths reviewed, host backup publish race found | Source reviewed | | Docker, age, staging and backup destination |
