# Repository and Release Asset Policy

The Git repository is intentionally **text-only** because the review and patch transport does not support binary files. Raster images, compiled translation catalogs, archives, and other binary artifacts must not be committed.

## SVG

Repository-native visual assets must use textual SVG when an image is necessary. SVG files are explicitly classified as UTF-8 text in `.gitattributes`. Any future SVG must be static, must not contain scripts, event-handler attributes, external resource references, embedded HTML, or user-controlled markup, and must be escaped or sanitized before browser output.

## Translations

PO and POT catalogs remain reviewable UTF-8 text. The committed `*.l10n.php`
catalog is generated with `composer build-php-translations` and allows modern
WordPress versions to load Persian translations directly from a text-only source
package. Release packaging also runs `composer build-translations` and copies the
staged MO file into the ZIP for WordPress 6.4 compatibility. Binary files are
never written into the tracked source catalog directory or included in a
source-review patch.

## Enforcement

Run `composer build-php-translations` and `composer check-text` before committing.
The check rejects NUL-containing and non-UTF-8 files outside ignored
build/vendor/Git directories. CI must fail if rebuilding the PHP catalog changes
the working tree. Release automation compiles binary translations after this
source check and packages artifacts from a disposable build directory.
