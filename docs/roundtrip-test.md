# Contributed module round-trip test — 2026-10-21

**Result: saved content survives; complete visual compatibility fails.**

Tested locally on Drupal 11 with Embedded Content 2.0.4, its examples 2.0.3, and the current Inside modules. Database backup: ignored `backups/roundtrip/before.sql.gz`. Final site uses Inside; contributed modules are uninstalled. Existing configuration was restored exactly, including editor settings, buttons, and roles. Admin credentials were not changed.

## Results

| Check | Result |
| --- | --- |
| Existing saved formatted text after switching to contrib | 26/26 unchanged (SHA-256 comparison of complete field values, including format) |
| Existing rendered output under contrib | 12 CTA differences; 10 missing custom-image plugin errors; 4 unchanged values |
| Existing saved text after returning to Inside | 26/26 unchanged |
| Existing rendered output after returning to Inside | 26/26 exactly matches original server-rendered HTML |
| New contrib-authored page | Node 28, “Contributed module round-trip test” |
| New page components | Call to action, counter starting at 17, inline footnote; all inserted through the contributed CKEditor dialog |
| New page stored text after switching to Inside | Identical, including plugin configuration and text format ID |
| New page rendering under Inside | CTA styling differs; counter still starts at 17; footnote preserved |
| New page editor under Inside | Widgets load; CTA opens in off-canvas with original field value |
| Existing Media Library image after restoration | Browser confirms image is visible and has positive natural width |

The four unchanged baseline values use a pre-existing format with no enabled embedded-content filter. They stayed unchanged; this does not establish successful rendering for those pages.

## Compatibility gaps

The contributed CTA renders a blue, rounded, padded link with white text and a 2rem font. The custom CTA renderer emits a link with `inside-call-to-action` instead of that inline styling. This changes appearance in both switch directions.

`inside.media_image` is custom-only. The original module cannot resolve it and renders a missing-plugin error. The image components render normally after Inside is restored.

The counter receives an additional `inside-counter` class under Inside. Its initial number is preserved. Contrib emits nested `<body>` wrappers from its filter; Inside emits document fragments. Server HTML is therefore not byte-identical for the new page even apart from CTA styling. This test checks initial counter content, not timing equivalence of live counter animations.

## Configuration matters

Drupal 11 requires uninstalling these modules rather than simply disabling them. Active text filters prevent uninstall. This test temporarily removed filter and toolbar references, uninstalled the current module and examples, installed the other module and examples, then explicitly mapped filter and toolbar identifiers while preserving text format IDs and all node bodies.

For the newly created `legacy_roundtrip` format, `embedded_content` was mapped to `inside_embedded_content`, and `embeddedContent__default` to `insideEmbeddedContent__default`. This was test setup, not an automatic migration feature. A bare uninstall/install is not sufficient for a production migration; custom buttons, permissions, and third-party plugin implementations also need attention.

## Test artifacts and reproducibility

`scripts/roundtrip.php` captures configuration and formatted text, neutralizes provider references, maps test formats, and restores original configuration. It writes snapshots under ignored `drupal/private/roundtrip/`. Its stages are ordered: `capture`, `neutral-inside`, uninstall Inside, install contrib, `contrib-config`, browser create phase, `contrib-capture`, `neutral-contrib`, uninstall contrib, install Inside, `restore-inside`, cache rebuild, `final-capture`, browser verify phase. Run each PHP stage through Drush with `ROUNDTRIP_STAGE` set, and stop on any failure. Always take a full database backup first. The capture guard prevents overwriting an existing run.

`tests/roundtrip.spec.js` has explicit `ROUNDTRIP_BROWSER_PHASE=create` and `verify` phases, passed into the browser container with `docker compose run -e`. Both completed successfully. It deliberately records the known CTA mismatch rather than treating the visual parity requirement as passed. Without a phase it skips. Private snapshots and screenshots are retained locally and not committed.

Screenshots of node 28 were captured under each provider. No module rendering code was changed during the test. The preserved page is available at http://localhost:8088/node/28 and its editor at http://localhost:8088/node/28/edit.
