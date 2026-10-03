# Dynamic platform detection and early page cache

NPE 1.9 resolves one public page-cache owner at runtime. The resolver prefers a
capable LiteSpeed server cache, respects every foreign `advanced-cache.php` and
known active page-cache plugin, uses NPE Early Cache only after explicit opt-in,
and otherwise falls back to the application cache. LiteSpeed plugin presence
alone is not treated as server-cache capability.

## Early hit path

When `WP_CACHE` is already enabled and NPE owns the drop-in, WordPress loads
`wp-content/advanced-cache.php` near the beginning of bootstrap. The small drop-in
loads only the plain-PHP early-cache request, policy, key, reader, response, and
runtime classes. A safe filesystem hit emits bounded headers and HTML, then exits;
it does not boot the NPE plugin, WooCommerce, the theme, or the normal plugin set.

The early reader consumes the guarded, hashed cache record format already written
by the application cache. It verifies the record version, key, content hash, and
fresh/stale timestamps. Object cache is intentionally not used in this path,
because its availability is not deterministic at drop-in execution time.

## Ownership and installation

The installer reads but never executes an existing drop-in. It recognizes the NPE
ownership marker, the paired signatures of markerless legacy NPE drop-ins, and
common external owners. Legacy recognition requires both the NPE runtime class
and its private configuration path, so a third-party compatibility comment cannot
be mistaken for ownership. Any unknown file is classified as
external and remains byte-for-byte unchanged. NPE writes through a temporary file,
renames atomically, and verifies the final SHA-256 hash. Deactivation removes only
an NPE-owned drop-in.

When a foreign owner is detected, the administration notice names known owners
and treats the condition as an ownership handoff rather than an installation
failure. If the other cache is active, no action is required. Switching owners is
deliberately manual: disable the other cache through its own controls, let its
cleanup tool remove `advanced-cache.php`, then save NPE settings again. NPE never
deletes or chains a foreign drop-in.

NPE does not modify `wp-config.php`; `WP_CACHE` must be enabled by the operator or
hosting platform. NPE also requires a writable `wp-content` directory to install
the drop-in. If either requirement is missing, application caching remains the
safe fallback.

## Request safety

The early policy defaults to bypass. It permits only same-host GET/HEAD requests
that match the configured site and cache dimensions. It bypasses login/admin/REST,
preview and `wc-ajax` requests, cart, checkout, account, non-idempotent methods,
authorization, password/logged-in/customer/cart/session cookies, sensitive or
unapproved query parameters, and administrator-defined excluded paths.

Application response policy additionally refuses non-200, empty, personalized,
non-HTML, `Set-Cookie`, `private`/`no-store`, password, nonce, and admin-toolbar
responses before any record can reach the early cache.

## Diagnostics

NPE → Diagnostics reports the detected server, reverse proxy, selected owner and
reason, early-cache availability/installation/configuration, drop-in owner,
filesystem writability, persistent object cache, Redis, Memcached, LiteSpeed, and
ownership conflicts. Detection is read-only and is based on multiple capability
signals; `SERVER_SOFTWARE` is only one signal.

Diagnostic response headers contain no path, key, identity, or backend secret:

```text
X-NPE-Cache: HIT|MISS|STALE|BYPASS
X-NPE-Cache-Layer: EARLY|APPLICATION
X-NPE-Cache-Provider: NPE
Age: <seconds>
```
