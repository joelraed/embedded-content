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
