<?php

namespace Drupal\inside_ckeditor5_embedded_content\Plugin\EntityUsage\Track;

use Drupal\Component\Utility\Html;
use Drupal\Core\Entity\FieldableEntityInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\entity_usage\Attribute\EntityUsageTrack;
use Drupal\entity_usage\Plugin\EntityUsage\Track\TextFieldEmbedBase;
use Drupal\path_alias\AliasManagerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Tracks media used inside embedded image and video gallery content plugins.
 */
#[EntityUsageTrack(
  id: 'gallery_embedded_content',
  label: new TranslatableMarkup('Gallery Embedded Content'),
  description: new TranslatableMarkup('Tracks media entities referenced inside embedded image and video gallery plugins.'),
  field_types: ['text', 'text_long', 'text_with_summary'],
  source_entity_class: FieldableEntityInterface::class,
)]
class GalleryEmbeddedContent extends TextFieldEmbedBase {

  /**
   * Maps each embedded content plugin ID to the config keys that hold media paths.
   */
  const GALLERY_PLUGINS = [
    'imagegallery'  => ['image'],
    'videogallery'  => ['yt_video', 'ms_video'],
  ];

  /**
   * The path alias manager.
   *
   * @var \Drupal\path_alias\AliasManagerInterface
   */
  protected AliasManagerInterface $aliasManager;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    $instance = parent::create($container, $configuration, $plugin_id, $plugin_definition);
    $instance->aliasManager = $container->get('path_alias.manager');
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public function parseEntitiesFromText($text): array {
    $dom = Html::load($text);
    $xpath = new \DOMXPath($dom);
    $entities = [];

    foreach (static::GALLERY_PLUGINS as $plugin_id => $path_keys) {
      $query = '//embedded-content[@data-plugin-id="' . $plugin_id . '"]';

      foreach ($xpath->query($query) as $node) {
        assert($node instanceof \DOMElement);

        $raw_config = $node->getAttribute('data-plugin-config');
        if (empty($raw_config)) {
          continue;
        }

        $config = json_decode($raw_config, TRUE);
        $items = $config['items'] ?? [];

        foreach ($items as $item) {
          foreach ($path_keys as $key) {
            $media_path = $item[$key] ?? NULL;
            if (empty($media_path)) {
              continue;
            }

            $internal_path = $this->aliasManager->getPathByAlias($media_path);
            if (!preg_match('/^\/media\/(\d+)$/', $internal_path, $matches)) {
              continue;
            }

            $media = $this->entityTypeManager->getStorage('media')->load((int) $matches[1]);
            if ($media === NULL) {
              continue;
            }

            // Entity Usage expects [$uuid => $entity_type_id].
            $entities[$media->uuid()] = 'media';
          }
        }
      }
    }

    return $entities;
  }

}