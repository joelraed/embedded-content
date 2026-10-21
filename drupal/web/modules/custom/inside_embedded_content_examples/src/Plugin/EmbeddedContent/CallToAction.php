<?php

namespace Drupal\inside_embedded_content_examples\Plugin\EmbeddedContent;

use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\inside_embedded_content\EmbeddedContentInterface;
use Drupal\inside_embedded_content\EmbeddedContentPluginBase;

/**
 * Plugin iframes.
 *
 * @EmbeddedContent(
 *   id = "example.call_to_action",
 *   label = @Translation("Call to action"),
 *   description = @Translation("Renders a call to action."),
 * )
 */
class CallToAction extends EmbeddedContentPluginBase implements EmbeddedContentInterface {

  use StringTranslationTrait;

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration() {
    return [
      'url' => NULL,
      'text' => NULL,
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function build(): array {
    $url = $this->configuration['url'] ?? '';
    if (!preg_match('@^https?://@i', $url)) {
      return ['#plain_text' => $this->configuration['text'] ?? ''];
    }
    return [
      '#type' => 'link',
      '#title' => $this->configuration['text'] ?? '',
      '#url' => \Drupal\Core\Url::fromUri($url),
      '#attributes' => ['class' => ['inside-call-to-action']],
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state) {
    $form['url'] = [
      '#type' => 'url',
      '#title' => $this->t('Url'),
      '#default_value' => $this->configuration['url'],
      '#required' => TRUE,
    ];
    $form['text'] = [
      '#type' => 'textfield',
      '#title' => $this->t('text'),
      '#default_value' => $this->configuration['text'],
      '#required' => TRUE,
    ];
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function isInline(): bool {
    return FALSE;
  }

}
