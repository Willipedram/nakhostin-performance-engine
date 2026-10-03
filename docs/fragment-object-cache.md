# Fragment Cache and Object Cache Integration

## Purpose

The fragment cache reduces repeated backend work on pages that cannot safely use full-page caching. It is an explicit API: NPE does not automatically cache arbitrary template output or customer state. Suitable public examples include product cards, navigation, expensive Elementor widgets, and custom PLP components. Customer-specific fragments, such as a mini-cart shell containing customer data, must use private visibility and an opaque scope.

## API and privacy model

`FragmentCacheInterface` exposes `get`, `put`, `delete`, dependency invalidation, and stampede-protected `remember` operations. `FragmentKey` includes the sanitized component name, an explicit version, sorted scalar dimensions, visibility, and—for private fragments—a one-way hash of the required opaque scope. Raw customer identifiers and sensitive dimensions such as tokens, passwords, nonces, or email addresses are not placed in keys or metadata.

Entries accept scalar, array, or null values, and contain creation/expiration timestamps, dependency tags, visibility, version, and an integrity hash. Unsupported objects and resources are not stored.

## Dependency invalidation

Fragments declare bounded tags such as `product-123`, `post-123`, `product-category-7`, `menu-2`, or `component-product-card`. Invalidation removes only entries matching changed dependencies. WordPress hooks invalidate post/product, WooCommerce product-category, navigation-menu, and custom component dependencies. NPE never flushes the entire WordPress object cache.

The WooCommerce policy helper creates public product-fragment keys and conventional product/category dependencies. Customer fragments require private keys. It intentionally does not cache carts, sessions, prices personalized per customer, checkout state, account data, or mutable customer objects by default.

## Backends

`FragmentStoreInterface` isolates storage. `FragmentStoreFactory` selects the public WordPress object-cache API only when WordPress reports an external persistent object cache. Redis, Memcached, and other implementations work through `wp_cache_*`; NPE does not require or call a particular plugin's private classes. If persistence is unavailable, NPE uses its atomic filesystem backend.

The filesystem backend uses content hashes, PHP exit guards, atomic rename, and non-blocking file locks. The WordPress backend uses a dedicated `npe_fragments` group, `wp_cache_add` locks, targeted deletes, and a small option index solely for dependency discovery. It never calls `wp_cache_flush`.

## Concurrency and statistics

`remember` performs a second lookup after a lock collision and returns a `locked` result rather than duplicating expensive work. Filesystem locks use `flock`; persistent object-cache locks use atomic `wp_cache_add`. The Cache Overview reports persistent status, detected backend, fragment backend, entries, hit ratio, and top fragments.

## Limitations

- The object-cache index uses a WordPress option and is designed for a bounded number of reusable fragments, not millions of per-customer keys.
- Private fragment scopes are caller-provided; integrations must use non-personal opaque values and appropriate TTLs.
- Redis/Memcached server health and eviction policy remain the responsibility of the installed object-cache drop-in.
- Filesystem fallback still invokes PHP and WordPress; it is not a server-side edge cache.
- Fragment invalidation is tag-based. Integrations must declare all data dependencies used to render their fragment.
