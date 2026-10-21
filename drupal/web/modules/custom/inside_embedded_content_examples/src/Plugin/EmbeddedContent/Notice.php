<?php

namespace Drupal\inside_embedded_content_examples\Plugin\EmbeddedContent;

use Drupal\Core\Form\FormStateInterface;
use Drupal\inside_embedded_content\EmbeddedContentPluginBase;

/**
 * An AJAX-dependent form with nested settings and custom validation.
 *
 * @EmbeddedContent(
 *   id = "inside.notice",
 *   label = @Translation("Notice with AJAX settings"),
 * )
 */
final class Notice extends EmbeddedContentPluginBase {
  public function isInline(): bool { return FALSE; }
  public function defaultConfiguration() { return ['title' => '', 'mode' => 'simple', 'details' => ['message' => '']]; }
  public function build(): array {
    return [
      '#type' => 'container', '#attributes' => ['class' => ['inside-notice']],
      'title' => ['#type' => 'html_tag', '#tag' => 'strong', '#value' => \Drupal\Component\Utility\Html::escape($this->configuration['title'])],
      'message' => ['#plain_text' => $this->configuration['mode'] === 'detailed' ? ($this->configuration['details']['message'] ?? '') : ''],
    ];
  }
  public function buildConfigurationForm(array $form, FormStateInterface $form_state) {
    $mode = $form_state->getUserInput()['config']['plugin_config']['inside.notice']['mode'] ?? $this->configuration['mode'];
    $form['title'] = ['#type' => 'textfield', '#title' => $this->t('Notice title'), '#required' => TRUE, '#default_value' => $this->configuration['title']];
    $form['mode'] = [
      '#type' => 'select', '#title' => $this->t('Display mode'),
      '#options' => ['simple' => $this->t('Simple'), 'detailed' => $this->t('Detailed')],
      '#default_value' => $mode, '#limit_validation_errors' => [],
      '#ajax' => ['callback' => [static::class, 'rebuild'], 'wrapper' => 'inside-embedded-content-form-' . $form['#button']->id()],
    ];
    $form['details'] = ['#type' => 'container', '#tree' => TRUE];
    if ($mode === 'detailed') {
      $form['details']['message'] = ['#type' => 'textarea', '#title' => $this->t('Detailed message'), '#default_value' => $this->configuration['details']['message'], '#required' => TRUE];
    }
    return $form;
  }
  public static function rebuild(array &$form, FormStateInterface $form_state) { return $form; }
  public function validateConfigurationForm(array &$form, FormStateInterface $form_state) {
    if (mb_strlen(trim($form_state->getValue('title') ?? '')) < 3) {
      $form_state->setErrorByName('title', $this->t('The notice title must contain at least three characters.'));
    }
  }
}
