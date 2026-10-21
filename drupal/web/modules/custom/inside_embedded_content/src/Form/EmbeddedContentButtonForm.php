<?php

declare(strict_types=1);

namespace Drupal\inside_embedded_content\Form;

use Drupal\Component\Utility\Environment;
use Drupal\Component\Utility\NestedArray;
use Drupal\Core\Entity\EntityForm;
use Drupal\Core\File\FileSystem;
use Drupal\Core\Form\FormStateInterface;
use Drupal\inside_embedded_content\Entity\EmbeddedContentButton;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Embedded content button form.
 */
final class EmbeddedContentButtonForm extends EntityForm {

  /**
   * The file system service.
   *
   * @var \Drupal\Core\File\FileSystem
   */
  protected $fileSystem;

  /**
   * Constructs a new Embedded content button form.
   *
   * @param \Drupal\Core\File\FileSystem $file_system
   *   The file system service.
   */
  public function __construct(FileSystem $file_system) {
    $this->fileSystem = $file_system;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('file_system'),
    );
  }

  /**
   * The embedded content button entity.
   *
   * @var \Drupal\inside_embedded_content\Entity\EmbeddedContentButtonInterface
   */
  protected $entity;

  /**
   * Get upload validators for the icon upload.
   *
   * @return array
   *   The validators.
   */
  protected function getIconUploadValidators(): array {
    return [
      'FileExtension' => [
        'extensions' => 'svg',
      ],
      'FileSizeLimit' => [
        'fileLimit' => Environment::getUploadMaxSize(),
      ],
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function form(array $form, FormStateInterface $form_state): array {
    $form = parent::form($form, $form_state);

    $form['label'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Label'),
      '#description' => $this->t('The label of the content to embed.'),
      '#maxlength' => 255,
      '#default_value' => $this->entity->label(),
      '#required' => TRUE,
    ];

    $form['id'] = [
      '#type' => 'machine_name',
      '#default_value' => $this->entity->id(),
      '#machine_name' => [
        'exists' => [EmbeddedContentButton::class, 'load'],
      ],
      '#disabled' => !$this->entity->isNew(),
    ];

    $form['settings'] = [
      '#tree' => TRUE,
      '#type' => 'container',
      'icon' => [
        '#type' => 'value',
        '#value' => $this->entity->getSetting('icon'),
      ],
      'label_singular' => [
        '#type' => 'textfield',
        '#title' => $this->t('Label singular'),
        '#description' => $this->t('The indefinite singular name of the content to embed.'),
        '#default_value' => $this->entity->getSetting('label_singular'),
        '#required' => TRUE,
      ],
      'submit_button_text' => [
        '#type' => 'textfield',
        '#title' => $this->t('Submit button text'),
        '#description' => $this->t('Text to display for the submit button of the form. Leave empty to use the default Submit button text.'),
        '#default_value' => $this->entity->getSetting('submit_button_text'),
      ],
      'modal_title' => [
        '#type' => 'textfield',
        '#title' => $this->t('Modal title'),
        '#description' => $this->t('Title to display in the modal. Leave empty to use the default modal title.'),
        '#default_value' => $this->entity->getSetting('modal_title'),
      ],
      'conditions' => [
        '#type' => 'textarea',
        '#title' => $this->t('Plugin conditions'),
        '#description' => $this->t("Specify allowed plugins by using their ids. Enter one id per line. The '*' character is a wildcard. Example: inside_embedded_content_example.*."),
        '#default_value' => implode(PHP_EOL, $this->entity->getConditions()),
      ],
      'icon_upload' => [
        '#title' => $this->t('Icon'),
        '#description' => $this->t('The icon will be added as base64 string to the configuration.'),
        '#type' => 'file',
        '#upload_validators' => $this->getIconUploadValidators(),
        '#required' => !$this->entity->getSetting('icon'),
        '#states' => [
          'required' => [
            ':input[name="settings[icon_preview][replace_icon]"]' => ['checked' => TRUE],
          ],
          'visible' => [
            ':input[name="settings[icon_preview][replace_icon]"]' => ['checked' => TRUE],
          ],
        ],
      ],
      'icon_preview' => !$this->entity->isNew() ? [
        '#type' => 'fieldset',
        '#title' => $this->t('Icon preview'),
        'image' => [
          '#theme' => 'image',
          '#attributes' => [
            'width' => '32px',
          ],
          '#uri' => $this->entity->getIconUrl(),
          '#alt' => $this->t('Preview of @label button icon', ['@label' => $this->entity->label()]),
          '#height' => 32,
          '#width' => 32,
        ],
        'replace_icon' => [
          '#type' => 'checkbox',
          '#title' => $this->t('Replace icon'),
        ],
      ] : [],
      'dialog_settings' => [
        '#type' => 'fieldset',
        '#title' => $this->t('Dialog settings'),
        'width' => [
          '#title' => $this->t('Dialog width'),
          '#type' => 'textfield',
          '#default_value' => $this->entity->getSetting('dialog_settings')['width'] ?? EmbeddedContentDialogForm::DEFAULT_DIALOG_WIDTH,
        ],
        'height' => [
          '#title' => $this->t('Dialog height'),
          '#type' => 'textfield',
          '#default_value' => $this->entity->getSetting('dialog_settings')['height'] ?? EmbeddedContentDialogForm::DEFAULT_DIALOG_HEIGHT,
        ],
        'renderer' => [
          '#title' => $this->t('Dialog renderer'),
          '#type' => 'select',
          '#options' => [
            'modal' => $this->t('Modal'),
            'off_canvas' => $this->t('Off canvas'),
          ],
          '#required' => TRUE,
          '#default_value' => $this->entity->getDialogSetting('renderer') ?? EmbeddedContentDialogForm::DEFAULT_DIALOG_RENDERER,
        ],
      ],
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function save(array $form, FormStateInterface $form_state): int {
    $file = file_save_upload('settings', $this->getIconUploadValidators(), 'temporary://', 0);

    $settings = $form_state->getValue('settings');
    $settings = NestedArray::mergeDeep($settings, $this->entity->getSettings());
    unset($settings['icon_upload']);
    unset($settings['icon_preview']);
    if ($file) {
      $svg = file_get_contents($this->fileSystem->realpath($file->getFileUri()));
      $settings['icon'] = $svg;
    }
    $this->entity->set('settings', $settings);
    $result = parent::save($form, $form_state);
    $message_args = ['%label' => $this->entity->label()];
    $this->messenger()->addStatus(
      match ($result) {
        \SAVED_NEW => $this->t('Created new example %label.', $message_args),
        \SAVED_UPDATED => $this->t('Updated example %label.', $message_args),
      }
    );
    $form_state->setRedirectUrl($this->entity->toUrl('collection'));
    $this->entity->save();
    return $result;
  }

}
