# JavaScript Intelligence

## Safety model

JavaScript Intelligence treats registration metadata and execution order as part
of script behavior. It never infers that an unused CSS-like token means a script
is unnecessary. When source content, dependency data, execution semantics, or
runtime configuration is uncertain, the original registered handle is retained.

No analysis or optimization runs on normal frontend requests. The administration
screen performs an explicit diagnostic snapshot. Other build/learning workflows
may call the same services deliberately in a future phase.

## Source discovery

`ScriptDiscovery` reads the public `WP_Scripts` registration and queue model:

- registered and enqueued handles;
- source URLs and versions;
- declared dependencies;
- header/footer groups;
- `defer` and `async` strategy metadata;
- inline scripts before and after a handle; and
- localized data attached through the registered script's public extra metadata.

Runtime inline/localized contents remain in memory only. Persisted manifests
contain counts and boolean evidence, never nonce values, AJAX/REST URLs,
credentials, or configuration payloads. Script source URLs are persisted without
credentials, query strings, or fragments.

Handles with WordPress script translations are treated as runtime-configured and
retain their original registration so translation bootstrapping remains intact.

WordPress currently has no stable public getter for the complete Script Modules
registry. Public integrations may provide module snapshots through the
`npe/javascript/module_snapshots` filter. Modules are analyzed and retained with
their original loading semantics; they are not concatenated by the conservative
classic-script bundler.

## Dependency graph and ordering

`DependencyGraph` recursively resolves every dependency before its dependent and
preserves WordPress registration order for otherwise independent roots. Missing
dependencies and cycles produce warnings. A cycle causes the affected analysis
to retain original scripts rather than guessing an order.

jQuery, WooCommerce checkout/cart/variation scripts, Elementor frontend/editor
scripts, login/admin contexts, scripts with inline or localized data, external
sources, async/deferred registrations, and script modules are retained by the
default safety policy. Inline/localized code stays associated with its original
handle, preserving nonces, AJAX URLs, REST configuration, and execution timing.

## Optimization planning

The manifest distinguishes scripts required by the current page (enqueued roots,
their transitive dependencies, and component-declared handles) from scripts that
are merely registered but outside that page dependency closure. Registered-only
handles are reported as unused candidates and are not included in a page bundle.
This is a page-specific dependency decision, not source-code dead-code elimination.
When a DOM manifest exists, the administration analysis matches its privacy-safe
script source paths to registered WordPress handles and uses only components
detected in that manifest. Without a DOM manifest, it conservatively falls back
to the scripts enqueued in the diagnostic request.

The preferred workflow uses the one-time frontend capture initiated from DOM
Intelligence. Script discovery runs after the public page has rendered, when the
actual WordPress queue is available. Refreshing JavaScript analysis revisits the
latest analyzed public URL and refreshes DOM, component, CSS, and JavaScript
manifests as one consistent snapshot. Heavy analysis never runs inside an ordinary
visitor response; opt-in DOM learning only enqueues eligible URLs for WP-Cron.

`JavaScriptPlanner` produces three narrow layers instead of one global bundle:

1. `core`
2. `component`
3. `page`

Component JavaScript handles come from Component Intelligence definitions. Each
layer uses the dependency graph's topological order and removes handles already
assigned to an earlier layer. Only classic local scripts with readable trusted
source content and no runtime data are bundle candidates.

Deferral and delayed loading are explicit handle allowlists. They are disabled by
default. Critical scripts are rejected, and a dependency cannot be delayed while
an active non-delayed dependent still requires it. The planner reports a warning
and retains original behavior for rejected requests.

Approved defer plans can be applied explicitly through `ScriptStrategyApplier`,
which uses WordPress's public `wp_script_add_data()` strategy API. NPE does not
silently change strategies during ordinary requests.

## Minification and generated files

The built-in conservative minifier removes only a UTF-8 BOM, normalizes line
endings, trims trailing whitespace, and removes leading/trailing blank space. It
leaves sources containing template-literal markers byte-for-byte unchanged after
an optional BOM because template whitespace may be significant. It does not use
unsafe regular expressions to remove comments or rewrite JavaScript syntax. More
aggressive minifiers must implement a future tested adapter.

`JavaScriptBundleWriter` verifies that every asset has trusted source content and
no runtime data, re-resolves dependency order, and writes atomically. Generated
names use the first 20 hexadecimal characters of the SHA-256 content hash, for
example `component-a1b2c3d4e5f607182930.js`.

## Invalidation

The JavaScript manifest signature includes:

- sanitized registration metadata and dependency lists;
- hashes of source file contents;
- hashes of inline/localized runtime data without persisting that data;
- plugin, theme, WordPress, WooCommerce, Elementor, and NPE versions supplied by
  the analysis context;
- the NPE settings hash; and
- Component Intelligence signatures.

Changing any of these inputs yields a different signature. The stored diagnostic
manifest can also be deleted explicitly. Full page-cache invalidation is outside
this phase.

## Administration

**NPE → JavaScript** shows original and projected optimized size, file count,
bundle plans, deferred/delayed handles, warnings, script handles, dependencies,
and existing WordPress strategies. The action requires `manage_options` and a
valid nonce. The diagnostic action analyzes the current admin registry in safe
retain-original mode; production frontend builds require a future explicit build
workflow with the correct frontend registration context.

## Known limitations

- No headless browser or interaction crawler discovers runtime-injected scripts.
- Script modules are analyzed but not bundled.
- Delayed loading is planned and validated but no frontend delay loader is
  automatically installed.
- The conservative minifier intentionally achieves smaller reductions than a
  parser-based production minifier.
- External scripts, inline/localized handles, checkout/cart/account behavior,
  Elementor editing, login, and admin scripts remain original by default.
- This subsystem does not implement full-page or object caching.
