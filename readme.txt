=== Nakhostin Performance Engine ===
Contributors: nakhostin
Tags: performance, cache, optimization, woocommerce
Requires at least: 6.4
Requires PHP: 7.4
Stable tag: 1.5.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

A modular, integration-friendly performance engine foundation for WordPress.

== Description ==

Nakhostin Performance Engine (NPE) is being built as an independent performance
platform for WordPress and WooCommerce. Version 1.5.0 adds opt-in, gradual page intelligence that queues sampled public requests and analyzes them asynchronously. Automatic CSS tree shaking remains intentionally unavailable.

== Installation ==

1. Upload the plugin directory to `/wp-content/plugins/`.
2. Activate Nakhostin Performance Engine in WordPress.
3. Optionally generate the optimized Composer autoloader when deploying from source; NPE has no required production Composer packages.

== Changelog ==

= 1.5.0 =
* Add opt-in automatic DOM learning from eligible anonymous public page requests.
* Process a bounded deduplicated queue through WP-Cron with cooldowns, retries, and sensitive-route exclusions.

= 1.4.0 =
* Add one-click, one-time frontend capture for DOM, component, CSS, and JavaScript manifests.
* Automatically collect bounded same-origin stylesheets and the rendered page script queue.

= 1.3.1 =
* Load the complete Persian administration translation from a deployable text-based WordPress catalog.

= 1.3.0 =
* Add conservative CSS selector usage analysis against DOM manifests.
* Distinguish required scripts from registered-only scripts using page and dependency evidence.
* Explain analysis decisions and settings effects in professional Persian.

= 1.2.1 =
* Remove binary artifacts from Git; add translation compilation, SVG/text classification, and a binary-file repository guard.

= 1.2.0 =
* Harden cache privacy, filesystem records, generated JavaScript output, and fail-open runtime behavior; add release, security, troubleshooting, and overhead documentation.

= 1.1.0 =
* Add the production administration dashboard, ordered module navigation, Persian translations, RTL styling, accessibility, and mobile layouts.

= 1.0.0 =
* Add sampled backend performance monitoring, statistical dashboards, privacy-safe breakdowns, and bounded retention.

= 0.9.0 =
* Add optional LiteSpeed Cache detection, ownership, cacheability, tags, TTL, vary, and scoped purge bridges.

= 0.8.0 =
* Add smart purge dependency graphs and asynchronous warmup queues.

= 0.7.0 =
* Add dependency-aware fragment caching and persistent WordPress object-cache integration.

= 0.6.0 =
* Add the independent application-level full-page cache foundation.

= 0.5.0 =
* Add dependency-safe JavaScript Intelligence and hashed bundle output.

= 0.4.0 =
* Add Component Intelligence, custom definitions, adapters, and bundle plans.

= 0.3.0 =
* Add the DOM Intelligence analyzer, manifest storage, and diagnostic screen.

= 0.2.0 =
* Add the Phase 1 core and administration settings foundation.

= 0.1.0 =
* Establish Phase 0 architecture and testing foundation.
