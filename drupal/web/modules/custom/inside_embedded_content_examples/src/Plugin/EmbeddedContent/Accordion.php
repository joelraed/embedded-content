<?php

namespace Drupal\inside_embedded_content_examples\Plugin\EmbeddedContent;

use Drupal\Core\Form\FormStateInterface;
use Drupal\inside_embedded_content\EmbeddedContentPluginBase;

/**
 * Native accessible details elements with stable dynamic form item keys.
 *
 * @EmbeddedContent(
 *   id = "inside.accordion",
 *   label = @Translation("Accordion"),
 * )
 */
final class Accordion extends EmbeddedContentPluginBase {
  public function isInline(): bool { return FALSE; }

  public function defaultConfiguration() { return ['items' => []]; }

  public function build(): array {
    $build = [];
    foreach ($this->configuration['items'] as $key => $item) {
      $build[$key] = [
        '#type' => 'details', '#title' => \Drupal\Component\Utility\Html::escape($item['title'] ?? ''),
        'body' => ['#plain_text' => $item['body'] ?? ''],
      ];
    }
    return $build;
  }

  public function buildConfigurationForm(array $form, FormStateInterface $form_state) {
    $ids = $form_state->get('inside_accordion_ids');
    if ($ids === NULL) {
      $ids = array_keys($this->configuration['items'] ?: ['item_0' => []]);
      $form_state->set('inside_accordion_ids', $ids);
    }
    $form['items'] = ['#type' => 'container', '#tree' => TRUE];
    foreach ($ids as $key) {
      $item = $this->configuration['items'][$key] ?? [];
      $form['items'][$key] = ['#type' => 'fieldset', '#title' => $this->t('Accordion item')];
      $form['items'][$key]['title'] = ['#type' => 'textfield', '#title' => $this->t('Item title'), '#default_value' => $item['title'] ?? '', '#required' => TRUE];
      $form['items'][$key]['body'] = ['#type' => 'textarea', '#title' => $this->t('Item body'), '#default_value' => $item['body'] ?? '', '#required' => TRUE];
      $form['items'][$key]['remove'] = [
        '#type' => 'submit', '#value' => $this->t('Remove item'), '#name' => 'remove_' . $key,
        '#item_key' => $key, '#limit_validation_errors' => [],
        '#submit' => [[static::class, 'removeItem']],
        '#ajax' => ['callback' => [static::class, 'rebuild'], 'wrapper' => 'inside-embedded-content-form-' . $form['#button']->id()],
      ];
    }
    $form['add'] = [
      '#type' => 'submit', '#value' => $this->t('Add item'), '#name' => 'inside_accordion_add',
      '#limit_validation_errors' => [], '#submit' => [[static::class, 'addItem']],
      '#ajax' => ['callback' => [static::class, 'rebuild'], 'wrapper' => 'inside-embedded-content-form-' . $form['#button']->id()],
    ];
    return $form;
  }

  public static function addItem(array &$form, FormStateInterface $form_state) {
    $ids = $form_state->get('inside_accordion_ids');
    $ids[] = 'item_' . bin2hex(random_bytes(6));
    $form_state->set('inside_accordion_ids', $ids)->setRebuild();
  }

  public static function removeItem(array &$form, FormStateInterface $form_state) {
    $key = $form_state->getTriggeringElement()['#item_key'];
    $form_state->set('inside_accordion_ids', array_values(array_diff($form_state->get('inside_accordion_ids'), [$key])))->setRebuild();
  }

  public static function rebuild(array &$form, FormStateInterface $form_state) { return $form; }

  public function validateConfigurationForm(array &$form, FormStateInterface $form_state) {
    if (!$form_state->get('inside_accordion_ids')) {
      $form_state->setError($form, $this->t('Add at least one accordion item.'));
    }
  }

  public function massageFormValues(array &$values, array $form, FormStateInterface $form_state) {
    unset($values['add']);
    if (isset($values['items'])) {
      foreach ($values['items'] as &$item) { unset($item['remove']); }
    }
  }
}
