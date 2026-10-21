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
 *   id = "message",
 *   label = @Translation("Message Block"),
 *   description = @Translation("Renders a message."),
 * )
 */
class Message extends EmbeddedContentPluginBase implements EmbeddedContentInterface {

  use StringTranslationTrait;

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration() {
    return [
      'type' => 'info',
      'titlestyle' => 'h2',
      'title' => NULL,
      'body' => [
        'value' => NULL
      ],
      'layout' => 'right'
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function isInline(): bool
  {
    return FALSE;
  }

  /**
   * {@inheritdoc}
   */
  public function build(): array {
    return [
      '#theme' => 'inside_ckeditor5_embedded_content_message',
      '#type' => $this->configuration['type'],
      '#titlestyle' => $this->configuration['titlestyle'],
      '#title' => $this->configuration['title'],
      '#body' => $this->configuration['body'],
      '#layout' => $this->configuration['layout']
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state) {
    $form['type'] = [
      '#type' => 'select',
      '#title' => $this->t('Message Type'),
      '#options' => [
        'error' => $this->t('Error Message'),
        'info' => $this->t('Info Message'),
        'success' => $this->t('Success Message'),
        'warning' => $this->t('Warning Message'),
        'security' => $this->t('Security Message'),
        'generic' => $this->t('Generic Message'),
      ],
      '#default_value' => $this->configuration['type'],
      '#required' => TRUE,
    ];
    $form['layout'] = [
      '#type' => 'radios',
      '#title' => $this->t('Message Block Layout'),
      '#options' => [
        'right' => $this->t('Right Rail'),
        'full' => $this->t('Full Width'),
      ],
      '#default_value' => $this->configuration['layout'] ?? 'right',
      '#required' => FALSE,
      '#states' => [
        'visible' => [
          ':input[name="config[plugin_config][message][type]"]' => ['value' => 'generic'],
        ],
      ],
    ];
    $form['titlestyle'] = [
      '#type' => 'select',
      '#title' => $this->t('Message Block Title Heading Level'),
      '#options' => [
        'h2' => $this->t('H2'),
        'h3' => $this->t('H3'),
        'h4' => $this->t('H4'),
        'h5' => $this->t('H5'),
        'h6' => $this->t('H6'),
      ],
      '#default_value' => $this->configuration['titlestyle'] ?? 'h2',
      '#description' => $this->t('Ensure the Message Block Title Heading Level aligns with the page content hierarchy. The default is H2, suitable if the message is under an H1. Adjust as needed for consistency.'),
      '#required' => TRUE,
    ];
    $form['title'] = [
      '#type' => 'textarea',
      '#rows' => 1,
      '#resizable' => TRUE,
      '#title' => $this->t('Message Block Title'),
      '#default_value' => $this->configuration['title'],
      '#required' => FALSE,
    ];
    $form['body'] = [
      '#type' => 'text_format',
      '#title' => $this->t('Body'),
      '#format' => 'embed_content',
      '#allowed_formats' => ['embed_content'],
      '#default_value' => $this->configuration['body']['value'] ?? '',
      '#required' => FALSE,
    ];
    return $form;
  }

}
