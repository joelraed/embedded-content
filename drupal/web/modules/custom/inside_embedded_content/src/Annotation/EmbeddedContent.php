<?php

namespace Drupal\inside_embedded_content\Annotation;

use Drupal\Component\Annotation\Plugin;

/**
 * Defines inside_embedded_content annotation object.
 *
 * @Annotation
 */
class EmbeddedContent extends Plugin {

  /**
   * The plugin ID.
   *
   * @var string
   */
  public $id;

  /**
   * The human-readable name of the plugin.
   *
   * @var \Drupal\Core\Annotation\Translation
   *
   * @ingroup plugin_translatable
   */
  public $title;

  /**
   * The description of the plugin.
   *
   * @var \Drupal\Core\Annotation\Translation
   *
   * @ingroup plugin_translatable
   */
  public $description;

}
