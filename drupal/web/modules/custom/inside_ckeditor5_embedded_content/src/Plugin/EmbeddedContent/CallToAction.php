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
 *   id = "calltoaction",
 *   label = @Translation("Call To Action Button"),
 *   description = @Translation("Renders a Call To Action Button."),
 * )
 */
class CallToAction extends EmbeddedContentPluginBase implements EmbeddedContentInterface {

  use StringTranslationTrait;
  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration() {
    return [
      'label' => NULL,
      'type' => NULL,
      'destination_uri' => NULL, 
      'icon' => NULL,
      'tooltip' => NULL,
      'alignment' => NULL,
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
      '#theme' => 'inside_ckeditor5_embedded_content_calltoaction',
      '#label' => $this->configuration['label'],
      '#type' => $this->configuration['type'],
      '#destination_uri' => $this->configuration['destination_uri'],
      '#icon' => $this->configuration['icon'],
      '#tooltip' => $this->configuration['tooltip'],
      '#alignment' => $this->configuration['alignment'],
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state) {
    $form['label'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Button Label/Text'),      
      '#default_value' => $this->configuration['label'],
      '#required' => TRUE,
    ];

    $form['type'] = [
      '#type' => 'select',
      '#title' => $this->t('Destination Type'),
      '#options' => [
        'internal' => $this->t('Internal Page/Resource'),
        'external' => $this->t('External URL'),
        'email' => $this->t('Email address (mailto:)'),
        'file' => $this->t('Document/File Link'),
      ],
      '#default_value' => $this->configuration['type'] ?? 'internal',
      '#required' => TRUE,
    ];
    $form['destination_uri'] = [
      '#type' => 'linkit',
      '#title' => $this->t('Destination URL, Email Address, or System Action'),
      '#description' => $this->t('Start typing the title of a page/file, or type an external URL (e.g., https://example.com).'),
      '#default_value' => $this->configuration['destination_uri'],
      '#required' => TRUE,
      '#autocomplete_route_name' => 'linkit.autocomplete',
      '#autocomplete_route_parameters' => [
        'linkit_profile_id' => 'admin',
      ],
    ];

    $form['icon'] = [
      '#type' => 'radios',
      '#title' => $this->t('Icon'),
      '#options' => [
        'yes' => $this->t('Yes'),
        'no' => $this->t('No'),
      ],
      '#default_value' => $this->configuration['icon'] ?? 'yes',
    ];

    $form['tooltip'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Optional tooltip/help text'),
      '#default_value' => $this->configuration['tooltip'],
    ];

    $form['alignment'] = [
      '#type' => 'select',
      '#title' => $this->t('Alignment Options'),
      '#options' => [
        'break' => $this->t('Break Text'),
        'right' => $this->t('Align right and wrap text'),
        'left' => $this->t('Align left and wrap text'),
      ],
      '#default_value' => $this->configuration['alignment'] ?? 'break',
    ];
    return $form;
  }
}