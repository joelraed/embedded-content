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

Actual Media Library selection remains **untested** because the example plugins
do not invoke it. A nested native Drupal modal and multiple CKEditors in the tray
were exercised. Screen-reader, Safari/Firefox/mobile and collaborative-editing
checks were not performed. GitHub Actions is configured but its remote result must
be checked separately.

To verify as a non-admin editor:

```sh
docker compose exec -T web vendor/bin/drush php:script /var/www/scripts/test-editor.php
BROWSER_TEST_ACCOUNT=inside_editor_regression scripts/browser-test.sh inside --grep 'off-canvas insert'
```
