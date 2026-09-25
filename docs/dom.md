# DOM Intelligence

See [DOM Intelligence](dom-intelligence.md) for the analyzer, manifest, signature, privacy model, static states, integrations, and known limitations.

Analysis is administrator-triggered, nonce/capability protected, same-origin, response-size bounded, and performed with WordPress safe HTTP APIs. Full HTML, cookies, credentials, tokens, password values, and sensitive query parameters are not persisted. Invalid or unavailable DOM input produces no optimization and leaves the page unchanged.
