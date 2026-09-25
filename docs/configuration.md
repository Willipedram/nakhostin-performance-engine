# Configuration and Administration

## Storage model

NPE stores configuration in the single `npe_settings` option. The value is a
version-independent nested array owned by `Infrastructure\Settings`. Callers
must use that service rather than reading the option directly. Missing values are
filled from defaults, unknown keys are ignored on read, and all writes pass the
registered sanitizer.

The separate `npe_version` and `npe_db_version` options track installed code and
schema versions. They are lifecycle metadata, not user configuration.

## Configuration groups

| Group | Phase 1 controls | Availability |
| --- | --- | --- |
| General | Remove owned settings during uninstall | Available; removal defaults off |
| Debugging | Enable redacted logs when `WP_DEBUG` is also active | Available; defaults off |
| DOM Intelligence | Explicit diagnostic analysis; reserved automatic-run flag | Available on demand |
| CSS | Reserved `enabled` flag | Unavailable |
| JavaScript | Explicit diagnostic analysis; reserved automatic-run flag | Available on demand |
| Cache | Application page cache, fragment cache, purge, and warmup controls | Available; defaults off |
| WooCommerce | Reserved `enabled` flag | Unavailable |
| Elementor | Reserved `enabled` flag | Unavailable |
| WoodMart | Reserved `enabled` flag | Unavailable |
| LiteSpeed | Independent, Compatible, or Cooperative coexistence mode | Available; Compatible is the default |
| Performance | Diagnostic sampling rate, retention, and bounded history | Available; defaults off |

Flags for unfinished modules exist to stabilize the configuration shape and are
not shown as editable controls. `FeatureFlags` requires both an available
implementation and a validated enabled setting, so manually changing an option
cannot start an unfinished module. DOM diagnostic analysis is an explicit administrator action;
its default-off flag continues to prevent automatic execution.

The release availability registry is centralized in
`FeatureFlags::AVAILABLE_MODULES`: DOM Intelligence, JavaScript, Cache,
LiteSpeed, and Performance are available. CSS, WooCommerce, Elementor, and
WoodMart remain unavailable. Their reserved configuration groups and defensive
detection adapters do not make unfinished optimization modules available.

## Administration security

The top-level **NPE / Performance Engine** screen requires the WordPress
`manage_options` capability. The form posts to `options.php` and uses the
WordPress Settings API, which supplies capability enforcement and a settings
nonce. The screen performs its own capability check as defense in depth.

Inputs are accepted only by the settings sanitizer. Checkbox values are
normalized with an explicit allowlist, unknown groups and keys are dropped, and
request globals are never read by NPE. Output is escaped for HTML or attribute
context at render time.

## Localization and direction

All visible copy uses the `nakhostin-performance-engine` text domain. The screen
uses WordPress locale direction, a safe `dir` attribute, and CSS logical
properties so the same markup supports Persian/Farsi RTL and English LTR.
Translation files belong in `languages`; translated strings must never be used
as configuration keys or business rules.

## Diagnostics

Diagnostics are read-only and generated only when the NPE screen is rendered.
They report WordPress and PHP versions, sanitized server software, HTTPS and
multisite state, optional plugin presence, external object-cache use, and Redis
when a public client class, standard constant, or recognizable cache object is
available. A negative Redis result means "not detected," not proof that the
server has no Redis service.

## Uninstall behavior

NPE preserves its options by default. When **Remove NPE settings when the plugin
is uninstalled** is selected, uninstall removes only `npe_settings`,
`npe_version`, and `npe_db_version`. It does not alter third-party options or
files. On multisite, each site controls its own removal preference.

## Smart purge and warmup targets

The cache group also accepts `warm_homepage`, `important_product_ids`, and `important_category_ids`. IDs are positive integers and are used only to derive public same-origin URLs when a full rebuild is explicitly queued. Routine product and taxonomy changes warm their recently changed URLs through dependency-graph metadata. Purge and warmup work is asynchronous and retry-limited.

## LiteSpeed Cache modes

`litespeed.enabled` defaults to `true` as a compatibility safeguard and has no effect unless LiteSpeed Cache is active. `litespeed.mode` accepts only `independent`, `compatible`, or `cooperative`; invalid values fall back to `compatible`. Compatible mode is the conservative default and delegates public page-cache ownership when LiteSpeed is detected as cache-capable. Cooperative mode additionally emits documented LiteSpeed TTL, tag, vary, and scoped purge hooks. Independent mode emits no LiteSpeed hooks and never changes LiteSpeed configuration.

## Performance diagnostics

`performance.enabled` is off by default. When enabled, `sample_rate` is constrained to 1–100 percent, `retention_days` to 1–90 days, and `max_samples` to 10–2,000. Only public GET requests are eligible. Stored records exclude URLs and private request data; see [Performance Monitoring](performance-monitoring.md).
