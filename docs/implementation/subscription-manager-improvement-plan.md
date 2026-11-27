# Subscription Manager improvement plan

Status: planned; no implementation work is included in this document.

## Goal

Give Subscription Managers the tools to operate the plan catalog, subscriber
records, coupons, and trial policies while keeping payment execution and
refund authority with Finance. Backend permissions remain the security
boundary; navigation and hidden controls are only presentation.

The current implementation already includes plan CRUD and archival, regional
prices, plan entitlements, coupon management, plan-level trial days, subscriber
listing, an entitlement simulator, and plan performance. Plan and coupon
mutations are audited, and archived plans preserve existing subscriber
snapshots. The main gaps are subscriber lookup, trial policy depth, the
subscription lifecycle boundary, and financial metric permissions.

## Milestone 1: Subscriber lookup

- Add server-side search by account email or ID, plus status, plan, and date
  filters. Keep pagination and maximum page size enforced by Laravel.
- Show only the identifiers and subscription fields needed for this role;
  avoid exposing payment instruments, full payment history, or unrelated
  customer profile data.
- Add accessible filter controls, pagination, useful empty/error states, and a
  read-only subscription detail view with plan snapshot, dates, status, and
  entitlement summary.
- Keep subscriber access behind `subscriptions.subscribers.view`; add audit
  events only for sensitive access or exports if product policy requires them.

**Acceptance:** a Subscription Manager can locate a subscriber, apply filters,
page through results, and inspect the saved entitlement snapshot. Requests
without the permission receive 403 responses, and the UI never loads the
directory for unauthorized roles.

## Milestone 2: Explicit trial policy controls

- Decide the policy fields the business needs: trial duration, first-time
  eligibility, allowed plans/regions, and whether a payment method is required.
- Move trial policy editing into a clearly named workflow rather than treating
  `trial_days` as an incidental plan form field. Keep plan-level trial values
  backward compatible while a migration path is agreed.
- Enforce eligibility and idempotency in the checkout/service layer, not only
  in the admin UI. Preserve the rule that a trial cannot be granted twice to
  the same customer unless policy explicitly allows it.
- Audit policy changes with before/after values and actor identity.

**Acceptance:** permitted staff can configure and review policies; checkout
consistently applies them; prior subscribers cannot bypass eligibility through
another client or direct API request.

## Milestone 3: Governed subscription lifecycle operations

- Decide which lifecycle actions belong to this role, such as scheduling
  cancellation at period end or viewing renewal state. Keep customer-initiated
  cancellation and provider synchronization as existing system contracts.
- Do not add manual payment capture, refunds, reconciliation, or unverified
  entitlement grants to this role. Those remain Finance/provider workflows.
- For each approved mutation, define authorization, confirmation, idempotency,
  audit fields, and behavior when the provider is unavailable or reports an
  uncertain result. Add a dedicated permission only if lifecycle writes are
  approved.

**Acceptance:** each approved action is reflected after refresh, has an audit
record, cannot be duplicated by retries, and does not claim a provider-side
change until confirmed.

## Milestone 4: Separate subscription performance from finance

- Keep plan-level active subscriber and conversion measures under subscription
  permissions where their definitions are agreed.
- Gate gross revenue, paid transaction totals, and currency aggregates behind
  finance permissions, or remove them from this page. Do not grant
  `finance.payments.view` to Subscription Manager solely to render the page.
- Define time ranges, currency grouping, refunds/chargebacks treatment, and
  source-of-truth transaction states before adding charts or exports.

**Acceptance:** a Subscription Manager sees subscription-operational metrics
only; Finance permissions remain independently enforced; totals reconcile to
the documented transaction definitions.

## Milestone 5: Workflow and authorization verification

- Extend Laravel feature coverage for subscriber filtering/detail authorization,
  trial policy validation and eligibility, lifecycle idempotency if approved,
  and permission boundaries for financial metrics.
- Add admin browser coverage for plan create/edit/archive, coupon lifecycle,
  subscriber search/pagination, trial policy editing, and denied access.
- Exercise the Subscription Manager, Finance Manager, Customer Support, and
  Super Admin roles. Verify API denial directly as well as UI visibility.
- Update the admin functional inventory and operator documentation with final
  role boundaries and any provider prerequisites.

**Acceptance:** persisted changes survive reload; restricted API requests are
denied; UI states and validation errors are clear; the role matrix matches the
canonical RBAC registry.

## Scope boundaries and order

Deliver Milestones 1 and 4 first: they address the immediate operational lookup
gap and the financial-permission boundary without changing checkout behavior.
Then settle trial policy requirements before Milestone 2, and approve lifecycle
actions explicitly before Milestone 3. Keep the existing `subscriptions.*`
permission identifiers stable unless a separately reviewed RBAC migration is
needed. No payment-provider contract, refund flow, or existing subscription
snapshot should change as a cleanup side effect.
