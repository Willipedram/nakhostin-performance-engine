# CSS Intelligence

NPE now provides an explicit, review-only selector usage analyzer. An administrator
first captures the target page with DOM Intelligence and then submits the related
stylesheet on the CSS screen. The analyzer compares element, class, ID, and
attribute requirements with the latest DOM manifest and separates selectors into:

- matched selectors;
- possible unused selectors; and
- preserved dynamic or uncertain selectors.

Pseudo-classes, interaction states, and selectors containing common runtime state
names are preserved. The submitted stylesheet is bounded to one megabyte, processed
in memory, and never persisted. Only the compact manifest is stored.

General selector analysis intentionally does **not** remove, rewrite, or tree-shake CSS.
“Possible unused” is diagnostic evidence, not permission to delete a rule. A later
build phase may consume reviewed manifests and must retain the original stylesheet
whenever confidence is insufficient.

The normal workflow no longer requires copying CSS. **Analyze Page Assets**
automatically fetches up to 50 same-origin stylesheets with per-file and aggregate
size limits. Cross-origin or failed sources are skipped. The paste form remains
available only as an advanced diagnostic fallback.

## Font intelligence

Automatic page analysis inventories `@font-face` declarations from bounded,
same-origin stylesheets. The compact per-page manifest records family, weight,
style, format, absolute source URL, originating stylesheet, and whether the
analyzed page references that variant. Up to 100 page manifests are retained,
so **NPE → CSS** provides a site-wide map without storing stylesheet source.

Page-aware font loading is disabled by default. When explicitly enabled, NPE
acts only when the current anonymous public URL has a matching analysis. It may
dequeue a registered stylesheet only if that stylesheet contains nothing except
`@font-face` declarations, then reconstructs constrained CSS from required
HTTP(S) WOFF/WOFF2/TrueType/OpenType faces. Mixed stylesheets, cross-origin or
unavailable stylesheets, unknown pages, logged-in requests, and uncertain fonts
fail open and remain unchanged. Explicitly unused preload candidates are removed
only for the matching analyzed page.

Hard-coded `<link rel="preload">` tags printed directly by a theme or third-party
plugin are reported through their font URL but are not rewritten. They should be
removed at their source or through that product's public configuration.

NPE never modifies theme, Elementor, WooCommerce, or LiteSpeed files and never
deletes original fonts. Cache purges requeue known pages through DOM learning,
which refreshes their font decisions.
