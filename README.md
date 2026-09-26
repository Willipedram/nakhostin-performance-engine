# Nakhostin Performance Engine

Nakhostin Performance Engine (NPE) is a modular WordPress/WooCommerce performance plugin authored by Seyed Pedram Nakhostin. It currently provides DOM and component intelligence, review-only CSS usage analysis, dependency-aware page-specific JavaScript planning, application-level page and fragment caching, smart purge and warmup queues, optional public-hook-only LiteSpeed cooperation, privacy-safe backend monitoring, and a Persian-first administration interface.

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
in a bounded queue. WP-Cron analyzes one page at a time after the visitor response;
account, cart, checkout, administration, and logged-in requests are excluded.

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
