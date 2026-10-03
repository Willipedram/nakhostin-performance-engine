# Nakhostin Performance Engine

Nakhostin Performance Engine (NPE) is a modular WordPress/WooCommerce performance plugin authored by Seyed Pedram Nakhostin. It currently provides DOM and component intelligence, review-only CSS usage analysis, dependency-aware page-specific JavaScript planning, dynamic early/application page caching and fragment caching, smart purge and warmup queues, optional public-hook-only LiteSpeed cooperation, privacy-safe backend monitoring, and a Persian-first administration interface.

## Requirements

- WordPress 6.4 or newer
- PHP 7.4 or newer
- DOM extension for DOM Intelligence
- A writable `wp-content/cache` directory for filesystem caching and generated assets

Redis, Memcached, WooCommerce, Elementor, WoodMart, and LiteSpeed Cache are optional.

## Installation

1. Copy the repository to `wp-content/plugins/nakhostin-performance-engine`.
2. Optionally run `composer dump-autoload --no-dev --classmap-authoritative`; NPE also ships a constrained source fallback autoloader and has no production Composer packages.
3. Activate **Nakhostin Performance Engine** in WordPress.
4. Review **NPE → Settings**. Runtime caching, monitoring, and debugging are disabled by default.
5. Verify filesystem and object-cache status under **NPE → Cache** and **NPE → Diagnostics** before enabling cache features.

Automatic DOM learning is opt-in under **NPE → Settings**. When enabled, NPE only
observes sampled anonymous, query-free public requests and places normalized URLs
in a bounded queue. WP-Cron analyzes small bounded batches after the visitor response;
account, cart, checkout, administration, and logged-in requests are excluded.

Version 1.6 processes that queue in configurable short batches, displays progress,
analyzed-page totals and an estimated completion time, and safely requeues known
pages after full NPE or public LiteSpeed cache purge signals.

Version 1.7 builds a bounded site-wide font map during those scans. Its optional
page-aware loader changes only analyzed font-only stylesheets and keeps mixed CSS,
unknown pages, and logged-in requests untouched.

Version 1.8 reorganizes settings into accessible WordPress-native tabs with
Dashicons, a responsive configuration summary, clear Persian descriptions, and
a persistent save action. Without JavaScript, all standard settings remain visible
and usable in one form.

Version 1.9 adds capability-based platform detection and dynamic page-cache
ownership. A capable LiteSpeed deployment remains the preferred provider;
foreign WordPress cache drop-ins remain untouched; otherwise administrators can
explicitly enable NPE Early Cache. Its verified `advanced-cache.php` drop-in uses
the filesystem and plain PHP on the hit path, so Apache, Nginx, generic PHP/FPM,
and reverse-proxy deployments do not require a LiteSpeed API. `WP_CACHE` must
already be enabled; NPE does not edit `wp-config.php`.

Version 2.0 turns stored analysis into an opt-in frontend runtime. During queued
page analysis NPE precomputes a unified manifest containing critical CSS,
dependency-safe JavaScript strategies, conservative asset decisions, and at most
two critical font preloads. Normal frontend requests only read a fresh matching
manifest. Safe mode, missing/stale manifests, logged-in users, and cart, checkout,
or account contexts retain original assets. Optimization ownership is resolved per
feature, so LiteSpeed page caching does not automatically disable NPE CSS, JS, or
font work. Images and media are never modified.

Persian works in modern WordPress installations through the committed text-based
`languages/nakhostin-performance-engine-fa_IR.l10n.php` catalog. Before creating
a release ZIP, run `composer build-php-translations` to synchronize it and
`composer build-translations` to stage the binary MO fallback required by
WordPress 6.4. Copy `build/languages/*.mo` into the ZIP's plugin `languages`
directory; compiled binary files never enter the Git source tree.

## Safe defaults

NPE does not automatically enable page caching, JavaScript changes, performance sampling, or logging. It does not modify LiteSpeed Cache configuration. Private, authenticated, cart, checkout, account, preview, REST, AJAX, session-bearing, and sensitive-query requests bypass public page caching.

## Development checks

```bash
composer validate --strict
composer test
composer lint
composer check-text
composer build-php-translations
composer build-translations
find . -path './vendor' -prune -o -name '*.php' -print0 | xargs -0 -n1 php -l
php tests/Performance/benchmark.php
```

## Rollback

1. Disable NPE runtime features under **NPE → Settings**.
2. Purge NPE cache entries from **NPE → Cache**.
3. Deactivate NPE.
4. Replace the plugin directory with the previously tested release.
5. Reactivate it. The version manager applies only forward migrations; restore a pre-upgrade database backup if a future release introduces a non-reversible schema migration.

Do not delete NPE data during rollback unless intentionally uninstalling. See [Troubleshooting](docs/troubleshooting.md), [Security](docs/security.md), and [Architecture](docs/architecture.md).

## Technical documentation

For a Persian, engineering-focused explanation of the plugin's architecture,
technology stack, runtime pipelines, security boundaries, operational features,
and current limitations, see the [complete technical overview](docs/technical-overview-fa.md).
