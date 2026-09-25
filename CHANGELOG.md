# Changelog

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
