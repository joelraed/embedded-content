<?php

namespace Drupal\inside_ckeditor5_embedded_content\Plugin\EmbeddedContent;

use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\embedded_content\EmbeddedContentInterface;
use Drupal\embedded_content\EmbeddedContentPluginBase;

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

    if (isset($this->configuration['items'])) {
      $items = $this->configuration['items'];
    }
    elseif (isset($this->configuration['accordion']['items'])) {
      $items = $this->configuration['accordion']['items'];
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

    foreach ($items as $delta => $item) {
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
    return $form['config']['plugin_config']['accordion']['items'];
  }

}