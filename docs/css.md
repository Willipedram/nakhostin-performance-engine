# CSS

CSS optimization is intentionally not implemented in this release. The contract, component dependency fields, configuration group, administration status, and future bundle boundaries exist, but NPE performs no CSS tree shaking, critical CSS generation, stylesheet rewriting, or automatic removal.

This fail-safe boundary ensures original stylesheets remain untouched. A future CSS phase must use DOM manifests and component dependencies, preserve dynamic/runtime states, generate content-hashed artifacts under a protected trusted directory, and retain original styles whenever confidence is insufficient.
