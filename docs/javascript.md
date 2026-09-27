# JavaScript

See [JavaScript Intelligence](javascript-intelligence.md) for discovery and planning details.

NPE discovers WordPress script handles, dependency order, inline/localized data, modules, and loading strategies. Uncertain, remote, module, localized, inline-configured, or safety-critical scripts retain their original handles. Bundle writing is not automatically applied to frontend requests.

Generated bundles require all sources to be explicitly provided from trusted local registered scripts, reject PHP payload markers and oversized content, preserve dependency order, use content-hashed `.js` names, write atomically, and are confined to the configured NPE JavaScript asset directory. Failure throws before WordPress enqueue state is changed, leaving original assets usable.
