# Adaptive diagnostics and hardening

NPE samples bounded WordPress lifecycle checkpoints and records only aggregate database timing. It never stores SQL, cookies, authorization data, request bodies, customer data, or secrets. Detailed hook profiling is available only when both the explicit setting and `WP_DEBUG` are enabled.

Health checks are server-neutral. Recommendations select LiteSpeed native caching only when the server capability exists; Nginx, Apache, and generic PHP environments receive NPE Early Cache guidance. Redis and Memcached are reported as optional capabilities.

Browser-cache findings distinguish private WooCommerce contexts from public pages. `no-store` on checkout is expected; on a public homepage it is marked for investigation. Security headers are audited only. NPE does not guess or automatically enable CSP or HSTS. CSP management remains disabled by default, and any future management must begin with report-only mode.

DOM and main-thread reports are diagnostic indicators. The server cannot reproduce a Chrome trace or browser-measured Total Blocking Time, and the UI must not represent these estimates as Lighthouse measurements. The scorecard uses evidence-based categorical states and deliberately has no synthetic overall score.

Support exports use a strict section allowlist and recursively remove keys associated with credentials, identity, sessions, SQL, and request secrets.

## LiteSpeed Guest Vary latency

The Diagnostics screen provides an explicit same-origin probe for `guest.vary.php`. It is never executed for frontend visitors, follows no redirects, sends no visitor cookies, and has a three-second network timeout. NPE stores only the status, elapsed time, content type, and a bounded finding—not the response body.

A duration above two seconds is reported as slow. HTTP 403, non-200 responses, or an HTML response where JSON is expected are reported as external configuration failures. NPE does not patch LiteSpeed; administrators should inspect PHP worker saturation, loopback DNS/TLS, WAF or ModSecurity rules, and LiteSpeed Guest Mode configuration.
