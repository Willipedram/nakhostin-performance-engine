# Smart Purge and Cache Warmup

## Goals and safety

Smart Purge invalidates only cache entries whose declared dependencies are affected by a content change. Publishing requests enqueue compact jobs and do not perform filesystem scans, remote warmup requests, or transitive graph traversal inline. WP-Cron workers process bounded batches. Queue fingerprints coalesce duplicate pending/running jobs, retries stop after three attempts, stale running claims are recovered, and worker re-entry guards prevent loops.

## Dependency graph

`CacheDependencyGraph` stores a bounded directed graph in `npe_cache_dependency_graph`. An edge points from changed data to a dependent cache surface. Supported nodes include:

- `product:{id}` → product page, product categories, shop page type, related-product component, and optionally homepage;
- `taxonomy:{taxonomy}:{term}` → taxonomy archive and product-listing page type;
- `component:{id}` → page types recorded by Component Intelligence;
- `asset:{handle}` → generated bundle nodes;
- `page:{id}`, `page_type:{type}`, and `bundle:{id}`.

Resolution is transitive, cycle-safe, and bounded. Nodes carry cache tags, purge URLs, and warmup URLs. Page-cache responses also record current post, product, category, page-type, and integration-supplied tags through `CacheDependencyCollector`.

## Purge levels

`PurgeRequest` validates the levels `url`, `page`, `component`, `product`, `taxonomy`, `page_type`, `asset`, and `full`. URL purges target one normalized URL. Other scoped requests resolve graph relationships and invalidate matching page-cache URLs/tags, fragment dependencies, and bundle-index entries. Full invalidation is reserved for explicit administration, cache-setting changes, theme switches, and plugin/theme upgrades; routine content events do not flush everything.

## Events

The event subscriber covers post save/trash/delete, edited terms, navigation changes, WooCommerce product updates, stock changes, variation stock changes, relevant price/stock property updates, component definition changes, bundle indexing, explicit asset-change events, theme switches, upgrades, and cache-setting changes. Autosaves and revisions are ignored. Integrations may publish `npe/cache/asset_changed` with an asset handle and dependent bundle IDs.

## Queue and warmup

Purge and warmup queues use separate non-autoloaded options and short atomic option locks. Purge jobs run in batches of five; warmup jobs run in batches of three. Warmup accepts only normalized same-origin URLs and uses `wp_safe_remote_get`. Targets may include the changed URL, shop, affected categories, affected products, homepage when applicable, and configured important product/category IDs. Remote requests never run in the publishing request.

Cache Overview displays queue counts and recent jobs, graph node/relationship counts, last purge reason, last warmup result, and failed jobs. Operational state contains public URLs and error summaries only.

## Limitations

- WP-Cron requires site traffic unless a real system cron invokes it.
- Dependency accuracy depends on cache producers declaring all component/asset relationships.
- The option-backed graph and queues are intentionally bounded for a single-site plugin workload; very large installations may need a future database or external-queue adapter.
- Full invalidation remains available for explicit operations and changes that can affect every rendered template, but is not used for routine product, post, term, component, or asset changes.
- Warmup is intentionally low-concurrency and does not replace an external crawler or edge-cache preloader.
