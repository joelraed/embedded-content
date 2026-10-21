<?php

namespace Drupal\inside_embedded_content_examples\Plugin\EmbeddedContent;

use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Component\Utility\Html;
use Drupal\Core\Form\FormStateInterface;
use Drupal\inside_embedded_content\EmbeddedContentPluginBase;
use Drupal\media\Entity\Media;
use Drupal\media_library_form_element\Element\MediaLibrary;

/**
 * An image selected or uploaded through a nested Media Library dialog.
 *
 * @EmbeddedContent(
 *   id = "inside.media_image",
 *   label = @Translation("Image from Media Library"),
 * )
 */
final class MediaImage extends EmbeddedContentPluginBase {
  public function isInline(): bool { return FALSE; }

  public function defaultConfiguration() { return ['image' => NULL, 'caption' => '']; }

  public function buildConfigurationForm(array $form, FormStateInterface $form_state) {
    $image = $this->configuration['image'];
    if (is_array($image)) {
      $image = $image['media_library_selection'] ?? NULL;
    }
    $form['image'] = [
      '#type' => 'media_library',
      '#value_callback' => [static::class, 'imageValue'],
      '#process' => array_merge(\Drupal::service('element_info')->getInfo('media_library')['#process'], [[static::class, 'normalizeWidgetWrapper']]),
      '#title' => $this->t('Image'),
      '#allowed_bundles' => ['image'],
      '#cardinality' => 1,
      '#default_value' => $image,
      '#description' => $this->t('Select an existing image or upload a new one.'),
    ];
    $form['#attached']['library'][] = 'inside_embedded_content_examples/media_image';
    $form['caption'] = [
      '#type' => 'textfield', '#title' => $this->t('Caption'),
      '#default_value' => $this->configuration['caption'],
    ];
    return $form;
  }

  /** Avoid Form API falling back to a raw array when the empty widget returns NULL. */
  public static function imageValue(array &$element, $input, FormStateInterface $form_state) {
    return MediaLibrary::valueCallback($element, $input, $form_state) ?? (($input === FALSE || $input === NULL) ? ($element['#default_value'] ?? '') : '');
  }

  /** Keep dotted plugin IDs out of CSS selectors used by widget AJAX updates. */
  public static function normalizeWidgetWrapper(array $element, FormStateInterface $form_state, array &$complete_form): array {
    $old_id = $element['#attributes']['id'];
    $new_id = Html::getId($old_id);
    $element['#attributes']['id'] = $new_id;
    $normalize = function (array &$part) use (&$normalize, $old_id, $new_id) {
      if (($part['#ajax']['wrapper'] ?? NULL) === $old_id) { $part['#ajax']['wrapper'] = $new_id; }
      foreach ($part as $key => &$child) {
        if (is_array($child) && !str_starts_with((string) $key, '#')) { $normalize($child); }
      }
    };
    $normalize($element);
    return $element;
  }

  public function getAttachments(): array {
    return ['library' => ['inside_embedded_content_examples/media_image']];
  }

  public function validateConfigurationForm(array &$form, FormStateInterface $form_state) {
    $id = $form_state->getValue('image');
    $media = is_scalar($id) && ctype_digit((string) $id) ? Media::load($id) : NULL;
    if (!$media || $media->bundle() !== 'image' || !$media->access('view')) {
      $form_state->setErrorByName('image', $this->t('Select an image you have permission to view.'));
    }
  }

  public function massageFormValues(array &$values, array $form, FormStateInterface $form_state) {
    $values = ['image' => (int) $values['image'], 'caption' => $values['caption'] ?? ''];
  }

  public function build(): array {
    $id = $this->configuration['image'];
    $media = is_scalar($id) && ctype_digit((string) $id) ? Media::load($id) : NULL;
    $build = ['#cache' => ['tags' => $id && is_scalar($id) ? ['media:' . $id] : []]];
    if (!$media || $media->bundle() !== 'image') { return $build; }
    $access = $media->access('view', NULL, TRUE);
    $field_name = $media->getSource()->getConfiguration()['source_field'];
    $field = $media->get($field_name);
    $access = $access->andIf($field->access('view', NULL, TRUE));
    CacheableMetadata::createFromObject($media)->merge(CacheableMetadata::createFromObject($access))->applyTo($build);
    if (!$access->isAllowed()) { return $build; }
    $build += [
      '#type' => 'html_tag', '#tag' => 'figure',
      '#attributes' => ['class' => ['inside-media-image']],
      'image' => $field->view(['label' => 'hidden', 'type' => 'image', 'settings' => ['image_style' => 'large', 'image_link' => '']]),
      'caption' => [
        '#type' => 'html_tag', '#tag' => 'figcaption',
        'text' => ['#plain_text' => $this->configuration['caption']],
        '#access' => $this->configuration['caption'] !== '',
      ],
    ];
    return $build;
  }
}
