<?php

declare(strict_types=1);

namespace Drupal\inside_embedded_content\Plugin\CKEditor5Plugin;

use Drupal\Core\Access\CsrfTokenGenerator;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Url;
use Drupal\ckeditor5\Plugin\CKEditor5PluginDefault;
use Drupal\ckeditor5\Plugin\CKEditor5PluginDefinition;
use Drupal\editor\EditorInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Plugin class to add dialog url for embedded content.
 */
class EmbeddedContent extends CKEditor5PluginDefault implements ContainerFactoryPluginInterface {

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;
  /**
   * The CSRF token generator.
   *
   * @var \Drupal\Core\Access\CsrfTokenGenerator
   */
  protected $csrfTokenGenerator;

  /**
   * DrupalEntity constructor.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin_id for the plugin instance.
   * @param \Drupal\ckeditor5\Plugin\CKEditor5PluginDefinition $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The Entity Type Manager service.
   * @param \Drupal\Core\Access\CsrfTokenGenerator $csrf_token_generator
   *   The CSRF token generator.
   */
  public function __construct(
    array $configuration,
    string $plugin_id,
    CKEditor5PluginDefinition $plugin_definition,
    EntityTypeManagerInterface $entity_type_manager,
    CsrfTokenGenerator $csrf_token_generator,
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->entityTypeManager = $entity_type_manager;
    $this->csrfTokenGenerator = $csrf_token_generator;
  }

  /**
   * {@inheritDoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
          $configuration,
          $plugin_id,
          $plugin_definition,
          $container->get('entity_type.manager'),
          $container->get('csrf_token')
      );
  }

  /**
   * {@inheritdoc}
   */
  public function getDynamicPluginConfig(array $static_plugin_config, EditorInterface $editor): array {
    // Register embed buttons as individual buttons on admin pages.
    $dynamic_plugin_config = $static_plugin_config;
    $inside_embedded_content_buttons = $this
      ->entityTypeManager
      ->getStorage('inside_embedded_content_button')
      ->loadMultiple();

    $url = Url::fromRoute(
          'inside_embedded_content.preview', [
            'editor' => $editor->id(),
          ]
      );
    $token = $this->csrfTokenGenerator->get($url->getInternalPath());
    $url->setOptions(['query' => ['token' => $token]]);
    $dynamic_plugin_config['insideEmbeddedContent']['previewUrl'] = $url->toString();
    /**
* @var \Drupal\inside_embedded_content\Entity\EmbeddedContentButtonInterface $inside_embedded_content_button
*/
    foreach ($inside_embedded_content_buttons as $inside_embedded_content_button) {
      $dynamic_plugin_config['insideEmbeddedContent']['buttons'][$inside_embedded_content_button->id()] = [
        'label' => $inside_embedded_content_button->label(),
        'iconUrl' => $inside_embedded_content_button->getIconUrl(),
        'dialogUrl' => Url::fromRoute(
            'inside_embedded_content.dialog', [
              'inside_embedded_content_button' => $inside_embedded_content_button->id(),
              'filter_format' => $editor->getFilterFormat()->id(),
            ]
        )->toString(),
        'dialogSettings' => $inside_embedded_content_button->getDialogSettings(),
      ];
    }
    return $dynamic_plugin_config;
  }

}
