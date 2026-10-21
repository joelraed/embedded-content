<?php

namespace Drupal\inside_embedded_content\Plugin\Deriver;

use Drupal\Component\Plugin\Derivative\DeriverBase;
use Drupal\ckeditor5\Plugin\CKEditor5PluginDefinition;
use Drupal\inside_embedded_content\Entity\EmbeddedContentButton;

/**
 * Provides a deriver for embedded content ckeditor5 plugins.
 */
class EmbeddedContentDeriver extends DeriverBase {

  /**
   * {@inheritdoc}
   */
  public function getDerivativeDefinitions($base_plugin_definition) {
    assert($base_plugin_definition instanceof CKEditor5PluginDefinition);
    foreach (EmbeddedContentButton::loadMultiple() as $button) {
      $this->derivatives[$button->id()] = new CKEditor5PluginDefinition($button->getPluginDefinition());
    }
    return $this->derivatives;
  }

}
