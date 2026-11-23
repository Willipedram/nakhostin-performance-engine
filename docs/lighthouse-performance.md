# Lighthouse measurement and asset protection

## Audit findings

| Priority | Evidence | Risk and response |
| --- | --- | --- |
| High | The runtime bundle path dequeued source handles before checking the generated file and bypassed the ordinary stale-manifest policy. | Missing or stale files could break rendering or interactions. Bundle application now checks availability, size, ownership, freshness, capture completeness, and the live queue before replacing a suffix of that queue. |
| High | CSS bundling treated initial HTML selector presence as permission to strip other rules. | Responsive and dynamically inserted content could lose styles. Bundle generation now preserves rules and rejects stylesheet syntax that cannot be safely relocated by the existing URL rewriter. |
| High | Apache returned HTTP 403 for generated assets placed under the private cache directory, even though PHP could read them. | Serve generated files from a separate public asset location; the worker validates HTTP delivery before enabling replacement. |
| High | Cached HTML could outlive a local generated bundle. | A cached public response bypasses the frontend queue check. NPE does not store responses that use replacement bundles, and the format change invalidates older cached HTML in both NPE cache layers. |
| Medium | Stylesheet response limits could admit a truncated file, and a missing manifest was loaded again in a later hook. | Collection now rejects payloads exceeding its budget, and the runtime remembers a manifest miss for the rest of the request. |
| Medium | Font preloads were printed directly in the head. | WordPress now assembles and deduplicates the preload list by URL. This covers integrations using the WordPress preload filter. |
| Medium | Backend time, server DOM evidence, and JavaScript risk reports were available without real browser runs. | Laboratory Lighthouse reports are imported separately from PHP timing. The dashboard leaves INP unavailable until a real-user measurement source is provided. |

NPE already has page-scoped DOM learning, component registries for WooCommerce, Elementor, and WoodMart, a JavaScript dependency graph, LiteSpeed ownership resolution, bounded warmup and purge queues, fragment locks, early page cache, and optimization manifests. These remain the owners of their respective features. The current patch does not implement browser-derived responsive critical CSS, adaptive LCP image mutation, third-party script delays, field measurement collection, or a self-tuning apply-and-rollback loop. Full-page cold generation can still race before a cache entry exists. The background worker checks that the generated URL returns a successful HEAD response with the expected MIME type. It cannot detect a later server/CDN outage or prove the behavior of arbitrary scripts.

## Import a laboratory run

Open **NPE → Performance** and upload a Lighthouse JSON report as a baseline or candidate. The importer accepts up to 4 MiB from an authenticated administrator, extracts fixed numeric fields, and verifies the final URL is within this site's origin and path. It retains at most 30 reports. Raw audits, query strings, page contents, browser traces, and report uploads are not stored. The import performs no outbound network request. Estimated savings are Lighthouse estimates for the latest imported run and should not be added together. The dashboard compares the three most recently imported runs per phase for matching paths, profiles, Lighthouse versions, and configurations.

## Reproduce a comparison

Install a pinned Lighthouse CLI and Chrome or Chromium in a separate tool directory. Use the same browser, Lighthouse version, host, fixture, settings, and cache policy for both phases. Each phase writes three or more independent trials per profile. Use a fresh directory each time and run the phases serially.

```bash
export NPE_LIGHTHOUSE_BIN=/path/to/lighthouse/cli/index.js
export CHROME_PATH=/path/to/chromium
node tools/lighthouse-benchmark.mjs run https://example.test/page/ /tmp/npe-baseline
# Deploy the candidate to the same staging site without changing the fixture.
node tools/lighthouse-benchmark.mjs run https://example.test/page/ /tmp/npe-candidate
node tools/lighthouse-benchmark.mjs compare /tmp/npe-baseline /tmp/npe-candidate
```

Repeat separately for the home, product archive, category, product, cart, checkout, and Elementor pages on a representative staging site. Use browser interaction tests for carts, payments, variations, galleries, popups, menus and filters. A navigation audit does not measure field INP or real-user p75.

## Local laboratory results

Lighthouse 13.6.0 and Chromium ran against the same published block-theme page on a local WordPress instance. The baseline used commit `3196b39`. Each cell is the median of three trials with default plugin settings, for 12 runs in total.

| Profile | Score | LCP, ms | FCP, ms | Speed Index, ms | TBT, ms | CLS |
| --- | --- | --- | --- | --- | --- | --- |
| Mobile | 100 → 100 | 1357 → 1360 | 639 → 643 | 639 → 643 | 0 → 0 | 0 → 0 |
| Desktop | 100 → 100 | 369 → 327 | 175 → 174 | 200 → 195 | 0 → 0 | 0 → 0 |

The local fixture had no WooCommerce, WoodMart, Elementor, or external cache installed. The run measures default-mode behavior on that fixture and does not establish a performance gain or production scores. Field measurements and representative commerce compatibility require a suitable staging site.

## Rollback

Disable page-specific bundles in **NPE → Settings → Intelligence** to restore direct WordPress asset delivery. Switch off frontend optimization to remove the remaining runtime transformations. Purge NPE and any external page caches that may contain earlier HTML. This release uses optimization manifest format 4 and page cache format 5, so the two layers rebuild incompatible records from live requests and analysis.
