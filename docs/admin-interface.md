# Administration Interface

## Settings experience

The settings screen uses WordPress-native navigation tabs, buttons, notices,
form controls, and Dashicons. Sections are grouped into General, Intelligence,
Cache, Integrations, and Monitoring views. JavaScript progressively enhances the
screen: without JavaScript every section remains visible and the standard
WordPress settings form remains fully usable.

Tabs expose ARIA selection state and support Left/Right arrow navigation. A
sticky WordPress primary action keeps Save Settings reachable on long screens,
while the responsive configuration summary reports enabled and disabled runtime
features using both text and icons. Technical inputs remain LTR within Persian
RTL layouts. The settings JavaScript is enqueued only on the NPE settings screen.

## Information architecture

NPE registers one WordPress administration menu with ordered sections for Overview, Cache, DOM Intelligence, CSS, JavaScript, Components, WooCommerce, Elementor, LiteSpeed, Performance, Diagnostics, Settings, and Logs. Implemented modules expose their existing controls; reserved or integration sections clearly state whether they are enabled, disabled, or planned and never imply unfinished optimization is active.

The Overview and DOM Intelligence screens expose **Analyze Page Assets** as the
primary operational workflow. A single action refreshes DOM, component, CSS, and
JavaScript manifests from a one-time public-page capture. Separate CSS input is
kept only as an explicitly labelled advanced fallback.

The Overview reads existing bounded metrics and manifests. It does not run analysis, purge caches, or collect additional data merely because an administrator opens the page.

## Localization

English source strings use the `nakhostin-performance-engine` text domain. The
POT template, Persian PO source, and generated `fa_IR.l10n.php` runtime catalog
are committed as UTF-8 text. Modern WordPress loads the PHP catalog without a
binary file; release packaging additionally stages an MO fallback for WordPress
6.4. Persian terminology favors established, natural administration language,
including «برخورد موفق با کش», «عدم وجود در کش», «پیش‌گرم‌سازی کش»,
«پاک‌سازی هوشمند», «مانیفست ساختار DOM», and «بسته منابع».

## RTL and technical values

All screens derive their `dir` value from WordPress. Base CSS uses logical properties and grid/flex layouts. A small RTL-only stylesheet changes direction and alignment, while URLs, hashes, versions, code, and numeric technical values use `.npe-technical`, `dir="ltr"`, and Unicode isolation. Tables become horizontally scrollable on narrow screens rather than overflowing the viewport.

## Accessibility

- Navigation uses native WordPress menu APIs and remains keyboard accessible.
- Dashboard summaries use semantic headings and list/list-item roles.
- Focus-visible outlines are explicit.
- Status indicators combine text with symbols; color is never the sole signal.
- Form controls retain labels, WordPress nonces, capability checks, and standard buttons.
- Reduced-motion preferences disable transitions.

## Asset loading

Admin styles load only for the NPE top-level page and NPE submenu hook suffixes. The RTL override loads only when `is_rtl()` is true. No dashboard JavaScript or third-party UI framework is loaded.

## Settings navigation

Settings tabs use real WordPress admin URLs with an allowlisted `section` query parameter. The server renders only the requested group, so navigation remains functional if JavaScript is blocked or delayed. JavaScript progressively enhances those links into instant tabs after `DOMContentLoaded`, maintains the URL, applies ARIA state, and supports Arrow, Home, and End keys.

Each subsystem remains a separate WordPress-style card. Option groups visually separate the control, its effect, and its safety description. The sticky save area and configuration summary remain visible without obscuring mobile content.
