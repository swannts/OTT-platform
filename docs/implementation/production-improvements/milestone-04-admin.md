# Milestone 04 Verify the existing administration workflows

## Existing implementation and gaps

Next.js pages and Laravel routes cover content review and licensing, media, subscriptions, finance, marketing, support, analytics, operations, and access management. See the functional inventory below.

## Changes

Keyed the existing campaign and banner forms by the selected record. Cancelling a campaign edit discards unsaved values. Selecting another banner replaces modified fields before Save can update the selected banner.

## Files, database, API, and compatibility

`apps/admin/src/features/marketing/MarketingOperationsPage.jsx`. No new API, permission, or database changes.

## Verification and measurements

Focused ESLint exited successfully. A Chromium fixture using the real React/MUI component reproduced stale form values on the baseline. The branch reset the campaign form on cancel, loaded the second banner's values after editing the first, and sent the second banner ID and title on save. The API and authentication were test doubles.

## Remaining work

Verify representative workflows against Laravel with permissioned staff, persist and reload changes, and audit the legacy content editor for season and episode CRUD, localized metadata, and publication actions. Inspect each remaining button and form for pending, error, and empty-state behavior.

## Rollback

Revert the admin marketing commit and rebuild the admin image. No database rollback is needed.

## Commit references

`c719df8`

## Representative functional inventory

The paths below are relative to `/api/admin`. Each requires staff access in addition to the stated permission or controller policy. This inventory covers reviewed actions. The [existing admin checklist](../admin-ui-functional-plan.md) tracks the larger UI scope.

| Screen | Action | API endpoint | Permission | Source status | Existing feature coverage | Remaining check |
| --- | --- | --- | --- | --- | --- | --- |
| Content review | Submit, approve, reject, publish | `POST content/{content}/submit`, `POST content/reviews/{submission}/{approve,reject,publish}` | `content.submit`, `content.approve`, `content.reject`, `content.publish` respectively | Handlers and controls present | AdminContentWorkflowTest | Recheck maker-checker through browser and direct API |
| Content licensing | Read and update license window | `GET/PUT content/{contentType}/{contentId}/license-window` | Controller read policy and `content.schedule` for writes | Form and routes present | AdminContentWorkflowTest and playback entitlement suite are related | Verify regional playback against trusted staging geolocation |
| Media library | Upload, view QC, retry processing | `POST media/uploads`, `PUT media/uploads/{session}/chunks/{index}`, `GET media/assets/{asset}/quality`, `POST media/assets/{asset}/retry` | `media.upload`, `media.view`, `media.transcode.retry` by action | Form and endpoints present | AdminMediaManagementTest | Test interrupted upload and actual worker completion |
| Subscription plans | Create, edit, archive | `POST subscriptions/plans`, `PUT subscriptions/plans/{plan}`, `POST subscriptions/plans/{plan}/archive` | `subscriptions.plans.create`, `subscriptions.plans.update`, `subscriptions.plans.archive` | Forms and endpoints present | AdminSubscriptionManagementTest | Verify regional pricing and entitlement simulation in browser |
| Finance | Request, review, execute refund | `POST finance/refunds`, `POST finance/refunds/{refund}/review`, `POST finance/refunds/{refund}/execute` | `finance.refunds.request`, `finance.refunds.approve` | Controls and endpoints present | FinanceOperationsTest | Verify independent approval and reconciliation with provider sandbox |
| Marketing | Edit banner and save draft | `POST marketing/banners`, `PUT marketing/banners/{banner}`, `PUT marketing/campaigns/{campaign}` | `marketing.banners.manage`, `marketing.campaigns.manage` | Controls and endpoints present | MarketingOperationsTest | Verify save, refresh, and permission denial against Laravel |
| Support | Inspect customer, revoke playback | `GET support/customers/{customer}`, `POST support/customers/{customer}/playback-sessions/{session}/revoke` | `support.accounts.view`, `support.sessions.revoke` | Controls and endpoints present | SupportOperationsTest | Verify revoke is enforced by player and private media |
| Analytics | Export and schedule reports | `GET analytics/export`, `POST analytics/reports` | `analytics.export` and controller data scope | Controls and endpoints present | AnalyticsOperationsTest | Compare results to source database records |
| Technical operations | Health, retry, incident management | `GET operations/health`, `POST operations/jobs/{job}/retry`, `POST/PATCH operations/incidents` | `operations.health.view`, `operations.jobs.retry`, `operations.incidents.manage` | Controls and endpoints present | TechnicalOperationsTest | Verify broker failure, retry, and delivered alerts |
