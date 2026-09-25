# Component Intelligence

## Purpose

Component Intelligence converts DOM evidence into reusable functional component
definitions. A component describes stable selectors, CSS and JavaScript inputs,
dynamic states, future cache behavior, invalidation dependencies, and its owner.
It does not concatenate, minify, defer, or otherwise optimize assets in this
phase.

## Component model

`ComponentDefinition` normalizes every definition and calculates a structural
SHA-256 signature from fields that affect behavior. The display name is excluded
from the signature, allowing localization or label changes without rebuilding an
asset plan. Selector and dependency ordering is normalized, so equivalent
definitions share a signature.

Definitions may come from:

- core semantic components such as header, footer, navigation, and mobile menu;
- read-only WooCommerce, Elementor, and WoodMart adapters;
- administrators defining custom PLP/PDP or other site-specific structures.

Each adapter has a separate enabled state in addition to availability detection,
so callers can disable one integration without disabling Component Intelligence
or another adapter.

Custom definitions are stored in the non-autoloaded `npe_custom_components`
option. Detected adapter definitions and usage metadata use separate
non-autoloaded options. No custom database table is necessary at this scale.

## Integrations

### WooCommerce

The adapter uses public availability signals and DOM manifest evidence. It maps
body classes to shop, product category, product tag, single product, cart,
checkout, and account page types. Detected product cards, grids, filters,
sorting, galleries, variations, mini carts, wishlists, and reviews become
component definitions. Personalized mini-cart behavior is marked private.

### Elementor

The adapter identifies Elementor pages and template body classes, converts
widget types into reusable components, and attaches generated Elementor
stylesheet evidence already privacy-normalized by DOM Intelligence. Component
definitions use a stable generated-style dependency marker rather than volatile
per-post stylesheet URLs. The adapter does not call Elementor private APIs or
depend on Elementor internal classes.

### WoodMart

The adapter checks the public active-theme identity and `wd-`/`woodmart` markup
evidence. It is a safe no-op when WoodMart is absent and never loads or calls
undocumented WoodMart PHP classes.

## Custom component administration

**NPE → Components** allows users with `manage_options` to define a component ID,
name, selectors, CSS dependencies/sources, JavaScript dependencies/sources,
dynamic states, cache behavior, and invalidation dependencies. Saving uses an
`admin-post.php` action protected by a nonce. Fields are allowlisted, sanitized,
and escaped on output.

The screen lists built-in, detected, and custom components with usage count,
asset dependencies, observed page types, and signature. Custom selector matching
currently supports class, ID, and element evidence available in DOM manifests.

## Bundle plans and invalidation

`BundleAssembler` creates metadata plans with four layers:

1. Core
2. Components
3. Page type
4. Template

The plan deduplicates and sorts component signatures and asset dependencies.
Equivalent component sets, page types, templates, and core versions resolve to
the same bundle ID, preventing per-URL bundle proliferation. This phase creates
plans only; it does not generate CSS or JavaScript files.

`BundleIndex` stores a compact dependency map. When a custom component's
structural signature changes, only plans containing that component ID are
removed. Independent plans remain valid. Future asset builders can use the same
index to remove physical artifacts.

## Limitations

- Usage counts reflect explicit DOM analyses, not every frontend request.
- Custom selector matching does not implement a full browser selector engine.
- Adapter detection is evidence-based and does not guarantee third-party markup
  remains unchanged across future plugin/theme releases.
- Cache behavior metadata can now be mapped to versioned fragment keys and dependency tags; rendering integrations must still opt in explicitly and classify private state correctly.
- Bundle plans are dependency manifests, not generated optimized assets.
