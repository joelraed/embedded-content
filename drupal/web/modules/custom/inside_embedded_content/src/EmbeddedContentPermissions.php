<?php

namespace Drupal\inside_embedded_content;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\inside_embedded_content\Entity\EmbeddedContentButton;

/**
 * Provides permissions for embedded content.
 */
class EmbeddedContentPermissions {

  /**
   * Returns an array of embedded content permisssions.
   *
   * @return array
   *   The embedded content permissions.
   */
  public function getPermissions(): array {
    $permissions = [];
    foreach (EmbeddedContentButton::loadMultiple() as $button) {
      $permissions['use ' . $button->id() . ' embedded content button'] = [
        'title' => new TranslatableMarkup(
            'Use @label embedded content button',
            [
              '@label' => $button->label(),
            ]
        ),
      ];
    }
    return $permissions;
  }

}
