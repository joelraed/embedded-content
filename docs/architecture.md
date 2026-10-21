# Upstream review and refactor boundaries

Reference: drupal/embedded_content 2.0.4 and
 drupal/embedded_content_examples-embedded_content_examples 2.0.3, resolved in composer.lock.

The upstream implementation consists of an annotation-based EmbeddedContent plugin
manager, a ConfigEntity button per collection, a CKEditor plugin deriver, a Form API
dialog, a preview controller and a reversible text filter. The editor stores
`embedded-content` / `embedded-content-inline` elements with JSON in
`data-plugin-config`, plus `data-plugin-id` and `data-button-id`.

JavaScript editing.js defines the CKEditor schema and converters; command.js inserts
model objects; ui.js registers collection buttons; toolbar.js registers Edit;
utils.js opens Drupal AJAX dialogs and sets the global editor save callback.
The preview controller calls the same plugin build() as the text filter. The filter
bubbles cache metadata and plugin attachments. Button conditions and permissions
control collection access; the preview route includes a CSRF token.

Upstream already supports off-canvas through `dialogSettings.renderer`:
Drupal.ajax uses dialogType=dialog and dialogRenderer=off_canvas. Core's
OffCanvasRenderer returns OpenOffCanvasDialogCommand. Form submission uses
EditorDialogSave and CloseDialogCommand for the tray; otherwise it uses a modal.
There is no reason to recreate core's tray positioning or behavior lifecycle.

Problems addressed by the custom implementation:

- Force the supported off_canvas renderer in PHP and JavaScript, retaining width.
- Replace shared editor save callbacks with request-scoped, one-shot callbacks.
- Capture a target model element or live insertion position before opening. An edit
  updates that exact object, with undo support; a deleted or changed target is stale.
- Invalidate pending work on tray close, editor destruction, or a newer open request.
- Validate button and text-format access and enforce collection plugin conditions
  on POST as well as during form construction.
- Decode JSON as data; escaping belongs in each rendering plugin, not an XSS filter
  over serialized JSON that can corrupt saved configuration.
- Preserve nested plugin values and stable dynamic item keys across AJAX rebuilds.
- Render field errors and messages inside the replaced form wrapper.
- Refresh previews when configuration changes and discard stale preview requests.
- Separate entity, route, filter, permission, library and JS plugin identifiers.

The PHP plugin interface, button collection model, HTML storage format, render
pipeline and compatible example plugins are retained with attribution. Existing
contributed plugins must import the new base/interface to be discoverable by the
new manager. Plugin IDs can stay stable so existing content can be migrated.

Original contrib files are managed only by Composer and remain unmodified.
The two implementations are exercised sequentially; never enable both filters
for the same text format or both CKEditor implementations in one editor.

## Implemented lifecycle refinements

The controller now constructs OpenOffCanvasDialogCommand explicitly. The preview
controller can return a Drupal AJAX HtmlCommand carrying plugin assets. The client
sends ajax_page_state so core does not reload existing libraries, executes the
commands only for the current unique preview ID, and detaches behaviors before
replacement/removal. Counter timers are per instance and stop on unload.

Drupal 11.4 dispatches native dialog lifecycle events from the dialog element.
The bridge listens once to dialog:beforeclose, rather than relying on legacy
jQuery extra event arguments. Invalidation happens before AJAX removes the tray,
so a close event cannot be lost when the detached element stops bubbling.

Rebuild configuration reads raw nested input as defaults, but final Form API
validation checks processed values. The plugin select exposes a data-inside-plugin
attribute on the rebuilt container; tests wait for the selected form to finish
rebuilding before submitting, including plugins with no configuration fields.


## Media Library image example

`inside.media_image` uses Media Library Form Element 2.1.5 with single-image
cardinality, a caption and the standard Image media bundle. The selected media ID
is stored as an integer in component JSON. Rendering checks media and source-field
view access and bubbles media/access cache metadata; image-field rendering retains
alt text and Drupal image styles.

The contributed widget derives wrapper IDs from Form API parents, which include
the dotted component plugin ID. A post-processor normalizes only the generated
wrapper ID and matching AJAX callback wrapper values. The widget's original
selection/open/remove implementation is reused. Nested media picker closes do not
invalidate the component session, because its lifecycle listener only handles
`#drupal-off-canvas`.
