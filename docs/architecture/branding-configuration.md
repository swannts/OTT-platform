# Branding configuration

The current customer-facing brand remains **NOOR Islamic Cinema**. Keep
customer-facing defaults and public identity aligned with NOOR while generic
engineering and framework defaults use **OTT Platform**.

Laravel's existing Settings module is the operational source for configured
business identity. Its General Settings UI stores the business and user app
names, primary and dark logos, mini logos, favicon, contact information,
social links, and copyright text. Existing Laravel configuration helpers and
the public app-configuration and SEO endpoints provide the supported read
paths. Settings writes remain behind the existing administrative routes and
authorization; do not add a second environment-variable-backed settings
store.

The deployment examples now default Laravel's framework `APP_NAME` to
`OTT Platform` and the customer image build label to `NOOR Islamic Cinema`.
These defaults do not rename the existing database (`swann`), Docker volumes,
Flutter package/application identifiers, Firebase projects, or media storage
paths, because those names identify persisted data or installed client apps.

## Remaining work

Branding is not yet fully centralized across every customer web screen. The
Next.js site still has NOOR-specific labels, URLs, and visual assets in source,
and the existing settings API does not expose one validated, complete public
brand document for that site. Keep the current NOOR identity until an additive
API contract and UI integration can be tested without changing mobile/TV
clients. Color tokens, email template identity, legal references, and support
contacts also need a permission-aware inventory before claiming full
brand-independence. No secrets should be included in any public brand response.
