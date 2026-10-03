# Full Page Cache

## Scope and execution model

NPE's page cache is server-agnostic. It does not require LiteSpeed Cache, Redis,
or a particular web server. Its application fallback runs at an early
`template_redirect` priority. Version 1.9 additionally provides an explicitly
enabled `advanced-cache.php` provider whose safe filesystem hits terminate before
normal plugin and theme rendering.

Dynamic ownership selects capable LiteSpeed page cache, a foreign drop-in, NPE
Early Cache, or NPE application cache without modifying other cache systems. See
[Dynamic platform detection and early page cache](early-page-cache.md).

## Pipeline

1. `CacheRequestFactory` creates a bounded request model. Cookie values, authorization values, and other secrets are never persisted.
2. `CachePolicy` rejects unsafe methods, private WordPress contexts, logged-in users, previews, REST/AJAX requests, WooCommerce cart/checkout/account requests, configured paths, authorization, and known session cookies.
3. `QueryPolicy` drops only known analytics parameters. Every other query parameter is either explicitly allowlisted and included in the key or causes a bypass.
4. `URLNormalizer` and `CacheKeyGenerator` create a SHA-256 key from canonical URL, site, language, and only configured device/currency/variation dimensions.
5. `PageCache` reads or atomically writes a versioned `CacheEntry`. Stale entries may be served during the short stale window while a warmup hook is scheduled.
6. `CacheHeaderManager` emits bounded cache status, age, and cache-control headers.

The cache is disabled by default. It never stores responses which set cookies, declare `private`/`no-store`, are personalized, are non-HTML, or do not have status 200.

## Storage and metadata

`PageCacheStoreInterface` is the backend boundary. `PageCacheStoreFactory` always
uses deterministic filesystem storage for the early provider; application mode
may use a persistent WordPress object cache when healthy and otherwise falls back
to filesystem. `FilesystemCacheStore` uses hashed paths, per-entry locks,
temporary files, atomic rename, access-denial rules, and a PHP exit guard. Each
JSON entry contains cache format version, normalized source URL, timestamps,
expiry and stale limits, structural signature, dependency tags, safe response
headers, HTML, and an HTML content hash. Invalid JSON, wrong keys, unsupported
versions, and hash mismatches are misses. Full HTML is stored only for pages
already classified public.

There is no custom database table. Lightweight hit/miss counters are kept in a locked file so a cache hit does not introduce a WordPress options write.

## Invalidation and warming

Invalidation supports all entries, normalized URLs, and dependency tags. URL and tag purges scan filesystem metadata; a future indexed backend may implement these methods more efficiently. The admin rebuild action remembers cached public URLs, purges, and schedules a capped, same-origin WordPress cron warmup. Warmup uses `wp_safe_remote_get` and is a hook foundation rather than an unbounded crawler.

## Configuration

The `cache` settings group supports `enabled` and `early_cache` (both default
`false`), `ttl` (30–86,400 seconds), `stale_ttl` (0–3,600 seconds),
`allowed_query_parameters`, `excluded_paths`, `vary_device`, `vary_currency`, and
`variation_dimensions`. Dimensions must be enabled only when response content
actually varies to avoid duplicate entries.

## Known limitations

- Application fallback hits still incur WordPress bootstrap; only an installed and active early provider avoids it.
- NPE does not edit `wp-config.php`; the operator must enable `WP_CACHE` before using the drop-in.
- Early cache uses filesystem, not Redis/Memcached, so its hit path remains deterministic before object-cache bootstrap.
- Anonymous pages containing application-specific personalization must be excluded or marked personalized by the calling integration.
- Warmup relies on WordPress cron traffic and loopback HTTP availability.

## Complete-response guarantee

The application cache may receive several output-buffer callbacks when a theme or plugin flushes HTML. NPE accumulates those chunks but writes exactly once, only during `PHP_OUTPUT_HANDLER_FINAL`. A response must contain a recognizable HTML document and a closing `body` or `html` tag before it can enter page cache.

Cache format version 2 invalidates earlier records that may have been created from an intermediate chunk. Both application and early readers reject incomplete or corrupt records and fail open to normal WordPress rendering. Consequently a new page is rendered normally on its first request and cannot be replaced by a cached blank shell.
