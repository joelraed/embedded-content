<?php

namespace Drupal\inside_embedded_content\Controller;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\inside_embedded_content\Entity\EmbeddedContentButtonInterface;
use Symfony\Component\HttpFoundation\Response;

/**
 * Returns the preview for embedded content.
 */
class EmbeddedContentIconController extends ControllerBase {

  /**
   * Create a binary response for the embedded content button icon.
   *
   * @param \Drupal\inside_embedded_content\Entity\EmbeddedContentButtonInterface $inside_embedded_content_button
   *   The embedded content button configuration entity.
   *
   * @return \Symfony\Component\HttpFoundation\BinaryFileResponse
   *   The binary file response.
   */
  public function build(EmbeddedContentButtonInterface $inside_embedded_content_button): Response {
    $svg = $inside_embedded_content_button->getIconSvg();
    return new Response(
          $svg, 200, [
            'Content-Type' => 'image/svg+xml',
            'Content-Transfer-Encoding' => 'File Transfer',
          ]
      );
  }

  /**
   * Create a binary response for the admin css.
   *
   * @param \Drupal\inside_embedded_content\Entity\EmbeddedContentButtonInterface $inside_embedded_content_button
   *   The embedded content button configuration entity.
   *
   * @return \Symfony\Component\HttpFoundation\BinaryFileResponse
   *   The binary file response.
   */
  public function buildAdminCss(EmbeddedContentButtonInterface $inside_embedded_content_button): Response {
    $css = sprintf('.ckeditor5-toolbar-button-insideEmbeddedContent__%s { background-image:url(%s)}', $inside_embedded_content_button->id(), $inside_embedded_content_button->getIconUrl());
    return new Response(
          $css, 200, [
            'Content-Type' => 'text/css',
          ]
      );
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
    return AccessResult::allowedIfHasPermission($account, 'use ' . $inside_embedded_content_button->id() . ' inside embedded content button');
  }

}
