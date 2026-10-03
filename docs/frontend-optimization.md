# Dynamic frontend optimization

NPE 2.0 converts the existing DOM, component, CSS, JavaScript, and font analysis
results into an explicitly enabled runtime. It remains server-agnostic and does
not require LiteSpeed, Apache directives, Nginx directives, Redis, or a CDN.

## Precomputed pipeline

The existing page-analysis queue fetches and analyzes a bounded public page. After
DOM/component, CSS, JavaScript, and font analysis completes, the optimization
manifest builder creates a per-URL record containing a stable page/DOM signature,
generation time, stored critical CSS, the approved JavaScript manifest, selective
font preloads, component IDs, conservative asset decisions, and a decision summary.
No stylesheet parsing or dependency planning runs during an ordinary frontend
request.

The runtime requires a fresh matching manifest. Missing, malformed, or stale data
means KEEP. Logged-in, admin, cart, checkout, and account contexts bypass every
runtime change.

## Page-specific asset evidence

WordPress's global script registry is not a list of assets used by the current
page. The analysis worker therefore keeps global registration counts as
diagnostics and limits actionable decisions to page-relevant handles: assets
observed in rendered markup, handles actually enqueued for the capture, direct
page requirements, detected-component requirements, protected handles, explicit
absence candidates, and the transitive dependencies of retained handles. Merely
being globally registered never creates an actionable `keep / 0%` row.

Capture completeness is explicit in manifest schema version 3. A capture is
complete only when the bounded document has a trustworthy end and all required
analysis stages complete. Failed, partial, truncated, or ambiguous captures may
record observations but cannot infer absence. Explicit absence is actionable only
for a known page candidate in a complete capture; it does not mean "registered
globally but not seen." Unknown and incomplete states always remain KEEP.

Decision reasons describe the evidence rather than an optimistic guess:
`required-by-page`, `required-by-component`, `required-by-dependency`,
`observed-on-rendered-page`, `protected-by-safety-policy`, and
`explicitly-absent-from-complete-capture`. Incomplete or missing dependency
evidence uses a fail-open reason and zero confidence. Confidence 100 is reserved
for direct, complete evidence; it is not synthesized from heuristics.

Dependency closure is computed by the worker across every direct and transitive
edge. Diamond graphs are deduplicated, cycles terminate deterministically, and a
missing dependency node makes the affected unload decision uncertain. Thus an
asset cannot unload while any retained page asset depends on it at any depth.

## CSS

Critical CSS is generated from selectors already classified as observed by DOM
analysis and capped at 50 KB. It is stored in the unified manifest and may be
inlined when enabled. Dynamic selectors and pseudo states such as hover, focus,
active, checked, before/after, `is-*`, `has-*`, WooCommerce, Elementor, and
WoodMart selectors are preserved. An unused candidate is never removed solely
because one HTML snapshot did not contain it.

Stylesheet decisions support KEEP, DEFER, INLINE_CRITICAL, UNLOAD, and UNKNOWN.
Safe mode converts uncertain or unload decisions to KEEP. Runtime unloading
requires an explicit high-confidence precomputed decision.

## JavaScript

Queued analysis requests dependency-safe defer for observed page handles. The
existing safety policy rejects critical commerce, payment, authentication,
challenge, consent, navigation, accessibility, inline/localized, external, module,
or otherwise unsafe scripts. A dependency is not deferred when an enqueued
non-deferred dependent would violate ordering. Runtime uses WordPress's native
`strategy=defer` data and only applies decisions from the fresh manifest.

JavaScript delay is experimental, disabled by default, and allowlist-only. The
planner rejects commerce, payment, authentication, consent, captcha, cart,
navigation, and accessibility handles. No aggressive event interception is
enabled automatically.

## Assets and fonts

Unknown asset usage always results in KEEP. Outside safe mode, unloading still
requires explicit absence evidence, confidence of at least 90, and a dependency
graph showing no required dependent. Font planning reads the existing font
manifest, prefers WOFF2, selects only required faces, and caps preloads at two.

## Feature ownership

Ownership is resolved separately for CSS, JavaScript, fonts, and assets. A
capable LiteSpeed page cache does not imply ownership of any frontend feature.
LiteSpeed frontend ownership is accepted only through a feature-specific public
filter signal; known external optimizers can own CSS/JS while NPE retains font
work. This avoids duplicate processing without globally disabling NPE.

## Invalidation and rollback

Optimization manifests are invalidated on theme switch/update, plugin
activation/deactivation/update, NPE settings changes, cache purge, Elementor save,
WoodMart option changes, and relevant post/product updates. Disable
`optimization.enabled` for immediate rollback; originals remain registered and
available. Individual critical CSS, defer, unload, delay, and preload switches are
independent and aggressive controls default to off.

NPE does not rewrite, resize, transcode, preload, lazy-load, or otherwise optimize
images, video, audio, or other media in this phase.

## Page-specific CSS and JavaScript bundles

When **Build and serve page-specific CSS and JavaScript bundles** is enabled and Safe Mode is disabled, the asynchronous page-analysis worker may write content-addressed bundles under `wp-content/cache/nakhostin-performance-engine/assets/`. Normal frontend requests only read the saved manifest; they never parse source files.

A CSS bundle is published only when every stylesheet observed in the rendered page was fetched from the same origin. Rules classified as required or dynamic are retained in source order, at-rules are preserved, and relative asset URLs are rewritten. If any stylesheet is unknown, external, unavailable, or truncated, NPE keeps the original stylesheets.

A JavaScript bundle contains only local classic scripts with trusted source content and no inline/localized data. Dependency order is retained. jQuery handles (including distinct versions), modules, external scripts, protected WooCommerce scripts, and any handle needed by a retained script remain separate. This boundary is intentional: removing arbitrary functions from a JavaScript library cannot be proven safe from a server-side DOM snapshot.

Runtime replacement is fail-open. Missing/stale manifests, Safe Mode, logged-in users, cart, checkout, account pages, provider conflicts, or incomplete analysis retain the original WordPress handles.

Bundle diagnostics pair `bundle_bytes` with a bounded status: `disabled`,
`safe-mode`, `incomplete-source-capture`, `no-eligible-assets`,
`external-or-unsafe-assets`, `source-unavailable`, `writer-failed`, or
`generated`. Zero bytes is therefore not presented as a silent failure. Source
fetching, graph traversal, parsing, and bundle writing remain worker-only;
ordinary requests only validate and apply the precomputed manifest.


## Font rendering

Generated CSS bundles normalize missing, `auto`, and `block` `font-display` declarations to `swap`, preventing invisible text while first-party fonts download. Existing `fallback` and `optional` declarations are respected. When page-aware font replacement is explicitly enabled outside Safe Mode, its reconstructed `@font-face` rules use the same policy and only required faces are emitted. NPE does not guess font metric overrides because incorrect ascent, descent, or size-adjust values can introduce layout shift.
