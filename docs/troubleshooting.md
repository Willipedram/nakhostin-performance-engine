# Troubleshooting

## Site output is unexpected

1. Disable NPE page cache and diagnostics under **NPE → Settings**.
2. Purge NPE caches under **NPE → Cache**.
3. Confirm whether LiteSpeed Compatible/Cooperative mode delegates public page caching.
4. Retest while logged out and with a clean browser session.
5. Keep CSS/JavaScript application disabled; current analyzers are diagnostic and original assets should remain active.

## Cache directory is unavailable

NPE fails open and serves the original WordPress response. Confirm that `wp-content/cache` is writable by PHP, is not a symlink to an untrusted location, has sufficient disk space, and permits creation of NPE protection files. Do not grant world-writable permissions.

## DOM analysis fails

Confirm the PHP DOM extension is installed, the URL is same-origin and uses the site's configured scheme/port, and loopback HTTP requests are allowed. Responses are limited to 2 MiB.

## Database timing is unavailable

NPE deliberately does not enable `SAVEQUERIES` in production. Query count remains available, but query duration is shown only when another controlled diagnostic environment already provides query timing.

## Rollback

Disable features, purge NPE-owned caches, deactivate the plugin, restore the previous plugin release, and reactivate. Preserve plugin options unless performing a deliberate uninstall. If a future migration is marked non-reversible, restore the matching database backup.
