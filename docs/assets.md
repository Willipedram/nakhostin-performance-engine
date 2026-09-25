# Repository and Release Asset Policy

The Git repository is intentionally **text-only** because the review and patch transport does not support binary files. Raster images, compiled translation catalogs, archives, and other binary artifacts must not be committed.

## SVG

Repository-native visual assets must use textual SVG when an image is necessary. SVG files are explicitly classified as UTF-8 text in `.gitattributes`. Any future SVG must be static, must not contain scripts, event-handler attributes, external resource references, embedded HTML, or user-controlled markup, and must be escaped or sanitized before browser output.

## Translations

PO and POT catalogs remain reviewable UTF-8 text. WordPress 6.4 requires binary MO catalogs at runtime, so `composer build-translations` compiles MO files into the ignored `build/languages` release-staging directory. Packaging must copy that directory into the distributable ZIP's `languages` directory; binary files are never written into the source catalog directory or included in a source-review patch.

## Enforcement

Run `composer check-text` before committing. The check rejects NUL-containing and non-UTF-8 files outside ignored build/vendor/Git directories. Release automation must compile translations after this source check and package artifacts from a disposable build directory.
