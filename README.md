# Nakhostin Performance Engine

Nakhostin Performance Engine (NPE) is a modular WordPress/WooCommerce performance plugin authored by Seyed Pedram Nakhostin. It currently provides DOM and component intelligence, dependency-aware JavaScript planning, application-level page and fragment caching, smart purge and warmup queues, optional public-hook-only LiteSpeed cooperation, privacy-safe backend monitoring, and a Persian-first administration interface.

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

For a Persian-capable release package, run `composer build-translations` before creating the ZIP, then copy `build/languages/*.mo` into the ZIP's plugin `languages` directory. Compiled files never enter the source tree tracked by Git.

## Safe defaults

NPE does not automatically enable page caching, JavaScript changes, performance sampling, or logging. It does not modify LiteSpeed Cache configuration. Private, authenticated, cart, checkout, account, preview, REST, AJAX, session-bearing, and sensitive-query requests bypass public page caching.

## Development checks

```bash
composer validate --strict
composer test
composer lint
composer check-text
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
