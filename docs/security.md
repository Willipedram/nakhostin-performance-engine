# Security and Threat Model

## Audit result

The release audit found no direct SQL construction, REST routes, unsafe PHP unserialization, dynamic PHP includes, or evaluation of user code. State-changing admin handlers require `manage_options` and WordPress nonces. Settings use the Settings API sanitizer; read-only notice query values are allowlisted. Output uses context-appropriate WordPress escaping.

## Cache privacy

Public caching is denied for private WordPress/WooCommerce contexts, known authentication/session/cart/customer cookies, authorization headers, foreign host/port requests, sensitive query parameters, personalized responses, `Set-Cookie`, private/no-store headers, password/nonce markup, and the WordPress admin toolbar. Cache dimensions are bounded and allowlisted. Private fragments require an opaque caller scope which is stored only as a SHA-256 hash.

Cache and fragment filenames are hashes rather than request paths. Records use content hashes and must begin with an immediate `<?php exit; ?>` guard. Direct access is further restricted with `index.php` and `.htaccess`; these are defense in depth and server-level Nginx rules remain an operator responsibility.

## Assets and files

JavaScript bundles are diagnostic build artifacts, never arbitrary form input. The writer accepts only known bundle layers, bounded source strings, rejects PHP payload markers, preserves dependency order, uses content hashes, confines output to one configured trusted directory, and uses atomic replacement. Protection files disable CGI handlers for executable extensions. If creation, protection, or publication fails, no enqueue state changes and original scripts remain usable.

All cache paths are plugin-owned fixed roots. User-controlled cache keys are hashed. Reads never call `unserialize`; malformed, tampered, unguarded, or hash-invalid records are discarded. Uninstall removes only the fixed NPE cache directory and only after the explicit remove-data option is enabled.

## Logging and privacy

Logging is opt-in and requires `WP_DEBUG`. Sensitive context keys and credential-like message values are redacted. Performance records contain no URLs, query strings, request bodies, cookies, headers, SQL, identities, credentials, or tokens.

## Residual risks

- Application-level page cache cannot run before generic WordPress bootstrap.
- `.htaccess` is ignored by Nginx; the PHP exit prefix remains the portable record safeguard, while administrators should deny direct access to `wp-content/cache/nakhostin-performance-engine` at the server layer.
- Third-party filters can provide cache dimensions and component identifiers; extensions must not provide personal or secret data.
- This review is internal hardening, not an independent penetration test.
