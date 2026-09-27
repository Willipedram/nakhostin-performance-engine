## 2.2.0

- Fixed automatic DOM learning startup by seeding the homepage, immediately scheduling WP-Cron, and dispatching the worker non-blockingly after visitor responses.
- Changed visit sampling so eligible URLs can be learned over time instead of being excluded for an entire day.
- Added lost-schedule recovery, WP-Cron health visibility, queue error details, and capability-protected manual start/process controls.

## 2.1.1

- Added sampled WordPress lifecycle and aggregate database timing diagnostics.
- Added opt-in debug hook profiling, object-cache, server, PHP, compression, browser-cache, security-header, DOM-complexity, LiteSpeed, and optimization-conflict health checks.
- Added evidence-based site-health scorecards and sanitized support exports.
- Added an explicit, same-origin, three-second LiteSpeed Guest Vary latency probe with 403 and invalid-response diagnostics.

# Changelog

## 2.0.0 — Dynamic frontend optimization engine

- Added unified per-page optimization manifests generated only during explicit or
  queued page analysis, never through expensive parsing on ordinary frontend requests.
- Added conservative critical CSS, dynamic selector preservation, high-confidence
  asset decisions, dependency-safe native script defer, and capped font preloads.
- Added feature-specific ownership for NPE, LiteSpeed, and known external optimizers
  instead of globally disabling NPE whenever LiteSpeed is present.
- Added safe mode, protected WooCommerce contexts, stale/missing-manifest fallback,
  targeted/global invalidation, page decision diagnostics, and opt-in controls.
- JavaScript delay remains experimental and allowlist-only; images and media remain
  outside the optimization pipeline.

## 1.9.0 — Dynamic platform detection and safe early cache

- Added a comprehensive Persian technical overview covering the architecture,
  technology stack, runtime pipelines, security model, integrations, fail-open
  behavior, operational capabilities, and explicit limitations of NPE.
- Added immutable runtime capability reporting for web servers, reverse proxies,
  compression, WordPress drop-ins, Redis, Memcached, and persistent object cache.
- Added provider-based page-cache ownership resolution without coupling core cache
  decisions to LiteSpeed implementation classes.
- Added an opt-in, filesystem-backed `advanced-cache.php` runtime that can serve
  verified public hits before normal plugin and theme rendering.
- Added atomic ownership-safe drop-in installation; unknown and third-party
  `advanced-cache.php` files are never overwritten or removed.
- Added dynamic application page-store selection and unified cache diagnostic
  headers and health reporting.
- Preserved fail-closed WooCommerce, authenticated, session, authorization,
  sensitive-query, and unsafe-method bypass behavior.

## 1.8.0 — Native WordPress settings experience

- Rebuilt the settings screen with WordPress nav tabs, buttons, notices, inputs, Dashicons, responsive cards, a sticky save action, and an at-a-glance configuration summary.
- Added progressive enhancement, keyboard tab navigation, RTL-safe technical fields, mobile layouts, and settings-only asset loading.

## 1.7.0 — Page-aware font intelligence

- Discover font families, weights, styles, formats, source URLs, and per-page requirements during automatic analysis.
- Add an opt-in conservative runtime optimizer that replaces only analyzed font-only stylesheets and removes unused preload candidates while leaving mixed or unknown CSS unchanged.

## 1.6.0 — Fast managed DOM scans

- Process automatic DOM scans in short bounded batches and report progress, completion totals, last completion time, and estimated remaining time.
- Retain a bounded list of discovered public pages and requeue affected pages after NPE or public LiteSpeed purge signals without creating purge loops.

## 1.5.0 — Gradual automatic DOM learning

- Added a bounded, deduplicated WP-Cron queue that learns from sampled anonymous public page requests without delaying visitors.
- Added same-origin normalization, sensitive-route exclusions, cooldowns, retry backoff, failed-job visibility, and conservative production controls.

## 1.4.0 — Automated page intelligence

- Replaced separate manual DOM/CSS/JavaScript diagnostics with a one-click, one-time frontend page asset capture and safe fallback.

## 1.3.1 — Persian runtime localization

- Added a deployable text-based Persian runtime catalog so translated administration strings load without committing a binary MO file.

## 1.3.0 — Asset usage intelligence and Persian guidance

- Added review-only CSS selector usage intelligence backed by DOM manifests and page-specific JavaScript requirement reporting.
- Added Persian explanations for analysis decisions and the operational effect of every settings control.

## 1.2.1 — Text-only source packaging

- Removed the compiled binary MO catalog from Git to support text-only review and patch transports.
- Added deterministic translation compilation for distributable packages.
- Classified SVG, PO, POT, and source formats as text and added a repository binary-file guard.
- Documented secure SVG and release-artifact policy.
- Corrected the administration module-availability panel so implemented and future modules are clearly distinguished.

## 1.2.0 — Release hardening

- Harden public cache classification for additional WordPress/WooCommerce sessions, sensitive query parameters, admin-toolbar markup, and host-port mismatches.
- Require executable cache records to carry an immediate PHP exit guard and add directory access protections.
- Constrain generated JavaScript output to a configured trusted directory, reject PHP payload markers, and protect generated directories.
- Make application-level cache lookup and storage fail open if an unexpected backend error occurs.
- Add production installation, rollback, security, troubleshooting, compatibility, and overhead documentation.
- Add cache privacy, filesystem, generated asset, and release regression tests.

## 1.1.0 — Administration interface

- Added the Persian-first responsive administration dashboard, complete navigation, RTL isolation, accessibility improvements, and Persian translation catalog.

## 1.0.0 — Performance monitoring

- Added bounded, privacy-safe backend performance sampling and statistical diagnostics.

Earlier development history remains available in `readme.txt` and the Git history.
