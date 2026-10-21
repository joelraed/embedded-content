<?php

namespace Drupal\inside_ckeditor5_embedded_content\Plugin\EmbeddedContent;

use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\embedded_content\EmbeddedContentInterface;
use Drupal\embedded_content\EmbeddedContentPluginBase;
use Drupal\file\Entity\File;
use Drupal\media\Entity\Media;

/**
 * Plugin iframes.
 *
 * @EmbeddedContent(
 *   id = "imagegallery",
 *   label = @Translation("Image Gallery Path"),
 *   description = @Translation("Renders a image gallery."),
 * )
 */
class ImageGallery extends EmbeddedContentPluginBase implements EmbeddedContentInterface {

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
      $path_alias_manager = \Drupal::service('path_alias.manager');
      $media_path = $item['image'] ?? '';
      $url = '';
      $mcredit = '';
      $mcaption = '';

      if ($media_path !== '') {
        $internal_path = $path_alias_manager->getPathByAlias($media_path);

        if (preg_match('/^\/media\/(\d+)$/', $internal_path, $matches)) {
          $media = Media::load($matches[1]);
          if ($media) {
            $fid = NULL;
            if ($media->hasField('field_media_image')) {
              $fid = $media->get('field_media_image')->target_id;
            }
            if (!$fid && $media->hasField('field_private_image')) {
              $fid = $media->get('field_private_image')->target_id;
            }

            if ($fid) {
              $file = File::load($fid);
              if ($file) {
                $url = $file->createFileUrl();
              }
            }

            if ($media->hasField('field_credit')) {
              $mcredit = $media->get('field_credit')->value ?? '';
            }
            if ($media->hasField('field_caption_html')) {
              $mcaption = $media->get('field_caption_html')->value ?? '';
            }
          }
        }
      }

      if ($item['usecaption'] === 1) {
        $caption = $mcaption;
      }
      else {
        $caption = $item['caption'] ?? '';
      }
      if ($item['usecredit'] === 1) {
        $credit = $mcredit;
      }
      else {
        $credit = $item['credit'] ?? '';
      }

      $items[$delta] = [
        '#image' => $url,
        '#credit' => $credit,
        '#caption' => $caption,
        'order' => $item['order'] ?? 0
      ];
    }
    return [
      '#theme' => 'inside_ckeditor5_embedded_content_imagegallery',
      '#aid' => $aid,
      '#width' => $width,
      '#items' => $items,
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state) {

    if (isset($this->configuration['items'])) {
      $items = $this->configuration['items'];
    }
    elseif (isset($this->configuration['imagegallery']['items'])) {
      $items = $this->configuration['imagegallery']['items'];
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
      '#title' => $this->t('Image Gallery Items'),
      '#attributes' => [
        'id' => 'items-wrapper',
        'style' => 'min-width:800px',
      ],
    ];

    foreach ($items as $delta => $item) {
      $element = [
        '#type' => 'details',
        '#open' => TRUE,
        '#title' => 'Image Item',
      ];
      $element['image'] = [
        '#type' => 'textfield',
        '#title' => $this->t('Enter the media path like... /media/title-of-media'),
        '#default_value' => $item['image'] ?? '',
        '#required' => FALSE,
      ];
      $element['caption'] = [
        '#type' => 'textfield',
        '#title' => $this->t('Enter a custom caption to be displayed for this image, otherwise leave it empty for no caption'),
        '#default_value' => $item['caption'] ?? '',
        '#required' => FALSE,
        '#states' => [
          'invisible' => [
            ':input[name="use"]' => ['checked' => TRUE],
          ],
        ],
      ];
      $element['usecaption'] = [
        '#type' => 'checkbox',
        '#title' => $this->t('Display the media default caption'),
        '#default_value' => $item['usecaption'] ?? 0,
        '#required' => FALSE,
      ];
      $element['credit'] = [
        '#type' => 'textfield',
        '#title' => $this->t('Enter a custom credit to be displayed for this image, otherwise leave it empty for no credit'),
        '#default_value' => $item['credit'] ?? '',
        '#required' => FALSE,
        '#states' => [
          'invisible' => [
            ':input[name="use"]' => ['checked' => TRUE],
          ],
        ],
      ];
      $element['usecredit'] = [
        '#type' => 'checkbox',
        '#title' => $this->t('Display the media default credit'),
        '#default_value' => $item['usecredit'] ?? 0,
        '#required' => FALSE,
      ];
      $element['order'] = [
        '#type' => 'number',
        '#title' => $this->t('Assign the order that this image item will appear'),
        '#default_value' => $item['order'] ?? 0,
        '#description' => $this->t('Give each image item a position number. Use numbers that increase by 10 each time so you can easily add or move items later. You can use both positive and negative numbers. Items with lower numbers will appear higher up.'),
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
    return $form['config']['plugin_config']['imagegallery']['items'];
  }

}
