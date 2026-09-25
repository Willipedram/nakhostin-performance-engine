# Nakhostin Performance Engine Architecture

## Purpose and scope

Nakhostin Performance Engine (NPE) is an independent, modular WordPress
performance platform. Its long-term scope includes DOM intelligence, asset
analysis, page-specific bundles, several cache layers, cache warming and
purging, backend monitoring, and optional compatibility with WooCommerce,
Elementor, WoodMart, and LiteSpeed Cache.

DOM Intelligence provides an explicitly triggered pipeline on top of the
production core foundation. It creates privacy-conscious structural manifests
without rewriting pages or changing third-party configuration.

Component Intelligence now maps those structural manifests to reusable core,
integration-owned, and administrator-defined functional components. Stable
component signatures and a compact bundle dependency index prepare later asset
generation without producing optimized files in this phase.

JavaScript Intelligence consumes WordPress registration metadata and Component
Intelligence dependencies to create dependency-ordered core, component, and page
plans. Unsafe, critical, inline-configured, external, or ambiguous scripts retain
their original handles. Generated classic-script bundles are explicit build
artifacts with content-hashed names; no frontend optimization runs automatically.

## Directory and module boundaries

- `Core` owns startup, lifecycle, and the service registry. Core services depend
  on `Contracts` and `Support`; the `Plugin` composition root is the deliberate
  exception that wires concrete feature services without moving their logic into
  Core.
- `Contracts` contains narrow interfaces shared across modules. It depends on no
  concrete module.
- `Admin` will own capability-protected settings, diagnostics, notices, and the
  RTL-first administration interface.
- `Infrastructure` will contain concrete persistence, scheduling, HTTP, and
  filesystem adapters. Infrastructure implements contracts; domain modules do
  not depend on its concrete classes.
- `DOM` owns structural page analysis. Review-only `CSS` usage analysis and current
  `JavaScript` planning consume normalized DOM/component results through
  contracts rather than concrete analyzer classes where practical.
- `JavaScript` owns WordPress source discovery, dependency graphs, safety policy,
  layered optimization plans, conservative minification, hashed output, and
  privacy-safe reports. It consumes component metadata but does not own it.
- `Components` owns reusable component definitions, signatures, usage metadata,
  future bundle plans, and targeted plan invalidation. It consumes DOM manifests
  but never mutates DOM output or generates optimized assets.
- `Cache` will coordinate page, fragment, object-cache, warmup, and invalidation
  policies behind storage and purge contracts.
- `Performance` will measure backend and response performance without coupling
  feature modules to a specific telemetry system.
- `Integrations` contains isolated adapters for LiteSpeed, WooCommerce,
  Elementor, and WoodMart. Each integration has its own feature detection and
  enabled state and can be disabled without affecting core behavior.
- `Support` is reserved for small, framework-independent helpers and value
  objects. It must not become a miscellaneous business-logic module.
- `assets/admin` and `assets/public` separate dashboard assets from public
  assets. `languages` stores translation catalogs. `tests/Fixtures` stores only
  deterministic test inputs.

Empty module directories contain `.gitkeep` files in Phase 0 so their intended
boundaries remain visible. This is the only significant deviation from a fully
populated tree: placeholder implementation classes were intentionally avoided.

## Dependency rules

1. Code targets PHP 7.4 or newer, is namespaced under
   `Nakhostin\PerformanceEngine`, and is loaded with PSR-4.
2. Feature code depends on interfaces from `Contracts`; adapters depend inward
   on those contracts. Integration SDK details may not leak into core modules.
3. `Core` orchestrates registration but performs no request-time analysis or
   expensive I/O during file inclusion.
4. Integrations never become required dependencies. Missing integrations must
   result in a no-op, not a fatal error.
5. Global state is accessed only at WordPress boundaries and is wrapped where
   doing so improves unit testability.
6. Public APIs will be versioned and documented before external consumers are
   encouraged to use them.

## Bootstrap and service registration

The root plugin file checks for WordPress, defines immutable path/version
constants, loads Composer when present, registers a small source fallback
autoloader for packaged-source resilience, attaches activation/deactivation
callbacks, and asks the singleton coordinator to register hooks. The coordinator
registers translation loading for `init` and exposes a lightweight service
registry. Services are small shared instances or lazy factories; no reflection or
automatic dependency resolution is used.

Activation runs ordered schema migrations and records the code and schema
versions per site. Runtime version checks are idempotent. Deactivation only
clears NPE's reserved maintenance schedule. Uninstall retains data by default and
removes NPE-owned options only after an administrator explicitly opts in. Network
activation and uninstall apply these rules independently to each site.

## Configuration and feature gates

`Infrastructure\Settings` is the single option boundary. It supplies complete
defaults, discards unknown keys, normalizes booleans, and returns known values
only. The WordPress Settings API owns persistence and nonce verification, while
the NPE admin page additionally checks `manage_options` before rendering.

Every future module has a stored, default-off flag, but a flag becomes effective
only when that module is also declared available in code. This two-part check
prevents crafted option writes from activating unfinished work. Phase 1 declares
all performance modules unavailable and does not render activation controls.

## Diagnostics and logging

Diagnostics are computed on demand in the administration screen and never alter
the environment. Detection is intentionally shallow: public constants, classes,
hooks, and WordPress APIs report platform versions, HTTPS, multisite, optional
plugins, persistent object cache, and detectable Redis clients.

The logger is injected through `LoggerInterface`, is disabled by default, and
also requires `WP_DEBUG`. It accepts bounded scalar context, drops nested data,
and redacts keys that may contain credentials, authentication data, or personal
information. Production behavior must never depend on logging.

## Integration strategy

Every integration implements `IntegrationInterface`, handles its own detection,
and registers only documented public hooks or APIs. A future integration manager
will read independent enable/disable settings and lazily instantiate available
adapters. No feature module may directly call an optional plugin's private class,
read its internal options as an API, or modify its files.

### LiteSpeed compatibility

NPE is server-agnostic and must function on Apache, Nginx, LiteSpeed, and other
WordPress-compatible environments without LiteSpeed Cache. A future LiteSpeed
adapter will use documented public actions, filters, and APIs only. It will not:

- inherit from or depend on LiteSpeed's internal classes;
- edit LiteSpeed files or overwrite LiteSpeed settings;
- silently disable or duplicate LiteSpeed features; or
- prevent NPE's independent cache implementations from using other backends.

When overlapping capabilities are detected, the adapter will report them and
apply an explicit, user-selected compatibility policy. Equivalent boundaries
apply to WooCommerce, Elementor, and WoodMart.

## Future data and storage strategy

Phase 1 creates no custom tables. It stores only validated settings plus plugin
and schema version options. Future storage choices will follow lifecycle and
query requirements:

| Store | Intended data | Reason |
| --- | --- | --- |
| Options | Versioned settings, enabled modules, schema version | Small site-wide configuration that WordPress already loads and manages |
| Post meta | Per-content optimization state or bundle references | Data whose lifecycle and permissions follow a post |
| Transients | Short-lived discovery results, locks, and retry state | Expiring coordination data that may use persistent object cache automatically |
| Object cache | Hot computed manifests and request-independent lookups | Fast ephemeral access with graceful fallback when persistence is unavailable |
| Custom tables | Large dependency graphs, measurements, and URL-indexed analysis histories | Only when volume, relational queries, retention, and concurrency cannot be handled safely by core stores |

Any custom table proposal must include measured need, multisite behavior,
indexes, migrations, retention, export/erasure considerations, and uninstall
policy before implementation. Cache payloads must be versioned and disposable;
authoritative configuration must never live only in a cache.

## High-level caching strategy

Future page, fragment, and object caching will share explicit key construction,
storage, invalidation, and warmup contracts. Cache keys will account for site,
locale, relevant request variation, content version, and authenticated or
commerce context. Dependency-aware tags will map changes to the smallest safe
purge set. WooCommerce sessions, carts, checkout, personalized responses, and
non-idempotent requests will default to bypass. Stampede protection, bounded
retention, observability, and safe fallback are required before a cache backend
ships.

## DOM and asset analysis strategy

DOM analysis is explicitly invoked from a capability- and nonce-protected admin
workflow rather than performed on frontend requests. It normalizes a safely
fetched, size-limited response into a versioned structural manifest. Full HTML is
kept only in memory. Signatures omit volatile instance details, query strings,
text, and values so later consumers can reuse results across matching templates.
Static component and state evidence is descriptive and never executes content.

Asset intelligence constructs dependency graphs from registered WordPress
assets and observed markup. CSS selector use and JavaScript dependencies are
analyzed conservatively. Future generated page-specific artifacts must retain ordering,
conditional loading, localization data, and dependency semantics. Unsupported
or ambiguous assets will remain unchanged. Atomic writes, content-addressed
names, rollback, and Content Security Policy compatibility are future design
requirements.

## Security strategy

- Administrative operations require an explicit capability and verified nonce.
- Input is validated against an expected shape, sanitized at entry, and escaped
  for its output context at the last possible moment.
- SQL uses WordPress database APIs and prepared statements; filesystem and HTTP
  access use WordPress APIs with allowlists, path checks, size limits, and
  timeouts.
- Cached/generated data is treated as untrusted on read. Diagnostic output
  redacts secrets and personally identifiable information.
- Background tasks are idempotent, bounded, and protected against replay and
  concurrent execution. Failures must fall back to unoptimized WordPress output.
- Integration presence never grants authorization and third-party payloads are
  never trusted implicitly.

## Internationalization and RTL

All user-facing strings use the `nakhostin-performance-engine` text domain and
WordPress internationalization functions. Business logic contains no hard-coded
Persian or other translated copy. Translation loading runs on
`init`; catalogs belong in `languages`.

Administration UI will use logical CSS properties, avoid directional imagery,
and be designed in Persian/Farsi RTL and English LTR from its first phase.
Dynamic strings must be escaped after translation, and translators' comments
will accompany placeholders and ambiguous context.

## Independent full-page cache

The cache module is a conservative application-level pipeline with a backend-neutral store, stable bounded keys, public/private classification, atomic filesystem persistence, scoped invalidation, and same-origin warmup hooks. It is off by default and has no LiteSpeed dependency. See [Full Page Cache](page-cache.md). A normal plugin cannot universally intercept requests before WordPress bootstrap, so future server integrations remain behind `ServerCacheAdapterInterface`.

## Fragment and object caching

Reusable dynamic fragments are isolated behind `FragmentCacheInterface` and `FragmentStoreInterface`. Stable versioned keys distinguish public and private scopes; dependency tags permit targeted invalidation. A factory uses the public WordPress object-cache API only when persistence is reported, otherwise selecting an atomic filesystem fallback. No backend performs a global WordPress object-cache flush. See [Fragment Cache and Object Cache Integration](fragment-object-cache.md).

## Smart purge and warmup

Cache invalidation is modeled as a bounded directed dependency graph and processed through deduplicated WP-Cron queues. Routine post, product, taxonomy, component, and asset changes resolve only their transitive page, fragment, and bundle dependencies; full invalidation is reserved for explicit or global changes. Same-origin warmup jobs run separately in small asynchronous batches. See [Smart Purge and Cache Warmup](smart-purge-warmup.md).

## LiteSpeed Cache integration

LiteSpeed cooperation is isolated behind `LiteSpeedDetector`, `LiteSpeedAdapter`, `LiteSpeedCacheBridge`, and `LiteSpeedPurgeBridge`. The default Compatible mode delegates public page-cache ownership when LiteSpeed is active and capable, while suppressing duplicate NPE runtime optimization. Cooperative mode explicitly adds public cacheability, TTL, vary, tag, and scoped purge hooks. Independent mode emits no LiteSpeed hooks. See [LiteSpeed Cache Integration](litespeed-integration.md).

## Performance monitoring

The opt-in Performance module samples public backend requests and stores bounded, privacy-safe measurements. It separates PHP/backend generation from real-world TTFB, does not enable expensive database tracing, and retains only low-cardinality page type, template, component, and cache-state dimensions. See [Performance Monitoring](performance-monitoring.md).

## Administration interface

The administration layer uses native WordPress menus, capabilities, nonces, localization, and asset APIs. Overview is read-only, subsystem screens remain modular, Persian/RTL is first-class, and technical values retain LTR isolation. Assets are scoped to NPE screens and the responsive layout requires no external framework. See [Administration Interface](admin-interface.md).

## Release hardening

Runtime adapters follow fail-open/pass-through behavior: cache or artifact storage failures retain the original WordPress response and assets. Public cache policy rejects personalized/session-bearing traffic, filesystem records are guarded and content-verified, and generated JavaScript is confined to a protected trusted directory. The detailed threat model and residual risks are documented in [Security](security.md).

## Future phases

1. **Administration and configuration (Phase 1):** validated settings, safe
   feature gates, diagnostics, RTL/LTR UI, and lifecycle-safe migrations.
2. **DOM Intelligence (current):** bounded explicit capture, normalized usage
   manifests, stable signatures, static component/state evidence, and safe
   latest-manifest storage. Scheduling and runtime-state learning remain future.
3. **Component Intelligence (current):** reusable definitions, defensive
   adapters, custom PLP/PDP registration, stable signatures, and deduplicated
   bundle dependency plans.
4. **JavaScript Intelligence (current):** WordPress source discovery,
   dependency-safe ordering, conservative layered plans, explicit defer/delay
   policy, content hashing, and diagnostics.
5. **CSS analysis (current diagnostics):** conservative selector classification
   against DOM manifests is available; automatic removal, artifact generation,
   and serving with rollback remain future work.
6. **Caching (current):** conservative full-page caching, dependency-aware fragment caching, atomic filesystem storage, persistent WordPress object-cache integration, invalidation, and bounded warmup.
7. **Optional integrations (current foundations):** defensive WooCommerce,
   Elementor, WoodMart, and public-hook-only LiteSpeed adapters; future phases
   may deepen support without introducing hard dependencies.
8. **Operations and hardening:** backend monitoring is now available; browser
   timing, diagnostics hardening, multisite, privacy tools, load tests,
   compatibility matrices, and automated releases remain future work.

Each phase must add focused unit and WordPress integration tests and must retain
a safe pass-through behavior when optimization cannot be proven correct.
