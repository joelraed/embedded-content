<?php

namespace Drupal\inside_ckeditor5_embedded_content\Plugin\EmbeddedContent;

use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\inside_embedded_content\EmbeddedContentInterface;
use Drupal\inside_embedded_content\EmbeddedContentPluginBase;
use Drupal\media\Entity\Media;

/**
 * Plugin iframes.
 *
 * @EmbeddedContent(
 *   id = "videogallery",
 *   label = @Translation("Video Gallery Path"),
 *   description = @Translation("Renders a video gallery."),
 * )
 */
class VideoGalleryPath extends EmbeddedContentPluginBase implements EmbeddedContentInterface {

  use StringTranslationTrait;

  public function isInline(): bool {
    return FALSE;
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration() {
    return [
      'type' => NULL,
      'items' => NULL,
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function build(): array {
    $aid = $this->configuration['aid'] ?? NULL;
    $width = $this->configuration['width'] ?? NULL;
    $items = $this->configuration['items'] ?? NULL;
    if (empty($items)) {
      return [
        '#type' => 'markup',
        '#markup' => $this->t('No items set.'),
      ];
    }
    foreach ($items as $delta => $item) {
      $yvid = $item['yt_video'] ?? '';
      $msvid = $item['ms_video'] ?? '';
      $path_alias_manager = \Drupal::service('path_alias.manager');

      $video = NULL;
      $title = NULL;
      $type = NULL;

      if ($yvid != '') {
        $type = "youtube";
        $media_path = $yvid;
        $internal_path = $path_alias_manager->getPathByAlias($media_path);
        if (preg_match('/^\/media\/(\d+)$/', $internal_path, $matches)) {
          $media_id = $matches[1];
        }
        else {
          $media_id = null;
        }

        if ($media_id) {
          $ymedia = Media::load($media_id);
          if ($ymedia) {
            $video = \Drupal::service('entity_type.manager')->getViewBuilder('media')->view($ymedia, '100_width');
            $title = $ymedia->getName();
          }
        }
      }
      else if ($msvid != '') {
        $type = "msstream";
        $media_path = $msvid;
        $internal_path = $path_alias_manager->getPathByAlias($media_path);
        if (preg_match('/^\/media\/(\d+)$/', $internal_path, $matches)) {
          $media_id = $matches[1];
        }
        else {
          $media_id = null;
        }

        if ($media_id) {
          $msmedia = Media::load($media_id);
          if ($msmedia && $msmedia->hasField('field_ms_stream_embed_iframe')) {
            $msurl = $msmedia->get('field_ms_stream_embed_iframe')->value;
            preg_match('/<iframe[^>]+src=\"([^\"]+)\"/', $msurl, $matches);

            if (isset($matches[1])) {
              $video = $matches[1];
            }
            else {
              $video = $msurl;
            }

            $title = $msmedia->getName();
          }
        }
      }

      $items[$delta] = [
        '#video' => $video,
        '#title' => $title,
        '#type' => $type,
        'order' => $item['order'] ?? 0
      ];
    }
    return [
      '#theme' => 'inside_ckeditor5_embedded_content_video_gallery',
      '#aid' => $aid,
      '#items' => $items,
      '#rid' => rand(10000, 99999),
      '#gwidth' => '100',
      '#width' => $this->configuration['width'] ?? NULL,
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state) {

    if (isset($this->configuration['items'])) {
      $items = $this->configuration['items'];
    }
    elseif (isset($this->configuration['videogallery']['items'])) {
      $items = $this->configuration['videogallery']['items'];
    }
    else {
      $items = [];
    }

    usort($items, function ($a, $b) { return ($a['order'] ?? 0) - ($b['order'] ?? 0); });

    if (empty($items)) {
      $items[] = [];
    }
    if (!$form_state->get('items')) {
      $form_state->set('items', $items);
    }
    else {
      $items = $form_state->get('items');
    }

    if ($triggeringElement = $form_state->getTriggeringElement()) {
      if (($triggeringElement['#op'] ?? '') == 'remove_item') {
        unset($items[$triggeringElement['#delta']]);
        $items = array_values($items);
        $form_state->set('items', $items);
      }
      if (($triggeringElement['#op'] ?? '') == 'add_item') {
        $items[] = [];
        $form_state->set('items', $items);
      }
    }

    $form['width'] = [
      '#type' => 'select',
      '#title' => $this->t('Column Width'),
      '#options' => [
        '25' => $this->t('25% - 4 column layout'),
        '33' => $this->t('33% - 3 column layout'),
        '50' => $this->t('50% - 2 column layout'),
      ],
      '#default_value' => $this->configuration['width'] ?? '20',
      '#required' => FALSE,
    ];

    $raid = $this->configuration['aid'] ?? rand(10000, 99999);
    $form['aid'] = [
      '#type' => 'hidden',
      '#value' => $raid,
      '#default_value' => $this->configuration['aid'] ?? $raid,
    ];

    $form['items'] = [
      '#type' => 'fieldset',
      '#tree' => TRUE,
      '#title' => $this->t('Video Gallery Items'),
      '#attributes' => [
        'id' => 'items-wrapper',
        'style' => 'min-width:800px',
      ],
    ];

    foreach ($items as $delta => $item) {
      $element = [
        '#type' => 'details',
        '#open' => TRUE,
        '#title' => 'Video Item',
      ];
      $element['yt_video'] = [
        '#type' => 'textfield',
        '#title' => $this->t('Add a YouTube Video Media Path'),
        '#default_value' => $item['yt_video'] ?? '',
        '#description' => $this->t('Media path format /media/name-of-video'),
      ];
      $element['ms_video'] = [
        '#type' => 'textfield',
        '#title' => $this->t('Add a MS Stream Video Media Path'),
        '#default_value' => $item['ms_video'] ?? '',
        '#description' => $this->t('Media path format /media/name-of-video'),
      ];
      $element['order'] = [
        '#type' => 'number',
        '#title' => $this->t('Assign the order that this video item will appear'),
        '#default_value' => $item['order'] ?? 0,
        '#description' => $this->t('Give each video item a position number. Use numbers that increase by 10 each time so you can easily add or move items later. You can use both positive and negative numbers. Items with lower numbers will appear higher up.'),
        '#required' => FALSE,
      ];
      $element['remove_item'] = [
        '#type' => 'button',
        '#limit_validation_errors' => [],
        '#value' => $this->t('Remove item'),
        '#delta' => $delta,
        '#op' => 'remove_item',
        '#name' => 'remove_item_' . $delta,
        '#ajax' => [
          'wrapper' => 'items-wrapper',
          'callback' => [static::class, 'updateItems'],
        ],
      ];
      $form['items'][$delta] = $element;
    }

    $form['add_item'] = [
      '#type' => 'button',
      '#limit_validation_errors' => [],
      '#value' => $this->t('Add item'),
      '#name' => 'add_item',
      '#op' => 'add_item',
      '#ajax' => [
        'wrapper' => 'items-wrapper',
        'callback' => [static::class, 'updateItems'],
      ],
    ];
    return $form;
  }

  /**
   * Update items form element.
   *
   * @param array $form
   *   The form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   *
   * @return array
   *   The form element.
   */
  public static function updateItems(array &$form, FormStateInterface $form_state) {
    return $form['config']['plugin_config']['videogallery']['items'];
  }

}
