<?php

namespace Drupal\inside_embedded_content\Controller;

use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\OpenOffCanvasDialogCommand;
use Drupal\Core\Controller\ControllerBase;
use Drupal\filter\Entity\FilterFormat;
use Drupal\inside_embedded_content\Entity\EmbeddedContentButtonInterface;
use Drupal\inside_embedded_content\Form\EmbeddedContentDialogForm;

/** Always opens the component form using Drupal's non-modal off-canvas command. */
final class EmbeddedContentDialogController extends ControllerBase {
  public function open(EmbeddedContentButtonInterface $inside_embedded_content_button, FilterFormat $filter_format) {
    $form = $this->formBuilder()->getForm(EmbeddedContentDialogForm::class, $inside_embedded_content_button, $filter_format);
    $width = (int) $inside_embedded_content_button->getDialogSetting('width');
    $response = new AjaxResponse();
    $response->addCommand(new OpenOffCanvasDialogCommand(
      $inside_embedded_content_button->getModalTitle() ?: $this->t('Configure @label', ['@label' => $inside_embedded_content_button->getSingularLabel()]),
      $form,
      ['width' => max(300, min(1000, $width ?: 480)), 'classes' => ['ui-dialog' => 'inside-embedded-content-panel']],
    ));
    return $response;
  }
}
