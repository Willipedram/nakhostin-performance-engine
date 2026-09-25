# DOM Intelligence

## Scope

The DOM Intelligence subsystem turns an explicitly supplied rendered HTML page
into a compact, reusable DOM Usage Manifest. It does not rewrite markup, remove
CSS, optimize JavaScript, cache pages, or run on ordinary frontend requests.

Administrators can run an analysis from **NPE → DOM Intelligence**. The action
requires `manage_options`, a valid nonce, and a public HTTP(S) URL whose host
matches the current WordPress site. Fetches use the WordPress safe HTTP API with
a 10-second timeout, three-redirect limit, and 2 MiB response limit.

The action is now an orchestration entry point rather than a DOM-only task. It
performs a one-time, no-cache frontend capture and generates the DOM manifest,
detected component usage, same-origin CSS usage manifest, and JavaScript
dependency manifest together. The high-entropy capture token is single-use and
expires after five minutes. If loopback capture is unavailable, NPE falls back to
a bounded safe HTTP fetch so DOM, component, and CSS analysis can still complete.

## Pipeline

1. `DOMSnapshot` holds HTML and analysis context in memory and enforces the input
   limit. Snapshot HTML is never persisted.
2. `DOMAnalyzer` parses with `DOMDocument`, disables network access through
   `LIBXML_NONET`, and inventories tags, class names, safe IDs, attribute names,
   forms, buttons, links, scripts, and stylesheets.
3. `DOMComponentDetector` applies conservative semantic rules for headers,
   footers, navigation, mobile menus, commerce structures, and Elementor widgets.
4. `DOMStateRegistry` exposes the stable future runtime-state vocabulary and
   records states visible in static classes and selected ARIA/data attributes.
5. `DOMSignature` hashes a canonical structural projection. It intentionally
   excludes IDs, text, URLs, counts, timestamps, and common dynamic WordPress or
   Elementor instance classes so pages sharing a template can share a signature.
6. `DOMStorage` stores only the latest manifest in the non-autoloaded
   `npe_dom_manifest` option. No custom table is justified for a single compact
   diagnostic snapshot.

## Manifest shape

```json
{
  "manifest_version": 1,
  "page_type": "product",
  "template_identifier": "single-product",
  "dom_signature": "sha256…",
  "elements": ["body", "div", "form"],
  "classes": ["product", "variations"],
  "ids": ["reviews"],
  "attributes": ["class", "data-product_variations", "id"],
  "data_attributes": ["data-product_variations"],
  "body_classes": ["single-product", "woocommerce"],
  "forms": 1,
  "buttons": 0,
  "links": 0,
  "scripts": [],
  "stylesheets": [],
  "components": ["product-variations", "reviews", "woocommerce-components"],
  "elementor_widgets": [],
  "integrations": ["woocommerce"],
  "detected_states": [],
  "supported_states": ["menu-open", "modal-open", "loading", "active"],
  "source_url": "https://example.test/product/example",
  "generated_at": "2026-09-25T00:00:00+00:00",
  "versions": {"wordpress": "…", "theme": "…", "npe": "…"}
}
```

Arrays are deduplicated and sorted where order has no meaning. Counts represent
the analyzed static response, not future browser-created nodes.

## Privacy and safety

The manifest never includes text nodes, full HTML, form values, cookies, request
headers, inline script/style contents, or attribute values other than a narrow
set used transiently for state detection. Query strings, fragments, and URL
credentials are removed. Email-like, credential-like, long hash-like, and long
numeric IDs/classes are discarded; dynamic URL path segments are replaced.

Malformed HTML is handled using libxml's recovery behavior. External entity and
network resolution is not enabled. Consumers must still treat manifests as
untrusted data and escape all displayed values.

## Known boundaries

- Static analysis cannot discover JavaScript-only states or post-interaction DOM.
- Detection is heuristic and reports evidence; it does not activate or modify
  WooCommerce, Elementor, themes, or other plugins.
- Only the latest site manifest is retained. Per-template histories and scheduled
  learning are intentionally deferred.
- Shadow DOM, iframe documents, CSS pseudo-elements, and browser accessibility
  trees are outside this phase.
