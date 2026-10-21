# Verification record

Environment: native arm64 Docker, Drupal 11.4.11, PHP 8.4.26, Drush 13.8.0,
MariaDB 11, Embedded Content 2.0.4, contributed examples 2.0.3.

Completed initial checks:

- `docker compose config --quiet`
- `composer check-platform-reqs` inside web: all requirements pass
- Drupal full bootstrap and database connection succeed
- HTTP 200 at localhost:8088 and localhost:8089
- Contributed plugin discovery finds all four example plugins
- Contributed baseline processed text renders the CTA plugin
- Local baseline database snapshot stored in ignored backups/baseline.sql.gz

Browser regression command: `scripts/browser-test.sh baseline` before switching to
the custom implementation; `scripts/browser-test.sh inside` after switching.
The browser runs in an arm64 Playwright container, avoiding macOS sandbox launch
restrictions and host Node requirements. `npm ci` installs test libraries; Node 22
is available as a local development dependency if the host Node is too old.

A blank installation logs a mail error because no local SMTP transport exists.
That is unrelated to embedded content. Never commit local login links or passwords.

Drupal 11.4 Standard no longer supplies the page bundle/body storage used by this
fixture. scripts/baseline.php therefore creates these explicitly before testing.
The initial node-view 500 from the missing bundle was corrected by that setup.
Automatic cron is disabled in this development site to run cron explicitly and
avoid a post-response database shutdown error observed during browser saves.

The post-response error persisted with automatic cron disabled. Core's database
Connection.php documents that Xdebug develop mode changes destruction order. The
web container now sets XDEBUG_MODE=debug (step debugging remains available), with
no develop mode. This is an environment fix, with no contrib/core changes.

## Final local results

- Contributed baseline Chromium lifecycle: **1 passed** (insert, edit without
  duplicates, save, front-end rendering), before the custom implementation.
- Custom Chromium suite: **7 passed**. Covers the core lifecycle, nested validation,
  unlimited accordion items, multiple components, undo/remove, inline/block
  replacement, stale saves, native dialog closing/focus, nested Drupal modal,
  nested styled-list CKEditors, attached preview behaviors and CSRF rejection.
- Non-admin Inside content editor: **1 passed** for insert/edit/cancel/save/reopen
  and front-end rendering using only the configured editor role.
- Bootstrapped PHP integration script: **12 checks passed** for discovery, escaping,
  nested rendering, malformed-tag resilience, library bubbling, access, forced
  off-canvas settings, safe URI schemes and CKEditor config validation.
- PHP syntax checks across both custom modules: passed.
- Production Webpack build: passed with no warnings.
- Composer platform checks, Drupal bootstrap, database connection and both local
  HTTP endpoints: passed.

Development failures were fixed and retained in logs for transparency: the initial
missing page/body fixture, use of a nonexistent Editor::validate method (replaced
with typed configuration validation), Xdebug develop shutdown errors, partial
validation clearing selected plugin values, missing AJAX page-state information,
and legacy jQuery dialog event assumptions. No core or contributed files were patched.
Recent Drupal PHP errors are historical setup failures, not new errors from the
passing suite. Missing-CSRF requests intentionally produce access-denied logs.

Media Library image upload/selection has now been exercised with
`drupal/media_library_form_element` 2.1.5; see the follow-up record below.
Screen-reader, Safari/Firefox/mobile and collaborative-editing checks have not been
performed. GitHub Actions runs are reported separately from local results.

To verify as a non-admin editor:

```sh
docker compose exec -T web vendor/bin/drush php:script /var/www/scripts/test-editor.php
BROWSER_TEST_ACCOUNT=inside_editor_regression scripts/browser-test.sh inside --grep 'off-canvas insert'
```


## Media Library Form Element follow-up

Installed `drupal/media_library_form_element:^2.1` (locked 2.1.5), core Media and
Media Library. The `inside.media_image` example uses the actual contributed
`media_library` form element in the off-canvas form; core's media picker is a nested
modal while component configuration remains off-canvas.

The first run exposed an AJAX selector mismatch: the contributed element constructs
wrapper IDs from Form API parents, and the component ID `inside.media_image`
contains a dot. The example normalizes the wrapper ID and its update/remove AJAX
callbacks without patching contrib. It also delegates the value callback with an
empty-value fallback to avoid the module's PHP 8.4 `ctype_digit(NULL)` deprecation.
PHP GD was rebuilt with WebP support so Drupal's thumbnail image styles generate
real images successfully.

The two browser cases in `tests/media.spec.js` cover:

- Upload PNG, enter alt text, save and insert through the actual Media Library.
- Preserve the caption while the media widget rebuilds.
- Insert into CKEditor, save a node, verify a fully loaded image on the page.
- Reopen the saved component and update its caption without duplication.
- Select existing media, remove and reselect it.
- Cancel the nested picker while retaining the off-canvas form.
- Remove an image and cancel the component editor without changing document data.
- Validate missing media and reject embedding without a permitted image.

Both cases passed as administrator and as the non-admin Inside content editor.
Image assertions check `complete` and `naturalWidth`, so a broken image URL cannot
pass merely because an `<img>` tag exists. Saved-node reopening uses keyboard
activation of the component edit action to exercise that accessible workflow too.
The editor role has `view media`, `create image media` and `edit own image media`.

Historical image-generation notices predate the WebP rebuild; no contributed or
core files were changed. Other media types and image-editing widgets are outside
this test's coverage.

The pre-existing synthetic nested-dialog test now waits for core's initial
`dialogContentResize` event before closing its directly-created modal. Closing it
in the same event loop as creation exposed a pending core debounce on a removed
jQuery UI widget. The readiness-based test passed five consecutive repetitions.
This does not suppress client errors or patch core.

Final follow-up verification: all **9** custom browser tests passed together; the
**2** Media Library cases also passed under the non-admin editor account. All
**12** bootstrapped PHP integration checks and Composer platform requirements
passed. No new PHP errors or image-generation failures occurred after the fixes.
