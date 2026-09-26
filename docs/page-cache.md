# Full Page Cache

## Scope and execution model

NPE's page cache is an **independent application-level cache**. It does not require LiteSpeed Cache, Redis, or a particular web server. When enabled, lookup runs at an early `template_redirect` priority and a hit avoids theme template rendering, but a normal WordPress plugin loads after WordPress bootstrap. NPE therefore does not claim server-level or drop-in-level TTFB on generic hosting.

`ServerCacheAdapterInterface` is the explicit future boundary for documented Apache, Nginx, or other early-cache integrations. This phase ships no server adapter and contains no LiteSpeed-specific code.

## Pipeline

1. `CacheRequestFactory` creates a bounded request model. Cookie values, authorization values, and other secrets are never persisted.
2. `CachePolicy` rejects unsafe methods, private WordPress contexts, logged-in users, previews, REST/AJAX requests, WooCommerce cart/checkout/account requests, configured paths, authorization, and known session cookies.
3. `QueryPolicy` drops only known analytics parameters. Every other query parameter is either explicitly allowlisted and included in the key or causes a bypass.
4. `URLNormalizer` and `CacheKeyGenerator` create a SHA-256 key from canonical URL, site, language, and only configured device/currency/variation dimensions.
5. `PageCache` reads or atomically writes a versioned `CacheEntry`. Stale entries may be served during the short stale window while a warmup hook is scheduled.
6. `CacheHeaderManager` emits bounded cache status, age, and cache-control headers.

The cache is disabled by default. It never stores responses which set cookies, declare `private`/`no-store`, are personalized, are non-HTML, or do not have status 200.

## Storage and metadata

`PageCacheStoreInterface` is the backend boundary for filesystem, Redis, WordPress object-cache, or external implementations. The included `FilesystemCacheStore` uses hashed paths, per-entry locks, temporary files, atomic rename, access-denial rules, and a PHP exit guard. Each JSON entry contains cache format version, normalized source URL, timestamps, expiry and stale limits, structural signature, dependency tags, safe response headers, HTML, and an HTML content hash. Invalid JSON, wrong keys, unsupported versions, and hash mismatches are misses and are removed. Full HTML is stored only for pages already classified public.

There is no custom database table. Lightweight hit/miss counters are kept in a locked file so a cache hit does not introduce a WordPress options write.

## Invalidation and warming

Invalidation supports all entries, normalized URLs, and dependency tags. URL and tag purges scan filesystem metadata; a future indexed backend may implement these methods more efficiently. The admin rebuild action remembers cached public URLs, purges, and schedules a capped, same-origin WordPress cron warmup. Warmup uses `wp_safe_remote_get` and is a hook foundation rather than an unbounded crawler.

## Configuration

The `cache` settings group supports `enabled` (default `false`), `ttl` (30–86,400 seconds), `stale_ttl` (0–3,600 seconds), `allowed_query_parameters`, `excluded_paths`, `vary_device`, `vary_currency`, and `variation_dimensions`. Dimensions must be enabled only when response content actually varies to avoid duplicate entries.

## Known limitations

- Application-level hits still incur WordPress bootstrap.
- No server rewrite, advanced-cache drop-in, Redis backend, object-cache backend, or LiteSpeed adapter is included in this phase.
- Anonymous pages containing application-specific personalization must be excluded or marked personalized by the calling integration.
- Warmup relies on WordPress cron traffic and loopback HTTP availability.
