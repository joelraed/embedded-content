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
