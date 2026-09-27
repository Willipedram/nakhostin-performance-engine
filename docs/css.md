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

This release intentionally does **not** remove, rewrite, tree-shake, or serve CSS.
“Possible unused” is diagnostic evidence, not permission to delete a rule. A later
build phase may consume reviewed manifests and must retain the original stylesheet
whenever confidence is insufficient.

The normal workflow no longer requires copying CSS. **Analyze Page Assets**
automatically fetches up to 50 same-origin stylesheets with per-file and aggregate
size limits. Cross-origin or failed sources are skipped. The paste form remains
available only as an advanced diagnostic fallback.
