# Inside Embedded Content — Drupal 11

An independently maintained fork of Embedded Content 2.x with CKEditor 5 component
configuration in Drupal's right-side off-canvas tray. The editor stays visible.
The contributed implementation remains Composer-managed for reference and baseline
testing; it is disabled on the completed custom site.

## Development environment

Use Docker Desktop with Apple Silicon support and Docker Compose v2. No host PHP,
Composer or database server is required. Node 22+ and npm are needed only to rebuild
JavaScript or install the browser test dependencies. All service images support
arm64; no x86 emulation is forced.

```sh
cd ~/Sites/embedded-content
cp .env.example .env
# Replace all three placeholder values with independently generated random values.
scripts/install.sh
```

The script starts PHP 8.4/Apache, MariaDB 11 and phpMyAdmin, runs Composer 2, installs
Drupal 11 Standard, enables the verified machine names `embedded_content` and
`embedded_content_examples`, and creates a baseline text format, page type/body
field and demonstration node. It leaves an existing installed database intact.
If the custom implementation is already enabled, it leaves baseline modules disabled.
The initial administrator password is printed by Drush during installation; it is
not written to tracked files.

- Drupal: http://localhost:8088
- phpMyAdmin: http://localhost:8089 (server `db`, user `drupal`, password from `.env`)
- Database: `embedded_content`; internal hostname: `db`
- Source: `./drupal` mounted at `/var/www/html`; document root: `drupal/web`
- Database persistence: Compose volume `db_data`

Both published ports bind only to the loopback interface. Uploaded files remain
local under `drupal/web/sites/default/files`. Settings read passwords and hash salt
from environment variables. `.env`, databases, uploads, vendor, core and downloaded
contrib modules are excluded from Git. Run `docker compose down` to stop; do not
add `-v` unless intentionally discarding the local database.

If Docker reports a macOS Keychain credential-helper error, repair/unlock Docker
Desktop's Keychain integration before pulling. Do not commit Docker credentials.
The initial build in this environment used a temporary empty Docker configuration
for public pulls; it did not change the user's saved credentials.

## Baseline, then custom implementation

The Composer lock fixes Drupal 11.4.11, Embedded Content 2.0.4, examples 2.0.3 and
Drush 13.8.0. These are real package versions resolved for this project.

```sh
docker compose exec web composer require 'drupal/embedded_content:^2.0'
docker compose exec web composer require 'drupal/embedded_content_examples-embedded_content_examples:^2.0'
docker compose exec web vendor/bin/drush status
npm ci
docker compose --profile test pull browser
scripts/browser-test.sh baseline
scripts/switch-to-custom.sh
scripts/browser-test.sh inside
```

The switch script first saves a timestamped baseline database dump in ignored
`backups/`, disables the original format filter/editor, uninstalls both contributed
modules, and enables `inside_embedded_content` plus `inside_embedded_content_examples`.
It creates the **Inside Embedded Content** text format and the **Inside content
editor** role. Assign that role to an editor account to use the feature without
administrator permissions. The custom module refuses installation while the
contributed implementation is enabled. Keep the implementations separate when
re-running comparisons.

The original baseline database snapshot from development is also available locally
as `backups/baseline.sql.gz`; snapshots are intentionally absent from Git. Restore
that snapshot in a separate development database for further baseline comparisons.
Never restore it over a database containing work you need to retain.

Get a local administrator login link (treat the link as a secret):

```sh
docker compose exec web vendor/bin/drush uli --uri=http://localhost:8088
```

Create/edit a Basic page, select **Inside Embedded Content** as the body text format,
and click its toolbar button. Select a plugin, fill its fields, and press **Embed**.
Select a component and choose **Edit component** or **Remove component**. Keyboard
Delete/Backspace and undo/redo use CKEditor's normal object editing. Escape or the
tray's Close button cancels. Changed documents/targets require reopening the tray
before a pending save can be accepted.

For another text format, enable CKEditor 5, add the Inside Embedded Content toolbar
button, enable the `inside_embedded_content` text filter, and allow both storage
tags and their three data attributes. The provided format includes a restrictive
HTML filter with those tags, rather than unrestricted author HTML.
Button collections, icons, permissions, plugin conditions and panel width are
managed at `/admin/config/content/inside-embedded-content/button`.
Width is clamped to 300–1000 pixels; the default is 480 and the tray can be resized.

## Architecture and differences from upstream

See [the upstream analysis](docs/architecture.md) and
[the verification record](docs/testing.md).

The custom namespace is `Drupal\inside_embedded_content`. Services, configuration
entities, permissions, routes, libraries, filter IDs and JS plugin identifiers are
independent of the contributed module. `plugin.manager.inside_embedded_content`
discovers compatible `@EmbeddedContent` plugins. Button entities create CKEditor
plugin derivatives and provide collection-specific conditions and permissions.

The storage format remains:

```html
<embedded-content data-plugin-id="example.counter"
  data-plugin-config="{&quot;start_at&quot;:10}"
  data-button-id="default"></embedded-content>
```

Inline plugins use `embedded-content-inline`. JSON supports nested arrays/objects.
Plugin IDs for adapted original examples are preserved. A namespace fork does not
automatically migrate third-party plugin classes or text formats: adapt the plugin
imports and dependencies, enable the new filter/editor and retain compatible IDs.
Back up and test stored content before migrating a production site.

Upstream already selected core's off-canvas renderer through optional button
settings. This fork makes it the only configuration interface and explicitly uses
`OpenOffCanvasDialogCommand`; no CKEditor Dialog API is used. Core still supplies
the underlying dialog implementation, positioning and accessibility behavior.

The opening route checks the collection permission, enabled button, format access,
enabled Inside filter and a CSRF token. Form API supplies form tokens and validation.
Successful submission sends a custom request-scoped AJAX command. The client checks
the editor ID and nonce, editor lifetime/read-only state and original target
attributes before using supported CKEditor model writer operations. Existing
objects are updated in place; changing between inline and block replaces the
selected model range. New insertions retain a live model position and reject a
save if the document has changed while the panel was open.

Pending callbacks are invalidated on close, a newer open and editor destruction.
Only an accepted save closes the tray. Preview requests use unique RawElement
selectors, abort superseded requests and load plugin assets via Drupal's AJAX
commands. Behaviors attach after insertion and detach on replacement, removal and
editor destruction. The editor's serialized HTML is never directly manipulated.

## Creating component plugins

Create a module that depends on `inside_embedded_content:inside_embedded_content`.
Add a class under `src/Plugin/EmbeddedContent`, extending the new base class:

```php
namespace Drupal\my_module\Plugin\EmbeddedContent;

use Drupal\inside_embedded_content\EmbeddedContentPluginBase;

/**
 * @EmbeddedContent(
 *   id = "my_module.message",
 *   label = @Translation("Message"),
 * )
 */
final class Message extends EmbeddedContentPluginBase {
  public function isInline(): bool { return FALSE; }
  public function build(): array {
    return ['#plain_text' => $this->configuration['message'] ?? 'Hello'];
  }
}
```

Implement `defaultConfiguration()`, `buildConfigurationForm()` and optional
`validateConfigurationForm()` and `massageFormValues()` as required. The form gets
a `SubformState` scoped to its nested plugin values. `build()` returns the same
render array used by preview and front-end filtering. Return libraries from
`getAttachments()`; bubble cache dependencies in render arrays. Escape untrusted
text, validate URI schemes and enforce access for any referenced entities.
`submitConfigurationForm()` is retained for interface compatibility; the bridge
uses `massageFormValues()` to normalize the final stored configuration.

The examples demonstrate:

- `example.footnote`: a simple inline component.
- `example.call_to_action`: required URL and label fields, safe HTTP(S) links.
- `example.counter`: attached CSS/JS with per-instance behavior cleanup.
- `example.styled_list`: dynamic items containing nested CKEditors.
- `inside.notice`: an AJAX-dependent nested form and custom validation.
- `inside.accordion`: arbitrary add/remove items, stable keys and native details.

## AJAX form lifecycle

Plugin selection and field callbacks rebuild the Form API subtree without a page
reload. Read submitted nested values for rebuild defaults: partial validation
intentionally clears processed values, so raw input must be retained until final
validation. Do not treat rebuild defaults as validated data.

Dynamic add/remove controls use submit handlers with empty validation limits and
`setRebuild()`. Stable item keys prevent removal from shifting the remaining input
onto the wrong item. Final normalization removes action controls before JSON storage.
Return the requested wrapper, retain its ID, and include status messages so field
validation errors stay in the tray. See the accordion and styled list implementations
for working examples. Drupal detaches behaviors with `serialize` before collecting
nested editor values and with `unload` before replacing form DOM.

## Composer, Drush and configuration

```sh
docker compose exec web composer install
docker compose exec web composer check-platform-reqs
docker compose exec web vendor/bin/drush cr
docker compose exec web vendor/bin/drush cron
docker compose exec web vendor/bin/drush config:export -y
docker compose exec web vendor/bin/drush watchdog:show --severity=Error
```

Configuration exports are in `config/sync` and mounted at `/var/www/config/sync`.
They describe the completed custom site, including editor, format and role.
Use normal Drupal site UUID/configuration management rules when importing into a
different database; the setup scripts configure a fresh local site without needing
to import another site's UUID. Exported configuration contains no user passwords,
uploads, content entities or database credentials.

## Database backup and restore

```sh
mkdir -p backups
docker compose exec -T web vendor/bin/drush sql:dump --gzip --result-file=/tmp/site.sql
docker compose cp web:/tmp/site.sql.gz backups/site.sql.gz
# Restore only into an intended disposable/development database:
gunzip -c backups/site.sql.gz | docker compose exec -T web vendor/bin/drush sql:cli
docker compose exec web vendor/bin/drush cr
```

## Xdebug

Xdebug listens for triggered debugging on port 9003 and connects to
`host.docker.internal`. Configure your IDE's `embedded-content` server with
`/var/www/html` mapped to the local `drupal` directory. Trigger with an Xdebug browser
extension or `XDEBUG_TRIGGER=1`. The container sets `XDEBUG_MODE=debug`.
Do not enable `develop` mode on this setup: it caused database object destruction
errors after redirects during initial verification. PHP step debugging is available
without that mode. Automatic cron is disabled in the delivered configuration;
run `drush cron` explicitly during development. There is no local SMTP service.

## Build and test

```sh
npm ci
npm run build
docker compose exec -T web vendor/bin/drush php:script /var/www/scripts/regression.php
scripts/browser-test.sh inside
```

The compiled CKEditor bundle and npm lock are committed. Webpack references the
CKEditor DLL matching the installed Drupal 11.4 version (`ckeditor5 ~47.6.2`);
review this dependency when updating Drupal's CKEditor version. No CKEditor runtime
is bundled twice. Playwright tests run in a pinned container, with a single
account login in global setup and isolated browser contexts per test.
Login state and traces are ignored by Git. The GitHub Actions workflow runs baseline
then custom tests on a fresh database and verifies the compiled bundle is current.

The PHP integration script checks discovery, safe rendering, nested configuration,
cache/library propagation, access, forced off-canvas settings and config validation.
Browser tests cover insertion, identity-preserving edits, no duplicates, cancel,
save/reopen/render, multiple components, undo/removal, inline/block conversion,
validation, AJAX fields, more than two accordion items, nested CKEditors, stale
callbacks, keyboard focus, repeated close/reopen, plugin behaviors, nested Drupal
dialogs and missing-CSRF rejection. See docs/testing.md for actual results and limits.

## Known limits and future improvements

- Actual Media Library selection was not exercised: no supplied plugin uses it.
  A nested core Drupal modal inside the tray and nested CKEditors were tested;
  this is not a claim of complete Media Library or contributed-widget compatibility.
- Chromium was automated; screen-reader, Safari, Firefox and mobile testing remain.
- Collaborative editing and simultaneous edits to the same component need a more
  sophisticated merge policy. Current stale-save protection rejects conflicting
  work rather than overwriting it.
- Annotation plugin discovery is retained for Drupal 11 compatibility. Attribute
  discovery and Drupal 12 migration are future work.
- This is a development environment, not production hosting configuration.
- CI is defined in the repository; its remote run status is separate from local
  test results.

GPL-2.0-or-later. Upstream licensing is preserved; see
[ATTRIBUTION.md](drupal/web/modules/custom/inside_embedded_content/ATTRIBUTION.md).
