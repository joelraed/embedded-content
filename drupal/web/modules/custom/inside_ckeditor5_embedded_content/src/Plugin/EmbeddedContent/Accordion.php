<?php

namespace Drupal\inside_ckeditor5_embedded_content\Plugin\EmbeddedContent;

use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\inside_embedded_content\EmbeddedContentInterface;
use Drupal\inside_embedded_content\EmbeddedContentPluginBase;

/**
 * Plugin iframes.
 *
 * @EmbeddedContent(
 *   id = "accordion",
 *   label = @Translation("Accordion"),
 *   description = @Translation("Renders an accordion."),
 * )
 */
class Accordion extends EmbeddedContentPluginBase implements EmbeddedContentInterface {

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
    $type = $this->configuration['type'] ?? NULL;
    $level = $this->configuration['level'] ?? NULL;
    $items = $this->configuration['items'] ?? NULL;
    if (empty($items)) {
      return [
        '#type' => 'markup',
        '#markup' => $this->t('No items set.'),
      ];
    }
    foreach ($items as $delta => $item) {
      $items[$delta] = [
        '#head' => $item['head'] ?? '',
        '#text' => [
          '#type' => 'processed_text',
          '#text' => $item['body']['value'] ?? '',
          '#format' => $item['body']['format'] ?? 'basic_html',
        ],
        'order' => $item['order'] ?? 0
      ];
    }
    return [
      '#theme' => 'inside_ckeditor5_embedded_content_accordion',
      '#aid' => $aid,
      '#type' => $type,
      '#level' => $level,
      '#items' => $items,
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state) {

    $items = $this->configuration['items'] ?? $this->configuration['accordion']['items'] ?? [];
    $keys = $form_state->get('custom_accordion_item_keys');
    if ($keys === NULL) {
      // Sort only when opening the dialog; preserve keys throughout AJAX rebuilds.
      uasort($items, fn($a, $b) => ($a['order'] ?? 0) <=> ($b['order'] ?? 0));
      $keys = array_keys($items ?: [0 => []]);
      $form_state->set('custom_accordion_item_keys', $keys);
    }

    $raid = $this->configuration['aid'] ?? rand(10000, 99999);
    $form['aid'] = [
      '#type' => 'hidden',
      '#value' => $raid,
      '#default_value' => $this->configuration['aid'] ?? $raid,
    ];

    $form['type'] = [
      '#type' => 'select',
      '#title' => $this->t('Accordion Default Display Style'),
      '#options' => [
        'first' => $this->t('First Item Open'),
        'closed' => $this->t('All Items Closed'),
      ],
      '#default_value' => $this->configuration['type'] ?? 'closed',
      '#required' => FALSE,
    ];

    $form['level'] = [
      '#type' => 'select',
      '#title' => $this->t('Accordion Item Title Heading Level'),
      '#options' => [
        'h2' => $this->t('H2'),
        'h3' => $this->t('H3'),
        'h4' => $this->t('H4'),
        'h5' => $this->t('H5'),
        'h6' => $this->t('H6'),
      ],
      '#default_value' => $this->configuration['level'] ?? 'h3',
      '#description' => $this->t('Ensure the Accordion Item Title\'s Heading Level aligns with the page content hierarchy. The default is H3, suitable if the accordion is under an H2. Adjust as needed for consistency.'),
      '#required' => FALSE,
    ];

    $form['items'] = [
      '#type' => 'fieldset',
      '#tree' => TRUE,
      '#title' => $this->t('Accordion Items'),
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
        '#title' => 'Accordion Item',
      ];
      $element['head'] = [
        '#type' => 'textarea',
        '#rows' => 3,
        '#resizable' => TRUE,
        '#title' => $this->t('Accordion Item Title'),
        '#default_value' => $item['head'] ?? '',
        '#required' => FALSE,
      ];
      $element['body'] = [
        '#type' => 'text_format',
        '#title' => $this->t('Body'),
        '#format' => $item['body']['format'] ?? 'basic_html',
        '#default_value' => $item['body']['value'] ?? '',
        '#description' => $this->t('Ensure the headings used in the Accordion Item Body align with the page content hierarchy. Heading levels in the Body start one level below the Accordion Item Title\'s Heading Level.'),
        '#required' => TRUE,
      ];
      $element['order'] = [
        '#type' => 'number',
        '#title' => $this->t('Assign the order that this accordion item will appear'),
        '#default_value' => $item['order'] ?? 0,
        '#description' => $this->t('Give each accordion item a position number. Use numbers that increase by 10 each time so you can easily add or move items later. You can use both positive and negative numbers. Items with lower numbers will appear higher up.'),
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

  /** Add a stable key without renumbering existing input names. */
  public static function addItem(array &$form, FormStateInterface $form_state): void {
    $keys = $form_state->get('custom_accordion_item_keys');
    $keys[] = 'item_' . bin2hex(random_bytes(6));
    $form_state->set('custom_accordion_item_keys', $keys)->setRebuild();
  }

  /** Remove only the key belonging to the clicked button. */
  public static function removeItem(array &$form, FormStateInterface $form_state): void {
    $key = $form_state->getTriggeringElement()['#delta'];
    $keys = array_values(array_filter($form_state->get('custom_accordion_item_keys'), fn($candidate) => $candidate !== $key));
    $form_state->set('custom_accordion_item_keys', $keys)->setRebuild();
  }

  /** Keep the saved configuration as a list, excluding form action controls. */
  public function massageFormValues(array &$values, array $form, FormStateInterface $form_state) {
    unset($values['add_item']);
    $values['items'] = array_values($values['items'] ?? []);
    foreach ($values['items'] as &$item) {
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
    return $form['config']['plugin_config']['accordion']['items'];
  }

}