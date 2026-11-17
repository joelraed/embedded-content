Inside CKeditor
=============================

Provides Embedded content components for Video, Image, Accordions and Alert Messages.

Local Inside integration
------------------------

This copy uses the `inside_embedded_content` interface and base class instead of
`embedded_content`. Plugin IDs and saved configuration keys are unchanged.
Dependencies include Linkit and Media Library Form Element. The gallery caption
includes its original theme template when available, with a module-local fallback
for sites without the `@atoms` Twig namespace. The JavaScript library declares
its Drupal and jQuery dependencies.

The local test setup is `scripts/custom-plugin-setup.php`, run through Drush.
It creates the expected `embed_content` body format and `admin` Linkit profile,
plus an MS Stream bundle/field scaffold and a remote-video fixture if missing.
It retains existing media and writes the selected fixture IDs to private storage.
`DELETE_TEST_PAGES=yes` explicitly deletes all nodes; take a database backup before
using that option. Without the flag, setup does not delete content.

Run `scripts/browser-test.sh custom-plugins` after setup. The six browser tests
create, save, render, and reopen each plugin; the accordion test exercises adding
a second item, and the video test selects existing remote-video media through the
nested Media Library. Image tests verify actual image loading. Video tests verify
iframe output, not external playback or authenticated MS Stream integration.

The local Olivero theme does not include the original site's CTA/status/grid CSS
or Bootstrap collapse JavaScript. Component markup and content are tested here;
full theme styling and accordion expansion need the original theme/assets. The
standalone MS Stream scaffold is not a production source configuration.

Core Insert Media in plugin rich text
------------------------------------

Run `scripts/enable-plugin-media-embed.php` through Drush to enable Drupal's
standard `drupalMedia` toolbar button and `media_embed` text filter on
`embed_content`. The HTML filter must allow the `<drupal-media>` tag and its
entity UUID/type attributes. The setup validates CKEditor configuration and
retains the previous settings in private storage. The editor role also needs
permission to use this text format.

Run `scripts/browser-test.sh plugin-media-embed` for the nested rich-text media
workflow. Both the Message and Accordion plugins passed on 2026-10-23: select an
existing image through core Media Library, cancel another library dialog without
losing content, embed the component, save the page, reopen the component, and save
again. The test checks actual image loading and that the nested CKEditor media
markup stays identical after reopening. Accordion bodies must select the
“Embedded component body” format to use this configuration. These tests exercise
core Insert Media, separately from the custom `media_library` Form API widget.

Image Gallery with Media Library Form Element
--------------------------------------------

The `imagegallery` plugin is labelled “Image Gallery.” Each item uses a
single-image `media_library` Form API element, allowing existing-image selection
or upload through a nested Media Library dialog. Image media bundles are detected
from their image source plugin, including custom image bundles. Captions, credits,
media-default toggles, ordering, and column width remain available. Stable form
item keys keep add/remove operations and widget updates tied to the correct item.

Newly saved `items[*].image` values are integer media IDs. Existing canonical
`/media/123` paths and aliases still render and populate the selector when editing;
saving the component converts those references to IDs. The plugin ID is unchanged.
Entity Usage tracking accepts both representations when that optional module is
installed. Rendering uses the source image's alt text and includes media access
and cache metadata.

For the gallery regression suite, first run `scripts/gallery-legacy-fixture.php`
through Drush to create a fresh legacy-path fixture, then run
`scripts/browser-test.sh image-gallery`. Tests cover upload, distinct existing
images in multiple widgets, middle/last item removal, widget removal/reselection,
nested cancellation, save/reopen, actual image loading, and legacy alias conversion.
The same upload/selection test can run with
`BROWSER_TEST_ACCOUNT=inside_editor_regression` and `--grep 'upload, select'`.
