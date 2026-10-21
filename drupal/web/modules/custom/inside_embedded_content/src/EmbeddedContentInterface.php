<?php

namespace Drupal\inside_embedded_content;

use Drupal\Component\Plugin\ConfigurableInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Plugin\PluginFormInterface;

/**
 * Interface for embedded content render plugins.
 */
interface EmbeddedContentInterface extends PluginFormInterface, ConfigurableInterface {

  /**
   * Return the plugin id.
   *
   * @return string
   *   The id.
   */
  public function id();

  /**
   * Returns the translated plugin label.
   *
   * @return string
   *   The translated title.
   */
  public function label();

  /**
   * Render the submitted result of a webform element.
   *
   * @return array
   *   The render array.
   */
  public function build(): array;

  /**
   * Get the attachments used by the plugin.
   */
  public function getAttachments(): array;

  /**
   * Checks if the plugin is inline.
   *
   * @return bool
   *   TRUE if the plugin is inline.
   */
  public function isInline(): bool;

  /**
   * Massage the submitted values.
   *
   * @param array $values
   *   The submitted values.
   * @param array $form
   *   The form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   */
  public function massageFormValues(array &$values, array $form, FormStateInterface $form_state);

}
