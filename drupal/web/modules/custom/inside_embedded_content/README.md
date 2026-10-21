# Inside Embedded Content

Drupal 11 / CKEditor 5 component editing using Drupal's off-canvas tray.
See the repository README, docs/architecture.md and docs/testing.md for installation,
plugin authoring, implementation details and verified regression scenarios.

Original Embedded Content PHP plugin interfaces and HTML serialization are retained
under the Drupal\inside_embedded_content namespace. The new module uses independent
service, config-entity, filter, route, permission and CKEditor identifiers.
See ATTRIBUTION.md and LICENSE.txt for upstream attribution and GPL licensing.
