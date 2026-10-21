<?php

namespace Drupal\inside_ckeditor5_embedded_content\Plugin\EmbeddedContent;

use Drupal\ckeditor5_embedded_content\EmbeddedContentInterface;
use Drupal\ckeditor5_embedded_content\EmbeddedContentPluginBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\media\Entity\Media;
use Drupal\file\Entity\File;

/**
 * Plugin iframes.
 *
 * @EmbeddedContent(
 *   id = "galllery",
 *   label = @Translation("Media Gallery"),
 *   description = @Translation("Renders a Media Gallery."),
 * )
 */
class Gallery extends EmbeddedContentPluginBase implements EmbeddedContentInterface {

  use StringTranslationTrait;

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
    $type = $this->configuration['type'] ?? NULL;
    $items = $this->configuration['items'] ?? NULL;
    if (empty($items)) {
      return [
        '#type' => 'markup',
        '#markup' => $this->t('No items set.'),
      ];
    }
    foreach ($items as $delta => $item) {
      $mid = $item['image'];
      $media = Media::load($mid);
      $fid = $media->field_media_image->target_id;
      $file = File::load($fid);
      $url = $file->createFileUrl();
      $items[$delta] = [
        '#image' => $url,
        '#width' => $item['width']
      ];
    }
    return [
      '#theme' => 'inside_ckeditor5_embedded_content_gallery',
      '#aid' => $aid,
      '#type' => $type,
      '#items' => $items,
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state) {

    $items = $this->configuration['items'] ?? [];

    //usort($items, 'compareByWeight');

    if (empty($items)) {
      $items[] = [];
    }
    if ($triggeringElement = $form_state->getTriggeringElement()) {
      if (($triggeringElement['#op'] ?? '') == 'remove_item') {
        unset($items[$triggeringElement['#delta']]);
      }
      if (($triggeringElement['#op'] ?? '') == 'add_item') {
        $items[] = [];
      }
    }
    $raid = rand(10000,99999);
    $form['aid'] = [
      '#type' => 'hidden',
      '#value' => $raid,
      '#default_value' => $this->configuration['aid'],
    ];

    $form['type'] = [
      '#type' => 'select',
      '#title' => $this->t('Gallery Type'),
      '#options' => [
        'first' => $this->t('Mosaic'),
        'closed' => $this->t('Slider'),
      ],
      '#default_value' => $this->configuration['type'],
      '#required' => FALSE,
    ];


    $form['items'] = [
      '#type' => 'table',
      '#header' => [
        $this->t('Image'),
        $this->t('Width'),
        $this->t('Remove')
      ],
      '#empty' => $this->t('No items.'),
      '#tableselect' => FALSE,
      '#attributes' => [
        'id' => 'items-wrapper',
        'style' => 'min-width:800px',
      ],
    ];

    foreach ($items as $delta => $item) {

      $element = [
        '#type' => 'details',
        '#open' => FALSE,
        '#title' => 'Media Gallery Item',
      ];
      $element['image'] = [
        '#type' => 'media_library',
        '#allowed_bundles' => ['image'],
        '#title' => t('Upload your image'),
        '#default_value' => $this->configuration['items'][$delta]['image'] ?? NULL,
        '#description' => t('Upload or select your profile image.'),
      ];
      $element['width'] = [
        '#type' => 'select',
        '#title' => $this->t('image width'),
        '#options' => [
          '100' => $this->t('100'),
          '200' => $this->t('200'),
          '300' => $this->t('300'),
          '400' => $this->t('400')
        ],
        '#default_value' => $this->configuration['items'][$delta]['width'] ?? '200',
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
        ]
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
    return $form['config']['plugin_config']['items'];
  }

  public static function compareByWeight($a,$b) {
    return $a['weight']['#default_value'] - $b['weight']['#default_value'];
  }

}
