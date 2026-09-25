# LiteSpeed Cache Integration

## Scope

The LiteSpeed integration is optional and uses only LiteSpeed Cache for WordPress public action hooks. NPE does not instantiate LiteSpeed classes, inspect or mutate LiteSpeed settings, modify plugin files, disable LiteSpeed features, or assume a LiteSpeed server exists. Removing or deactivating LiteSpeed Cache leaves NPE's independent cache, fragment, purge, and warmup services operational.

## Detection

`LiteSpeedDetector` distinguishes four facts:

- **installed** — the standard plugin entry exists or the plugin is active;
- **active** — `LSCWP_V`, the public `litespeed_init` hook, or WordPress active-plugin options indicate activation;
- **server** — the server identifies as LiteSpeed through public environment signals;
- **cache capable** — active plus a detected LiteSpeed server, filterable through `npe/litespeed/cache_capable` for documented external/QUIC.cloud deployments that PHP cannot infer.

An installed but inactive plugin is never called. Detection does not load LiteSpeed files.

## Modes and ownership

| Mode | NPE page cache | LiteSpeed public page cache | Scoped purge forwarding | NPE CSS/JS runtime optimization |
| --- | --- | --- | --- | --- |
| Independent | Owned by NPE when enabled | Not coordinated by NPE | No | Allowed |
| Compatible (default) | Disabled when LiteSpeed is active and cache-capable | Preferred owner | No; LiteSpeed's native WordPress invalidation remains responsible | Suppressed to prevent duplicate work |
| Cooperative | Disabled when LiteSpeed is active and cache-capable | Preferred owner | Yes | Suppressed to prevent duplicate work |

If LiteSpeed is absent, inactive, or not cache-capable, NPE remains the page-cache owner. Independent mode is an explicit operator choice and causes NPE to emit no LiteSpeed hooks. Compatible mode is conservative: it prevents competing page cache and optimization layers but does not add NPE purge signals. Cooperative mode must be explicitly selected and adds scoped NPE tags and purge forwarding.

## Public hooks

The bridge emits LiteSpeed's public hooks:

- `litespeed_control_set_nocache` for requests NPE classifies as private or unsafe;
- `litespeed_control_set_cacheable` and `litespeed_control_set_ttl` only in Cooperative mode for safely classified public requests;
- `litespeed_tag_add` for `npe-*` dependency tags;
- `litespeed_vary_add` for explicitly configured device/currency dimensions;
- `litespeed_purge_url` for scoped URLs;
- `litespeed_purge_tag` for matching `npe-*` dependency tags;
- `litespeed_purge_all` only when NPE is genuinely executing a full-cache purge.

NPE does not call undocumented LiteSpeed PHP classes or methods. Tags added during Cooperative requests use the same prefix as purge tags, so product, taxonomy, page-type, component, and asset invalidation remains scoped.

## Cacheability and privacy

NPE always communicates a no-cache decision for logged-in, administrative, login, preview, REST, AJAX, cart, checkout, account, authorization-bearing, foreign-host, or known-session requests. Compatible mode does not force public cacheability. Cooperative mode can mark requests public only after the existing conservative NPE request policy accepts them. LiteSpeed may still apply its own stricter policy.

## Optimization conflict prevention

When Compatible or Cooperative mode is active, the adapter returns `false` through NPE's CSS and JavaScript runtime-optimization gates. This does not change LiteSpeed configuration. NPE analysis and diagnostics remain available, while automatic NPE minification/bundling must stay pass-through. Independent mode leaves NPE gates unchanged.

## Purge cooperation

The purge bridge listens to NPE's normalized `npe/cache/purged` event. URL and dependency purges are forwarded individually. A full LiteSpeed purge is never synthesized from a product, post, category, component, page-type, or asset event; it is forwarded only from a real NPE full purge.

## Fallback and limitations

- LiteSpeed Cache may operate through infrastructure PHP cannot identify. Operators may use the capability filter after confirming their deployment.
- Compatible mode relies on LiteSpeed's native invalidation for WordPress content. Select Cooperative mode when custom NPE components/assets require NPE dependency tags and purge signals.
- NPE never reads LiteSpeed optimization settings, so it conservatively suppresses future NPE runtime CSS/JS optimization whenever compatibility safeguards are active.
- Vary dimensions are emitted only when explicitly configured and present in NPE's bounded request context.
- This layer does not configure server rewrites, QUIC.cloud, crawler behavior, guest mode, ESI, or LiteSpeed object cache.
- NPE never forwards cookies, authorization values, query secrets, or cached response bodies through LiteSpeed hooks. Cooperative purge messages contain only normalized URLs or sanitized NPE dependency tags.
