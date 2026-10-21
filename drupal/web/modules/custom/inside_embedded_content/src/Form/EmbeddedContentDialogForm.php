<?php

namespace Drupal\inside_embedded_content\Form;

use Drupal\Component\Serialization\Json;
use Drupal\Core\Access\AccessResult;
use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Form\SubformState;
use Drupal\Core\Session\AccountInterface;
use Drupal\filter\Entity\FilterFormat;
use Drupal\inside_embedded_content\Ajax\SaveEmbeddedContentCommand;
use Drupal\inside_embedded_content\EmbeddedContentPluginManager;
use Drupal\inside_embedded_content\Entity\EmbeddedContentButtonInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/** Form API configuration editor, displayed by core's off-canvas infrastructure. */
class EmbeddedContentDialogForm extends FormBase {
  public const DEFAULT_DIALOG_WIDTH = '480';
  public const DEFAULT_DIALOG_HEIGHT = 'auto';
  public const DEFAULT_DIALOG_RENDERER = 'off_canvas';

  public function __construct(protected EmbeddedContentPluginManager $pluginManager) {}

  public static function create(ContainerInterface $container) {
    return new static($container->get('plugin.manager.inside_embedded_content'));
  }

  public function getFormId() {
    return 'inside_embedded_content_dialog_form';
  }

  public function checkAccess(AccountInterface $account, EmbeddedContentButtonInterface $inside_embedded_content_button, FilterFormat $filter_format) {
    return AccessResult::allowedIf($inside_embedded_content_button->status())
      ->andIf(AccessResult::allowedIfHasPermission($account, 'use ' . $inside_embedded_content_button->id() . ' inside embedded content button'))
      ->andIf($filter_format->access('use', $account, TRUE))
      ->andIf(AccessResult::allowedIf($filter_format->filters()->has('inside_embedded_content') && $filter_format->filters('inside_embedded_content')->status))
      ->addCacheableDependency($inside_embedded_content_button)
      ->addCacheableDependency($filter_format);
  }

  public function buildForm(array $form, FormStateInterface $form_state, ?EmbeddedContentButtonInterface $inside_embedded_content_button = NULL, ?FilterFormat $filter_format = NULL) {
    $button = $inside_embedded_content_button;
    $payload = $this->getRequest()->getPayload();
    if (!$form_state->has('initial_config')) {
      $decoded = Json::decode($payload->get('plugin_config') ?? '{}');
      $form_state->set('initial_config', is_array($decoded) ? $decoded : []);
      $form_state->set('initial_plugin', $payload->get('plugin_id'));
    }
    foreach (['editor_id', 'request_id'] as $key) {
      $form[$key] = ['#type' => 'hidden', '#default_value' => $payload->get($key) ?? ''];
    }
    $form['button'] = ['#type' => 'value', '#value' => $button];
    $wrapper = 'inside-embedded-content-form-' . $button->id();
    $form['#prefix'] = '<div id="' . $wrapper . '">';
    $form['#suffix'] = '</div>';
    $form['#attached']['library'][] = 'inside_embedded_content/inside_embedded_content';
    $form['messages'] = ['#type' => 'status_messages'];
    $definitions = array_filter($this->pluginManager->getDefinitions(), fn($definition, $id) => $button->meetsCondition($id), ARRAY_FILTER_USE_BOTH);
    if (!$definitions) {
      $form['warning'] = ['#markup' => $this->t('No embedded content plugins are available for this button.')];
      return $form;
    }
    $plugin_id = $form_state->getUserInput()['config']['plugin_id'] ?? $form_state->getValue(['config', 'plugin_id']) ?? $form_state->get('initial_plugin');
    if (!$plugin_id && count($definitions) === 1) {
      $plugin_id = array_key_first($definitions);
    }
    $form['config'] = ['#type' => 'container', '#tree' => TRUE, '#attributes' => ['data-inside-plugin' => $plugin_id ?: '']];
    $form['config']['plugin_id'] = [
      '#type' => 'select', '#title' => $this->t('Embedded content'),
      '#empty_option' => $this->t('- Select a type -'),
      '#options' => array_map(fn($definition) => $definition['label'], $definitions),
      '#default_value' => $plugin_id, '#required' => TRUE,
      '#limit_validation_errors' => [],
      '#ajax' => ['callback' => '::rebuildForm', 'wrapper' => $wrapper],
    ];
    if ($plugin_id && isset($definitions[$plugin_id])) {
      $configuration = $form_state->getUserInput()['config']['plugin_config'][$plugin_id] ?? $form_state->getValue(['config', 'plugin_config', $plugin_id]);
      if (!is_array($configuration)) {
        $configuration = $plugin_id === $form_state->get('initial_plugin') ? $form_state->get('initial_config') : [];
      }
      $instance = $this->pluginManager->createInstance($plugin_id, $configuration);
      $subform = ['#type' => 'container', '#button' => $button, '#tree' => TRUE];
      $form['config']['plugin_config'][$plugin_id] = $subform;
      $subform_state = SubformState::createForSubform($form['config']['plugin_config'][$plugin_id], $form, $form_state);
      $form['config']['plugin_config'][$plugin_id] = $instance->buildConfigurationForm($form['config']['plugin_config'][$plugin_id], $subform_state);
    }
    $form['actions'] = ['#type' => 'actions'];
    $form['actions']['submit'] = [
      '#type' => 'submit', '#inside_save' => TRUE, '#value' => $button->getSubmitButtonText() ?: $this->t('Embed'),
      '#ajax' => ['callback' => '::ajaxSubmitForm', 'wrapper' => $wrapper, 'disable-refocus' => TRUE],
    ];
    return $form;
  }

  public function rebuildForm(array &$form, FormStateInterface $form_state): array {
    return $form;
  }

  public function validateForm(array &$form, FormStateInterface $form_state) {
    if (empty($form_state->getTriggeringElement()['#inside_save'])) { return; }
    $id = $form_state->getValue(['config', 'plugin_id']);
    $button = $form_state->getValue('button');
    if (!$id || !$this->pluginManager->hasDefinition($id) || !$button->meetsCondition($id)) {
      $form_state->setErrorByName('config][plugin_id', $this->t('Select an available embedded content type.'));
      return;
    }
    $values = $form_state->getValue(['config', 'plugin_config', $id]) ?? [];
    $instance = $this->pluginManager->createInstance($id, $values);
    $subform =& $form['config']['plugin_config'][$id];
    $subform_state = SubformState::createForSubform($subform, $form, $form_state);
    $instance->validateConfigurationForm($subform, $subform_state);
  }

  public function submitForm(array &$form, FormStateInterface $form_state) {}

  public function ajaxSubmitForm(array &$form, FormStateInterface $form_state) {
    if ($form_state->hasAnyErrors()) {
      return $form;
    }
    $id = $form_state->getValue(['config', 'plugin_id']);
    $values = $form_state->getValue(['config', 'plugin_config', $id]) ?? [];
    $instance = $this->pluginManager->createInstance($id, $values);
    $instance->massageFormValues($values, $form, $form_state);
    $response = new AjaxResponse();
    // The request-scoped JS command closes the tray only after an accepted save.
    $response->addCommand(new SaveEmbeddedContentCommand(
      $form_state->getValue('editor_id'), $form_state->getValue('request_id'),
      [
        'element' => $instance->isInline() ? 'insideEmbeddedContentInline' : 'insideEmbeddedContent',
        'attributes' => [
          'data-plugin-id' => $id,
          'data-plugin-config' => Json::encode($values),
          'data-button-id' => $form_state->getValue('button')->id(),
        ],
      ],
    ));
    return $response;
  }
}
