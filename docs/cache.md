# Cache

NPE provides an independent application-level full-page cache, fragment cache, object-cache adapter, dependency-aware invalidation, and asynchronous warmup. Detailed design documents are [Full Page Cache](page-cache.md), [Fragment and Object Cache](fragment-object-cache.md), and [Smart Purge and Warmup](smart-purge-warmup.md).

Public page caching is opt-in. Requests bypass cache for authenticated/admin/login/preview/AJAX/REST/cart/checkout/account contexts, authorization headers, private WordPress or commerce cookies, foreign host/port values, unsafe methods, sensitive or unapproved query parameters, and excluded paths. Responses bypass storage when they set cookies, are private/no-store, are non-HTML, include password/nonce/admin-toolbar markers, are personalized, empty, or non-200.

Filesystem records use SHA-256 paths, content hashes, atomic replacement, lock files, PHP exit prefixes, `index.php`, and Apache denial rules. Missing, corrupt, unguarded, or unwritable storage is treated as a miss so the original WordPress response remains available.
