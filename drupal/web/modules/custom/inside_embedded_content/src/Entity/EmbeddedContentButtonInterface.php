<?php

declare(strict_types=1);

namespace Drupal\inside_embedded_content\Entity;

use Drupal\Core\Config\Entity\ConfigEntityInterface;

/**
 * Provides an interface defining an embedded content entity type.
 */
interface EmbeddedContentButtonInterface extends ConfigEntityInterface {

  /**
   * Get the button icon SVG.
   *
   * @return string
   *   The svg HTML code.
   */
  public function getIconSvg(): string;

  /**
   * Get the button conditions.
   *
   * @return array
   *   The conditions.
   */
  public function getConditions(): array;

  /**
   * Checks if the conditions set in the button are met.
   *
   * @return bool
   *   TRUE if the conditions are met, FALSE otherwise.
   */
  public function meetsCondition(string $plugin_id): bool;

  /**
   * Gets the icon URL.
   *
   * @return string
   *   The relative icon url.
   */
  public function getIconUrl(): string;

  /**
   * Builds up the menu link plugin definition for this entity.
   *
   * @return array
   *   The plugin definition corresponding to this entity.
   *
   * @see \Drupal\Core\Menu\MenuLinkTree::$defaults
   */
  public function getPluginDefinition();

  /**
   * Get the dialog settings.
   *
   * @return array
   *   The dialog settings.
   */
  public function getDialogSettings(): array;

  /**
   * Get a specific dialog setting.
   *
   * @return string|null
   *   The setting.
   */
  public function getDialogSetting(string $setting):? string;

  /**
   * Get the administrative label.
   *
   * @return string
   *   The label.
   */
  public function getLabel(): string;

  /**
   * Get a specific button setting.
   *
   * @param array|string $value
   *   Array if nested value is required. String otherwise.
   *
   * @return mixed
   *   the specific setting.
   */
  public function getSetting(array|string $value):mixed;

  /**
   * Get the button settings.
   *
   * @return array
   *   The button settings.
   */
  public function getSettings(): array;

  /**
   * Get the singular label for the button.
   *
   * @return string
   *   The singular label.
   */
  public function getSingularLabel(): string;

  /**
   * Get the submit button text.
   *
   * @return string|null
   *   The submit button text if any.
   */
  public function getSubmitButtonText():? string;

  /**
   * Get the modal title.
   *
   * @return string|null
   *   The modal title if any.
   */
  public function getModalTitle():? string;

}
