<?php

namespace Drupal\inside_embedded_content_examples\Plugin\EmbeddedContent;

use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\inside_embedded_content\EmbeddedContentInterface;
use Drupal\inside_embedded_content\EmbeddedContentPluginBase;

/**
 * Renders a list that is styled.
 *
 * @EmbeddedContent(
 *   id = "example.styled_list",
 *   label = @Translation("Styled list"),
 * )
 */
class StyledList extends EmbeddedContentPluginBase implements EmbeddedContentInterface {

  const GREEN = 'green';
  const RED = 'red';

  use StringTranslationTrait;

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration() {
    return [
      'items' => NULL,
      'style' => NULL,
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function build(): array {
    $items = $this->configuration['items'] ?? NULL;
    if (empty($items)) {
      return [
        '#type' => 'markup',
        '#markup' => $this->t('No items set.'),
      ];
    }
    foreach ($items as $delta => $item) {
      $items[$delta] = [
        '#type' => 'processed_text',
        '#text' => $item['body']['value'],
        '#format' => $item['body']['format'],
      ];
    }
    return [
      '#theme' => 'inside_embedded_content_styled_list',
      '#items' => $items,
      '#style' => $this->configuration['style'] ?? NULL,
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state) {

    $items = $form_state->get('inside_styled_ids');
    if ($items === NULL) {
      $items = array_keys($this->configuration['items'] ?: ['item_0' => []]);
      $form_state->set('inside_styled_ids', $items);
    }

    $form['style'] = [
      '#type' => 'select',
      '#title' => $this->t('Style'),
      '#options' => [
        self::GREEN => $this->t('Green'),
        self::RED => $this->t('Red'),
      ],
      '#default_value' => $this->configuration['style'],
    ];

    $form['items'] = [
      '#type' => 'container',
      '#attributes' => [
        'id' => 'inside-styled-items-wrapper',
      ],
    ];

    foreach ($items as $delta) {
      $element = [
        '#type' => 'details',
        '#open' => TRUE,
        '#title' => 'Item',
      ];
      $element['body'] = [
        '#type' => 'text_format',
        '#title' => $this->t('Body'),
        '#format' => $this->configuration['items'][$delta]['body']['format'] ?? 'basic_html',
        '#default_value' => $this->configuration['items'][$delta]['body']['value'] ?? '',
        '#required' => TRUE,
      ];
      $element['remove_item'] = [
        '#type' => 'submit',
        '#limit_validation_errors' => [],
        '#value' => $this->t('Remove item'),
        '#delta' => $delta,
        '#op' => 'remove_item',
        '#submit' => [[static::class, 'removeItem']],
        '#name' => 'remove_item_' . $delta,
        '#ajax' => [
          'wrapper' => 'inside-embedded-content-form-' . $form['#button']->id(),
          'callback' => [static::class, 'updateItems'],
        ],
      ];
      $form['items'][$delta] = $element;
    }

    $form['add_item'] = [
      '#type' => 'submit',
      '#limit_validation_errors' => [],
      '#value' => $this->t('Add item'),
      '#name' => 'add_item',
      '#op' => 'add_item',
      '#submit' => [[static::class, 'addItem']],
      '#ajax' => [
        'wrapper' => 'inside-embedded-content-form-' . $form['#button']->id(),
        'callback' => [static::class, 'updateItems'],
      ],
    ];
    return $form;
  }

  public static function addItem(array &$form, FormStateInterface $form_state) {
    $ids = $form_state->get('inside_styled_ids');
    $ids[] = 'item_' . bin2hex(random_bytes(6));
    $form_state->set('inside_styled_ids', $ids)->setRebuild();
  }

  public static function removeItem(array &$form, FormStateInterface $form_state) {
    $delta = $form_state->getTriggeringElement()['#delta'];
    $form_state->set('inside_styled_ids', array_values(array_diff($form_state->get('inside_styled_ids'), [$delta])))->setRebuild();
  }

  public function massageFormValues(array &$values, array $form, FormStateInterface $form_state) {
    unset($values['add_item']);
    if (isset($values['items'])) {
      foreach ($values['items'] as &$item) { unset($item['remove_item']); }
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
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function isInline(): bool {
    return FALSE;
  }

}
