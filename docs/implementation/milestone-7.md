# Milestone 7: Subscription management

## Implemented

- Added permission-checked plan creation, editing, archival, subscriber listing, coupon management, and entitlement simulation APIs under `/api/admin/subscriptions`.
- Removed unauthenticated plan and plan-limitation mutation routes. The public `plan-list` catalog remains available for customer clients.
- Added a subscription management page with plan editing, trial configuration, plan comparison, subscriber overview, coupon management, and entitlement simulation.
- Added first-subscription-only trials. Trial activation locks the account, rechecks its full subscription history, and does not create a paid transaction.
- Added plan-level device, profile, simultaneous-stream, and maximum-quality limitations. Existing subscription snapshots remain unchanged when a plan is edited or archived; playback enforces the saved limits.
- Restricted trial configuration to `subscriptions.trials.manage`; subscriber directory access uses `subscriptions.subscribers.view`.
- Added plan performance reporting based on active subscriptions and records marked paid in `subscriptions_transactions`. New subscription payments record their currency; totals are grouped by currency, and legacy amounts without currency are labeled `UNRECORDED`.
- Hardened the existing coupon API so write operations require `subscriptions.coupons.manage`; coupon plan applicability and expiry are checked during pricing.
- Added coupon-wide and per-account redemption caps. Checkout reserves a use under a coupon row lock, places the reservation token in provider metadata, and records a redemption only when the server verifies payment. Reservations are released for failed gateway creation, Stripe/Razorpay failure or cancellation events, and expired reservations are swept hourly.
- Defined the plan-change policy: a plan change replaces the active plan immediately after the new payment is verified. An unverified checkout never changes access, and the admin UI explains this behavior.
- Added audited per-country plan price overrides with explicit ISO country, currency, and amount entries. Checkout and customer plan catalogs select an override only from the trusted edge country resolver; when a plan has regional rows, missing/untrusted country data or a missing country price fails closed. Plans with no overrides preserve their existing global base price, currency, and subscription snapshots.
- Added the permission-protected `/api/admin/subscriptions/pricing-options` catalog and regional price editing to the admin plan form. Primary and regional prices respect zero- or two-decimal currency precision supported by the payment APIs. Fixed-amount coupons and fixed taxes are rejected for a regional currency different from the configured primary currency because no cross-currency discount/tax matrix is defined; percentage coupons and taxes continue to apply.
- Web checkout now creates Stripe, Razorpay, and Paystack orders using the resolved quote currency, and stores the expected minor-unit amount and currency in provider metadata. Return and webhook verification bind the provider-confirmed charge to that quote instead of re-resolving the customer's country after checkout. Subscription transaction snapshots retain the charged currency and amount.
- Provider amount conversion uses gateway rules rather than the display precision stored in the currency catalog; Paystack is limited to its currently supported currencies and uses its documented 100x subunit amount, including XOF.

## Verification

- Laravel subscription, trial, and playback suites: 23 tests / 85 assertions passed in the running backend container. PHPUnit reports one existing deprecation.
- Admin UI production build passed with `/dashboard/subscriptions`. Next.js reports existing dynamic-export warnings on unrelated routes and Node's `url.parse()` deprecation warning.
- Additive migrations through `2026_11_24_000011` were applied to the local Docker database.
- PHP syntax checks and `git diff --check` passed. Git reported only expected CRLF-to-LF normalization warnings for legacy files.
- The regional pricing slice received source review, PHP syntax checks, and `git diff --check` only. Builds and tests remain deferred per instruction.

The current provider checkout path supports reservation metadata for Stripe Checkout, Stripe PaymentIntents, Razorpay, and Paystack. PayPal checkout is not implemented in this application; coupon reservations for that unavailable path are released immediately.

## External integration dependency

The regional pricing mechanism is implemented, but actual country prices remain business configuration and no production matrix is seeded. Production must configure `PLAYBACK_GEO_TRUSTED_PROXY_CIDRS` and ensure the trusted edge overwrites its country header; a plan configured with regional rows fails closed until that signal and a matching price are available. The matrix must also use currencies supported by the selected payment provider. The global primary-currency price remains unchanged for plans without regional rows.
