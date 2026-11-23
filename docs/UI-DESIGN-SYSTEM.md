# NPE administration design system

## Architecture

Register NPE screens with `AdminShell::menu()` or `AdminShell::submenu()` in `src/Admin/AdminShell.php`. The wrappers use native WordPress menu registration and preserve page slugs and callbacks. The registered hook determines asset loading. Each callback receives the same breadcrumb, title, active navigation, skip link, content landmark, and footer. A new registered page appears under More until assigned a section. Its callback supplies page content.

`assets/admin/design-system.css` owns the semantic colors, typography, spacing, radii, and shared component rules. Dark appearance follows the browser color-scheme preference. `assets/admin/admin.css` contains existing feature-specific layout rules. Reuse the central tokens in those rules. `admin-rtl.css` isolates technical text and inputs that must remain LTR. The system uses native WordPress Dashicons and local system fonts.

## Component and interaction contracts

| Surface | Contract |
| --- | --- |
| Page content | Use the shell title, then describe the page and show relevant status, evidence, and actions before secondary content. Use semantic headings for sections. |
| Cards and numbers | Reuse `npe-card`, `npe-metric-grid`, `AdminComponents::metric()`, and `npe-technical`. Include meaningful units, source, date and sample count when available. Keep lab data, field data, estimates and unknown values distinct. |
| Settings | Reuse `AdminComponents::field()` and its native inputs and contextual help. Keep the field name, allowed values, nonce, permission check, and save behavior intact. Put high-risk controls in a native `npe-advanced` disclosure. |
| Navigation | Use the shared menu and real WordPress admin links. The active item uses `aria-current`. Use the existing Settings tab behavior for in-page options; links must still work without scripting. |
| Tables | Use native tables and headings, WordPress `widefat` and `striped`. The shell supplies scroll containers with keyboard focus when needed. Keep comparable values aligned and avoid hiding important columns. |
| Feedback | Use native WordPress notices and their status classes in the shell. Include text and an action when appropriate. Avoid relying on badge color alone. Use native progress and live regions for asynchronous work. |
| Confirmation or dialogs | Prefer an existing native confirmation or WordPress action flow. A new dialog must include an accessible name, keyboard dismissal, focus containment and focus restoration. |
| Charts and scores | Use the same metrics, colors, labels, and units as tables. Provide the underlying figures as text. Load any visualization script only on pages that use it. |

All CSS selectors must be rooted at `.npe-admin` so they apply only to the plugin UI. Use logical properties, localize all labels, retain visible focus, support narrow and wide screens, and respect reduced motion. Screens must work using normal WordPress navigation without a frontend framework. Keep JavaScript local, small, and limited to the registered screen or shared shell where needed.

## Migration checklist

- Overview and dashboard cards, Cache and Optimization
- DOM Intelligence, CSS Intelligence, JavaScript Intelligence and Components
- WooCommerce, Elementor and LiteSpeed integration pages
- Performance and Lighthouse history, Diagnostics, Logs and Settings

These are the fourteen routable admin screens in this version. WoodMart, media, fonts, security, exports, and help appear where their existing NPE functionality is exposed; new pages for them must use the same registration layer. Existing URLs, form actions, settings, and capability checks remain the public contract.

The audit identified three independent header treatments, hard-coded light surfaces and inconsistent radius/spacing, and hook-prefix/query asset routing. This migration centralizes the shell, makes the palette theme-aware, and scopes assets to registered WordPress hooks. Feature-specific diagnostic tables and existing workflows remain in their screens.

## Verify changes

Run the PHP suite and `tests/Integration/admin-design-smoke.cjs` against staging with Playwright Core and Chrome/Chromium. Pass `NPE_UI_ADMIN_URL` with a trailing slash and `NPE_UI_STORAGE_STATE` pointing to an authenticated Playwright storage state kept outside the repository. To exercise RTL, provide `NPE_UI_RTL_STORAGE_STATE` for a Persian admin. Use `NPE_UI_SCREENSHOTS` to capture review screenshots. Set `NPE_UI_AXE_SCRIPT` to the path of an independently installed axe-core script to scan the shell on each route. Review desktop, tablet and mobile screenshots in both directions, verify keyboard focus and touch targets, confirm responsive tables, and validate any form interactions and negative states changed by the work. Keep test credentials and session cookies outside screenshots and commits.

Automated smoke checks cover structural scope, layout overflow, route reachability, navigation and selected interactions; they do not certify conformance with WCAG 2.2 AA. A reference design or approved screenshot baseline must be available before asserting visual parity.
