# Release Readiness Report — 1.5.0

## Completed modules

- Core lifecycle, service registry, settings, feature flags, migrations, safe uninstall, diagnostics, redacted logging, localization, and administration.
- DOM Intelligence and reusable component manifests/registry.
- Dependency-aware JavaScript discovery and conservative build planning; automatic frontend optimization remains intentionally inactive.
- Independent application-level page cache, fragment cache, persistent WordPress object-cache adapter, smart purge, and asynchronous warmup.
- Defensive WooCommerce, Elementor, WoodMart, and public-hook-only LiteSpeed adapters.
- Sampled privacy-safe backend performance monitoring.

CSS optimization, browser crawling/runtime-state discovery, true generic server-level full-page caching, and automatic frontend JavaScript bundle replacement are not completed and are not presented as active.

## Security findings

No direct SQL, REST routes, unsafe unserialization, arbitrary PHP evaluation, or user-directed PHP includes were found. Admin mutations consistently use capability and nonce checks. Hardening added stricter private-cookie/query/response detection, host-port validation, guarded and verified cache records, protected directories, trusted JavaScript output confinement, and fail-open cache runtime handling. See [Security](security.md).

## Compatibility

- WordPress 6.4+ and PHP 7.4+.
- Apache, Nginx, LiteSpeed, and other WordPress-capable servers at application level.
- Optional WooCommerce, Elementor, WoodMart, LiteSpeed Cache, Redis, and Memcached; absence is non-fatal.
- Persian (`fa_IR`) RTL and default English LTR administration.
- Multisite-aware activation/uninstall lifecycle.

## Measured overhead

The repeatable CLI micro-benchmark (`php tests/Performance/benchmark.php`) on the release container measured the following approximate mean costs. These are micro-benchmarks, not production latency promises:

| Scenario | Mean |
| --- | ---: |
| Disabled monitor registration | 32.374 µs |
| Enabled-but-idle settings read | 24.132 µs |
| Filesystem cache hit lookup | 339.132 µs |
| Scoped URL purge | 702.034 µs |
| Small DOM analysis | 493.308 µs |
| Small JavaScript asset build | 273.802 µs |
| Warmup scheduling decision | 12.345 µs |

DOM analysis and asset builds are explicit administrator/build operations, not normal frontend work. Purge and warmup are queued. Results vary by filesystem, PHP build, object cache, and hardware.

## Release decision

Recommended version: **1.5.0**. It is suitable for a controlled production release with conservative defaults. Page intelligence supports explicit capture plus opt-in bounded asynchronous learning; CSS analysis remains diagnostic-only. Modern WordPress loads the committed text-based Persian catalog; release packaging must additionally compile the MO fallback for WordPress 6.4. Operators should stage-test their theme/commerce flows, retain backups, verify cache directory protections for their web server, and initially leave optional runtime features disabled.

The capture listener performs only an empty query-parameter check on ordinary
frontend requests. DOM parsing, stylesheet HTTP requests, script discovery, and
manifest writes run only for a valid single-use analysis token.
