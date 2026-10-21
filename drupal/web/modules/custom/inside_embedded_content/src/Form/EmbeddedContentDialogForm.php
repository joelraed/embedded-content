<?php

namespace Drupal\inside_embedded_content\Form;

use Drupal\Component\Serialization\Json;
use Drupal\Component\Utility\Xss;
use Drupal\Core\Access\AccessResult;
use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\CloseDialogCommand;
use Drupal\Core\Ajax\CloseModalDialogCommand;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Form\SubformState;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\editor\Ajax\EditorDialogSave;
use Drupal\inside_embedded_content\EmbeddedContentPluginManager;
use Drupal\inside_embedded_content\Entity\EmbeddedContentButtonInterface;
use Drupal\filter\Entity\FilterFormat;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Ckeditor dialog form to insert webform submission results in text.
 */
class EmbeddedContentDialogForm extends FormBase {

  const DEFAULT_DIALOG_WIDTH = '800px';

  const DEFAULT_DIALOG_HEIGHT = 'auto';

  const DEFAULT_DIALOG_RENDERER = 'modal';

  /**
   * The embedded content plugin manager.
   *
   * @var \Drupal\inside_embedded_content\EmbeddedContentPluginManager
   */
  protected $insideEmbeddedContentPluginManager;

  /**
   * The ajax wrapper id to use for re-rendering the form.
   *
   * @var string
   */
  protected $ajaxWrapper = 'inside-embedded-content-dialog-form-wrapper';

  /**
   * The modal selector.
   *
   * @var string
   */
  protected $modalSelector = '';

  /**
   * The form constructor.
   *
   * @param \Drupal\inside_embedded_content\EmbeddedContentPluginManager $inside_embedded_content_plugin_manager
   *   The embedded content plugin manager.
   */
  public function __construct(EmbeddedContentPluginManager $inside_embedded_content_plugin_manager) {
    $this->insideEmbeddedContentPluginManager = $inside_embedded_content_plugin_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('plugin.manager.inside_embedded_content')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'inside_embedded_content_dialog_form';
  }

  /**
   * Access callback for embedded content.
   *
   * @param \Drupal\Core\Session\AccountProxyInterface $account
   *   The user account.
   * @param \Drupal\inside_embedded_content\Entity\EmbeddedContentButtonInterface $inside_embedded_content_button
   *   The embedded content configuration entity.
   *
   * @return \Drupal\Core\Access\AccessResultInterface
   *   The access result.
   */
  public function checkAccess(AccountProxyInterface $account, EmbeddedContentButtonInterface $inside_embedded_content_button) {
    return AccessResult::allowedIfHasPermission($account, 'use ' . $inside_embedded_content_button->id() . ' embedded content button');
  }

  /**
   * Get the available embedded content plugins filtered by the button settings.
   *
   * @return \Drupal\Component\Plugin\Definition\PluginDefinitionInterface[]
   *   The plugin definitions.
   */
  protected function getAvailablePluginDefinitions(EmbeddedContentButtonInterface $inside_embedded_content_button): array {
    $definitions = $this->insideEmbeddedContentPluginManager->getDefinitions();
    foreach ($definitions as $id => $definition) {
      if (!$inside_embedded_content_button->meetsCondition($id)) {
        unset($definitions[$id]);
      }
    }
    return $definitions;
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, ?EmbeddedContentButtonInterface $inside_embedded_content_button = NULL, ?FilterFormat $filter_format = NULL) {
    $payload = $this->getRequest()->getPayload();

    $editor_id = $payload->get('editor_id') ?? $form_state->getValue('editor_id');
    $form['editor_id'] = [
      '#type' => 'hidden',
      '#default_value' => $editor_id ?? '',
    ];

    $this->ajaxWrapper .= '-' . $inside_embedded_content_button->id();
    $this->modalSelector = '#inside-embedded-content-dialog-form-' . $editor_id;

    $form['#modal_selector'] = $this->modalSelector;

    $plugin_config = $form_state->getValue(['config', 'plugin_config']) ?? Json::decode(Xss::filter($payload->get('plugin_config') ?? '')) ?? $form_state->getUserInput()['config']['plugin_config'] ?? [];
    $plugin_id = $form_state->getValue(['config', 'plugin_id']) ?? $payload->get('plugin_id') ?? $form_state->getUserInput()['config']['plugin_id'] ?? NULL;

    $definitions = $this->getAvailablePluginDefinitions($inside_embedded_content_button);

    if (!$definitions) {
      $form['warning'] = [
        '#type' => 'markup',
        '#markup' => $this->t('No embedded content plugins were found. Enable the examples module to see some examples or revise if the filter conditions in the button configration are met.'),
      ];
      return $form;
    }
    if ($plugin_id && !isset($definitions[$plugin_id])) {
      $form['warning'] = [
        '#type' => 'markup',
        '#markup' => $this->t('The plugin used for this embedded content is not enabled. Please revise the button settings.'),
      ];
      return $form;
    }
    if (count($definitions) === 1 && !$plugin_id) {
      $plugin_id = array_key_first($definitions);
    }

    $form['button'] = [
      '#type' => 'value',
      '#value' => $inside_embedded_content_button,
    ];

    $update_button = 'update_' . $editor_id;

    $form['config'] = [
      '#type' => 'container',
      '#tree' => TRUE,
      '#attributes' => [
        'id' => $this->ajaxWrapper,
      ],

      'plugin_id' => count($definitions) > 1 ? [
        '#type' => 'select',
        '#title' => $this->t('Embedded content'),
        '#empty_option' => $this->t('- Select a type -'),
        '#default_value' => $plugin_id,
        '#options' => array_map(
          function ($definition) {
            return $definition['label'];
          }, $definitions
        ),
        '#required' => TRUE,
        '#ajax' => [
          'callback' => [$this, 'updateFormElement'],
          'event' => 'change',
          'wrapper' => $this->ajaxWrapper,

        ],
      ] : [
        '#type' => 'value',
        '#value' => $plugin_id,
      ],
    ];
    if ($plugin_id) {
      /**
       * @var \Drupal\inside_embedded_content\EmbeddedContentInterface $instance
       */
      try {
        $instance = $this->insideEmbeddedContentPluginManager->createInstance($plugin_id, $plugin_config);
        $form['instance'] = [
          '#type' => 'value',
          '#value' => $instance,
        ];

        // Add the button so it can be used by the plugin.
        $form['config']['plugin_config'][$plugin_id] = [
          '#button' => $inside_embedded_content_button,
        ];
        $subform_state = SubformState::createForSubform($form['config']['plugin_config'][$plugin_id], $form, $form_state);

        // $subform_state->setLimitValidationErrors([]);
        $form['config']['plugin_config'][$plugin_id] = $instance->buildConfigurationForm($form['config']['plugin_config'][$plugin_id], $subform_state);
      }
      catch (\Exception $exception) {
        $this->messenger()->addError($exception->getMessage());
        $form['config']['messages'] = [
          '#type' => 'status_messages',
        ];
      }
    }

    $form['actions'] = [
      '#type' => 'actions',
      'submit' => [
        '#type' => 'button',
        '#value' => $inside_embedded_content_button->getSubmitButtonText() ?? $this->t('Embed'),
        '#ajax' => [
          'callback' => [$this, 'ajaxSubmitForm'],
          'wrapper' => $this->ajaxWrapper,
          'disable-refocus' => TRUE,
        ],
      ],
    ];

    return $form;
  }

  /**
   * Return the title for the dialog.
   *
   * @param \Drupal\inside_embedded_content\Entity\EmbeddedContentButtonInterface|null $inside_embedded_content_button
   *   The embedded content button.
   * @param \Drupal\filter\Entity\FilterFormat|null $filter_format
   *   The filter format.
   */
  public function title(?EmbeddedContentButtonInterface $inside_embedded_content_button = NULL, ?FilterFormat $filter_format = NULL): TranslatableMarkup|string {
    return $inside_embedded_content_button->getModalTitle() ?? $this->t('Embed @label', ['@label' => $inside_embedded_content_button->getSingularLabel()]);
  }

  /**
   * Update the form after selecting a plugin type.
   *
   * @param array $form
   *   The form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   *
   * @return array
   *   The form element for webform elements.
   */
  public function updateFormElement(array $form, FormStateInterface $form_state): array {
    return $form['config'];
  }

  /**
   * Ajax submit callback to insert or replace the html in ckeditor.
   *
   * @param array $form
   *   The form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   *
   * @return \Drupal\Core\Ajax\AjaxResponse|array
   *   Ajax response for injecting html in ckeditor.
   */
  public function ajaxSubmitForm(array &$form, FormStateInterface $form_state) {
    if ($form_state->getErrors()) {
      return $form['config'];
    }
    $plugin_id = $form_state->getValue(['config', 'plugin_id']);

    $plugin_config = $form_state->getValue(
      [
        'config',
        'plugin_config',
        $plugin_id,
      ]
    ) ?? [];

    /**
     * @var \Drupal\inside_embedded_content\EmbeddedContentPluginManager $pluginManager
     */
    $pluginManager = \Drupal::service('plugin.manager.inside_embedded_content');

    /**
     * @var \Drupal\inside_embedded_content\EmbeddedContentInterface $instance
     */
    $instance = $pluginManager->createInstance($plugin_id, $plugin_config);
    $instance->massageFormValues($plugin_config, $form, $form_state);
    $button = $form_state->getValue('button');
    $response = new AjaxResponse();

    $response->addCommand(
      new EditorDialogSave(
        [
          'element' => $instance->isInline() ? 'insideEmbeddedContentInline' : 'insideEmbeddedContent',
          'attributes' => [
            'data-plugin-id' => $plugin_id,
            'data-plugin-config' => Json::encode($plugin_config ?? []),
            'data-button-id' => $form_state->getValue('button')->id(),
          ],
        ],
        $this->modalSelector,
      )
    );

    if ($button->getDialogSetting('renderer') === 'off_canvas') {
      $response->addCommand(new CloseDialogCommand('#drupal-off-canvas'));
    }
    else {
      $response->addCommand(new CloseModalDialogCommand(FALSE, $this->modalSelector));
    }
    return $response;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    /**
     * @var \Drupal\inside_embedded_content\EmbeddedContentInterface $instance
     */
    $plugin_id = $form_state->getValue(['config', 'plugin_id']);
    if ($plugin_id) {
      try {
        $instance = $this->insideEmbeddedContentPluginManager->createInstance(
          $plugin_id, $form_state->getValue(
          [
            'config',
            'plugin_config',
          ]
        ) ?? []
        );
        $subform = $form['config']['plugin_config'][$plugin_id] ?? [];
        $subform_state = SubformState::createForSubform($subform, $form, $form_state);
        $instance->validateConfigurationForm($subform, $subform_state);
        if ($subform_state->getErrors()) {
          return $subform;
        }
        $config = $form_state->getValue('config');
        $form_state->setValue('config', $config);
      }
      catch (\Exception $exception) {
        $form_state->setValue('config', []);
      }
    }
    else {
      $form_state->setValue('config', []);
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    // Don't do anything.
  }

}
