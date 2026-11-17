<?php

namespace Drupal\inside_ckeditor5_embedded_content\Plugin\EmbeddedContent;

use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\inside_embedded_content\EmbeddedContentInterface;
use Drupal\inside_embedded_content\EmbeddedContentPluginBase;
use Drupal\Core\Cache\CacheableMetadata;
use Drupal\media_library_form_element\Element\MediaLibrary;
use Drupal\media\Entity\Media;

/**
 * Plugin iframes.
 *
 * @EmbeddedContent(
 *   id = "imagegallery",
 *   label = @Translation("Image Gallery"),
 *   description = @Translation("Renders an image gallery."),
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
    $metadata = new CacheableMetadata();
    foreach ($items as $delta => $item) {
      $url = $mcredit = $mcaption = $description = '';
      $media = Media::load(static::mediaId($item['image'] ?? '') ?: 0);
      if ($media && $media->getSource()->getPluginId() === 'image') {
        $access = $media->access('view', NULL, TRUE);
        $metadata->addCacheableDependency($media)->addCacheableDependency($access);
        $field_name = $media->getSource()->getConfiguration()['source_field'];
        $field = $media->get($field_name);
        $field_access = $field->access('view', NULL, TRUE);
        $metadata->addCacheableDependency($field_access);
        if ($access->isAllowed() && $field_access->isAllowed() && $field->entity) {
          $metadata->addCacheableDependency($field->entity);
          $url = $field->entity->createFileUrl();
          $description = $field->alt ?? '';
          $mcredit = $media->hasField('field_credit') ? ($media->get('field_credit')->value ?? '') : '';
          $mcaption = $media->hasField('field_caption_html') ? ($media->get('field_caption_html')->value ?? '') : '';
        }
      }
      // Deleted or inaccessible media must not produce broken or private images.
      if ($url === '') {
        unset($items[$delta]);
        continue;
      }

      if (!empty($item['usecaption'])) {
        $caption = $mcaption;
      }
      else {
        $caption = $item['caption'] ?? '';
      }
      if (!empty($item['usecredit'])) {
        $credit = $mcredit;
      }
      else {
        $credit = $item['credit'] ?? '';
      }

      $items[$delta] = [
        '#image' => $url,
        '#description' => $description,
        '#credit' => $credit,
        '#caption' => $caption,
        'order' => $item['order'] ?? 0
      ];
    }
    $build = [
      '#theme' => 'inside_ckeditor5_embedded_content_imagegallery',
      '#aid' => $aid,
      '#width' => $width,
      '#items' => $items,
    ];
    $metadata->applyTo($build);
    return $build;
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state) {

    $items = $this->configuration['items'] ?? $this->configuration['imagegallery']['items'] ?? [];
    $keys = $form_state->get('custom_imagegallery_item_keys');
    if ($keys === NULL) {
      uasort($items, fn($a, $b) => ($a['order'] ?? 0) <=> ($b['order'] ?? 0));
      $keys = array_keys($items ?: [0 => []]);
      $form_state->set('custom_imagegallery_item_keys', $keys);
    }
    $bundles = [];
    foreach (\Drupal::entityTypeManager()->getStorage('media_type')->loadMultiple() as $id => $type) {
      if ($type->getSource()->getPluginId() === 'image') { $bundles[] = $id; }
    }

    $form['#attached']['library'][] = 'inside_ckeditor5_embedded_content/image_gallery_form';
    $form['width'] = [
      '#type' => 'select',
      '#title' => $this->t('Column Width'),
      '#options' => [
        '25' => $this->t('25% - 4 column layout'),
        '33' => $this->t('33% - 3 column layout'),
        '50' => $this->t('50% - 2 column layout'),
      ],
      '#default_value' => $this->configuration['width'] ?? '50',
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

    foreach ($keys as $delta) {
      $item = $items[$delta] ?? [];
      $element = [
        '#type' => 'details',
        '#open' => TRUE,
        '#title' => 'Image Item',
      ];
      $element['image'] = [
        '#type' => 'media_library',
        '#title' => $this->t('Image'),
        '#allowed_bundles' => $bundles,
        '#cardinality' => 1,
        '#value_callback' => [static::class, 'mediaValue'],
        '#default_value' => static::mediaId($item['image'] ?? '') ?: '',
        '#description' => $this->t('Select an image or upload one through the Media Library.'),
      ];
      $element['caption'] = [
        '#type' => 'textfield',
        '#title' => $this->t('Enter a custom caption to be displayed for this image, otherwise leave it empty for no caption'),
        '#default_value' => $item['caption'] ?? '',
        '#required' => FALSE,
        '#states' => [
          'invisible' => [
            ':input[name="config[plugin_config][imagegallery][items][' . $delta . '][usecaption]"]' => ['checked' => TRUE],
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
            ':input[name="config[plugin_config][imagegallery][items][' . $delta . '][usecredit]"]' => ['checked' => TRUE],
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
        '#type' => 'submit',
        '#limit_validation_errors' => [],
        '#submit' => [[static::class, 'removeItem']],
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
      '#type' => 'submit',
      '#limit_validation_errors' => [],
      '#submit' => [[static::class, 'addItem']],
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

  /** Accept newly selected IDs as well as legacy canonical and aliased paths. */
  public static function mediaId($value): ?int {
    if (is_array($value)) { $value = $value['media_library_selection'] ?? ''; }
    if (!is_scalar($value) || (string) $value === '') { return NULL; }
    if (ctype_digit((string) $value)) { return (int) $value ?: NULL; }
    if (!str_starts_with((string) $value, '/')) { return NULL; }
    $path = \Drupal::service('path_alias.manager')->getPathByAlias((string) $value);
    return preg_match('@^/media/(\d+)$@', $path, $matches) ? (int) $matches[1] : NULL;
  }

  /** Keep empty widgets scalar rather than falling back to raw submitted arrays. */
  public static function mediaValue(array &$element, $input, FormStateInterface $form_state) {
    return MediaLibrary::valueCallback($element, $input, $form_state) ?? '';
  }

  public static function addItem(array &$form, FormStateInterface $form_state): void {
    $keys = $form_state->get('custom_imagegallery_item_keys');
    $keys[] = 'item_' . bin2hex(random_bytes(6));
    $form_state->set('custom_imagegallery_item_keys', $keys)->setRebuild();
  }

  public static function removeItem(array &$form, FormStateInterface $form_state): void {
    $key = $form_state->getTriggeringElement()['#delta'];
    $keys = array_values(array_filter($form_state->get('custom_imagegallery_item_keys'), fn($candidate) => $candidate !== $key));
    $form_state->set('custom_imagegallery_item_keys', $keys)->setRebuild();
  }

  public function validateConfigurationForm(array &$form, FormStateInterface $form_state) {
    foreach ($form_state->getValue('items') ?? [] as $key => $item) {
      $media = Media::load(static::mediaId($item['image'] ?? '') ?: 0);
      if (!$media || $media->getSource()->getPluginId() !== 'image' || !$media->access('view')) {
        $form_state->setError($form['items'][$key]['image'], $this->t('Select an image you have permission to view.'));
      }
    }
  }

  public function massageFormValues(array &$values, array $form, FormStateInterface $form_state) {
    unset($values['add_item']);
    $values['items'] = array_values($values['items'] ?? []);
    foreach ($values['items'] as &$item) {
      $item['image'] = static::mediaId($item['image']);
      unset($item['remove_item']);
    }
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
